"""
Large Dataset Integration Module for H3 Framework

Optimized strategies for processing millions of records with H3.
"""

import h3
import pandas as pd
import numpy as np
from typing import List, Dict, Iterator, Tuple, Optional
import sqlite3
from pathlib import Path
import json
import time
from concurrent.futures import ThreadPoolExecutor, ProcessPoolExecutor
import multiprocessing as mp
from functools import partial
import dask.dataframe as dd
from data_processor import H3DataProcessor

class LargeDatasetProcessor(H3DataProcessor):
    """Specialized processor for large datasets with H3 optimization."""
    
    def __init__(self, db_path: Optional[str] = None, batch_size: int = 10000):
        super().__init__(db_path)
        self.batch_size = batch_size
        self.cpu_count = mp.cpu_count()
    
    def process_large_csv_streaming(self, file_path: str, lat_col: str, lng_col: str, 
                                  resolution: int = 8, output_path: Optional[str] = None) -> Iterator[pd.DataFrame]:
        """
        Stream process large CSV files without loading entire dataset into memory.
        
        Args:
            file_path (str): Path to large CSV file
            lat_col (str): Latitude column name
            lng_col (str): Longitude column name  
            resolution (int): H3 resolution (7-9 recommended for large datasets)
            output_path (Optional[str]): Path to save processed chunks
            
        Yields:
            pd.DataFrame: Processed chunks with H3 indices
        """
        print(f"Processing large CSV: {file_path}")
        print(f"Using resolution {resolution} (avg hexagon edge: {h3.edge_length(resolution, 'km'):.1f} km)")
        
        chunk_count = 0
        total_rows = 0
        
        # Stream process CSV in chunks
        for chunk in pd.read_csv(file_path, chunksize=self.batch_size):
            chunk_count += 1
            batch_start = time.time()
            
            # Add H3 indices to chunk
            chunk = self._add_h3_to_chunk(chunk, lat_col, lng_col, resolution)
            
            # Optional: Save processed chunk
            if output_path:
                chunk_file = f"{output_path}_chunk_{chunk_count:04d}.parquet"
                chunk.to_parquet(chunk_file, index=False)
            
            total_rows += len(chunk)
            batch_time = time.time() - batch_start
            
            print(f"Processed chunk {chunk_count}: {len(chunk):,} rows ({batch_time:.2f}s, {len(chunk)/batch_time:.0f} rows/sec)")
            
            yield chunk
        
        print(f"Total processed: {total_rows:,} rows in {chunk_count} chunks")
    
    def _add_h3_to_chunk(self, df: pd.DataFrame, lat_col: str, lng_col: str, resolution: int) -> pd.DataFrame:
        """Add H3 indices to DataFrame chunk efficiently."""
        # Vectorized H3 conversion using numpy
        lats = df[lat_col].values
        lngs = df[lng_col].values
        
        # Filter out invalid coordinates
        valid_mask = (~pd.isna(lats)) & (~pd.isna(lngs)) & \
                    (lats >= -90) & (lats <= 90) & \
                    (lngs >= -180) & (lngs <= 180)
        
        h3_indices = np.full(len(df), '', dtype=object)
        
        if valid_mask.any():
            valid_lats = lats[valid_mask]
            valid_lngs = lngs[valid_mask]
            
            # Vectorized H3 conversion
            valid_h3 = [h3.latlng_to_cell(lat, lng, resolution) 
                       for lat, lng in zip(valid_lats, valid_lngs)]
            h3_indices[valid_mask] = valid_h3
        
        df['h3_index'] = h3_indices
        df['h3_resolution'] = resolution
        
        return df
    
    def parallel_process_dataset(self, file_path: str, lat_col: str, lng_col: str,
                               resolution: int = 8, n_workers: Optional[int] = None) -> pd.DataFrame:
        """
        Process large dataset using parallel processing.
        
        Args:
            file_path (str): Path to dataset
            lat_col (str): Latitude column name
            lng_col (str): Longitude column name
            resolution (int): H3 resolution
            n_workers (Optional[int]): Number of parallel workers
            
        Returns:
            pd.DataFrame: Processed dataset with H3 indices
        """
        if n_workers is None:
            n_workers = min(self.cpu_count, 8)  # Cap at 8 to avoid memory issues
        
        print(f"Using {n_workers} parallel workers")
        
        # Read dataset in chunks
        chunks = []
        for chunk in pd.read_csv(file_path, chunksize=self.batch_size):
            chunks.append(chunk)
        
        # Process chunks in parallel
        process_func = partial(self._add_h3_to_chunk, 
                             lat_col=lat_col, lng_col=lng_col, resolution=resolution)
        
        with ProcessPoolExecutor(max_workers=n_workers) as executor:
            processed_chunks = list(executor.map(process_func, chunks))
        
        # Combine results
        result = pd.concat(processed_chunks, ignore_index=True)
        print(f"Processed {len(result):,} total rows")
        
        return result
    
    def aggregate_to_h3_grid(self, df: pd.DataFrame, value_cols: List[str], 
                           agg_functions: Dict[str, str] = None) -> pd.DataFrame:
        """
        Aggregate large dataset to H3 hexagonal grid.
        
        Args:
            df (pd.DataFrame): Dataset with h3_index column
            value_cols (List[str]): Columns to aggregate
            agg_functions (Dict[str, str]): Aggregation functions per column
            
        Returns:
            pd.DataFrame: Aggregated data by H3 hexagon
        """
        if agg_functions is None:
            agg_functions = {col: 'mean' for col in value_cols}
        
        print(f"Aggregating {len(df):,} records to H3 grid...")
        
        # Group by H3 index and aggregate
        agg_dict = {}
        for col in value_cols:
            if col in df.columns:
                func = agg_functions.get(col, 'mean')
                agg_dict[col] = func
        
        # Add count
        agg_dict['record_count'] = 'count'
        
        # Perform aggregation
        result = df.groupby('h3_index').agg(agg_dict).reset_index()
        
        # Add H3 coordinates
        h3_coords = [h3.cell_to_latlng(idx) for idx in result['h3_index']]
        result['lat'] = [coord[0] for coord in h3_coords]
        result['lng'] = [coord[1] for coord in h3_coords]
        result['resolution'] = df['h3_resolution'].iloc[0]
        
        print(f"Aggregated to {len(result):,} H3 hexagons")
        
        return result
    
    def create_h3_spatial_index(self, df: pd.DataFrame) -> Dict[str, List[int]]:
        """
        Create spatial index for fast regional queries.
        
        Args:
            df (pd.DataFrame): Dataset with h3_index column
            
        Returns:
            Dict[str, List[int]]: Spatial index mapping H3 cells to record indices
        """
        print("Creating H3 spatial index...")
        
        spatial_index = {}
        for idx, h3_index in enumerate(df['h3_index']):
            if h3_index and h3_index != '':
                if h3_index not in spatial_index:
                    spatial_index[h3_index] = []
                spatial_index[h3_index].append(idx)
        
        print(f"Created spatial index with {len(spatial_index):,} H3 cells")
        return spatial_index
    
    def query_by_bounding_box(self, df: pd.DataFrame, 
                            min_lat: float, max_lat: float,
                            min_lng: float, max_lng: float,
                            resolution: int = 8) -> pd.DataFrame:
        """
        Efficiently query large dataset within bounding box using H3.
        
        Args:
            df (pd.DataFrame): Dataset with H3 indices
            min_lat, max_lat, min_lng, max_lng: Bounding box coordinates
            resolution (int): H3 resolution for query
            
        Returns:
            pd.DataFrame: Filtered dataset within bounding box
        """
        # Get all H3 cells that intersect with bounding box
        bbox_cells = set()
        
        # Sample points within bounding box to get H3 cells
        lat_step = (max_lat - min_lat) / 20
        lng_step = (max_lng - min_lng) / 20
        
        for lat in np.arange(min_lat, max_lat + lat_step, lat_step):
            for lng in np.arange(min_lng, max_lng + lng_step, lng_step):
                if -90 <= lat <= 90 and -180 <= lng <= 180:
                    cell = h3.latlng_to_cell(lat, lng, resolution)
                    bbox_cells.add(cell)
                    # Add neighbors to ensure complete coverage
                    neighbors = h3.grid_ring(cell, 1)
                    bbox_cells.update(neighbors)
        
        # Filter dataset
        result = df[df['h3_index'].isin(bbox_cells)]
        print(f"Filtered to {len(result):,} records within bounding box")
        
        return result
    
    def optimize_for_analysis(self, df: pd.DataFrame, analysis_resolution: int = 8) -> Dict:
        """
        Optimize large dataset for different types of analysis.
        
        Args:
            df (pd.DataFrame): Raw dataset with coordinates
            analysis_resolution (int): Target H3 resolution for analysis
            
        Returns:
            Dict: Optimized datasets for different use cases
        """
        optimization_results = {}
        
        # 1. Create aggregated H3 grid (for visualization and summary stats)
        if 'h3_index' not in df.columns:
            df = self._add_h3_to_chunk(df, 'lat', 'lng', analysis_resolution)
        
        # 2. Aggregate to hexagons
        numeric_cols = df.select_dtypes(include=[np.number]).columns.tolist()
        if 'lat' in numeric_cols: numeric_cols.remove('lat')
        if 'lng' in numeric_cols: numeric_cols.remove('lng')
        if 'h3_resolution' in numeric_cols: numeric_cols.remove('h3_resolution')
        
        if numeric_cols:
            aggregated = self.aggregate_to_h3_grid(df, numeric_cols)
            optimization_results['aggregated'] = aggregated
        
        # 3. Create spatial index
        spatial_index = self.create_h3_spatial_index(df)
        optimization_results['spatial_index'] = spatial_index
        
        # 4. Create hierarchical summaries (multiple resolutions)
        hierarchical_data = {}
        for res in range(max(0, analysis_resolution-2), min(15, analysis_resolution+3)):
            if res != analysis_resolution:
                df_res = self._add_h3_to_chunk(df[['lat', 'lng'] + numeric_cols], 'lat', 'lng', res)
                if numeric_cols:
                    agg_res = self.aggregate_to_h3_grid(df_res, numeric_cols)
                    hierarchical_data[f'resolution_{res}'] = agg_res
        
        optimization_results['hierarchical'] = hierarchical_data
        
        return optimization_results

def demonstrate_large_dataset_processing():
    """Demonstrate large dataset processing capabilities."""
    print("=== Large Dataset Processing Demo ===")
    
    # Create sample large dataset
    n_records = 100000
    print(f"Creating sample dataset with {n_records:,} records...")
    
    # Generate sample data around multiple cities
    cities = [
        (38.6270, -90.1994, "St. Louis"),
        (39.1032, -84.5120, "Cincinnati"), 
        (39.7392, -104.9903, "Denver"),
        (34.0522, -118.2437, "Los Angeles")
    ]
    
    sample_data = []
    for i in range(n_records):
        city = cities[i % len(cities)]
        lat = city[0] + np.random.normal(0, 0.1)  # ~11km std dev
        lng = city[1] + np.random.normal(0, 0.1)
        
        record = {
            'lat': lat,
            'lng': lng,
            'value': np.random.exponential(100),
            'category': np.random.choice(['A', 'B', 'C']),
            'timestamp': f"2024-{np.random.randint(1,13):02d}-{np.random.randint(1,29):02d}",
            'city': city[2]
        }
        sample_data.append(record)
    
    # Save sample data
    sample_df = pd.DataFrame(sample_data)
    sample_file = "/tmp/large_sample_dataset.csv"
    sample_df.to_csv(sample_file, index=False)
    print(f"Saved sample dataset: {sample_file}")
    
    # Process with large dataset processor
    processor = LargeDatasetProcessor(batch_size=5000)
    
    # Method 1: Streaming processing
    print("\n--- Streaming Processing ---")
    processed_chunks = []
    for chunk in processor.process_large_csv_streaming(
        sample_file, 'lat', 'lng', resolution=8
    ):
        processed_chunks.append(chunk)
    
    full_dataset = pd.concat(processed_chunks, ignore_index=True)
    
    # Method 2: Parallel processing  
    print("\n--- Parallel Processing ---")
    parallel_result = processor.parallel_process_dataset(
        sample_file, 'lat', 'lng', resolution=8, n_workers=4
    )
    
    # Method 3: Optimization for analysis
    print("\n--- Optimization for Analysis ---")
    optimized = processor.optimize_for_analysis(parallel_result, 8)
    
    print(f"\nResults:")
    print(f"- Aggregated hexagons: {len(optimized['aggregated']):,}")
    print(f"- Spatial index cells: {len(optimized['spatial_index']):,}")
    print(f"- Hierarchical resolutions: {list(optimized['hierarchical'].keys())}")
    
    # Method 4: Bounding box query
    print("\n--- Bounding Box Query ---")
    stl_data = processor.query_by_bounding_box(
        parallel_result, 38.5, 38.8, -90.4, -89.9, resolution=8
    )
    print(f"St. Louis area records: {len(stl_data):,}")
    
    return optimized

if __name__ == "__main__":
    demonstrate_large_dataset_processing()
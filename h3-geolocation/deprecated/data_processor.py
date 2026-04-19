"""
Data Processing Module for H3 Framework

Handles data import, export, transformation, and analysis operations
for the H3 geolocation framework.
"""

import json
import csv
import pandas as pd
import numpy as np
import h3
from typing import List, Dict, Tuple, Optional, Union, Any
from pathlib import Path
import sqlite3
from datetime import datetime
import pickle
import gzip

class H3DataProcessor:
    """Data processing utilities for H3 framework."""
    
    def __init__(self, db_path: Optional[str] = None):
        """
        Initialize data processor.
        
        Args:
            db_path (Optional[str]): Path to SQLite database file
        """
        self.db_path = db_path
        if db_path:
            self._init_database()
    
    def _init_database(self):
        """Initialize SQLite database with H3 tables."""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        # Create H3 hexagons table
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS h3_hexagons (
                h3_index TEXT PRIMARY KEY,
                resolution INTEGER,
                lat REAL,
                lng REAL,
                data TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ''')
        
        # Create spatial data table
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS spatial_data (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                lat REAL,
                lng REAL,
                h3_index TEXT,
                properties TEXT,
                created_at TEXT
            )
        ''')
        
        # Create indexes for better performance
        cursor.execute('CREATE INDEX IF NOT EXISTS idx_h3_resolution ON h3_hexagons(resolution)')
        cursor.execute('CREATE INDEX IF NOT EXISTS idx_spatial_h3 ON spatial_data(h3_index)')
        cursor.execute('CREATE INDEX IF NOT EXISTS idx_spatial_coords ON spatial_data(lat, lng)')
        
        conn.commit()
        conn.close()
    
    def import_csv(self, file_path: str, lat_col: str = 'lat', lng_col: str = 'lng', 
                   resolution: int = 9) -> List[Dict]:
        """
        Import data from CSV file and convert to H3.
        
        Args:
            file_path (str): Path to CSV file
            lat_col (str): Name of latitude column
            lng_col (str): Name of longitude column
            resolution (int): H3 resolution level
            
        Returns:
            List[Dict]: Processed data with H3 indices
        """
        df = pd.read_csv(file_path)
        
        if lat_col not in df.columns or lng_col not in df.columns:
            raise ValueError(f"Required columns {lat_col}, {lng_col} not found in CSV")
        
        data = []
        for _, row in df.iterrows():
            try:
                lat, lng = float(row[lat_col]), float(row[lng_col])
                Anyh3_index = h3.latlng_to_cell(lat, lng, resolution)
                
                record = {
                    'lat': lat,
                    'lng': lng,
                    'h3_index': h3_index,
                    'resolution': resolution,
                    'data': row.to_dict()
                }
                data.append(record)
            except (ValueError, TypeError) as e:
                print(f"Error processing row {row.name}: {e}")
                continue
        
        return data
    
    def export_to_csv(self, data: List[Dict], file_path: str, include_h3: bool = True):
        """
        Export data to CSV file.
        
        Args:
            data (List[Dict]): Data to export
            file_path (str): Output CSV file path
            include_h3 (bool): Whether to include H3 indices
        """
        if not data:
            return
        
        # Flatten nested data structures
        flattened_data = []
        for record in data:
            flat_record = {}
            
            # Add coordinates and H3 info
            if 'lat' in record:
                flat_record['lat'] = record['lat']
            if 'lng' in record:
                flat_record['lng'] = record['lng']
            if include_h3 and 'h3_index' in record:
                flat_record['h3_index'] = record['h3_index']
            if include_h3 and 'resolution' in record:
                flat_record['resolution'] = record['resolution']
            
            # Add nested data
            if 'data' in record and isinstance(record['data'], dict):
                for key, value in record['data'].items():
                    if isinstance(value, (str, int, float, bool)):
                        flat_record[key] = value
                    else:
                        flat_record[key] = str(value)
            
            flattened_data.append(flat_record)
        
        df = pd.DataFrame(flattened_data)
        df.to_csv(file_path, index=False)
    
    def import_geojson(self, file_path: str, resolution: int = 9) -> List[Dict]:
        """
        Import GeoJSON file and convert to H3.
        
        Args:
            file_path (str): Path to GeoJSON file
            resolution (int): H3 resolution level
            
        Returns:
            List[Dict]: Processed data with H3 indices
        """
        with open(file_path, 'r') as f:
            geojson_data = json.load(f)
        
        data = []
        features = geojson_data.get('features', [])
        
        for feature in features:
            geometry = feature.get('geometry', {})
            properties = feature.get('properties', {})
            
            if geometry.get('type') == 'Point':
                coordinates = geometry.get('coordinates', [])
                if len(coordinates) >= 2:
                    lng, lat = coordinates[0], coordinates[1]
                    h3_index = h3.latlng_to_cell(lat, lng, resolution)
                    
                    record = {
                        'lat': lat,
                        'lng': lng,
                        'h3_index': h3_index,
                        'resolution': resolution,
                        'data': properties
                    }
                    data.append(record)
            
            elif geometry.get('type') == 'Polygon':
                # For polygons, get centroid and convert
                coordinates = geometry.get('coordinates', [[]])
                if coordinates and coordinates[0]:
                    # Calculate centroid
                    lngs = [coord[0] for coord in coordinates[0]]
                    lats = [coord[1] for coord in coordinates[0]]
                    centroid_lng = sum(lngs) / len(lngs)
                    centroid_lat = sum(lats) / len(lats)
                    
                    h3_index = h3.latlng_to_cell(centroid_lat, centroid_lng, resolution)
                    
                    record = {
                        'lat': centroid_lat,
                        'lng': centroid_lng,
                        'h3_index': h3_index,
                        'resolution': resolution,
                        'data': properties,
                        'geometry': geometry
                    }
                    data.append(record)
        
        return data
    
    def export_to_geojson(self, data: List[Dict], file_path: str, 
                         as_hexagons: bool = True) -> Dict:
        """
        Export data to GeoJSON format.
        
        Args:
            data (List[Dict]): Data to export
            file_path (str): Output GeoJSON file path
            as_hexagons (bool): Export as hexagon polygons or points
            
        Returns:
            Dict: GeoJSON structure
        """
        features = []
        
        for record in data:
            if 'h3_index' not in record:
                continue
            
            h3_index = record['h3_index']
            
            if as_hexagons:
                # Get hexagon boundary
                boundary = h3.cell_to_boundary(h3_index)
                coordinates = [[list(coord) for coord in boundary]]
                coordinates[0].append(coordinates[0][0])  # Close polygon
                
                geometry = {
                    'type': 'Polygon',
                    'coordinates': coordinates
                }
            else:
                # Use center point
                lat, lng = h3.cell_to_latlng(h3_index)
                geometry = {
                    'type': 'Point',
                    'coordinates': [lng, lat]
                }
            
            # Prepare properties
            properties = {
                'h3_index': h3_index,
                'resolution': record.get('resolution', h3.cell_to_res(h3_index))
            }
            
            # Add data properties
            if 'data' in record and isinstance(record['data'], dict):
                properties.update(record['data'])
            
            feature = {
                'type': 'Feature',
                'geometry': geometry,
                'properties': properties
            }
            
            features.append(feature)
        
        geojson = {
            'type': 'FeatureCollection',
            'features': features
        }
        
        # Save to file
        with open(file_path, 'w') as f:
            json.dump(geojson, f, indent=2)
        
        return geojson
    
    def aggregate_by_h3(self, data: List[Dict], aggregation_functions: Dict[str, str] = None) -> Dict[str, Dict]:
        """
        Aggregate data by H3 hexagon.
        
        Args:
            data (List[Dict]): Input data
            aggregation_functions (Dict[str, str]): Field aggregation functions
            
        Returns:
            Dict[str, Dict]: Aggregated data by H3 index
        """
        if not aggregation_functions:
            aggregation_functions = {'count': 'count'}
        
        # Group by H3 index
        h3_groups = {}
        for record in data:
            h3_index = record.get('h3_index')
            if not h3_index:
                continue
            
            if h3_index not in h3_groups:
                h3_groups[h3_index] = []
            h3_groups[h3_index].append(record)
        
        # Aggregate each group
        aggregated = {}
        for h3_index, records in h3_groups.items():
            agg_data = {
                'h3_index': h3_index,
                'count': len(records),
                'resolution': records[0].get('resolution') if records else None
            }
            
            # Add coordinate information
            if records:
                lat, lng = h3.cell_to_latlng(h3_index)
                agg_data['lat'] = lat
                agg_data['lng'] = lng
            
            # Apply aggregation functions
            for field, func in aggregation_functions.items():
                if field == 'count':
                    continue
                
                values = []
                for record in records:
                    if 'data' in record and isinstance(record['data'], dict):
                        if field in record['data']:
                            try:
                                values.append(float(record['data'][field]))
                            except (ValueError, TypeError):
                                pass
                
                if values:
                    if func == 'sum':
                        agg_data[f'{field}_sum'] = sum(values)
                    elif func == 'mean' or func == 'avg':
                        agg_data[f'{field}_avg'] = np.mean(values)
                    elif func == 'min':
                        agg_data[f'{field}_min'] = min(values)
                    elif func == 'max':
                        agg_data[f'{field}_max'] = max(values)
                    elif func == 'std':
                        agg_data[f'{field}_std'] = np.std(values)
                    elif func == 'median':
                        agg_data[f'{field}_median'] = np.median(values)
            
            aggregated[h3_index] = agg_data
        
        return aggregated
    
    def save_to_database(self, data: List[Dict], table: str = 'spatial_data'):
        """
        Save data to SQLite database.
        
        Args:
            data (List[Dict]): Data to save
            table (str): Database table name
        """
        if not self.db_path:
            raise ValueError("Database path not configured")
        
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        current_time = datetime.now().isoformat()
        
        for record in data:
            if table == 'h3_hexagons':
                cursor.execute('''
                    INSERT OR REPLACE INTO h3_hexagons 
                    (h3_index, resolution, lat, lng, data, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ''', (
                    record.get('h3_index'),
                    record.get('resolution'),
                    record.get('lat'),
                    record.get('lng'),
                    json.dumps(record.get('data', {})),
                    current_time,
                    current_time
                ))
            
            elif table == 'spatial_data':
                cursor.execute('''
                    INSERT INTO spatial_data 
                    (name, lat, lng, h3_index, properties, created_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                ''', (
                    record.get('name', ''),
                    record.get('lat'),
                    record.get('lng'),
                    record.get('h3_index'),
                    json.dumps(record.get('data', {})),
                    current_time
                ))
        
        conn.commit()
        conn.close()
    
    def load_from_database(self, table: str = 'spatial_data', 
                          filters: Dict[str, Any] = None) -> List[Dict]:
        """
        Load data from SQLite database.
        
        Args:
            table (str): Database table name
            filters (Dict[str, Any]): Query filters
            
        Returns:
            List[Dict]: Loaded data
        """
        if not self.db_path:
            raise ValueError("Database path not configured")
        
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        # Build query
        if table == 'h3_hexagons':
            query = 'SELECT * FROM h3_hexagons'
            columns = ['h3_index', 'resolution', 'lat', 'lng', 'data', 'created_at', 'updated_at']
        else:
            query = 'SELECT * FROM spatial_data'
            columns = ['id', 'name', 'lat', 'lng', 'h3_index', 'properties', 'created_at']
        
        # Add filters
        if filters:
            conditions = []
            values = []
            for key, value in filters.items():
                conditions.append(f"{key} = ?")
                values.append(value)
            
            if conditions:
                query += ' WHERE ' + ' AND '.join(conditions)
        
        # Execute query
        if filters:
            cursor.execute(query, values)
        else:
            cursor.execute(query)
        
        rows = cursor.fetchall()
        conn.close()
        
        # Convert to list of dictionaries
        data = []
        for row in rows:
            record = dict(zip(columns, row))
            
            # Parse JSON fields
            if 'data' in record and record['data']:
                try:
                    record['data'] = json.loads(record['data'])
                except json.JSONDecodeError:
                    pass
            
            if 'properties' in record and record['properties']:
                try:
                    record['properties'] = json.loads(record['properties'])
                except json.JSONDecodeError:
                    pass
            
            data.append(record)
        
        return data
    
    def create_spatial_index(self, data: List[Dict], resolution: int) -> Dict[str, List[Dict]]:
        """
        Create spatial index for fast lookups.
        
        Args:
            data (List[Dict]): Input data
            resolution (int): H3 resolution for indexing
            
        Returns:
            Dict[str, List[Dict]]: Spatial index by H3 cells
        """
        index = {}
        
        for record in data:
            lat = record.get('lat')
            lng = record.get('lng')
            
            if lat is not None and lng is not None:
                h3_index = h3.latlng_to_cell(lat, lng, resolution)
                
                if h3_index not in index:
                    index[h3_index] = []
                
                record_copy = record.copy()
                record_copy['h3_index'] = h3_index
                record_copy['resolution'] = resolution
                
                index[h3_index].append(record_copy)
        
        return index
    
    def query_by_region(self, index: Dict[str, List[Dict]], 
                       center: Tuple[float, float], radius_km: float) -> List[Dict]:
        """
        Query data within a region using spatial index.
        
        Args:
            index (Dict[str, List[Dict]]): Spatial index
            center (Tuple[float, float]): Center coordinate (lat, lng)
            radius_km (float): Search radius in kilometers
            
        Returns:
            List[Dict]: Data within the region
        """
        # Get H3 cells within radius
        resolution = 7  # Estimate resolution for search
        center_h3 = h3.latlng_to_cell(center[0], center[1], resolution)
        
        # Get all cells within approximately the specified radius
        radius_cells = h3.grid_disk(center_h3, int(radius_km / 5))  # Rough estimate
        
        results = []
        for cell in radius_cells:
            if cell in index:
                results.extend(index[cell])
        
        return results
    
    def export_to_pickle(self, data: List[Dict], file_path: str, compress: bool = True):
        """
        Export data to pickle format for fast loading.
        
        Args:
            data (List[Dict]): Data to export
            file_path (str): Output file path
            compress (bool): Whether to compress the file
        """
        if compress:
            with gzip.open(file_path, 'wb') as f:
                pickle.dump(data, f)
        else:
            with open(file_path, 'wb') as f:
                pickle.dump(data, f)
    
    def import_pickle(self, file_path: str, compressed: bool = True) -> List[Dict]:
        """
        Import data from pickle format.
        
        Args:
            file_path (str): Input file path
            compressed (bool): Whether the file is compressed
            
        Returns:
            List[Dict]: Imported data
        """
        if compressed:
            with gzip.open(file_path, 'rb') as f:
                return pickle.load(f)
        else:
            with open(file_path, 'rb') as f:
                return pickle.load(f)
    
    def create_data_summary(self, data: List[Dict]) -> Dict:
        """
        Create summary statistics for the dataset.
        
        Args:
            data (List[Dict]): Input data
            
        Returns:
            Dict: Summary statistics
        """
        if not data:
            return {'total_records': 0}
        
        summary = {
            'total_records': len(data),
            'h3_resolutions': {},
            'coordinate_bounds': {
                'min_lat': float('inf'),
                'max_lat': float('-inf'),
                'min_lng': float('inf'),
                'max_lng': float('-inf')
            },
            'data_fields': set()
        }
        
        # Analyze data
        for record in data:
            # Resolution analysis
            if 'resolution' in record:
                res = record['resolution']
                summary['h3_resolutions'][res] = summary['h3_resolutions'].get(res, 0) + 1
            
            # Coordinate bounds
            if 'lat' in record and 'lng' in record:
                lat, lng = record['lat'], record['lng']
                summary['coordinate_bounds']['min_lat'] = min(summary['coordinate_bounds']['min_lat'], lat)
                summary['coordinate_bounds']['max_lat'] = max(summary['coordinate_bounds']['max_lat'], lat)
                summary['coordinate_bounds']['min_lng'] = min(summary['coordinate_bounds']['min_lng'], lng)
                summary['coordinate_bounds']['max_lng'] = max(summary['coordinate_bounds']['max_lng'], lng)
            
            # Data fields analysis
            if 'data' in record and isinstance(record['data'], dict):
                summary['data_fields'].update(record['data'].keys())
        
        # Convert set to list for JSON serialization
        summary['data_fields'] = list(summary['data_fields'])
        
        # Handle empty bounds
        if summary['coordinate_bounds']['min_lat'] == float('inf'):
            summary['coordinate_bounds'] = None
        
        return summary
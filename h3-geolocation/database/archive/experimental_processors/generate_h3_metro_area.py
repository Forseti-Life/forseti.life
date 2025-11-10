#!/usr/bin/env python3
"""
Generate H3 Resolutions 5-7 hexagons for Philadelphia Metropolitan Area
Creates the "gold layer" aggregated data for large-scale metro area visualization
Resolution 5: ~251km² - Entire metro area (2-4 hexagons)
Resolution 6: ~36km² - Major districts (10-20 hexagons) 
Resolution 7: ~5.2km² - Neighborhoods (50-100 hexagons)
"""

import os
import sys
import mysql.connector
from mysql.connector import Error
import h3
import json
from datetime import datetime
import logging

# Database configuration
DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'drupal_user',
    'password': 'drupal_secure_password',
    'database': 'theoryofconspiracies_dev',
    'autocommit': True
}

# Philadelphia metropolitan area bounds
PHILLY_METRO_BOUNDS = {
    'north': 41.0,    # Extends into New Jersey and suburbs
    'south': 39.5,    # South to Delaware County
    'east': -74.5,    # East to New Jersey
    'west': -76.0     # West to Lancaster County edge
}

class H3MetroAreaGenerator:
    """Generate H3 resolutions 5-7 hexagons for Philadelphia metro area"""
    
    def __init__(self):
        self.logger = logging.getLogger(__name__)
        logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
        
    def connect_to_mysql(self):
        """Create MySQL connection"""
        try:
            connection = mysql.connector.connect(**DB_CONFIG)
            if connection.is_connected():
                self.logger.info("Connected to MySQL database")
                return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    def get_metro_area_hexagons(self, resolution):
        """Get all H3 hexagons at given resolution that cover the Philadelphia metro area"""
        hexagons = set()
        
        # Adjust sampling density based on resolution
        # Higher resolution = smaller hexagons = need more sample points
        if resolution == 5:
            lat_step = 0.2  # Coarse sampling for large hexagons (~251km²)
            lng_step = 0.2
        elif resolution == 6:
            lat_step = 0.1  # Medium sampling for medium hexagons (~36km²)
            lng_step = 0.1
        elif resolution == 7:
            lat_step = 0.05  # Fine sampling for smaller hexagons (~5.2km²)
            lng_step = 0.05
        else:
            lat_step = 0.1
            lng_step = 0.1
        
        current_lat = PHILLY_METRO_BOUNDS['south']
        while current_lat <= PHILLY_METRO_BOUNDS['north']:
            current_lng = PHILLY_METRO_BOUNDS['west']
            while current_lng <= PHILLY_METRO_BOUNDS['east']:
                h3_cell = h3.latlng_to_cell(current_lat, current_lng, resolution)
                hexagons.add(h3_cell)
                current_lng += lng_step
            current_lat += lat_step
        
        self.logger.info(f"Found {len(hexagons)} H3:{resolution} hexagons covering Philadelphia metro area")
        return list(hexagons)
    
    def get_incident_count_for_hexagon(self, connection, h3_cell, resolution):
        """Get incident count for a specific H3 hexagon"""
        cursor = connection.cursor()
        
        # Get the hexagon boundary
        boundary = h3.cell_to_boundary(h3_cell)
        center = h3.cell_to_latlng(h3_cell)
        
        # Convert boundary to a polygon string for spatial query
        # For now, we'll use a simpler approach - count incidents within the center area
        # In production, you'd want proper spatial indexing
        
        # Get approximate incident count within the hexagon area
        # Using a simple radius-based approach since we don't have spatial indices
        # Adjust radius based on H3 resolution
        if resolution == 5:
            radius_km = 14    # ~251km² hexagons
        elif resolution == 6:
            radius_km = 5.5   # ~36km² hexagons  
        elif resolution == 7:
            radius_km = 2.0   # ~5.2km² hexagons
        else:
            radius_km = 10    # Default fallback
        
        query = """
        SELECT COUNT(*) as incident_count
        FROM raw.incidents 
        WHERE lat IS NOT NULL 
          AND lng IS NOT NULL
          AND (
            (6371 * acos(
                cos(radians(%s)) * cos(radians(lat)) *
                cos(radians(lng) - radians(%s)) +
                sin(radians(%s)) * sin(radians(lat))
            )) <= %s
          )
        """
        
        cursor.execute(query, (center[0], center[1], center[0], radius_km))
        result = cursor.fetchone()
        cursor.close()
        
        return result[0] if result else 0
    
    def get_crime_types_for_hexagon(self, connection, h3_cell, resolution):
        """Get crime type breakdown for a specific H3 hexagon"""
        cursor = connection.cursor()
        center = h3.cell_to_latlng(h3_cell)
        
        # Adjust radius based on H3 resolution
        if resolution == 5:
            radius_km = 14
        elif resolution == 6:
            radius_km = 5.5
        elif resolution == 7:
            radius_km = 2.0
        else:
            radius_km = 10
        
        query = """
        SELECT 
            text_general_code,
            COUNT(*) as count
        FROM raw.incidents 
        WHERE lat IS NOT NULL 
          AND lng IS NOT NULL
          AND text_general_code IS NOT NULL
          AND (
            (6371 * acos(
                cos(radians(%s)) * cos(radians(lat)) *
                cos(radians(lng) - radians(%s)) +
                sin(radians(%s)) * sin(radians(lat))
            )) <= %s
          )
        GROUP BY text_general_code
        ORDER BY count DESC
        LIMIT 20
        """
        
        cursor.execute(query, (center[0], center[1], center[0], radius_km))
        results = cursor.fetchall()
        cursor.close()
        
        # Convert to JSON format
        crime_types = {}
        for crime_code, count in results:
            # Simplify crime codes for JSON storage
            simplified_code = str(crime_code)[:10] if crime_code else 'UNKNOWN'
            crime_types[simplified_code] = count
            
        return crime_types
    
    def generate_h3_boundary_json(self, h3_cell):
        """Generate boundary JSON for H3 cell"""
        boundary = h3.cell_to_boundary(h3_cell)
        # Convert from (lat, lng) to [lng, lat] for GeoJSON format
        coords = [[lng, lat] for lat, lng in boundary]
        return json.dumps(coords)
    
    def insert_h3_resolution_data(self, connection, resolution):
        """Insert H3 data for given resolution into the database"""
        hexagons = self.get_metro_area_hexagons(resolution)
        cursor = connection.cursor()
        
        # First, delete any existing data for this resolution
        self.logger.info(f"Clearing existing H3:{resolution} data...")
        cursor.execute("DELETE FROM amisafe_h3_aggregated WHERE h3_resolution = %s", (resolution,))
        
        inserted_count = 0
        total_incidents = 0
        
        for h3_cell in hexagons:
            try:
                center = h3.cell_to_latlng(h3_cell)
                boundary_json = self.generate_h3_boundary_json(h3_cell)
                
                # Get incident data for this hexagon
                incident_count = self.get_incident_count_for_hexagon(connection, h3_cell, resolution)
                crime_types = self.get_crime_types_for_hexagon(connection, h3_cell, resolution)
                
                # Calculate severity (average based on crime mix and resolution)
                severity_avg = 3.0  # Default moderate
                
                # Adjust thresholds based on resolution (smaller hexagons = fewer incidents expected)
                if resolution == 5:
                    # Large metro hexagons
                    if incident_count > 1000:
                        severity_avg = 4.5
                    elif incident_count > 500:
                        severity_avg = 3.8
                    elif incident_count > 100:
                        severity_avg = 3.2
                    elif incident_count > 10:
                        severity_avg = 2.8
                    else:
                        severity_avg = 2.0
                elif resolution == 6:
                    # District-level hexagons
                    if incident_count > 300:
                        severity_avg = 4.5
                    elif incident_count > 150:
                        severity_avg = 3.8
                    elif incident_count > 50:
                        severity_avg = 3.2
                    elif incident_count > 5:
                        severity_avg = 2.8
                    else:
                        severity_avg = 2.0
                elif resolution == 7:
                    # Neighborhood hexagons
                    if incident_count > 100:
                        severity_avg = 4.5
                    elif incident_count > 50:
                        severity_avg = 3.8
                    elif incident_count > 20:
                        severity_avg = 3.2
                    elif incident_count > 2:
                        severity_avg = 2.8
                    else:
                        severity_avg = 2.0
                
                # Determine if hexagon is empty
                is_empty = incident_count == 0
                
                # Get districts (simplified - would need more complex spatial join in production)
                districts_json = json.dumps(["METRO"])  # Placeholder
                
                # Insert the hexagon data
                insert_query = """
                INSERT INTO amisafe_h3_aggregated 
                (h3_index, h3_resolution, center_lat, center_lng, boundary_json, 
                 crime_count, crime_types_json, severity_avg, is_empty, 
                 last_updated, districts_json, peak_hour)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """
                
                cursor.execute(insert_query, (
                    h3_cell,                    # h3_index
                    resolution,                 # h3_resolution
                    center[0],                  # center_lat
                    center[1],                  # center_lng
                    boundary_json,              # boundary_json
                    incident_count,             # crime_count
                    json.dumps(crime_types),    # crime_types_json
                    severity_avg,               # severity_avg
                    is_empty,                   # is_empty
                    datetime.now(),             # last_updated
                    districts_json,             # districts_json
                    16                          # peak_hour (default afternoon)
                ))
                
                inserted_count += 1
                total_incidents += incident_count
                
                if inserted_count % 10 == 0:
                    self.logger.info(f"Inserted {inserted_count}/{len(hexagons)} H3:{resolution} hexagons...")
                    
            except Exception as e:
                self.logger.error(f"Error processing hexagon {h3_cell}: {e}")
                continue
        
        cursor.close()
        self.logger.info(f"Successfully inserted {inserted_count} H3:{resolution} hexagons with {total_incidents} total incidents")
        return inserted_count, total_incidents
    
    def verify_h3_data(self, connection, resolution):
        """Verify the generated H3 data for given resolution"""
        cursor = connection.cursor()
        
        # Get summary statistics
        cursor.execute("""
        SELECT 
            COUNT(*) as total_hexagons,
            SUM(crime_count) as total_incidents,
            AVG(crime_count) as avg_incidents_per_hex,
            MIN(crime_count) as min_incidents,
            MAX(crime_count) as max_incidents,
            COUNT(CASE WHEN is_empty = FALSE THEN 1 END) as non_empty_hexagons
        FROM amisafe_h3_aggregated 
        WHERE h3_resolution = %s
        """, (resolution,))
        
        stats = cursor.fetchone()
        
        # Get resolution description
        res_descriptions = {
            5: "Metro Area (~251km²)",
            6: "Districts (~36km²)", 
            7: "Neighborhoods (~5.2km²)"
        }
        desc = res_descriptions.get(resolution, f"Resolution {resolution}")
        
        self.logger.info(f"H3:{resolution} Data Verification - {desc}:")
        self.logger.info(f"  Total hexagons: {stats[0]}")
        self.logger.info(f"  Total incidents: {stats[1]:,}")
        self.logger.info(f"  Average incidents per hexagon: {stats[2]:.1f}")
        self.logger.info(f"  Range: {stats[3]} - {stats[4]:,} incidents")
        self.logger.info(f"  Non-empty hexagons: {stats[5]}")
        
        # Show sample hexagons
        cursor.execute("""
        SELECT h3_index, crime_count, center_lat, center_lng
        FROM amisafe_h3_aggregated 
        WHERE h3_resolution = %s AND is_empty = FALSE
        ORDER BY crime_count DESC
        LIMIT 5
        """, (resolution,))
        
        self.logger.info(f"Top 5 H3:{resolution} hexagons by incident count:")
        for row in cursor.fetchall():
            self.logger.info(f"  {row[0]}: {row[1]:,} incidents at ({row[2]:.4f}, {row[3]:.4f})")
        
        cursor.close()
    
    def run(self, resolutions=[5, 6, 7]):
        """Main execution method - generate H3 data for specified resolutions"""
        self.logger.info(f"🚀 Starting H3 hexagon generation for resolutions {resolutions} - Philadelphia metro area")
        
        connection = self.connect_to_mysql()
        results = {}
        
        try:
            for resolution in resolutions:
                self.logger.info(f"\n📊 Processing H3 Resolution {resolution}...")
                
                # Generate and insert H3 data for this resolution
                inserted_count, total_incidents = self.insert_h3_resolution_data(connection, resolution)
                
                # Verify the data
                self.verify_h3_data(connection, resolution)
                
                results[resolution] = {
                    'inserted_count': inserted_count,
                    'total_incidents': total_incidents
                }
            
            self.logger.info(f"✅ H3 hexagon generation completed successfully for resolutions {resolutions}!")
            return results
            
        finally:
            if connection.is_connected():
                connection.close()


def main():
    """Main function"""
    generator = H3MetroAreaGenerator()
    try:
        # Generate H3 resolutions 5-7 for complete metro area coverage
        results = generator.run([5, 6, 7])
        
        print(f"\n🎯 SUCCESS: Generated H3 hexagons for Philadelphia metro area")
        print("=" * 60)
        
        total_hexagons = 0
        total_incidents = 0
        
        for resolution, data in results.items():
            count = data['inserted_count']
            incidents = data['total_incidents']
            total_hexagons += count
            total_incidents += incidents
            
            if resolution == 5:
                desc = "Metro Area (~251km² each)"
            elif resolution == 6:
                desc = "Districts (~36km² each)"
            elif resolution == 7:
                desc = "Neighborhoods (~5.2km² each)"
            else:
                desc = f"Resolution {resolution}"
                
            print(f"📊 H3:{resolution} - {desc}")
            print(f"   Hexagons: {count:,}")
            print(f"   Incidents: {incidents:,}")
            print()
        
        print(f"🎯 TOTAL: {total_hexagons:,} hexagons with {total_incidents:,} incidents")
        print("📊 The 'gold layer' H3:5-7 data is now ready for multi-scale metro area visualization")
        print("🗺️ Covers entire Philadelphia metropolitan area with hierarchical resolution levels")
        
    except Exception as e:
        print(f"\n❌ ERROR: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()
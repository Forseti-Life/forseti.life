#!/usr/bin/env python3
"""
Populate H3:13 incident_ids in the aggregated table.
This script calculates H3:13 indices for incidents and updates the aggregated table.
"""

import mysql.connector
import h3
import json
from collections import defaultdict
import sys

# Database configuration
DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'drupal_user',
    'password': 'drupal_secure_password',
    'database': 'stlouisintegration_dev'
}

def connect_to_database():
    """Connect to MySQL database."""
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        return connection
    except mysql.connector.Error as err:
        print(f"Error connecting to database: {err}")
        sys.exit(1)

def get_incidents_with_coordinates(connection):
    """Get all incidents with valid coordinates."""
    cursor = connection.cursor(dictionary=True)
    
    query = """
    SELECT incident_id, lat, lng
    FROM amisafe_clean_incidents
    WHERE lat IS NOT NULL 
    AND lng IS NOT NULL
    AND lat BETWEEN 39.8 AND 40.2
    AND lng BETWEEN -75.3 AND -74.9
    LIMIT 10000
    """
    
    cursor.execute(query)
    incidents = cursor.fetchall()
    cursor.close()
    
    print(f"Retrieved {len(incidents)} incidents with coordinates")
    return incidents

def calculate_h3_indices(incidents, resolution=13):
    """Calculate H3 indices for incidents and group by H3 index."""
    h3_to_incidents = defaultdict(list)
    
    for incident in incidents:
        try:
            lat = float(incident['lat'])
            lng = float(incident['lng'])
            
            # Calculate H3 index at resolution 13 (updated API)
            h3_index = h3.latlng_to_cell(lat, lng, resolution)
            h3_to_incidents[h3_index].append(incident['incident_id'])
            
        except (ValueError, TypeError) as e:
            print(f"Error processing incident {incident['incident_id']}: {e}")
            continue
    
    print(f"Grouped incidents into {len(h3_to_incidents)} H3:13 hexagons")
    return dict(h3_to_incidents)

def update_h3_aggregated_table(connection, h3_to_incidents):
    """Update the H3 aggregated table with incident IDs."""
    cursor = connection.cursor()
    
    updated_count = 0
    for h3_index, incident_ids in h3_to_incidents.items():
        incident_ids_json = json.dumps(incident_ids)
        
        # Update existing H3:13 hexagons with incident IDs
        update_query = """
        UPDATE amisafe_h3_aggregated 
        SET incident_ids = %s
        WHERE h3_index = %s AND h3_resolution = 13
        """
        
        cursor.execute(update_query, (incident_ids_json, h3_index))
        
        if cursor.rowcount > 0:
            updated_count += 1
            print(f"Updated hexagon {h3_index} with {len(incident_ids)} incidents")
    
    connection.commit()
    cursor.close()
    
    print(f"Updated {updated_count} hexagons with incident IDs")

def verify_updates(connection):
    """Verify that incident_ids were populated correctly."""
    cursor = connection.cursor(dictionary=True)
    
    # Check how many H3:13 hexagons now have incident_ids
    query = """
    SELECT 
        COUNT(*) as total_h3_13,
        COUNT(incident_ids) as with_incident_ids,
        AVG(JSON_LENGTH(incident_ids)) as avg_incidents_per_hex
    FROM amisafe_h3_aggregated 
    WHERE h3_resolution = 13
    """
    
    cursor.execute(query)
    result = cursor.fetchone()
    cursor.close()
    
    print(f"\nVerification Results:")
    print(f"Total H3:13 hexagons: {result['total_h3_13']}")
    print(f"Hexagons with incident_ids: {result['with_incident_ids']}")
    print(f"Average incidents per hexagon: {result['avg_incidents_per_hex']:.1f}")

def main():
    """Main execution function."""
    print("Starting H3:13 incident_ids population...")
    
    # Connect to database
    connection = connect_to_database()
    
    try:
        # Get incidents with coordinates
        incidents = get_incidents_with_coordinates(connection)
        
        # Calculate H3 indices and group incidents
        h3_to_incidents = calculate_h3_indices(incidents)
        
        # Update H3 aggregated table
        update_h3_aggregated_table(connection, h3_to_incidents)
        
        # Verify updates
        verify_updates(connection)
        
        print("\nH3:13 incident_ids population completed successfully!")
        
    except Exception as e:
        print(f"Error during processing: {e}")
        sys.exit(1)
    
    finally:
        connection.close()

if __name__ == "__main__":
    main()
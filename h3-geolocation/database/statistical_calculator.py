#!/usr/bin/env python3
"""
Statistical Calculator for AmISafe H3 Aggregations
Calculates z-scores, percentiles, and risk scores for hexagons
"""

import numpy as np
from typing import List, Dict, Tuple
from datetime import datetime, timedelta

class StatisticalCalculator:
    """
    Calculates statistical metrics for H3 hexagons including:
    - Violent/non-violent crime breakdowns
    - Z-scores and percentiles 
    - Risk scores and categories
    - Rolling window statistics (6mo, 12mo)
    """
    
    # Violent crime UCR codes (Homicide, Rape, Robbery, Aggravated Assault)
    VIOLENT_CRIME_CODES = ['100', '200', '300', '400']
    
    def __init__(self):
        """Initialize the statistical calculator."""
        pass
    
    def classify_violent_crimes(self, incidents: List[Dict]) -> Tuple[int, int]:
        """
        Classify incidents into violent and non-violent categories.
        
        Args:
            incidents: List of incident dictionaries with 'ucr_general' field
            
        Returns:
            Tuple[int, int]: (violent_count, nonviolent_count)
        """
        violent_count = 0
        nonviolent_count = 0
        
        for incident in incidents:
            ucr_code = str(incident.get('ucr_general', ''))
            if ucr_code in self.VIOLENT_CRIME_CODES:
                violent_count += 1
            else:
                nonviolent_count += 1
                
        return violent_count, nonviolent_count
    
    def filter_incidents_by_window(self, incidents: List[Dict], months: int) -> List[Dict]:
        """
        Filter incidents to those within the last N months from today.
        
        Args:
            incidents: List of incident dictionaries with 'incident_datetime' or 'incident_date'
            months: Number of months to look back from today
            
        Returns:
            List[Dict]: Filtered incidents within the time window
        """
        cutoff_date = datetime.now() - timedelta(days=months * 30)
        filtered = []
        
        for incident in incidents:
            # Try incident_datetime first, then incident_date
            incident_dt = incident.get('incident_datetime') or incident.get('incident_date')
            
            if incident_dt:
                # Convert to datetime if it's a date object
                if isinstance(incident_dt, datetime):
                    dt = incident_dt
                else:
                    try:
                        dt = datetime.combine(incident_dt, datetime.min.time())
                    except:
                        continue
                
                if dt >= cutoff_date:
                    filtered.append(incident)
        
        return filtered
    
    def calculate_basic_stats(self, incidents: List[Dict]) -> Dict:
        """
        Calculate basic crime statistics for a hexagon.
        
        Returns:
            Dict with violent_count, nonviolent_count, percentages, etc.
        """
        total_count = len(incidents)
        
        if total_count == 0:
            return {
                'violent_count': 0,
                'nonviolent_count': 0,
                'violent_percentage': 0.0,
                'nonviolent_percentage': 0.0
            }
        
        violent_count, nonviolent_count = self.classify_violent_crimes(incidents)
        
        return {
            'violent_count': violent_count,
            'nonviolent_count': nonviolent_count,
            'violent_percentage': round((violent_count / total_count) * 100, 2) if total_count > 0 else 0.0,
            'nonviolent_percentage': round((nonviolent_count / total_count) * 100, 2) if total_count > 0 else 0.0
        }
    
    def calculate_resolution_statistics(self, all_hex_data: List[Dict], field: str) -> Tuple[float, float]:
        """
        Calculate mean and standard deviation across all hexagons at a resolution.
        
        Args:
            all_hex_data: List of dicts with hex statistics
            field: Field name to calculate stats for (e.g., 'violent_count', 'incident_count')
            
        Returns:
            Tuple[float, float]: (mean, std_dev)
        """
        values = [hex_data.get(field, 0) for hex_data in all_hex_data]
        
        if not values:
            return 0.0, 0.0
        
        mean = np.mean(values)
        std_dev = np.std(values, ddof=1) if len(values) > 1 else 0.0
        
        return float(mean), float(std_dev)
    
    def calculate_z_score(self, value: float, mean: float, std_dev: float) -> float:
        """
        Calculate z-score for a value.
        
        Args:
            value: The value to score
            mean: Population mean
            std_dev: Population standard deviation
            
        Returns:
            float: Z-score (how many std devs from mean)
        """
        if std_dev == 0:
            return 0.0
        
        z_score = (value - mean) / std_dev
        return round(z_score, 3)
    
    def calculate_percentile(self, value: float, all_values: List[float]) -> int:
        """
        Calculate percentile rank (0-100) for a value.
        
        Args:
            value: The value to rank
            all_values: All values in the population
            
        Returns:
            int: Percentile rank (0-100)
        """
        if not all_values:
            return 50
        
        # Use scipy.stats.percentileofscore if available, otherwise calculate manually
        try:
            from scipy import stats
            percentile = stats.percentileofscore(all_values, value, kind='rank')
        except ImportError:
            # Manual calculation: percentage of values <= this value
            count_below = sum(1 for v in all_values if v <= value)
            percentile = (count_below / len(all_values)) * 100
        
        return int(round(percentile))
    
    def calculate_risk_score(self, 
                             incident_z_score: float,
                             violent_z_score: float,
                             trend_factor: float = 0.0,
                             volatility_factor: float = 0.0) -> float:
        """
        Calculate composite risk score using weighted z-scores.
        
        Formula:
            risk_score = 0.30 * incident_z_score + 
                        0.40 * violent_z_score + 
                        0.20 * trend_factor + 
                        0.10 * volatility_factor
        
        Args:
            incident_z_score: Overall incident z-score
            violent_z_score: Violent crime z-score
            trend_factor: Trend indicator (not yet implemented, default 0)
            volatility_factor: Volatility indicator (not yet implemented, default 0)
            
        Returns:
            float: Composite risk score
        """
        risk_score = (
            0.30 * incident_z_score +
            0.40 * violent_z_score +
            0.20 * trend_factor +
            0.10 * volatility_factor
        )
        
        return round(risk_score, 3)
    
    def categorize_risk(self, risk_score: float) -> str:
        """
        Categorize risk score into LOW, MODERATE, HIGH, or CRITICAL.
        
        Based on z-score interpretation:
        - Below -0.5: LOW
        - -0.5 to 0.5: MODERATE  
        - 0.5 to 1.5: HIGH
        - Above 1.5: CRITICAL
        
        Args:
            risk_score: Composite risk score
            
        Returns:
            str: Risk category (LOW, MODERATE, HIGH, CRITICAL)
        """
        if risk_score < -0.5:
            return 'LOW'
        elif risk_score < 0.5:
            return 'MODERATE'
        elif risk_score < 1.5:
            return 'HIGH'
        else:
            return 'CRITICAL'
    
    def categorize_hotspot(self, risk_score: float) -> str:
        """
        Categorize hotspot status into COLD, WARM, HOT, or EXTREME.
        
        More granular than risk_category for heat maps:
        - Below -1.0: COLD
        - -1.0 to 0.0: WARM
        - 0.0 to 1.0: HOT
        - Above 1.0: EXTREME
        
        Args:
            risk_score: Composite risk score
            
        Returns:
            str: Hotspot status (COLD, WARM, HOT, EXTREME)
        """
        if risk_score < -1.0:
            return 'COLD'
        elif risk_score < 0.0:
            return 'WARM'
        elif risk_score < 1.0:
            return 'HOT'
        else:
            return 'EXTREME'
    
    def calculate_temporal_metrics(self, incidents: List[Dict]) -> Dict:
        """
        Calculate temporal pattern metrics from incidents.
        
        Returns:
            Dict with incidents_by_hour, incidents_by_dow, incidents_by_month,
            peak_hour, peak_dow, top_crime_type, crime_diversity_index
        """
        metrics = {
            'incidents_by_hour': [0] * 24,
            'incidents_by_dow': [0] * 7,
            'incidents_by_month': [0] * 12,
            'peak_hour': None,
            'peak_dow': None,
            'top_crime_type': None,
            'crime_diversity_index': 0.0
        }
        
        if not incidents:
            return metrics
        
        crime_counts = {}
        
        for incident in incidents:
            # Hour pattern
            hour = incident.get('hour_of_day')
            if hour is not None and 0 <= hour <= 23:
                metrics['incidents_by_hour'][hour] += 1
            
            # Day of week pattern
            dow = incident.get('day_of_week')
            if dow is not None and 0 <= dow <= 6:
                metrics['incidents_by_dow'][dow] += 1
            
            # Month pattern
            month = incident.get('month_num')
            if month is not None and 1 <= month <= 12:
                metrics['incidents_by_month'][month - 1] += 1
            
            # Crime type counting
            crime_type = incident.get('ucr_general')
            if crime_type:
                crime_counts[str(crime_type)] = crime_counts.get(str(crime_type), 0) + 1
        
        # Find peaks
        if any(metrics['incidents_by_hour']):
            metrics['peak_hour'] = metrics['incidents_by_hour'].index(max(metrics['incidents_by_hour']))
        
        if any(metrics['incidents_by_dow']):
            metrics['peak_dow'] = metrics['incidents_by_dow'].index(max(metrics['incidents_by_dow']))
        
        # Top crime type
        if crime_counts:
            metrics['top_crime_type'] = max(crime_counts.keys(), key=crime_counts.get)
            
            # Shannon diversity index
            if len(crime_counts) > 1:
                total_crimes = sum(crime_counts.values())
                shannon_index = 0.0
                
                for count in crime_counts.values():
                    if count > 0:
                        proportion = count / total_crimes
                        shannon_index -= proportion * np.log(proportion)
                
                metrics['crime_diversity_index'] = round(shannon_index, 3)
        
        return metrics
    
    def calculate_complete_statistics(self, 
                                     incidents: List[Dict],
                                     all_hex_stats: List[Dict]) -> Dict:
        """
        Calculate complete statistical package for a hexagon including:
        - All-time statistics
        - 12-month window statistics
        - 6-month window statistics
        
        Args:
            incidents: All incidents for this hexagon (all-time)
            all_hex_stats: Statistics for all hexagons at this resolution (for z-scores/percentiles)
            
        Returns:
            Dict: Complete statistics package
        """
        result = {}
        
        # Calculate all-time statistics
        all_time = self.calculate_window_statistics(incidents, all_hex_stats, suffix='')
        result.update(all_time)
        
        # Calculate 12-month window
        incidents_12mo = self.filter_incidents_by_window(incidents, 12)
        twelve_mo = self.calculate_window_statistics(incidents_12mo, all_hex_stats, suffix='_12mo')
        result.update(twelve_mo)
        
        # Calculate 6-month window  
        incidents_6mo = self.filter_incidents_by_window(incidents, 6)
        six_mo = self.calculate_window_statistics(incidents_6mo, all_hex_stats, suffix='_6mo')
        result.update(six_mo)
        
        return result
    
    def calculate_window_statistics(self, 
                                    incidents: List[Dict],
                                    all_hex_stats: List[Dict],
                                    suffix: str = '') -> Dict:
        """
        Calculate statistics for a time window (all-time, 12mo, or 6mo).
        
        Args:
            incidents: Incidents for this window
            all_hex_stats: All hexagon stats for resolution (for comparative metrics)
            suffix: Suffix for field names ('', '_12mo', '_6mo')
            
        Returns:
            Dict: Statistics for this window
        """
        stats = {}
        
        # Basic counts and percentages
        basic = self.calculate_basic_stats(incidents)
        stats[f'violent_crime_count{suffix}'] = basic['violent_count']
        stats[f'nonviolent_crime_count{suffix}'] = basic['nonviolent_count']
        stats[f'violent_crime_percentage{suffix}'] = basic['violent_percentage']
        stats[f'nonviolent_crime_percentage{suffix}'] = basic['nonviolent_percentage']
        
        # Incident count
        stats[f'incident_count{suffix}'] = len(incidents)
        
        # Calculate resolution-wide statistics (mean, std_dev)
        incident_mean, incident_std = self.calculate_resolution_statistics(
            all_hex_stats, f'incident_count{suffix}')
        violent_mean, violent_std = self.calculate_resolution_statistics(
            all_hex_stats, f'violent_crime_count{suffix}')
        nonviolent_mean, nonviolent_std = self.calculate_resolution_statistics(
            all_hex_stats, f'nonviolent_crime_count{suffix}')
        
        # Store means and std devs (denormalized)
        stats[f'incident_mean{suffix}'] = round(incident_mean, 2)
        stats[f'incident_std_dev{suffix}'] = round(incident_std, 2)
        stats[f'violent_crime_mean{suffix}'] = round(violent_mean, 2)
        stats[f'violent_crime_std_dev{suffix}'] = round(violent_std, 2)
        stats[f'nonviolent_crime_mean{suffix}'] = round(nonviolent_mean, 2)
        stats[f'nonviolent_crime_std_dev{suffix}'] = round(nonviolent_std, 2)
        
        # Calculate z-scores
        stats[f'incident_z_score{suffix}'] = self.calculate_z_score(
            stats[f'incident_count{suffix}'], incident_mean, incident_std)
        stats[f'violent_crime_z_score{suffix}'] = self.calculate_z_score(
            stats[f'violent_crime_count{suffix}'], violent_mean, violent_std)
        stats[f'nonviolent_crime_z_score{suffix}'] = self.calculate_z_score(
            stats[f'nonviolent_crime_count{suffix}'], nonviolent_mean, nonviolent_std)
        
        # Calculate percentiles
        all_incident_counts = [h.get(f'incident_count{suffix}', 0) for h in all_hex_stats]
        all_violent_counts = [h.get(f'violent_crime_count{suffix}', 0) for h in all_hex_stats]
        all_nonviolent_counts = [h.get(f'nonviolent_crime_count{suffix}', 0) for h in all_hex_stats]
        
        stats[f'incident_percentile{suffix}'] = self.calculate_percentile(
            stats[f'incident_count{suffix}'], all_incident_counts)
        stats[f'violent_crime_percentile{suffix}'] = self.calculate_percentile(
            stats[f'violent_crime_count{suffix}'], all_violent_counts)
        stats[f'nonviolent_crime_percentile{suffix}'] = self.calculate_percentile(
            stats[f'nonviolent_crime_count{suffix}'], all_nonviolent_counts)
        
        # Calculate risk score
        stats[f'risk_score{suffix}'] = self.calculate_risk_score(
            stats[f'incident_z_score{suffix}'],
            stats[f'violent_crime_z_score{suffix}']
        )
        
        # Categorize risk
        stats[f'risk_category{suffix}'] = self.categorize_risk(stats[f'risk_score{suffix}'])
        stats[f'hotspot_status{suffix}'] = self.categorize_hotspot(stats[f'risk_score{suffix}'])
        
        # Calculate temporal metrics (only for suffixed windows, not all-time)
        if suffix:
            temporal = self.calculate_temporal_metrics(incidents)
            stats[f'unique_incident_types{suffix}'] = len(set(
                str(i.get('ucr_general')) for i in incidents if i.get('ucr_general')))
            stats[f'incidents_by_hour{suffix}'] = temporal['incidents_by_hour']
            stats[f'incidents_by_dow{suffix}'] = temporal['incidents_by_dow']
            stats[f'incidents_by_month{suffix}'] = temporal['incidents_by_month']
            stats[f'peak_hour{suffix}'] = temporal['peak_hour']
            stats[f'peak_dow{suffix}'] = temporal['peak_dow']
            stats[f'top_crime_type{suffix}'] = temporal['top_crime_type']
            stats[f'crime_diversity_index{suffix}'] = temporal['crime_diversity_index']
        
        return stats

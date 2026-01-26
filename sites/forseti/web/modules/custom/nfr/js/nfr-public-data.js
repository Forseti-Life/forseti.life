/**
 * NFR Public Data Dashboard - US Map Visualization
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.nfrPublicDataMap = {
    attach: function (context, settings) {
      const $map = $('#us-map', context).once('nfr-map');
      if (!$map.length) {
        return;
      }

      const stateData = drupalSettings.nfr.stateData || {};
      
      // State abbreviations to full names mapping
      const stateNames = {
        'AL': 'Alabama', 'AK': 'Alaska', 'AZ': 'Arizona', 'AR': 'Arkansas',
        'CA': 'California', 'CO': 'Colorado', 'CT': 'Connecticut', 'DE': 'Delaware',
        'FL': 'Florida', 'GA': 'Georgia', 'HI': 'Hawaii', 'ID': 'Idaho',
        'IL': 'Illinois', 'IN': 'Indiana', 'IA': 'Iowa', 'KS': 'Kansas',
        'KY': 'Kentucky', 'LA': 'Louisiana', 'ME': 'Maine', 'MD': 'Maryland',
        'MA': 'Massachusetts', 'MI': 'Michigan', 'MN': 'Minnesota', 'MS': 'Mississippi',
        'MO': 'Missouri', 'MT': 'Montana', 'NE': 'Nebraska', 'NV': 'Nevada',
        'NH': 'New Hampshire', 'NJ': 'New Jersey', 'NM': 'New Mexico', 'NY': 'New York',
        'NC': 'North Carolina', 'ND': 'North Dakota', 'OH': 'Ohio', 'OK': 'Oklahoma',
        'OR': 'Oregon', 'PA': 'Pennsylvania', 'RI': 'Rhode Island', 'SC': 'South Carolina',
        'SD': 'South Dakota', 'TN': 'Tennessee', 'TX': 'Texas', 'UT': 'Utah',
        'VT': 'Vermont', 'VA': 'Virginia', 'WA': 'Washington', 'WV': 'West Virginia',
        'WI': 'Wisconsin', 'WY': 'Wyoming', 'DC': 'District of Columbia',
        'PR': 'Puerto Rico', 'GU': 'Guam', 'VI': 'Virgin Islands'
      };

      // Simplified US map - we'll use a table-based approach for better reliability
      // Create a simple choropleth map using HTML table
      renderSimpleMap();

      function renderSimpleMap() {
        const container = document.getElementById('us-map-container');
        container.innerHTML = '';
        
        const mapDiv = document.createElement('div');
        mapDiv.className = 'simple-us-map';
        
        // Group states by region for layout
        const regions = {
          'Northeast': ['ME', 'NH', 'VT', 'MA', 'RI', 'CT', 'NY', 'PA', 'NJ', 'DE', 'MD', 'DC'],
          'Southeast': ['VA', 'WV', 'KY', 'TN', 'NC', 'SC', 'GA', 'FL', 'AL', 'MS', 'LA', 'AR'],
          'Midwest': ['OH', 'IN', 'IL', 'MI', 'WI', 'MN', 'IA', 'MO', 'ND', 'SD', 'NE', 'KS'],
          'Southwest': ['TX', 'OK', 'NM', 'AZ'],
          'West': ['CA', 'NV', 'OR', 'WA', 'ID', 'MT', 'WY', 'UT', 'CO'],
          'Pacific': ['HI', 'AK'],
          'Territories': ['PR', 'GU', 'VI']
        };

        let html = '<div class="us-map-grid">';
        
        for (const [region, states] of Object.entries(regions)) {
          html += `<div class="map-region">`;
          html += `<h4 class="region-title text-white mb-3">${region}</h4>`;
          html += `<div class="state-grid">`;
          
          states.forEach(stateCode => {
            const count = stateData[stateCode] || 0;
            const colorClass = getColorClass(count);
            const stateName = stateNames[stateCode] || stateCode;
            
            html += `<div class="state-box ${colorClass}" data-state="${stateCode}" data-count="${count}">`;
            html += `<div class="state-code">${stateCode}</div>`;
            html += `<div class="state-name">${stateName}</div>`;
            html += `<div class="state-count">${count} ${count === 1 ? 'firefighter' : 'firefighters'}</div>`;
            html += `</div>`;
          });
          
          html += `</div></div>`;
        }
        
        html += '</div>';
        
        mapDiv.innerHTML = html;
        container.appendChild(mapDiv);
        
        // Add hover effects
        $('.state-box').hover(
          function() {
            $(this).addClass('hover');
          },
          function() {
            $(this).removeClass('hover');
          }
        );
      }

      function getColorClass(count) {
        if (count === 0) return 'state-level-0';
        if (count <= 10) return 'state-level-1';
        if (count <= 50) return 'state-level-2';
        if (count <= 100) return 'state-level-3';
        if (count <= 250) return 'state-level-4';
        return 'state-level-5';
      }
    }
  };

})(jQuery, Drupal, drupalSettings);

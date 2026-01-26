/**
 * NFR Public Data Dashboard - US Heat Map Visualization
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

      loadSVGMap();

      function loadSVGMap() {
        const container = document.getElementById('us-map-container');
        
        // Load the SVG map
        $.get('/modules/custom/nfr/images/us-map.svg', function(svgDoc) {
          const $svg = $(svgDoc).find('svg');
          
          // Set responsive attributes
          $svg.attr('width', '100%');
          $svg.attr('height', 'auto');
          $svg.attr('id', 'heat-map-svg');
          
          // Style all state paths
          $svg.find('path[data-id]').each(function() {
            const $path = $(this);
            const stateCode = $path.attr('data-id');
            const count = stateData[stateCode] || 0;
            const fillColor = getHeatColor(count);
            
            $path.css({
              'fill': fillColor,
              'stroke': 'rgba(255, 255, 255, 0.4)',
              'stroke-width': '1',
              'cursor': 'pointer',
              'transition': 'all 0.3s ease'
            });
            
            $path.attr('data-count', count);
            $path.attr('data-state-name', stateNames[stateCode] || stateCode);
          });
          
          // Clear container and append SVG
          container.innerHTML = '';
          container.appendChild($svg[0]);
          
          // Add tooltip
          const tooltip = $('<div class="map-tooltip"></div>').appendTo('body');
          
          // Add hover interactions
          $svg.find('path[data-id]').hover(
            function() {
              const $this = $(this);
              const stateName = $this.attr('data-state-name');
              const count = $this.attr('data-count');
              
              $this.css({
                'stroke': '#00d4ff',
                'stroke-width': '2',
                'filter': 'brightness(1.2)'
              });
              
              tooltip.html(`
                <strong>${stateName}</strong><br>
                ${count} ${count == 1 ? 'firefighter' : 'firefighters'}
              `).show();
            },
            function() {
              $(this).css({
                'stroke': 'rgba(255, 255, 255, 0.4)',
                'stroke-width': '1',
                'filter': 'none'
              });
              tooltip.hide();
            }
          );
          
          // Tooltip follows mouse
          $svg.on('mousemove', function(e) {
            tooltip.css({
              left: e.pageX + 15 + 'px',
              top: e.pageY + 15 + 'px'
            });
          });
          
        }).fail(function() {
          // Fallback: show error message
          container.innerHTML = '<div class="alert alert-warning">Unable to load heat map. Please refresh the page.</div>';
        });
      }

      function getHeatColor(count) {
        // Return color based on count (heat map gradient from light to dark)
        if (count === 0) return 'rgba(100, 100, 100, 0.3)'; // Gray for no data
        if (count <= 10) return 'rgba(153, 213, 255, 0.5)'; // Light blue
        if (count <= 50) return 'rgba(82, 183, 255, 0.7)'; // Medium blue
        if (count <= 100) return 'rgba(0, 153, 255, 0.8)'; // Bright blue
        if (count <= 250) return 'rgba(0, 102, 204, 0.9)'; // Dark blue
        return 'rgba(0, 51, 153, 1)'; // Deepest blue for 250+
      }
    }
  };

})(jQuery, Drupal, drupalSettings);

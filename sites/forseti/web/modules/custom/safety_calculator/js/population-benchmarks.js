/**
 * @file
 * Population Benchmarks page behaviors.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.populationBenchmarks = {
    attach: function (context, settings) {
      // Smooth scroll to expanded accordion
      once('accordion-scroll', '.accordion-button', context).forEach(function(element) {
        $(element).on('click', function() {
          var $button = $(this);
          setTimeout(function() {
            if (!$button.hasClass('collapsed')) {
              $('html, body').animate({
                scrollTop: $button.offset().top - 100
              }, 500);
            }
          }, 350);
        });
      });

      // Add tooltips for metrics with references
      $('[data-bs-toggle="tooltip"]', context).tooltip();

      // Highlight dimension on hover
      once('dimension-highlight', '.dimension-slider', context).forEach(function(element) {
        $(element).hover(
          function() {
            $(this).closest('.card').addClass('border-primary shadow');
          },
          function() {
            $(this).closest('.card').removeClass('border-primary shadow');
          }
        );
      });

      // Print dimension scores summary
      once('print-summary', '.dimension-scores-section', context).forEach(function(element) {
        $(element).append(
          '<div class="text-center mt-4">' +
          '  <button class="btn btn-outline-primary btn-sm" id="print-summary">' +
          '    <i class="fas fa-print me-2"></i>Print Summary' +
          '  </button>' +
          '</div>'
        );
      });

      once('print-handler', '#print-summary', context).forEach(function(element) {
        $(element).on('click', function() {
          window.print();
        });
      });

      // Initialize Chart.js charts for distribution data
      once('chart-init', 'canvas[data-distribution]', context).forEach(function(canvas) {
        var $canvas = $(canvas);
        var distributionData = JSON.parse($canvas.attr('data-distribution'));
        
        if (!distributionData || typeof distributionData !== 'object') {
          return;
        }

        var labels = Object.keys(distributionData);
        var values = Object.values(distributionData);
        
        // Check if this is a bell curve (numeric keys) or categorical data (text keys)
        var isNumericDistribution = !isNaN(parseFloat(labels[0]));
        
        var ctx = canvas.getContext('2d');
        
        if (isNumericDistribution) {
          // Extract metadata if present
          var metadata = distributionData._metadata || {};
          var meanValue = metadata.mean;
          var minValue = metadata.min_value;
          var maxValue = metadata.max_value;
          
          // Remove metadata from display data
          var cleanLabels = labels.filter(function(l) { return l !== '_metadata'; });
          var cleanValues = values.slice(0, cleanLabels.length);
          
          // Bell curve visualization for normalized scores
          new Chart(ctx, {
            type: 'line',
            data: {
              labels: cleanLabels,
              datasets: [{
                label: 'Distribution',
                data: cleanValues,
                borderColor: 'rgba(13, 110, 253, 1)',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.4, // Smooth curve
                borderWidth: 2,
                pointRadius: 0, // Hide individual points for smooth line
                pointHoverRadius: 4
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  display: false
                },
                tooltip: {
                  callbacks: {
                    title: function(context) {
                      return 'Score: ' + context[0].label;
                    },
                    label: function(context) {
                      return 'Density: ' + context.parsed.y.toFixed(2);
                    }
                  }
                },
                annotation: {
                  annotations: {
                    label0: {
                      type: 'label',
                      xValue: 0,
                      xAdjust: 20,
                      yValue: 0,
                      yAdjust: -20,
                      backgroundColor: 'rgba(0,0,0,0.8)',
                      content: minValue !== undefined ? [minValue.toString()] : ['Min'],
                      font: { size: 10 },
                      color: 'white',
                      padding: 4
                    },
                    label50: {
                      type: 'label',
                      xValue: '50',
                      yValue: Math.max.apply(null, cleanValues),
                      yAdjust: 15,
                      backgroundColor: 'rgba(13, 110, 253, 0.9)',
                      content: meanValue !== undefined ? ['Mean: ' + meanValue] : ['Mean'],
                      font: { size: 11, weight: 'bold' },
                      color: 'white',
                      padding: 6
                    },
                    label100: {
                      type: 'label',
                      xValue: '100',
                      xAdjust: -20,
                      yValue: 0,
                      yAdjust: -20,
                      backgroundColor: 'rgba(0,0,0,0.8)',
                      content: maxValue !== undefined ? [maxValue.toString()] : ['Max'],
                      font: { size: 10 },
                      color: 'white',
                      padding: 4
                    }
                  }
                }
              },
              scales: {
                x: {
                  title: {
                    display: true,
                    text: 'Normalized Score (0-100)'
                  },
                  ticks: {
                    callback: function(value, index) {
                      // Show every other label to avoid crowding
                      return index % 2 === 0 ? this.getLabelForValue(value) : '';
                    }
                  }
                },
                y: {
                  beginAtZero: true,
                  title: {
                    display: true,
                    text: 'Probability Density'
                  },
                  ticks: {
                    callback: function(value) {
                      return value.toFixed(0);
                    }
                  }
                }
              }
            }
          });
        } else {
          // Bar chart for categorical data
          var percentages = values.map(function(v) {
            return typeof v === 'number' && v <= 1 ? v * 100 : v;
          });
          
          new Chart(ctx, {
            type: 'bar',
            data: {
              labels: labels,
              datasets: [{
                label: 'Distribution (%)',
                data: percentages,
                backgroundColor: [
                  'rgba(13, 110, 253, 0.7)',
                  'rgba(25, 135, 84, 0.7)',
                  'rgba(255, 193, 7, 0.7)',
                  'rgba(220, 53, 69, 0.7)',
                  'rgba(108, 117, 125, 0.7)',
                  'rgba(102, 16, 242, 0.7)',
                  'rgba(13, 202, 240, 0.7)',
                  'rgba(214, 51, 132, 0.7)'
                ],
                borderColor: [
                  'rgba(13, 110, 253, 1)',
                  'rgba(25, 135, 84, 1)',
                  'rgba(255, 193, 7, 1)',
                  'rgba(220, 53, 69, 1)',
                  'rgba(108, 117, 125, 1)',
                  'rgba(102, 16, 242, 1)',
                  'rgba(13, 202, 240, 1)',
                  'rgba(214, 51, 132, 1)'
                ],
                borderWidth: 1
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  display: false
                },
                tooltip: {
                  callbacks: {
                    label: function(context) {
                      return context.parsed.y.toFixed(1) + '%';
                    }
                  }
                }
              },
              scales: {
                y: {
                  beginAtZero: true,
                  max: 100,
                  ticks: {
                    callback: function(value) {
                      return value + '%';
                    }
                  }
                }
              }
            }
          });
        }
      });
    }
  };

})(jQuery, Drupal, once);

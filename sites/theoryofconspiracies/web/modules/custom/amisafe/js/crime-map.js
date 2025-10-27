/**
 * @file
 * AmISafe Crime Map - Core JavaScript functionality
 * 
 * Initializes and manages the interactive crime map using Leaflet.js and H3 hexagons
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * AmISafe Crime Map behavior
   */
  Drupal.behaviors.amisafeCrimeMap = {
    attach: function (context, settings) {
      if (!settings.amisafe) {
        console.warn('AmISafe settings not found');
        return;
      }

      $('#crime-map-container', context).once('amisafe-map').each(function () {
        var crimeMap = new AmISafeCrimeMap(this, settings.amisafe);
        crimeMap.initialize();
      });
    }
  };

  /**
   * AmISafe Crime Map Class
   */
  function AmISafeCrimeMap(container, settings) {
    this.container = container;
    this.settings = settings;
    this.map = null;
    this.hexagonLayer = null;
    this.heatmapLayer = null;
    this.markersLayer = null;
    this.currentView = 'hexagon';
    this.currentFilters = {};
    this.loadingOverlay = document.getElementById('loading-overlay');
  }

  AmISafeCrimeMap.prototype = {
    
    /**
     * Initialize the crime map
     */
    initialize: function () {
      console.log('Initializing AmISafe Crime Map...');
      
      this.showLoading('INITIALIZING NEURAL MAP...');
      
      try {
        this.initializeMap();
        this.initializeControls();
        this.initializeFilters();
        this.loadInitialData();
        
        console.log('AmISafe Crime Map initialized successfully');
        this.hideLoading();
      } catch (error) {
        console.error('Error initializing crime map:', error);
        this.showError('INITIALIZATION FAILED: ' + error.message);
      }
    },

    /**
     * Initialize the Leaflet map
     */
    initializeMap: function () {
      if (!window.L) {
        throw new Error('Leaflet library not loaded');
      }

      var mapConfig = this.settings.mapConfig;
      
      this.map = L.map(this.container, {
        center: mapConfig.center,
        zoom: mapConfig.zoom,
        minZoom: 9,
        maxZoom: 16,
        zoomControl: true,
        attributionControl: false
      });

      // Add dark cyberpunk base tiles
      var darkTiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '',
        subdomains: 'abcd',
        maxZoom: 19,
        className: 'dark-tiles'
      });

      darkTiles.addTo(this.map);

      // Initialize layer groups
      this.hexagonLayer = L.layerGroup().addTo(this.map);
      this.heatmapLayer = L.layerGroup();
      this.markersLayer = L.layerGroup();

      // Add map event listeners
      this.map.on('zoomend', this.onMapZoom.bind(this));
      this.map.on('moveend', this.onMapMove.bind(this));
    },

    /**
     * Initialize map controls
     */
    initializeControls: function () {
      var self = this;

      // View mode buttons
      $('#hexagon-view').on('click', function () {
        self.switchView('hexagon');
        $('.view-options .cyber-button').removeClass('active');
        $(this).addClass('active');
      });

      $('#heatmap-view').on('click', function () {
        self.switchView('heatmap');
        $('.view-options .cyber-button').removeClass('active');
        $(this).addClass('active');
      });

      $('#points-view').on('click', function () {
        self.switchView('points');
        $('.view-options .cyber-button').removeClass('active');
        $(this).addClass('active');
      });

      // Map control buttons
      $('#fullscreen-btn').on('click', function () {
        self.toggleFullscreen();
      });

      $('#reset-view-btn').on('click', function () {
        self.resetView();
      });

      $('#screenshot-btn').on('click', function () {
        self.takeScreenshot();
      });
    },

    /**
     * Initialize filter controls
     */
    initializeFilters: function () {
      var self = this;

      // Load filter options from API
      this.loadFilterOptions();
      
      // Set up event handlers
      this.setupFilterEventHandlers();
    },

    /**
     * Load filter options from API endpoints
     */
    loadFilterOptions: function () {
      var self = this;
      
      // Load crime types with fallback
      $.get('/api/amisafe/crime-types')
        .done(function (response) {
          self.populateCrimeTypeFilters(response.crime_types);
        })
        .fail(function (xhr) {
          console.error('Failed to load crime types:', xhr.responseText);
          // Use fallback crime types
          self.populateCrimeTypeFilters({
            '100': 'Murder',
            '200': 'Rape', 
            '300': 'Robbery',
            '400': 'Aggravated Assault',
            '500': 'Burglary',
            '600': 'Theft from Vehicle',
            '700': 'All Other Larceny',
            '800': 'Vandalism',
            '1100': 'Narcotic Drug Law Violations',
            '1400': 'Other Assaults',
            '2600': 'Theft from Person'
          });
        });

      // Load districts with fallback
      $.get('/api/amisafe/districts')
        .done(function (response) {
          self.populateDistrictFilter(response.districts);
        })
        .fail(function (xhr) {
          console.error('Failed to load districts:', xhr.responseText);
          // Use fallback districts
          self.populateDistrictFilter(['1', '2', '3', '5', '6', '7', '8', '9', '12', '14', '15', '16', '17', '18', '19', '22', '24', '25', '26']);
        });

      // Setup date range with fallback
      $.get('/api/amisafe/date-range')
        .done(function (response) {
          self.setupDateRangePicker(response.date_range);
        })
        .fail(function (xhr) {
          console.error('Failed to load date range:', xhr.responseText);
          // Use fallback date range
          self.setupDateRangePicker({
            min: '2025-01-01 00:00:00',
            max: '2025-12-31 23:59:59'
          });
        });
    },

    /**
     * Populate crime type checkboxes
     */
    populateCrimeTypeFilters: function (crimeTypes) {
      var container = $('#crime-type-filters');
      container.empty();
      
      var crimeTypeColors = {
        '100': '#ff0066', '200': '#ff3366', '300': '#ff6600', '400': '#ff9900',
        '500': '#ffcc00', '600': '#ccff00', '700': '#66ff00', '800': '#00ff66',
        '900': '#00ffcc', '1000': '#00ccff', '1100': '#0066ff', '1200': '#3300ff',
        '1300': '#6600ff', '1400': '#9900ff', '1500': '#cc00ff', '1600': '#ff00cc',
        '1700': '#ff0099', '1800': '#ff6699', '2000': '#ff9999', '2100': '#ffcccc',
        '2600': '#ff3333'
      };

      for (var code in crimeTypes) {
        var color = crimeTypeColors[code] || '#00ffff';
        var html = '<div class="crime-type-option">' +
          '<input type="checkbox" id="crime-' + code + '" value="' + code + '" checked>' +
          '<label for="crime-' + code + '" class="cyber-label">' +
          '<span class="crime-indicator" style="background-color: ' + color + '; width: 12px; height: 12px; display: inline-block; margin-right: 8px; border-radius: 2px;"></span>' +
          crimeTypes[code] +
          '</label>' +
          '</div>';
        container.append(html);
      }
    },

    /**
     * Populate district selector
     */
    populateDistrictFilter: function (districts) {
      var select = $('#district-selector');
      select.find('option:not(:first)').remove(); // Keep "ALL DISTRICTS" option
      
      districts.forEach(function (district) {
        select.append('<option value="' + district + '">DISTRICT ' + district + '</option>');
      });
    },

    /**
     * Setup date range picker (using simple date inputs)
     */
    setupDateRangePicker: function (dateRange) {
      var container = $('.filter-section').has('#date-range-picker');
      container.find('#date-range-picker').remove();
      
      var html = '<div class="date-range-inputs">' +
        '<input type="date" id="start-date" class="cyber-input" value="' + dateRange.min.split(' ')[0] + '" min="' + dateRange.min.split(' ')[0] + '" max="' + dateRange.max.split(' ')[0] + '">' +
        '<span class="date-separator">TO</span>' +
        '<input type="date" id="end-date" class="cyber-input" value="' + dateRange.max.split(' ')[0] + '" min="' + dateRange.min.split(' ')[0] + '" max="' + dateRange.max.split(' ')[0] + '">' +
        '</div>';
      
      container.append(html);
    },

    /**
     * Setup all filter event handlers
     */
    setupFilterEventHandlers: function () {
      var self = this;

      // Crime type checkboxes
      $(document).on('change', '#crime-type-filters input[type="checkbox"]', function () {
        self.scheduleFilterUpdate();
      });

      // District selector
      $(document).on('change', '#district-selector', function () {
        self.scheduleFilterUpdate();
      });

      // Date inputs
      $(document).on('change', '#start-date, #end-date', function () {
        self.scheduleFilterUpdate();
      });

      // Hour range sliders
      $(document).on('input', '#hour-range-start, #hour-range-end', function () {
        self.updateHourDisplay();
        self.scheduleFilterUpdate();
      });

      // Filter action buttons
      $(document).on('click', '#apply-filters', function () {
        self.applyFilters();
      });

      $(document).on('click', '#clear-filters', function () {
        self.clearAllFilters();
      });
    },

    /**
     * Update hour range display
     */
    updateHourDisplay: function () {
      var startHour = parseInt($('#hour-range-start').val()) || 0;
      var endHour = parseInt($('#hour-range-end').val()) || 23;
      
      var startTime = (startHour < 10 ? '0' : '') + startHour + ':00';
      var endTime = (endHour < 10 ? '0' : '') + endHour + ':59';
      
      $('#hour-display').text(startTime + ' - ' + endTime);
    },

    /**
     * Schedule filter update (debounced)
     */
    scheduleFilterUpdate: function () {
      var self = this;
      
      if (this.filterUpdateTimeout) {
        clearTimeout(this.filterUpdateTimeout);
      }
      
      this.filterUpdateTimeout = setTimeout(function () {
        self.applyFilters();
      }, 500); // 500ms debounce
    },

    /**
     * Apply current filters to the map
     */
    applyFilters: function () {
      this.showLoading('APPLYING FILTERS...');
      
      var filters = this.getCurrentFilters();
      console.log('Applying filters:', filters);
      
      // Reload hexagon data with filters
      this.loadHexagonData(filters);
    },

    /**
     * Get current filter values
     */
    getCurrentFilters: function () {
      var filters = {};
      
      // Crime types
      var selectedCrimeTypes = [];
      $('#crime-type-filters input[type="checkbox"]:checked').each(function () {
        selectedCrimeTypes.push($(this).val());
      });
      if (selectedCrimeTypes.length > 0) {
        filters.crime_types = selectedCrimeTypes;
      }
      
      // District
      var district = $('#district-selector').val();
      if (district) {
        filters.district = district;
      }
      
      // Date range
      var startDate = $('#start-date').val();
      var endDate = $('#end-date').val();
      if (startDate) {
        filters.start_date = startDate;
      }
      if (endDate) {
        filters.end_date = endDate;
      }
      
      // Hour range
      var hourStart = $('#hour-range-start').val();
      var hourEnd = $('#hour-range-end').val();
      if (hourStart !== undefined && hourStart !== '0') {
        filters.hour_start = hourStart;
      }
      if (hourEnd !== undefined && hourEnd !== '23') {
        filters.hour_end = hourEnd;
      }
      
      return filters;
    },

    /**
     * Clear all filters
     */
    clearAllFilters: function () {
      // Uncheck all crime type checkboxes
      $('#crime-type-filters input[type="checkbox"]').prop('checked', true);
      
      // Reset district selector
      $('#district-selector').val('');
      
      // Reset date inputs to full range
      var startDate = $('#start-date');
      var endDate = $('#end-date');
      if (startDate.length) {
        startDate.val(startDate.attr('min'));
      }
      if (endDate.length) {
        endDate.val(endDate.attr('max'));
      }
      
      // Reset hour sliders
      $('#hour-range-start').val(0);
      $('#hour-range-end').val(23);
      this.updateHourDisplay();
      
      // Apply filters (which will be empty, showing all data)
      this.applyFilters();
    },

    /**
     * Load initial map data
     */
    loadInitialData: function () {
      this.showLoading('LOADING CRIME DATA...');
      
      // Load real hexagon data from API
      this.loadHexagonData();
    },

    /**
     * Load hexagon data from API
     */
    loadHexagonData: function (filters) {
      var self = this;
      
      // Get current map bounds and zoom level
      var bounds = this.map.getBounds();
      var resolution = this.getOptimalResolution(this.map.getZoom());
      
      // Build API request parameters
      var params = {
        resolution: resolution,
        bounds: bounds.getNorth() + ',' + bounds.getEast() + ',' + bounds.getSouth() + ',' + bounds.getWest()
      };
      
      // Add filters if provided
      if (filters) {
        if (filters.crime_types && filters.crime_types.length > 0) {
          params.crime_types = filters.crime_types.join(',');
        }
        if (filters.district) {
          params.districts = filters.district;
        }
        if (filters.start_date) {
          params.start_date = filters.start_date;
        }
        if (filters.end_date) {
          params.end_date = filters.end_date;
        }
        if (filters.hour_start) {
          params.hour_start = filters.hour_start;
        }
        if (filters.hour_end) {
          params.hour_end = filters.hour_end;
        }
      }
      
      // Make API request
      $.ajax({
        url: this.settings.apiEndpoints.aggregated,
        method: 'GET',
        data: params,
        dataType: 'json',
        timeout: 10000,
        success: function (response) {
          console.log('API Response:', response);
          if (response.hexagons && Array.isArray(response.hexagons)) {
            self.renderHexagons(response.hexagons);
            self.updateStats(
              response.meta.count || response.hexagons.length,
              self.calculateOverallThreatLevel(response.hexagons),
              response.hexagons.length
            );
          } else {
            console.warn('Invalid API response format');
            self.loadSampleData(); // Fallback to sample data
          }
          self.hideLoading();
        },
        error: function (xhr, status, error) {
          console.error('API Error:', status, error);
          console.log('XHR Response:', xhr.responseText);
          
          // Show error message but fallback to sample data for development
          self.showMessage('API Error: Using sample data for development');
          self.loadSampleData();
        }
      });
    },

    /**
     * Load sample data for testing (fallback)
     */
    loadSampleData: function () {
      var self = this;
      
      setTimeout(function () {
        // Create sample hexagons around Philadelphia
        var sampleHexagons = self.generateSampleHexagons();
        self.renderHexagons(sampleHexagons);
        self.updateStats(sampleHexagons.length, 'MEDIUM', sampleHexagons.length);
        self.hideLoading();
      }, 1500);
    },

    /**
     * Generate sample hexagon data for testing
     */
    generateSampleHexagons: function () {
      var hexagons = [];
      var centerLat = 39.9526;
      var centerLng = -75.1652;
      
      // Create a grid of sample hexagons
      for (var i = 0; i < 20; i++) {
        var lat = centerLat + (Math.random() - 0.5) * 0.1;
        var lng = centerLng + (Math.random() - 0.5) * 0.1;
        var crimeCount = Math.floor(Math.random() * 50) + 1;
        
        hexagons.push({
          h3_index: 'sample_' + i,
          lat: lat,
          lng: lng,
          crime_count: crimeCount,
          severity_avg: Math.floor(Math.random() * 5) + 1
        });
      }
      
      return hexagons;
    },

    /**
     * Render hexagons on the map
     */
    renderHexagons: function (hexagons) {
      this.hexagonLayer.clearLayers();
      
      var self = this;
      
      hexagons.forEach(function (hexagon) {
        var crimeCount = hexagon.crime_count || hexagon.total_incidents || 0;
        var color = self.getHexagonColor(crimeCount);
        var h3Index = hexagon.h3_index;
        
        if (h3Index && window.h3) {
          // Create actual H3 hexagon using h3-js library
          try {
            var boundary = h3.h3ToGeoBoundary(h3Index, true);
            
            var hexagonPolygon = L.polygon(boundary, {
              fillColor: color,
              fillOpacity: 0.7,
              color: '#00ffff',
              weight: 1,
              className: 'h3-hexagon',
              h3Index: h3Index,
              crimeCount: crimeCount
            });

            hexagonPolygon.on('click', function (e) {
              self.showHexagonPopup(hexagon, e.latlng);
            });

            hexagonPolygon.on('mouseover', function (e) {
              e.target.setStyle({
                weight: 2,
                fillOpacity: 0.9
              });
            });

            hexagonPolygon.on('mouseout', function (e) {
              e.target.setStyle({
                weight: 1,
                fillOpacity: 0.7
              });
            });

            hexagonPolygon.addTo(self.hexagonLayer);
          } catch (error) {
            console.warn('Error creating H3 hexagon for', h3Index, ':', error);
            // Fallback to circle
            self.createFallbackCircle(hexagon, color);
          }
        } else {
          // Fallback to circle if H3 library not available or no h3_index
          console.warn('H3 library not available or missing h3_index, using fallback circle');
          self.createFallbackCircle(hexagon, color);
        }
      });
      
      console.log('Rendered', hexagons.length, 'hexagons on map');
    },

    /**
     * Create fallback circle when H3 hexagon creation fails
     */
    createFallbackCircle: function (hexagon, color) {
      var crimeCount = hexagon.crime_count || hexagon.total_incidents || 0;
      var lat = hexagon.lat || 39.9526;
      var lng = hexagon.lng || -75.1652;
      var radius = Math.max(50, crimeCount * 2);
      
      var circle = L.circle([lat, lng], {
        radius: radius,
        fillColor: color,
        fillOpacity: 0.7,
        color: '#00ffff',
        weight: 2,
        className: 'h3-hexagon-fallback'
      });

      var self = this;
      circle.on('click', function (e) {
        self.showHexagonPopup(hexagon, e.latlng);
      });

      circle.addTo(this.hexagonLayer);
    },

    /**
     * Get hexagon color based on crime count
     */
    getHexagonColor: function (crimeCount) {
      if (crimeCount === 0) return '#0a0a0a';
      if (crimeCount <= 5) return '#1a4d4d';
      if (crimeCount <= 15) return '#00ff00';
      if (crimeCount <= 30) return '#ffff00';
      if (crimeCount <= 50) return '#ff8800';
      return '#ff0000';
    },

    /**
     * Show hexagon popup with crime details
     */
    showHexagonPopup: function (hexagon, latlng) {
      var threatLevel = this.getThreatLevel(hexagon.crime_count);
      
      var popupContent = `
        <div class="crime-popup">
          <h3 class="terminal-text">SECTOR ${hexagon.h3_index.substring(0, 8).toUpperCase()}</h3>
          <div class="crime-stats">
            <div class="stat-line">INCIDENTS: <span class="neon-green">${hexagon.crime_count}</span></div>
            <div class="stat-line">THREAT LEVEL: <span class="threat-${threatLevel.toLowerCase()}">${threatLevel}</span></div>
            <div class="stat-line">SEVERITY: <span class="neon-orange">${hexagon.severity_avg}/5</span></div>
          </div>
          <button class="cyber-button" onclick="AmISafeMap.showFullDetails('${hexagon.h3_index}')">
            &gt; ANALYZE SECTOR_
          </button>
        </div>
      `;
      
      L.popup()
        .setLatLng(latlng)
        .setContent(popupContent)
        .openOn(this.map);
    },

    /**
     * Get threat level based on crime count
     */
    getThreatLevel: function (crimeCount) {
      if (crimeCount <= 5) return 'LOW';
      if (crimeCount <= 20) return 'MEDIUM';
      return 'HIGH';
    },

    /**
     * Calculate overall threat level from hexagon data
     */
    calculateOverallThreatLevel: function (hexagons) {
      if (!hexagons || hexagons.length === 0) return 'LOW';
      
      var totalCrimes = 0;
      var highThreatSectors = 0;
      
      hexagons.forEach(function (hexagon) {
        var crimeCount = hexagon.crime_count || hexagon.total_incidents || 0;
        totalCrimes += crimeCount;
        if (crimeCount > 20) {
          highThreatSectors++;
        }
      });
      
      var avgCrimesPerSector = totalCrimes / hexagons.length;
      var highThreatRatio = highThreatSectors / hexagons.length;
      
      if (avgCrimesPerSector > 15 || highThreatRatio > 0.3) return 'HIGH';
      if (avgCrimesPerSector > 8 || highThreatRatio > 0.1) return 'MEDIUM';
      return 'LOW';
    },

    /**
     * Switch between different view modes
     */
    switchView: function (viewMode) {
      console.log('Switching to view mode:', viewMode);
      this.currentView = viewMode;
      
      // Hide all layers
      this.map.removeLayer(this.hexagonLayer);
      this.map.removeLayer(this.heatmapLayer);
      this.map.removeLayer(this.markersLayer);
      
      // Show selected layer
      switch (viewMode) {
        case 'hexagon':
          this.map.addLayer(this.hexagonLayer);
          break;
        case 'heatmap':
          this.map.addLayer(this.heatmapLayer);
          this.showMessage('Heatmap view - Coming Soon');
          break;
        case 'points':
          this.map.addLayer(this.markersLayer);
          this.showMessage('Points view - Coming Soon');
          break;
      }
    },

    /**
     * Update filters and reload data
     */
    updateFilters: function () {
      console.log('Updating filters...');
      
      // Collect filter values
      var crimeTypes = [];
      $('.filter-checkboxes input[type="checkbox"]:checked').each(function () {
        crimeTypes.push($(this).val());
      });
      
      var districts = $('#district-filter').val() || [];
      
      // Get date range if available
      var dateRange = $('#date-range-picker').val();
      var startDate = null, endDate = null;
      if (dateRange && dateRange.includes(' to ')) {
        var dates = dateRange.split(' to ');
        startDate = dates[0].trim();
        endDate = dates[1].trim();
      }
      
      this.currentFilters = {
        crime_types: crimeTypes,
        districts: districts,
        start_date: startDate,
        end_date: endDate
      };
      
      console.log('Applied filters:', this.currentFilters);
      
      // Reload data with new filters
      this.showLoading('APPLYING FILTERS...');
      this.loadHexagonData();
    },

    /**
     * Handle map zoom events
     */
    onMapZoom: function () {
      var zoom = this.map.getZoom();
      console.log('Map zoom changed to:', zoom);
      
      // Adjust hexagon resolution based on zoom level
      var resolution = this.getOptimalResolution(zoom);
      console.log('Optimal H3 resolution:', resolution);
    },

    /**
     * Handle map move events
     */
    onMapMove: function () {
      // Reload data for new viewport (throttled)
      clearTimeout(this.moveTimeout);
      this.moveTimeout = setTimeout(() => {
        console.log('Map moved, reloading data...');
        this.showLoading('SCANNING NEW SECTORS...');
        this.loadHexagonData();
      }, 500);
    },

    /**
     * Get optimal H3 resolution based on zoom level
     */
    getOptimalResolution: function (zoomLevel) {
      if (zoomLevel <= 10) return 7;
      if (zoomLevel <= 12) return 8;
      if (zoomLevel <= 14) return 9;
      return 10;
    },

    /**
     * Update statistics panel
     */
    updateStats: function (totalIncidents, threatLevel, activeSectors) {
      $('#total-incidents').text(totalIncidents.toLocaleString());
      $('#threat-level').text(threatLevel);
      $('#active-sectors').text(activeSectors);
      
      // Add terminal typing effect
      this.typeText($('#threat-level'), threatLevel);
    },

    /**
     * Terminal typing effect
     */
    typeText: function (element, text) {
      element.empty();
      var i = 0;
      var timer = setInterval(function () {
        element.text(text.substring(0, i + 1));
        i++;
        if (i >= text.length) {
          clearInterval(timer);
          element.append('<span class="cursor">_</span>');
        }
      }, 50);
    },

    /**
     * Show loading overlay
     */
    showLoading: function (message) {
      if (this.loadingOverlay) {
        this.loadingOverlay.querySelector('.terminal-text').textContent = message || 'LOADING...';
        this.loadingOverlay.style.display = 'flex';
      }
    },

    /**
     * Hide loading overlay
     */
    hideLoading: function () {
      if (this.loadingOverlay) {
        this.loadingOverlay.style.display = 'none';
      }
    },

    /**
     * Show error message
     */
    showError: function (message) {
      this.hideLoading();
      console.error('AmISafe Error:', message);
      
      // Show error in the map container
      $(this.container).html(`
        <div class="error-message terminal-text">
          <h3>SYSTEM ERROR</h3>
          <p>${message}</p>
          <button class="cyber-button" onclick="location.reload()">RESTART_SYSTEM</button>
        </div>
      `);
    },

    /**
     * Show temporary message
     */
    showMessage: function (message) {
      var messageDiv = $(`
        <div class="temp-message terminal-text">
          ${message}
        </div>
      `).appendTo('body');
      
      setTimeout(function () {
        messageDiv.fadeOut(500, function () {
          $(this).remove();
        });
      }, 3000);
    },

    /**
     * Toggle fullscreen mode
     */
    toggleFullscreen: function () {
      console.log('Toggle fullscreen');
      // Implementation for fullscreen mode
    },

    /**
     * Reset map view to default
     */
    resetView: function () {
      var mapConfig = this.settings.mapConfig;
      this.map.setView(mapConfig.center, mapConfig.zoom);
    },

    /**
     * Take screenshot of the map
     */
    takeScreenshot: function () {
      console.log('Take screenshot');
      this.showMessage('Screenshot saved to neural storage');
    }
  };

  // Global reference for popup callbacks
  window.AmISafeMap = {
    showFullDetails: function (h3Index) {
      console.log('Show full details for:', h3Index);
      // Implementation for detailed analysis modal
    }
  };

})(jQuery, Drupal, drupalSettings);
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
      console.log('AmISafe behavior attached, context:', context, 'settings:', settings);
      
      if (!settings.amisafe) {
        console.warn('AmISafe settings not found');
        return;
      }

      $(context).find('#crime-map-container').addBack('#crime-map-container').each(function () {
        console.log('Found crime map container:', this);
        if (!this.hasAttribute('data-amisafe-initialized')) {
          console.log('Initializing AmISafe Crime Map...');
          this.setAttribute('data-amisafe-initialized', 'true');
          var crimeMap = new AmISafeCrimeMap(this, settings.amisafe);
          crimeMap.initialize();
        } else {
          console.log('AmISafe already initialized for this container');
        }
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
        
        // Use a slight delay to ensure DOM is fully ready for filter initialization
        var self = this;
        setTimeout(function() {
          self.initializeFilters();
        }, 100);
        
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

      console.log('Initializing filters...');
      
      // Ensure filter selectors exist before loading options
      var retryCount = 0;
      var maxRetries = 10;
      
      function waitForSelectors() {
        var crimeTypeSelector = $('#crime-type-selector');
        var districtSelector = $('#district-selector');
        
        console.log('Checking for selectors... Crime type:', crimeTypeSelector.length, ', District:', districtSelector.length);
        
        if (crimeTypeSelector.length > 0 && districtSelector.length > 0) {
          console.log('Selectors found, loading filter options...');
          
          // Load with immediate fallback to ensure options are populated
          self.loadFilterOptions();
          
          // Also populate with fallback data immediately to ensure something shows
          setTimeout(function() {
            if (crimeTypeSelector.find('option').length === 0) {
              console.log('No options found in crime type selector, using fallback...');
              self.populateCrimeTypeSelector({
                '100': 'Murder',
                '200': 'Rape',
                '300': 'Robbery - Total',
                '400': 'Aggravated Assault - Total',
                '500': 'Burglary - Total',
                '600': 'Theft from Vehicle',
                '700': 'All Other Larceny',
                '800': 'Vandalism',
                '900': 'Fraud',
                '1000': 'Embezzlement',
                '1100': 'Narcotic Drug Law Violations',
                '1200': 'Weapons Violations',
                '1300': 'Prostitution',
                '1400': 'Other Assaults',
                '1500': 'Arson',
                '1600': 'Stolen Property',
                '1700': 'DUI',
                '1800': 'Liquor Laws',
                '2000': 'Public Drunkenness',
                '2100': 'Disorderly Conduct',
                '2600': 'Theft from Person'
              });
            }
            
            if (districtSelector.find('option').length === 0) {
              console.log('No options found in district selector, using fallback...');
              self.populateDistrictSelector(['01', '02', '03', '05', '07', '08', '09', '12', '14', '15', '16', '17']);
            }
          }, 2000);
          
          self.setupFilterEventHandlers();
        } else if (retryCount < maxRetries) {
          retryCount++;
          console.log('Selectors not found, retry', retryCount, 'of', maxRetries);
          setTimeout(waitForSelectors, 200);
        } else {
          console.error('Filter selectors not found after', maxRetries, 'retries');
        }
      }
      
      waitForSelectors();
    },

    /**
     * Load filter options from API endpoints
     */
    loadFilterOptions: function () {
      var self = this;
      
      console.log('Loading filter options...');
      
      // Load crime types for multi-select dropdown
      $.get('/api/amisafe/crime-types')
        .done(function (response) {
          console.log('Crime types API response:', response);
          if (response && response.crime_types) {
            self.populateCrimeTypeSelector(response.crime_types);
          } else {
            console.warn('Invalid crime types response format');
            self.populateCrimeTypeSelector({
              '100': 'Murder',
              '200': 'Rape', 
              '300': 'Robbery - Total',
              '400': 'Aggravated Assault - Total',
              '500': 'Burglary - Total',
              '600': 'Theft from Vehicle',
              '700': 'All Other Larceny',
              '800': 'Vandalism',
              '900': 'Fraud',
              '1000': 'Embezzlement',
              '1100': 'Narcotic Drug Law Violations',
              '1200': 'Weapons Violations',
              '1300': 'Prostitution',
              '1400': 'Other Assaults',
              '1500': 'Arson',
              '1600': 'Stolen Property',
              '1700': 'DUI',
              '1800': 'Liquor Laws',
              '2000': 'Public Drunkenness',
              '2100': 'Disorderly Conduct',
              '2600': 'Theft from Person'
            });
          }
        })
        .fail(function (xhr) {
          console.error('Failed to load crime types:', xhr.responseText);
          // Use fallback crime types
          self.populateCrimeTypeSelector({
            '100': 'Murder',
            '200': 'Rape', 
            '300': 'Robbery - Total',
            '400': 'Aggravated Assault - Total',
            '500': 'Burglary - Total',
            '600': 'Theft from Vehicle',
            '700': 'All Other Larceny',
            '800': 'Vandalism',
            '900': 'Fraud',
            '1000': 'Embezzlement',
            '1100': 'Narcotic Drug Law Violations',
            '1200': 'Weapons Violations',
            '1300': 'Prostitution',
            '1400': 'Other Assaults',
            '1500': 'Arson',
            '1600': 'Stolen Property',
            '1700': 'DUI',
            '1800': 'Liquor Laws',
            '2000': 'Public Drunkenness',
            '2100': 'Disorderly Conduct',
            '2600': 'Theft from Person'
          });
        });

      // Load districts for multi-select dropdown
      $.get('/api/amisafe/districts')
        .done(function (response) {
          self.populateDistrictSelector(response.districts);
        })
        .fail(function (xhr) {
          console.error('Failed to load districts:', xhr.responseText);
          // Use fallback districts
          self.populateDistrictSelector(['01', '02', '03', '05', '07', '08', '09', '12', '14', '15', '16', '17']);
        });
    },

    /**
     * Populate crime type multi-select dropdown
     */
    populateCrimeTypeSelector: function (crimeTypes) {
      console.log('Populating crime type selector with:', crimeTypes);
      var select = $('#crime-type-selector');
      console.log('Crime type selector found:', select.length);
      
      if (select.length === 0) {
        console.error('Crime type selector not found!');
        return;
      }
      
      select.empty();
      
      // Add all crime types as options, selected by default
      for (var code in crimeTypes) {
        var option = $('<option></option>')
          .val(code)
          .text('[' + code + '] ' + crimeTypes[code])
          .prop('selected', true);
        select.append(option);
      }
      
      console.log('Added', Object.keys(crimeTypes).length, 'crime type options to selector');
      
      // Trigger change event to update any dependent UI elements
      select.trigger('change');
    },

    /**
     * Populate district multi-select dropdown
     */
    populateDistrictSelector: function (districts) {
      console.log('Populating district selector with:', districts);
      var select = $('#district-selector');
      console.log('District selector found:', select.length);
      
      if (select.length === 0) {
        console.error('District selector not found!');
        return;
      }
      
      select.empty();
      
      // Add all districts as options, selected by default
      districts.forEach(function (district) {
        var option = $('<option></option>')
          .val(district)
          .text('DISTRICT ' + district)
          .prop('selected', true);
        select.append(option);
      });
      
      console.log('Added', districts.length, 'district options to selector');
      
      // Trigger change event to update any dependent UI elements
      select.trigger('change');
    },

    /**
     * Setup all filter event handlers
     */
    setupFilterEventHandlers: function () {
      var self = this;

      // Multi-select change handlers
      $(document).on('change', '#crime-type-selector, #district-selector, #severity-selector, #time-period-selector', function () {
        self.scheduleFilterUpdate();
      });

      // Date selector change handlers
      $(document).on('change', '#start-month, #end-month', function () {
        self.scheduleFilterUpdate();
      });

      // Preset buttons
      $(document).on('click', '.preset-btn', function () {
        var preset = $(this).data('preset');
        self.applyPreset(preset);
        
        // Toggle active state
        $('.preset-btn').removeClass('active');
        $(this).addClass('active');
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
     * Apply preset filter configurations
     */
    applyPreset: function (preset) {
      var self = this;
      
      // Clear current selections first
      $('#crime-type-selector option').prop('selected', false);
      $('#severity-selector option').prop('selected', false);
      $('#time-period-selector option').prop('selected', true);
      
      switch (preset) {
        case 'violent':
          // Select violent crime types
          $('#crime-type-selector option[value="100"], #crime-type-selector option[value="200"], #crime-type-selector option[value="300"], #crime-type-selector option[value="400"], #crime-type-selector option[value="1500"]').prop('selected', true);
          $('#severity-selector option[value="3"], #severity-selector option[value="4"], #severity-selector option[value="5"]').prop('selected', true);
          break;
          
        case 'property':
          // Select property crime types
          $('#crime-type-selector option[value="500"], #crime-type-selector option[value="600"], #crime-type-selector option[value="700"], #crime-type-selector option[value="800"], #crime-type-selector option[value="1600"]').prop('selected', true);
          $('#severity-selector option[value="1"], #severity-selector option[value="2"], #severity-selector option[value="3"]').prop('selected', true);
          break;
          
        case 'recent':
          // Select all types but limit to recent months
          $('#crime-type-selector option').prop('selected', true);
          $('#severity-selector option').prop('selected', true);
          // Set date to last 3 months
          var currentMonth = new Date().getMonth() + 1;
          var startMonth = Math.max(1, currentMonth - 2);
          $('#start-month').val(startMonth.toString().padStart(2, '0'));
          $('#end-month').val(currentMonth.toString().padStart(2, '0'));
          break;
          
        case 'high-severity':
          // Select all types but only high severity
          $('#crime-type-selector option').prop('selected', true);
          $('#severity-selector option[value="4"], #severity-selector option[value="5"]').prop('selected', true);
          break;
      }
      
      // Apply the preset filters immediately
      this.scheduleFilterUpdate();
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
     * Get current filter values from selectors
     */
    getCurrentFilters: function () {
      var filters = {};
      
      // Crime types from multi-select
      var selectedCrimeTypes = $('#crime-type-selector').val();
      if (selectedCrimeTypes && selectedCrimeTypes.length > 0) {
        filters.crime_types = selectedCrimeTypes;
      }
      
      // Districts from multi-select
      var selectedDistricts = $('#district-selector').val();
      if (selectedDistricts && selectedDistricts.length > 0) {
        filters.districts = selectedDistricts;
      }
      
      // Severity levels from multi-select
      var selectedSeverities = $('#severity-selector').val();
      if (selectedSeverities && selectedSeverities.length > 0) {
        filters.severities = selectedSeverities;
      }
      
      // Date range from month selectors
      var startMonth = $('#start-month').val();
      var endMonth = $('#end-month').val();
      if (startMonth && endMonth) {
        filters.start_date = '2025-' + startMonth + '-01';
        // Get last day of end month
        var lastDay = new Date(2025, parseInt(endMonth), 0).getDate();
        filters.end_date = '2025-' + endMonth + '-' + lastDay;
      }
      
      // Time periods from multi-select
      var selectedTimePeriods = $('#time-period-selector').val();
      if (selectedTimePeriods && selectedTimePeriods.length > 0) {
        filters.time_periods = selectedTimePeriods;
      }
      
      return filters;
    },

    /**
     * Clear all filters to default state
     */
    clearAllFilters: function () {
      // Select all crime types
      $('#crime-type-selector option').prop('selected', true);
      
      // Select all districts
      $('#district-selector option').prop('selected', true);
      
      // Select all severity levels
      $('#severity-selector option').prop('selected', true);
      
      // Reset date range to full year
      $('#start-month').val('01');
      $('#end-month').val('12');
      
      // Select all time periods
      $('#time-period-selector option').prop('selected', true);
      
      // Clear preset button states
      $('.preset-btn').removeClass('active');
      
      // Apply filters (which will show all data)
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
        // Crime types
        if (filters.crime_types && filters.crime_types.length > 0) {
          params.crime_types = filters.crime_types.join(',');
        }
        
        // Districts (updated for new multi-select format)
        if (filters.districts && filters.districts.length > 0) {
          params.districts = filters.districts.join(',');
        }
        
        // Severity levels (new selector-based)
        if (filters.severities && filters.severities.length > 0) {
          params.severities = filters.severities.join(',');
        }
        
        // Date range
        if (filters.start_date) {
          params.start_date = filters.start_date;
        }
        if (filters.end_date) {
          params.end_date = filters.end_date;
        }
        
        // Time periods (new selector-based)
        if (filters.time_periods && filters.time_periods.length > 0) {
          params.time_periods = filters.time_periods.join(',');
        }
        
        // Legacy filters (backwards compatibility)
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
            
            // Calculate real statistics from hexagon data
            var totalIncidents = 0;
            var activeSectors = 0;
            response.hexagons.forEach(function(hexagon) {
              var incidents = hexagon.crime_count || hexagon.total_incidents || 0;
              totalIncidents += incidents;
              if (incidents > 0) {
                activeSectors++;
              }
            });
            
            self.updateStats(
              totalIncidents,
              self.calculateOverallThreatLevel(response.hexagons),
              activeSectors
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
      
      console.log('Rendering', hexagons.length, 'hexagons');
      console.log('H3 library available:', !!window.h3);
      if (window.h3) {
        console.log('H3 functions available:', {
          cellToBoundary: !!h3.cellToBoundary,
          h3ToGeoBoundary: !!h3.h3ToGeoBoundary
        });
      }
      
      var self = this;
      
      hexagons.forEach(function (hexagon) {
        var crimeCount = hexagon.crime_count || hexagon.total_incidents || 0;
        var color = self.getHexagonColor(crimeCount);
        var h3Index = hexagon.h3_index;
        
        if (h3Index && window.h3) {
          // Create actual H3 hexagon using h3-js library v4+ API
          try {
            // Check which H3 API version is available
            var boundary;
            if (h3.cellToBoundary) {
              // H3-js v4+ API
              boundary = h3.cellToBoundary(h3Index, true);
            } else if (h3.h3ToGeoBoundary) {
              // H3-js v3 API
              boundary = h3.h3ToGeoBoundary(h3Index, true);
            } else {
              throw new Error('No compatible H3 boundary function found');
            }
            
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
          <button class="cyber-button" onclick="AmISafeCrimeMap.showFullDetails('${hexagon.h3_index}')">
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
     * Show full detailed analysis for a hexagon
     */
    showFullDetails: function (h3Index) {
      var self = this;
      
      // Show loading modal
      this.showDetailModal('LOADING SECTOR ANALYSIS...', '<div class="loading-spinner">█ ACCESSING DATABASE_</div>');
      
      // Get current filters to apply to detailed data
      var filters = this.getCurrentFilters();
      
      // Fetch detailed hexagon data
      $.ajax({
        url: '/api/amisafe/hexagon/' + h3Index,
        type: 'POST',
        data: JSON.stringify(filters),
        contentType: 'application/json',
        dataType: 'json'
      })
      .done(function (response) {
        self.displayDetailedAnalysis(response);
      })
      .fail(function (xhr, status, error) {
        console.error('Failed to fetch hexagon details:', error);
        self.showDetailModal('ERROR', '<div class="error-text">FAILED TO ACCESS DATABASE<br>CONNECTION SEVERED</div>');
      });
    },

    /**
     * Display detailed analysis modal
     */
    displayDetailedAnalysis: function (data) {
      var hexagonData = data.hexagon_data;
      var threatAnalysis = data.threat_analysis;
      var recommendations = data.recommendations;
      var h3Index = data.h3_index;
      
      // Build crime breakdown
      var crimeBreakdown = '';
      if (hexagonData.crime_breakdown && hexagonData.crime_breakdown.length > 0) {
        crimeBreakdown = '<div class="crime-breakdown"><h4 class="terminal-text">CRIME ANALYSIS</h4>';
        hexagonData.crime_breakdown.slice(0, 5).forEach(function (crime) {
          crimeBreakdown += `
            <div class="crime-item">
              <span class="crime-type">${crime.description}</span>
              <span class="crime-count neon-green">${crime.count} (${crime.percentage}%)</span>
            </div>
          `;
        });
        crimeBreakdown += '</div>';
      }
      
      // Build time distribution
      var timeAnalysis = '';
      if (hexagonData.hourly_distribution) {
        var highActivity = this.getHighActivityPeriods(hexagonData.hourly_distribution);
        timeAnalysis = `
          <div class="time-analysis">
            <h4 class="terminal-text">TEMPORAL PATTERNS</h4>
            <div class="activity-periods">${highActivity}</div>
          </div>
        `;
      }
      
      // Build recommendations
      var recList = '';
      if (recommendations && recommendations.length > 0) {
        recList = '<div class="recommendations"><h4 class="terminal-text">SECURITY PROTOCOLS</h4>';
        recommendations.forEach(function (rec) {
          recList += `<div class="rec-item">&gt; ${rec}</div>`;
        });
        recList += '</div>';
      }
      
      // Build recent incidents
      var recentIncidents = '';
      if (hexagonData.recent_incidents && hexagonData.recent_incidents.length > 0) {
        recentIncidents = '<div class="recent-incidents"><h4 class="terminal-text">RECENT ACTIVITY</h4>';
        hexagonData.recent_incidents.slice(0, 3).forEach(function (incident) {
          recentIncidents += `
            <div class="incident-item">
              <span class="incident-date">${incident.incident_date}</span>
              <span class="incident-type">${incident.ucr_description}</span>
            </div>
          `;
        });
        recentIncidents += '</div>';
      }
      
      var threatColor = this.getThreatColor(threatAnalysis.level);
      
      var content = `
        <div class="detailed-analysis">
          <h2 class="terminal-text">SECTOR ${h3Index.substring(0, 8).toUpperCase()} - FULL ANALYSIS</h2>
          
          <div class="threat-header">
            <div class="threat-badge threat-${threatAnalysis.level.toLowerCase()}" style="color: ${threatColor}">
              █ THREAT LEVEL: ${threatAnalysis.level}
            </div>
            <div class="confidence">CONFIDENCE: ${data.meta.confidence}</div>
          </div>
          
          <div class="analysis-grid">
            <div class="stats-panel">
              <h4 class="terminal-text">SECTOR STATISTICS</h4>
              <div class="stat-line">TOTAL INCIDENTS: <span class="neon-green">${hexagonData.total_incidents}</span></div>
              <div class="stat-line">SEVERITY AVERAGE: <span class="neon-orange">${hexagonData.severity_avg}/5</span></div>
              <div class="stat-line">LAST INCIDENT: <span class="neon-blue">${hexagonData.last_incident}</span></div>
              <div class="stat-line">DISTRICTS: <span class="neon-purple">${hexagonData.districts.join(', ')}</span></div>
            </div>
            
            ${crimeBreakdown}
            ${timeAnalysis}
            ${recentIncidents}
            ${recList}
          </div>
          
          <div class="risk-factors">
            <h4 class="terminal-text">RISK ASSESSMENT</h4>
            ${threatAnalysis.risk_factors.map(factor => `<div class="risk-item">⚠ ${factor}</div>`).join('')}
          </div>
        </div>
      `;
      
      this.showDetailModal('SECTOR ANALYSIS COMPLETE', content);
    },

    /**
     * Get high activity time periods
     */
    getHighActivityPeriods: function (hourlyDist) {
      var periods = [];
      var maxActivity = Math.max.apply(Math, Object.values(hourlyDist));
      
      for (var hour in hourlyDist) {
        if (hourlyDist[hour] >= maxActivity * 0.7) {
          var timeStr = (hour < 10 ? '0' + hour : hour) + ':00';
          periods.push(`${timeStr} (${hourlyDist[hour]} incidents)`);
        }
      }
      
      return periods.join('<br>') || 'NO CLEAR PATTERN DETECTED';
    },

    /**
     * Get threat level color
     */
    getThreatColor: function (level) {
      var colors = {
        'CRITICAL': '#ff0066',
        'HIGH': '#ff3300',
        'MODERATE': '#ffaa00',
        'LOW': '#00ff66',
        'MINIMAL': '#0099ff'
      };
      return colors[level] || '#888888';
    },

    /**
     * Show detail modal
     */
    showDetailModal: function (title, content) {
      // Remove existing modal
      $('.crime-detail-modal').remove();
      
      var modal = $(`
        <div class="crime-detail-modal">
          <div class="modal-overlay"></div>
          <div class="modal-content">
            <div class="modal-header">
              <h3 class="terminal-text">${title}</h3>
              <button class="close-btn" onclick="AmISafeCrimeMap.closeDetailModal()">[X]</button>
            </div>
            <div class="modal-body">
              ${content}
            </div>
          </div>
        </div>
      `);
      
      $('body').append(modal);
      
      // Close on overlay click
      modal.find('.modal-overlay').on('click', function () {
        AmISafeCrimeMap.closeDetailModal();
      });
    },

    /**
     * Close detail modal
     */
    closeDetailModal: function () {
      $('.crime-detail-modal').remove();
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
      if (!hexagons || hexagons.length === 0) return 'MINIMAL';
      
      var totalCrimes = 0;
      var highThreatSectors = 0;
      var criticalSectors = 0;
      var severityScores = [];
      
      hexagons.forEach(function (hexagon) {
        var crimeCount = hexagon.crime_count || hexagon.total_incidents || 0;
        var avgSeverity = hexagon.severity_avg || 2;
        
        totalCrimes += crimeCount;
        severityScores.push(avgSeverity);
        
        if (crimeCount > 30) {
          criticalSectors++;
        } else if (crimeCount > 15) {
          highThreatSectors++;
        }
      });
      
      var avgCrimesPerSector = totalCrimes / hexagons.length;
      var avgSeverity = severityScores.reduce((a, b) => a + b, 0) / severityScores.length;
      var criticalRatio = criticalSectors / hexagons.length;
      var highThreatRatio = highThreatSectors / hexagons.length;
      
      // Calculate threat level based on multiple factors
      if (avgCrimesPerSector > 25 || criticalRatio > 0.2 || avgSeverity > 4) return 'CRITICAL';
      if (avgCrimesPerSector > 15 || highThreatRatio > 0.3 || avgSeverity > 3) return 'HIGH';
      if (avgCrimesPerSector > 8 || highThreatRatio > 0.1 || avgSeverity > 2.5) return 'MODERATE';
      if (avgCrimesPerSector > 3 || totalCrimes > 0) return 'LOW';
      return 'MINIMAL';
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
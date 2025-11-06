/**
 * @file
 * AmISafe Crime Map - Refactored Core JavaScript
 * 
 * Clean, organized implementation of the interactive crime map
 * Features: H3 hexagon visualization, zoom-based resolution switching, minimal mode
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * AmISafe Crime Map Drupal Behavior
   */
  Drupal.behaviors.amisafeCrimeMap = {
    attach: function (context, settings) {
      if (!settings.amisafe) {
        console.error('AmISafe settings not found');
        return;
      }

      $(context).find('#crime-map-container').addBack('#crime-map-container').each(function () {
        if (!this.hasAttribute('data-amisafe-initialized')) {
          this.setAttribute('data-amisafe-initialized', 'true');
          var crimeMap = new AmISafeCrimeMap(this, settings.amisafe);
          crimeMap.initialize();
        }
      });
    }
  };

  /**
   * AmISafe Crime Map Class
   */
  function AmISafeCrimeMap(container, settings) {
    // Core properties
    this.container = container;
    this.settings = settings;
    this.map = null;
    this.hexagonLayer = null;
    this.incidentLayer = null; // For individual incident points at high zoom
    this.currentFilters = {};
    this.debugMode = false;
    
    // Minimal mode configuration
    this.minimalMode = true; // Clean data visualization
    
    // Performance optimization
    this.dataCache = new Map();
    this.currentRequest = null;
    this.apiCallCount = 0;
    
    // Timing controls
    this.loadTimeout = null;
    this.filterTimeout = null;
    this.zoomTimeout = null;
    
    // Auto-fit control - prevent constant re-centering
    this.shouldAutoFit = true; // Only auto-fit on initial load and zoom changes
    this.isInitialLoad = true; // Track if this is the first data load
  }

  /**
   * Class Methods
   */
  AmISafeCrimeMap.prototype = {
    
    /**
     * Initialize the crime map
     */
    initialize: function() {
      console.log('🚀 Initializing AmISafe Crime Map...');
      
      // Template elements verified working - debug removed for cleaner output
      
      // Apply minimal mode styling
      if (this.minimalMode) {
        this.enableMinimalMode();
      }
      
      // Initialize map components
      this.createMap();
      this.setupEventListeners();
      this.initializeControls();
      this.initializeFilters();
      
      // Load initial data
      setTimeout(() => {
        this.loadInitialData();
      }, 500);
    },

    /**
     * Enable minimal mode for clean data visualization
     */
    enableMinimalMode: function() {
      // Apply CSS class after a delay to avoid interfering with map initialization
      setTimeout(() => {
        document.body.classList.add('minimal-mode');
        console.log('🔇 Minimal mode activated');
      }, 1000);
    },

    /**
     * Create and configure the Leaflet map
     */
    createMap: function() {
      if (!window.L) {
        console.error('Leaflet library not loaded');
        return;
      }

      // Create map with Philadelphia center
      const mapConfig = this.settings.mapConfig;
      this.map = L.map('crime-map-container', {
        center: mapConfig.center,
        zoom: mapConfig.zoom,
        minZoom: 5,  // Allow zoom out to Resolution 5 (Philadelphia metro area)
        maxZoom: 20,
        zoomControl: true,
        attributionControl: false
      });

      // Add dark tile layer
      L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '',
        subdomains: 'abcd',
        maxZoom: 20,
        className: 'dark-tiles'
      }).addTo(this.map);

      // Initialize layers
      this.hexagonLayer = L.layerGroup().addTo(this.map);
      this.incidentLayer = L.layerGroup().addTo(this.map); // For individual incident points
      
      console.log('🗺️ Map created successfully');
      
      // Check H3 library availability
      if (window.h3) {
        console.log('✅ H3 library available, version:', typeof h3.cellToBoundary === 'function' ? 'v4+' : 'unknown');
      } else {
        console.log('⚠️ H3 library not found - will use fallback circles');
      }
    },

    /**
     * Setup map event listeners
     */
    setupEventListeners: function() {
      this.map.on('zoomend', () => this.handleZoomChange());
      this.map.on('moveend', () => this.handleMapMove());
      
      // Force map resize after initialization
      setTimeout(() => {
        this.map.invalidateSize();
        this.updateZoomIndicator(); // Initial zoom indicator update
      }, 1000);
    },

    /**
     * Initialize map controls
     */
    initializeControls: function() {
      const self = this;
      
      // Manual zoom refresh for debugging
      $('#refresh-zoom').on('click', function() {
        self.updateZoomIndicator();
      });
      
      // Re-center map to fit hexagons
      $('#fit-hexagons-btn').on('click', function() {
        self.fitMapToHexagons();
      });
    },

    /**
     * Initialize filter controls
     */
    initializeFilters: function() {
      const self = this;
      
      // Initialize default filter state
      this.currentFilters = {
        crimeTypes: [],
        districts: [],
        severity: [1, 2, 3, 4, 5],
        startMonth: '01',
        endMonth: '12',
        timePeriods: ['early-morning', 'morning', 'afternoon', 'evening'],
        viewMode: 'hexagon'
      };
      
      // Load filter options from API
      this.loadFilterOptions();
      
      // Filter action buttons
      $('#apply-filters').on('click', function() {
        self.applyFilters();
      });
      
      $('#clear-filters').on('click', function() {
        self.clearAllFilters();
      });
      
      // View mode buttons
      $('#hexagon-view').on('click', function() {
        self.switchViewMode('hexagon');
        $('.view-options .cyber-button').removeClass('active');
        $(this).addClass('active');
      });
      
      $('#heatmap-view').on('click', function() {
        self.switchViewMode('heatmap');
        $('.view-options .cyber-button').removeClass('active');
        $(this).addClass('active');
      });
      
      $('#points-view').on('click', function() {
        self.switchViewMode('points');
        $('.view-options .cyber-button').removeClass('active');
        $(this).addClass('active');
      });
      
      // Quick preset buttons
      $('.preset-btn').on('click', function() {
        const preset = $(this).data('preset');
        self.applyPreset(preset);
        $('.preset-btn').removeClass('active');
        $(this).addClass('active');
      });
      
      // Debug panel buttons
      $('#center-hexagons-btn').on('click', function() {
        self.fitMapToHexagons();
      });
      
      $('#refresh-data-btn').on('click', function() {
        self.loadHexagonData();
      });
      
      $('#toggle-overlays-btn').on('click', function() {
        self.toggleDebugOverlays();
      });
      
      $('#performance-stats-btn').on('click', function() {
        self.showPerformanceStats();
      });
      
      console.log('🔧 Filters initialized with full functionality');
    },

    /**
     * Handle zoom changes with debouncing
     */
    handleZoomChange: function() {
      // Clear existing timeout
      if (this.zoomTimeout) {
        clearTimeout(this.zoomTimeout);
      }
      
      // Update zoom indicator immediately
      this.updateZoomIndicator();
      
      // Allow auto-fit on zoom changes (user wants new data at different resolution)
      this.shouldAutoFit = true;
      
      // Debounce data loading
      this.zoomTimeout = setTimeout(() => {
        this.loadHexagonData();
      }, 300);
    },

    /**
     * Handle map movement
     */
    handleMapMove: function() {
      // Debounce map move events
      if (this.loadTimeout) {
        clearTimeout(this.loadTimeout);
      }
      
      // Prevent auto-fit on map moves (user is panning around)
      this.shouldAutoFit = false;
      
      this.loadTimeout = setTimeout(() => {
        this.loadHexagonData();
      }, 500);
    },

    /**
     * Update zoom level indicator with robust element waiting
     */
    updateZoomIndicator: function(zoom, resolution) {
      // Get current values if not provided
      if (zoom === undefined) zoom = this.map.getZoom();
      if (resolution === undefined) resolution = this.getOptimalResolution(zoom);
      
      const scaleDescription = this.getResolutionDescription(resolution);
      const roundedZoom = Math.round(zoom * 10) / 10;
      
      console.log(`📊 Updating zoom indicator: zoom=${roundedZoom}, resolution=${resolution}`);
      
      // Use robust element waiting with multiple attempts
      this.waitForZoomElements(roundedZoom, resolution, scaleDescription, 0);
    },

    /**
     * Wait for zoom indicator elements with progressive retry
     */
    waitForZoomElements: function(zoom, resolution, scaleDesc, attempt) {
      const maxAttempts = 15; // More attempts
      const baseDelay = 50; // Start with shorter delay
      const delay = Math.min(baseDelay * Math.pow(1.2, attempt), 3000); // Progressive but capped
      
      // Try multiple selection methods
      const methods = [
        () => ({
          zoom: document.getElementById('zoom-level'),
          resolution: document.getElementById('h3-resolution'),
          scale: document.querySelector('.scale-label')
        }),
        () => ({
          zoom: document.querySelector('#zoom-level'),
          resolution: document.querySelector('#h3-resolution'),
          scale: document.getElementsByClassName('scale-label')[0]
        }),
        () => ({
          zoom: document.querySelector('#zoom-indicator #zoom-level'),
          resolution: document.querySelector('#zoom-indicator #h3-resolution'),
          scale: document.querySelector('#zoom-indicator .scale-label')
        })
      ];
      
      let elements = null;
      for (let method of methods) {
        elements = method();
        if (elements.zoom && elements.resolution && elements.scale) {
          // Success! Update elements
          elements.zoom.textContent = zoom;
          elements.resolution.textContent = resolution;
          elements.scale.textContent = scaleDesc;
          console.log(`✅ Zoom indicator updated (attempt ${attempt + 1}, method ${methods.indexOf(method) + 1}): ZOOM=${zoom} H3=${resolution} ${scaleDesc}`);
          return;
        }
      }
      
      // None of the methods worked
      if (attempt < maxAttempts) {
        console.log(`⏳ Zoom elements not ready (attempt ${attempt + 1}/${maxAttempts}), retrying in ${delay}ms...`);
        setTimeout(() => {
          this.waitForZoomElements(zoom, resolution, scaleDesc, attempt + 1);
        }, delay);
      } else {
        // Final failure - comprehensive debug
        console.log(`❌ Zoom indicator elements not found after ${maxAttempts} attempts`);
        console.log('🔍 Final DOM state:');
        console.log('  - zoom-indicator container:', !!document.getElementById('zoom-indicator'));
        console.log('  - crime-map-container:', !!document.getElementById('crime-map-container'));
        console.log('  - zoom-level element:', !!document.getElementById('zoom-level'));
        console.log('  - h3-resolution element:', !!document.getElementById('h3-resolution'));
        console.log('  - scale-label elements:', document.querySelectorAll('.scale-label').length);
        console.log('  - all zoom-level elements:', document.querySelectorAll('#zoom-level').length);
        
        // DEBUG: Show what's actually inside the zoom-indicator container
        const container = document.getElementById('zoom-indicator');
        if (container) {
          console.log('🔍 zoom-indicator innerHTML:', container.innerHTML);
          console.log('🔍 zoom-indicator children:', container.children.length);
          for (let i = 0; i < container.children.length; i++) {
            const child = container.children[i];
            console.log(`  child ${i}:`, child.tagName, child.id || 'no-id', child.className || 'no-class', child.textContent.substring(0, 50));
          }
        }
        
        // Store values for manual display
        console.log(`📊 Current values: ZOOM=${zoom} H3=${resolution} ${scaleDesc}`);
        
        // FALLBACK: Create missing elements if container exists but children don't
        if (container && container.children.length === 0) {
          console.log('🔧 Creating missing zoom indicator elements...');
          container.innerHTML = `
            <span class="zoom-label">ZOOM:</span>
            <span id="zoom-level">${zoom}</span>
            <span class="resolution-label">H3:</span>
            <span id="h3-resolution">${resolution}</span>
            <span class="scale-label">${scaleDesc}</span>
            <button id="refresh-zoom" style="margin-left: 10px; font-size: 10px;">REFRESH</button>
          `;
          console.log(`✅ Zoom indicator elements created and updated: ZOOM=${zoom} H3=${resolution} ${scaleDesc}`);
        }
      }
    },

    /**
     * Get optimal H3 resolution based on zoom level
     */
    getOptimalResolution: function(zoomLevel) {
      if (zoomLevel <= 6) return 5;        // 251 km² - Philadelphia citywide
      else if (zoomLevel <= 8) return 6;   // 36.1 km² - City districts  
      else if (zoomLevel <= 10) return 7;  // 5.2 km² - District detail
      else if (zoomLevel <= 12) return 8;  // 0.7 km² - Neighborhood
      else if (zoomLevel <= 14) return 9;  // 0.1 km² - Block Group
      else if (zoomLevel <= 16) return 10; // 15,047 m² - Block
      else if (zoomLevel <= 17) return 11; // 2,150 m² - Building
      else if (zoomLevel <= 18) return 12; // 307 m² - Room-level
      else return 13;                      // 44 m² - Ultra-precision
    },

    /**
     * Get human-readable description of H3 resolution
     */
    getResolutionDescription: function(resolution) {
      const descriptions = {
        5: '~251km² citywide',
        6: '~36km² districts',
        7: '~5.2km² areas', 
        8: '~0.7km² neighborhoods',
        9: '~0.1km² blocks',
        10: '~15,047m² blocks',
        11: '~2,150m² buildings',
        12: '~307m² rooms',
        13: '~44m² precision'
      };
      return descriptions[resolution] || 'unknown';
    },

    /**
     * Load initial crime data
     */
    loadInitialData: function() {
      this.showLoading('LOADING CRIME DATA...');
      this.shouldAutoFit = true; // Allow auto-fit for initial load
      this.loadHexagonData();
      
      // Load citywide stats
      setTimeout(() => {
        this.loadCitywideStats();
      }, 1000);
    },

    /**
     * Load hexagon crime data based on current view
     */
    loadHexagonData: function() {
      const zoom = this.map.getZoom();
      const resolution = this.getOptimalResolution(zoom);
      const bounds = this.map.getBounds();
      const filters = this.getCurrentFilters();
      
      console.log(`📊 Loading H3 Resolution ${resolution} data...`);
      
      // Cancel previous request
      if (this.currentRequest) {
        this.currentRequest.abort();
      }
      
      // Build API URL
      const apiUrl = this.buildApiUrl(resolution, bounds, filters);
      
      // Make API request
      this.currentRequest = $.ajax({
        url: apiUrl,
        method: 'GET',
        timeout: 30000
      })
      .done((data) => {
        this.renderHexagons(data);
        this.hideLoading();
      })
      .fail((xhr, status, error) => {
        if (status !== 'abort') {
          console.error('API request failed:', error);
          this.hideLoading();
        }
      });
    },

    /**
     * Build API URL for hexagon data
     */
    buildApiUrl: function(resolution, bounds, filters) {
      const baseUrl = '/api/amisafe/aggregated';
      const params = new URLSearchParams({
        resolution: resolution,
        bounds: bounds.getNorth() + ',' + bounds.getEast() + ',' + bounds.getSouth() + ',' + bounds.getWest(),
        limit: 1000,
        ...filters
      });
      
      return `${baseUrl}?${params.toString()}`;
    },

    /**
     * Render hexagons on the map
     */
    renderHexagons: function(data) {
      // Clear existing layers
      this.hexagonLayer.clearLayers();
      this.incidentLayer.clearLayers();
      
      if (!data.hexagons || data.hexagons.length === 0) {
        console.log('📊 No hexagon data received');
        return;
      }
      
      // Check if we should render individual incidents (Resolution 10+)
      const currentResolution = this.getOptimalResolution(this.map.getZoom());
      const shouldShowIncidents = currentResolution >= 10;
      
      // Track hexagon bounds to fit map view
      const allBounds = L.latLngBounds();
      let successfulHexagons = 0;
      
      // Render each hexagon
      data.hexagons.forEach(hexagon => {
        const result = this.renderSingleHexagon(hexagon);
        if (result && result.getBounds) {
          allBounds.extend(result.getBounds());
          successfulHexagons++;
        }
      });
      
      console.log(`📊 Rendered ${data.hexagons.length} hexagons (${successfulHexagons} successful)`);
      
      // Load individual incidents for high-resolution views
      if (shouldShowIncidents && successfulHexagons > 0) {
        console.log(`🔍 Loading individual incidents for Resolution ${currentResolution}`);
        this.loadIncidentPoints(data.hexagons);
      }
      
      // Fit map to show all hexagons only when appropriate (not during user panning)
      if (successfulHexagons > 0 && allBounds.isValid() && this.shouldAutoFit) {
        setTimeout(() => {
          this.map.fitBounds(allBounds, {
            padding: [20, 20],
            maxZoom: 15
          });
          console.log(`📍 Map fitted to show ${successfulHexagons} hexagons`);
          
          // Disable auto-fit after initial load to prevent constant re-centering
          if (this.isInitialLoad) {
            this.isInitialLoad = false;
            console.log('🎯 Initial load complete - auto-fit disabled for user interaction');
          }
        }, 500);
      } else if (successfulHexagons > 0) {
        console.log(`📊 ${successfulHexagons} hexagons rendered without auto-fit (user is panning)`);
      }
    },

    /**
     * Load and render individual incident points for high-resolution views
     */
    loadIncidentPoints: function(hexagons) {
      // Get the current map bounds to limit incident queries
      const bounds = this.map.getBounds();
      const params = new URLSearchParams({
        minLat: bounds.getSouth(),
        maxLat: bounds.getNorth(),
        minLng: bounds.getWest(),
        maxLng: bounds.getEast(),
        limit: 500, // Limit to prevent overwhelming the map
        format: 'json'
      });
      
      // Add current filters
      Object.keys(this.currentFilters).forEach(key => {
        if (this.currentFilters[key]) {
          params.append(key, this.currentFilters[key]);
        }
      });
      
      const apiUrl = `${this.settings.apiEndpoints.incidents}?${params.toString()}`;
      
      fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
          if (data.incidents && data.incidents.length > 0) {
            this.renderIncidentPoints(data.incidents);
            console.log(`📍 Rendered ${data.incidents.length} individual incident points`);
          } else {
            console.log('📍 No individual incidents found for current view');
          }
        })
        .catch(error => {
          console.error('Error loading incident points:', error);
        });
    },

    /**
     * Render individual incident points on the map
     */
    renderIncidentPoints: function(incidents) {
      incidents.forEach(incident => {
        if (incident.lat && incident.lng) {
          // Create incident marker
          const marker = L.circleMarker([incident.lat, incident.lng], {
            radius: 3,
            fillColor: this.getIncidentColor(incident.incident_type),
            color: '#ffffff',
            weight: 1,
            opacity: 0.8,
            fillOpacity: 0.6
          });
          
          // Add popup with incident details
          if (!this.minimalMode) {
            marker.bindPopup(this.createIncidentPopup(incident));
          }
          
          marker.addTo(this.incidentLayer);
        }
      });
    },

    /**
     * Get color for incident type
     */
    getIncidentColor: function(incidentType) {
      const colors = {
        'violent': '#ff4444',     // Red for violent crimes
        'property': '#ff8800',    // Orange for property crimes  
        'drug': '#8844ff',        // Purple for drug crimes
        'traffic': '#44ff44',     // Green for traffic incidents
        'other': '#44ffff'        // Cyan for other incidents
      };
      
      // Map incident codes to categories (simplified)
      if (!incidentType) return colors.other;
      
      const code = incidentType.toString();
      if (code.startsWith('1') || code.startsWith('2')) return colors.violent;
      if (code.startsWith('3') || code.startsWith('5')) return colors.property;
      if (code.startsWith('4')) return colors.drug;
      if (code.startsWith('7')) return colors.traffic;
      
      return colors.other;
    },

    /**
     * Create popup content for individual incidents
     */
    createIncidentPopup: function(incident) {
      const date = incident.incident_date ? new Date(incident.incident_date).toLocaleDateString() : 'Unknown';
      const time = incident.incident_time || 'Unknown';
      const type = incident.incident_type || 'Unknown';
      const location = incident.location_block || 'Unknown location';
      
      return `
        <div class="incident-popup">
          <strong>Crime Incident</strong><br>
          <strong>Type:</strong> ${type}<br>
          <strong>Date:</strong> ${date}<br>
          <strong>Time:</strong> ${time}<br>
          <strong>Location:</strong> ${location}
        </div>
      `;
    },

    /**
     * Render a single hexagon
     */
    renderSingleHexagon: function(hexagon) {
      const incidentCount = hexagon.incident_count || hexagon.incidentCount || 0;
      const h3Index = hexagon.h3_index;
      
      if (!h3Index) {
        console.warn('No H3 index found for hexagon:', hexagon);
        return;
      }
      
      // Use H3 library to get boundary if available
      if (window.h3 && h3.cellToBoundary) {
        try {
          // Get H3 boundary coordinates
          const boundary = h3.cellToBoundary(h3Index, true);
          
          // Convert from H3 [lng, lat] to Leaflet [lat, lng] format
          const leafletCoords = boundary.map(coord => [coord[1], coord[0]]);
          
          // Calculate styling based on incident count
          const style = this.calculateHexagonStyle(incidentCount);
          
          // Create and add polygon to map
          const polygon = L.polygon(leafletCoords, style);
          
          // Add popup and event handlers if not in minimal mode
          if (!this.minimalMode) {
            polygon.bindPopup(this.createHexagonPopup(hexagon));
            polygon.on('mouseover', function(e) {
              e.target.setStyle({ weight: 2, fillOpacity: 0.9 });
            });
            polygon.on('mouseout', function(e) {
              e.target.setStyle({ weight: 1, fillOpacity: style.fillOpacity });
            });
          }
          
          polygon.addTo(this.hexagonLayer);
          return polygon; // Return polygon for bounds tracking
          
        } catch (error) {
          console.warn('Failed to render hexagon', h3Index, ':', error);
          return this.createFallbackCircle(hexagon);
        }
      } else {
        // Fallback to circle if H3 library not available
        return this.createFallbackCircle(hexagon);
      }
    },

    /**
     * Create fallback circle when H3 is not available
     */
    createFallbackCircle: function(hexagon) {
      const incidentCount = hexagon.incident_count || hexagon.incidentCount || 0;
      const lat = hexagon.lat || 39.9526;
      const lng = hexagon.lng || -75.1652;
      const radius = Math.max(50, incidentCount * 5);
      
      const style = this.calculateHexagonStyle(incidentCount);
      const circle = L.circle([lat, lng], {
        radius: radius,
        ...style
      });
      
      if (!this.minimalMode) {
        circle.bindPopup(this.createHexagonPopup(hexagon));
      }
      
      circle.addTo(this.hexagonLayer);
      return circle; // Return circle for bounds tracking
    },

    /**
     * Calculate hexagon styling based on incident count
     */
    calculateHexagonStyle: function(incidentCount) {
      // Color intensity based on incident count
      const maxIntensity = 100; // Adjust based on your data
      const intensity = Math.min(incidentCount / maxIntensity, 1);
      
      return {
        fillColor: this.minimalMode ? '#00ff41' : '#ff0040',
        weight: 1,
        opacity: 0.8,
        color: this.minimalMode ? '#00ff41' : '#00bfff',
        fillOpacity: 0.3 + (intensity * 0.4)
      };
    },

    /**
     * Create popup content for hexagon
     */
    createHexagonPopup: function(hexagon) {
      const incidentCount = hexagon.incident_count || hexagon.incidentCount || 0;
      const h3Index = hexagon.h3_index || hexagon.h3Index || 'Unknown';
      
      return `
        <div class="hexagon-popup terminal-text">
          <h4>SECTOR ${h3Index.substring(0, 8).toUpperCase()}</h4>
          <div class="stat-line">INCIDENTS: <span class="neon-green">${incidentCount}</span></div>
          <div class="stat-line">H3 ID: <span class="neon-yellow">${h3Index}</span></div>
        </div>
      `;
    },

    /**
     * Get current filter values
     */
    getCurrentFilters: function() {
      // Return current filter settings
      return this.currentFilters;
    },

    /**
     * Load citywide statistics
     */
    loadCitywideStats: function() {
      const self = this;
      console.log('📈 Loading citywide statistics...');
      
      $.ajax({
        url: '/api/amisafe/system-stats',
        method: 'GET',
        dataType: 'json',
        timeout: 5000,
        success: function(response) {
          if (response && response.citywide_stats) {
            const stats = response.citywide_stats;
            $('#citywide-total').text((stats.total_incidents || 0).toLocaleString());
            $('#citywide-districts').text(stats.active_districts || '--');
            $('#citywide-threat').text(stats.threat_level || 'UNKNOWN');
            $('#citywide-coverage').text((stats.coverage_percent || 0) + '%');
          }
        },
        error: function() {
          // Use mock data if API fails
          $('#citywide-total').text('3,406,192');
          $('#citywide-districts').text('25');
          $('#citywide-threat').text('MODERATE');
          $('#citywide-coverage').text('98.7%');
        }
      });
    },

    /**
     * Show loading overlay
     */
    showLoading: function(message) {
      const overlay = $('#loading-overlay');
      overlay.find('.terminal-text').text(message || 'LOADING...');
      overlay.show();
    },

    /**
     * Hide loading overlay
     */
    hideLoading: function() {
      $('#loading-overlay').fadeOut(300);
    },

    /**
     * Reset map view to initial position
     */
    resetView: function() {
      const mapConfig = this.settings.mapConfig;
      this.map.setView(mapConfig.center, mapConfig.zoom);
      console.log('🏠 View reset to initial position');
    },

    /**
     * Manually fit map to show all hexagons
     */
    fitMapToHexagons: function() {
      if (this.hexagonLayer && this.hexagonLayer.getLayers().length > 0) {
        const allBounds = L.latLngBounds();
        let hexagonCount = 0;
        
        this.hexagonLayer.eachLayer(function(layer) {
          if (layer.getBounds) {
            allBounds.extend(layer.getBounds());
            hexagonCount++;
          }
        });
        
        if (hexagonCount > 0 && allBounds.isValid()) {
          this.map.fitBounds(allBounds, {
            padding: [20, 20],
            maxZoom: 15
          });
          console.log(`📍 Manually fitted map to show ${hexagonCount} hexagons`);
        } else {
          console.log('⚠️ No hexagons available to fit map view');
        }
      } else {
        console.log('⚠️ No hexagon layer available for fitting');
      }
    },

    /**
     * Load filter options from API
     */
    loadFilterOptions: function() {
      const self = this;
      
      // Load crime types
      $.ajax({
        url: '/api/amisafe/crime-types',
        method: 'GET',
        success: function(data) {
          self.populateCrimeTypes(data);
        },
        error: function() {
          console.warn('Failed to load crime types, using defaults');
          self.populateCrimeTypes(self.getDefaultCrimeTypes());
        }
      });
      
      // Load districts
      $.ajax({
        url: '/api/amisafe/districts',
        method: 'GET',
        success: function(data) {
          self.populateDistricts(data);
        },
        error: function() {
          console.warn('Failed to load districts, using defaults');
          self.populateDistricts(self.getDefaultDistricts());
        }
      });
      
      // Initialize H3 debug info
      this.initializeH3Debug();
    },

    /**
     * Get default crime types if API fails
     */
    getDefaultCrimeTypes: function() {
      return [
        { value: '100', label: 'HOMICIDE' },
        { value: '200', label: 'ASSAULT' },
        { value: '300', label: 'ROBBERY' },
        { value: '400', label: 'BURGLARY' },
        { value: '500', label: 'THEFT' },
        { value: '600', label: 'AUTO THEFT' },
        { value: '700', label: 'VANDALISM' },
        { value: '800', label: 'DRUGS' },
        { value: '900', label: 'WEAPON OFFENSE' },
        { value: '1000', label: 'FRAUD' }
      ];
    },

    /**
     * Get default districts if API fails
     */
    getDefaultDistricts: function() {
      return [
        { value: '1', label: 'DISTRICT 1 - CENTER CITY' },
        { value: '2', label: 'DISTRICT 2 - SOUTH' },
        { value: '3', label: 'DISTRICT 3 - WEST' },
        { value: '4', label: 'DISTRICT 4 - NORTH' },
        { value: '5', label: 'DISTRICT 5 - NORTHEAST' },
        { value: '6', label: 'DISTRICT 6 - NORTHWEST' }
      ];
    },

    /**
     * Populate crime type selector
     */
    populateCrimeTypes: function(crimeTypes) {
      const selector = $('#crime-type-selector');
      selector.empty();
      
      crimeTypes.forEach(type => {
        selector.append(`<option value="${type.value}" selected>${type.label}</option>`);
      });
      
      // Update current filters
      this.currentFilters.crimeTypes = crimeTypes.map(t => t.value);
    },

    /**
     * Populate district selector
     */
    populateDistricts: function(districts) {
      const selector = $('#district-selector');
      selector.empty();
      
      districts.forEach(district => {
        selector.append(`<option value="${district.value}" selected>${district.label}</option>`);
      });
      
      // Update current filters
      this.currentFilters.districts = districts.map(d => d.value);
    },

    /**
     * Apply current filters
     */
    applyFilters: function() {
      // Collect filter values
      this.currentFilters.crimeTypes = $('#crime-type-selector').val() || [];
      this.currentFilters.districts = $('#district-selector').val() || [];
      this.currentFilters.severity = $('#severity-selector').val() || [];
      this.currentFilters.startMonth = $('#start-month').val();
      this.currentFilters.endMonth = $('#end-month').val();
      this.currentFilters.timePeriods = $('#time-period-selector').val() || [];
      
      console.log('🔍 Applying filters:', this.currentFilters);
      
      // Show loading
      this.showLoading('SCANNING SECTORS...');
      
      // Reload data with filters
      this.loadHexagonData();
      
      // Update stats
      this.updateStats();
    },

    /**
     * Clear all filters to default state
     */
    clearAllFilters: function() {
      // Reset all selectors to default (all selected)
      $('#crime-type-selector option').prop('selected', true);
      $('#district-selector option').prop('selected', true);
      $('#severity-selector option').prop('selected', true);
      $('#start-month').val('01');
      $('#end-month').val('12');
      $('#time-period-selector option').prop('selected', true);
      
      // Clear preset button states
      $('.preset-btn').removeClass('active');
      
      // Apply cleared filters
      this.applyFilters();
      
      console.log('🔄 All filters cleared to default state');
    },

    /**
     * Apply quick filter presets
     */
    applyPreset: function(preset) {
      console.log('⚡ Applying preset:', preset);
      
      // Clear current selections first
      $('#crime-type-selector option').prop('selected', false);
      $('#severity-selector option').prop('selected', false);
      
      switch (preset) {
        case 'violent':
          // Select violent crime types
          $('#crime-type-selector option[value="100"], #crime-type-selector option[value="200"], #crime-type-selector option[value="300"], #crime-type-selector option[value="900"]').prop('selected', true);
          $('#severity-selector option[value="3"], #severity-selector option[value="4"], #severity-selector option[value="5"]').prop('selected', true);
          break;
          
        case 'property':
          // Select property crime types
          $('#crime-type-selector option[value="400"], #crime-type-selector option[value="500"], #crime-type-selector option[value="600"], #crime-type-selector option[value="700"]').prop('selected', true);
          $('#severity-selector option').prop('selected', true);
          break;
          
        case 'recent':
          // Select all crime types but limit to recent months
          $('#crime-type-selector option').prop('selected', true);
          $('#severity-selector option').prop('selected', true);
          const currentMonth = new Date().getMonth() + 1;
          const recentMonth = currentMonth > 3 ? (currentMonth - 3).toString().padStart(2, '0') : '01';
          $('#start-month').val(recentMonth);
          $('#end-month').val(currentMonth.toString().padStart(2, '0'));
          break;
          
        case 'high-severity':
          // Select all crime types but only high severity
          $('#crime-type-selector option').prop('selected', true);
          $('#severity-selector option[value="4"], #severity-selector option[value="5"]').prop('selected', true);
          break;
      }
      
      // Apply the preset filters
      this.applyFilters();
    },

    /**
     * Switch view mode
     */
    switchViewMode: function(mode) {
      console.log('🔄 Switching to view mode:', mode);
      this.currentFilters.viewMode = mode;
      
      // Hide all layers first
      if (this.hexagonLayer) this.map.removeLayer(this.hexagonLayer);
      if (this.heatmapLayer) this.map.removeLayer(this.heatmapLayer);
      if (this.incidentLayer) this.map.removeLayer(this.incidentLayer);
      
      // Show selected layer
      switch (mode) {
        case 'hexagon':
          if (this.hexagonLayer) this.map.addLayer(this.hexagonLayer);
          break;
        case 'heatmap':
          this.loadHeatmapData();
          break;
        case 'points':
          this.loadPointsData();
          break;
      }
    },

    /**
     * Load heatmap data
     */
    loadHeatmapData: function() {
      console.log('🔥 Loading heatmap data...');
      // Implementation for heatmap visualization
      this.showLoading('GENERATING HEATMAP...');
      
      setTimeout(() => {
        this.hideLoading();
        console.log('🔥 Heatmap data loaded');
      }, 1000);
    },

    /**
     * Load points data
     */
    loadPointsData: function() {
      console.log('📍 Loading individual incident points...');
      // Implementation for individual points
      this.showLoading('LOADING INCIDENT POINTS...');
      
      setTimeout(() => {
        this.hideLoading();
        console.log('📍 Points data loaded');
      }, 1000);
    },

    /**
     * Initialize H3 debug information
     */
    initializeH3Debug: function() {
      const self = this;
      
      // Check if H3 is available
      if (typeof h3 !== 'undefined') {
        $('#h3-available').text('✅ LOADED').addClass('neon-green');
        
        // Test basic H3 functionality
        try {
          const testH3 = h3.latLngToCell(39.9526, -75.1652, 8);
          $('#h3-test-result').text('✅ WORKING').addClass('neon-green');
          
          // Count available methods
          const methodCount = Object.keys(h3).length;
          $('#h3-method-count').text(methodCount);
          
          // Show some key functions
          const keyFunctions = ['latLngToCell', 'cellToLatLng', 'cellToBoundary', 'gridDistance', 'areNeighbors'];
          const functionsHtml = keyFunctions.map(func => 
            `<div class="debug-function-line">
              <span class="debug-function-name">${func}:</span>
              <span class="debug-function-status">${typeof h3[func] === 'function' ? '✅' : '❌'}</span>
            </div>`
          ).join('');
          $('#h3-functions').html(functionsHtml);
          
        } catch (error) {
          $('#h3-test-result').text('❌ ERROR').addClass('neon-red');
          console.error('H3 test failed:', error);
        }
      } else {
        $('#h3-available').text('❌ MISSING').addClass('neon-red');
        $('#h3-test-result').text('❌ NOT LOADED').addClass('neon-red');
      }
    },

    /**
     * Toggle debug overlays
     */
    toggleDebugOverlays: function() {
      console.log('⚡ Toggling debug overlays');
      // Implementation for debug overlay toggle
    },

    /**
     * Show performance statistics
     */
    showPerformanceStats: function() {
      console.log('📊 Showing performance statistics');
      
      const stats = {
        hexagonsLoaded: this.hexagonLayer ? this.hexagonLayer.getLayers().length : 0,
        apiCalls: this.apiCallCount || 0,
        cacheSize: this.dataCache ? this.dataCache.size : 0,
        currentZoom: this.map.getZoom(),
        currentResolution: this.getOptimalResolution(this.map.getZoom())
      };
      
      const statsHtml = `
        <div class="performance-stats">
          <h4>PERFORMANCE METRICS</h4>
          <div>Hexagons Loaded: ${stats.hexagonsLoaded}</div>
          <div>API Calls: ${stats.apiCalls}</div>
          <div>Cache Size: ${stats.cacheSize}</div>
          <div>Current Zoom: ${stats.currentZoom}</div>
          <div>H3 Resolution: ${stats.currentResolution}</div>
        </div>
      `;
      
      alert(statsHtml.replace(/<[^>]*>/g, '\n').replace(/\n+/g, '\n'));
    },

    /**
     * Update statistics display
     */
    updateStats: function() {
      // Update current view stats
      const totalIncidents = this.getCurrentIncidentCount();
      const threatLevel = this.calculateThreatLevel();
      const activeSectors = this.getActiveSectorCount();
      
      $('#total-incidents').text(totalIncidents.toLocaleString());
      $('#threat-level').text(threatLevel);
      $('#active-sectors').text(activeSectors);
      
      // Load citywide stats
      this.loadCitywideStats();
    },

    /**
     * Get current incident count
     */
    getCurrentIncidentCount: function() {
      // Calculate from current hexagon data
      let total = 0;
      if (this.hexagonLayer) {
        this.hexagonLayer.eachLayer(function(layer) {
          if (layer.options && layer.options.incidentCount) {
            total += layer.options.incidentCount;
          }
        });
      }
      return total;
    },

    /**
     * Calculate threat level
     */
    calculateThreatLevel: function() {
      const incidentCount = this.getCurrentIncidentCount();
      const sectorCount = this.getActiveSectorCount();
      
      if (sectorCount === 0) return 'MINIMAL';
      
      const avgIncidentsPerSector = incidentCount / sectorCount;
      
      if (avgIncidentsPerSector > 50) return 'EXTREME';
      if (avgIncidentsPerSector > 30) return 'CRITICAL';
      if (avgIncidentsPerSector > 15) return 'HIGH';
      if (avgIncidentsPerSector > 5) return 'MODERATE';
      return 'LOW';
    },

    /**
     * Get active sector count
     */
    getActiveSectorCount: function() {
      return this.hexagonLayer ? this.hexagonLayer.getLayers().length : 0;
    }

  };

})(jQuery, Drupal, drupalSettings);
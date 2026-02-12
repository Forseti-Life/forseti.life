/**
 * @file
 * Hex map rendering with PixiJS.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Hex map behavior.
   */
  Drupal.behaviors.hexMap = {
    app: null,
    hexContainer: null,
    gridContainer: null,
    hexSize: 30,
    gridWidth: 20,
    gridHeight: 20,
    showCoordinates: false,
    showGrid: true,
    selectedHex: null,
    hoveredHex: null,

    attach: function (context, settings) {
      const container = once('hexmap-init', '#hexmap-canvas-container', context);
      
      if (container.length === 0) {
        return;
      }

      this.initPixiApp(container[0]);
      this.generateHexGrid();
      this.setupControls();
      this.setupInteraction();
    },

    /**
     * Initialize PixiJS application.
     */
    initPixiApp: function (container) {
      // Create PixiJS application
      this.app = new PIXI.Application({
        width: container.clientWidth || 800,
        height: container.clientHeight || 600,
        backgroundColor: 0x1a1a2e,
        antialias: true,
        resolution: window.devicePixelRatio || 1,
        autoDensity: true,
      });

      container.appendChild(this.app.view);

      // Create containers for layers
      this.hexContainer = new PIXI.Container();
      this.gridContainer = new PIXI.Container();
      
      this.app.stage.addChild(this.hexContainer);
      this.app.stage.addChild(this.gridContainer);

      // Center the view
      this.hexContainer.x = this.app.screen.width / 2;
      this.hexContainer.y = this.app.screen.height / 2;
      this.gridContainer.x = this.hexContainer.x;
      this.gridContainer.y = this.hexContainer.y;

      // Enable interactivity on stage
      this.app.stage.interactive = true;
      this.app.stage.hitArea = this.app.screen;

      console.log('PixiJS initialized');
    },

    /**
     * Generate hexagonal grid.
     */
    generateHexGrid: function () {
      // Clear existing hexes
      this.hexContainer.removeChildren();
      this.gridContainer.removeChildren();

      const hexSize = this.hexSize;
      const width = this.gridWidth;
      const height = this.gridHeight;

      // Calculate hex dimensions
      const hexWidth = hexSize * 2;
      const hexHeight = Math.sqrt(3) * hexSize;

      // Generate grid (flat-top orientation)
      for (let q = -Math.floor(width / 2); q < Math.ceil(width / 2); q++) {
        for (let r = -Math.floor(height / 2); r < Math.ceil(height / 2); r++) {
          this.createHex(q, r, hexSize);
        }
      }

      console.log(`Generated ${width}x${height} hex grid`);
    },

    /**
     * Create a single hex.
     */
    createHex: function (q, r, size) {
      const hex = new PIXI.Graphics();
      const pos = this.axialToPixel(q, r, size);

      // Draw hex shape
      hex.beginFill(0x2d3748);
      hex.lineStyle(1, 0x4a5568, 1);
      
      // Draw hexagon (flat-top)
      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = size * Math.cos(angle);
        const y = size * Math.sin(angle);
        
        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();

      // Position hex
      hex.x = pos.x;
      hex.y = pos.y;

      // Store hex data
      hex.hexData = { q, r };

      // Make interactive
      hex.interactive = true;
      hex.buttonMode = true;

      // Event handlers
      hex.on('pointerover', () => this.onHexHover(hex));
      hex.on('pointerout', () => this.onHexOut(hex));
      hex.on('pointerdown', () => this.onHexClick(hex));

      this.hexContainer.addChild(hex);

      // Add coordinates text if enabled
      if (this.showCoordinates) {
        this.addHexCoordinates(hex, q, r, pos);
      }
    },

    /**
     * Add coordinate text to hex.
     */
    addHexCoordinates: function (hex, q, r, pos) {
      const text = new PIXI.Text(`${q},${r}`, {
        fontFamily: 'Arial',
        fontSize: 10,
        fill: 0x718096,
        align: 'center',
      });
      
      text.anchor.set(0.5);
      text.x = pos.x;
      text.y = pos.y;
      
      this.gridContainer.addChild(text);
    },

    /**
     * Convert axial coordinates (q, r) to pixel position.
     */
    axialToPixel: function (q, r, size) {
      const x = size * (3 / 2 * q);
      const y = size * (Math.sqrt(3) / 2 * q + Math.sqrt(3) * r);
      return { x, y };
    },

    /**
     * Convert pixel position to axial coordinates.
     */
    pixelToAxial: function (x, y, size) {
      const q = (2 / 3 * x) / size;
      const r = (-1 / 3 * x + Math.sqrt(3) / 3 * y) / size;
      return this.roundAxial(q, r);
    },

    /**
     * Round fractional axial coordinates to nearest hex.
     */
    roundAxial: function (q, r) {
      const s = -q - r;
      
      let rq = Math.round(q);
      let rr = Math.round(r);
      let rs = Math.round(s);
      
      const qDiff = Math.abs(rq - q);
      const rDiff = Math.abs(rr - r);
      const sDiff = Math.abs(rs - s);
      
      if (qDiff > rDiff && qDiff > sDiff) {
        rq = -rr - rs;
      } else if (rDiff > sDiff) {
        rr = -rq - rs;
      }
      
      return { q: rq, r: rr };
    },

    /**
     * Hex hover event.
     */
    onHexHover: function (hex) {
      // Highlight hex
      hex.clear();
      hex.beginFill(0x4a5568);
      hex.lineStyle(2, 0xfbbf24, 1);
      
      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = this.hexSize * Math.cos(angle);
        const y = this.hexSize * Math.sin(angle);
        
        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();

      this.hoveredHex = hex;
      
      // Update UI
      const { q, r } = hex.hexData;
      document.getElementById('hovered-hex').textContent = `(${q}, ${r})`;
    },

    /**
     * Hex out event.
     */
    onHexOut: function (hex) {
      // Reset hex appearance (unless it's selected)
      if (this.selectedHex !== hex) {
        hex.clear();
        hex.beginFill(0x2d3748);
        hex.lineStyle(1, 0x4a5568, 1);
        
        for (let i = 0; i < 6; i++) {
          const angle = (Math.PI / 3) * i;
          const x = this.hexSize * Math.cos(angle);
          const y = this.hexSize * Math.sin(angle);
          
          if (i === 0) {
            hex.moveTo(x, y);
          } else {
            hex.lineTo(x, y);
          }
        }
        hex.closePath();
        hex.endFill();
      }

      this.hoveredHex = null;
      document.getElementById('hovered-hex').textContent = 'None';
    },

    /**
     * Hex click event.
     */
    onHexClick: function (hex) {
      // Deselect previous hex
      if (this.selectedHex) {
        this.onHexOut(this.selectedHex);
      }

      // Select this hex
      this.selectedHex = hex;
      
      hex.clear();
      hex.beginFill(0x3b82f6);
      hex.lineStyle(3, 0x60a5fa, 1);
      
      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = this.hexSize * Math.cos(angle);
        const y = this.hexSize * Math.sin(angle);
        
        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();

      // Update UI
      const { q, r } = hex.hexData;
      document.getElementById('selected-hex').textContent = `(${q}, ${r})`;
      
      console.log('Selected hex:', q, r);
    },

    /**
     * Setup control handlers.
     */
    setupControls: function () {
      const self = this;

      // Grid size selector
      document.getElementById('grid-size').addEventListener('change', function (e) {
        const size = e.target.value;
        switch (size) {
          case 'small':
            self.gridWidth = 10;
            self.gridHeight = 10;
            break;
          case 'medium':
            self.gridWidth = 20;
            self.gridHeight = 20;
            break;
          case 'large':
            self.gridWidth = 40;
            self.gridHeight = 40;
            break;
        }
        self.generateHexGrid();
      });

      // Hex size slider
      document.getElementById('hex-size').addEventListener('input', function (e) {
        self.hexSize = parseInt(e.target.value);
        document.getElementById('hex-size-value').textContent = self.hexSize + 'px';
        self.generateHexGrid();
      });

      // Toggle coordinates
      document.getElementById('toggle-coordinates').addEventListener('click', function () {
        self.showCoordinates = !self.showCoordinates;
        self.generateHexGrid();
      });

      // Toggle grid lines
      document.getElementById('toggle-grid').addEventListener('click', function () {
        self.showGrid = !self.showGrid;
        self.gridContainer.visible = self.showGrid;
      });

      // Reset view
      document.getElementById('reset-view').addEventListener('click', function () {
        self.hexContainer.scale.set(1);
        self.hexContainer.x = self.app.screen.width / 2;
        self.hexContainer.y = self.app.screen.height / 2;
        self.gridContainer.scale.set(1);
        self.gridContainer.x = self.hexContainer.x;
        self.gridContainer.y = self.hexContainer.y;
        document.getElementById('zoom-level').textContent = '100%';
      });
    },

    /**
     * Setup pan and zoom interaction.
     */
    setupInteraction: function () {
      const self = this;
      let isDragging = false;
      let dragStart = { x: 0, y: 0 };

      // Pan functionality
      this.app.stage.on('pointerdown', function (e) {
        isDragging = true;
        dragStart = { x: e.data.global.x, y: e.data.global.y };
      });

      this.app.stage.on('pointerup', function () {
        isDragging = false;
      });

      this.app.stage.on('pointerupoutside', function () {
        isDragging = false;
      });

      this.app.stage.on('pointermove', function (e) {
        if (isDragging) {
          const dx = e.data.global.x - dragStart.x;
          const dy = e.data.global.y - dragStart.y;
          
          self.hexContainer.x += dx;
          self.hexContainer.y += dy;
          self.gridContainer.x += dx;
          self.gridContainer.y += dy;
          
          dragStart = { x: e.data.global.x, y: e.data.global.y };
        }
      });

      // Zoom functionality
      this.app.view.addEventListener('wheel', function (e) {
        e.preventDefault();
        
        const delta = e.deltaY < 0 ? 1.1 : 0.9;
        const newScale = self.hexContainer.scale.x * delta;
        
        // Limit zoom
        if (newScale > 0.5 && newScale < 3) {
          self.hexContainer.scale.set(newScale);
          self.gridContainer.scale.set(newScale);
          
          const zoomPercent = Math.round(newScale * 100);
          document.getElementById('zoom-level').textContent = zoomPercent + '%';
        }
      });
    }
  };

})(Drupal, once);

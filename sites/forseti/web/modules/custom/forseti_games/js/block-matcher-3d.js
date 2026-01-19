/**
 * @file
 * 3D Block Matcher game logic using Three.js.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.blockMatcher3D = {
    attach: function (context, settings) {
      once('block-matcher-3d', '#game-board', context).forEach(function(element) {
        var $gameBoard = $(element);
        
        // Wait for Three.js to load
        if (typeof THREE === 'undefined') {
          setTimeout(function() {
            Drupal.behaviors.blockMatcher3D.attach(context, settings);
          }, 100);
          return;
        }
        
        var game = new BlockMatcher3DGame($gameBoard);
        game.init();
      });
    }
  };

  /**
   * 3D Block Matcher Game Class
   */
  function BlockMatcher3DGame($board) {
    this.$board = $board;
    this.gridSize = 18; // Full grid size (18x18x18)
    this.level = 1; // Current level (starts at 1)
    this.maxLevel = 999; // No effective level cap (playable size caps at level 7)
    this.playableSize = 3; // Will be calculated based on level
    this.blockTypes = parseInt($board.data('block-types')) || 5;
    this.minMatch = parseInt($board.data('min-match')) || 3;
    this.grid = []; // 3D array [x][y][z]
    this.selectedBlock = null;
    this.score = 0;
    this.moves = 0;
    this.startTime = null;
    this.timerInterval = null;
    
    // Combo tracking
    this.initialMatchCount = 0;
    this.comboMatchCount = 0;
    
    // Settlement lock
    this.isSettling = false;
    
    // Special block system
    this.pointMultiplier = 1;
    this.multiplierTurnsLeft = 0;
    this.freezeTurnsLeft = 0;
    this.hasShield = false;
    
    // Special blocks: 100-114 (with some numbers removed)
    this.specialBlocks = {
      100: { name: 'Bomb', emoji: '💣', color: 0xff0000, rarity: 30 },
      101: { name: 'Lightning', emoji: '⚡', color: 0xffff00, rarity: 30 },
      105: { name: 'Shuffler', emoji: '🔄', color: 0x9900ff, rarity: 60 },
      106: { name: 'Laser', emoji: '🎯', color: 0xff0066, rarity: 60 },
      107: { name: 'Freeze', emoji: '⏸️', color: 0x66ccff, rarity: 30 },
      108: { name: 'Multiplier', emoji: '💎', color: 0xffd700, rarity: 30 },
      109: { name: 'Jackpot', emoji: '🎰', color: 0x00ff00, rarity: 30 },
      110: { name: 'Combo Extender', emoji: '⭐', color: 0xc0c0c0, rarity: 9 },
      112: { name: 'Teleporter', emoji: '🔮', color: 0xff66cc, rarity: 9 },
      113: { name: 'Color Changer', emoji: '🎨', color: 0x6699ff, rarity: 9 },
      114: { name: 'Shield', emoji: '🛡️', color: 0x0099ff, rarity: 1 }
    };
    
    // Audio
    this.audioContext = null;
    this.initAudio();
    
    // Three.js objects
    this.scene = null;
    this.camera = null;
    this.renderer = null;
    this.blockMeshes = {};
    this.raycaster = new THREE.Raycaster();
    this.mouse = new THREE.Vector2();
    
    // Camera rotation
    this.cameraAngle = { theta: Math.PI / 4, phi: Math.PI / 4 };
    this.cameraDistance = this.playableSize * 3.5; // Focus on playable area
    
    // Drag state
    this.draggedBlock = null;
    this.validDropZones = [];
    this.dropZoneMeshes = [];
  }

  BlockMatcher3DGame.prototype = {
    init: function() {
      this.updateLevel();
      this.precalculateDistances(); // Cache distances for settlement optimization
      this.createGrid();
      this.initThreeJS();
      this.render3D();
      this.startTimer();
      this.bindEvents();
      this.startCenterBlockShimmer();
    },

    initAudio: function() {
      try {
        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
      } catch(e) {
        console.log('Web Audio API not supported');
      }
    },

    playClickSound: function(count) {
      if (!this.audioContext) return;
      
      // Create a short click/tap sound
      var oscillator = this.audioContext.createOscillator();
      var gainNode = this.audioContext.createGain();
      
      oscillator.connect(gainNode);
      gainNode.connect(this.audioContext.destination);
      
      // Higher pitch for more blocks moving
      var baseFreq = 800;
      var freqVariation = Math.min(count * 5, 400);
      oscillator.frequency.value = baseFreq + freqVariation;
      oscillator.type = 'sine';
      
      // Quick attack and decay
      var now = this.audioContext.currentTime;
      gainNode.gain.setValueAtTime(0, now);
      gainNode.gain.linearRampToValueAtTime(0.1, now + 0.01);
      gainNode.gain.exponentialRampToValueAtTime(0.01, now + 0.05);
      
      oscillator.start(now);
      oscillator.stop(now + 0.05);
    },

    playExplosionSound: function(count) {
      if (!this.audioContext) return;
      
      // Create a noise-based explosion sound
      var duration = 0.3;
      var now = this.audioContext.currentTime;
      
      // Low frequency rumble
      var rumble = this.audioContext.createOscillator();
      var rumbleGain = this.audioContext.createGain();
      rumble.connect(rumbleGain);
      rumbleGain.connect(this.audioContext.destination);
      rumble.type = 'sawtooth';
      rumble.frequency.value = 50 + Math.min(count * 2, 100);
      
      rumbleGain.gain.setValueAtTime(0.2, now);
      rumbleGain.gain.exponentialRampToValueAtTime(0.01, now + duration);
      
      rumble.start(now);
      rumble.stop(now + duration);
      
      // High frequency crack
      var crack = this.audioContext.createOscillator();
      var crackGain = this.audioContext.createGain();
      crack.connect(crackGain);
      crackGain.connect(this.audioContext.destination);
      crack.type = 'square';
      crack.frequency.setValueAtTime(1200, now);
      crack.frequency.exponentialRampToValueAtTime(100, now + 0.1);
      
      crackGain.gain.setValueAtTime(0.15, now);
      crackGain.gain.exponentialRampToValueAtTime(0.01, now + 0.1);
      
      crack.start(now);
      crack.stop(now + 0.1);
    },

    playWhooshSound: function() {
      if (!this.audioContext) return;
      
      // Create a whooshing sound for rotation
      var duration = 0.15;
      var now = this.audioContext.currentTime;
      
      // Sweeping frequency for whoosh effect
      var whoosh = this.audioContext.createOscillator();
      var whooshGain = this.audioContext.createGain();
      whoosh.connect(whooshGain);
      whooshGain.connect(this.audioContext.destination);
      whoosh.type = 'sine';
      
      // Sweep from high to low for whoosh
      whoosh.frequency.setValueAtTime(600, now);
      whoosh.frequency.exponentialRampToValueAtTime(200, now + duration);
      
      whooshGain.gain.setValueAtTime(0.05, now);
      whooshGain.gain.linearRampToValueAtTime(0.08, now + 0.05);
      whooshGain.gain.exponentialRampToValueAtTime(0.01, now + duration);
      
      whoosh.start(now);
      whoosh.stop(now + duration);
    },

    playSelectSound: function() {
      if (!this.audioContext) return;
      
      // Create a bright click for first block selection
      var oscillator = this.audioContext.createOscillator();
      var gainNode = this.audioContext.createGain();
      
      oscillator.connect(gainNode);
      gainNode.connect(this.audioContext.destination);
      
      oscillator.frequency.value = 1200;
      oscillator.type = 'sine';
      
      var now = this.audioContext.currentTime;
      gainNode.gain.setValueAtTime(0.15, now);
      gainNode.gain.exponentialRampToValueAtTime(0.01, now + 0.08);
      
      oscillator.start(now);
      oscillator.stop(now + 0.08);
    },

    playDeselectSound: function() {
      if (!this.audioContext) return;
      
      // Create a lower click for second block selection (inverse of select)
      var oscillator = this.audioContext.createOscillator();
      var gainNode = this.audioContext.createGain();
      
      oscillator.connect(gainNode);
      gainNode.connect(this.audioContext.destination);
      
      // Lower frequency than select (inverse)
      oscillator.frequency.value = 600;
      oscillator.type = 'sine';
      
      var now = this.audioContext.currentTime;
      gainNode.gain.setValueAtTime(0.12, now);
      gainNode.gain.exponentialRampToValueAtTime(0.01, now + 0.1);
      
      oscillator.start(now);
      oscillator.stop(now + 0.1);
    },

    precalculateDistances: function() {
      // Pre-calculate all distances from center for settlement optimization
      // This eliminates ~58,000-116,000 distance calculations per turn
      console.log('Precalculating block distances for settlement optimization...');
      
      var centerPos = Math.floor(this.gridSize / 2);
      this.blockDistances = {};  // Cache of distance info by position key
      this.blocksByDistance = []; // Pre-sorted array of all positions
      
      for (var x = 0; x < this.gridSize; x++) {
        for (var y = 0; y < this.gridSize; y++) {
          for (var z = 0; z < this.gridSize; z++) {
            var xDist = Math.abs(x - centerPos);
            var yDist = Math.abs(y - centerPos);
            var zDist = Math.abs(z - centerPos);
            var totalDist = Math.sqrt(xDist*xDist + yDist*yDist + zDist*zDist);
            
            var key = x + '_' + y + '_' + z;
            var distInfo = {
              x: x,
              y: y,
              z: z,
              dist: totalDist,
              xDist: xDist,
              yDist: yDist,
              zDist: zDist
            };
            
            this.blockDistances[key] = distInfo;
            this.blocksByDistance.push(distInfo);
          }
        }
      }
      
      // Sort once at initialization instead of every settlement iteration
      this.blocksByDistance.sort(function(a, b) {
        return b.dist - a.dist; // Sort descending (furthest first)
      });
      
      console.log('Distance cache initialized: ' + this.blocksByDistance.length + ' positions pre-calculated and sorted');
    },

    showGameMessage: function(text) {
      var self = this;
      
      // Create text sprite
      var canvas = document.createElement('canvas');
      canvas.width = 1024;
      canvas.height = 256;
      var ctx = canvas.getContext('2d');
      
      // Draw background
      ctx.fillStyle = 'rgba(0, 0, 0, 0.8)';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      
      // Draw text
      ctx.font = 'bold 72px Arial';
      ctx.fillStyle = '#ffffff';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(text, canvas.width / 2, canvas.height / 2);
      
      var texture = new THREE.CanvasTexture(canvas);
      var spriteMaterial = new THREE.SpriteMaterial({ 
        map: texture,
        transparent: true,
        depthTest: false
      });
      var sprite = new THREE.Sprite(spriteMaterial);
      
      // Position in front of camera
      sprite.scale.set(10, 2.5, 1);
      sprite.position.set(0, 0, 0);
      sprite.renderOrder = 9999;
      
      this.scene.add(sprite);
      
      // Animate: expand and fade out
      var startTime = Date.now();
      var duration = 2000;
      
      function animate() {
        var elapsed = Date.now() - startTime;
        var progress = elapsed / duration;
        
        if (progress < 1) {
          var scale = 10 + progress * 5; // Expand from 10 to 15
          sprite.scale.set(scale, scale * 0.25, 1);
          sprite.material.opacity = 1 - progress;
          self.renderer.render(self.scene, self.camera);
          requestAnimationFrame(animate);
        } else {
          self.scene.remove(sprite);
          self.renderer.render(self.scene, self.camera);
        }
      }
      
      animate();
    },

    playSuccessSound: function() {
      if (!this.audioContext) return;
      
      // Create an uplifting success jingle
      var now = this.audioContext.currentTime;
      var notes = [523.25, 659.25, 783.99]; // C5, E5, G5 chord
      
      notes.forEach(function(freq, index) {
        var osc = this.audioContext.createOscillator();
        var gain = this.audioContext.createGain();
        
        osc.connect(gain);
        gain.connect(this.audioContext.destination);
        
        osc.frequency.value = freq;
        osc.type = 'sine';
        
        var delay = index * 0.08;
        gain.gain.setValueAtTime(0.15, now + delay);
        gain.gain.exponentialRampToValueAtTime(0.01, now + delay + 0.5);
        
        osc.start(now + delay);
        osc.stop(now + delay + 0.5);
      }.bind(this));
    },

    playVictorySound: function() {
      if (!this.audioContext) return;
      
      // Create a celebratory fanfare
      var now = this.audioContext.currentTime;
      
      // Triumphant chord progression: C-E-G, then up to high C
      var sequence = [
        { notes: [523.25, 659.25, 783.99], time: 0 },      // C5, E5, G5
        { notes: [587.33, 739.99, 880.00], time: 0.15 },   // D5, F#5, A5
        { notes: [659.25, 783.99, 987.77], time: 0.3 },    // E5, G5, B5
        { notes: [1046.50], time: 0.5 }                     // C6 (high note)
      ];
      
      sequence.forEach(function(chord) {
        chord.notes.forEach(function(freq) {
          var osc = this.audioContext.createOscillator();
          var gain = this.audioContext.createGain();
          
          osc.connect(gain);
          gain.connect(this.audioContext.destination);
          
          osc.frequency.value = freq;
          osc.type = 'sine';
          
          var startTime = now + chord.time;
          var duration = chord.time === 0.5 ? 0.8 : 0.2; // Longer final note
          
          gain.gain.setValueAtTime(0.2, startTime);
          gain.gain.exponentialRampToValueAtTime(0.01, startTime + duration);
          
          osc.start(startTime);
          osc.stop(startTime + duration);
        }.bind(this));
      }.bind(this));
      
      // Add some shimmer with higher harmonics
      for (var i = 0; i < 5; i++) {
        var shimmer = this.audioContext.createOscillator();
        var shimmerGain = this.audioContext.createGain();
        
        shimmer.connect(shimmerGain);
        shimmerGain.connect(this.audioContext.destination);
        
        shimmer.frequency.value = 2000 + (i * 400);
        shimmer.type = 'sine';
        
        var shimmerTime = now + (i * 0.1);
        shimmerGain.gain.setValueAtTime(0.05, shimmerTime);
        shimmerGain.gain.exponentialRampToValueAtTime(0.01, shimmerTime + 0.3);
        
        shimmer.start(shimmerTime);
        shimmer.stop(shimmerTime + 0.3);
      }
    },

    updateLevel: function() {
      // Calculate playable size based on level: Level 1 = 5x5x5, Level 2 = 7x7x7, etc.
      // Cap playable size at level 6 (15x15x15), but level can continue to increase
      var targetSize = 2 * Math.min(this.level + 1, 7) + 1;
      this.playableSize = Math.min(targetSize, this.gridSize);
      $('#level').text(this.level);
    },

    createGrid: function() {
      this.grid = [];
      var centerPos = Math.floor(this.gridSize / 2); // Always 9 for 18x18x18
      var halfSize = Math.floor(this.playableSize / 2); // How many blocks on each side of center
      var startIdx = centerPos - halfSize;
      var endIdx = centerPos + halfSize + 1; // +1 because center block itself
      
      // Initialize entire 18x18x18 grid as empty
      for (var x = 0; x < this.gridSize; x++) {
        this.grid[x] = [];
        for (var y = 0; y < this.gridSize; y++) {
          this.grid[x][y] = [];
          for (var z = 0; z < this.gridSize; z++) {
            // Only fill playable area centered around (9,9,9)
            if (x >= startIdx && x < endIdx && 
                y >= startIdx && y < endIdx && 
                z >= startIdx && z < endIdx) {
              // Center block always gets special type -2 (will render black)
              if (x === centerPos && y === centerPos && z === centerPos) {
                this.grid[x][y][z] = -2; // Special center block marker
              } else {
                this.grid[x][y][z] = this.randomBlockType();
              }
            } else {
              this.grid[x][y][z] = -1; // Empty
            }
          }
        }
      }
      this.removeInitialMatches();
    },

    randomBlockType: function() {
      // 10% chance for special block
      if (Math.random() < 0.1) {
        return this.getRandomSpecialBlock();
      }
      return Math.floor(Math.random() * this.blockTypes);
    },

    getRandomSpecialBlock: function() {
      // Calculate total rarity weight
      var totalRarity = 0;
      var self = this;
      Object.keys(this.specialBlocks).forEach(function(key) {
        totalRarity += self.specialBlocks[key].rarity;
      });
      
      // Pick based on rarity
      var roll = Math.random() * totalRarity;
      var currentWeight = 0;
      
      for (var key in this.specialBlocks) {
        currentWeight += this.specialBlocks[key].rarity;
        if (roll <= currentWeight) {
          console.log('Generated special block:', key, this.specialBlocks[key].name);
          return parseInt(key);
        }
      }
      
      console.log('Using fallback special block: Bomb');
      return 100; // Bomb as fallback
    },

    isSpecialBlock: function(type) {
      return type >= 100 && type <= 114;
    },

    getSpecialBlockData: function(type) {
      return this.specialBlocks[type] || null;
    },

    getBlockMatchColor: function(type) {
      // For special blocks, extract the base color (100-114 maps to 0-4)
      // This allows special blocks to match with regular blocks of the same color
      if (this.isSpecialBlock(type)) {
        // Map special blocks to colors 0-4 based on their ID
        return (type - 100) % this.blockTypes;
      }
      return type;
    },

    removeInitialMatches: function() {
      var hasMatches = true;
      var iterations = 0;
      while (hasMatches && iterations < 100) {
        hasMatches = false;
        for (var x = 0; x < this.gridSize; x++) {
          for (var y = 0; y < this.gridSize; y++) {
            for (var z = 0; z < this.gridSize; z++) {
              if (this.grid[x][y][z] !== -1 && this.checkMatchAt(x, y, z).length >= this.minMatch) {
                this.grid[x][y][z] = this.randomBlockType();
                hasMatches = true;
              }
            }
          }
        }
        iterations++;
      }
    },

    initThreeJS: function() {
      var canvas = document.getElementById('game-canvas');
      var width = canvas.parentElement.clientWidth;
      var height = canvas.parentElement.clientHeight;
      
      // Scene
      this.scene = new THREE.Scene();
      this.scene.background = new THREE.Color(0x2c3e50);
      
      // Camera
      this.camera = new THREE.PerspectiveCamera(50, width / height, 0.1, 1000);
      this.updateCameraPosition();
      
      // Renderer
      this.renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true });
      this.renderer.setSize(width, height);
      this.renderer.setPixelRatio(window.devicePixelRatio);
      
      // Lighting
      var ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
      this.scene.add(ambientLight);
      
      var directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
      directionalLight.position.set(10, 10, 10);
      this.scene.add(directionalLight);
      
      // Grid helper at center - show full grid
      var gridHelper = new THREE.GridHelper(this.gridSize, this.gridSize, 0x444444, 0x222222);
      gridHelper.position.y = -this.gridSize / 2;
      gridHelper.raycast = function() {}; // Disable raycasting so it doesn't block clicks
      this.scene.add(gridHelper);
    },

    updateCameraPosition: function() {
      var theta = this.cameraAngle.theta;
      var phi = this.cameraAngle.phi;
      var radius = this.cameraDistance;
      
      this.camera.position.x = radius * Math.sin(phi) * Math.cos(theta);
      this.camera.position.y = radius * Math.cos(phi);
      this.camera.position.z = radius * Math.sin(phi) * Math.sin(theta);
      this.camera.lookAt(0, 0, 0);
    },

    getBlockColor: function(type) {
      // Define base colors: Red, Blue, Green, Yellow, Purple, Pink
      var colors = [0xe74c3c, 0x3498db, 0x2ecc71, 0xf39c12, 0x9b59b6, 0xe91e63];
      
      if (this.isSpecialBlock(type)) {
        // Special blocks use the color they will match with
        var matchColor = this.getBlockMatchColor(type);
        return colors[matchColor] || 0xffffff;
      }
      
      return colors[type] || 0xcccccc;
    },

    createEmojiTexture: function(emoji) {
      var canvas = document.createElement('canvas');
      canvas.width = 128;
      canvas.height = 128;
      var ctx = canvas.getContext('2d');
      
      // Draw white circle background
      ctx.fillStyle = '#ffffff';
      ctx.beginPath();
      ctx.arc(64, 64, 60, 0, Math.PI * 2);
      ctx.fill();
      
      // Draw emoji
      ctx.font = 'bold 70px Arial';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillStyle = '#000000';
      ctx.fillText(emoji, 64, 64);
      
      var texture = new THREE.CanvasTexture(canvas);
      texture.needsUpdate = true;
      return texture;
    },

    render3D: function() {
      var self = this;
      var centerPos = Math.floor(this.gridSize / 2);
      var offset = this.gridSize / 2;
      
      // Clear existing meshes
      Object.keys(this.blockMeshes).forEach(function(key) {
        self.scene.remove(self.blockMeshes[key]);
      });
      this.blockMeshes = {};
      
      // Create block meshes
      var geometry = new THREE.BoxGeometry(0.9, 0.9, 0.9);
      
      for (var x = 0; x < this.gridSize; x++) {
        for (var y = 0; y < this.gridSize; y++) {
          for (var z = 0; z < this.gridSize; z++) {
            if (this.grid[x][y][z] === -1) continue;
            
            var blockType = this.grid[x][y][z];
            var color = blockType === -2 ? 0x000000 : this.getBlockColor(blockType);
            
            // Special blocks: more opaque with brighter glow to show color clearly
            var isSpecial = this.isSpecialBlock(blockType);
            var material = new THREE.MeshStandardMaterial({
              color: color,
              emissive: blockType === -2 ? 0xffaa00 : color,
              emissiveIntensity: blockType === -2 ? 0.5 : (isSpecial ? 0.3 : 0.15),
              metalness: 0.2,
              roughness: 0.6,
              transparent: isSpecial,
              opacity: isSpecial ? 0.6 : 1.0
            });
            
            // Glow moving blocks brighter
            var key = x + ',' + y + ',' + z;
            if (self.movingBlocks && self.movingBlocks[key]) {
              material.emissiveIntensity = 0.8;
            }
            
            var mesh = new THREE.Mesh(geometry, material);
            mesh.position.set(x - offset, y - offset, z - offset);
            mesh.userData = { x: x, y: y, z: z };
            
            // Highlight center block in black AFTER mesh is created
            if (x === centerPos && y === centerPos && z === centerPos) {
              material.color.setHex(0x000000);
              material.emissive.setHex(0xffaa00);
              material.emissiveIntensity = 0.5;
              material.metalness = 0.3;
              material.roughness = 0.7;
              mesh.userData.isCenter = true;
              self.centerBlockMesh = mesh;
            }
            
            this.scene.add(mesh);
            
            // Add emoji sprite for special blocks
            if (isSpecial) {
              var special = this.getSpecialBlockData(blockType);
              if (special) {
                console.log('Creating sprite for special block:', blockType, special.name, special.emoji);
                var spriteMap = this.createEmojiTexture(special.emoji);
                var spriteMaterial = new THREE.SpriteMaterial({ 
                  map: spriteMap,
                  transparent: false,
                  depthTest: true,
                  depthWrite: true
                });
                var sprite = new THREE.Sprite(spriteMaterial);
                sprite.scale.set(0.9, 0.9, 0.9);
                mesh.add(sprite);
              }
            }
            
            this.blockMeshes[x + '_' + y + '_' + z] = mesh;
          }
        }
      }
      
      this.renderer.render(this.scene, this.camera);
    },

    explodeAllBlocks: function() {
      var self = this;
      clearInterval(this.timerInterval);
      
      // Animate all blocks exploding outward
      var blocks = [];
      for (var x = 0; x < this.gridSize; x++) {
        for (var y = 0; y < this.gridSize; y++) {
          for (var z = 0; z < this.gridSize; z++) {
            if (this.grid[x][y][z] !== -1) {
              var key = x + '_' + y + '_' + z;
              var mesh = this.blockMeshes[key];
              if (mesh) {
                blocks.push({ mesh: mesh, x: x, y: y, z: z });
              }
            }
          }
        }
      }
      
      var centerPos = this.gridSize / 2;
      var startTime = Date.now();
      var duration = 2000;
      
      function animate() {
        var elapsed = Date.now() - startTime;
        var progress = Math.min(elapsed / duration, 1);
        
        blocks.forEach(function(block) {
          var dx = block.x - centerPos;
          var dy = block.y - centerPos;
          var dz = block.z - centerPos;
          var distance = Math.sqrt(dx * dx + dy * dy + dz * dz) || 1;
          
          // Explode outward
          var explosionDistance = progress * distance * 3;
          block.mesh.position.x = (block.x - centerPos) + (dx / distance) * explosionDistance;
          block.mesh.position.y = (block.y - centerPos) + (dy / distance) * explosionDistance;
          block.mesh.position.z = (block.z - centerPos) + (dz / distance) * explosionDistance;
          
          // Rotate and fade
          block.mesh.rotation.x += 0.1;
          block.mesh.rotation.y += 0.15;
          block.mesh.rotation.z += 0.08;
          block.mesh.material.opacity = 1 - progress;
          block.mesh.material.transparent = true;
        });
        
        self.renderer.render(self.scene, self.camera);
        
        if (progress < 1) {
          requestAnimationFrame(animate);
        } else {
          self.advanceLevel();
        }
      }
      
      animate();
    },

    dropRandomBlocks: function(count) {
      var self = this;
      // Number of blocks to drop equals current level (1-9)
      var blocksToPlace = this.level;
      var blocksPlaced = 0;
      var maxAttempts = blocksToPlace * 10; // Allow multiple attempts to find empty spots
      var attempts = 0;
      
      // Try to place all requested blocks
      while (blocksPlaced < blocksToPlace && attempts < maxAttempts) {
        // Pick a random edge position
        var face = Math.floor(Math.random() * 6);
        var targetX, targetY, targetZ;
        
        // Random position within grid bounds for target
        var randVal1 = Math.floor(Math.random() * this.gridSize);
        var randVal2 = Math.floor(Math.random() * this.gridSize);
        
        switch(face) {
          case 0: // Top
            targetX = randVal1;
            targetY = this.gridSize - 1;
            targetZ = randVal2;
            break;
          case 1: // Bottom
            targetX = randVal1;
            targetY = 0;
            targetZ = randVal2;
            break;
          case 2: // Left
            targetX = 0;
            targetY = randVal1;
            targetZ = randVal2;
            break;
          case 3: // Right
            targetX = this.gridSize - 1;
            targetY = randVal1;
            targetZ = randVal2;
            break;
          case 4: // Front
            targetX = randVal1;
            targetY = randVal2;
            targetZ = this.gridSize - 1;
            break;
          case 5: // Back
            targetX = randVal1;
            targetY = randVal2;
            targetZ = 0;
            break;
        }
        
        // If position is empty, place the block
        if (this.grid[targetX][targetY][targetZ] === -1) {
          this.grid[targetX][targetY][targetZ] = this.randomBlockType();
          blocksPlaced++;
        }
        
        attempts++;
      }
      
      // Check if we couldn't place enough blocks (game might be full)
      if (blocksPlaced < count && attempts >= maxAttempts) {
        // Check if shield is active
        if (this.hasShield) {
          console.log('SHIELD ACTIVATED: Removing 50% of blocks');
          this.hasShield = false; // Consume shield
          this.showGameMessage('🛡️ Shield saved you! Removing half the blocks.');
          
          // Remove 50% of regular blocks from grid
          var allBlocks = [];
          for (var ix = 0; ix < this.gridSize; ix++) {
            for (var iy = 0; iy < this.gridSize; iy++) {
              for (var iz = 0; iz < this.gridSize; iz++) {
                if (this.grid[ix][iy][iz] >= 0) {
                  allBlocks.push({x: ix, y: iy, z: iz});
                }
              }
            }
          }
          
          // Shuffle and remove half
          allBlocks.sort(function() { return Math.random() - 0.5; });
          var toRemove = allBlocks.slice(0, Math.floor(allBlocks.length * 0.5));
          
          toRemove.forEach(function(block) {
            self.grid[block.x][block.y][block.z] = -1;
          });
          
          this.render3D();
          this.dropBlocks();
          return;
        }
        
        console.log('GAME OVER: Could not place blocks (grid too full)');
        this.gameOver(false, 'Game Over! No space for new blocks.');
        return;
      }
      
      // Blocks are now in grid, render and settle them
      if (blocksPlaced > 0) {
        this.render3D();
        this.dropBlocks(function() {
          // Check for matches after new blocks settle
          self.processMatchesWithoutDrop(function() {
            self.completeTurn();
          });
        });
      } else {
        self.completeTurn();
      }
    },

    startCenterBlockShimmer: function() {
      var self = this;
      var startTime = Date.now();
      
      function shimmer() {
        if (!self.centerBlockMesh) {
          requestAnimationFrame(shimmer);
          return;
        }
        
        var elapsed = Date.now() - startTime;
        var intensity = 0.5 + Math.sin(elapsed * 0.003) * 0.3; // Oscillate between 0.2 and 0.8
        var scale = 1 + Math.sin(elapsed * 0.005) * 0.1; // Pulse scale
        
        self.centerBlockMesh.material.emissiveIntensity = intensity;
        self.centerBlockMesh.scale.set(scale, scale, scale);
        self.renderer.render(self.scene, self.camera);
        
        requestAnimationFrame(shimmer);
      }
      
      shimmer();
    },

    bindEvents: function() {
      var self = this;
      var canvas = document.getElementById('game-canvas');
      var isCameraRotating = false;
      var previousMousePosition = { x: 0, y: 0 };
      var mouseDownTime = 0;
      var mouseDownPos = { x: 0, y: 0 };
      
      // Mouse down - prepare for click or drag
      canvas.addEventListener('mousedown', function(event) {
        isCameraRotating = false;
        mouseDownTime = Date.now();
        previousMousePosition = { x: event.clientX, y: event.clientY };
        mouseDownPos = { x: event.clientX, y: event.clientY };
      });
      
      // Mouse move - camera rotation or show drag preview
      canvas.addEventListener('mousemove', function(event) {
        if (event.buttons === 1) {
          var deltaX = event.clientX - previousMousePosition.x;
          var deltaY = event.clientY - previousMousePosition.y;
          var totalMove = Math.sqrt(
            Math.pow(event.clientX - mouseDownPos.x, 2) + 
            Math.pow(event.clientY - mouseDownPos.y, 2)
          );
          
          // If moved more than 5 pixels, it's camera rotation
          if (totalMove > 5) {
            if (!isCameraRotating) {
              self.playWhooshSound();
              self.clearDropZones();
            }
            isCameraRotating = true;
            
            self.cameraAngle.theta += deltaX * 0.01;
            self.cameraAngle.phi += deltaY * 0.01;
            self.cameraAngle.phi = Math.max(0.1, Math.min(Math.PI - 0.1, self.cameraAngle.phi));
            
            self.updateCameraPosition();
            self.renderer.render(self.scene, self.camera);
          }
          
          previousMousePosition = { x: event.clientX, y: event.clientY };
        }
      });
      
      // Mouse up - handle click
      canvas.addEventListener('mouseup', function(event) {
        if (!isCameraRotating) {
          self.handleCanvasClick(event);
        }
        isCameraRotating = false;
      });
      
      // Mouse wheel for zoom
      canvas.addEventListener('wheel', function(event) {
        event.preventDefault();
        self.cameraDistance += event.deltaY * 0.01;
        self.cameraDistance = Math.max(self.playableSize * 1.5, Math.min(self.gridSize * 4, self.cameraDistance));
        self.updateCameraPosition();
        self.renderer.render(self.scene, self.camera);
      });

      $('#new-game-btn').on('click', function() {
        self.newGame();
      });

      $('#drop-blocks-btn').on('click', function() {
        self.dropRandomBlocks(1);
      });

      $('#settle-blocks-btn').on('click', function() {
        console.log('=== MANUAL SETTLE TRIGGERED ===');
        self.settleAllBlocksWithCount(function(moveCount) {
          console.log('=== MANUAL SETTLE COMPLETE - Total moves: ' + moveCount + ' ===');
        });
      });

      $('#play-again-btn').on('click', function() {
        self.newGame();
        $('#game-over-modal').hide();
      });
      
      // Remove orientation controls for 3D (camera controls instead)
      $('.orientation-controls').hide();
    },

    advanceLevel: function() {
      var self = this;
      
      if (this.level >= this.maxLevel) {
        // Beat final level!
        setTimeout(function() {
          self.gameOver(true, 'ULTIMATE VICTORY! You completed all ' + self.maxLevel + ' levels!');
        }, 2000);
        return;
      }
      
      // Advance to next level
      this.level++;
      this.updateLevel();
      
      // Brief pause then restart with new level
      setTimeout(function() {
        self.createGrid();
        self.render3D();
        self.moves = 0;
        $('#moves').text(self.moves);
      }, 2000);
    },

    completeTurn: function() {
      console.log('Turn complete, unlocking (isSettling = false)');
      this.isSettling = false;
    },

    handleCanvasClick: function(event) {
      var canvas = document.getElementById('game-canvas');
      var rect = canvas.getBoundingClientRect();
      
      this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
      this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
      
      this.raycaster.setFromCamera(this.mouse, this.camera);
      
      var intersects = this.raycaster.intersectObjects(this.scene.children);
      
      if (intersects.length > 0) {
        var mesh = intersects[0].object;
        if (mesh.userData.x !== undefined) {
          this.handleBlockClick(mesh.userData.x, mesh.userData.y, mesh.userData.z);
        }
      }
    },

    handleBlockClick: function(x, y, z) {
      // Block interaction during settlement
      if (this.isSettling) {
        console.log('Click blocked: isSettling is true');
        return;
      }
      
      // Allow clicking on empty spaces if we have a selected block (for drop zones)
      if (this.grid[x][y][z] === -1) {
        if (this.selectedBlock) {
          // Check if this is a valid drop zone
          if (this.isValidDropZone(this.selectedBlock.x, this.selectedBlock.y, this.selectedBlock.z, x, y, z)) {
            this.moveBlockToEmpty(this.selectedBlock.x, this.selectedBlock.y, this.selectedBlock.z, x, y, z);
            this.clearSelection();
            this.moves++;
            $('#moves').text(this.moves);
          }
        }
        return;
      }
      
      var centerPos = Math.floor(this.gridSize / 2);
      
      // Check if center block was clicked
      if (x === centerPos && y === centerPos && z === centerPos) {
        this.playSuccessSound();
        this.explodeAllBlocks();
        return;
      }
      
      var blockType = this.grid[x][y][z];
      var self = this;
      
      // Lock interaction immediately
      console.log('Setting isSettling = true');
      this.isSettling = true;
      
      /* DISABLED: Special blocks now match by color instead of triggering on click
      // Check if it's a special block
      if (this.isSpecialBlock(blockType)) {
        this.playExplosionSound(1);
        this.handleSpecialBlock(blockType, x, y, z);
        return;
      }
      */
      
      /* DIRECT ELIMINATION MODE - commented out
      // Regular block logic
      this.playExplosionSound(1);
      this.moves++;
      $('#moves').text(this.moves);
      
      // Check freeze status
      var movesToDrop = this.freezeTurnsLeft > 0 ? 0 : this.moves;
      if (this.freezeTurnsLeft > 0) {
        this.freezeTurnsLeft--;
      }
      
      // Remove the clicked block with explosion animation, then drop new blocks
      this.removeMatches([{x: x, y: y, z: z}], false, function() {
        // After settlement completes, drop blocks if not frozen
        if (movesToDrop > 0) {
          self.dropRandomBlocks(movesToDrop);
        } else {
          self.completeTurn();
        }
      });
      */
      
      // SWAP MODE - Select first block, then swap with adjacent block or move to empty space
      if (!this.selectedBlock) {
        this.selectedBlock = { x: x, y: y, z: z };
        this.playSelectSound();
        var mesh = this.blockMeshes[x + '_' + y + '_' + z];
        if (mesh) {
          mesh.material.emissive = new THREE.Color(0xffffff);
          mesh.material.emissiveIntensity = 0.5;
        }
        // Show valid drop zones
        this.showValidDropZones(x, y, z);
        this.renderer.render(this.scene, this.camera);
        // Unlock immediately after selection
        this.isSettling = false;
      } else {
        var prev = this.selectedBlock;
        
        // Check if clicked position is adjacent
        if (this.isAdjacent3D(prev.x, prev.y, prev.z, x, y, z)) {
          // If clicked another block, swap them
          this.swapBlocks(prev.x, prev.y, prev.z, x, y, z);
          this.clearSelection();
          this.moves++;
          $('#moves').text(this.moves);
        } else {
          // Not adjacent, deselect
          this.clearSelection();
          this.isSettling = false;
        }
        
        this.renderer.render(this.scene, this.camera);
      }
    },

    clearSelection: function() {
      if (this.selectedBlock) {
        var prevMesh = this.blockMeshes[this.selectedBlock.x + '_' + this.selectedBlock.y + '_' + this.selectedBlock.z];
        if (prevMesh) {
          prevMesh.material.emissive = new THREE.Color(0x000000);
          prevMesh.material.emissiveIntensity = 0;
        }
        this.selectedBlock = null;
      }
      this.clearDropZones();
      this.playDeselectSound();
    },

    clearDropZones: function() {
      var self = this;
      this.dropZoneMeshes.forEach(function(mesh) {
        self.scene.remove(mesh);
      });
      this.dropZoneMeshes = [];
      this.validDropZones = [];
    },

    getDistanceFromCenter: function(x, y, z) {
      var centerPos = Math.floor(this.gridSize / 2);
      var dx = x - centerPos;
      var dy = y - centerPos;
      var dz = z - centerPos;
      return Math.sqrt(dx*dx + dy*dy + dz*dz);
    },

    isValidDropZone: function(fromX, fromY, fromZ, toX, toY, toZ) {
      // Must be adjacent
      if (!this.isAdjacent3D(fromX, fromY, fromZ, toX, toY, toZ)) {
        return false;
      }
      
      // Must be empty
      if (this.grid[toX][toY][toZ] !== -1) {
        return false;
      }
      
      // Must not move away from center (can move toward or maintain distance)
      var fromDist = this.getDistanceFromCenter(fromX, fromY, fromZ);
      var toDist = this.getDistanceFromCenter(toX, toY, toZ);
      
      return toDist <= fromDist;
    },

    showValidDropZones: function(x, y, z) {
      var self = this;
      var offset = this.gridSize / 2;
      
      // Clear existing drop zones
      this.clearDropZones();
      
      // Check all 6 adjacent positions
      var adjacentPositions = [
        [x-1, y, z], [x+1, y, z],
        [x, y-1, z], [x, y+1, z],
        [x, y, z-1], [x, y, z+1]
      ];
      
      var geometry = new THREE.BoxGeometry(0.85, 0.85, 0.85);
      
      adjacentPositions.forEach(function(pos) {
        var ax = pos[0], ay = pos[1], az = pos[2];
        
        if (self.isValidDropZone(x, y, z, ax, ay, az)) {
          // Create a semi-transparent green box to show valid drop zone
          var material = new THREE.MeshStandardMaterial({
            color: 0x00ff00,
            emissive: 0x00ff00,
            emissiveIntensity: 0.4,
            transparent: true,
            opacity: 0.3,
            depthTest: true
          });
          
          var mesh = new THREE.Mesh(geometry, material);
          mesh.position.set(ax - offset, ay - offset, az - offset);
          mesh.userData = { x: ax, y: ay, z: az, isDropZone: true };
          
          self.scene.add(mesh);
          self.dropZoneMeshes.push(mesh);
          self.validDropZones.push({ x: ax, y: ay, z: az });
        }
      });
    },

    moveBlockToEmpty: function(x1, y1, z1, x2, y2, z2) {
      console.log('>>> MOVE: Block from (' + x1 + ',' + y1 + ',' + z1 + ') to empty (' + x2 + ',' + y2 + ',' + z2 + ')');
      var self = this;
      
      // Move the block to the empty space
      this.grid[x2][y2][z2] = this.grid[x1][y1][z1];
      this.grid[x1][y1][z1] = -1;
      
      // Re-render to show the move
      this.render3D();
      
      // Settle blocks and spawn new blocks
      setTimeout(function() {
        self.dropBlocks(function() {
          // Spawn blocks equal to level
          var blocksToSpawn = self.level;
          self.regenerateBlocks(blocksToSpawn);
        });
      }, 300);
    },

    // Special Block Effect Functions
    handleSpecialBlock: function(blockType, x, y, z) {
      var self = this;
      
      // Remove the special block first
      this.grid[x][y][z] = -1;
      this.render3D();
      
      var special = this.getSpecialBlockData(blockType);
      if (special) {
        console.log('Activated: ' + special.name + ' ' + special.emoji);
      }
      
      switch(blockType) {
        case 100: this.effectBomb(x, y, z); break;
        case 101: this.effectLightning(x, y, z); break;
        case 105: this.effectShuffler(x, y, z); break;
        case 106: this.effectLaser(x, y, z); break;
        case 107: this.effectFreeze(x, y, z); break;
        case 108: this.effectMultiplier(x, y, z); break;
        case 109: this.effectJackpot(x, y, z); break;
        case 110: this.effectComboExtender(x, y, z); break;
        case 112: this.effectTeleporter(x, y, z); break;
        case 113: this.effectColorChanger(x, y, z); break;
        case 114: this.effectShield(x, y, z); break;
      }
    },

    // 100: Bomb - 3x3x3 explosion
    effectBomb: function(x, y, z) {
      var self = this;
      var toRemove = [];
      
      for (var dx = -1; dx <= 1; dx++) {
        for (var dy = -1; dy <= 1; dy++) {
          for (var dz = -1; dz <= 1; dz++) {
            var nx = x + dx, ny = y + dy, nz = z + dz;
            if (nx >= 0 && nx < this.gridSize && ny >= 0 && ny < this.gridSize && nz >= 0 && nz < this.gridSize) {
              if (this.grid[nx][ny][nz] >= 0) {
                toRemove.push({x: nx, y: ny, z: nz});
              }
            }
          }
        }
      }
      
      if (toRemove.length > 0) {
        this.removeMatches(toRemove, false, function() {
          self.completeTurn();
        });
      } else {
        this.completeTurn();
      }
    },

    // 101: Lightning - Destroy all blocks of same color
    effectLightning: function(x, y, z) {
      var self = this;
      var toRemove = [];
      
      // Pick a random regular color
      var targetColor = Math.floor(Math.random() * this.blockTypes);
      
      for (var ix = 0; ix < this.gridSize; ix++) {
        for (var iy = 0; iy < this.gridSize; iy++) {
          for (var iz = 0; iz < this.gridSize; iz++) {
            if (this.grid[ix][iy][iz] === targetColor) {
              toRemove.push({x: ix, y: iy, z: iz});
            }
          }
        }
      }
      
      if (toRemove.length > 0) {
        this.removeMatches(toRemove, false, function() {
          self.completeTurn();
        });
      } else {
        this.completeTurn();
      }
    },

    // 105: Shuffler - Randomly reposition 10 blocks
    effectShuffler: function(x, y, z) {
      var self = this;
      var blocks = [];
      for (var ix = 0; ix < this.gridSize; ix++) {
        for (var iy = 0; iy < this.gridSize; iy++) {
          for (var iz = 0; iz < this.gridSize; iz++) {
            if (this.grid[ix][iy][iz] >= 0) {
              blocks.push({x: ix, y: iy, z: iz, type: this.grid[ix][iy][iz]});
            }
          }
        }
      }
      
      // Shuffle 10 random blocks
      for (var i = 0; i < Math.min(10, blocks.length); i++) {
        var idx1 = Math.floor(Math.random() * blocks.length);
        var idx2 = Math.floor(Math.random() * blocks.length);
        
        var temp = this.grid[blocks[idx1].x][blocks[idx1].y][blocks[idx1].z];
        this.grid[blocks[idx1].x][blocks[idx1].y][blocks[idx1].z] = this.grid[blocks[idx2].x][blocks[idx2].y][blocks[idx2].z];
        this.grid[blocks[idx2].x][blocks[idx2].y][blocks[idx2].z] = temp;
      }
      
      this.render3D();
      this.dropBlocks(function() {
        self.completeTurn();
      });
    },

    // 106: Laser - Destroy entire line
    effectLaser: function(x, y, z) {
      var self = this;
      var toRemove = [];
      
      // Pick random axis
      var axis = Math.floor(Math.random() * 3);
      
      if (axis === 0) { // X axis
        for (var ix = 0; ix < this.gridSize; ix++) {
          if (this.grid[ix][y][z] >= 0) toRemove.push({x: ix, y: y, z: z});
        }
      } else if (axis === 1) { // Y axis
        for (var iy = 0; iy < this.gridSize; iy++) {
          if (this.grid[x][iy][z] >= 0) toRemove.push({x: x, y: iy, z: z});
        }
      } else { // Z axis
        for (var iz = 0; iz < this.gridSize; iz++) {
          if (this.grid[x][y][iz] >= 0) toRemove.push({x: x, y: y, z: iz});
        }
      }
      
      if (toRemove.length > 0) {
        this.removeMatches(toRemove, false, function() {
          self.completeTurn();
        });
      } else {
        this.completeTurn();
      }
    },

    // 107: Freeze - No blocks drop for 2 turns
    effectFreeze: function(x, y, z) {
      var self = this;
      this.freezeTurnsLeft = 2;
      this.showGameMessage('❄️ Freeze activated! No new blocks for 2 turns.');
      this.dropBlocks(function() {
        self.completeTurn();
      });
    },

    // 108: Multiplier - Double points for 5 explosions
    effectMultiplier: function(x, y, z) {
      var self = this;
      this.pointMultiplier = 2;
      this.multiplierTurnsLeft = 5;
      this.showGameMessage('💎 2x Multiplier activated for 5 explosions!');
      this.dropBlocks(function() {
        self.completeTurn();
      });
    },

    // 109: Jackpot - Bonus points
    effectJackpot: function(x, y, z) {
      var self = this;
      var bonus = 100 * this.level;
      this.score += bonus;
      $('#score').text(this.score);
      this.showGameMessage('🎰 Jackpot! +' + bonus + ' points!');
      this.dropBlocks(function() {
        self.completeTurn();
      });
    },

    // 110: Combo Extender - Keep combo running
    effectComboExtender: function(x, y, z) {
      var self = this;
      this.showGameMessage('⭐ Combo extender activated!');
      // TODO: Implement combo timer system
      this.dropBlocks(function() {
        self.completeTurn();
      });
    },

    // 112: Teleporter - Swap 5 random block pairs
    effectTeleporter: function(x, y, z) {
      var self = this;
      var blocks = [];
      for (var ix = 0; ix < this.gridSize; ix++) {
        for (var iy = 0; iy < this.gridSize; iy++) {
          for (var iz = 0; iz < this.gridSize; iz++) {
            if (this.grid[ix][iy][iz] >= 0) {
              blocks.push({x: ix, y: iy, z: iz});
            }
          }
        }
      }
      
      for (var i = 0; i < 5 && blocks.length >= 2; i++) {
        var idx1 = Math.floor(Math.random() * blocks.length);
        var idx2 = Math.floor(Math.random() * blocks.length);
        
        var temp = this.grid[blocks[idx1].x][blocks[idx1].y][blocks[idx1].z];
        this.grid[blocks[idx1].x][blocks[idx1].y][blocks[idx1].z] = this.grid[blocks[idx2].x][blocks[idx2].y][blocks[idx2].z];
        this.grid[blocks[idx2].x][blocks[idx2].y][blocks[idx2].z] = temp;
      }
      
      this.render3D();
      this.dropBlocks(function() {
        self.completeTurn();
      });
    },

    // 113: Color Changer - Convert one color to another
    effectColorChanger: function(x, y, z) {
      var fromColor = Math.floor(Math.random() * this.blockTypes);
      var toColor = Math.floor(Math.random() * this.blockTypes);
      
      for (var ix = 0; ix < this.gridSize; ix++) {
        for (var iy = 0; iy < this.gridSize; iy++) {
          for (var iz = 0; iz < this.gridSize; iz++) {
            if (this.grid[ix][iy][iz] === fromColor) {
              this.grid[ix][iy][iz] = toColor;
            }
          }
        }
      }
      
      this.render3D();
      this.isSettling = false;
      this.showGameMessage('🎨 Color changed!');
    },

    // 114: Shield - Save from game over once
    effectShield: function(x, y, z) {
      var self = this;
      this.hasShield = true;
      this.showGameMessage('🛡️ Shield activated! One free continue.');
      this.dropBlocks(function() {
        self.isSettling = false;
      });
    },

    isAdjacent3D: function(x1, y1, z1, x2, y2, z2) {
      var dx = Math.abs(x1 - x2);
      var dy = Math.abs(y1 - y2);
      var dz = Math.abs(z1 - z2);
      return (dx + dy + dz) === 1;
    },

    updateComboDisplay: function() {
      var total = this.initialMatchCount + this.comboMatchCount;
      var display = '';
      
      if (this.initialMatchCount > 0) {
        display = 'Match: ' + this.initialMatchCount;
        if (this.comboMatchCount > 0) {
          display += ' | Combos: ' + this.comboMatchCount;
        }
        display += ' | Total: ' + total;
      }
      
      $('#combo-stats').text(display);
    },

    swapBlocks: function(x1, y1, z1, x2, y2, z2, isUndo) {
      console.log('>>> STEP 1: Player Swap - (' + x1 + ',' + y1 + ',' + z1 + ') <-> (' + x2 + ',' + y2 + ',' + z2 + ')');
      
      // Lock interaction during swap processing (only on initial swap, not undo)
      if (!isUndo) {
        this.isSettling = true;
      }
      
      // Reset combo tracking for new move
      this.initialMatchCount = 0;
      this.comboMatchCount = 0;
      this.updateComboDisplay();
      
      var temp = this.grid[x1][y1][z1];
      this.grid[x1][y1][z1] = this.grid[x2][y2][z2];
      this.grid[x2][y2][z2] = temp;
      
      this.render3D();
      
      // If this is an undo swap, don't check for matches
      if (isUndo) {
        this.isSettling = false; // Unlock after undo
        return;
      }
      
      var self = this;
      setTimeout(function() {
        var matches1 = self.checkMatchAt(x1, y1, z1);
        var matches2 = self.checkMatchAt(x2, y2, z2);
        
        if (matches1.length >= self.minMatch || matches2.length >= self.minMatch) {
          // Process matches and spawn new blocks
          self.processMatches();
        } else {
          // No match - settle and spawn new blocks anyway
          self.dropBlocks(function() {
            // Spawn blocks equal to level
            var blocksToSpawn = self.level;
            self.regenerateBlocks(blocksToSpawn);
          });
        }
      }, 300);
    },

    checkMatchAt: function(x, y, z) {
      var centerPos = Math.floor(this.gridSize / 2);
      
      // Never match the center block
      if (x === centerPos && y === centerPos && z === centerPos) {
        return [];
      }
      
      if (this.grid[x][y][z] === -1 || this.grid[x][y][z] === -2) return [];
      
      var color = this.getBlockMatchColor(this.grid[x][y][z]);
      var matches = [{x: x, y: y, z: z}];
      
      // Check X axis
      var left = x - 1;
      while (left >= 0 && this.getBlockMatchColor(this.grid[left][y][z]) === color) {
        matches.push({x: left, y: y, z: z});
        left--;
      }
      var right = x + 1;
      while (right < this.gridSize && this.getBlockMatchColor(this.grid[right][y][z]) === color) {
        matches.push({x: right, y: y, z: z});
        right++;
      }
      
      // Check Y axis
      var down = y - 1;
      while (down >= 0 && this.getBlockMatchColor(this.grid[x][down][z]) === color) {
        matches.push({x: x, y: down, z: z});
        down--;
      }
      var up = y + 1;
      while (up < this.gridSize && this.getBlockMatchColor(this.grid[x][up][z]) === color) {
        matches.push({x: x, y: up, z: z});
        up++;
      }
      
      // Check Z axis
      var back = z - 1;
      while (back >= 0 && this.getBlockMatchColor(this.grid[x][y][back]) === color) {
        matches.push({x: x, y: y, z: back});
        back--;
      }
      var forward = z + 1;
      while (forward < this.gridSize && this.getBlockMatchColor(this.grid[x][y][forward]) === color) {
        matches.push({x: x, y: y, z: forward});
        forward++;
      }
      
      return matches;
    },

    processMatches: function() {
      console.log('>>> STEP 2: Check Initial Matches');
      var allMatches = [];
      
      for (var x = 0; x < this.gridSize; x++) {
        for (var y = 0; y < this.gridSize; y++) {
          for (var z = 0; z < this.gridSize; z++) {
            var matches = this.checkMatchAt(x, y, z);
            if (matches.length >= this.minMatch) {
              allMatches = allMatches.concat(matches);
            }
          }
        }
      }
      
      if (allMatches.length > 0) {
        this.initialMatchCount = allMatches.length;
        this.updateComboDisplay();
        this.removeMatches(allMatches, false);
        var points = allMatches.length * 10 * this.pointMultiplier;
        this.score += points;
        $('#score').text(this.score);
        // Decrement multiplier turns
        if (this.multiplierTurnsLeft > 0) {
          this.multiplierTurnsLeft--;
          if (this.multiplierTurnsLeft === 0) {
            this.pointMultiplier = 1;
          }
        }
      }
    },

    processMatchesWithoutDrop: function(callback) {
      console.log('>>> STEP 5: Check Chain Matches');
      var self = this;
      var allMatches = [];
      
      for (var x = 0; x < this.gridSize; x++) {
        for (var y = 0; y < this.gridSize; y++) {
          for (var z = 0; z < this.gridSize; z++) {
            var matches = this.checkMatchAt(x, y, z);
            if (matches.length >= this.minMatch) {
              allMatches = allMatches.concat(matches);
            }
          }
        }
      }
      
      if (allMatches.length > 0) {
        this.comboMatchCount += allMatches.length;
        this.updateComboDisplay();
        this.removeMatches(allMatches, true, callback);
        var points = allMatches.length * 10 * this.pointMultiplier;
        this.score += points;
        $('#score').text(this.score);
        // Decrement multiplier turns
        if (this.multiplierTurnsLeft > 0) {
          this.multiplierTurnsLeft--;
          if (this.multiplierTurnsLeft === 0) {
            this.pointMultiplier = 1;
          }
        }
      } else {
        // No more matches, trigger callback if provided
        if (callback) {
          setTimeout(callback, 100);
        }
      }
    },

    removeMatches: function(matches, skipDrop, callback) {
      var self = this;
      var eliminatedCount = matches.length;
      var offset = this.gridSize / 2;
      
      console.log('>>> STEP 3: Explosion - ' + eliminatedCount + ' blocks (skipDrop=' + skipDrop + ')');
      
      // Play explosion sound
      this.playExplosionSound(eliminatedCount);
      
      // Animate explosion before removing
      matches.forEach(function(match) {
        var worldX = match.x - offset;
        var worldY = match.y - offset;
        var worldZ = match.z - offset;
        
        var mesh = self.scene.children.find(function(child) {
          return child.position.x === worldX && 
                 child.position.y === worldY && 
                 child.position.z === worldZ &&
                 child.geometry && child.geometry.type === 'BoxGeometry';
        });
        
        if (mesh) {
          // Explosion animation: scale up, rotate, brighten and fade out
          var startScale = 1;
          var endScale = 4;
          var steps = 8;
          var currentStep = 0;
          var originalColor = mesh.material.color.getHex();
          
          var explode = function() {
            currentStep++;
            var progress = currentStep / steps;
            var scale = startScale + (endScale - startScale) * progress;
            mesh.scale.set(scale, scale, scale);
            
            // Rotate for dramatic effect
            mesh.rotation.x += 0.2;
            mesh.rotation.y += 0.3;
            mesh.rotation.z += 0.1;
            
            // Brighten then fade
            var brightness = progress < 0.3 ? 1 + progress * 3 : 1 + (1 - progress) * 2;
            mesh.material.emissive.setHex(originalColor);
            mesh.material.emissiveIntensity = brightness;
            mesh.material.opacity = 1 - progress;
            mesh.material.transparent = true;
            
            if (currentStep < steps) {
              setTimeout(explode, 30);
            }
          };
          explode();
        }
      });
      
      setTimeout(function() {
        // Remove matched blocks from grid and their meshes
        matches.forEach(function(match) {
          self.grid[match.x][match.y][match.z] = -1;
          
          // Remove the specific mesh for this block
          var key = match.x + '_' + match.y + '_' + match.z;
          var mesh = self.blockMeshes[key];
          if (mesh) {
            self.scene.remove(mesh);
            if (mesh.geometry) mesh.geometry.dispose();
            if (mesh.material) mesh.material.dispose();
            delete self.blockMeshes[key];
          }
        });
        // DO NOT call render3D() here - it would recreate all meshes at their current positions
        // Settlement will animate blocks to their new positions
      }, 250);
      
      setTimeout(function() {
        if (!skipDrop) {
          // Drop blocks toward center
          self.dropBlocks(function() {
            // After settling, check for chain matches without dropping
            self.processMatchesWithoutDrop(function() {
              // After all chain matches cleared, settle again before regenerating
              console.log('>>> STEP 4b: Final Settle Before Regeneration');
              self.dropBlocks(function() {
                // Now regenerate blocks (handles its own completion via completeTurn)
                self.regenerateBlocks(eliminatedCount);
              });
            });
          });
        } else {
          // Just check for more matches without dropping, then callback
          setTimeout(function() {
            self.processMatchesWithoutDrop(callback);
          }, 300);
        }
      }, 300);
    },

    dropBlocks: function(callback) {
      console.log('>>> STEP 4: Drop & Settle Starting');
      var self = this;
      this.movingBlocks = {};
      
      // No render needed - grid hasn't changed yet, settlement will animate moves
      self.settleAllBlocks(callback);
    },

    settleAllBlocks: function(callback) {
      console.log('  >> Settle: Beginning settlement');
      var self = this;
      var centerPos = Math.floor(this.gridSize / 2);
      
      // Lock interaction during settlement
      this.isSettling = true;
      console.log('  >> Settle: isSettling set to TRUE');
      
      function settleStep() {
        console.log('  >> Settle: settleStep() iteration starting');
        // Use pre-calculated, pre-sorted distance cache (no calculations or sorting needed!)
        // This eliminates ~58,000-116,000 operations per turn
        var moveMap = {}; // Track old position -> new position
        var movedThisIteration = {}; // Track blocks that already moved this iteration
        var blocksChecked = 0;
        var blocksMoved = 0;
        
        // Process each position from pre-sorted cache (furthest to closest)
        // NOTE: blocksByDistance is already sorted by distance (furthest first)
        for (var i = 0; i < self.blocksByDistance.length; i++) {
          var pos = self.blocksByDistance[i];
          var x = pos.x;
          var y = pos.y;
          var z = pos.z;
          var posKey = x + '_' + y + '_' + z;
          
          // Skip if no block at this position
          if (self.grid[x][y][z] === -1) continue;
          
          // Skip if this position is a NEW destination from a move this iteration
          // (prevents moving the same block multiple times in one iteration)
          if (movedThisIteration[posKey]) continue;
          
          blocksChecked++;
          
          // Log blocks we're checking
          if (blocksChecked <= 5 || self.grid[x][y][z] >= 100) {
            console.log('  >> Settle: Checking block at (' + x + ',' + y + ',' + z + ') type=' + self.grid[x][y][z]);
          }
          
          // Skip center block - it never moves
          if (x === centerPos && y === centerPos && z === centerPos) continue;
          
          // Use pre-calculated distances from cache (no sqrt or abs calculations!)
          var xDist = pos.xDist;
          var yDist = pos.yDist;
          var zDist = pos.zDist;
          
          var xDir = x < centerPos ? 1 : (x > centerPos ? -1 : 0);
          var yDir = y < centerPos ? 1 : (y > centerPos ? -1 : 0);
          var zDir = z < centerPos ? 1 : (z > centerPos ? -1 : 0);
          
          // Try to move one space on the axis with highest distance
          var blockMoved = false;
          var oldKey = x + '_' + y + '_' + z;
          
          if (xDist >= yDist && xDist >= zDist && xDir !== 0 && !blockMoved) {
            var newX = x + xDir;
            console.log('    >> Try X: (' + x + ',' + y + ',' + z + ') -> (' + newX + ',' + y + ',' + z + ') - distances[x=' + xDist + ',y=' + yDist + ',z=' + zDist + '] target=' + (self.grid[newX] && self.grid[newX][y] ? self.grid[newX][y][z] : 'OOB'));
            if (newX >= 0 && newX < self.gridSize && self.grid[newX][y][z] === -1) {
              console.log('  >> Settle: Moving block from (' + x + ',' + y + ',' + z + ') to (' + newX + ',' + y + ',' + z + ')');
              var newKey = newX + '_' + y + '_' + z;
              moveMap[oldKey] = { newX: newX, newY: y, newZ: z, oldX: x, oldY: y, oldZ: z };
              movedThisIteration[newKey] = true;  // Mark destination as occupied by a moved block
              self.grid[newX][y][z] = self.grid[x][y][z];
              self.grid[x][y][z] = -1;
              blockMoved = true;
              blocksMoved++;
            }
          }
          
          if (yDist >= xDist && yDist >= zDist && yDir !== 0 && !blockMoved) {
            var newY = y + yDir;
            console.log('    >> Try Y: (' + x + ',' + y + ',' + z + ') -> (' + x + ',' + newY + ',' + z + ') - distances[x=' + xDist + ',y=' + yDist + ',z=' + zDist + '] target=' + (self.grid[x] && self.grid[x][newY] ? self.grid[x][newY][z] : 'OOB'));
            if (newY >= 0 && newY < self.gridSize && self.grid[x][newY][z] === -1) {
              console.log('  >> Settle: Moving block from (' + x + ',' + y + ',' + z + ') to (' + x + ',' + newY + ',' + z + ')');
              var newKey = x + '_' + newY + '_' + z;
              moveMap[oldKey] = { newX: x, newY: newY, newZ: z, oldX: x, oldY: y, oldZ: z };
              movedThisIteration[newKey] = true;  // Mark destination as occupied by a moved block
              self.grid[x][newY][z] = self.grid[x][y][z];
              self.grid[x][y][z] = -1;
              blockMoved = true;
              blocksMoved++;
            }
          }
          
          if (zDist >= xDist && zDist >= yDist && zDir !== 0 && !blockMoved) {
            var newZ = z + zDir;
            console.log('    >> Try Z: (' + x + ',' + y + ',' + z + ') -> (' + x + ',' + y + ',' + newZ + ') - distances[x=' + xDist + ',y=' + yDist + ',z=' + zDist + '] target=' + (self.grid[x] && self.grid[x][y] ? self.grid[x][y][newZ] : 'OOB'));
            if (newZ >= 0 && newZ < self.gridSize && self.grid[x][y][newZ] === -1) {
              console.log('  >> Settle: Moving block from (' + x + ',' + y + ',' + z + ') to (' + x + ',' + y + ',' + newZ + ')');
              var newKey = x + '_' + y + '_' + newZ;
              moveMap[oldKey] = { newX: x, newY: y, newZ: newZ, oldX: x, oldY: y, oldZ: z };
              movedThisIteration[newKey] = true;  // Mark destination as occupied by a moved block
              self.grid[x][y][newZ] = self.grid[x][y][z];
              self.grid[x][y][z] = -1;
              blockMoved = true;
              blocksMoved++;
            }
          }
        }
        
        console.log('  >> Settle: Checked ' + blocksChecked + ' blocks, moved ' + blocksMoved + ' blocks');
        
        var moveCount = Object.keys(moveMap).length;
        console.log('  >> Settle: moveMap contains ' + moveCount + ' moves');
        
        if (moveCount > 0) {
          // Animate blocks to new positions smoothly
          console.log('  >> Settle: Calling animateBlockMovement() with ' + moveCount + ' blocks');
          self.animateBlockMovement(moveMap, function() {
            console.log('  >> Settle: Animation callback - iteration complete, blocks moved');
            setTimeout(settleStep, 25); // Fast iterations for fluid movement
          });
        } else {
          console.log('  >> Settle: No blocks to move, settlement complete');
          // All blocks fully settled - always check for new matches
          // No render3D() needed - meshes already in correct positions from animation
          console.log('  >> Settle: Complete - all blocks stable');
          console.log('  >> Settle: Checking for new matches after settlement');
          
          // Always check for matches after settling
          var hadMatches = false;
          for (var x = 0; x < self.gridSize; x++) {
            for (var y = 0; y < self.gridSize; y++) {
              for (var z = 0; z < self.gridSize; z++) {
                var matches = self.checkMatchAt(x, y, z);
                if (matches.length >= self.minMatch) {
                  hadMatches = true;
                  break;
                }
              }
              if (hadMatches) break;
            }
            if (hadMatches) break;
          }
          
          if (hadMatches) {
            // Found matches after settling, process them then settle again
            console.log('  >> Settle: Matches found after settlement, processing...');
            self.processMatchesWithoutDrop(function() {
              // After chain reactions, settle again to fill gaps
              console.log('  >> Settle: Re-settling after matches...');
              self.settleAllBlocks(callback);
            });
          } else {
            // No matches, proceed with callback (don't unlock yet - let callback chain finish)
            console.log('  >> Settle: Complete, calling callback');
            if (callback) callback();
          }
        }
      }
      
      settleStep();
    },

    animateBlockMovement: function(moveMap, callback) {
      console.log('  >> ANIMATE: Starting animation for ' + Object.keys(moveMap).length + ' blocks');
      var self = this;
      var startTime = performance.now();
      var duration = 100; // Fast, smooth animation
      var offset = this.gridSize / 2;
      
      function animate(currentTime) {
        var elapsed = currentTime - startTime;
        var progress = Math.min(elapsed / duration, 1);
        console.log('  >> ANIMATE: Frame - elapsed=' + elapsed.toFixed(0) + 'ms, progress=' + (progress * 100).toFixed(1) + '%');
        
        // Ease-out cubic for smooth deceleration
        var eased = 1 - Math.pow(1 - progress, 3);
        
        // Update each moving mesh position with interpolation
        var meshesUpdated = 0;
        var meshesMissing = 0;
        Object.keys(moveMap).forEach(function(oldKey) {
          var move = moveMap[oldKey];
          var mesh = self.blockMeshes[oldKey];
          
          if (mesh) {
            var oldX = move.oldX - offset;
            var oldY = move.oldY - offset;
            var oldZ = move.oldZ - offset;
            
            var newX = move.newX - offset;
            var newY = move.newY - offset;
            var newZ = move.newZ - offset;
            
            var oldPos = mesh.position.x + ',' + mesh.position.y + ',' + mesh.position.z;
            mesh.position.x = oldX + (newX - oldX) * eased;
            mesh.position.y = oldY + (newY - oldY) * eased;
            mesh.position.z = oldZ + (newZ - oldZ) * eased;
            var newPos = mesh.position.x.toFixed(2) + ',' + mesh.position.y.toFixed(2) + ',' + mesh.position.z.toFixed(2);
            
            if (progress === 0 || progress === 1) {
              console.log('    >> MESH UPDATE: ' + oldKey + ' mesh position ' + oldPos + ' -> ' + newPos + ' (eased=' + eased.toFixed(2) + ')');
            }
            meshesUpdated++;
          } else {
            meshesMissing++;
            if (progress === 0) {
              console.log('    >> MESH MISSING: No mesh found for key ' + oldKey + ' (grid has type ' + self.grid[move.oldX][move.oldY][move.oldZ] + ')');
            }
          }
        });
        
        if (progress === 0 || progress === 1) {
          console.log('    >> RENDER: Updated ' + meshesUpdated + ' meshes, missing ' + meshesMissing + ', rendering scene');
        }
        self.renderer.render(self.scene, self.camera);
        
        if (progress < 1) {
          requestAnimationFrame(animate);
        } else {
          console.log('  >> ANIMATE: Animation complete for ' + Object.keys(moveMap).length + ' blocks');
          // Animation complete - play click sound based on number of blocks moved
          var blockCount = Object.keys(moveMap).length;
          self.playClickSound(blockCount);
          
          // Update mesh keys to match new grid positions (no need to rebuild everything)
          Object.keys(moveMap).forEach(function(oldKey) {
            var move = moveMap[oldKey];
            var mesh = self.blockMeshes[oldKey];
            if (mesh) {
              var newKey = move.newX + '_' + move.newY + '_' + move.newZ;
              mesh.userData = { x: move.newX, y: move.newY, z: move.newZ };
              self.blockMeshes[newKey] = mesh;
              delete self.blockMeshes[oldKey];
            }
          });
          
          // Callback to continue settlement
          console.log('  >> ANIMATE: Calling callback to continue settlement');
          if (callback) callback();
        }
      }
      
      console.log('  >> ANIMATE: Starting requestAnimationFrame loop');
      requestAnimationFrame(animate);
    },

    settleAllBlocksWithCount: function(callback) {
      console.log('  >> Settle: Beginning settlement with move tracking');
      var self = this;
      var centerPos = Math.floor(this.gridSize / 2);
      var totalMoves = 0;
      
      function settleStep() {
        // Collect all blocks with their positions and distances
        var blocks = [];
        for (var x = 0; x < self.gridSize; x++) {
          for (var y = 0; y < self.gridSize; y++) {
            for (var z = 0; z < self.gridSize; z++) {
              if (self.grid[x][y][z] !== -1) {
                var xDist = Math.abs(x - centerPos);
                var yDist = Math.abs(y - centerPos);
                var zDist = Math.abs(z - centerPos);
                var totalDist = Math.sqrt(xDist*xDist + yDist*yDist + zDist*zDist);
                blocks.push({ x: x, y: y, z: z, dist: totalDist });
              }
            }
          }
        }
        
        // Sort by distance: closest to center first
        blocks.sort(function(a, b) {
          return a.dist - b.dist;
        });
        
        var moved = false;
        var movesThisIteration = 0;
        
        // Process each block in order, moving one space if possible
        for (var i = 0; i < blocks.length; i++) {
          var block = blocks[i];
          var x = block.x;
          var y = block.y;
          var z = block.z;
          
          // Skip if block was already moved by another block
          if (self.grid[x][y][z] === -1) continue;
          
          // Calculate distances and directions
          var xDist = Math.abs(x - centerPos);
          var yDist = Math.abs(y - centerPos);
          var zDist = Math.abs(z - centerPos);
          
          var xDir = x < centerPos ? 1 : (x > centerPos ? -1 : 0);
          var yDir = y < centerPos ? 1 : (y > centerPos ? -1 : 0);
          var zDir = z < centerPos ? 1 : (z > centerPos ? -1 : 0);
          
          // Try to move one space on the axis with highest distance
          var blockMoved = false;
          
          if (xDist >= yDist && xDist >= zDist && xDir !== 0 && !blockMoved) {
            var newX = x + xDir;
            if (newX >= 0 && newX < self.gridSize && self.grid[newX][y][z] === -1) {
              self.grid[newX][y][z] = self.grid[x][y][z];
              self.grid[x][y][z] = -1;
              blockMoved = true;
              moved = true;
              movesThisIteration++;
            }
          }
          
          if (yDist >= xDist && yDist >= zDist && yDir !== 0 && !blockMoved) {
            var newY = y + yDir;
            if (newY >= 0 && newY < self.gridSize && self.grid[x][newY][z] === -1) {
              self.grid[x][newY][z] = self.grid[x][y][z];
              self.grid[x][y][z] = -1;
              blockMoved = true;
              moved = true;
              movesThisIteration++;
            }
          }
          
          if (zDist >= xDist && zDist >= yDist && zDir !== 0 && !blockMoved) {
            var newZ = z + zDir;
            if (newZ >= 0 && newZ < self.gridSize && self.grid[x][y][newZ] === -1) {
              self.grid[x][y][newZ] = self.grid[x][y][z];
              self.grid[x][y][z] = -1;
              blockMoved = true;
              moved = true;
              movesThisIteration++;
            }
          }
        }
        
        totalMoves += movesThisIteration;
        
        if (moved) {
          self.render3D();
          console.log('  >> Settle: Iteration complete - ' + movesThisIteration + ' blocks moved (total: ' + totalMoves + ')');
          setTimeout(settleStep, 250);
        } else {
          // All blocks fully settled
          self.render3D();
          console.log('  >> Settle: Complete - all blocks stable');
          if (callback) callback(totalMoves);
        }
      }
      
      settleStep();
    },

    regenerateBlocks: function(eliminatedCount) {
      var self = this;
      // Number of blocks to regenerate equals current level (1-9)
      // Only regenerate if blocks were actually eliminated
      var newBlockCount = eliminatedCount > 0 ? this.level : 0;
      
      console.log('>>> STEP 6: Regenerate Blocks - adding ' + newBlockCount + ' blocks (level ' + this.level + ') (eliminated: ' + eliminatedCount + ')');
      
      // If no blocks to add, complete turn immediately
      if (newBlockCount === 0) {
        console.log('>>> STEP 6: No blocks to regenerate, completing turn');
        self.completeTurn();
        return;
      }
      
      // Calculate playable area boundaries
      var centerPos = Math.floor(this.gridSize / 2);
      var halfSize = Math.floor(this.playableSize / 2);
      var minIndex = centerPos - halfSize;
      var maxIndex = centerPos + halfSize;
      
      // Spawn blocks at the TRUE GRID EDGES (far from center) so they have a long journey inward
      var spawnMin = 0;  // Far edge of grid
      var spawnMax = this.gridSize - 1;  // Far edge of grid
      
      console.log('>>> REGEN: Playable area is from ' + minIndex + ' to ' + maxIndex + ' (center=' + centerPos + ', playableSize=' + this.playableSize + ')');
      console.log('>>> REGEN: Spawn positions will be at grid edges: ' + spawnMin + ' and ' + spawnMax);
      
      var faces = [
        { name: 'top', axis: 'y', value: spawnMax, range: [minIndex, maxIndex] },
        { name: 'bottom', axis: 'y', value: spawnMin, range: [minIndex, maxIndex] },
        { name: 'left', axis: 'x', value: spawnMin, range: [minIndex, maxIndex] },
        { name: 'right', axis: 'x', value: spawnMax, range: [minIndex, maxIndex] },
        { name: 'front', axis: 'z', value: spawnMax, range: [minIndex, maxIndex] },
        { name: 'back', axis: 'z', value: spawnMin, range: [minIndex, maxIndex] }
      ];
      
      // Place blocks directly at grid edges
      var blocksPlaced = [];
      for (var i = 0; i < newBlockCount; i++) {
        var face = faces[Math.floor(Math.random() * faces.length)];
        var placed = false;
        var attempts = 0;
        
        while (!placed && attempts < 50) {
          var x, y, z;
          
          // Place block at edge position (outside playable area)
          // Other coordinates stay within playable area range
          if (face.axis === 'x') {
            x = face.value;  // Outside edge
            y = face.range[0] + Math.floor(Math.random() * (face.range[1] - face.range[0] + 1));
            z = face.range[0] + Math.floor(Math.random() * (face.range[1] - face.range[0] + 1));
          } else if (face.axis === 'y') {
            x = face.range[0] + Math.floor(Math.random() * (face.range[1] - face.range[0] + 1));
            y = face.value;  // Outside edge
            z = face.range[0] + Math.floor(Math.random() * (face.range[1] - face.range[0] + 1));
          } else {
            x = face.range[0] + Math.floor(Math.random() * (face.range[1] - face.range[0] + 1));
            y = face.range[0] + Math.floor(Math.random() * (face.range[1] - face.range[0] + 1));
            z = face.value;  // Outside edge
          }
          
          // Place if empty
          if (this.grid[x][y][z] === -1) {
            var blockType = this.randomBlockType();
            this.grid[x][y][z] = blockType;
            blocksPlaced.push({x: x, y: y, z: z, type: blockType});
            placed = true;
          }
          attempts++;
        }
      }
      console.log('>>> REGEN: Placed blocks at positions:', blocksPlaced);
      
      // Log each placed block for debugging
      blocksPlaced.forEach(function(block) {
        console.log('>>> REGEN: Block placed at (' + block.x + ',' + block.y + ',' + block.z + ') type=' + block.type);
      });
      
      // Create meshes ONLY for new blocks (don't rebuild existing ones)
      console.log('>>> REGEN: Creating meshes for ' + blocksPlaced.length + ' new blocks');
      blocksPlaced.forEach(function(block) {
        var key = block.x + '_' + block.y + '_' + block.z;
        var blockSize = 1;  // Must match render3D() scale: 1 world unit = 1 grid unit
        var center = self.gridSize / 2;
        var worldX = (block.x - center) * blockSize;
        var worldY = (block.y - center) * blockSize;
        var worldZ = (block.z - center) * blockSize;
        
        // Create mesh for this new block (0.9 to match render3D spacing)
        var geometry = new THREE.BoxGeometry(blockSize * 0.9, blockSize * 0.9, blockSize * 0.9);
        var color = self.getBlockColor(block.type);
        var material = new THREE.MeshPhongMaterial({ color: color });
        var mesh = new THREE.Mesh(geometry, material);
        mesh.position.set(worldX, worldY, worldZ);
        mesh.userData = { x: block.x, y: block.y, z: block.z };
        
        self.scene.add(mesh);
        self.blockMeshes[key] = mesh;
        
        // Add emoji sprite for special blocks
        if (self.isSpecialBlock(block.type)) {
          var special = self.getSpecialBlockData(block.type);
          if (special) {
            console.log('>>> REGEN: Adding sprite for special block:', block.type, special.name, special.emoji);
            var spriteMap = self.createEmojiTexture(special.emoji);
            var spriteMaterial = new THREE.SpriteMaterial({ 
              map: spriteMap,
              transparent: false,
              depthTest: true,
              depthWrite: true
            });
            var sprite = new THREE.Sprite(spriteMaterial);
            sprite.scale.set(0.9, 0.9, 0.9);
            mesh.add(sprite);
          }
        }
        
        console.log('>>> REGEN: Created mesh at key=' + key + ' worldPos=(' + worldX + ',' + worldY + ',' + worldZ + ')');
      });
      console.log('>>> REGEN: Mesh creation complete, calling dropBlocks() immediately');
      self.dropBlocks(function() {
        // Check for matches after settling
        self.processMatchesWithoutDrop(function() {
          // If there were chain reactions, settle again
          console.log('>>> STEP 4c: Final Settle After Regeneration Chains');
          self.dropBlocks(function() {
            self.completeTurn();
          });
        });
      });
    },

    getRandomSpawnPosition: function(face) {
      var maxIndex = this.gridSize - 1;
      var attempts = 0;
      var maxAttempts = 50;
      
      while (attempts < maxAttempts) {
        var x, y, z, spawnX, spawnY, spawnZ;
        
        if (face.axis === 'x') {
          x = face.value;
          y = Math.floor(Math.random() * this.gridSize);
          z = Math.floor(Math.random() * this.gridSize);
          spawnX = face.value + (face.dir * 9);
          spawnY = y;
          spawnZ = z;
        } else if (face.axis === 'y') {
          x = Math.floor(Math.random() * this.gridSize);
          y = face.value;
          z = Math.floor(Math.random() * this.gridSize);
          spawnX = x;
          spawnY = face.value + (face.dir * 9);
          spawnZ = z;
        } else { // z axis
          x = Math.floor(Math.random() * this.gridSize);
          y = Math.floor(Math.random() * this.gridSize);
          z = face.value;
          spawnX = x;
          spawnY = y;
          spawnZ = face.value + (face.dir * 9);
        }
        
        if (this.grid[x][y][z] === -1) {
          return {
            spawn: { x: spawnX, y: spawnY, z: spawnZ },
            target: { x: x, y: y, z: z }
          };
        }
        
        attempts++;
      }
      
      return null;
    },

    animateNewBlocks: function(newBlocks) {
      var self = this;
      
      // Create temporary meshes for incoming blocks
      var geometry = new THREE.BoxGeometry(0.9, 0.9, 0.9);
      var tempMeshes = [];
      
      newBlocks.forEach(function(block) {
        var material = new THREE.MeshPhongMaterial({
          color: self.getBlockColor(block.color),
          shininess: 30,
          transparent: true,
          opacity: 0.7
        });
        
        var mesh = new THREE.Mesh(geometry, material);
        var offset = self.gridSize / 2;
        mesh.position.set(
          block.current.x - offset,
          block.current.y - offset,
          block.current.z - offset
        );
        
        // Store spawn position for interpolation
        block.spawn = { x: block.current.x, y: block.current.y, z: block.current.z };
        
        self.scene.add(mesh);
        tempMeshes.push({ mesh: mesh, block: block, added: false });
      });
      
      // Animate blocks moving inward smoothly
      var startTime = Date.now();
      var animationDuration = 2000; // 2 seconds for smooth travel
      
      function animateStep() {
        var elapsed = Date.now() - startTime;
        var progress = Math.min(elapsed / animationDuration, 1);
        var stillMoving = progress < 1;
        
        var offset = self.gridSize / 2;
        
        tempMeshes.forEach(function(item) {
          if (progress < 1) {
            // Smooth interpolation from spawn to target
            var startX = item.block.spawn.x;
            var startY = item.block.spawn.y;
            var startZ = item.block.spawn.z;
            var targetX = item.block.target.x;
            var targetY = item.block.target.y;
            var targetZ = item.block.target.z;
            
            // Ease-in-out interpolation
            var easeProgress = progress < 0.5 
              ? 2 * progress * progress 
              : 1 - Math.pow(-2 * progress + 2, 2) / 2;
            
            var currentX = startX + (targetX - startX) * easeProgress;
            var currentY = startY + (targetY - startY) * easeProgress;
            var currentZ = startZ + (targetZ - startZ) * easeProgress;
            
            item.mesh.position.set(
              currentX - offset,
              currentY - offset,
              currentZ - offset
            );
          } else if (!item.added) {
            // Animation complete, add to grid
            item.added = true;
            self.grid[item.block.target.x][item.block.target.y][item.block.target.z] = item.block.color;
            self.scene.remove(item.mesh);
          }
        });
        
        self.renderer.render(self.scene, self.camera);
        
        if (stillMoving) {
          requestAnimationFrame(animateStep);
        } else {
          // All blocks arrived, now drop them toward center
          setTimeout(function() {
            self.dropBlocks(function() {
              // Check for matches after new blocks settle
              self.processMatchesWithoutDrop(function() {
                self.completeTurn();
              });
            });
          }, 500);
        }
      }
      
      animateStep();
    },

    checkWinCondition: function() {
      var blockCount = 0;
      
      for (var x = 0; x < this.gridSize; x++) {
        for (var y = 0; y < this.gridSize; y++) {
          for (var z = 0; z < this.gridSize; z++) {
            if (this.grid[x][y][z] !== -1) {
              blockCount++;
            }
          }
        }
      }
      
      if (blockCount === 1) {
        this.gameOver(true, 'You Win! Only one block remains in 3D space!');
      }
    },

    gameOver: function(won, message) {
      clearInterval(this.timerInterval);
      var self = this;
      
      // Play victory sound if won
      if (won) {
        this.playVictorySound();
      }
      
      $('#final-score').text(this.score);
      $('#final-moves').text(this.moves);
      $('#final-time').text($('#timer').text());
      
      $('#game-over-modal h2').text(won ? 'Congratulations!' : 'Game Over');
      
      if (message) {
        var $message = $('<p>').addClass('game-message').text(message);
        $('#game-over-modal .modal-content p:first').remove();
        $('#game-over-modal .modal-content').prepend($message);
      }
      
      // Check if score qualifies for high score table
      var timeInSeconds = Math.floor((Date.now() - this.startTime) / 1000);
      this.checkHighScore(this.score, this.level, timeInSeconds, function(qualifies) {
        if (qualifies) {
          // Show high score modal instead
          self.showHighScoreModal(self.score, self.level, timeInSeconds);
        } else {
          // Show regular game over modal
          $('#game-over-modal').show();
        }
      });
    },

    checkHighScore: function(score, level, time, callback) {
      $.ajax({
        url: '/api/games/check-score',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
          game_id: 'block-matcher',
          score: score
        }),
        success: function(response) {
          callback(response.qualifies);
        },
        error: function() {
          callback(false);
        }
      });
    },

    showHighScoreModal: function(score, level, time) {
      var self = this;
      var minutes = Math.floor(time / 60);
      var seconds = time % 60;
      
      $('#hs-score').text(score);
      $('#hs-level').text(level);
      $('#hs-time').text(minutes + ':' + (seconds < 10 ? '0' : '') + seconds);
      $('#player-name-input').val('');
      
      $('#high-score-modal').show();
      $('#player-name-input').focus();
      
      // Handle submit
      $('#submit-score-btn').off('click').on('click', function() {
        var playerName = $('#player-name-input').val().trim() || 'Anonymous';
        self.submitHighScore(score, level, time, playerName);
      });
      
      // Handle skip
      $('#skip-score-btn').off('click').on('click', function() {
        $('#high-score-modal').hide();
        $('#game-over-modal').show();
      });
      
      // Allow Enter key to submit
      $('#player-name-input').off('keypress').on('keypress', function(e) {
        if (e.which === 13) {
          $('#submit-score-btn').click();
        }
      });
    },

    submitHighScore: function(score, level, time, playerName) {
      var self = this;
      
      $.ajax({
        url: '/api/games/submit-score',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
          game_id: 'block-matcher',
          score: score,
          level: level,
          time: time,
          player_name: playerName
        }),
        success: function(response) {
          if (response.success) {
            $('#high-score-modal').hide();
            $('#game-over-modal').show();
            // Refresh high scores display
            self.loadHighScores();
          }
        },
        error: function() {
          alert('Failed to submit score. Please try again.');
        }
      });
    },

    loadHighScores: function() {
      $.ajax({
        url: '/api/games/high-scores/block-matcher',
        method: 'GET',
        success: function(response) {
          var html = '<ol class="high-scores">';
          response.scores.forEach(function(score, index) {
            var minutes = Math.floor(score.time / 60);
            var seconds = score.time % 60;
            var timeStr = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            
            html += '<li class="high-score-item">';
            html += '<span class="rank">' + (index + 1) + '.</span>';
            html += '<span class="player">' + score.player_name + '</span>';
            html += '<span class="score">' + score.score + '</span>';
            html += '<span class="details">L' + score.level + ' • ' + timeStr + '</span>';
            html += '</li>';
          });
          html += '</ol>';
          
          $('#high-scores-list').html(html);
        }
      });
    },

    startTimer: function() {
      this.startTime = Date.now();
      var self = this;
      this.timerInterval = setInterval(function() {
        var elapsed = Math.floor((Date.now() - self.startTime) / 1000);
        var minutes = Math.floor(elapsed / 60);
        var seconds = elapsed % 60;
        $('#timer').text(minutes + ':' + (seconds < 10 ? '0' : '') + seconds);
      }, 1000);
    },

    advanceLevel: function() {
      var self = this;
      
      if (this.level >= this.maxLevel) {
        // Beat final level!
        setTimeout(function() {
          self.gameOver(true, 'ULTIMATE VICTORY! You completed all ' + self.maxLevel + ' levels!');
        }, 2000);
        return;
      }
      
      // Advance to next level
      this.level++;
      this.updateLevel();
      
      // Brief pause then restart with new level
      setTimeout(function() {
        self.createGrid();
        self.render3D();
        self.moves = 0;
        $('#moves').text(self.moves);
      }, 2000);
    },

    newGame: function() {
      this.level = 1;
      this.updateLevel();
      this.score = 0;
      this.moves = 0;
      this.selectedBlock = null;
      $('#score').text(0);
      $('#moves').text(0);
      clearInterval(this.timerInterval);
      this.createGrid();
      this.render3D();
      this.startTimer();
    }
  };

})(jQuery, Drupal, once);

/**
 * @file
 * Block Matcher game logic.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.blockMatcher = {
    attach: function (context, settings) {
      once('block-matcher', '#game-board', context).forEach(function(element) {
        var $gameBoard = $(element);
        var game = new BlockMatcherGame($gameBoard);
        game.init();
      });
    }
  };

  /**
   * Block Matcher Game Class
   */
  function BlockMatcherGame($board) {
    this.$board = $board;
    this.gridSize = parseInt($board.data('grid-size')) || 8;
    this.blockTypes = parseInt($board.data('block-types')) || 5;
    this.minMatch = parseInt($board.data('min-match')) || 3;
    this.grid = [];
    this.selectedBlock = null;
    this.score = 0;
    this.moves = 0;
    this.startTime = null;
    this.timerInterval = null;
    this.rotation = 0; // 0, 90, 180, 270
    this.flipH = false;
    this.flipV = false;
  }

  BlockMatcherGame.prototype = {
    init: function() {
      this.createGrid();
      this.render();
      this.startTimer();
      this.bindEvents();
    },

    createGrid: function() {
      this.grid = [];
      for (var i = 0; i < this.gridSize; i++) {
        this.grid[i] = [];
        for (var j = 0; j < this.gridSize; j++) {
          this.grid[i][j] = this.randomBlockType();
        }
      }
      // Ensure no initial matches
      this.removeInitialMatches();
    },

    randomBlockType: function() {
      return Math.floor(Math.random() * this.blockTypes);
    },

    removeInitialMatches: function() {
      var hasMatches = true;
      while (hasMatches) {
        hasMatches = false;
        for (var i = 0; i < this.gridSize; i++) {
          for (var j = 0; j < this.gridSize; j++) {
            if (this.checkMatchAt(i, j).length >= this.minMatch) {
              this.grid[i][j] = this.randomBlockType();
              hasMatches = true;
            }
          }
        }
      }
    },

    render: function() {
      var self = this;
      this.$board.empty();
      
      for (var i = 0; i < this.gridSize; i++) {
        for (var j = 0; j < this.gridSize; j++) {
          var $block = $('<div>')
            .addClass('block')
            .addClass('color-' + this.grid[i][j])
            .attr('data-row', i)
            .attr('data-col', j);
          this.$board.append($block);
        }
      }
    },

    bindEvents: function() {
      var self = this;
      
      this.$board.on('click', '.block', function() {
        self.handleBlockClick($(this));
      });

      $('#new-game-btn').on('click', function() {
        self.newGame();
      });

      $('#hint-btn').on('click', function() {
        self.showHint();
      });

      $('#play-again-btn').on('click', function() {
        self.newGame();
        $('#game-over-modal').hide();
      });

      $('#rotate-left-btn').on('click', function() {
        self.rotateBoard(-90);
      });

      $('#rotate-right-btn').on('click', function() {
        self.rotateBoard(90);
      });

      $('#flip-horizontal-btn').on('click', function() {
        self.flipBoard('horizontal');
      });

      $('#flip-vertical-btn').on('click', function() {
        self.flipBoard('vertical');
      });

      $('#reset-orientation-btn').on('click', function() {
        self.resetOrientation();
      });
    },

    handleBlockClick: function($block) {
      var row = parseInt($block.attr('data-row'));
      var col = parseInt($block.attr('data-col'));

      if (!this.selectedBlock) {
        // First selection
        this.selectedBlock = {row: row, col: col};
        $block.addClass('selected');
      } else {
        // Second selection - attempt swap
        var prevRow = this.selectedBlock.row;
        var prevCol = this.selectedBlock.col;
        
        $('.block.selected').removeClass('selected');
        
        if (this.isAdjacent(prevRow, prevCol, row, col)) {
          this.swapBlocks(prevRow, prevCol, row, col);
          this.moves++;
          $('#moves').text(this.moves);
        }
        
        this.selectedBlock = null;
      }
    },

    isAdjacent: function(row1, col1, row2, col2) {
      return (Math.abs(row1 - row2) === 1 && col1 === col2) ||
             (Math.abs(col1 - col2) === 1 && row1 === row2);
    },

    swapBlocks: function(row1, col1, row2, col2) {
      var self = this;
      var $block1 = $('.block[data-row="' + row1 + '"][data-col="' + col1 + '"]');
      var $block2 = $('.block[data-row="' + row2 + '"][data-col="' + col2 + '"]');
      
      // Add swapping animation class
      $block1.addClass('swapping');
      $block2.addClass('swapping');
      
      setTimeout(function() {
        // Perform the swap
        var temp = self.grid[row1][col1];
        self.grid[row1][col1] = self.grid[row2][col2];
        self.grid[row2][col2] = temp;
        
        self.render();
        
        setTimeout(function() {
          var matches1 = self.checkMatchAt(row1, col1);
          var matches2 = self.checkMatchAt(row2, col2);
          
          if (matches1.length >= self.minMatch || matches2.length >= self.minMatch) {
            self.processMatches();
          } else {
            // Swap back if no matches
            setTimeout(function() {
              $block1 = $('.block[data-row="' + row1 + '"][data-col="' + col1 + '"]');
              $block2 = $('.block[data-row="' + row2 + '"][data-col="' + col2 + '"]');
              $block1.addClass('swapping');
              $block2.addClass('swapping');
              
              setTimeout(function() {
                self.swapBlocks(row1, col1, row2, col2);
              }, 300);
            }, 200);
          }
        }, 100);
      }, 300);
    },

    checkMatchAt: function(row, col) {
      var color = this.grid[row][col];
      var matches = [{row: row, col: col}];
      
      // Check horizontal
      var left = col - 1;
      while (left >= 0 && this.grid[row][left] === color) {
        matches.push({row: row, col: left});
        left--;
      }
      var right = col + 1;
      while (right < this.gridSize && this.grid[row][right] === color) {
        matches.push({row: row, col: right});
        right++;
      }
      
      // Check vertical
      var up = row - 1;
      while (up >= 0 && this.grid[up][col] === color) {
        matches.push({row: up, col: col});
        up--;
      }
      var down = row + 1;
      while (down < this.gridSize && this.grid[down][col] === color) {
        matches.push({row: down, col: col});
        down++;
      }
      
      return matches;
    },

    processMatches: function() {
      var allMatches = [];
      
      for (var i = 0; i < this.gridSize; i++) {
        for (var j = 0; j < this.gridSize; j++) {
          var matches = this.checkMatchAt(i, j);
          if (matches.length >= this.minMatch) {
            allMatches = allMatches.concat(matches);
          }
        }
      }
      
      if (allMatches.length > 0) {
        this.removeMatches(allMatches);
        this.score += allMatches.length * 10;
        $('#score').text(this.score);
      }
    },

    removeMatches: function(matches) {
      var self = this;
      
      // Mark matched blocks
      matches.forEach(function(match) {
        var $block = $('.block[data-row="' + match.row + '"][data-col="' + match.col + '"]');
        $block.addClass('matched');
      });
      
      setTimeout(function() {
        // Remove matched blocks
        matches.forEach(function(match) {
          self.grid[match.row][match.col] = -1;
        });
        
        // Drop blocks down
        self.dropBlocks();
        
        // Check for new matches after animation completes
        setTimeout(function() {
          self.processMatches();
        }, 600);
      }, 600);
    },

    dropBlocks: function() {
      var movements = []; // Track which blocks moved and how far
      var newBlocks = []; // Track newly generated blocks
      
      for (var col = 0; col < this.gridSize; col++) {
        var emptyRow = this.gridSize - 1;
        for (var row = this.gridSize - 1; row >= 0; row--) {
          if (this.grid[row][col] !== -1) {
            if (emptyRow !== row) {
              // Track the movement
              movements.push({
                color: this.grid[row][col],
                fromRow: row,
                toRow: emptyRow,
                col: col,
                distance: emptyRow - row
              });
            }
            this.grid[emptyRow][col] = this.grid[row][col];
            if (emptyRow !== row) {
              this.grid[row][col] = -1;
            }
            emptyRow--;
          }
        }
        // Fill empty spaces at top and track them
        var emptyCount = emptyRow + 1;
        while (emptyRow >= 0) {
          this.grid[emptyRow][col] = this.randomBlockType();
          newBlocks.push({
            row: emptyRow,
            col: col,
            distance: emptyCount
          });
          emptyRow--;
        }
      }
      
      // Re-render
      this.render();
      
      // Apply animations with appropriate delays
      setTimeout(function() {
        // Animate moved blocks
        movements.forEach(function(move) {
          var $block = $('.block[data-row="' + move.toRow + '"][data-col="' + move.col + '"]');
          $block.css('--drop-distance', move.distance);
          $block.attr('data-drop-distance', move.distance);
          $block.addClass('dropping');
        });
        
        // Animate new blocks from top
        newBlocks.forEach(function(newBlock) {
          var $block = $('.block[data-row="' + newBlock.row + '"][data-col="' + newBlock.col + '"]');
          $block.css('--drop-distance', newBlock.distance);
          $block.attr('data-drop-distance', newBlock.distance);
          $block.addClass('dropping');
        });
        
        // Clean up animation classes and attributes after longest animation
        var maxDistance = Math.max(
          movements.length > 0 ? Math.max.apply(null, movements.map(m => m.distance)) : 0,
          newBlocks.length > 0 ? Math.max.apply(null, newBlocks.map(n => n.distance)) : 0
        );
        var animationDuration = 300 + maxDistance * 80;
        
        setTimeout(function() {
          $('.block').removeClass('dropping');
          $('.block').removeAttr('data-drop-distance');
          $('.block').css('--drop-distance', '');
        }, animationDuration);
      }, 50);
    },

    showHint: function() {
      // Simple hint: find first possible move
      for (var i = 0; i < this.gridSize; i++) {
        for (var j = 0; j < this.gridSize - 1; j++) {
          // Try horizontal swap
          this.swapBlocks(i, j, i, j + 1);
          var matches = this.checkMatchAt(i, j).concat(this.checkMatchAt(i, j + 1));
          this.swapBlocks(i, j, i, j + 1); // Swap back
          
          if (matches.length >= this.minMatch) {
            var $hint = $('.block[data-row="' + i + '"][data-col="' + j + '"]');
            $hint.addClass('selected');
            setTimeout(function() { $hint.removeClass('selected'); }, 1000);
            return;
          }
        }
      }
      alert('No obvious moves available!');
    },

    startTimer: function() {
      this.startTime = Date.now();
      var self = this;
      this.timerInterval = setInterval(function() {
        self.updateTimer();
      }, 1000);
    },

    updateTimer: function() {
      var elapsed = Math.floor((Date.now() - this.startTime) / 1000);
      var minutes = Math.floor(elapsed / 60);
      var seconds = elapsed % 60;
      $('#timer').text(minutes + ':' + (seconds < 10 ? '0' : '') + seconds);
    },

    newGame: function() {
      this.score = 0;
      this.moves = 0;
      this.selectedBlock = null;
      $('#score').text(0);
      $('#moves').text(0);
      clearInterval(this.timerInterval);
      this.createGrid();
      this.render();
      this.startTimer();
    }
  };

  /**
   * Rotate the board view.
   */
  BlockMatcherGame.prototype.rotateBoard = function(degrees) {
    this.rotation = (this.rotation + degrees + 360) % 360;
    this.updateBoardTransform();
  };

  /**
   * Flip the board view.
   */
  BlockMatcherGame.prototype.flipBoard = function(direction) {
    if (direction === 'horizontal') {
      this.flipH = !this.flipH;
    } else if (direction === 'vertical') {
      this.flipV = !this.flipV;
    }
    this.updateBoardTransform();
  };

  /**
   * Reset board orientation.
   */
  BlockMatcherGame.prototype.resetOrientation = function() {
    this.rotation = 0;
    this.flipH = false;
    this.flipV = false;
    this.updateBoardTransform();
  };

  /**
   * Update board transform classes.
   */
  BlockMatcherGame.prototype.updateBoardTransform = function() {
    this.$board.removeClass('rotate-90 rotate-180 rotate-270 flip-h flip-v');
    
    if (this.rotation === 90) {
      this.$board.addClass('rotate-90');
    } else if (this.rotation === 180) {
      this.$board.addClass('rotate-180');
    } else if (this.rotation === 270) {
      this.$board.addClass('rotate-270');
    }
    
    if (this.flipH) {
      this.$board.addClass('flip-h');
    }
    if (this.flipV) {
      this.$board.addClass('flip-v');
    }
  };

})(jQuery, Drupal, once);

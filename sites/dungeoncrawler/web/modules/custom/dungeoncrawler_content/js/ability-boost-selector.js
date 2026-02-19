/**
 * @file
 * Pathbuilder-inspired Interactive Ability Score Boost Selector
 *
 * Handles interactive ability boost selection with real-time API-driven
 * calculation and validation, following Pathbuilder 2e's excellent UX patterns.
 *
 * Features:
 * - Click to select/deselect ability boosts
 * - Real-time score preview as user selects
 * - Validation preventing invalid selections
 * - Visual feedback (animations, color changes)
 * - Debounced API calls for performance
 * - Keyboard accessibility
 *
 * Usage:
 * - Attach to character creation steps 3, 4, 5 (background, class, free boosts)
 * - Requires ability-widget.html.twig with mode="interactive"
 *
 * @see AbilityScoreTracker Service (backend calculations)
 * @see AbilityScoreApiController (API endpoints)
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Configuration constants
   */
  const CONFIG = {
    API_ENDPOINTS: {
      CALCULATE: '/api/characters/ability-scores/calculate',
      VALIDATE_BOOST: '/api/characters/ability-scores/validate-boost',
      AVAILABLE_BOOSTS: '/api/characters/ability-scores/available-boosts',
    },
    DEBOUNCE_DELAY: 300, // milliseconds
    ANIMATION_DURATION: 300,
  };

  /**
   * Ability Score Boost Selector Behavior
   */
  Drupal.behaviors.abilityScoreBoostSelector = {
    /**
     * State management
     */
    state: {
      selectedBoosts: [],
      maxBoosts: 4,
      characterData: {},
      currentStep: 'free', // 'background', 'class', or 'free'
      isCalculating: false,
    },

    /**
     * Attach behavior to ability score widgets
     */
    attach: function (context, settings) {
      const self = this;

      // Initialize interactive ability widgets
      $(once('ability-boost-selector', '.abilities-interactive', context)).each(function () {
        const $widget = $(this);
        self.initializeWidget($widget);
      });

      // Initialize ability card click handlers
      $(once('ability-card-click', '.ability-card--selectable', context)).each(function () {
        const $card = $(this);
        self.initializeCard($card);
      });

      // Load character data from form or data attributes
      self.loadCharacterData(context);
    },

    /**
     * Initialize the interactive widget
     */
    initializeWidget: function ($widget) {
      const self = this;

      // Get configuration from data attributes
      const maxBoosts = parseInt($widget.closest('.ability-score-widget').data('max-boosts') || 4);
      const step = $widget.closest('.ability-score-widget').data('step') || 'free';

      self.state.maxBoosts = maxBoosts;
      self.state.currentStep = step;

      // Load existing selections from hidden field
      const fieldId = step === 'background' ? '#background-boosts-field' : '#free-boosts-field';
      const $hiddenField = $(fieldId);
      if ($hiddenField.length && $hiddenField.val()) {
        try {
          self.state.selectedBoosts = JSON.parse($hiddenField.val());
          console.log('Loaded existing selections:', self.state.selectedBoosts);
          
          // Mark cards as selected
          self.state.selectedBoosts.forEach(function(ability) {
            const $card = $('.ability-card[data-ability="' + ability + '"]');
            if ($card.length) {
              $card.addClass('ability-card--selected');
              $card.find('.ability-checkbox').prop('checked', true);
            }
          });
        } catch (e) {
          console.warn('Failed to parse existing boosts:', e);
          self.state.selectedBoosts = [];
        }
      }

      // Initialize boost counter display
      self.updateBoostCounter();

      console.log('Ability boost selector initialized', {
        step: self.state.currentStep,
        maxBoosts: self.state.maxBoosts,
      });
    },

    /**
     * Initialize individual ability card
     */
    initializeCard: function ($card) {
      const self = this;
      const ability = $card.data('ability');

      // Click handler
      $card.on('click', function (e) {
        e.preventDefault();
        if (!$card.hasClass('ability-card--disabled')) {
          self.toggleAbilitySelection($card, ability);
        }
      });

      // Keyboard handler (Enter/Space)
      $card.on('keypress', function (e) {
        if (e.which === 13 || e.which === 32) { // Enter or Space
          e.preventDefault();
          if (!$card.hasClass('ability-card--disabled')) {
            self.toggleAbilitySelection($card, ability);
          }
        }
      });

      // Hover effect shows preview
      $card.on('mouseenter', function () {
        if (!$card.hasClass('ability-card--selected') && !$card.hasClass('ability-card--disabled')) {
          self.showBoostPreview($card, ability);
        }
      });

      $card.on('mouseleave', function () {
        if (!$card.hasClass('ability-card--selected')) {
          self.hideBoostPreview($card);
        }
      });
    },

    /**
     * Toggle ability boost selection
     */
    toggleAbilitySelection: function ($card, ability) {
      const self = this;
      const isSelected = $card.hasClass('ability-card--selected');
      const $checkbox = $card.find('.ability-checkbox');

      if (isSelected) {
        // Deselect
        self.deselectAbility($card, ability);
      } else {
        // Select (if under max limit)
        if (self.state.selectedBoosts.length < self.state.maxBoosts) {
          self.selectAbility($card, ability);
        } else {
          self.showMaxBoostsWarning();
        }
      }

      // Update boost counter
      self.updateBoostCounter();

      // Recalculate scores with debounce
      self.debouncedRecalculate();
    },

    /**
     * Select an ability for boost
     */
    selectAbility: function ($card, ability) {
      const self = this;

      // Add to selected list
      self.state.selectedBoosts.push(ability);

      // Update UI
      $card.addClass('ability-card--selected');
      $card.find('.ability-checkbox').prop('checked', true);

      // Update hidden form field
      self.updateHiddenField();

      // Animate selection
      $card.css('transform', 'scale(1.05)');
      setTimeout(() => {
        $card.css('transform', '');
      }, CONFIG.ANIMATION_DURATION);

      console.log('Selected boost:', ability, 'Total:', self.state.selectedBoosts.length);
    },

    /**
     * Deselect an ability
     */
    deselectAbility: function ($card, ability) {
      const self = this;

      // Remove from selected list
      const index = self.state.selectedBoosts.indexOf(ability);
      if (index > -1) {
        self.state.selectedBoosts.splice(index, 1);
      }

      // Update UI
      $card.removeClass('ability-card--selected');
      $card.find('.ability-checkbox').prop('checked', false);

      // Update hidden form field
      self.updateHiddenField();

      console.log('Deselected boost:', ability, 'Total:', self.state.selectedBoosts.length);
    },

    /**
     * Update hidden form field with selected boosts
     */
    updateHiddenField: function () {
      const self = this;
      const fieldId = self.state.currentStep === 'background' 
        ? '#background-boosts-field' 
        : '#free-boosts-field';
      
      const $hiddenField = $(fieldId);
      if ($hiddenField.length) {
        $hiddenField.val(JSON.stringify(self.state.selectedBoosts));
      }
    },

    /**
     * Show preview of boost effect
     */
    showBoostPreview: function ($card, ability) {
      const currentScore = parseInt($card.data('score'));
      const previewScore = currentScore < 18 ? currentScore + 2 : currentScore + 1;

      // Calculate new modifier
      const previewModifier = Math.floor((previewScore - 10) / 2);

      // Show preview in card
      $card.find('.preview-score').text(previewScore).show();
      $card.find('.arrow-icon').show();

      // Add preview class for styling
      $card.addClass('ability-card--preview');
    },

    /**
     * Hide boost preview
     */
    hideBoostPreview: function ($card) {
      $card.find('.preview-score').hide();
      $card.find('.arrow-icon').hide();
      $card.removeClass('ability-card--preview');
    },

    /**
     * Update boost counter display
     */
    updateBoostCounter: function () {
      const self = this;
      const remaining = self.state.maxBoosts - self.state.selectedBoosts.length;

      $('.boosts-remaining').text(remaining).attr('data-remaining', remaining);

      // Visual feedback when at max
      if (remaining === 0) {
        $('.boosts-remaining').addClass('at-max');
      } else {
        $('.boosts-remaining').removeClass('at-max');
      }
    },

    /**
     * Show warning when max boosts reached
     */
    showMaxBoostsWarning: function () {
      const self = this;

      const $warning = $('<div class="boost-warning">')
        .text(`Maximum ${self.state.maxBoosts} boosts selected. Deselect one to choose a different ability.`)
        .css({
          position: 'fixed',
          top: '20px',
          left: '50%',
          transform: 'translateX(-50%)',
          background: '#ffc107',
          color: '#000',
          padding: '1rem 2rem',
          borderRadius: '8px',
          boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
          zIndex: 9999,
          fontWeight: 'bold',
        });

      $('body').append($warning);

      setTimeout(() => {
        $warning.fadeOut(CONFIG.ANIMATION_DURATION, function () {
          $(this).remove();
        });
      }, 2000);
    },

    /**
     * Load character data from form context
     */
    loadCharacterData: function (context) {
      const self = this;

      // Try to get character data from form fields or data attributes
      const $characterDataInput = $('input[name="character_data"]', context);
      if ($characterDataInput.length) {
        try {
          self.state.characterData = JSON.parse($characterDataInput.val() || '{}');
        } catch (e) {
          console.warn('Failed to parse character data:', e);
        }
      }

      // Also check for data attribute on widget container
      const $widget = $('.ability-score-widget', context);
      if ($widget.length && $widget.data('character-data')) {
        self.state.characterData = $widget.data('character-data');
      }

      console.log('Loaded character data:', self.state.characterData);
    },

    /**
     * Recalculate ability scores via API
     */
    recalculateScores: function () {
      const self = this;

      if (self.state.isCalculating) {
        console.log('Calculation already in progress, skipping...');
        return;
      }

      self.state.isCalculating = true;

      // Build character data with current selections
      const characterData = Object.assign({}, self.state.characterData);

      // Add current boost selections based on step
      if (self.state.currentStep === 'background') {
        characterData.background_boosts = self.state.selectedBoosts;
      } else if (self.state.currentStep === 'class') {
        characterData.class_key_ability = self.state.selectedBoosts[0] || null;
      } else if (self.state.currentStep === 'free') {
        characterData.free_boosts = self.state.selectedBoosts;
      }

      // Show loading indicator
      self.showLoadingIndicator();

      // Make API call
      $.ajax({
        url: CONFIG.API_ENDPOINTS.CALCULATE,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({character_data: characterData}),
        dataType: 'json',
      })
        .done(function (response) {
          console.log('Calculation response:', response);

          if (response.success) {
            self.updateScoreDisplay(response);
          } else {
            self.showValidationErrors(response.validation || []);
          }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          console.error('Calculation failed:', textStatus, errorThrown);
          self.showError('Failed to calculate ability scores. Please try again.');
        })
        .always(function () {
          self.state.isCalculating = false;
          self.hideLoadingIndicator();
        });
    },

    /**
     * Debounced recalculation (prevents API spam)
     */
    debouncedRecalculate: (function () {
      let timer;
      return function () {
        const self = Drupal.behaviors.abilityScoreBoostSelector;
        clearTimeout(timer);
        timer = setTimeout(() => {
          self.recalculateScores();
        }, CONFIG.DEBOUNCE_DELAY);
      };
    })(),

    /**
     * Update score display with API response
     */
    updateScoreDisplay: function (response) {
      const scores = response.scores || {};
      const modifiers = response.modifiers || {};

      // Update each ability card
      $('.ability-card').each(function () {
        const $card = $(this);
        const ability = $card.data('ability');

        if (scores[ability] !== undefined) {
          const newScore = scores[ability];
          const newModifier = modifiers[ability];
          const modifierText = newModifier >= 0 ? '+' + newModifier : newModifier;

          // Update score value with animation
          const $scoreValue = $card.find('.score-value, .current-score');
          const oldScore = parseInt($scoreValue.text());

          if (oldScore !== newScore) {
            $scoreValue.addClass('score-display--changed');
            setTimeout(() => {
              $scoreValue.text(newScore);
              $card.data('score', newScore);
            }, 100);
            setTimeout(() => {
              $scoreValue.removeClass('score-display--changed');
            }, 600);
          }

          // Update modifier
          $card.find('.modifier-value, .current-modifier').text(modifierText)
            .removeClass('modifier-positive modifier-negative positive negative')
            .addClass(newModifier >= 0 ? 'modifier-positive positive' : 'modifier-negative negative');
        }
      });
    },

    /**
     * Show validation errors to user
     */
    showValidationErrors: function (errors) {
      if (errors.length === 0) return;

      const errorHtml = '<div class="validation-errors">' +
        '<strong>Validation Errors:</strong>' +
        '<ul>' + errors.map(e => '<li>' + e + '</li>').join('') + '</ul>' +
        '</div>';

      const $errors = $(errorHtml).css({
        background: '#f8d7da',
        border: '1px solid #f5c2c7',
        borderRadius: '8px',
        padding: '1rem',
        margin: '1rem 0',
        color: '#842029',
      });

      $('.ability-score-widget').prepend($errors);

      setTimeout(() => {
        $errors.fadeOut(CONFIG.ANIMATION_DURATION, function () {
          $(this).remove();
        });
      }, 5000);
    },

    /**
     * Show error message
     */
    showError: function (message) {
      const $error = $('<div class="calculation-error">')
        .text(message)
        .css({
          position: 'fixed',
          top: '20px',
          left: '50%',
          transform: 'translateX(-50%)',
          background: '#dc3545',
          color: 'white',
          padding: '1rem 2rem',
          borderRadius: '8px',
          boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
          zIndex: 9999,
        });

      $('body').append($error);

      setTimeout(() => {
        $error.fadeOut(CONFIG.ANIMATION_DURATION, function () {
          $(this).remove();
        });
      }, 3000);
    },

    /**
     * Show loading indicator
     */
    showLoadingIndicator: function () {
      const $loading = $('<div class="calculation-loading">')
        .html('<div class="spinner"></div> Calculating...')
        .css({
          position: 'fixed',
          top: '50%',
          left: '50%',
          transform: 'translate(-50%, -50%)',
          background: 'rgba(0, 0, 0, 0.8)',
          color: 'white',
          padding: '2rem 3rem',
          borderRadius: '12px',
          zIndex: 9999,
          fontSize: '1.25rem',
        });

      $('body').append($loading);
    },

    /**
     * Hide loading indicator
     */
    hideLoadingIndicator: function () {
      $('.calculation-loading').remove();
    },

  };

  /**
   * Form validation before submission
   */
  Drupal.behaviors.abilityBoostFormValidation = {
    attach: function (context, settings) {
      $(once('ability-boost-form-validation', 'form.character-creation-form', context)).each(function () {
        const $form = $(this);

        $form.on('submit', function (e) {
          const selector = Drupal.behaviors.abilityScoreBoostSelector;
          const selectedCount = selector.state.selectedBoosts.length;
          const requiredCount = selector.state.maxBoosts;

          if (selectedCount < requiredCount) {
            e.preventDefault();
            alert(`Please select ${requiredCount} ability boosts before continuing. You have selected ${selectedCount}.`);
            return false;
          }

          // Add selected boosts as hidden inputs
          selector.state.selectedBoosts.forEach(function (ability) {
            $('<input>').attr({
              type: 'hidden',
              name: 'ability_boosts[]',
              value: ability,
            }).appendTo($form);
          });
        });
      });
    },
  };

})(jQuery, Drupal, once);

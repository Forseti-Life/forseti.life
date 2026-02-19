/**
 * @file
 * Character Creation Step 3 - Background & Ability Boosts
 */

(function ($, Drupal, once) {
  'use strict';

  // Configuration constants
  const CONFIG = {
    maxBoosts: 2,
    selectors: {
      form: '#step-3-form',
      nextButton: '#next-button',
      errorMessage: '#error-message',
      boostCount: '#boost-count',
      backgroundCard: '.background-card',
      abilityCard: '.ability-card',
      abilityBoostSection: '#ability-boost-section',
      selectedBackground: '#selected-background',
      abilityBoost1: '#ability-boost-1',
      abilityBoost2: '#ability-boost-2',
    },
    cssClasses: {
      selected: 'selected',
      hidden: 'hidden',
    },
    messages: {
      saveFailed: 'Failed to save. Please try again.',
      genericError: 'An error occurred.',
    },
    buttonText: {
      default: 'Next Step →',
      saving: 'Saving...',
    },
  };

  /**
   * Select a background and update UI state.
   * 
   * @param {string} backgroundId - The ID of the selected background.
   * @param {Object} state - Current form state object.
   * @param {jQuery} $boostCount - The boost counter element.
   */
  function selectBackground(backgroundId, state, $boostCount) {
    state.selectedBackground = backgroundId;

    // Update UI
    $(CONFIG.selectors.backgroundCard).removeClass(CONFIG.cssClasses.selected);
    $(`.background-card[data-background="${backgroundId}"]`).addClass(CONFIG.cssClasses.selected);

    // Update hidden field
    $(CONFIG.selectors.selectedBackground).val(backgroundId);

    // Show ability boost selection
    $(CONFIG.selectors.abilityBoostSection).slideDown();

    // Reset ability boost selection
    state.selectedBoosts = [];
    $(CONFIG.selectors.abilityCard).removeClass(CONFIG.cssClasses.selected);
    updateBoostCounter($boostCount, state);

    // Check if we can enable next button
    checkFormComplete(state);
  }

  /**
   * Toggle ability boost selection.
   * 
   * @param {string} ability - The ability score to toggle.
   * @param {Object} state - Current form state object.
   * @param {jQuery} $counter - The boost counter element.
   */
  function toggleAbilityBoost(ability, state, $counter) {
    const index = state.selectedBoosts.indexOf(ability);
    
    if (index > -1) {
      // Deselect
      state.selectedBoosts.splice(index, 1);
      $(`.ability-card[data-ability="${ability}"]`).removeClass(CONFIG.cssClasses.selected);
    } else {
      // Select (if not at max)
      if (state.selectedBoosts.length < CONFIG.maxBoosts) {
        state.selectedBoosts.push(ability);
        $(`.ability-card[data-ability="${ability}"]`).addClass(CONFIG.cssClasses.selected);
      }
    }

    updateBoostCounter($counter, state);
    updateBoostHiddenFields(state);
    checkFormComplete(state);
  }

  /**
   * Update boost counter display.
   * 
   * @param {jQuery} $counter - The boost counter element.
   * @param {Object} state - Current form state object.
   */
  function updateBoostCounter($counter, state) {
    if ($counter && $counter.length) {
      $counter.text(state.selectedBoosts.length);
    }
  }

  /**
   * Update hidden fields for ability boosts.
   * 
   * @param {Object} state - Current form state object.
   */
  function updateBoostHiddenFields(state) {
    $(CONFIG.selectors.abilityBoost1).val(state.selectedBoosts[0] || '');
    $(CONFIG.selectors.abilityBoost2).val(state.selectedBoosts[1] || '');
  }

  /**
   * Check if form is complete and enable/disable next button.
   * 
   * @param {Object} state - Current form state object.
   */
  function checkFormComplete(state) {
    $(CONFIG.selectors.nextButton).prop('disabled', false);
  }

  /**
   * Update button state and text.
   * 
   * @param {jQuery} $button - The button element to update.
   * @param {boolean} disabled - Whether the button should be disabled.
   * @param {string} text - The button text to display.
   */
  function updateButtonState($button, disabled, text) {
    if ($button && $button.length) {
      $button.prop('disabled', disabled).text(text);
    }
  }

  /**
   * Show error message to user.
   * 
   * @param {jQuery} $errorElement - The error message element.
   * @param {string} message - The error message to display.
   */
  function showError($errorElement, message) {
    if ($errorElement && $errorElement.length) {
      $errorElement.text(message).removeClass(CONFIG.cssClasses.hidden).show();
    }
  }

  /**
   * Hide error message from user.
   * 
   * @param {jQuery} $errorElement - The error message element.
   */
  function hideError($errorElement) {
    if ($errorElement && $errorElement.length) {
      $errorElement.addClass(CONFIG.cssClasses.hidden).hide();
    }
  }

  /**
   * Handle AJAX error response.
   * 
   * @param {jQuery} $nextButton - The submit button element.
   * @param {jQuery} $errorMessage - The error message element.
   * @param {Object} xhr - XMLHttpRequest object.
   */
  function handleAjaxError($nextButton, $errorMessage, xhr) {
    let message = CONFIG.messages.saveFailed;
    if (xhr.responseJSON && xhr.responseJSON.message) {
      message = xhr.responseJSON.message;
    }
    showError($errorMessage, message);
    updateButtonState($nextButton, false, CONFIG.buttonText.default);
  }

  Drupal.behaviors.characterStep3 = {
    attach: function (context, settings) {
      once('step3-init', CONFIG.selectors.form, context).forEach(function(element) {
        const $form = $(element);
        const $nextButton = $(CONFIG.selectors.nextButton, context);
        const $errorMessage = $(CONFIG.selectors.errorMessage, context);
        const $boostCount = $(CONFIG.selectors.boostCount, context);

        // Guard clause: ensure required elements exist
        if (!$form.length || !$nextButton.length) {
          console.warn('Character step 3: Required form elements not found');
          return;
        }

        // Initialize state object (replaces global variables)
        const state = {
          selectedBackground: null,
          selectedBoosts: [],
        };

        // Background card click - use event delegation
        once('background-click', CONFIG.selectors.backgroundCard, context).forEach((card) => {
          $(card).on('click', function() {
            const backgroundId = $(this).data('background');
            selectBackground(backgroundId, state, $boostCount);
          });
        });

        // Ability boost card click - use event delegation
        once('ability-click', CONFIG.selectors.abilityCard, context).forEach((card) => {
          $(card).on('click', function() {
            const ability = $(this).data('ability');
            toggleAbilityBoost(ability, state, $boostCount);
          });
        });

        // Pre-select if already chosen
        const currentBackground = $(CONFIG.selectors.selectedBackground).val();
        if (currentBackground) {
          selectBackground(currentBackground, state, $boostCount);
          
          // Pre-select ability boosts if they exist
          const existingBoosts = [];
          const boost1 = $(CONFIG.selectors.abilityBoost1).val();
          const boost2 = $(CONFIG.selectors.abilityBoost2).val();
          if (boost1) existingBoosts.push(boost1);
          if (boost2) existingBoosts.push(boost2);
          
          existingBoosts.forEach(ability => {
            state.selectedBoosts.push(ability);
            $(`.ability-card[data-ability="${ability}"]`).addClass(CONFIG.cssClasses.selected);
          });
          
          updateBoostCounter($boostCount, state);
          checkFormComplete(state);
        }

        // Form submission
        $form.on('submit', function(e) {
          e.preventDefault();

          const formData = $(this).serialize();
          const actionUrl = $(this).attr('action');

          updateButtonState($nextButton, true, CONFIG.buttonText.saving);
          hideError($errorMessage);

          $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
              if (response && response.success) {
                window.location.href = response.redirect;
              } else {
                const message = (response && response.message) || CONFIG.messages.genericError;
                showError($errorMessage, message);
                updateButtonState($nextButton, false, CONFIG.buttonText.default);
              }
            },
            error: function(xhr) {
              handleAjaxError($nextButton, $errorMessage, xhr);
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

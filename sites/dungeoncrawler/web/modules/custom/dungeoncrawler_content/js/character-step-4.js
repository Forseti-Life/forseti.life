/**
 * @file
 * Character Creation Step 4 - Class Selection
 *
 * Handles class selection in character creation flow. Updates the 'class'
 * field in dc_campaign_characters table (varchar 64) and character_data JSON.
 * Schema: sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/config/schemas/character_options_step4.json
 */

(function ($, Drupal, once) {
  'use strict';

  // Constants for DOM selectors
  const SELECTORS = {
    FORM: '#step-4-form',
    CLASS_CARD: '.class-card',
    NEXT_BUTTON: '#next-button',
    SELECTED_CLASS: '#selected-class',
    ERROR_MESSAGE: '#error-message',
  };

  // CSS class constants
  const CSS_CLASSES = {
    SELECTED: 'selected',
    HIDDEN: 'hidden',
  };

  // Button text constants
  const BUTTON_TEXT = {
    SAVING: 'Saving...',
    DEFAULT: 'Next Step →',
  };

  // Error message constants
  const MESSAGES = {
    SAVE_ERROR: 'Failed to save. Please try again.',
  };

  // Module-level state
  let selectedClass = null;

  /**
   * Select a class and update UI state.
   *
   * @param {string} classId - The class identifier (e.g., 'fighter', 'wizard')
   * @param {jQuery} $classCards - jQuery collection of class cards
   * @param {jQuery} $selectedClassInput - Hidden input field for selected class
   * @param {jQuery} $nextButton - Submit button element
   */
  function selectClass(classId, $classCards, $selectedClassInput, $nextButton) {
    if (!classId) {
      return;
    }

    selectedClass = classId;

    // Update UI - remove all selected states, add to chosen card
    $classCards.removeClass(CSS_CLASSES.SELECTED);
    $(`.class-card[data-class="${classId}"]`).addClass(CSS_CLASSES.SELECTED);

    // Update hidden field for form submission
    $selectedClassInput.val(classId);

    // Enable next button
    $nextButton.prop('disabled', false);
  }

  /**
   * Show error message to user.
   *
   * @param {jQuery} $errorElement - Error message container element
   * @param {string} message - Error message to display
   */
  function showError($errorElement, message) {
    if (!$errorElement || !$errorElement.length) {
      return;
    }
    $errorElement.text(message).removeClass(CSS_CLASSES.HIDDEN).show();
  }

  /**
   * Hide error message from user.
   *
   * @param {jQuery} $errorElement - Error message container element
   */
  function hideError($errorElement) {
    if (!$errorElement || !$errorElement.length) {
      return;
    }
    $errorElement.addClass(CSS_CLASSES.HIDDEN).hide();
  }

  /**
   * Reset button to default state.
   *
   * @param {jQuery} $button - Button element to reset
   */
  function resetButtonState($button) {
    if (!$button || !$button.length) {
      return;
    }
    $button.prop('disabled', false).text(BUTTON_TEXT.DEFAULT);
  }

  Drupal.behaviors.characterStep4 = {
    attach: function (context, settings) {
      // Cache DOM elements with context for proper Drupal integration
      const $errorMessage = $(SELECTORS.ERROR_MESSAGE, context);
      const $nextButton = $(SELECTORS.NEXT_BUTTON, context);
      const $selectedClass = $(SELECTORS.SELECTED_CLASS, context);
      const $classCards = $(SELECTORS.CLASS_CARD, context);

      // Defensive check - ensure required elements exist
      if (!$selectedClass.length || !$nextButton.length) {
        console.warn('[Character Step 4] Required DOM elements not found');
        return;
      }

      // Class card click using event delegation
      once('class-select', SELECTORS.CLASS_CARD, context).forEach((element) => {
        $(element).on('click', function() {
          const classId = $(this).data('class');
          selectClass(classId, $classCards, $selectedClass, $nextButton);
        });
      });

      // Pre-select if already chosen (for editing/back navigation)
      const currentClass = $selectedClass.val();
      if (currentClass) {
        selectClass(currentClass, $classCards, $selectedClass, $nextButton);
      }

      resetButtonState($nextButton);

      // Form submission with validation and AJAX
      once('step4-submit', SELECTORS.FORM, context).forEach((element) => {
        $(element).on('submit', function(e) {
          e.preventDefault();

          const formData = $(this).serialize();
          const actionUrl = $(this).attr('action');

          // Show loading state
          $nextButton.prop('disabled', true).text(BUTTON_TEXT.SAVING);
          hideError($errorMessage);

          $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
              if (response && response.success) {
                // Successful save - redirect to next step
                window.location.href = response.redirect;
              } else {
                // Server returned error message
                const message = (response && response.message) || MESSAGES.SAVE_ERROR;
                showError($errorMessage, message);
                resetButtonState($nextButton);
              }
            },
            error: function(xhr) {
              // AJAX request failed or server error
              let errorMsg = MESSAGES.SAVE_ERROR;
              if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
              }
              showError($errorMessage, errorMsg);
              resetButtonState($nextButton);
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

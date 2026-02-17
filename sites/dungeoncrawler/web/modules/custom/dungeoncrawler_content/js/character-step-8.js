/**
 * @file
 * Character Creation Step 8: Final Details & Review
 */

(function ($, Drupal, once) {
  'use strict';

  // Configuration constants
  const CONFIG = {
    redirectDelay: 500,
    errorClass: 'hidden',
    messages: {
      createFailed: 'Failed to create character. Please try again.',
      createError: 'Error creating character.',
      genericError: 'An error occurred.'
    },
    buttonText: {
      submit: '🎉 Create Character',
      creating: 'Creating Character...',
      success: '✓ Character Created!'
    }
  };

  /**
   * Updates submit button state and text.
   * 
   * @param {jQuery} $button - The submit button element.
   * @param {boolean} disabled - Whether button should be disabled.
   * @param {string} text - Button text to display.
   */
  function updateSubmitButton($button, disabled, text) {
    $button.prop('disabled', disabled).text(text);
  }

  /**
   * Shows error message in the error display element.
   * 
   * @param {string} message - The error message to display.
   */
  function showErrorMessage(message) {
    $('#error-message').text(message).removeClass(CONFIG.errorClass);
  }

  /**
   * Hides the error message display element.
   */
  function hideErrorMessage() {
    $('#error-message').addClass(CONFIG.errorClass);
  }

  /**
   * Handles AJAX error response.
   * 
   * @param {jQuery} $submitButton - The submit button element.
   * @param {Object} xhr - XMLHttpRequest object.
   */
  function handleAjaxError($submitButton, xhr) {
    let message = CONFIG.messages.createFailed;
    if (xhr.responseJSON && xhr.responseJSON.message) {
      message = xhr.responseJSON.message;
    }
    showErrorMessage(message);
    updateSubmitButton($submitButton, false, CONFIG.buttonText.submit);
  }

  Drupal.behaviors.characterStep8 = {
    attach: function (context, settings) {
      once('step8-submit', '#step-8-form', context).forEach((element) => {
        const $form = $(element);
        const $submitButton = $('#next-button', context);

        // Guard clause: ensure required elements exist
        if (!$submitButton.length) {
          console.warn('Character step 8: Submit button not found');
          return;
        }

        // Handle form submission with AJAX
        $form.on('submit', function(e) {
          e.preventDefault();

          // Validation (all fields optional for final details)
          // Character can be completed without these details
          
          hideErrorMessage();
          updateSubmitButton($submitButton, true, CONFIG.buttonText.creating);

          // Prepare form data
          const formData = $form.serialize();
          const actionUrl = $form.attr('action');

          // Submit via AJAX
          $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
              if (response && response.success) {
                updateSubmitButton($submitButton, true, CONFIG.buttonText.success);
                
                // Redirect to character sheet or character list
                setTimeout(function() {
                  window.location.href = response.redirect;
                }, CONFIG.redirectDelay);
              } else {
                const message = (response && response.message) || CONFIG.messages.createError;
                showErrorMessage(message);
                updateSubmitButton($submitButton, false, CONFIG.buttonText.submit);
              }
            },
            error: function(xhr, status, error) {
              handleAjaxError($submitButton, xhr);
              console.error('Save error:', error);
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

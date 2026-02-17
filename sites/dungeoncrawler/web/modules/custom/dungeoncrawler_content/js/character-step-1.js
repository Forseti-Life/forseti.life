/**
 * @file
 * Character Creation Step 1 - Name & Concept
 */

(function ($, Drupal, once) {
  'use strict';

  // Configuration constants
  const CONFIG = {
    minNameLength: 2,
    errorClass: 'error',
    messages: {
      nameRequired: 'Please enter a character name (at least 2 characters).',
      saveFailed: 'Failed to save. Please try again.',
      genericError: 'An error occurred.'
    },
    buttonText: {
      submit: 'Next Step →',
      saving: 'Saving...'
    }
  };

  /**
   * Validates character name length.
   * 
   * @param {string} name - The character name to validate.
   * @return {boolean} True if name meets minimum length requirement.
   */
  function isValidName(name) {
    return name && name.trim().length >= CONFIG.minNameLength;
  }

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
   * Handles AJAX error response.
   * 
   * @param {jQuery} $submitButton - The submit button element.
   * @param {Object} xhr - XMLHttpRequest object.
   */
  function handleAjaxError($submitButton, xhr) {
    let message = CONFIG.messages.saveFailed;
    if (xhr.responseJSON && xhr.responseJSON.message) {
      message = xhr.responseJSON.message;
    }
    alert(message);
    updateSubmitButton($submitButton, false, CONFIG.buttonText.submit);
  }

  Drupal.behaviors.characterStep1 = {
    attach: function (context, settings) {
      once('step1-init', '#step1Form', context).forEach(function(element) {
        const $form = $(element);
        const $nameInput = $('#name', context);
        const $submitButton = $form.find('button[type="submit"]');
        
        // Guard clause: ensure required elements exist
        if (!$nameInput.length || !$submitButton.length) {
          console.warn('Character step 1: Required form elements not found');
          return;
        }
        
        // Real-time validation
        $nameInput.on('input', function() {
          const name = $(this).val();
          if (isValidName(name)) {
            $(this).removeClass(CONFIG.errorClass);
          } else {
            $(this).addClass(CONFIG.errorClass);
          }
        });

        // Form submission
        $form.on('submit', function(e) {
          e.preventDefault();
          
          const name = $nameInput.val().trim();
          
          // Validate name
          if (!isValidName(name)) {
            alert(CONFIG.messages.nameRequired);
            $nameInput.focus();
            return false;
          }
          
          const formData = $(this).serialize();
          const actionUrl = $(this).attr('action');
          
          // Show loading state
          updateSubmitButton($submitButton, true, CONFIG.buttonText.saving);
          
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
                alert(message);
                updateSubmitButton($submitButton, false, CONFIG.buttonText.submit);
              }
            },
            error: function(xhr) {
              handleAjaxError($submitButton, xhr);
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

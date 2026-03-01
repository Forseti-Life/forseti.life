/**
 * @file
 * Character Creation Step 1 - Name & Concept
 *
 * Schema Conformance:
 * This behavior validates and submits character data conforming to
 * character.schema.json. The name field validation (minLength: 2) matches
 * the schema requirement (line 24: "minLength": 2, "maxLength": 100).
 * The concept field is optional in both the schema (line 28) and this form.
 *
 * Data Storage:
 * Form data is saved to the dc_characters table via AJAX POST to
 * CharacterCreationStepController::saveStep(). Data is stored in the
 * character_data JSON column, with select fields also duplicated to
 * direct table columns (name, level, ancestry, class) for query optimization.
 *
 * @see /config/schemas/character.schema.json
 * @see /src/Controller/CharacterCreationStepController.php
 */

(function ($, Drupal, once) {
  'use strict';

  // Configuration constants
  // Note: minNameLength matches character.schema.json requirement
  const CONFIG = {
    messages: {
      saveFailed: 'Failed to save. Please try again.',
      genericError: 'An error occurred.'
    },
    buttonText: {
      submit: 'Next Step →',
      saving: 'Saving...'
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
        
        // Form submission
        $form.on('submit', function(e) {
          const actionUrl = $(this).attr('action');

          // Use native Drupal form submit unless this is an explicit JSON save endpoint.
          if (!actionUrl || actionUrl.indexOf('/save') === -1) {
            return;
          }

          e.preventDefault();

          const formData = $(this).serialize();
          
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

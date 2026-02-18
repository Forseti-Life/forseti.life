/**
 * @file
 * Character Creation Step 8: Finishing Touches
 * 
 * Schema-driven final step for character creation. All fields are optional.
 * Validates against character_options_step8.json schema and stores data in
 * character.schema.json format in the dc_characters.character_data JSON column.
 */

(function ($, Drupal, once) {
  'use strict';

  // Constants for selectors
  const SELECTORS = {
    FORM: '#step-8-form',
    SUBMIT_BUTTON: '#next-button',
    ERROR_MESSAGE: '#error-message',
    AGE_FIELD: '#age',
    GENDER_FIELD: '#gender',
    APPEARANCE_FIELD: '#appearance',
    PERSONALITY_FIELD: '#personality',
    BACKSTORY_FIELD: '#backstory'
  };

  // CSS classes
  const CSS_CLASSES = {
    HIDDEN: 'hidden',
    ERROR: 'error',
    INVALID: 'is-invalid'
  };

  // Configuration constants
  const CONFIG = {
    redirectDelay: 500,
    messages: {
      createFailed: 'Failed to create character. Please try again.',
      createError: 'Error creating character.',
      genericError: 'An error occurred.',
      validationError: 'Please correct the errors below.',
      ageTooLong: 'Age cannot exceed 50 characters.',
      ageInvalid: 'Age must contain only letters, numbers, spaces, commas, and hyphens.',
      genderTooLong: 'Gender / Pronouns cannot exceed 100 characters.',
      appearanceTooLong: 'Physical Appearance cannot exceed 1000 characters.',
      personalityTooLong: 'Personality & Mannerisms cannot exceed 1000 characters.',
      backstoryTooLong: 'Backstory cannot exceed 5000 characters.'
    },
    buttonText: {
      submit: '🎉 Create Character',
      creating: 'Creating Character...',
      success: '✓ Character Created!'
    },
    validation: {
      ageMaxLength: 50,
      agePattern: /^[0-9a-zA-Z\s,\-]+$/,
      genderMaxLength: 100,
      appearanceMaxLength: 1000,
      personalityMaxLength: 1000,
      backstoryMaxLength: 5000
    }
  };

  /**
   * Validates a field against its schema rules.
   * 
   * @param {jQuery} $field - The field element to validate.
   * @param {string} value - The field value.
   * @param {Object} rules - Validation rules (maxLength, pattern, etc.).
   * @return {Object} Validation result with isValid and message properties.
   */
  function validateField($field, value, rules) {
    // Empty values are always valid for optional step 8 fields
    if (!value || value.trim() === '') {
      return { isValid: true };
    }

    // Check max length
    if (rules.maxLength && value.length > rules.maxLength) {
      return { 
        isValid: false, 
        message: rules.errorMessage || `Field cannot exceed ${rules.maxLength} characters.`
      };
    }

    // Check pattern (if provided)
    if (rules.pattern && !rules.pattern.test(value)) {
      return { 
        isValid: false, 
        message: rules.patternError || 'Invalid format.'
      };
    }

    return { isValid: true };
  }

  /**
   * Marks a field as invalid and shows error message.
   * 
   * @param {jQuery} $field - The field element.
   * @param {string} message - Error message to display.
   */
  function markFieldInvalid($field, message) {
    $field.addClass(CSS_CLASSES.INVALID);
    const $feedback = $field.siblings('.invalid-feedback');
    if ($feedback.length) {
      $feedback.text(message);
    } else {
      $field.after(`<div class="invalid-feedback">${message}</div>`);
    }
  }

  /**
   * Clears validation state from a field.
   * 
   * @param {jQuery} $field - The field element.
   */
  function clearFieldValidation($field) {
    $field.removeClass(CSS_CLASSES.INVALID);
    $field.siblings('.invalid-feedback').remove();
  }

  /**
   * Validates all form fields before submission.
   * 
   * @param {jQuery} $form - The form element.
   * @return {Object} Validation result with isValid property and errors array.
   */
  function validateForm($form) {
    const errors = [];
    let isValid = true;

    // Clear previous validation states
    $form.find(`.${CSS_CLASSES.INVALID}`).each(function() {
      clearFieldValidation($(this));
    });

    // Validate age
    const $age = $(SELECTORS.AGE_FIELD);
    const ageValue = $age.val();
    const ageValidation = validateField($age, ageValue, {
      maxLength: CONFIG.validation.ageMaxLength,
      pattern: CONFIG.validation.agePattern,
      errorMessage: CONFIG.messages.ageTooLong,
      patternError: CONFIG.messages.ageInvalid
    });
    if (!ageValidation.isValid) {
      markFieldInvalid($age, ageValidation.message);
      errors.push(ageValidation.message);
      isValid = false;
    }

    // Validate gender
    const $gender = $(SELECTORS.GENDER_FIELD);
    const genderValue = $gender.val();
    const genderValidation = validateField($gender, genderValue, {
      maxLength: CONFIG.validation.genderMaxLength,
      errorMessage: CONFIG.messages.genderTooLong
    });
    if (!genderValidation.isValid) {
      markFieldInvalid($gender, genderValidation.message);
      errors.push(genderValidation.message);
      isValid = false;
    }

    // Validate appearance
    const $appearance = $(SELECTORS.APPEARANCE_FIELD);
    const appearanceValue = $appearance.val();
    const appearanceValidation = validateField($appearance, appearanceValue, {
      maxLength: CONFIG.validation.appearanceMaxLength,
      errorMessage: CONFIG.messages.appearanceTooLong
    });
    if (!appearanceValidation.isValid) {
      markFieldInvalid($appearance, appearanceValidation.message);
      errors.push(appearanceValidation.message);
      isValid = false;
    }

    // Validate personality
    const $personality = $(SELECTORS.PERSONALITY_FIELD);
    const personalityValue = $personality.val();
    const personalityValidation = validateField($personality, personalityValue, {
      maxLength: CONFIG.validation.personalityMaxLength,
      errorMessage: CONFIG.messages.personalityTooLong
    });
    if (!personalityValidation.isValid) {
      markFieldInvalid($personality, personalityValidation.message);
      errors.push(personalityValidation.message);
      isValid = false;
    }

    // Validate backstory
    const $backstory = $(SELECTORS.BACKSTORY_FIELD);
    const backstoryValue = $backstory.val();
    const backstoryValidation = validateField($backstory, backstoryValue, {
      maxLength: CONFIG.validation.backstoryMaxLength,
      errorMessage: CONFIG.messages.backstoryTooLong
    });
    if (!backstoryValidation.isValid) {
      markFieldInvalid($backstory, backstoryValidation.message);
      errors.push(backstoryValidation.message);
      isValid = false;
    }

    return { isValid, errors };
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
   * Shows error message in the error display element.
   * 
   * @param {string} message - The error message to display.
   */
  function showErrorMessage(message) {
    $(SELECTORS.ERROR_MESSAGE).text(message).removeClass(CSS_CLASSES.HIDDEN);
  }

  /**
   * Hides the error message display element.
   */
  function hideErrorMessage() {
    $(SELECTORS.ERROR_MESSAGE).addClass(CSS_CLASSES.HIDDEN);
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
      once('step8-submit', SELECTORS.FORM, context).forEach((element) => {
        const $form = $(element);
        const $submitButton = $(SELECTORS.SUBMIT_BUTTON, context);

        // Guard clause: ensure required elements exist
        if (!$submitButton.length) {
          console.warn('Character step 8: Submit button not found');
          return;
        }

        // Handle form submission with AJAX
        $form.on('submit', function(e) {
          e.preventDefault();

          // Validate form fields (all fields optional, but must meet constraints if filled)
          const validation = validateForm($form);
          if (!validation.isValid) {
            showErrorMessage(CONFIG.messages.validationError);
            return false;
          }
          
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

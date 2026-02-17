/**
 * @file
 * Character Creation Step 2 - Ancestry Selection
 */

(function ($, Drupal, once) {
  'use strict';

  // Constants
  const SELECTORS = {
    FORM: '#step2Form',
    ANCESTRY_CARD: '.ancestry-card',
    HERITAGE_SECTION: '#heritageSelection',
    HERITAGE_OPTIONS: '#heritageOptions',
    SUBMIT_BUTTON: '#step2Submit',
    SELECTED_ANCESTRY: '#selectedAncestry',
    SELECTED_HERITAGE: '#selectedHeritage',
  };

  const CSS_CLASSES = {
    SELECTED: 'selected',
    HIDDEN: 'hidden',
    HERITAGE_CARD: 'heritage-card',
  };

  const BUTTON_TEXT = {
    SAVING: 'Saving...',
    DEFAULT: 'Next: Choose Background →',
  };

  const MESSAGES = {
    SELECT_ANCESTRY: 'Please select an ancestry.',
    SELECT_HERITAGE: 'Please select a heritage.',
    SAVE_ERROR: 'Failed to save. Please try again.',
  };

  Drupal.behaviors.characterStep2 = {
    attach: function (context, settings) {
      once('step2-init', SELECTORS.FORM, context).forEach(function(element) {
        const $form = $(element);
        const $ancestryCards = $(SELECTORS.ANCESTRY_CARD, context);
        const $heritageSection = $(SELECTORS.HERITAGE_SECTION, context);
        const $heritageOptions = $(SELECTORS.HERITAGE_OPTIONS, context);
        const $submitButton = $(SELECTORS.SUBMIT_BUTTON, context);
        const $selectedAncestry = $(SELECTORS.SELECTED_ANCESTRY);
        const $selectedHeritage = $(SELECTORS.SELECTED_HERITAGE);
        
        /**
         * Parse and normalize heritage data from form attribute.
         *
         * @return {Object} Normalized heritage data keyed by ancestry ID
         */
        function parseHeritageData() {
          try {
            const rawData = $form.attr('data-heritages') || '{}';
            const parsed = JSON.parse(rawData);
            
            if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
              return {};
            }

            // Convert ancestry names to lowercase IDs for matching
            const normalized = {};
            Object.keys(parsed).forEach(function(key) {
              const normalizedKey = key.toLowerCase().replace(/\s+/g, '-');
              normalized[normalizedKey] = parsed[key];
            });
            
            return normalized;
          } catch (e) {
            console.error('Failed to parse heritage data:', e);
            return {};
          }
        }

        const normalizedHeritages = parseHeritageData();

        /**
         * Escape HTML special characters to prevent XSS.
         *
         * @param {string} str - String to escape
         * @return {string} HTML-safe string
         */
        function escapeHtml(str) {
          const div = document.createElement('div');
          div.textContent = str;
          return div.innerHTML;
        }

        /**
         * Render HTML for a single heritage card.
         *
         * @param {Object} heritage - Heritage data object
         * @param {string} heritage.id - Heritage identifier
         * @param {string} heritage.name - Heritage display name
         * @param {string} heritage.benefit - Heritage benefit description
         * @param {boolean} isSelected - Whether this heritage is currently selected
         * @return {string} HTML string for heritage card
         */
        function renderHeritageCard(heritage, isSelected) {
          const selectedClass = isSelected ? CSS_CLASSES.SELECTED : '';
          const safeId = escapeHtml(heritage.id);
          const safeName = escapeHtml(heritage.name);
          const safeBenefit = escapeHtml(heritage.benefit);
          
          return `<div class="${CSS_CLASSES.HERITAGE_CARD} ${selectedClass}" data-heritage="${safeId}">
            <h4>${safeName}</h4>
            <p>${safeBenefit}</p>
          </div>`;
        }

        /**
         * Display heritages for selected ancestry.
         *
         * @param {string} ancestryId - The ancestry identifier
         */
        function showHeritages(ancestryId) {
          const heritages = normalizedHeritages[ancestryId];
          
          if (!heritages || heritages.length === 0) {
            $heritageSection.addClass(CSS_CLASSES.HIDDEN);
            $submitButton.prop('disabled', false);
            return;
          }

          // Build heritage cards HTML
          const currentHeritageId = $selectedHeritage.val();
          const html = heritages
            .map(heritage => renderHeritageCard(heritage, currentHeritageId === heritage.id))
            .join('');
          
          $heritageOptions.html(html);
          $heritageSection.removeClass(CSS_CLASSES.HIDDEN);
          
          // Disable submit until heritage selected
          if (!currentHeritageId) {
            $submitButton.prop('disabled', true);
          }
        }

        /**
         * Update submit button state and text.
         *
         * @param {boolean} disabled - Whether button should be disabled
         * @param {string} text - Button text to display
         */
        function updateSubmitButton(disabled, text) {
          $submitButton.prop('disabled', disabled).text(text);
        }

        /**
         * Validate form data before submission.
         *
         * @return {Object} Validation result with isValid and message properties
         */
        function validateForm() {
          if (!$selectedAncestry.val()) {
            return { isValid: false, message: MESSAGES.SELECT_ANCESTRY };
          }
          
          const ancestryId = $selectedAncestry.val();
          const heritages = normalizedHeritages[ancestryId];
          if (heritages && heritages.length > 0 && !$selectedHeritage.val()) {
            return { isValid: false, message: MESSAGES.SELECT_HERITAGE };
          }
          
          return { isValid: true };
        }

        // Handle ancestry card clicks
        once('ancestry-click', SELECTORS.ANCESTRY_CARD, context).forEach(function(card) {
          $(card).on('click', function() {
            const ancestryId = $(this).data('ancestry');
            
            // Update UI
            $ancestryCards.removeClass(CSS_CLASSES.SELECTED);
            $(this).addClass(CSS_CLASSES.SELECTED);
            
            // Update hidden field
            $selectedAncestry.val(ancestryId);
            
            // Clear heritage selection
            $selectedHeritage.val('');
            
            // Show heritages for this ancestry
            showHeritages(ancestryId);
          });
        });

        // Handle heritage card clicks (delegated event)
        $heritageOptions.on('click', `.${CSS_CLASSES.HERITAGE_CARD}`, function() {
          const heritageId = $(this).data('heritage');
          
          // Update UI
          $(`.${CSS_CLASSES.HERITAGE_CARD}`).removeClass(CSS_CLASSES.SELECTED);
          $(this).addClass(CSS_CLASSES.SELECTED);
          
          // Update hidden field
          $selectedHeritage.val(heritageId);
          
          // Enable submit button
          $submitButton.prop('disabled', false);
        });

        // Initialize if ancestry already selected
        const currentAncestry = $selectedAncestry.val();
        if (currentAncestry) {
          showHeritages(currentAncestry);
        }

        // Handle form submission with AJAX
        $form.on('submit', function(e) {
          e.preventDefault();
          
          // Validation
          const validation = validateForm();
          if (!validation.isValid) {
            alert(validation.message);
            return false;
          }
          
          // Show loading state
          updateSubmitButton(true, BUTTON_TEXT.SAVING);
          
          const formData = $form.serialize();
          const actionUrl = $form.attr('action');
          
          $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                window.location.href = response.redirect;
              } else {
                alert(response.message || MESSAGES.SAVE_ERROR);
                updateSubmitButton(false, BUTTON_TEXT.DEFAULT);
              }
            },
            error: function(xhr) {
              const message = (xhr.responseJSON && xhr.responseJSON.message) || MESSAGES.SAVE_ERROR;
              alert(message);
              updateSubmitButton(false, BUTTON_TEXT.DEFAULT);
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

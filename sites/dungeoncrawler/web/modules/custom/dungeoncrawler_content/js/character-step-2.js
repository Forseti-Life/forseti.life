/**
 * @file
 * Character Creation Step 2 - Ancestry & Heritage Selection
 * 
 * Handles ancestry and heritage selection for PF2e character creation.
 * Data conforms to character_options_step2.json schema (v1.0.0).
 * 
 * Schema Conformance:
 * - Ancestry: Hot-column in dc_characters table + JSON (character_data.ancestry.name)
 * - Heritage: JSON-only field (character_data.ancestry.heritage), NOT a hot-column
 * 
 * Data Flow:
 * CharacterManager::HERITAGES → Form data-heritages attribute → JS parsing
 * Heritage structure per schema: {id: string, name: string, benefit: string}
 * 
 * @see character_options_step2.json Schema definition
 * @see CharacterManager::HERITAGES PHP constant for heritage data source
 * @see CharacterManager::buildCharacterJson() For JSON structure
 */

(function ($, Drupal, once) {
  'use strict';

  // Constants
  const SELECTORS = {
    FORM: 'form.character-creation-form',
    ANCESTRY_CARD: '.ancestry-card',
    HERITAGE_SECTION: '#heritageSelection',
    HERITAGE_OPTIONS: '#heritageOptions',
    SUBMIT_BUTTON: 'button[type="submit"], input[type="submit"]',
    SELECTED_ANCESTRY: 'select[name="ancestry"]',
    SELECTED_HERITAGE: 'select[name="heritage"]',
    SELECTED_ANCESTRY_FEAT: 'input[name="ancestry_feat"]',
    HERITAGE_DATA_HOST: '.ancestry-selection',
  };

  const CSS_CLASSES = {
    SELECTED: 'selected',
    HIDDEN: 'hidden',
    HERITAGE_CARD: 'heritage-card',
  };

  const BUTTON_TEXT = {
    DEFAULT: 'Next: Choose Background →',
  };

  /**
   * Drupal behavior for Character Creation Step 2.
   * 
   * Implementation Notes:
   * - This is a LEGACY implementation using manual HTML rendering and data-attribute parsing
   * - Schema-driven alternative exists in character-creation-schema.js (preferred for new steps)
   * - Kept for backward compatibility with existing Step 2 templates
   * - Data validation aligns with character_options_step2.json schema v1.0.0
   * 
   * Future Migration Path:
   * - Consider migrating to schema-driven approach (character-creation-schema.js)
   * - Would replace data-attributes with drupalSettings
   * - Would use schema-driven form generation instead of hardcoded templates
   */
  Drupal.behaviors.characterStep2 = {
    attach: function (context, settings) {
      once('step2-init', SELECTORS.FORM, context).forEach(function(element) {
        const $form = $(element);
        const $ancestryCards = $(SELECTORS.ANCESTRY_CARD, context);
        const $heritageSection = $(SELECTORS.HERITAGE_SECTION, context);
        const $heritageOptions = $(SELECTORS.HERITAGE_OPTIONS, context);
        const $submitButton = $(SELECTORS.SUBMIT_BUTTON, context).first();
        const $selectedAncestry = $(SELECTORS.SELECTED_ANCESTRY, context);
        const $selectedHeritage = $(SELECTORS.SELECTED_HERITAGE, context);
        const $heritageHost = $(SELECTORS.HERITAGE_DATA_HOST, context).first();
        
        /**
         * Parse and normalize heritage data from form attribute.
         * 
         * Parses the data-heritages attribute which contains CharacterManager::HERITAGES
         * structured as: {ancestryName: [{id, name, benefit}, ...]}
         * 
         * Schema Conformance: Validates against character_options_step2.json/$defs/heritageOption
         * - id: string (unique identifier, e.g., "ancient-blooded", "forge")
         * - name: string (display name, e.g., "Ancient-Blooded Dwarf")  
         * - benefit: string (mechanical benefit description)
         *
         * @return {Object} Normalized heritage data keyed by lowercase ancestry ID
         *   Example: {"dwarf": [{id: "forge", name: "Forge Dwarf", benefit: "Fire resistance"}]}
         */
        function parseHeritageData() {
          try {
            const rawData = $heritageHost.attr('data-heritages') || '{}';
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
         * Generates UI card element conforming to heritageOption schema definition.
         *
         * @param {Object} heritage - Heritage data object (matches schema/$defs/heritageOption)
         * @param {string} heritage.id - Heritage identifier (required by schema)
         * @param {string} heritage.name - Heritage display name (required by schema)
         * @param {string} heritage.benefit - Heritage benefit description (required by schema)
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
         * Loads and renders heritage options based on selected ancestry.
         * If no heritages exist for ancestry, submission is allowed without heritage selection.
         *
         * @param {string} ancestryId - The ancestry identifier (lowercase, normalized)
         */
        function showHeritages(ancestryId) {
          const heritages = normalizedHeritages[ancestryId];
          
          if (!heritages || heritages.length === 0) {
            $heritageSection.addClass(CSS_CLASSES.HIDDEN);
            return;
          }

          // Build heritage cards HTML
          const currentHeritageId = $selectedHeritage.val();
          const html = heritages
            .map(heritage => renderHeritageCard(heritage, currentHeritageId === heritage.id))
            .join('');
          
          $heritageOptions.html(html);
          $heritageSection.removeClass(CSS_CLASSES.HIDDEN);
          
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

        // Handle ancestry card clicks
        once('ancestry-click', SELECTORS.ANCESTRY_CARD, context).forEach(function(card) {
          $(card).on('click', function() {
            const ancestryId = $(this).data('ancestry');
            
            // Update UI
            $ancestryCards.removeClass(CSS_CLASSES.SELECTED);
            $(this).addClass(CSS_CLASSES.SELECTED);
            
            // Update hidden field
            if ($selectedAncestry.length) {
              $selectedAncestry.val(ancestryId).trigger('change');
            }
            
            // Clear heritage selection
            if ($selectedHeritage.length) {
              $selectedHeritage.val('');
            }

            // Clear stale ancestry feat choice before submit.
            $(SELECTORS.SELECTED_ANCESTRY_FEAT, context).prop('checked', false);
            
            // Show heritages for this ancestry
            showHeritages(ancestryId);

            if ($submitButton.length) {
              $submitButton.prop('disabled', false);
            }
          });
        });

        // Handle heritage card clicks (delegated event)
        $heritageOptions.on('click', `.${CSS_CLASSES.HERITAGE_CARD}`, function() {
          const heritageId = $(this).data('heritage');
          
          // Update UI
          $(`.${CSS_CLASSES.HERITAGE_CARD}`).removeClass(CSS_CLASSES.SELECTED);
          $(this).addClass(CSS_CLASSES.SELECTED);
          
          // Update hidden field
          if ($selectedHeritage.length) {
            $selectedHeritage.val(heritageId);
          }
          
          // Enable submit button
          $submitButton.prop('disabled', false);
        });

        // Initialize if ancestry already selected
        const currentAncestry = $selectedAncestry.val();
        if (currentAncestry) {
          showHeritages(currentAncestry);
        }

        // Dropdown ancestry changes (keyboard/manual select) also clear
        // dependent selections before form submit.
        once('ancestry-select-change', SELECTORS.SELECTED_ANCESTRY, context).forEach(function(selectElement) {
          $(selectElement).on('change', function() {
            if ($selectedHeritage.length) {
              $selectedHeritage.val('');
            }
            $(SELECTORS.SELECTED_ANCESTRY_FEAT, context).prop('checked', false);
          });
        });

        updateSubmitButton(false, BUTTON_TEXT.DEFAULT);

        // Native form submission only.
        $form.on('submit', function() {
          updateSubmitButton(true, 'Saving...');
        });
      });
    }
  };

})(jQuery, Drupal, once);

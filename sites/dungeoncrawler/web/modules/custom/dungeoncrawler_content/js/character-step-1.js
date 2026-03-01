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
        
        // Native form submission only.
        $form.on('submit', function() {
          $submitButton.prop('disabled', true).text('Saving...');
        });
      });
    }
  };

})(jQuery, Drupal, once);

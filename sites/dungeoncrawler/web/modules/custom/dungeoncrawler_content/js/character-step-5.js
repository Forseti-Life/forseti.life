/**
 * @file
 * Character Creation Step 5: Ability Scores (Read-Only Display)
 *
 * Step 5 displays auto-calculated ability scores based on character choices
 * from previous steps (ancestry, background, class). This step is read-only
 * and does not accept user input for ability boosts.
 *
 * Ability scores are calculated server-side in CharacterCreationStepForm::calculateAbilitiesFromSelections()
 * based on:
 * - Base scores (10 for all abilities)
 * - Ancestry boosts/flaws
 * - Background boosts (from step 3)
 * - Class key ability boost
 *
 * This file provides minimal client-side behavior to maintain consistency
 * with other character creation steps.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.characterStep5 = {
    attach: function (context, settings) {
      // Step 5 is read-only. Ability scores are displayed from server-side
      // calculation. No user interaction is needed or supported on this step.
      
      // The form submission is handled by Drupal's standard form system.
      // No custom AJAX handling is needed since there are no selections to validate.
      
      // If the form exists, it will automatically submit when the user clicks
      // the "Next" button, and the PHP backend will handle calculating and
      // storing the ability scores.
    }
  };

})(jQuery, Drupal, once);

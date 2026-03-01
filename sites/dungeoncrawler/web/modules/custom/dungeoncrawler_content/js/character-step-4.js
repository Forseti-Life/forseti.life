/**
 * @file
 * Character Creation Step 4 - Class Selection
 *
 * Class, key ability, class feats, and spells are all Drupal Form API
 * elements (select, radios, checkboxes). This file provides only the
 * submit-button loading state.
 *
 * @see CharacterCreationStepForm::buildStep4Fields()
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.characterStep4 = {
    attach: function (context) {
      once('step4-init', 'form.character-creation-form', context).forEach(function (element) {
        var $form = $(element);
        var $submit = $form.find('[type="submit"]');

        $form.on('submit', function () {
          $submit.prop('disabled', true).text('Saving\u2026');
        });
      });
    },
  };

})(jQuery, Drupal, once);

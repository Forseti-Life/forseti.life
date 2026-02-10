/**
 * @file
 * Character Creation Step 1 - Name & Concept
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.characterStep1 = {
    attach: function (context, settings) {
      once('step1-init', '#step1Form', context).forEach(function(element) {
        const $form = $(element);
        const $nameInput = $('#name');
        
        // Real-time validation
        $nameInput.on('input', function() {
          const name = $(this).val().trim();
          if (name.length >= 2) {
            $(this).removeClass('error');
          } else {
            $(this).addClass('error');
          }
        });

        // Form submission
        $form.on('submit', function(e) {
          const name = $nameInput.val().trim();
          
          if (name.length < 2) {
            e.preventDefault();
            alert('Please enter a character name (at least 2 characters).');
            $nameInput.focus();
            return false;
          }
          
          // Show loading state
          $form.find('button[type="submit"]').prop('disabled', true).text('Saving...');
        });
      });
    }
  };

})(jQuery, Drupal, once);

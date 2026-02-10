/**
 * @file
 * Character Creation Step 8: Final Details & Review
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.characterStep8 = {
    attach: function (context, settings) {
      once('step8-submit', '#step-8-form', context).forEach((element) => {
        const $form = $(element);

        // Handle form submission with AJAX
        $form.on('submit', function(e) {
          e.preventDefault();

          // Validation (all fields optional for final details)
          // Character can be completed without these details
          
          // Hide error message
          $('#error-message').addClass('hidden');

          // Disable submit button to prevent double submission
          const $submitButton = $('#next-button');
          $submitButton.prop('disabled', true).text('Creating Character...');

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
              if (response.success) {
                // Show success message
                $submitButton.text('✓ Character Created!');
                
                // Redirect to character sheet or character list
                setTimeout(function() {
                  window.location.href = response.redirect;
                }, 500);
              } else {
                $('#error-message').text(response.message || 'Error creating character.').removeClass('hidden');
                $submitButton.prop('disabled', false).text('🎉 Create Character');
              }
            },
            error: function(xhr, status, error) {
              $('#error-message').text('Failed to create character. Please try again.').removeClass('hidden');
              $submitButton.prop('disabled', false).text('🎉 Create Character');
              console.error('Save error:', error);
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

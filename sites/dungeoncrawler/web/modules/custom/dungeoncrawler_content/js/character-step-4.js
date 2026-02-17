/**
 * @file
 * Character Creation Step 4 - Class Selection
 */

(function ($, Drupal, once) {
  'use strict';

  let selectedClass = null;

  /**
   * Select a class.
   */
  function selectClass(classId) {
    selectedClass = classId;

    // Update UI
    $('.class-card').removeClass('selected');
    $(`.class-card[data-class="${classId}"]`).addClass('selected');

    // Update hidden field
    $('#selected-class').val(classId);

    // Enable next button
    $('#next-button').prop('disabled', false);
  }

  Drupal.behaviors.characterStep4 = {
    attach: function (context, settings) {
      const $form = $('#step-4-form', context);
      const $errorMessage = $('#error-message');
      const $nextButton = $('#next-button');
      const $selectedClass = $('#selected-class');

      // Class card click using event delegation
      once('class-select', '.class-card', context).forEach((element) => {
        $(element).on('click', function() {
          const classId = $(this).data('class');
          selectClass(classId);
        });
      });

      // Pre-select if already chosen
      const currentClass = $selectedClass.val();
      if (currentClass) {
        selectClass(currentClass);
      }

      // Form submission
      once('step4-submit', '#step-4-form', context).forEach((element) => {
        $(element).on('submit', function(e) {
          e.preventDefault();

          if (!selectedClass) {
            $errorMessage.text('Please select a class.').removeClass('hidden').show();
            return;
          }

          const formData = $(this).serialize();
          const actionUrl = $(this).attr('action');

          $nextButton.prop('disabled', true).text('Saving...');
          $errorMessage.addClass('hidden').hide();

          $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            success: function(response) {
              if (response.success) {
                window.location.href = response.redirect;
              } else {
                $errorMessage
                  .text(response.message || 'An error occurred.')
                  .removeClass('hidden')
                  .show();
                $nextButton.prop('disabled', false).text('Next Step →');
              }
            },
            error: function(xhr) {
              let errorMsg = 'Failed to save. Please try again.';
              if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
              }
              $errorMessage.text(errorMsg).removeClass('hidden').show();
              $nextButton.prop('disabled', false).text('Next Step →');
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

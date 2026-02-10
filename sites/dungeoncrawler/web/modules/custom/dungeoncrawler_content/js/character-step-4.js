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
      // Class card click using event delegation
      once('class-select', '.class-card', context).forEach((element) => {
        $(element).on('click', function() {
          const classId = $(this).data('class');
          selectClass(classId);
        });
      });

      // Pre-select if already chosen
      const currentClass = $('#selected-class').val();
      if (currentClass) {
        selectClass(currentClass);
      }

      // Form submission
      once('step4-submit', '#step-4-form', context).forEach((element) => {
        $(element).on('submit', function(e) {
          e.preventDefault();

          if (!selectedClass) {
            $('#error-message').text('Please select a class.').removeClass('hidden').show();
            return;
          }

          const formData = $(this).serialize();
          const actionUrl = $(this).attr('action');

          $('#next-button').prop('disabled', true).text('Saving...');
          $('#error-message').hide().addClass('hidden');

          $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            success: function(response) {
              if (response.success) {
                window.location.href = response.redirect;
              } else {
                $('#error-message')
                  .text(response.error || response.message || 'An error occurred.')
                  .removeClass('hidden')
                  .show();
                $('#next-button').prop('disabled', false).text('Next Step →');
              }
            },
            error: function(xhr) {
              let errorMsg = 'Failed to save. Please try again.';
              if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
              }
              $('#error-message').text(errorMsg).removeClass('hidden').show();
              $('#next-button').prop('disabled', false).text('Next Step →');
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

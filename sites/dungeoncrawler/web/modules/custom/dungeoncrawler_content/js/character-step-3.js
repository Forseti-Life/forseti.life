(function ($, Drupal, once) {
  'use strict';

  let selectedBackground = null;
  let selectedBoosts = [];

  /**
   * Select a background.
   */
  function selectBackground(backgroundId) {
    selectedBackground = backgroundId;

    // Update UI
    $('.background-card').removeClass('selected');
    $(`.background-card[data-background="${backgroundId}"]`).addClass('selected');

    // Update hidden field
    $('#selected-background').val(backgroundId);

    // Show ability boost selection
    $('#ability-boost-section').slideDown();

    // Reset ability boost selection
    selectedBoosts = [];
    $('.ability-card').removeClass('selected');
    updateBoostCounter();

    // Check if we can enable next button
    checkFormComplete();
  }

  /**
   * Toggle ability boost selection.
   */
  function toggleAbilityBoost(ability) {
    const index = selectedBoosts.indexOf(ability);
    
    if (index > -1) {
      // Deselect
      selectedBoosts.splice(index, 1);
      $(`.ability-card[data-ability="${ability}"]`).removeClass('selected');
    } else {
      // Select (if not at max)
      if (selectedBoosts.length < 2) {
        selectedBoosts.push(ability);
        $(`.ability-card[data-ability="${ability}"]`).addClass('selected');
      }
    }

    updateBoostCounter();
    updateBoostHiddenFields();
    checkFormComplete();
  }

  /**
   * Update boost counter display.
   */
  function updateBoostCounter() {
    $('#boost-count').text(selectedBoosts.length);
  }

  /**
   * Update hidden fields for ability boosts.
   */
  function updateBoostHiddenFields() {
    $('#ability-boost-1').val(selectedBoosts[0] || '');
    $('#ability-boost-2').val(selectedBoosts[1] || '');
  }

  /**
   * Check if form is complete and enable/disable next button.
   */
  function checkFormComplete() {
    const isComplete = selectedBackground && selectedBoosts.length === 2;
    $('#next-button').prop('disabled', !isComplete);
  }

  Drupal.behaviors.characterStep3 = {
    attach: function (context, settings) {
      const $form = $('#step-3-form', context);
      
      // Parse background data from form attribute (though we display it inline now)
      const backgroundsData = $form.length ? JSON.parse($form.attr('data-backgrounds') || '{}') : {};

      // Background card click - use event delegation
      once('background-click', '.background-card', context).forEach((element) => {
        $(element).on('click', function() {
          const backgroundId = $(this).data('background');
          selectBackground(backgroundId);
        });
      });

      // Ability boost card click - use event delegation
      once('ability-click', '.ability-card', context).forEach((element) => {
        $(element).on('click', function() {
          const ability = $(this).data('ability');
          toggleAbilityBoost(ability);
        });
      });

      // Pre-select if already chosen
      const currentBackground = $('#selected-background').val();
      if (currentBackground) {
        selectBackground(currentBackground);
        
        // Pre-select ability boosts if they exist
        const existingBoosts = [];
        const boost1 = $('#ability-boost-1').val();
        const boost2 = $('#ability-boost-2').val();
        if (boost1) existingBoosts.push(boost1);
        if (boost2) existingBoosts.push(boost2);
        
        existingBoosts.forEach(ability => {
          selectedBoosts.push(ability);
          $(`.ability-card[data-ability="${ability}"]`).addClass('selected');
        });
        
        updateBoostCounter();
        checkFormComplete();
      }

      // Form submission
      once('step3-submit', '#step-3-form', context).forEach((element) => {
        $(element).on('submit', function(e) {
          e.preventDefault();

          // Validate background
          if (!selectedBackground) {
            $('#error-message').text('Please select a background.').removeClass('hidden').show();
            return;
          }

          // Validate ability boosts
          if (selectedBoosts.length !== 2) {
            $('#error-message').text('Please select exactly 2 ability boosts.').removeClass('hidden').show();
            return;
          }

          const formData = $(this).serialize();
          const actionUrl = $(this).attr('action');

          $('#next-button').prop('disabled', true).text('Saving...');
          $('#error-message').addClass('hidden').hide();

          $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            success: function(response) {
              if (response.success) {
                window.location.href = response.redirect;
              } else {
                $('#error-message')
                  .text(response.message || 'An error occurred.')
                  .removeClass('hidden')
                  .show();
                $('#next-button').prop('disabled', false).text('Next Step →');
              }
            },
            error: function(xhr) {
              let errorMsg = 'Failed to save. Please try again.';
              if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
              }
              $('#error-message')
                .text(errorMsg)
                .removeClass('hidden')
                .show();
              $('#next-button').prop('disabled', false).text('Next Step →');
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

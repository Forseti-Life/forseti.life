/**
 * @file
 * Character Creation Step 3 - Background & Ability Boosts
 */

(function ($, Drupal, once) {
  'use strict';

  const MAX_BOOSTS = 2;

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
    updateBoostCounter($('#boost-count'));

    // Check if we can enable next button
    checkFormComplete();
  }

  /**
   * Toggle ability boost selection.
   */
  function toggleAbilityBoost(ability, $counter) {
    const index = selectedBoosts.indexOf(ability);
    
    if (index > -1) {
      // Deselect
      selectedBoosts.splice(index, 1);
      $(`.ability-card[data-ability="${ability}"]`).removeClass('selected');
    } else {
      // Select (if not at max)
      if (selectedBoosts.length < MAX_BOOSTS) {
        selectedBoosts.push(ability);
        $(`.ability-card[data-ability="${ability}"]`).addClass('selected');
      }
    }

    updateBoostCounter($counter);
    updateBoostHiddenFields();
    checkFormComplete();
  }

  /**
   * Update boost counter display.
   */
  function updateBoostCounter($counter) {
    $counter.text(selectedBoosts.length);
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
    const isComplete = selectedBackground && selectedBoosts.length === MAX_BOOSTS;
    $('#next-button').prop('disabled', !isComplete);
  }

  /**
   * Reset button to default state.
   */
  function resetButtonState($button) {
    $button.prop('disabled', false).text('Next Step →');
  }

  /**
   * Show error message.
   */
  function showError($errorElement, message) {
    $errorElement.text(message).removeClass('hidden').show();
  }

  /**
   * Hide error message.
   */
  function hideError($errorElement) {
    $errorElement.addClass('hidden').hide();
  }

  Drupal.behaviors.characterStep3 = {
    attach: function (context, settings) {
      const $form = $('#step-3-form', context);
      const $nextButton = $('#next-button', context);
      const $errorMessage = $('#error-message', context);
      const $boostCount = $('#boost-count', context);

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
          toggleAbilityBoost(ability, $boostCount);
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
        
        updateBoostCounter($boostCount);
        checkFormComplete();
      }

      // Form submission
      once('step3-submit', '#step-3-form', context).forEach((element) => {
        $(element).on('submit', function(e) {
          e.preventDefault();

          // Validate background
          if (!selectedBackground) {
            showError($errorMessage, 'Please select a background.');
            return;
          }

          // Validate ability boosts
          if (selectedBoosts.length !== MAX_BOOSTS) {
            showError($errorMessage, `Please select exactly ${MAX_BOOSTS} ability boosts.`);
            return;
          }

          const formData = $(this).serialize();
          const actionUrl = $(this).attr('action');

          $nextButton.prop('disabled', true).text('Saving...');
          hideError($errorMessage);

          $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                window.location.href = response.redirect;
              } else {
                showError($errorMessage, response.message || 'An error occurred.');
                resetButtonState($nextButton);
              }
            },
            error: function(xhr) {
              let errorMsg = 'Failed to save. Please try again.';
              if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
              }
              showError($errorMessage, errorMsg);
              resetButtonState($nextButton);
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

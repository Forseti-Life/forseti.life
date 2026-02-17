/**
 * @file
 * Character Creation Step 5: Ability Boosts
 */

(function ($, Drupal, once) {
  'use strict';

  const MAX_BOOSTS = 4;
  const MAX_PER_ABILITY = 1; // At character creation, can't boost same ability twice
  
  let selectedBoosts = {
    strength: 0,
    dexterity: 0,
    constitution: 0,
    intelligence: 0,
    wisdom: 0,
    charisma: 0
  };

  /**
   * Get total number of selected boosts.
   */
  function getTotalBoosts() {
    return Object.values(selectedBoosts).reduce((a, b) => a + b, 0);
  }

  /**
   * Convert selectedBoosts object to array format for storage.
   */
  function getBoostArray() {
    const boostArray = [];
    Object.keys(selectedBoosts).forEach(function(ability) {
      for (let i = 0; i < selectedBoosts[ability]; i++) {
        boostArray.push(ability);
      }
    });
    return boostArray;
  }

  /**
   * Update boost counters and UI.
   */
  function updateBoostUI() {
    const totalBoosts = getTotalBoosts();
    const remaining = MAX_BOOSTS - totalBoosts;
    
    // Update remaining counter
    $('#boosts-remaining').text(remaining);
    
    // Update each ability card
    Object.keys(selectedBoosts).forEach(function(ability) {
      const $card = $(`.ability-card[data-ability="${ability}"]`);
      const boostCount = selectedBoosts[ability];
      
      // Update counter
      $card.find('.boost-count').text(boostCount);
      
      // Update card state
      if (boostCount > 0) {
        $card.addClass('selected');
      } else {
        $card.removeClass('selected');
      }
      
      // Disable if at per-ability limit or no remaining boosts
      if (boostCount >= MAX_PER_ABILITY) {
        $card.addClass('maxed');
      } else if (remaining === 0) {
        $card.addClass('maxed');
      } else {
        $card.removeClass('maxed');
      }
    });
    
    // Update hidden field
    $('#selected-boosts').val(JSON.stringify(getBoostArray()));
    
    // Enable/disable next button
    if (totalBoosts === MAX_BOOSTS) {
      $('#next-button').prop('disabled', false);
      $('#error-message').addClass('hidden');
    } else {
      $('#next-button').prop('disabled', true);
    }
  }

  /**
   * Toggle ability boost.
   */
  function toggleBoost(ability) {
    const totalBoosts = getTotalBoosts();
    
    // If clicking a boosted ability, remove boost
    if (selectedBoosts[ability] > 0) {
      selectedBoosts[ability]--;
    }
    // If under max boosts and ability not maxed, add boost
    else if (totalBoosts < MAX_BOOSTS && selectedBoosts[ability] < MAX_PER_ABILITY) {
      selectedBoosts[ability]++;
    }
    
    updateBoostUI();
  }

  Drupal.behaviors.characterStep5 = {
    attach: function (context, settings) {
      // Ability card click
      once('ability-select', '.ability-card', context).forEach((element) => {
        $(element).on('click', function() {
          const ability = $(this).data('ability');
          // Allow clicking even if maxed to deselect
          toggleBoost(ability);
        });
      });

      // Load existing boosts if any
      const existingBoosts = $('#selected-boosts').val();
      if (existingBoosts) {
        try {
          const boostArray = JSON.parse(existingBoosts);
          boostArray.forEach(function(ability) {
            if (selectedBoosts.hasOwnProperty(ability)) {
              selectedBoosts[ability]++;
            } else {
              console.warn('Unknown ability in stored boosts:', ability);
            }
          });
          updateBoostUI();
        } catch (e) {
          console.error('Error parsing existing boosts:', e);
        }
      }

      // Form submission
      once('step5-submit', '#step-5-form', context).forEach((element) => {
        $(element).on('submit', function(e) {
          e.preventDefault();

          const totalBoosts = getTotalBoosts();
          
          if (totalBoosts !== MAX_BOOSTS) {
            $('#error-message').text(`Please select ${MAX_BOOSTS} ability boosts.`).removeClass('hidden').show();
            return;
          }

          const formData = $(this).serialize();
          const actionUrl = $(this).attr('action');

          $('#next-button').prop('disabled', true).text('Saving...');

          $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                window.location.href = response.redirect;
              } else {
                $('#error-message').text(response.error || response.message || 'An error occurred.').removeClass('hidden').show();
                $('#next-button').prop('disabled', false).text('Next Step →');
              }
            },
            error: function(xhr) {
              let errorMsg = 'Failed to save. Please try again.';
              if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
              } else if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
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

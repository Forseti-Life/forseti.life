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
   * Update boost counters and UI.
   */
  function updateBoostUI() {
    const totalBoosts = Object.values(selectedBoosts).reduce((a, b) => a + b, 0);
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
      
      // Max out if at limit
      if (boostCount >= MAX_PER_ABILITY) {
        $card.addClass('maxed');
      } else if (remaining === 0 && boostCount === 0) {
        $card.addClass('maxed');
      } else {
        $card.removeClass('maxed');
      }
    });
    
    // Update hidden field
    const boostArray = [];
    Object.keys(selectedBoosts).forEach(function(ability) {
      for (let i = 0; i < selectedBoosts[ability]; i++) {
        boostArray.push(ability);
      }
    });
    $('#selected-boosts').val(JSON.stringify(boostArray));
    
    // Enable/disable next button
    if (totalBoosts === MAX_BOOSTS) {
      $('#next-button').prop('disabled', false);
      $('#error-message').removeClass('error-message').addClass('hidden');
    } else {
      $('#next-button').prop('disabled', true);
    }
  }

  /**
   * Toggle ability boost.
   */
  function toggleBoost(ability) {
    const totalBoosts = Object.values(selectedBoosts).reduce((a, b) => a + b, 0);
    
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
          if (!$(this).hasClass('maxed')) {
            const ability = $(this).data('ability');
            toggleBoost(ability);
          }
        });
      });

      // Load existing boosts if any
      const existingBoosts = $('#selected-boosts').val();
      if (existingBoosts) {
        try {
          const boostArray = JSON.parse(existingBoosts);
          boostArray.forEach(function(ability) {
            if (selectedBoosts[ability] !== undefined) {
              selectedBoosts[ability]++;
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

          const totalBoosts = Object.values(selectedBoosts).reduce((a, b) => a + b, 0);
          
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
            success: function(response) {
              if (response.success) {
                window.location.href = response.redirect;
              } else {
                $('#error-message').text(response.message || 'An error occurred.').removeClass('hidden').show();
                $('#next-button').prop('disabled', false).text('Next Step →');
              }
            },
            error: function() {
              $('#error-message').text('Failed to save. Please try again.').removeClass('hidden').show();
              $('#next-button').prop('disabled', false).text('Next Step →');
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

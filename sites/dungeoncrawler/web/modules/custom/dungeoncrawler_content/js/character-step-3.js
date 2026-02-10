(function ($, Drupal, once) {
  'use strict';

  // Background details from Pathfinder 2E Core Rulebook
  const backgroundData = {
    acolyte: {
      name: 'Acolyte',
      description: 'You spent your early days in a religious monastery or cloister.',
      abilityBoosts: ['Intelligence', 'Wisdom'],
      skill: 'Religion',
      feat: 'Student of the Canon',
      details: 'You gain the Student of the Canon skill feat. You\'re trained in Religion and Scribing Lore.'
    },
    criminal: {
      name: 'Criminal',
      description: 'You have a history of breaking the law and living in the criminal underworld.',
      abilityBoosts: ['Dexterity', 'Intelligence'],
      skill: 'Stealth',
      feat: 'Experienced Smuggler',
      details: 'You gain the Experienced Smuggler skill feat. You\'re trained in Stealth and Underworld Lore.'
    },
    entertainer: {
      name: 'Entertainer',
      description: 'You performed before crowds, earning your coin through art and panache.',
      abilityBoosts: ['Dexterity', 'Charisma'],
      skill: 'Performance',
      feat: 'Fascinating Performance',
      details: 'You gain the Fascinating Performance skill feat. You\'re trained in Performance and Theater Lore.'
    },
    farmhand: {
      name: 'Farmhand',
      description: 'You grew up in a rural area, working the land and tending livestock.',
      abilityBoosts: ['Constitution', 'Wisdom'],
      skill: 'Athletics',
      feat: 'Assurance (Athletics)',
      details: 'You gain the Assurance (Athletics) skill feat. You\'re trained in Athletics and Farming Lore.'
    },
    guard: {
      name: 'Guard',
      description: 'You served in a military, guard force, or city watch, protecting others.',
      abilityBoosts: ['Strength', 'Charisma'],
      skill: 'Intimidation',
      feat: 'Quick Coercion',
      details: 'You gain the Quick Coercion skill feat. You\'re trained in Intimidation and Legal Lore or Warfare Lore.'
    },
    merchant: {
      name: 'Merchant',
      description: 'You come from a family of traders, or you worked in commerce yourself.',
      abilityBoosts: ['Intelligence', 'Charisma'],
      skill: 'Diplomacy',
      feat: 'Bargain Hunter',
      details: 'You gain the Bargain Hunter skill feat. You\'re trained in Diplomacy and Mercantile Lore.'
    },
    noble: {
      name: 'Noble',
      description: 'You were born into nobility or achieved a position of privilege.',
      abilityBoosts: ['Intelligence', 'Charisma'],
      skill: 'Society',
      feat: 'Courtly Graces',
      details: 'You gain the Courtly Graces skill feat. You\'re trained in Society and Heraldry Lore or Genealogy Lore.'
    },
    scholar: {
      name: 'Scholar',
      description: 'You spent years studying in libraries, academies, or under mentors.',
      abilityBoosts: ['Intelligence', 'Wisdom'],
      skill: 'Arcana, Nature, Occultism, or Religion',
      feat: 'Assurance (chosen skill)',
      details: 'Choose Arcana, Nature, Occultism, or Religion. You gain Assurance in that skill and are trained in Academia Lore.'
    },
    warrior: {
      name: 'Warrior',
      description: 'You have a history of fighting, whether through military service or personal conflict.',
      abilityBoosts: ['Strength', 'Constitution'],
      skill: 'Intimidation',
      feat: 'Intimidating Glare',
      details: 'You gain the Intimidating Glare skill feat. You\'re trained in Intimidation and Warfare Lore.'
    }
  };

  let selectedBackground = null;

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

    // Enable next button
    $('#next-button').prop('disabled', false);
  }

  /**
   * Populate background details.
   */
  function populateBackgroundDetails() {
    Object.keys(backgroundData).forEach(function(bgId) {
      const bg = backgroundData[bgId];
      const detailsHtml = `
        <p class="background-description">${bg.description}</p>
        <div class="background-mechanics">
          <div class="mechanic-item">
            <strong>Ability Boosts:</strong> ${bg.abilityBoosts.join(', ')}
          </div>
          <div class="mechanic-item">
            <strong>Skill Training:</strong> ${bg.skill}
          </div>
          <div class="mechanic-item">
            <strong>Feat:</strong> ${bg.feat}
          </div>
        </div>
      `;
      $(`#details-${bgId}`).html(detailsHtml);
    });
  }

  Drupal.behaviors.characterStep3 = {
    attach: function (context, settings) {
      // Populate details on page load
      populateBackgroundDetails();

      // Background card click
      once('background-select', '.background-card', context).forEach((element) => {
        $(element).on('click', function() {
          const backgroundId = $(this).data('background');
          selectBackground(backgroundId);
        });
      });

      // Pre-select if already chosen
      const currentBackground = $('#selected-background').val();
      if (currentBackground) {
        selectBackground(currentBackground);
      }

      // Form submission
      once('step3-submit', '#step-3-form', context).forEach((element) => {
        $(element).on('submit', function(e) {
          e.preventDefault();

          if (!selectedBackground) {
            $('#error-message').text('Please select a background.').show();
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
                $('#error-message').text(response.message || 'An error occurred.').show();
                $('#next-button').prop('disabled', false).text('Next Step →');
              }
            },
            error: function() {
              $('#error-message').text('Failed to save. Please try again.').show();
              $('#next-button').prop('disabled', false).text('Next Step →');
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

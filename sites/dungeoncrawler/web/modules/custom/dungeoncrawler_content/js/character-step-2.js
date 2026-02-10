/**
 * @file
 * Character Creation Step 2 - Ancestry Selection
 */

(function ($, Drupal, once) {
  'use strict';

  const ancestryData = {
    'dwarf': {
      heritages: [
        {id: 'ancient-blooded', name: 'Ancient-Blooded', benefit: 'Resistance to magic'},
        {id: 'forge', name: 'Forge Dwarf', benefit: 'Fire resistance'},
        {id: 'rock', name: 'Rock Dwarf', benefit: 'Extended darkvision'},
        {id: 'strong-blooded', name: 'Strong-Blooded', benefit: 'Poison resistance'}
      ]
    },
    'elf': {
      heritages: [
        {id: 'arctic', name: 'Arctic Elf', benefit: 'Cold resistance'},
        {id: 'cavern', name: 'Cavern Elf', benefit: 'Darkvision'},
        {id: 'seer', name: 'Seer Elf', benefit: 'Detect magic'},
        {id: 'woodland', name: 'Woodland Elf', benefit: 'Climb speed'}
      ]
    },
    'gnome': {
      heritages: [
        {id: 'chameleon', name: 'Chameleon Gnome', benefit: 'Change colors'},
        {id: 'fey-touched', name: 'Fey-Touched Gnome', benefit: 'First World magic'},
        {id: 'sensate', name: 'Sensate Gnome', benefit: 'Enhanced senses'},
        {id: 'umbral', name: 'Umbral Gnome', benefit: 'Darkvision'}
      ]
    },
    'goblin': {
      heritages: [
        {id: 'charhide', name: 'Charhide Goblin', benefit: 'Fire resistance'},
        {id: 'irongut', name: 'Irongut Goblin', benefit: 'Eat anything'},
        {id: 'razortooth', name: 'Razortooth Goblin', benefit: 'Bite attack'},
        {id: 'snow', name: 'Snow Goblin', benefit: 'Cold resistance'}
      ]
    },
    'halfling': {
      heritages: [
        {id: 'gutsy', name: 'Gutsy Halfling', benefit: 'Bonus vs fear'},
        {id: 'hillock', name: 'Hillock Halfling', benefit: 'Faster healing'},
        {id: 'nomadic', name: 'Nomadic Halfling', benefit: 'Extra languages'},
        {id: 'twilight', name: 'Twilight Halfling', benefit: 'Low-light vision'}
      ]
    },
    'human': {
      heritages: [
        {id: 'versatile', name: 'Versatile Heritage', benefit: 'Extra general feat'}
      ]
    }
  };

  window.selectAncestry = function(ancestryId) {
    $('.ancestry-card').removeClass('selected');
    $('.ancestry-card[data-ancestry="' + ancestryId + '"]').addClass('selected');
    $('#selectedAncestry').val(ancestryId);
    
    // Show heritages
    const ancestry = ancestryData[ancestryId];
    if (ancestry && ancestry.heritages.length > 0) {
      let html = '';
      ancestry.heritages.forEach(heritage => {
        html += '<div class="heritage-card" onclick="selectHeritage(\'' + heritage.id + '\')">';
        html += '<h4>' + heritage.name + '</h4>';
        html += '<p>' + heritage.benefit + '</p>';
        html += '</div>';
      });
      $('#heritageOptions').html(html);
      $('#heritageSelection').show();
      $('#step2Submit').prop('disabled', true);
    } else {
      $('#heritageSelection').hide();
      $('#step2Submit').prop('disabled', false);
    }
  };

  window.selectHeritage = function(heritageId) {
    $('.heritage-card').removeClass('selected');
    $('.heritage-card').filter(function() {
      return $(this).text().includes(heritageId);
    }).addClass('selected');
    $('#selectedHeritage').val(heritageId);
    $('#step2Submit').prop('disabled', false);
  };

  Drupal.behaviors.characterStep2 = {
    attach: function (context, settings) {
      // Initialize if ancestry is already selected
      const selectedAncestry = $('#selectedAncestry').val();
      if (selectedAncestry) {
        selectAncestry(selectedAncestry);
        
        const selectedHeritage = $('#selectedHeritage').val();
        if (selectedHeritage) {
          selectHeritage(selectedHeritage);
        }
      }

      once('step2-init', '#step2Form', context).forEach(function(element) {
        $(element).on('submit', function(e) {
        if (!$('#selectedAncestry').val()) {
          e.preventDefault();
          alert('Please select an ancestry.');
          return false;
        }
          $(this).find('button[type="submit"]').prop('disabled', true).text('Saving...');
        });
      });
    }
  };

})(jQuery, Drupal, once);

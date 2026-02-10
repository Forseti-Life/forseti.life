/**
 * @file
 * Character Creation Step 2 - Ancestry Selection
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.characterStep2 = {
    attach: function (context, settings) {
      once('step2-init', '#step2Form', context).forEach(function(element) {
        const $form = $(element);
        const $ancestryCards = $('.ancestry-card', context);
        const $heritageSection = $('#heritageSelection', context);
        const $heritageOptions = $('#heritageOptions', context);
        const $submitButton = $('#step2Submit', context);
        const $selectedAncestry = $('#selectedAncestry');
        const $selectedHeritage = $('#selectedHeritage');
        
        // Get heritage data from form data attribute
        let heritageData = {};
        try {
          const parsed = JSON.parse($form.attr('data-heritages') || '{}');
          // Ensure we have a valid object
          heritageData = (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) ? parsed : {};
        } catch (e) {
          console.error('Failed to parse heritage data:', e);
          heritageData = {};
        }

        // Convert ancestry names to lowercase IDs for matching
        const normalizedHeritages = {};
        if (heritageData && typeof heritageData === 'object') {
          Object.keys(heritageData).forEach(function(key) {
            const normalizedKey = key.toLowerCase().replace(/\s+/g, '-');
            normalizedHeritages[normalizedKey] = heritageData[key];
          });
        }
        
        console.log('Heritage data loaded:', normalizedHeritages);

        // Function to display heritages for selected ancestry
        function showHeritages(ancestryId) {
          console.log('showHeritages called with:', ancestryId);
          console.log('Available heritages:', Object.keys(normalizedHeritages));
          const heritages = normalizedHeritages[ancestryId];
          console.log('Found heritages for', ancestryId, ':', heritages);
          
          if (!heritages || heritages.length === 0) {
            $heritageSection.addClass('hidden');
            $submitButton.prop('disabled', false);
            return;
          }

          // Build heritage cards
          let html = '';
          heritages.forEach(function(heritage) {
            const isSelected = $selectedHeritage.val() === heritage.id ? 'selected' : '';
            html += '<div class="heritage-card ' + isSelected + '" data-heritage="' + heritage.id + '">';
            html += '<h4>' + heritage.name + '</h4>';
            html += '<p>' + heritage.benefit + '</p>';
            html += '</div>';
          });
          
          $heritageOptions.html(html);
          $heritageSection.removeClass('hidden');
          
          // Disable submit until heritage selected
          if (!$selectedHeritage.val()) {
            $submitButton.prop('disabled', true);
          }
        }

        // Handle ancestry card clicks
        once('ancestry-click', '.ancestry-card', context).forEach(function(card) {
          $(card).on('click', function() {
            const ancestryId = $(this).data('ancestry');
            
            // Update UI
            $ancestryCards.removeClass('selected');
            $(this).addClass('selected');
            
            // Update hidden field
            $selectedAncestry.val(ancestryId);
            
            // Clear heritage selection
            $selectedHeritage.val('');
            
            // Show heritages for this ancestry
            showHeritages(ancestryId);
          });
        });

        // Handle heritage card clicks (delegated event)
        $heritageOptions.on('click', '.heritage-card', function() {
          const heritageId = $(this).data('heritage');
          
          // Update UI
          $('.heritage-card').removeClass('selected');
          $(this).addClass('selected');
          
          // Update hidden field
          $selectedHeritage.val(heritageId);
          
          // Enable submit button
          $submitButton.prop('disabled', false);
        });

        // Initialize if ancestry already selected
        const currentAncestry = $selectedAncestry.val();
        if (currentAncestry) {
          showHeritages(currentAncestry);
        }

        // Handle form submission with AJAX
        $form.on('submit', function(e) {
          e.preventDefault();
          
          // Validation
          if (!$selectedAncestry.val()) {
            alert('Please select an ancestry.');
            return false;
          }
          
          // Check if heritage is required (all ancestries have heritages)
          const ancestryId = $selectedAncestry.val();
          const heritages = normalizedHeritages[ancestryId];
          if (heritages && heritages.length > 0 && !$selectedHeritage.val()) {
            alert('Please select a heritage.');
            return false;
          }
          
          // Show loading state
          $submitButton.prop('disabled', true).text('Saving...');
          
          const formData = $form.serialize();
          const actionUrl = $form.attr('action');
          
          $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                window.location.href = response.redirect;
              } else {
                alert(response.message || 'An error occurred.');
                $submitButton.prop('disabled', false).text('Next: Choose Background →');
              }
            },
            error: function(xhr) {
              let message = 'Failed to save. Please try again.';
              if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
              }
              alert(message);
              $submitButton.prop('disabled', false).text('Next: Choose Background →');
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

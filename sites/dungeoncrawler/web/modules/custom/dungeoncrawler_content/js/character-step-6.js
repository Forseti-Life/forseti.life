/**
 * @file
 * Character Creation Step 6: Alignment & Personality
 */

(function ($, Drupal, once) {
  'use strict';

  // Pathfinder 2E Alignments
  const alignments = {
    'lg': {
      name: 'Lawful Good',
      description: 'You believe in honor, order, and doing what is right. You follow rules and help others.',
      examples: 'Paladins, honorable knights, benevolent rulers'
    },
    'ng': {
      name: 'Neutral Good',
      description: 'You do what is good and right without bias for or against order. You help others as you can.',
      examples: 'Healers, charitable monks, helpful druids'
    },
    'cg': {
      name: 'Chaotic Good',
      description: 'You follow your conscience and value freedom. You do what is right in your own way.',
      examples: 'Freedom fighters, rebels with a cause, independent heroes'
    },
    'ln': {
      name: 'Lawful Neutral',
      description: 'You value order, organization, and tradition. You follow rules regardless of good or evil.',
      examples: 'Judges, soldiers, bureaucrats'
    },
    'n': {
      name: 'Neutral',
      description: 'You act naturally without prejudice or compulsion. You do what seems best at the time.',
      examples: 'Druids, merchants, pragmatic adventurers'
    },
    'cn': {
      name: 'Chaotic Neutral',
      description: 'You follow your whims and value freedom above all else. You are unpredictable.',
      examples: 'Tricksters, wanderers, free spirits'
    },
    'le': {
      name: 'Lawful Evil',
      description: 'You seek power through order and control. You follow rules but use them for your own gain.',
      examples: 'Tyrants, corrupt officials, ruthless overlords'
    },
    'ne': {
      name: 'Neutral Evil',
      description: 'You do whatever you can get away with. You are out for yourself, pure and simple.',
      examples: 'Criminals, mercenaries, selfish schemers'
    },
    'ce': {
      name: 'Chaotic Evil',
      description: 'You act with arbitrary violence, spurred by greed, hatred, or bloodlust.',
      examples: 'Demons, violent criminals, mad destroyers'
    }
  };

  Drupal.behaviors.characterStep6 = {
    attach: function (context, settings) {
      once('step6-init', '#step-6-form', context).forEach((element) => {
        const $form = $(element);
        let selectedAlignment = $('#selected-alignment').val() || '';

        // Populate alignments
        const alignmentsGrid = $('.alignments-grid', context);
        if (alignmentsGrid.children('.alignment-card').length === 0) {
          Object.keys(alignments).forEach(function(alignId) {
            const align = alignments[alignId];
            const card = $('<div>')
              .addClass('alignment-card')
              .attr('data-alignment', alignId)
              .html('<h3>' + align.name + '</h3>');
            alignmentsGrid.append(card);
          });
        }

        // Restore previous selection
        if (selectedAlignment) {
          $('.alignment-card[data-alignment="' + selectedAlignment + '"]').addClass('selected');
        }

        // Handle alignment selection
        once('alignment-click', '.alignment-card', context).forEach((card) => {
          $(card).on('click', function() {
            const alignId = $(this).data('alignment');
            
            // Update UI
            $('.alignment-card').removeClass('selected');
            $(this).addClass('selected');
            
            // Update hidden field
            selectedAlignment = alignId;
            $('#selected-alignment').val(alignId);
          });
        });

        // Handle form submission with AJAX
        $form.on('submit', function(e) {
          e.preventDefault();

          // Validation
          if (!selectedAlignment) {
            $('#error-message').text('Please select an alignment.').removeClass('hidden');
            return;
          }

          // Optional fields - no validation needed for age, deity, gender
          
          // Hide error message
          $('#error-message').addClass('hidden');

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
                window.location.href = response.redirect;
              } else {
                $('#error-message').text(response.message || 'Error saving step.').removeClass('hidden');
              }
            },
            error: function(xhr, status, error) {
              $('#error-message').text('Failed to save. Please try again.').removeClass('hidden');
              console.error('Save error:', error);
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

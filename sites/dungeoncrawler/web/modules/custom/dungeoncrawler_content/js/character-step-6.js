/**
 * @file
 * Character Creation Step 6: Alignment & Personality
 */

(function ($, Drupal, once) {
  'use strict';

  // Constants
  const CSS_CLASSES = {
    CARD: 'alignment-card',
    SELECTED: 'selected',
    HIDDEN: 'hidden',
    GRID: 'alignments-grid'
  };

  const SELECTORS = {
    FORM: '#step-6-form',
    ALIGNMENT_FIELD: '#selected-alignment',
    ERROR_MSG: '#error-message',
    GRID: '.alignments-grid',
    CARD: '.alignment-card'
  };

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
      once('step6-init', SELECTORS.FORM, context).forEach((element) => {
        const $form = $(element);
        const $alignmentField = $(SELECTORS.ALIGNMENT_FIELD, context);
        
        // Validate required elements exist
        if ($alignmentField.length === 0) {
          console.warn('Character Step 6: Missing alignment field');
          return;
        }

        const $alignmentsGrid = $(SELECTORS.GRID, context);
        let selectedAlignment = $alignmentField.val() || '';

        // Populate alignments
        if ($alignmentsGrid.children(SELECTORS.CARD).length === 0) {
          Object.keys(alignments).forEach(function(alignId) {
            const align = alignments[alignId];
            const card = $('<div>')
              .addClass(CSS_CLASSES.CARD)
              .attr('data-alignment', alignId)
              .html(`<h3>${align.name}</h3>`);
            $alignmentsGrid.append(card);
          });
        }

        // Restore previous selection
        if (selectedAlignment) {
          $alignmentsGrid.find(SELECTORS.CARD).filter(`[data-alignment="${selectedAlignment}"]`).addClass(CSS_CLASSES.SELECTED);
        }

        // Handle alignment selection
        once('alignment-click', SELECTORS.CARD, context).forEach((card) => {
          $(card).on('click', function() {
            const alignId = $(this).data('alignment');
            
            // Update UI
            $(SELECTORS.CARD).removeClass(CSS_CLASSES.SELECTED);
            $(this).addClass(CSS_CLASSES.SELECTED);
            
            // Update hidden field
            selectedAlignment = alignId;
            $alignmentField.val(alignId);
          });
        });

        // Handle form submission with AJAX
        once('step6-submit', SELECTORS.FORM, context).forEach((formEl) => {
          $(formEl).on('submit', function(e) {
            e.preventDefault();

            const $errorMsg = $(SELECTORS.ERROR_MSG);

            // Validation
            if (!selectedAlignment) {
              $errorMsg.text('Please select an alignment.').removeClass(CSS_CLASSES.HIDDEN);
              return;
            }

            // Optional fields - no validation needed for age, deity, gender
            
            // Hide error message
            $errorMsg.addClass(CSS_CLASSES.HIDDEN);

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
                  $errorMsg.text(response.message || 'Error saving step.').removeClass(CSS_CLASSES.HIDDEN);
                }
              },
              error: function(xhr, status, error) {
                console.error('Character Step 6 save error:', status, error, xhr.responseJSON);
                $errorMsg.text('Failed to save. Please try again.').removeClass(CSS_CLASSES.HIDDEN);
              }
            });
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

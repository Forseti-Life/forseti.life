/**
 * @file
 * Character Creation Step 6: Alignment & Personality
 * 
 * Handles alignment selection (required), deity selection (optional, required for clerics/champions),
 * age (optional), and gender (optional).
 * 
 * Alignment IDs use uppercase format ('LG', 'NG', 'CG', 'LN', 'N', 'CN', 'LE', 'NE', 'CE')
 * to match character.schema.json enum values for the 'alignment' field and
 * character_options_step6.json alignmentOption.id enum values.
 * 
 * The form includes fields for:
 * - alignment: Required, must be one of the 9 standard PF2e alignments
 * - deity: Optional (but required for cleric/champion classes per schema validation)
 * - age: Optional integer (1-500 per character.schema.json)
 * - gender: Optional string (max 100 chars per character.schema.json)
 * 
 * Server-side validation occurs in CharacterCreationStepController using SchemaLoader.
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
    DEITY_FIELD: '#deity',
    AGE_FIELD: '#age',
    GENDER_FIELD: '#gender',
    ERROR_MSG: '#error-message',
    GRID: '.alignments-grid',
    CARD: '.alignment-card'
  };

  // Pathfinder 2E Alignments - Using uppercase IDs to match character.schema.json
  const alignments = {
    'LG': {
      name: 'Lawful Good',
      description: 'You believe in honor, order, and doing what is right. You follow rules and help others.',
      examples: 'Paladins, honorable knights, benevolent rulers'
    },
    'NG': {
      name: 'Neutral Good',
      description: 'You do what is good and right without bias for or against order. You help others as you can.',
      examples: 'Healers, charitable monks, helpful druids'
    },
    'CG': {
      name: 'Chaotic Good',
      description: 'You follow your conscience and value freedom. You do what is right in your own way.',
      examples: 'Freedom fighters, rebels with a cause, independent heroes'
    },
    'LN': {
      name: 'Lawful Neutral',
      description: 'You value order, organization, and tradition. You follow rules regardless of good or evil.',
      examples: 'Judges, soldiers, bureaucrats'
    },
    'N': {
      name: 'Neutral',
      description: 'You act naturally without prejudice or compulsion. You do what seems best at the time.',
      examples: 'Druids, merchants, pragmatic adventurers'
    },
    'CN': {
      name: 'Chaotic Neutral',
      description: 'You follow your whims and value freedom above all else. You are unpredictable.',
      examples: 'Tricksters, wanderers, free spirits'
    },
    'LE': {
      name: 'Lawful Evil',
      description: 'You seek power through order and control. You follow rules but use them for your own gain.',
      examples: 'Tyrants, corrupt officials, ruthless overlords'
    },
    'NE': {
      name: 'Neutral Evil',
      description: 'You do whatever you can get away with. You are out for yourself, pure and simple.',
      examples: 'Criminals, mercenaries, selfish schemers'
    },
    'CE': {
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
            const actionUrl = $form.attr('action');

            // Use native Drupal form submit unless this is an explicit JSON save endpoint.
            if (!actionUrl || actionUrl.indexOf('/save') === -1) {
              return;
            }

            e.preventDefault();

            const $errorMsg = $(SELECTORS.ERROR_MSG);

            $errorMsg.addClass(CSS_CLASSES.HIDDEN);

            // Prepare form data
            const formData = $form.serialize();

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

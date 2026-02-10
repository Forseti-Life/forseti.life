/**
 * @file
 * Schema-driven character creation form builder.
 *
 * Dynamically generates form fields based on JSON schema loaded from drupalSettings.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.characterCreationSchema = {
    attach: function (context) {
      once('character-creation-schema', '#stepForm', context).forEach((form) => {
        const settings = drupalSettings.characterCreation || {};
        const step = settings.step;
        const schema = settings.schema;
        const options = settings.options || {};
        const characterData = settings.character || {};

        if (!schema) {
          console.error('Schema not loaded for step', step);
          return;
        }

        // Build form fields based on schema
        const fieldsContainer = form.querySelector('#formFields');
        const fieldDefs = schema.properties?.fields?.properties || {};

        Object.keys(fieldDefs).forEach(fieldName => {
          const fieldDef = fieldDefs[fieldName];
          const fieldProps = fieldDef.properties || {};
          const fieldType = fieldProps.field_type?.const;
          const label = fieldProps.label?.const || fieldName;
          const required = fieldProps.required?.const || false;
          const helpText = fieldProps.help_text?.const;
          const currentValue = characterData[fieldName] || '';

          const fieldHtml = this.buildField(fieldName, fieldType, label, required, helpText, fieldProps, options, currentValue);
          fieldsContainer.insertAdjacentHTML('beforeend', fieldHtml);
        });

        // Initialize step-specific behaviors
        this.initializeStep(step, form, schema, options);

        // Handle form submission
        $(form).off('submit').on('submit', (e) => {
          e.preventDefault();
          this.handleSubmit(form, step);
        });
      });
    },

    /**
     * Build individual form field HTML.
     */
    buildField: function(name, type, label, required, helpText, props, options, currentValue = '') {
      const requiredAttr = required ? 'required' : '';
      const requiredMark = required ? ' *' : '';
      let fieldHtml = '<div class="form-group">';
      fieldHtml += `<label for="${name}">${label}${requiredMark}</label>`;

      switch (type) {
        case 'text':
          const validation = props.validation?.properties || {};
          const maxLength = validation.max_length?.const || 100;
          const pattern = validation.pattern?.const || '';
          fieldHtml += `<input type="text" id="${name}" name="${name}" 
            class="form-control" ${requiredAttr} 
            maxlength="${maxLength}" 
            value="${this.escapeHtml(currentValue)}"
            ${pattern ? `pattern="${pattern}"` : ''}>`;
          break;

        case 'textarea':
          const textValidation = props.validation?.properties || {};
          const rows = textValidation.rows?.const || 4;
          const maxLen = textValidation.max_length?.const || 1000;
          fieldHtml += `<textarea id="${name}" name="${name}" 
            class="form-control" ${requiredAttr} 
            rows="${rows}" maxlength="${maxLen}">${this.escapeHtml(currentValue)}</textarea>`;
          break;

        case 'select':
          fieldHtml += `<select id="${name}" name="${name}" 
            class="form-control" ${requiredAttr}>`;
          fieldHtml += '<option value="">-- Select --</option>';
          
          // Add options from schema or options data
          const optionsList = this.getOptionsForField(name, props, options);
          optionsList.forEach(opt => {
            const selected = (currentValue === opt.id) ? 'selected' : '';
            fieldHtml += `<option value="${opt.id}" ${selected}>${opt.name}</option>`;
          });
          
          fieldHtml += '</select>';
          break;

        case 'multi-select':
          fieldHtml += '<div class="multi-select-group">';
          const multiOpts = props.options?.items?.enum || [];
          multiOpts.forEach(opt => {
            fieldHtml += `
              <label class="checkbox-label">
                <input type="checkbox" name="${name}[]" value="${opt}">
                ${opt}
              </label>`;
          });
          fieldHtml += '</div>';
          break;

        case 'readonly_display':
          fieldHtml += `<div class="readonly-display">${label}</div>`;
          break;

        default:
          fieldHtml += `<input type="text" id="${name}" name="${name}" class="form-control">`;
      }

      if (helpText) {
        fieldHtml += `<span class="form-help">${helpText}</span>`;
      }

      fieldHtml += '</div>';
      return fieldHtml;
    },

    /**
     * Get options for a select field.
     */
    getOptionsForField: function(fieldName, fieldProps, optionsData) {
      // Check if options are in optionsData first
      if (optionsData[fieldName]) {
        return optionsData[fieldName];
      }

      // Check for options in field properties
      const opts = fieldProps.options?.default || fieldProps.options?.items || [];
      return Array.isArray(opts) ? opts : [];
    },

    /**
     * Initialize step-specific behaviors.
     */
    initializeStep: function(step, form, schema, options) {
switch(step) {
        case 2:
          // Ancestry & Heritage - Set up dynamic heritage loading
          $(form).find('#ancestry').on('change', (e) => {
            const ancestry = $(e.target).val();
            const heritageSelect = $(form).find('#heritage');
            heritageSelect.empty().append('<option value="">-- Select Heritage --</option>');
            
            if (ancestry && options.heritages && options.heritages[ancestry]) {
              options.heritages[ancestry].forEach(h => {
                heritageSelect.append(`<option value="${h.id}">${h.name}</option>`);
              });
              heritageSelect.prop('disabled', false);
            } else {
              heritageSelect.prop('disabled', true);
            }
          });
          break;

        case 4:
          // Class selection - Could add class details display
          $(form).find('#class').on('change', (e) => {
            const classId = $(e.target).val();
            if (classId && options.classes) {
              const classData = options.classes.find(c => c.id === classId);
              if (classData) {
                // Could display class details here
                console.log('Selected class:', classData);
              }
            }
          });
          break;
      }
    },

    /**
     * Handle form submission.
     */
    handleSubmit: function(form, step) {
      const submitBtn = form.querySelector('#submitBtn');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving...';

      const formData = new FormData(form);
      const data = {};
      
      formData.forEach((value, key) => {
        if (key.endsWith('[]')) {
          const cleanKey = key.replace('[]', '');
          if (!data[cleanKey]) data[cleanKey] = [];
          data[cleanKey].push(value);
        } else {
          data[key] = value;
        }
      });

      $.ajax({
        url: form.action,
        method: 'POST',
        data: data,
        success: (response) => {
          if (response.success) {
            if (response.redirect) {
              window.location.href = response.redirect;
            }
          } else {
            alert(response.message || 'An error occurred');
            submitBtn.disabled = false;
            submitBtn.textContent = step < 8 ? 'Next →' : 'Create Character ✓';
          }
        },
        error: (xhr) => {
          const response = xhr.responseJSON || {};
          alert(response.message || 'An error occurred');
          submitBtn.disabled = false;
          submitBtn.textContent = step < 8 ? 'Next →' : 'Create Character ✓';
        }
      });
    },

    /**
     * Escape HTML to prevent XSS.
     */
    escapeHtml: function(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
  };

})(jQuery, Drupal, once);

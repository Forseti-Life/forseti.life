/**
 * @file
 * Character Creation Step 7: Starting Equipment
 */

(function ($, Drupal, once) {
  'use strict';

  // Configuration constants
  const CONFIG = {
    startingGold: 15,
    goldPrecision: 2, // Precision for internal calculations
    goldDisplayDecimals: 1, // Number of decimal places shown in UI
    errorDisplayDuration: 2000,
    selectors: {
      form: '#step-7-form',
      weaponsList: '#weapons-list',
      armorList: '#armor-list',
      gearList: '#gear-list',
      selectedItems: '#selected-items',
      goldRemaining: '#gold-remaining',
      errorMessage: '#error-message',
      selectedEquipmentData: '#selected-equipment-data',
      remainingGold: '#remaining-gold',
      addButton: '.btn-add-item',
      removeButton: '.btn-remove-item'
    },
    messages: {
      insufficientGold: 'Not enough gold!',
      emptyEquipment: 'No equipment selected',
      saveFailed: 'Failed to save. Please try again.'
    },
    classes: {
      equipmentItem: 'equipment-item',
      selectedItem: 'selected-item',
      hidden: 'hidden'
    }
  };

  // Starting Equipment Database (Simplified PF2E)
  const EQUIPMENT = {
    weapons: [
      { id: 'longsword', name: 'Longsword', cost: 1, damage: '1d8' },
      { id: 'shortsword', name: 'Shortsword', cost: 0.9, damage: '1d6' },
      { id: 'dagger', name: 'Dagger', cost: 0.2, damage: '1d4' },
      { id: 'rapier', name: 'Rapier', cost: 2, damage: '1d6' },
      { id: 'battleaxe', name: 'Battle Axe', cost: 1, damage: '1d8' },
      { id: 'warhammer', name: 'Warhammer', cost: 1, damage: '1d8' },
      { id: 'shortbow', name: 'Shortbow', cost: 3, damage: '1d6' },
      { id: 'longbow', name: 'Longbow', cost: 6, damage: '1d8' },
      { id: 'staff', name: 'Staff', cost: 0, damage: '1d4' }
    ],
    armor: [
      { id: 'leather', name: 'Leather Armor', cost: 2, ac: '+1' },
      { id: 'studded-leather', name: 'Studded Leather Armor', cost: 3, ac: '+2' },
      { id: 'chain-shirt', name: 'Chain Shirt', cost: 5, ac: '+2' },
      { id: 'hide-armor', name: 'Hide Armor', cost: 2, ac: '+3' },
      { id: 'scale-mail', name: 'Scale Mail', cost: 4, ac: '+3' },
      { id: 'chain-mail', name: 'Chain Mail', cost: 6, ac: '+4' },
      { id: 'breastplate', name: 'Breastplate', cost: 8, ac: '+4' },
      { id: 'shield', name: 'Wooden Shield', cost: 1, ac: '+2 circumstance' }
    ],
    gear: [
      { id: 'backpack', name: 'Backpack', cost: 0.1 },
      { id: 'bedroll', name: 'Bedroll', cost: 0.1 },
      { id: 'rope', name: 'Rope (50ft)', cost: 0.5 },
      { id: 'torch-5', name: 'Torches (5)', cost: 0.05 },
      { id: 'rations', name: "Rations (1 week)", cost: 0.4 },
      { id: 'waterskin', name: 'Waterskin', cost: 0.05 },
      { id: 'healers-kit', name: "Healer's Tools", cost: 5 },
      { id: 'thieves-tools', name: "Thieves' Tools", cost: 3 },
      { id: 'grappling-hook', name: 'Grappling Hook', cost: 0.1 },
      { id: 'lantern', name: 'Hooded Lantern', cost: 0.7 },
      { id: 'oil-flask', name: 'Oil (1 flask)', cost: 0.1 }
    ]
  };

  /**
   * Creates an equipment item HTML element.
   *
   * @param {Object} item - Equipment item data.
   * @param {string} category - Category of equipment (weapon, armor, gear).
   * @param {string} details - Additional details HTML (e.g., damage, AC).
   * @return {jQuery} jQuery element for the equipment item.
   */
  function createEquipmentElement(item, category, details) {
    const itemEl = $('<div>')
      .addClass(CONFIG.classes.equipmentItem)
      .attr('data-id', item.id)
      .attr('data-cost', item.cost)
      .attr('data-category', category);

    let html = '<div class="item-name">' + item.name + '</div>';
    if (details) {
      html += '<div class="item-details">' + details + '</div>';
    }
    html += '<div class="item-cost">' + item.cost + ' gp</div>';
    html += '<button type="button" class="btn-add-item">Add</button>';

    return itemEl.html(html);
  }

  /**
   * Populates an equipment list container with items.
   *
   * @param {jQuery} $container - Container element to populate.
   * @param {Array} items - Array of equipment items.
   * @param {string} category - Category of equipment.
   * @param {Function} detailsFormatter - Function to format item details (optional).
   */
  function populateEquipmentList($container, items, category, detailsFormatter) {
    if ($container.find('.' + CONFIG.classes.equipmentItem).length === 0) {
      $container.empty();
      items.forEach(function(item) {
        const details = detailsFormatter ? detailsFormatter(item) : null;
        const itemEl = createEquipmentElement(item, category, details);
        $container.append(itemEl);
      });
    }
  }

  /**
   * Rounds a number to specified decimal places.
   *
   * @param {number} value - Value to round.
   * @param {number} decimals - Number of decimal places.
   * @return {number} Rounded value.
   */
  function roundToDecimals(value, decimals) {
    const multiplier = Math.pow(10, decimals);
    return Math.round(value * multiplier) / multiplier;
  }

  Drupal.behaviors.characterStep7 = {
    attach: function (context, settings) {
      once('step7-init', CONFIG.selectors.form, context).forEach((element) => {
        const $form = $(element);
        let goldRemaining = CONFIG.startingGold;
        let selectedItems = [];

        /**
         * Populate all equipment lists.
         */
        function populateEquipment() {
          // Weapons
          const $weaponsList = $(CONFIG.selectors.weaponsList, context);
          populateEquipmentList($weaponsList, EQUIPMENT.weapons, 'weapon', function(item) {
            return item.damage + ' damage';
          });

          // Armor
          const $armorList = $(CONFIG.selectors.armorList, context);
          populateEquipmentList($armorList, EQUIPMENT.armor, 'armor', function(item) {
            return item.ac + ' AC';
          });

          // Gear
          const $gearList = $(CONFIG.selectors.gearList, context);
          populateEquipmentList($gearList, EQUIPMENT.gear, 'gear');
        }

        /**
         * Updates the selected items display and form fields.
         */
        function updateSelectedItems() {
          const $selectedContainer = $(CONFIG.selectors.selectedItems, context);
          
          if (selectedItems.length === 0) {
            $selectedContainer.html('<p class="empty-message">' + CONFIG.messages.emptyEquipment + '</p>');
          } else {
            $selectedContainer.empty();
            selectedItems.forEach(function(item, index) {
              const itemEl = $('<div>')
                .addClass(CONFIG.classes.selectedItem)
                .html(
                  '<div class="item-name">' + item.name + '</div>' +
                  '<div class="item-cost">' + item.cost + ' gp</div>' +
                  '<button type="button" class="btn-remove-item" data-index="' + index + '">Remove</button>'
                );
              $selectedContainer.append(itemEl);
            });
          }

          // Update hidden fields
          $(CONFIG.selectors.selectedEquipmentData).val(JSON.stringify(selectedItems));
          $(CONFIG.selectors.remainingGold).val(goldRemaining);
        }

        /**
         * Shows an error message temporarily.
         *
         * @param {string} message - Error message to display.
         */
        function showError(message) {
          const $errorElement = $(CONFIG.selectors.errorMessage);
          $errorElement.text(message).removeClass(CONFIG.classes.hidden);
          setTimeout(function() {
            $errorElement.addClass(CONFIG.classes.hidden);
          }, CONFIG.errorDisplayDuration);
        }

        /**
         * Finds an equipment item by ID and category.
         *
         * @param {string} itemId - ID of the item.
         * @param {string} category - Category (weapon, armor, gear).
         * @return {Object|null} The found item or null.
         */
        function findEquipmentItem(itemId, category) {
          if (category === 'weapon') {
            return EQUIPMENT.weapons.find(w => w.id === itemId);
          } else if (category === 'armor') {
            return EQUIPMENT.armor.find(a => a.id === itemId);
          } else if (category === 'gear') {
            return EQUIPMENT.gear.find(g => g.id === itemId);
          }
          return null;
        }

        /**
         * Updates gold remaining display.
         */
        function updateGoldDisplay() {
          $(CONFIG.selectors.goldRemaining).text(goldRemaining.toFixed(CONFIG.goldDisplayDecimals));
        }

        /**
         * Adds an item to the selected items list.
         *
         * @param {string} itemId - ID of the item to add.
         * @param {string} category - Category of the item.
         */
        function addItem(itemId, category) {
          const item = findEquipmentItem(itemId, category);
          
          if (!item) {
            console.warn('Item not found:', itemId, category);
            return;
          }

          // Check if affordable
          if (goldRemaining < item.cost) {
            showError(CONFIG.messages.insufficientGold);
            return;
          }

          // Add item to selection
          selectedItems.push({ ...item, category: category });
          goldRemaining = roundToDecimals(goldRemaining - item.cost, CONFIG.goldPrecision);
          
          // Update UI
          updateGoldDisplay();
          updateSelectedItems();
        }

        /**
         * Removes an item from the selected items list.
         *
         * @param {number} index - Index of the item to remove.
         */
        function removeItem(index) {
          if (index < 0 || index >= selectedItems.length) {
            console.warn('Invalid item index:', index);
            return;
          }

          const item = selectedItems[index];
          goldRemaining = roundToDecimals(goldRemaining + item.cost, CONFIG.goldPrecision);
          selectedItems.splice(index, 1);
          
          // Update UI
          updateGoldDisplay();
          updateSelectedItems();
        }

        // Initialize
        populateEquipment();
        updateSelectedItems();

        // Handle add item clicks
        once('add-item-click', CONFIG.selectors.addButton, context).forEach((button) => {
          $(button).on('click', function() {
            const $item = $(this).closest('.' + CONFIG.classes.equipmentItem);
            const itemId = $item.data('id');
            const category = $item.data('category');
            addItem(itemId, category);
          });
        });

        // Handle remove item clicks (delegated)
        $(CONFIG.selectors.selectedItems, context).on('click', CONFIG.selectors.removeButton, function() {
          const index = $(this).data('index');
          removeItem(index);
        });

        /**
         * Handles AJAX error responses.
         *
         * @param {Object} xhr - XMLHttpRequest object.
         * @param {string} status - Status text.
         * @param {string} error - Error message.
         */
        function handleAjaxError(xhr, status, error) {
          const $errorElement = $(CONFIG.selectors.errorMessage);
          $errorElement.text(CONFIG.messages.saveFailed).removeClass(CONFIG.classes.hidden);
          console.error('Save error:', error);
        }

        // Handle form submission with AJAX
        $form.on('submit', function(e) {
          e.preventDefault();

          // No validation - equipment is optional
          
          // Hide error message
          $(CONFIG.selectors.errorMessage).addClass(CONFIG.classes.hidden);

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
                const message = response.message || 'Error saving step.';
                $(CONFIG.selectors.errorMessage).text(message).removeClass(CONFIG.classes.hidden);
              }
            },
            error: handleAjaxError
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);

/**
 * @file
 * Character Creation Step 7: Starting Equipment
 */

(function ($, Drupal, once) {
  'use strict';

  // Starting Equipment Database (Simplified PF2E)
  const equipment = {
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

  Drupal.behaviors.characterStep7 = {
    attach: function (context, settings) {
      once('step7-init', '#step-7-form', context).forEach((element) => {
        const $form = $(element);
        let goldRemaining = 15;
        let selectedItems = [];

        // Populate equipment lists
        function populateEquipment() {
          // Weapons
          const weaponsList = $('#weapons-list', context);
          if (weaponsList.find('.equipment-item').length === 0) {
            weaponsList.empty();
            equipment.weapons.forEach(function(item) {
              const itemEl = $('<div>')
                .addClass('equipment-item')
                .attr('data-id', item.id)
                .attr('data-cost', item.cost)
                .attr('data-category', 'weapon')
                .html(
                  '<div class="item-name">' + item.name + '</div>' +
                  '<div class="item-details">' + item.damage + ' damage</div>' +
                  '<div class="item-cost">' + item.cost + ' gp</div>' +
                  '<button type="button" class="btn-add-item">Add</button>'
                );
              weaponsList.append(itemEl);
            });
          }

          // Armor
          const armorList = $('#armor-list', context);
          if (armorList.find('.equipment-item').length === 0) {
            armorList.empty();
            equipment.armor.forEach(function(item) {
              const itemEl = $('<div>')
                .addClass('equipment-item')
                .attr('data-id', item.id)
                .attr('data-cost', item.cost)
                .attr('data-category', 'armor')
                .html(
                  '<div class="item-name">' + item.name + '</div>' +
                  '<div class="item-details">' + item.ac + ' AC</div>' +
                  '<div class="item-cost">' + item.cost + ' gp</div>' +
                  '<button type="button" class="btn-add-item">Add</button>'
                );
              armorList.append(itemEl);
            });
          }

          // Gear
          const gearList = $('#gear-list', context);
          if (gearList.find('.equipment-item').length === 0) {
            gearList.empty();
            equipment.gear.forEach(function(item) {
              const itemEl = $('<div>')
                .addClass('equipment-item')
                .attr('data-id', item.id)
                .attr('data-cost', item.cost)
                .attr('data-category', 'gear')
                .html(
                  '<div class="item-name">' + item.name + '</div>' +
                  '<div class="item-cost">' + item.cost + ' gp</div>' +
                  '<button type="button" class="btn-add-item">Add</button>'
                );
              gearList.append(itemEl);
            });
          }
        }

        // Update selected items display
        function updateSelectedItems() {
          const selectedContainer = $('#selected-items', context);
          
          if (selectedItems.length === 0) {
            selectedContainer.html('<p class="empty-message">No equipment selected</p>');
          } else {
            selectedContainer.empty();
            selectedItems.forEach(function(item, index) {
              const itemEl = $('<div>')
                .addClass('selected-item')
                .html(
                  '<div class="item-name">' + item.name + '</div>' +
                  '<div class="item-cost">' + item.cost + ' gp</div>' +
                  '<button type="button" class="btn-remove-item" data-index="' + index + '">Remove</button>'
                );
              selectedContainer.append(itemEl);
            });
          }

          // Update hidden field
          $('#selected-equipment-data').val(JSON.stringify(selectedItems));
          $('#remaining-gold').val(goldRemaining);
        }

        // Add item to selection
        function addItem(itemId, category) {
          let item = null;
          
          // Find item in database
          if (category === 'weapon') {
            item = equipment.weapons.find(w => w.id === itemId);
          } else if (category === 'armor') {
            item = equipment.armor.find(a => a.id === itemId);
          } else if (category === 'gear') {
            item = equipment.gear.find(g => g.id === itemId);
          }

          if (!item) return;

          // Check if affordable
          if (goldRemaining < item.cost) {
            $('#error-message').text('Not enough gold!').removeClass('hidden');
            setTimeout(function() {
              $('#error-message').addClass('hidden');
            }, 2000);
            return;
          }

          // Add item
          selectedItems.push({ ...item, category: category });
          goldRemaining = Math.round((goldRemaining - item.cost) * 100) / 100;
          
          // Update UI
          $('#gold-remaining').text(goldRemaining.toFixed(1));
          updateSelectedItems();
        }

        // Remove item from selection
        function removeItem(index) {
          const item = selectedItems[index];
          goldRemaining = Math.round((goldRemaining + item.cost) * 100) / 100;
          selectedItems.splice(index, 1);
          
          // Update UI
          $('#gold-remaining').text(goldRemaining.toFixed(1));
          updateSelectedItems();
        }

        // Initialize
        populateEquipment();
        updateSelectedItems();

        // Handle add item clicks
        once('add-item-click', '.btn-add-item', context).forEach((button) => {
          $(button).on('click', function() {
            const $item = $(this).closest('.equipment-item');
            const itemId = $item.data('id');
            const category = $item.data('category');
            addItem(itemId, category);
          });
        });

        // Handle remove item clicks (delegated)
        $('#selected-items', context).on('click', '.btn-remove-item', function() {
          const index = $(this).data('index');
          removeItem(index);
        });

        // Handle form submission with AJAX
        $form.on('submit', function(e) {
          e.preventDefault();

          // No validation - equipment is optional
          
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

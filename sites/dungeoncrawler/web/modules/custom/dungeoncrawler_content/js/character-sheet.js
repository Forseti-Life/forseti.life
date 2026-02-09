/**
 * @file
 * JavaScript behaviors for character sheet and character list pages.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Toggle raw JSON display on character sheet.
   */
  Drupal.behaviors.dcCharacterSheetJson = {
    attach: function (context) {
      once('dc-json-toggle', '.dc-sheet__json-toggle', context).forEach(function (toggle) {
        toggle.addEventListener('click', function () {
          var targetId = toggle.getAttribute('data-dc-toggle');
          var target = document.getElementById(targetId);
          if (target) {
            var isHidden = target.style.display === 'none';
            target.style.display = isHidden ? 'block' : 'none';
            toggle.classList.toggle('dc-sheet__json-toggle--open', isHidden);
          }
        });
      });
    }
  };

  /**
   * Animate HP bar color based on current/max ratio.
   */
  Drupal.behaviors.dcCharacterHp = {
    attach: function (context) {
      once('dc-hp-color', '.dc-sheet__hp-current', context).forEach(function (el) {
        var current = parseInt(el.textContent, 10);
        var maxEl = el.parentElement.querySelector('.dc-sheet__hp-max');
        if (maxEl) {
          var max = parseInt(maxEl.textContent, 10);
          var ratio = max > 0 ? current / max : 1;
          if (ratio <= 0.25) {
            el.style.color = '#ef4444'; // danger red
          } else if (ratio <= 0.5) {
            el.style.color = '#f59e0b'; // warning gold
          } else {
            el.style.color = '#22c55e'; // healthy green
          }
        }
      });
    }
  };

  /**
   * Card HP coloring on list page.
   */
  Drupal.behaviors.dcCharacterListHp = {
    attach: function (context) {
      once('dc-list-hp', '.dc-character-card__hp', context).forEach(function (el) {
        var parts = el.textContent.split('/');
        if (parts.length === 2) {
          var current = parseInt(parts[0], 10);
          var max = parseInt(parts[1], 10);
          var ratio = max > 0 ? current / max : 1;
          if (ratio <= 0.25) {
            el.style.color = '#ef4444';
          } else if (ratio <= 0.5) {
            el.style.color = '#f59e0b';
          } else {
            el.style.color = '#22c55e';
          }
        }
      });
    }
  };

})(Drupal, once);

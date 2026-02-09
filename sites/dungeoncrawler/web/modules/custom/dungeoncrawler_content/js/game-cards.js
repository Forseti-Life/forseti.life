/**
 * @file
 * JavaScript behaviors for game content cards.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Attach game card behaviors.
   */
  Drupal.behaviors.dungeonCrawlerGameCards = {
    attach: function (context) {
      // Add rarity glow effect on hover for legendary items.
      $(context).find('.item-card--legendary').once('dc-legendary').each(function () {
        $(this).on('mouseenter', function () {
          $(this).addClass('legendary-glow');
        }).on('mouseleave', function () {
          $(this).removeClass('legendary-glow');
        });
      });
    }
  };

})(jQuery, Drupal);

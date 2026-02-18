/**
 * @file
 * JavaScript behaviors for game content cards.
 *
 * Provides interactive enhancements for item cards, including:
 * - Legendary item hover effects with enhanced glow
 * - Rarity-based visual feedback
 *
 * This script works in conjunction with:
 * - css/game-cards.css (base card styles and rarity colors)
 * - templates/item-card.html.twig (card markup structure)
 * - Database: dungeoncrawler_content_registry.rarity field
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Attach game card behaviors.
   *
   * Adds enhanced visual effects to game content cards, particularly
   * legendary items which receive an intensified glow effect on hover.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches hover effects to legendary item cards.
   */
  Drupal.behaviors.dungeonCrawlerGameCards = {
    attach: function (context) {
      // Add enhanced rarity glow effect on hover for legendary items.
      // The .legendary-glow class is defined in css/game-cards.css
      // and provides an intensified golden glow effect.
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

/**
 * Keith AI Chat Prompt Enhancement
 * Adds interactive effects to the AI chat button on Keith AI character page
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Enhanced Keith AI Chat Prompt behavior
   */
  Drupal.behaviors.keithAiChatPrompt = {
    attach: function (context, settings) {
      $(once('keith-ai-chat-enhanced', '.keith-ai-chat-prompt .ai-chat-button', context)).each(function() {
        const $button = $(this);
        const $container = $button.closest('.ai-chat-container');
        
        // Add typing effect to description on hover
        $container.on('mouseenter', function() {
          const $description = $(this).find('.chat-prompt-description p');
          if (!$description.hasClass('typing-active')) {
            $description.addClass('typing-active');
            typeWriter($description[0], $description.text(), 50);
          }
        });
        
        // Add click tracking and visual feedback
        $button.on('click', function(e) {
          // Add visual feedback
          $button.addClass('activating');
          $button.find('.button-status').text('CONNECTING...');
          
          // Visual effect
          setTimeout(function() {
            $button.find('.button-status').text('ESTABLISHED');
            $button.removeClass('activating').addClass('connected');
          }, 500);
          
          // Track engagement (if analytics available)
          if (typeof gtag !== 'undefined') {
            gtag('event', 'keith_ai_chat_clicked', {
              'event_category': 'engagement',
              'event_label': 'character_page_to_chat'
            });
          }
        });
        
        // Add random glitch effect
        setInterval(function() {
          if (Math.random() < 0.1) { // 10% chance every interval
            $container.addClass('glitch-effect');
            setTimeout(function() {
              $container.removeClass('glitch-effect');
            }, 200);
          }
        }, 3000);
      });
    }
  };
  
  /**
   * Typewriter effect function
   */
  function typeWriter(element, text, speed) {
    element.innerHTML = '';
    let i = 0;
    
    function type() {
      if (i < text.length) {
        element.innerHTML += text.charAt(i);
        i++;
        setTimeout(type, speed);
      }
    }
    
    type();
  }

})(jQuery, Drupal);
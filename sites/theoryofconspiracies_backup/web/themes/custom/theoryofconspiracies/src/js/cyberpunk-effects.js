/**
 * Cyberpunk Theme JavaScript Enhancements
 * Interactive effects and animations for the cyberpunk theme
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Initialize cyberpunk effects
   */
  Drupal.behaviors.cyberpunkEffects = {
    attach: function (context, settings) {
      
      // Add glitch effect to specific elements
      $(once('glitch-effect', '.cyber-title, h1', context)).each(function() {
        const $element = $(this);
        const text = $element.text();
        $element.attr('data-text', text);
        
        // Random glitch effect
        setInterval(function() {
          if (Math.random() > 0.95) {
            $element.addClass('cyber-glitch');
            setTimeout(function() {
              $element.removeClass('cyber-glitch');
            }, 200);
          }
        }, 100);
      });

      // Matrix rain effect
      if ($('.matrix-bg', context).length) {
        initMatrixRain(context);
      }

      // Typing effect for terminal-style text
      $(once('typing-effect', '.terminal-text', context)).each(function() {
        const $element = $(this);
        const text = $element.text();
        $element.text('');
        
        let i = 0;
        const typeInterval = setInterval(function() {
          if (i < text.length) {
            $element.text($element.text() + text.charAt(i));
            i++;
          } else {
            clearInterval(typeInterval);
          }
        }, 50);
      });

      // Hover effects for cyber panels
      $(once('hover-effects', '.cyber-panel', context)).hover(
        function() {
          $(this).addClass('cyber-glow');
        },
        function() {
          $(this).removeClass('cyber-glow');
        }
      );

      // Scan line animation on scroll
      initScanLines(context);

      // Cyberpunk button effects
      $(once('button-effects', '.btn-cyber', context)).on('click', function(e) {
        const $button = $(this);
        const rect = this.getBoundingClientRect();
        const ripple = $('<span class="ripple"></span>');
        
        ripple.css({
          left: e.clientX - rect.left,
          top: e.clientY - rect.top
        });
        
        $button.append(ripple);
        
        setTimeout(function() {
          ripple.remove();
        }, 600);
      });

      // Form field focus effects
      $(once('form-effects', '.form-control', context)).on('focus blur', function(e) {
        const $field = $(this);
        const $group = $field.closest('.form-group');
        
        if (e.type === 'focus') {
          $group.addClass('focused');
        } else {
          $group.removeClass('focused');
        }
      });

      // Navbar scroll effects
      initNavbarEffects(context);
    }
  };

  /**
   * Initialize matrix rain background effect
   */
  function initMatrixRain(context) {
    const canvas = $('<canvas class="matrix-canvas"></canvas>');
    $('.matrix-bg', context).append(canvas);
    
    const ctx = canvas[0].getContext('2d');
    const characters = '01アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲン';
    const fontSize = 14;
    const columns = Math.floor(window.innerWidth / fontSize);
    const drops = [];
    
    // Initialize drops
    for (let i = 0; i < columns; i++) {
      drops[i] = 1;
    }
    
    canvas[0].width = window.innerWidth;
    canvas[0].height = window.innerHeight;
    
    function draw() {
      ctx.fillStyle = 'rgba(10, 10, 10, 0.05)';
      ctx.fillRect(0, 0, canvas[0].width, canvas[0].height);
      
      ctx.fillStyle = '#00ff41';
      ctx.font = fontSize + 'px monospace';
      
      for (let i = 0; i < drops.length; i++) {
        const text = characters.charAt(Math.floor(Math.random() * characters.length));
        ctx.fillText(text, i * fontSize, drops[i] * fontSize);
        
        if (drops[i] * fontSize > canvas[0].height && Math.random() > 0.975) {
          drops[i] = 0;
        }
        drops[i]++;
      }
    }
    
    setInterval(draw, 35);
  }

  /**
   * Initialize scan line effects
   */
  function initScanLines(context) {
    $(window).on('scroll', function() {
      const scrollTop = $(window).scrollTop();
      const windowHeight = $(window).height();
      
      $('.cyber-panel, .card', context).each(function() {
        const $element = $(this);
        const elementTop = $element.offset().top;
        const elementHeight = $element.outerHeight();
        
        if (scrollTop + windowHeight > elementTop && scrollTop < elementTop + elementHeight) {
          if (!$element.hasClass('scan-lines')) {
            $element.addClass('scan-lines');
          }
        }
      });
    });
  }

  /**
   * Initialize navbar scroll effects
   */
  function initNavbarEffects(context) {
    let lastScrollTop = 0;
    
    $(window).on('scroll', function() {
      const scrollTop = $(window).scrollTop();
      const $navbar = $('.navbar', context);
      
      if (scrollTop > 100) {
        $navbar.addClass('scrolled');
      } else {
        $navbar.removeClass('scrolled');
      }
      
      // Hide/show navbar on scroll
      if (scrollTop > lastScrollTop && scrollTop > 200) {
        $navbar.addClass('navbar-hidden');
      } else {
        $navbar.removeClass('navbar-hidden');
      }
      
      lastScrollTop = scrollTop;
    });
  }

  /**
   * Cyberpunk text reveal animation
   */
  Drupal.behaviors.textReveal = {
    attach: function (context, settings) {
      const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
          if (entry.isIntersecting) {
            const $element = $(entry.target);
            const text = $element.text();
            const chars = text.split('');
            
            $element.empty();
            
            chars.forEach(function(char, index) {
              const $span = $('<span>').text(char === ' ' ? '\u00A0' : char);
              $span.css({
                opacity: 0,
                transform: 'translateY(20px)',
                transition: 'all 0.1s ease',
                transitionDelay: (index * 20) + 'ms'
              });
              $element.append($span);
              
              setTimeout(function() {
                $span.css({
                  opacity: 1,
                  transform: 'translateY(0)'
                });
              }, index * 20);
            });
          }
        });
      });
      
      $(once('text-reveal', '.reveal-text', context)).each(function() {
        observer.observe(this);
      });
    }
  };

  /**
   * Cyberpunk loading states
   */
  Drupal.behaviors.cyberpunkLoading = {
    attach: function (context, settings) {
      // Add loading state to forms on submit
      $(once('loading-state', 'form', context)).on('submit', function() {
        const $form = $(this);
        const $submitButton = $form.find('input[type="submit"], button[type="submit"]');
        
        $submitButton.addClass('loading').prop('disabled', true);
        
        // Add cyber loading text
        const originalText = $submitButton.val() || $submitButton.text();
        $submitButton.val('PROCESSING...').text('PROCESSING...');
        
        // Restore on page reload or form error
        setTimeout(function() {
          $submitButton.removeClass('loading').prop('disabled', false);
          $submitButton.val(originalText).text(originalText);
        }, 10000);
      });
    }
  };

  /**
   * Audio feedback (optional - can be disabled)
   */
  if (typeof Audio !== 'undefined') {
    Drupal.behaviors.cyberpunkAudio = {
      attach: function (context, settings) {
        // Create subtle sound effects (very quiet)
        const clickSound = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmccCT2X1+7RfSoFKn/M7+GORA0XZr3n7aMdFAhNnOH1vGwdCmJtk+2hdR8ILImz45xhGwU5jdLwuGkdCjuN1e/DbCUGM3/N7+GAOwtPnuH1vmceCzOJ0u7PeCkGLoXQ8OWjGRk');
        
        $(once('audio-feedback', '.btn, a, input[type="submit"]', context)).on('click', function() {
          if (clickSound && typeof clickSound.play === 'function') {
            clickSound.volume = 0.1;
            clickSound.play().catch(function() {
              // Ignore audio play errors (user hasn't interacted yet)
            });
          }
        });
      }
    };
  }

})(jQuery, Drupal);
/**
 * Mobile Menu Dropdown Functionality
 * Handles click-to-expand for submenu items on mobile devices
 */

(function () {
  'use strict';

  /**
   * Initialize mobile menu dropdown click handlers
   */
  function initMobileMenuDropdowns() {
    // Get all dropdown toggles in the navbar
    const dropdownToggles = document.querySelectorAll('.navbar-nav .dropdown-toggle');

    dropdownToggles.forEach(toggle => {
      // Add click event listener
      toggle.addEventListener('click', function(e) {
        // Only handle on mobile (< 992px)
        if (window.innerWidth >= 992) {
          return; // Let hover behavior work on desktop
        }

        // Prevent default link behavior
        e.preventDefault();
        e.stopPropagation();

        const parentDropdown = this.closest('.dropdown');
        const dropdownMenu = parentDropdown.querySelector('.dropdown-menu');

        // Close other open dropdowns
        const allDropdowns = document.querySelectorAll('.navbar-nav .dropdown');
        allDropdowns.forEach(dropdown => {
          if (dropdown !== parentDropdown) {
            dropdown.classList.remove('show');
            const menu = dropdown.querySelector('.dropdown-menu');
            if (menu) {
              menu.classList.remove('show');
            }
            const otherToggle = dropdown.querySelector('.dropdown-toggle');
            if (otherToggle) {
              otherToggle.setAttribute('aria-expanded', 'false');
            }
          }
        });

        // Toggle current dropdown
        const isExpanded = parentDropdown.classList.contains('show');
        
        if (isExpanded) {
          // Close
          parentDropdown.classList.remove('show');
          dropdownMenu.classList.remove('show');
          this.setAttribute('aria-expanded', 'false');
        } else {
          // Open
          parentDropdown.classList.add('show');
          dropdownMenu.classList.add('show');
          this.setAttribute('aria-expanded', 'true');
        }
      });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
      if (window.innerWidth >= 992) return;

      const isDropdownToggle = e.target.closest('.dropdown-toggle');
      const isInsideDropdown = e.target.closest('.dropdown-menu');
      
      if (!isDropdownToggle && !isInsideDropdown) {
        const allDropdowns = document.querySelectorAll('.navbar-nav .dropdown');
        allDropdowns.forEach(dropdown => {
          dropdown.classList.remove('show');
          const menu = dropdown.querySelector('.dropdown-menu');
          if (menu) {
            menu.classList.remove('show');
          }
          const toggle = dropdown.querySelector('.dropdown-toggle');
          if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
          }
        });
      }
    });

    // Close dropdowns when navbar collapses
    const navbarCollapse = document.querySelector('.navbar-collapse');
    if (navbarCollapse) {
      navbarCollapse.addEventListener('hidden.bs.collapse', function() {
        const allDropdowns = document.querySelectorAll('.navbar-nav .dropdown');
        allDropdowns.forEach(dropdown => {
          dropdown.classList.remove('show');
          const menu = dropdown.querySelector('.dropdown-menu');
          if (menu) {
            menu.classList.remove('show');
          }
          const toggle = dropdown.querySelector('.dropdown-toggle');
          if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
          }
        });
      });
    }
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMobileMenuDropdowns);
  } else {
    initMobileMenuDropdowns();
  }

})();

/**
 * @file
 * JavaScript for Job Search Results page - handles pagination and interactions.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Behavior for job search results pagination.
   */
  Drupal.behaviors.jobSearchResultsPagination = {
    attach: function (context, settings) {
      // Handle pagination button clicks
      once('pagination-handler', '.btn-pagination', context).forEach(function (button) {
        button.addEventListener('click', function (e) {
          e.preventDefault();
          
          const page = this.dataset.page;
          const token = this.dataset.token;
          
          // Get current URL and update parameters
          const url = new URL(window.location.href);
          url.searchParams.set('page', page);
          
          // Add next_page_token if it exists
          if (token) {
            url.searchParams.set('next_page_token', token);
          } else {
            // Remove token param if going to previous page
            url.searchParams.delete('next_page_token');
          }
          
          // Show loading indicator
          const resultsContainer = document.querySelector('.job-results-list');
          if (resultsContainer) {
            resultsContainer.style.opacity = '0.5';
            resultsContainer.style.pointerEvents = 'none';
          }
          
          // Navigate to new page
          window.location.href = url.toString();
        });
      });

      // Scroll to top on page load if pagination was used
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('page') && parseInt(urlParams.get('page')) > 1) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    }
  };

  /**
   * Behavior for job save buttons.
   */
  Drupal.behaviors.jobSearchResultsSave = {
    attach: function (context, settings) {
      // Handle save job button clicks
      once('save-job-handler', '.btn-save-job', context).forEach(function (button) {
        button.addEventListener('click', function (e) {
          // Let the link work normally, but could add AJAX functionality here
          const jobId = this.href.split('job_id=')[1];
          if (jobId) {
            console.log('Saving job:', jobId);
          }
        });
      });
    }
  };

})(Drupal, once);

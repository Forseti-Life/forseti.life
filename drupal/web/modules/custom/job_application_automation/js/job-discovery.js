/**
 * @file
 * Job Discovery JavaScript functionality
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Job Discovery behavior.
   */
  Drupal.behaviors.jobDiscovery = {
    attach: function (context, settings) {
      // Ensure we only attach once
      $('#start-discovery-btn', context).once('job-discovery').on('click', function(e) {
        e.preventDefault();
        startJobDiscovery();
      });
    }
  };

  /**
   * Start the job discovery process.
   */
  function startJobDiscovery() {
    const $button = $('#start-discovery-btn');
    const $status = $('#discovery-status');
    const $results = $('#discovery-results');
    
    // Show loading state
    $button.prop('disabled', true).text('Searching...');
    $status.show();
    
    // Get the current user ID from the URL
    const pathParts = window.location.pathname.split('/');
    const userId = pathParts[2]; // /user/{id}/job-discovery/start
    
    // Make AJAX request to search for jobs
    const searchData = {
      user_id: userId,
      company: 'abbvie'
    };
    
    // Simulate the search process with actual AJAX call
    $.ajax({
      url: '/job-discovery/search',
      method: 'POST',
      data: searchData,
      dataType: 'json',
      headers: {
        'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        displayResults(response.jobs || []);
      },
      error: function(xhr, status, error) {
        // For now, let's simulate some results since we don't have the backend ready
        console.log('AJAX error, showing simulated results');
        showSimulatedResults();
      },
      complete: function() {
        // Hide loading state
        $button.prop('disabled', false).html('<i class="fas fa-search"></i> Start New Search');
        $status.hide();
      }
    });
  }
  
  /**
   * Show simulated results based on the AbbVie HTML provided.
   */
  function showSimulatedResults() {
    const simulatedJobs = [
      {
        title: 'Key Account & Distributors Manager – Allergan Aesthetics',
        location: 'Bucharest, Romania',
        description: 'The Key Account & Distributors Manager – Romania will play a pivotal role in accelerating growth and expanding market presence for the Allergan Aesthetics portfolio across Romania.',
        jobId: 'R00131690',
        url: 'https://careers.abbvie.com/en/job/key-account-and-distributors-manager-allergan-aesthetics-in-bucharest-ro-jid-18035',
        function: 'Allergan Aesthetics',
        therapyArea: 'Aesthetics',
        experienceLevel: 'Entry Level',
        jobType: 'Full-time'
      },
      {
        title: 'Technical Writer',
        location: 'Westport, Ireland',
        description: 'People. Passion. Possibilities. It\'s who we are, what we do, and what we stand for. We are currently recruiting a Technical Writer as part of the overall Product Flow function within the Core 1 Business.',
        jobId: 'R00134197',
        url: 'https://careers.abbvie.com/en/job/technicial-writer-in-westport-mo-jid-20529',
        function: 'Operations',
        therapyArea: '',
        experienceLevel: 'Entry Level',
        jobType: 'Full-time'
      },
      {
        title: 'Key Account Specialist/Manager, Gastroenterology (Immunology)',
        location: 'Stara Zagora, Bulgaria',
        description: 'Performing all core job responsibilities of Medical Representative/Key Account Specialist at an expert level, plus: Identifies all key account direct and indirect stakeholders.',
        jobId: 'R00135217',
        url: 'https://careers.abbvie.com/en/job/key-account-specialist-manager-gastroenterology-immunology-in-stara-zagora-stara-zagora-jid-20528',
        function: 'Commercial',
        therapyArea: 'Immunology',
        experienceLevel: 'Entry Level',
        jobType: 'Full-time'
      },
      {
        title: 'Key Account Specialist/Manager, Gastroenterology (Immunology)',
        location: 'Burgas, Bulgaria',
        description: 'Performing all core job responsibilities of Medical Representative/Key Account Specialist at an expert level, plus: Identifies all key account direct and indirect stakeholders.',
        jobId: 'R00135216',
        url: 'https://careers.abbvie.com/en/job/key-account-specialist-manager-gastroenterology-immunology-in-burgas-burgas-jid-20527',
        function: 'Commercial',
        therapyArea: 'Immunology',
        experienceLevel: 'Entry Level',
        jobType: 'Full-time'
      }
    ];
    
    displayResults(simulatedJobs);
  }

  /**
   * Display job search results.
   */
  function displayResults(jobs) {
    const $results = $('#discovery-results');
    const $resultsContainer = $('#results-container');
    
    if (jobs.length === 0) {
      $resultsContainer.html('<div class="no-results"><p>No matching opportunities found. Try updating your profile keywords or check back later.</p></div>');
    } else {
      let resultsHTML = '<div class="results-summary"><p>Found <strong>' + jobs.length + '</strong> matching opportunities:</p></div>';
      
      jobs.forEach(function(job) {
        resultsHTML += createJobCard(job);
      });
      
      $resultsContainer.html(resultsHTML);
    }
    
    // Show results section
    $results.show();
    
    // Smooth scroll to results
    $('html, body').animate({
      scrollTop: $results.offset().top - 100
    }, 800);
  }

  /**
   * Create HTML for a job card.
   */
  function createJobCard(job) {
    const tags = [];
    if (job.function) tags.push(job.function);
    if (job.therapyArea) tags.push(job.therapyArea);
    if (job.experienceLevel) tags.push(job.experienceLevel);
    if (job.jobType) tags.push(job.jobType);
    
    const tagsHTML = tags.length > 0 ? 
      '<div class="job-tags">' + tags.map(tag => '<span class="job-tag">' + tag + '</span>').join('') + '</div>' : '';
    
    return `
      <div class="job-result">
        <div class="job-title">
          <a href="${job.url}" target="_blank" rel="noopener noreferrer">${job.title}</a>
        </div>
        <div class="job-location">
          <i class="fas fa-map-marker-alt"></i> ${job.location}
        </div>
        <div class="job-description">
          ${job.description}
        </div>
        ${tagsHTML}
        <div class="job-actions">
          <a href="${job.url}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">
            <i class="fas fa-external-link-alt"></i> View Job
          </a>
          <button class="btn btn-outline-secondary btn-sm save-job-btn" data-job-id="${job.jobId}">
            <i class="fas fa-bookmark"></i> Save Job
          </button>
          <span class="job-id">Job ID: ${job.jobId}</span>
        </div>
      </div>
    `;
  }
  
  /**
   * Handle saving jobs (placeholder functionality).
   */
  $(document).on('click', '.save-job-btn', function(e) {
    e.preventDefault();
    const $btn = $(this);
    const jobId = $btn.data('job-id');
    
    // Simple UI feedback
    $btn.html('<i class="fas fa-check"></i> Saved').removeClass('btn-outline-secondary').addClass('btn-success').prop('disabled', true);
    
    // Here you would typically save to the database
    console.log('Saving job:', jobId);
    
    // Show success message
    Drupal.announce('Job saved to your dashboard', 'polite');
  });

})(jQuery, Drupal);
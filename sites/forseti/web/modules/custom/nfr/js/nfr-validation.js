/**
 * NFR Validation Dashboard JavaScript
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.nfrValidation = {
    attach: function (context, settings) {
      // Test individual route
      once('nfr-validation-test', '.test-btn', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          
          const $btn = $(this);
          const route = $btn.data('route');
          const path = $btn.data('path');
          const uid = $btn.data('uid');
          const user = $btn.data('user');
          const cellId = $btn.closest('.test-cell').find('.test-result').attr('id');
          
          testRoute(route, path, uid, user, cellId, $btn);
        });
      });

      // Test all routes
      once('nfr-validation-all', '#test-all-routes', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          testAllRoutes();
        });
      });

      // Clear results
      once('nfr-validation-clear', '#clear-results', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          clearResults();
        });
      });

      // Questionnaire test buttons
      once('nfr-test-questionnaire', '#test-questionnaire-flow', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          testQuestionnaireFlow();
        });
      });

      once('nfr-verify-database', '#verify-questionnaire-data', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          verifyQuestionnaireDatabase();
        });
      });

      once('nfr-clear-test-data', '#clear-test-data', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          clearTestData();
        });
      });

      // Full enrollment flow test
      once('nfr-test-full-enrollment', '#test-full-enrollment', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          testFullEnrollmentFlow();
        });
      });

      // Max values test
      once('nfr-test-max-values', '#test-max-values', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          testMaxValuesFlow();
        });
      });

      // Min values test
      once('nfr-test-min-values', '#test-min-values', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          testMinValuesFlow();
        });
      });

      // Yes + minimal values test
      once('nfr-test-yes-minimal', '#test-yes-minimal', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          testYesMinimalFlow();
        });
      });

      once('nfr-check-error-logs', '#check-error-logs', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          checkErrorLogs();
        });
      });

      // Test users management
      once('nfr-create-test-users', '#create-test-users', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          createTestUsers();
        });
      });

      once('nfr-submit-all-firefighters', '#submit-all-firefighters', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          submitAllFirefighterQuestionnaires();
        });
      });

      once('nfr-view-fill-rates', '#view-fill-rates', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          window.location.href = '/nfr/validation/fill-rates';
        });
      });

      once('nfr-delete-test-users', '#delete-test-users', context).forEach(function (element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          if (confirm('Are you sure you want to delete all test users? This cannot be undone.')) {
            deleteTestUsers();
          }
        });
      });
    }
  };

  /**
   * Test a single route.
   */
  function testRoute(route, path, uid, user, cellId, $btn) {
    const $resultDiv = $('#' + cellId);
    
    // Show loading
    $btn.prop('disabled', true).text('Testing...');
    $resultDiv.html('<div class="test-loading">⏳</div>');
    
    // Make AJAX request
    $.ajax({
      url: '/nfr/validation/test-route',
      method: 'GET',
      data: {
        route: route,
        path: path,
        uid: uid
      },
      success: function (response) {
        displayResult(response, $resultDiv, $btn);
      },
      error: function (xhr, status, error) {
        $resultDiv.html('<div class="test-result-error">❌ Error: ' + error + '</div>');
        $btn.prop('disabled', false).text('Test');
      }
    });
  }

  /**
   * Display test result.
   */
  function displayResult(response, $resultDiv, $btn) {
    let html = '';
    let icon = '';
    let className = '';
    
    if (response.status_code === 200) {
      icon = '✅';
      className = 'test-result-success';
    } else if (response.status_code === 403) {
      icon = '🚫';
      className = 'test-result-forbidden';
    } else {
      icon = '❌';
      className = 'test-result-error';
    }
    
    html += '<div class="test-result-display ' + className + '">';
    html += '<div class="result-icon">' + icon + '</div>';
    html += '<div class="result-code">' + response.status_code + '</div>';
    html += '<div class="result-text">' + response.status_text + '</div>';
    if (response.error) {
      html += '<div class="result-error">' + response.error + '</div>';
    }
    html += '</div>';
    
    $resultDiv.html(html);
    $btn.prop('disabled', false).text('Retest');
    
    // Update summary
    updateSummary();
  }

  /**
   * Test all routes.
   */
  function testAllRoutes() {
    const $btn = $('#test-all-routes');
    const $allTestBtns = $('.test-btn');
    
    $btn.prop('disabled', true).text('Running All Tests...');
    
    let testCount = 0;
    const totalTests = $allTestBtns.length;
    
    // Test each button sequentially with delay
    $allTestBtns.each(function (index) {
      const $testBtn = $(this);
      
      setTimeout(function () {
        $testBtn.trigger('click');
        testCount++;
        
        // Update progress
        $btn.text('Testing... (' + testCount + '/' + totalTests + ')');
        
        // Re-enable when done
        if (testCount === totalTests) {
          setTimeout(function () {
            $btn.prop('disabled', false).text('🧪 Run All Tests');
            showSummary();
          }, 500);
        }
      }, index * 100); // Stagger tests by 100ms
    });
  }

  /**
   * Clear all results.
   */
  function clearResults() {
    $('.test-result').html('');
    $('.test-btn').prop('disabled', false).text('Test');
    $('#test-summary').hide();
  }

  /**
   * Update summary statistics.
   */
  function updateSummary() {
    const total = $('.test-result-display').length;
    const success = $('.test-result-success').length;
    const forbidden = $('.test-result-forbidden').length;
    const errors = $('.test-result-error').length;
    
    if (total > 0) {
      let html = '<div class="summary-stats">';
      html += '<div class="summary-stat summary-stat-total">';
      html += '<div class="summary-value">' + total + '</div>';
      html += '<div class="summary-label">Tests Run</div>';
      html += '</div>';
      html += '<div class="summary-stat summary-stat-success">';
      html += '<div class="summary-value">' + success + '</div>';
      html += '<div class="summary-label">✅ Success (200)</div>';
      html += '</div>';
      html += '<div class="summary-stat summary-stat-forbidden">';
      html += '<div class="summary-value">' + forbidden + '</div>';
      html += '<div class="summary-label">🚫 Forbidden (403)</div>';
      html += '</div>';
      html += '<div class="summary-stat summary-stat-error">';
      html += '<div class="summary-value">' + errors + '</div>';
      html += '<div class="summary-label">❌ Errors</div>';
      html += '</div>';
      html += '</div>';
      
      $('#summary-content').html(html);
    }
  }

  /**
   * Show summary panel.
   */
  function showSummary() {
    updateSummary();
    $('#test-summary').slideDown();
  }

  /**
   * Test questionnaire data flow.
   */
  function testQuestionnaireFlow() {
    const $btn = $('#test-questionnaire-flow');
    const $results = $('#questionnaire-test-results');
    
    $btn.prop('disabled', true).text('🧪 Running Test...');
    $results.html('<div class="alert alert-info">⏳ Running questionnaire test...</div>');
    
    $.ajax({
      url: '/nfr/validation/test-questionnaire',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        displayQuestionnaireResults(response, $results);
        $btn.prop('disabled', false).text('📝 Run Questionnaire Test');
      },
      error: function (xhr, status, error) {
        $results.html('<div class="alert alert-danger">❌ Error: ' + error + '</div>');
        $btn.prop('disabled', false).text('📝 Run Questionnaire Test');
      }
    });
  }

  /**
   * Display questionnaire test results.
   */
  function displayQuestionnaireResults(response, $results) {
    let html = '';
    
    if (response.success) {
      html += '<div class="alert alert-success">';
      html += '<h4>✅ Questionnaire Test Successful</h4>';
      html += '</div>';
    } else {
      html += '<div class="alert alert-danger">';
      html += '<h4>❌ Questionnaire Test Failed</h4>';
      html += '</div>';
    }
    
    // Display steps
    html += '<div class="test-steps">';
    html += '<h4>Test Steps:</h4>';
    response.steps.forEach(function (step, index) {
      const icon = step.status === 'success' ? '✅' : '❌';
      const className = step.status === 'success' ? 'step-success' : 'step-error';
      
      html += '<div class="test-step ' + className + '">';
      html += '<div class="step-header">';
      html += '<span class="step-icon">' + icon + '</span>';
      html += '<span class="step-number">Step ' + (index + 1) + ':</span> ';
      html += '<strong>' + step.step + '</strong>';
      html += '</div>';
      html += '<div class="step-message">' + step.message + '</div>';
      
      // Show verified fields for verification step
      if (step.verified_fields && Object.keys(step.verified_fields).length > 0) {
        html += '<div class="verified-fields">';
        html += '<h5>Verified Fields:</h5>';
        html += '<ul>';
        Object.keys(step.verified_fields).forEach(function (field) {
          const fieldData = step.verified_fields[field];
          html += '<li><code>' + field + '</code>: ' + fieldData.value + '</li>';
        });
        html += '</ul>';
        html += '</div>';
      }
      
      html += '</div>';
    });
    html += '</div>';
    
    // Display errors if any
    if (response.errors && response.errors.length > 0) {
      html += '<div class="test-errors">';
      html += '<h4>Errors:</h4>';
      html += '<ul>';
      response.errors.forEach(function (error) {
        html += '<li class="text-danger">' + error + '</li>';
      });
      html += '</ul>';
      html += '</div>';
    }
    
    $results.html(html);
  }

  /**
   * Verify questionnaire database.
   */
  function verifyQuestionnaireDatabase() {
    const $btn = $('#verify-questionnaire-data');
    const $results = $('#questionnaire-test-results');
    
    $btn.prop('disabled', true).text('⏳ Verifying...');
    $results.html('<div class="alert alert-info">🔍 Checking database...</div>');
    
    $.ajax({
      url: '/nfr/validation/verify-database',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        displayVerificationResults(response, $results);
        $btn.prop('disabled', false).text('✓ Verify Database');
      },
      error: function (xhr, status, error) {
        $results.html('<div class="alert alert-danger">❌ Error: ' + error + '</div>');
        $btn.prop('disabled', false).text('✓ Verify Database');
      }
    });
  }

  /**
   * Display verification results.
   */
  function displayVerificationResults(response, $results) {
    let html = '';
    
    if (response.success) {
      html += '<div class="alert alert-success">';
      html += '<h4>✅ ' + response.message + '</h4>';
      html += '</div>';
      
      if (response.fields && Object.keys(response.fields).length > 0) {
        html += '<div class="verified-fields-table">';
        html += '<table class="table table-sm">';
        html += '<thead><tr><th>Field</th><th>Status</th><th>Value</th></tr></thead>';
        html += '<tbody>';
        
        Object.keys(response.fields).forEach(function (field) {
          const fieldData = response.fields[field];
          const icon = fieldData.status === 'success' ? '✅' : '⚠️';
          
          html += '<tr>';
          html += '<td><code>' + field + '</code></td>';
          html += '<td>' + icon + '</td>';
          html += '<td>' + fieldData.value + '</td>';
          html += '</tr>';
        });
        
        html += '</tbody></table>';
        html += '</div>';
      }
    } else {
      html += '<div class="alert alert-warning">';
      html += '<h4>⚠️ ' + response.message + '</h4>';
      html += '</div>';
    }
    
    $results.html(html);
  }

  /**
   * Clear test data.
   */
  function clearTestData() {
    if (!confirm('Are you sure you want to clear test questionnaire data?')) {
      return;
    }
    
    const $btn = $('#clear-test-data');
    const $results = $('#questionnaire-test-results');
    
    $btn.prop('disabled', true).text('⏳ Clearing...');
    
    $.ajax({
      url: '/nfr/validation/clear-test-data',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          $results.html('<div class="alert alert-success">🗑️ ' + response.message + '</div>');
        } else {
          $results.html('<div class="alert alert-danger">❌ ' + response.message + '</div>');
        }
        $btn.prop('disabled', false).text('🗑️ Clear Test Data');
      },
      error: function (xhr, status, error) {
        $results.html('<div class="alert alert-danger">❌ Error: ' + error + '</div>');
        $btn.prop('disabled', false).text('🗑️ Clear Test Data');
      }
    });
  }

  /**
   * Test full enrollment flow with random data.
   */
  function testFullEnrollmentFlow() {
    const $btn = $('#test-full-enrollment');
    const $results = $('#enrollment-flow-results');
    
    $btn.prop('disabled', true).text('🎲 Running Test...');
    $results.html('<div class="alert alert-info">⏳ Running complete enrollment flow with random data...</div>');
    
    $.ajax({
      url: '/nfr/validation/test-full-enrollment',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        displayEnrollmentFlowResults(response, $results);
        $btn.prop('disabled', false).text('🎲 Run Full Enrollment Test (Random Data)');
      },
      error: function (xhr, status, error) {
        $results.html('<div class="alert alert-danger">❌ Error: ' + error + '</div>');
        $btn.prop('disabled', false).text('🎲 Run Full Enrollment Test (Random Data)');
      }
    });
  }

  /**
   * Display enrollment flow test results.
   */
  function displayEnrollmentFlowResults(response, $results) {
    let html = '';
    
    if (response.success) {
      html += '<div class="alert alert-success">';
      html += '<h4>✅ Enrollment Flow Test Successful</h4>';
      html += '</div>';
    } else {
      html += '<div class="alert alert-danger">';
      html += '<h4>❌ Enrollment Flow Test Failed</h4>';
      html += '</div>';
    }
    
    // Display steps
    html += '<div class="test-steps">';
    html += '<h4>Test Steps:</h4>';
    response.steps.forEach(function (step, index) {
      const icon = step.status === 'success' ? '✅' : (step.status === 'warning' ? '⚠️' : '❌');
      const className = step.status === 'success' ? 'step-success' : (step.status === 'warning' ? 'step-warning' : 'step-error');
      
      html += '<div class="test-step ' + className + '">';
      html += '<div class="step-header">';
      html += '<span class="step-icon">' + icon + '</span>';
      html += '<span class="step-number">Step ' + (index + 1) + ':</span> ';
      html += '<strong>' + step.step + '</strong>';
      html += '</div>';
      html += '<div class="step-message">' + step.message + '</div>';
      
      // Show error details if present
      if (step.error_count > 0 && step.recent_errors) {
        html += '<div class="error-log-details">';
        html += '<h5>Recent Errors (' + step.error_count + '):</h5>';
        html += '<ul>';
        step.recent_errors.forEach(function (error) {
          html += '<li>';
          html += '<strong>' + error.type + '</strong>: ';
          html += error.message + ' <em>(' + error.time + ')</em>';
          html += '</li>';
        });
        html += '</ul>';
        html += '</div>';
      }
      
      html += '</div>';
    });
    html += '</div>';
    
    // Display errors and warnings
    if (response.errors && response.errors.length > 0) {
      html += '<div class="test-errors">';
      html += '<h4>Errors:</h4>';
      html += '<ul>';
      response.errors.forEach(function (error) {
        html += '<li class="text-danger">' + error + '</li>';
      });
      html += '</ul>';
      html += '</div>';
    }
    
    if (response.warnings && response.warnings.length > 0) {
      html += '<div class="test-warnings">';
      html += '<h4>Warnings:</h4>';
      html += '<ul>';
      response.warnings.forEach(function (warning) {
        html += '<li class="text-warning">' + warning + '</li>';
      });
      html += '</ul>';
      html += '</div>';
    }
    
    $results.html(html);
  }

  /**
   * Test max values enrollment flow.
   */
  function testMaxValuesFlow() {
    const $btn = $('#test-max-values');
    const $results = $('#enrollment-flow-results');
    
    $btn.prop('disabled', true).text('⬆️ Running Test...');
    $results.html('<div class="alert alert-info">⏳ Running enrollment flow with maximum values (Yes to everything)...</div>');
    
    $.ajax({
      url: '/nfr/validation/test-max-values',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        displayEnrollmentFlowResults(response, $results);
        $btn.prop('disabled', false).text('⬆️ Max Values Test (Yes to All)');
      },
      error: function (xhr, status, error) {
        $results.html('<div class="alert alert-danger">❌ Error: ' + error + '</div>');
        $btn.prop('disabled', false).text('⬆️ Max Values Test (Yes to All)');
      }
    });
  }

  /**
   * Test min values enrollment flow.
   */
  function testMinValuesFlow() {
    const $btn = $('#test-min-values');
    const $results = $('#enrollment-flow-results');
    
    $btn.prop('disabled', true).text('⬇️ Running Test...');
    $results.html('<div class="alert alert-info">⏳ Running enrollment flow with minimum values (No to everything)...</div>');
    
    $.ajax({
      url: '/nfr/validation/test-min-values',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        displayEnrollmentFlowResults(response, $results);
        $btn.prop('disabled', false).text('⬇️ Min Values Test (No to All)');
      },
      error: function (xhr, status, error) {
        $results.html('<div class="alert alert-danger">❌ Error: ' + error + '</div>');
        $btn.prop('disabled', false).text('⬇️ Min Values Test (No to All)');
      }
    });
  }

  /**
   * Test yes + minimal values enrollment flow.
   */
  function testYesMinimalFlow() {
    const $btn = $('#test-yes-minimal');
    const $results = $('#enrollment-flow-results');
    
    $btn.prop('disabled', true).text('✔️ Running Test...');
    $results.html('<div class="alert alert-info">⏳ Running enrollment flow with yes answers and minimal values...</div>');
    
    $.ajax({
      url: '/nfr/validation/test-yes-minimal',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        displayEnrollmentFlowResults(response, $results);
        $btn.prop('disabled', false).text('✔️ Yes + Minimal Values Test');
      },
      error: function (xhr, status, error) {
        $results.html('<div class="alert alert-danger">❌ Error: ' + error + '</div>');
        $btn.prop('disabled', false).text('✔️ Yes + Minimal Values Test');
      }
    });
  }

  /**
   * Check error logs.
   */
  function checkErrorLogs() {
    const $btn = $('#check-error-logs');
    const $results = $('#enrollment-flow-results');
    
    $btn.prop('disabled', true).text('⏳ Checking...');
    $results.html('<div class="alert alert-info">🔍 Checking error logs...</div>');
    
    $.ajax({
      url: '/nfr/validation/check-error-logs',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        displayErrorLogResults(response, $results);
        $btn.prop('disabled', false).text('⚠️ Check Error Logs');
      },
      error: function (xhr, status, error) {
        $results.html('<div class="alert alert-danger">❌ Error: ' + error + '</div>');
        $btn.prop('disabled', false).text('⚠️ Check Error Logs');
      }
    });
  }

  /**
   * Display error log results.
   */
  function displayErrorLogResults(response, $results) {
    let html = '';
    
    if (response.has_errors) {
      html += '<div class="alert alert-warning">';
      html += '<h4>⚠️ ' + response.message + '</h4>';
      html += '</div>';
      
      if (response.recent_errors && response.recent_errors.length > 0) {
        html += '<div class="error-log-table">';
        html += '<table class="table table-sm">';
        html += '<thead><tr><th>Type</th><th>Severity</th><th>Message</th><th>Time</th></tr></thead>';
        html += '<tbody>';
        
        response.recent_errors.forEach(function (error) {
          const severityClass = error.severity <= 2 ? 'table-danger' : 'table-warning';
          html += '<tr class="' + severityClass + '">';
          html += '<td><code>' + error.type + '</code></td>';
          html += '<td>' + error.severity + '</td>';
          html += '<td>' + error.message + '</td>';
          html += '<td>' + error.time + '</td>';
          html += '</tr>';
        });
        
        html += '</tbody></table>';
        html += '</div>';
      }
    } else {
      html += '<div class="alert alert-success">';
      html += '<h4>✅ ' + response.message + '</h4>';
      html += '</div>';
    }
    
    $results.html(html);
  }

  /**
   * Create test users.
   */
  function createTestUsers() {
    const $btn = $('#create-test-users');
    const $results = $('#test-users-results');
    
    $btn.prop('disabled', true).text('➕ Creating Users...');
    $results.html('<div class="alert alert-info">⏳ Creating 170 test users (5 each of 4 roles + 150 firefighters)...</div>');
    
    $.ajax({
      url: '/nfr/validation/create-test-users',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        displayTestUsersResults(response, $results, 'create');
        $btn.prop('disabled', false).text('➕ Create Test Users (170 users)');
      },
      error: function (xhr, status, error) {
        $results.html('<div class="alert alert-danger">❌ Error: ' + error + '</div>');
        $btn.prop('disabled', false).text('➕ Create Test Users (170 users)');
      }
    });
  }

  /**
   * Delete test users.
   */
  function deleteTestUsers() {
    const $btn = $('#delete-test-users');
    const $results = $('#test-users-results');
    
    $btn.prop('disabled', true).text('🗑️ Deleting...');
    $results.html('<div class="alert alert-info">⏳ Deleting all test users...</div>');
    
    $.ajax({
      url: '/nfr/validation/delete-test-users',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        displayTestUsersResults(response, $results, 'delete');
        $btn.prop('disabled', false).text('🗑️ Delete All Test Users');
      },
      error: function (xhr, status, error) {
        $results.html('<div class="alert alert-danger">❌ Error: ' + error + '</div>');
        $btn.prop('disabled', false).text('🗑️ Delete All Test Users');
      }
    });
  }

  /**
   * Submit questionnaires for all firefighter users.
   */
  function submitAllFirefighterQuestionnaires() {
    const $btn = $('#submit-all-firefighters');
    const $results = $('#test-users-results');
    
    $btn.prop('disabled', true).text('📋 Submitting...');
    $results.html('<div class="alert alert-info">⏳ Submitting questionnaires for all firefighter users... This may take a few minutes.</div>');
    
    $.ajax({
      url: '/nfr/validation/submit-all-firefighters',
      method: 'GET',
      dataType: 'json',
      timeout: 600000, // 10 minute timeout
      success: function (response) {
        displayFirefighterSubmissionResults(response, $results);
        $btn.prop('disabled', false).text('📋 Submit Questionnaires for All Firefighters');
      },
      error: function (xhr, status, error) {
        $results.html('<div class="alert alert-danger">❌ Error: ' + error + '</div>');
        $btn.prop('disabled', false).text('📋 Submit Questionnaires for All Firefighters');
      }
    });
  }

  /**
   * Delete test users.
   */
  function deleteTestUsers() {
    const $btn = $('#delete-test-users');
    const $results = $('#test-users-results');
    
    $btn.prop('disabled', true).text('🗑️ Deleting...');
    $results.html('<div class="alert alert-info">⏳ Deleting all test users...</div>');
    
    $.ajax({
      url: '/nfr/validation/delete-test-users',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        displayTestUsersResults(response, $results, 'delete');
        $btn.prop('disabled', false).text('🗑️ Delete All Test Users');
      },
      error: function (xhr, status, error) {
        $results.html('<div class="alert alert-danger">❌ Error: ' + error + '</div>');
        $btn.prop('disabled', false).text('🗑️ Delete All Test Users');
      }
    });
  }

  /**
   * Display test users results.
   */
  function displayTestUsersResults(response, $results, action) {
    let html = '';
    
    if (response.success) {
      if (action === 'create') {
        html += '<div class="alert alert-success">';
        html += '<h4>✅ Successfully Created ' + response.total_created + ' Test Users</h4>';
        html += '</div>';
        
        if (response.summary) {
          html += '<div class="user-summary">';
          html += '<h5>Summary by Role:</h5>';
          html += '<ul>';
          html += '<li>NFR Administrators: ' + response.summary.nfr_administrators + '</li>';
          html += '<li>NFR Researchers: ' + response.summary.nfr_researchers + '</li>';
          html += '<li>Fire Department Admins: ' + response.summary.fire_dept_admins + '</li>';
          html += '<li>Firefighters: ' + response.summary.firefighters + '</li>';
          html += '<li><strong>Total: ' + response.summary.total + '</strong></li>';
          html += '</ul>';
          html += '</div>';
        }
        
        // Show first few users as sample
        if (response.users_created && response.users_created.length > 0) {
          html += '<div class="users-sample mt-3">';
          html += '<h5>Sample Users (first 10):</h5>';
          html += '<table class="table table-sm">';
          html += '<thead><tr><th>UID</th><th>Username</th><th>Role</th><th>Email</th></tr></thead>';
          html += '<tbody>';
          
          response.users_created.slice(0, 10).forEach(function (user) {
            html += '<tr>';
            html += '<td>' + user.uid + '</td>';
            html += '<td><code>' + user.username + '</code></td>';
            html += '<td>' + user.role + '</td>';
            html += '<td>' + user.email + '</td>';
            html += '</tr>';
          });
          
          html += '</tbody></table>';
          html += '<p class="text-muted">...and ' + (response.users_created.length - 10) + ' more users</p>';
          html += '</div>';
        }
      } else if (action === 'delete') {
        html += '<div class="alert alert-success">';
        html += '<h4>✅ Successfully Deleted ' + response.users_deleted + ' Test Users</h4>';
        html += '</div>';
      }
    } else {
      html += '<div class="alert alert-danger">';
      html += '<h4>❌ Operation Failed</h4>';
      if (response.errors && response.errors.length > 0) {
        html += '<ul>';
        response.errors.forEach(function (error) {
          html += '<li>' + error + '</li>';
        });
        html += '</ul>';
      }
      html += '</div>';
    }
    
    $results.html(html);
  }

  /**
   * Display firefighter submission results.
   */
  function displayFirefighterSubmissionResults(response, $results) {
    let html = '';
    
    if (response.success) {
      html += '<div class="alert alert-success">';
      html += '<h4>✅ Successfully Submitted All Questionnaires</h4>';
      html += '</div>';
    } else {
      html += '<div class="alert alert-warning">';
      html += '<h4>⚠️ Completed with Some Failures</h4>';
      html += '</div>';
    }
    
    // Summary stats
    html += '<div class="submission-summary mb-3">';
    html += '<h5>Summary:</h5>';
    html += '<ul>';
    html += '<li>Total Firefighters: ' + response.total_firefighters + '</li>';
    html += '<li>✅ Successful: ' + response.successful_submissions + '</li>';
    html += '<li>❌ Failed: ' + response.failed_submissions + '</li>';
    html += '<li>Success Rate: ' + response.success_rate + '%</li>';
    html += '</ul>';
    html += '</div>';
    
    // Show failed submissions if any
    if (response.failed_submissions > 0 && response.user_results) {
      const failures = response.user_results.filter(function(r) { return !r.success; });
      
      if (failures.length > 0) {
        html += '<div class="failed-submissions">';
        html += '<h5>Failed Submissions (' + failures.length + '):</h5>';
        html += '<table class="table table-sm">';
        html += '<thead><tr><th>UID</th><th>Username</th><th>Error</th></tr></thead>';
        html += '<tbody>';
        
        failures.forEach(function (result) {
          html += '<tr>';
          html += '<td>' + result.uid + '</td>';
          html += '<td><code>' + result.username + '</code></td>';
          html += '<td class="text-danger">' + result.error + '</td>';
          html += '</tr>';
        });
        
        html += '</tbody></table>';
        html += '</div>';
      }
    }
    
    // Show sample successful submissions
    if (response.successful_submissions > 0 && response.user_results) {
      const successes = response.user_results.filter(function(r) { return r.success; });
      
      if (successes.length > 0) {
        html += '<div class="successful-submissions mt-3">';
        html += '<h5>Successful Submissions (first 10):</h5>';
        html += '<table class="table table-sm">';
        html += '<thead><tr><th>UID</th><th>Username</th><th>Sections</th></tr></thead>';
        html += '<tbody>';
        
        successes.slice(0, 10).forEach(function (result) {
          html += '<tr class="table-success">';
          html += '<td>' + result.uid + '</td>';
          html += '<td><code>' + result.username + '</code></td>';
          html += '<td>✅ ' + result.sections_completed + ' sections</td>';
          html += '</tr>';
        });
        
        html += '</tbody></table>';
        if (successes.length > 10) {
          html += '<p class="text-muted">...and ' + (successes.length - 10) + ' more successful submissions</p>';
        }
        html += '</div>';
      }
    }
    
    $results.html(html);
  }

})(jQuery, Drupal, once);

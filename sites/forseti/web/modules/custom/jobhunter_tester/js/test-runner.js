/**
 * @file
 * Test runner functionality for Job Hunter testing dashboard.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.jobhunterTestRunner = {
    attach: function (context, settings) {
      $('.test-run-btn', context).once('test-runner').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var testFile = $button.data('test-file');
        var $resultsDiv = $('#test-results');
        var $output = $('#test-output');
        
        // Disable button and show loading
        $button.prop('disabled', true).text('Running...');
        $resultsDiv.show();
        $output.text('Running tests for ' + testFile + '...\n\n');
        
        // Make AJAX request
        $.ajax({
          url: '/jobhunter_testing/run-tests',
          method: 'POST',
          data: {
            test_file: testFile
          },
          success: function(response) {
            if (response.success) {
              $output.html('<span style="color: green; font-weight: bold;">✓ ALL TESTS PASSED</span>\n\n' + response.output);
            } else {
              $output.html('<span style="color: red; font-weight: bold;">✗ TESTS FAILED (Code: ' + response.return_code + ')</span>\n\n' + response.output);
            }
            $button.prop('disabled', false).text('Run Tests');
          },
          error: function(xhr) {
            var errorMsg = 'Error running tests';
            if (xhr.responseJSON && xhr.responseJSON.error) {
              errorMsg = xhr.responseJSON.error;
            }
            $output.html('<span style="color: red; font-weight: bold;">✗ ERROR</span>\n\n' + errorMsg);
            $button.prop('disabled', false).text('Run Tests');
          }
        });
      });
      
      // Add "Run All Tests" button
      if ($('.test-run-all-btn', context).length === 0 && $('.test-run-btn', context).length > 0) {
        var $runAllBtn = $('<button class="button button--primary test-run-all-btn" style="margin: 20px 0;">Run All Tests</button>');
        $runAllBtn.insertBefore($('#test-results'));
        
        $runAllBtn.on('click', function(e) {
          e.preventDefault();
          
          var $testButtons = $('.test-run-btn[data-test-file]');
          var currentIndex = 0;
          
          function runNextTest() {
            if (currentIndex >= $testButtons.length) {
              $('#test-output').append('\n\n<span style="color: blue; font-weight: bold;">✓ ALL TEST SUITES COMPLETED</span>');
              return;
            }
            
            var $btn = $testButtons.eq(currentIndex);
            var testFile = $btn.data('test-file');
            
            $('#test-output').append('\n\n=== Running ' + testFile + ' ===\n');
            
            $.ajax({
              url: '/jobhunter_testing/run-tests',
              method: 'POST',
              data: { test_file: testFile },
              success: function(response) {
                $('#test-output').append(response.output + '\n');
                currentIndex++;
                runNextTest();
              },
              error: function(xhr) {
                $('#test-output').append('ERROR: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error') + '\n');
                currentIndex++;
                runNextTest();
              }
            });
          }
          
          $('#test-results').show();
          $('#test-output').text('Running all test suites...\n\n');
          runNextTest();
        });
      }
    }
  };

})(jQuery, Drupal);

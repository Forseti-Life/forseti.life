/**
 * @file
 * Agent evaluation form handling for the framework page.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.agentEvaluation = {
    attach: function (context, settings) {
      once('agent-evaluation', '#startEvaluationBtn', context).forEach(function (button) {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          submitEvaluation();
        });
      });
      
      // Allow Enter key to submit
      once('agent-evaluation-input', '#agentNameInput', context).forEach(function (input) {
        input.addEventListener('keypress', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            submitEvaluation();
          }
        });
      });

      function submitEvaluation() {
        const agentName = document.getElementById('agentNameInput').value.trim();
        const button = document.getElementById('startEvaluationBtn');
        
        if (!agentName) {
          alert('Please enter an agent name.');
          return;
        }

        // Disable button and show loading state
        button.disabled = true;
        button.textContent = 'Creating Evaluation...';

        // Call the backend API to create the evaluation
        fetch('/api/agent-evaluation/create', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            entity_name: agentName
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Redirect to the entity node page
            window.location.href = data.entity_url;
          } else {
            alert('Error: ' + (data.error || 'Failed to create evaluation.'));
            button.disabled = false;
            button.textContent = 'Start Evaluation';
          }
        })
        .catch(error => {
          console.error('Error creating evaluation:', error);
          alert('An error occurred. Please try again.');
          button.disabled = false;
          button.textContent = 'Start Evaluation';
        });
      }
    }
  };

})(Drupal, once);

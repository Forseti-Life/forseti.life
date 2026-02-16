(function (Drupal, once, drupalSettings) {
  'use strict';

  Drupal.behaviors.dungeoncrawlerDeadValueActions = {
    attach(context) {
      const settings = drupalSettings?.dungeoncrawlerTester || {};
      const endpoint = settings?.routes?.deadClose;
      if (!endpoint) {
        return;
      }

      once('dc-dead-close', '.dc-dead-close-btn', context).forEach((button) => {
        button.addEventListener('click', async () => {
          const prNumber = Number(button.getAttribute('data-pr-number') || '0');
          const issueNumber = Number(button.getAttribute('data-issue-number') || '0');
          if (!prNumber) {
            return;
          }

          button.disabled = true;
          const originalText = button.textContent;
          button.textContent = 'Closing...';

          try {
            const response = await fetch(endpoint, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': settings?.csrfToken || '',
              },
              body: JSON.stringify({
                pr_number: prNumber,
                issue_number: issueNumber,
              }),
            });

            const data = await response.json();
            if (!response.ok || !data?.success) {
              throw new Error(data?.message || 'Failed to close dead-value PR.');
            }

            const card = button.closest('.issue-report-item');
            if (card) {
              card.remove();
            }

            Drupal.announce(data.message || `Closed PR #${prNumber}.`);
          }
          catch (error) {
            button.disabled = false;
            button.textContent = originalText;
            Drupal.announce(error.message || 'Close action failed.');
          }
        });
      });
    },
  };
})(Drupal, once, drupalSettings);

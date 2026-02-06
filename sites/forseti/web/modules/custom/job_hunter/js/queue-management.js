/**
 * Queue Management JavaScript
 * Handles deletion of queue items and files
 */

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.queueManagement = {
    attach: function (context, settings) {
      // Handle queue item deletion
      document.querySelectorAll('.btn-delete-item').forEach(function(button) {
        if (button.classList.contains('processed')) return;
        button.classList.add('processed');
        
        button.addEventListener('click', function(e) {
          e.preventDefault();
          
          const itemElement = this.closest('.queue-item');
          const itemId = itemElement.dataset.itemId;
          const queueName = itemElement.dataset.queueName;
          
          if (!confirm('Are you sure you want to delete this queue item?\n\nThis cannot be undone.')) {
            return;
          }
          
          // Disable button during request
          this.disabled = true;
          this.textContent = '⏳ Deleting...';
          
          fetch('/admin/jobhunter/queue/delete-item', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              item_id: itemId,
              queue_name: queueName
            })
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Fade out and remove the item
              itemElement.style.opacity = '0';
              itemElement.style.transition = 'opacity 0.3s';
              setTimeout(() => {
                itemElement.remove();
                
                // Check if list is now empty
                const remainingItems = document.querySelectorAll('.queue-item');
                if (remainingItems.length === 0) {
                  location.reload(); // Reload to show empty state
                } else {
                  // Update count
                  const countElement = document.querySelector('.list-header strong');
                  if (countElement) {
                    countElement.textContent = remainingItems.length;
                  }
                }
              }, 300);
              
              // Show success message
              showMessage('Queue item deleted successfully', 'success');
            } else {
              this.disabled = false;
              this.textContent = '🗑️ Delete Item';
              showMessage(data.message || 'Failed to delete queue item', 'error');
            }
          })
          .catch(error => {
            this.disabled = false;
            this.textContent = '🗑️ Delete Item';
            showMessage('Error deleting queue item: ' + error.message, 'error');
          });
        });
      });
      
      // Handle file deletion
      document.querySelectorAll('.btn-delete-file').forEach(function(button) {
        if (button.classList.contains('processed')) return;
        button.classList.add('processed');
        
        button.addEventListener('click', function(e) {
          e.preventDefault();
          
          const fileId = this.dataset.fileId;
          
          if (!confirm('Are you sure you want to delete this file?\n\nThis will permanently remove the file from the system and cannot be undone.')) {
            return;
          }
          
          // Disable button during request
          this.disabled = true;
          const originalText = this.textContent;
          this.textContent = '⏳';
          
          fetch('/admin/jobhunter/queue/delete-file', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              file_id: fileId
            })
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Hide the file reference
              const previewItem = this.closest('.preview-item');
              if (previewItem) {
                previewItem.style.opacity = '0.5';
                previewItem.style.textDecoration = 'line-through';
              }
              this.remove();
              
              showMessage(data.message, 'success');
            } else {
              this.disabled = false;
              this.textContent = originalText;
              showMessage(data.message || 'Failed to delete file', 'error');
            }
          })
          .catch(error => {
            this.disabled = false;
            this.textContent = originalText;
            showMessage('Error deleting file: ' + error.message, 'error');
          });
        });
      });
      
      /**
       * Show a temporary message to the user
       */
      function showMessage(message, type) {
        // Remove any existing messages
        const existing = document.querySelector('.queue-management-message');
        if (existing) {
          existing.remove();
        }
        
        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = 'queue-management-message queue-management-message-' + type;
        messageDiv.textContent = message;
        
        // Insert at top of page
        const container = document.querySelector('.queue-management-page');
        if (container) {
          container.insertBefore(messageDiv, container.firstChild);
          
          // Auto-remove after 5 seconds
          setTimeout(() => {
            messageDiv.style.opacity = '0';
            messageDiv.style.transition = 'opacity 0.3s';
            setTimeout(() => messageDiv.remove(), 300);
          }, 5000);
        }
      }
    }
  };

})(Drupal, drupalSettings);

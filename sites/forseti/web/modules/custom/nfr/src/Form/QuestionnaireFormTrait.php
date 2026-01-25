<?php

declare(strict_types=1);

namespace Drupal\nfr\Form;

/**
 * Common functionality for questionnaire section forms.
 */
trait QuestionnaireFormTrait {

  /**
   * Get database connection safely.
   */
  protected function getDatabase() {
    try {
      return $this->database;
    }
    catch (\Throwable $e) {
      // Fallback for AJAX contexts where property might not be initialized
      return \Drupal::database();
    }
  }

  /**
   * Load questionnaire data for a user.
   * Note: This now returns empty array as data is stored in specific columns, not a generic JSON field.
   */
  private function loadData(int $uid): array {
    // Data is now stored in specific database columns and normalized tables
    // Section forms should load data directly from those columns
    // This method is kept for backwards compatibility but returns empty
    return [];
  }

  /**
   * Save questionnaire data for a user.
   * Note: This is deprecated. Data should be saved to specific columns in nfr_questionnaire table.
   */
  private function saveData(int $uid, array $data): void {
    // Data is now stored in specific database columns
    // This method is kept for backwards compatibility but does nothing
    // Section forms should save directly to specific columns
    return;
  }

  /**
   * Build navigation menu for sections.
   */
  private function buildNavigationMenu(int $current_section): array {
    $uid = $this->getCurrentUserId();
    $existing = $this->loadData($uid);
    
    // Get completion status for each section
    $section_completion = $existing['section_completion'] ?? [];
    
    // Count completed sections
    $completed_count = count(array_filter($section_completion));
    
    // Calculate progress percentage
    $progress_percent = ($completed_count / 9) * 100;
    
    $sections = [
      1 => 'Demographics',
      2 => 'Work History',
      3 => 'Exposure Info',
      4 => 'Military Service',
      5 => 'Other Employment',
      6 => 'PPE Practices',
      7 => 'Decontamination',
      8 => 'Health Info',
      9 => 'Lifestyle',
    ];

    // Build process flow stepper
    $stepper_html = '<div class="nfr-process-stepper">';
    $stepper_html .= '<div class="stepper-header">';
    $stepper_html .= '<div class="stepper-title">Enrollment Questionnaire</div>';
    $stepper_html .= '<div class="stepper-progress">Section ' . $current_section . ' of 9 &middot; ' . round($progress_percent) . '% Complete</div>';
    $stepper_html .= '</div>';
    $stepper_html .= '<div class="stepper-steps">';
    
    foreach ($sections as $section_num => $section_name) {
      $step_class = 'stepper-step';
      $is_completed = !empty($section_completion[$section_num]);
      
      if ($is_completed) {
        $step_class .= ' completed';
      }
      elseif ($section_num == $current_section) {
        $step_class .= ' active';
      }
      else {
        $step_class .= ' upcoming';
      }
      
      // Make completed sections and current section clickable
      $is_clickable = ($is_completed || $section_num == $current_section);
      if ($is_clickable) {
        $step_class .= ' clickable';
      }
      
      $stepper_html .= '<div class="' . $step_class . '" data-section="' . $section_num . '">';
      
      if ($is_clickable) {
        $stepper_html .= '<a href="/nfr/questionnaire/section/' . $section_num . '" class="step-link">';
      }
      
      $stepper_html .= '<div class="step-number">';
      if ($is_completed) {
        $stepper_html .= '<svg class="step-check" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
      }
      else {
        $stepper_html .= $section_num;
      }
      $stepper_html .= '</div>';
      $stepper_html .= '<div class="step-label">' . $section_name . '</div>';
      
      if ($is_clickable) {
        $stepper_html .= '</a>';
      }
      
      if ($section_num < 9) {
        $stepper_html .= '<div class="step-connector"></div>';
      }
      $stepper_html .= '</div>';
    }
    
    $stepper_html .= '</div></div>';

    return [
      '#type' => 'markup',
      '#markup' => $stepper_html,
      '#attached' => [
        'library' => [
          'nfr/enrollment',
        ],
      ],
    ];
  }

  /**
   * Get current user ID safely.
   */
  protected function getCurrentUserId(): int {
    try {
      return (int) $this->currentUser->id();
    }
    catch (\Throwable $e) {
      // Fallback for AJAX contexts where property might not be initialized
      return (int) \Drupal::currentUser()->id();
    }
  }

}

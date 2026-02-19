<?php

namespace Drupal\job_hunter\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\User;

/**
 * Service for user profile validation and completeness calculation.
 */
class UserProfileService {
  
  use StringTranslationTrait;

  /**
   * Field weights for completeness calculation.
   *
   * @var array
   */
  const FIELD_WEIGHTS = [
    'field_resume_file' => 20,
    'field_work_authorization' => 15,
    'field_professional_summary' => 10,
    'field_skills_summary' => 10,
    'field_experience_years' => 8,
    'field_education_level' => 8,
    'field_remote_preference' => 5,
    'field_linkedin_url' => 5,
    'field_salary_expectation_min' => 5,
    'field_available_start_date' => 5,
    'field_portfolio_url' => 4,
    'field_github_url' => 3,
    'field_certifications' => 2,
  ];

  /**
   * Calculates user profile completeness percentage.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return int
   *   Profile completeness percentage (0-100).
   */
  public function calculateProfileCompleteness(User $user) {
    // Check user entity fields directly for completeness
    $completed_weight = 0;
    $total_weight = array_sum(self::FIELD_WEIGHTS);

    foreach (self::FIELD_WEIGHTS as $field_name => $weight) {
      if ($this->isFieldCompleted($user, $field_name)) {
        $completed_weight += $weight;
      }
    }

    return round(($completed_weight / $total_weight) * 100);
  }

  /**
   * Checks if a specific field is completed for a user.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param string $field_name
   *   The field name to check.
   *
   * @return bool
   *   TRUE if the field is completed, FALSE otherwise.
   */
  public function isFieldCompleted(User $user, $field_name) {
    if (!$user->hasField($field_name)) {
      return FALSE;
    }

    $field_value = $user->get($field_name);
    
    if ($field_value->isEmpty()) {
      return FALSE;
    }

    // Special handling for different field types
    if ($field_name === 'field_resume_file') {
      // File field - check if file exists
      return !$field_value->isEmpty();
    } elseif (in_array($field_name, ['field_portfolio_url', 'field_linkedin_url', 'field_github_url'])) {
      // URL fields - check if URI is valid
      $uri = $field_value->uri;
      return !empty($uri) && filter_var($uri, FILTER_VALIDATE_URL);
    } else {
      // Regular fields - check if value exists and is not empty
      $value = $field_value->value;
      return !empty($value);
    }
  }

  /**
   * Gets missing required fields for profile recommendations.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param int $limit
   *   Maximum number of recommendations to return.
   *
   * @return array
   *   Array of missing field recommendations.
   */
  public function getMissingFieldRecommendations(User $user, $limit = 5) {
    // Use the new profile-based method to align with field entity type migration
    return $this->getMissingFieldRecommendationsFromProfile($user, $limit);
  }

  /**
   * Updates profile completeness field for a user.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param bool $save
   *   Whether to save the user entity after updating.
   *
   * @return int
   *   The calculated completeness percentage.
   */
  public function updateProfileCompleteness(User $user, $save = TRUE) {
    // Use the new profile-based method to align with field entity type migration
    return $this->updateProfileCompletenessFromProfile($user, $save);
  }

  /**
   * Gets profile completeness status information.
   *
   * @param int $completeness
   *   The completeness percentage.
   *
   * @return array
   *   Array with status information including class, message, and level.
   */
  public function getCompletenessStatus($completeness) {
    if ($completeness >= 70) {
      return [
        'class' => 'complete',
        'level' => 'high',
        'message' => $this->t('Profile Complete'),
        'description' => $this->t('Your profile is ready for job applications.'),
      ];
    } elseif ($completeness >= 40) {
      return [
        'class' => 'partial',
        'level' => 'medium',
        'message' => $this->t('Almost There'),
        'description' => $this->t('Complete a few more fields to reach 70%.'),
      ];
    } else {
      return [
        'class' => 'incomplete',
        'level' => 'low',
        'message' => $this->t('Getting Started'),
        'description' => $this->t('Add more information to improve your profile.'),
      ];
    }
  }

  /**
   * Validates profile data for job application readiness.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   Validation result with status and messages.
   */
  public function validateForJobApplication(User $user) {
    // Use profile-based validation to align with field entity type migration
    return $this->validateForJobApplicationFromProfile($user);
  }

  /**
   * Validates user profile for job application using profile entity data.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   Validation results with ready, errors, warnings, and recommendations.
   */
  public function validateForJobApplicationFromProfile(User $user) {
    $completeness = $this->calculateProfileCompleteness($user);
    $errors = [];
    $warnings = [];
    $recommendations = [];

    // Critical Requirements (Blocking - cannot apply without these)
    if (!$this->isFieldCompleted($user, 'field_resume_file')) {
      $errors[] = $this->t('Resume upload is required - employers need to see your qualifications.');
    }

    if (!$this->isFieldCompleted($user, 'field_work_authorization')) {
      $errors[] = $this->t('Work authorization status is required - employers must verify eligibility.');
    }

    // Contact Information Validation (Critical for employer contact)
    $email = $user->getEmail();
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = $this->t('Valid email address is required for application responses.');
    }

    // Enhanced Data Quality Checks
    if ($this->isFieldCompleted($user, 'field_salary_expectation_min') && 
        $user->hasField('field_salary_expectation_max') && 
        !$user->get('field_salary_expectation_max')->isEmpty()) {
      $min_salary = (int)$user->get('field_salary_expectation_min')->value;
      $max_salary = (int)$user->get('field_salary_expectation_max')->value;
      
      if ($min_salary >= $max_salary) {
        $errors[] = $this->t('Minimum salary must be less than maximum salary.');
      }
      
      if ($min_salary < 20000 || $max_salary > 500000) {
        $warnings[] = $this->t('Salary expectations seem unusual - verify amounts are accurate.');
      }
    }

    // Professional Presence Validation
    if ($this->isFieldCompleted($user, 'field_linkedin_url')) {
      $linkedin_url = $user->get('field_linkedin_url')->uri ?? '';
      if (!preg_match('/linkedin\.com\/in\//i', $linkedin_url)) {
        $warnings[] = $this->t('LinkedIn URL should be a profile link (linkedin.com/in/yourname).');
      }
    }

    if ($this->isFieldCompleted($user, 'field_github_url')) {
      $github_url = $user->get('field_github_url')->uri ?? '';
      if (!preg_match('/github\.com\//i', $github_url)) {
        $warnings[] = $this->t('GitHub URL should be a valid GitHub profile or repository link.');
      }
    }

    // Application Success Factors (High-impact recommendations)
    if (!$this->isFieldCompleted($user, 'field_professional_summary')) {
      $recommendations[] = $this->t('Professional summary significantly improves application success rates.');
    }

    if (!$this->isFieldCompleted($user, 'field_skills_summary')) {
      $recommendations[] = $this->t('Skills summary helps employers match you to relevant positions.');
    }

    if (!$this->isFieldCompleted($user, 'field_linkedin_url')) {
      $recommendations[] = $this->t('LinkedIn profile adds credibility and helps employers learn about you.');
    }

    if (!$this->isFieldCompleted($user, 'field_experience_years')) {
      $warnings[] = $this->t('Years of experience helps employers assess your career level.');
    }

    if (!$this->isFieldCompleted($user, 'field_available_start_date')) {
      $warnings[] = $this->t('Start date availability is commonly requested in applications.');
    }

    // Completeness-based Validation
    if ($completeness < 50) {
      $errors[] = $this->t('Profile must be at least 50% complete for reliable application submissions.');
    } elseif ($completeness < 70) {
      $warnings[] = $this->t('Profile completeness below 70% significantly reduces application success rate.');
    }

    // Application Readiness Score
    $readiness_score = $this->calculateApplicationReadinessScore($user, $completeness, $errors, $warnings);

    return [
      'ready' => empty($errors),
      'completeness' => $completeness,
      'readiness_score' => $readiness_score,
      'errors' => $errors,
      'warnings' => $warnings,
      'recommendations' => $recommendations,
    ];
  }

  /**
   * Gets profile statistics for a user.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   Array of profile statistics.
   */
  public function getProfileStats(User $user) {
    $stats = [];
    
    // Basic completeness
    $stats['completeness'] = $this->calculateProfileCompleteness($user);
    
    // Field counts
    $total_fields = count(self::FIELD_WEIGHTS);
    $completed_fields = 0;
    
    foreach (array_keys(self::FIELD_WEIGHTS) as $field_name) {
      if ($this->isFieldCompleted($user, $field_name)) {
        $completed_fields++;
      }
    }
    
    $stats['completed_fields'] = $completed_fields;
    $stats['total_fields'] = $total_fields;
    
    // Last update
    $stats['last_updated'] = NULL;
    if ($user->hasField('field_last_profile_update') && !$user->get('field_last_profile_update')->isEmpty()) {
      $stats['last_updated'] = $user->get('field_last_profile_update')->value;
    }
    
    // Application readiness
    $validation = $this->validateForJobApplication($user);
    $stats['ready_for_applications'] = $validation['ready'];
    $stats['validation'] = $validation;
    
    return $stats;
  }

  /**
   * Checks if a job seeker field is completed.
   *
   * @param object $jobSeekerData
   *   The job seeker data from database.
   * @param string $field_name
   *   The field name to check.
   *
   * @return bool
   *   TRUE if the field is completed, FALSE otherwise.
   */
  protected function isJobSeekerFieldCompleted($jobSeekerData, $field_name) {
    // Map field names to database column names
    $field_mapping = [
      'field_resume_file' => 'resume_node_id',
      'field_work_authorization' => 'work_authorization',
      'field_professional_summary' => 'professional_summary',
      'field_skills_summary' => 'skills',
      'field_experience_years' => 'experience_years',
      'field_education_level' => 'education_level',
      'field_remote_preference' => 'remote_preference',
      'field_linkedin_url' => 'linkedin_url',
      'field_salary_expectation_min' => 'salary_expectation',
      'field_available_start_date' => 'availability',
      'field_portfolio_url' => 'portfolio_url',
      'field_github_url' => 'github_url',
      'field_certifications' => 'certifications',
    ];
    
    if (!isset($field_mapping[$field_name])) {
      return false;
    }
    
    $db_field = $field_mapping[$field_name];
    $value = $jobSeekerData->$db_field ?? null;
    
    return !empty($value);
  }

  /**
   * Updates profile completeness for job application readiness.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param bool $save
   *   Whether to save the user entity.
   *
   * @return int
   *   Updated completeness percentage.
   */
  protected function updateProfileCompletenessFromProfile(User $user, $save = TRUE) {
    $completeness = $this->calculateProfileCompleteness($user);
    
    // Store in field if available
    if ($user->hasField('field_profile_completeness')) {
      $user->set('field_profile_completeness', $completeness);
    }
    
    if ($save) {
      $user->save();
    }
    
    return $completeness;
  }

  /**
   * Gets missing field recommendations for profile.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param int $limit
   *   Maximum recommendations to return.
   *
   * @return array
   *   Array of missing field names with weights.
   */
  protected function getMissingFieldRecommendationsFromProfile(User $user, $limit = 5) {
    $recommendations = [];
    
    foreach (self::FIELD_WEIGHTS as $field_name => $weight) {
      if (!$this->isFieldCompleted($user, $field_name)) {
        $recommendations[] = [
          'field' => $field_name,
          'weight' => $weight,
          'impact' => $this->getFieldImpactDescription($field_name),
        ];
      }
    }
    
    // Sort by weight descending
    usort($recommendations, function($a, $b) {
      return $b['weight'] <=> $a['weight'];
    });
    
    return array_slice($recommendations, 0, $limit);
  }

  /**
   * Gets description of field impact on application success.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   Impact description.
   */
  protected function getFieldImpactDescription($field_name) {
    $descriptions = [
      'field_resume_file' => 'Resume is required by all employers',
      'field_work_authorization' => 'Work authorization status is critical for hiring',
      'field_professional_summary' => 'Summary helps employers quickly understand your value',
      'field_skills_summary' => 'Skills improve keyword matching in applications',
      'field_experience_years' => 'Experience helps employers assess seniority level',
      'field_education_level' => 'Education verification is often required',
      'field_linkedin_url' => 'LinkedIn profile adds credibility and background verification',
      'field_salary_expectation_min' => 'Salary expectations help filter suitable roles',
      'field_available_start_date' => 'Start date is commonly requested',
      'field_portfolio_url' => 'Portfolio demonstrates your work quality',
      'field_github_url' => 'GitHub shows technical contributions and code quality',
      'field_certifications' => 'Certifications validate specialized skills',
      'field_remote_preference' => 'Remote preference reduces irrelevant applications',
    ];
    
    return $descriptions[$field_name] ?? 'Improves profile completeness';
  }

  /**
   * Calculates application readiness score.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param int $completeness
   *   Profile completeness percentage.
   * @param array $errors
   *   Validation errors.
   * @param array $warnings
   *   Validation warnings.
   *
   * @return int
   *   Readiness score 0-100.
   */
  protected function calculateApplicationReadinessScore(User $user, $completeness, array $errors, array $warnings) {
    // Base score on completeness
    $score = $completeness;
    
    // Penalize for critical errors
    $score -= (count($errors) * 10);
    
    // Minor penalty for warnings
    $score -= (count($warnings) * 3);
    
    // Bonus for critical fields
    if ($this->isFieldCompleted($user, 'field_resume_file')) {
      if ($completeness >= 50) {
        $score += 5;
      }
    }
    
    if ($this->isFieldCompleted($user, 'field_professional_summary') && 
        $this->isFieldCompleted($user, 'field_skills_summary')) {
      $score += 5;
    }
    
    // Ensure score is in valid range
    return max(0, min(100, $score));
  }

  /**
   * Gets job seeker profile (placeholder for potential profile entity).
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return object|null
   *   Profile object or NULL if not applicable.
   */
  protected function getJobSeekerProfile(User $user) {
    // Currently profiles are stored as user entity fields
    // This method is here for future expansion to profile entities if needed
    return TRUE; // User entity itself is the profile
  }

  /**
   * Checks if a profile field is completed.
   *
   * @param mixed $profile
   *   The profile entity.
   * @param string $field_name
   *   The field name.
   *
   * @return bool
   *   TRUE if field is completed.
   */
  protected function isProfileFieldCompleted($profile, $field_name) {
    // If profile is the user entity
    if ($profile === TRUE) {
      return FALSE; // This shouldn't be called this way
    }
    
    if ($profile instanceof User) {
      return $this->isFieldCompleted($profile, $field_name);
    }
    
    return FALSE;
  }

}
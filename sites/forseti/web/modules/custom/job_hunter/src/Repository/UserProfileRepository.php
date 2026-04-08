<?php

namespace Drupal\job_hunter\Repository;

use Drupal\Core\Database\Connection;

/**
 * Provides database access methods for user profile operations.
 */
class UserProfileRepository {

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * Updates a parsed resume data row.
   *
   * Used by: UserProfileForm::submitForm() — parsed_data_* field handling.
   */
  public function updateParsedResumeData(int $parsedId, string $parsedData): void {
    $this->database->update('jobhunter_resume_parsed_data')
      ->fields(['parsed_data' => $parsedData, 'changed' => time()])
      ->condition('id', $parsedId)
      ->execute();
  }

  /**
   * Returns the consolidated_profile_json row for a user, or FALSE if absent.
   *
   * Used by: UserProfileForm::syncFormFieldsToConsolidatedJson().
   *
   * @return array|false
   *   Associative array with 'consolidated_profile_json' key, or FALSE.
   */
  public function getConsolidatedProfileJsonRow(int $uid): array|false {
    return $this->database->select('jobhunter_job_seeker', 'js')
      ->fields('js', ['consolidated_profile_json'])
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc();
  }

}

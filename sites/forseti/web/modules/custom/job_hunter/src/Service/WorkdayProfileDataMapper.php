<?php

namespace Drupal\job_hunter\Service;

use Drupal\Core\Database\Connection;

/**
 * Builds normalized profile payload data for Workday Playwright automation.
 */
class WorkdayProfileDataMapper {

  protected Connection $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Assemble profile data from the job_seeker table for form filling.
   */
  public function buildProfileData(int $uid): array {
    $data = $this->getDefaultProfileData();

    try {
      $row = $this->loadJobSeekerRow($uid);

      if ($row) {
        $data['full_name'] = (string) ($row['full_name'] ?? '');
        $data['email']     = (string) ($row['contact_email'] ?? '');
        $data['phone']     = (string) ($row['contact_phone'] ?? '');
        $data['city']      = (string) ($row['location_city'] ?? '');
        $data['state']     = (string) ($row['location_state'] ?? '');
        $data['country']   = (string) ($row['country'] ?? '');
        if (!empty($row['eeo_gender'])) {
          $data['eeo_gender'] = (string) $row['eeo_gender'];
        }
        if (!empty($row['eeo_ethnicity'])) {
          $data['eeo_ethnicity'] = (string) $row['eeo_ethnicity'];
        }
        if (!empty($row['eeo_veteran'])) {
          $data['eeo_veteran'] = (string) $row['eeo_veteran'];
        }
        if (!empty($row['eeo_disability'])) {
          $data['disability_status'] = (string) $row['eeo_disability'];
        }
        if (!empty($row['work_authorized_us'])) {
          $data['work_authorized_us'] = (string) $row['work_authorized_us'];
        }
        if (!empty($row['requires_sponsorship'])) {
          $data['requires_sponsorship'] = (string) $row['requires_sponsorship'];
        }
        if (!empty($row['age_18_or_older'])) {
          $data['age_18_or_older'] = (string) $row['age_18_or_older'];
        }

        $json = [];
        if (!empty($row['consolidated_profile_json'])) {
          $decoded = json_decode((string) $row['consolidated_profile_json'], TRUE);
          if (is_array($decoded)) {
            $json = $decoded;
          }
        }

        $contact = is_array($json['contact_info'] ?? NULL) ? $json['contact_info'] : [];
        $location = is_array($contact['location'] ?? NULL) ? $contact['location'] : [];
        $prefs = is_array($json['job_search_preferences'] ?? NULL) ? $json['job_search_preferences'] : [];
        $demographics = is_array($json['demographics'] ?? NULL) ? $json['demographics'] : [];
        $experience = is_array($json['professional_experience'] ?? NULL) ? $json['professional_experience'] : [];

        if ($data['full_name'] === '' && !empty($contact['full_name'])) {
          $data['full_name'] = (string) $contact['full_name'];
        }
        if ($data['email'] === '' && !empty($contact['email'])) {
          $data['email'] = (string) $contact['email'];
        }
        if ($data['phone'] === '' && !empty($contact['phone'])) {
          $data['phone'] = (string) $contact['phone'];
        }
        if ($data['city'] === '' && !empty($location['city'])) {
          $data['city'] = (string) $location['city'];
        }
        if ($data['state'] === '' && !empty($location['state'])) {
          $data['state'] = (string) $location['state'];
        }
        if ($data['country'] === '' && !empty($location['country'])) {
          $data['country'] = (string) $location['country'];
        }

        if ($data['work_authorized_us'] === '' && isset($prefs['us_work_authorized'])) {
          $data['work_authorized_us'] = (string) $prefs['us_work_authorized'];
        }
        if ($data['requires_sponsorship'] === '' && isset($prefs['requires_sponsorship'])) {
          $data['requires_sponsorship'] = (string) $prefs['requires_sponsorship'];
        }
        if ($data['age_18_or_older'] === '' && isset($prefs['age_18_or_older'])) {
          $data['age_18_or_older'] = (string) $prefs['age_18_or_older'];
        }
        if ($data['hear_about_us'] === '' && isset($prefs['hear_about_us'])) {
          $data['hear_about_us'] = (string) $prefs['hear_about_us'];
        }
        if ($data['prior_company_employment'] === '' && isset($prefs['prior_company_employment'])) {
          $data['prior_company_employment'] = (string) $prefs['prior_company_employment'];
        }
        if ($data['prior_company_wwid'] === '' && isset($prefs['prior_company_wwid'])) {
          $data['prior_company_wwid'] = (string) $prefs['prior_company_wwid'];
        }
        if ($data['prior_company_email'] === '' && isset($prefs['prior_company_email'])) {
          $data['prior_company_email'] = (string) $prefs['prior_company_email'];
        }
        if ($data['phone_device_type'] === '' && isset($prefs['phone_device_type'])) {
          $data['phone_device_type'] = (string) $prefs['phone_device_type'];
        }

        if ($data['salary_expectation'] === '') {
          foreach (['salary_expectation', 'salary_change_minimum', 'salary_min', 'expected_salary', 'desired_salary'] as $k) {
            if (!empty($prefs[$k])) {
              $data['salary_expectation'] = (string) $prefs[$k];
              break;
            }
          }
        }

        if ($data['years_experience'] === '') {
          foreach (['years_experience', 'experience_years', 'relevant_years_experience'] as $k) {
            if (!empty($prefs[$k])) {
              $data['years_experience'] = (string) $prefs[$k];
              break;
            }
          }
        }

        if ($data['willing_to_relocate'] === '' && array_key_exists('relocation_willing', $prefs)) {
          $v = $prefs['relocation_willing'];
          $data['willing_to_relocate'] = is_bool($v) ? ($v ? 'Yes' : 'No') : (string) $v;
        }

        if ($data['english_proficiency'] === '') {
          foreach (['english_proficiency', 'language_proficiency_english', 'english_level'] as $k) {
            if (!empty($prefs[$k])) {
              $data['english_proficiency'] = (string) $prefs[$k];
              break;
            }
          }
        }

        if ($data['restrictive_agreement'] === '') {
          foreach (['restrictive_agreement', 'non_compete_agreement', 'agreement_restriction'] as $k) {
            if (!empty($prefs[$k])) {
              $data['restrictive_agreement'] = (string) $prefs[$k];
              break;
            }
          }
        }

        $this->applyPrimaryExperienceData($data, $experience);
        $this->finalizeProfileData($data, $demographics, $contact, $row);
      }
    }
    catch (\Throwable $e) {
      // Non-fatal — continue with defaults.
    }

    return $data;
  }

  private function getDefaultProfileData(): array {
    return [
      'full_name'         => '',
      'first_name'        => '',
      'last_name'         => '',
      'email'             => '',
      'phone'             => '',
      'city'              => '',
      'state'             => '',
      'country'           => '',
      'linkedin'          => '',
      'eeo_gender'        => '',
      'eeo_ethnicity'     => '',
      'eeo_veteran'       => '',
      'disability_status' => '',
      'work_authorized_us'    => '',
      'requires_sponsorship'  => '',
      'age_18_or_older'       => '',
      'hear_about_us'         => '',
      'prior_company_employment' => '',
      'prior_company_wwid'    => '',
      'prior_company_email'   => '',
      'phone_device_type'     => '',
      'experience_job_title'  => '',
      'experience_company'    => '',
      'experience_from'       => '',
      'experience_to'         => '',
      'experience_role_description' => '',
      'salary_expectation'    => '',
      'years_experience'      => '',
      'willing_to_relocate'   => '',
      'english_proficiency'   => '',
      'restrictive_agreement' => '',
    ];
  }

  private function loadJobSeekerRow(int $uid): ?array {
    $row = $this->database->select('jobhunter_job_seeker', 'j')
      ->fields('j', [
        'full_name', 'contact_email', 'contact_phone',
        'location_city', 'location_state', 'country', 'linkedin_url',
        'eeo_gender', 'eeo_ethnicity', 'eeo_veteran', 'eeo_disability',
        'work_authorized_us', 'requires_sponsorship', 'age_18_or_older',
        'consolidated_profile_json',
      ])
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  private function applyPrimaryExperienceData(array &$data, array $experience): void {
    if (empty($experience) || !is_array($experience[0] ?? NULL)) {
      return;
    }

    $exp0 = $experience[0];
    if (empty($data['experience_job_title']) && !empty($exp0['title'])) {
      $data['experience_job_title'] = (string) $exp0['title'];
    }
    if (empty($data['experience_company']) && !empty($exp0['company'])) {
      $data['experience_company'] = (string) $exp0['company'];
    }
    if (empty($data['experience_from']) && !empty($exp0['start_date'])) {
      $data['experience_from'] = (string) $exp0['start_date'];
    }
    if (empty($data['experience_to']) && !empty($exp0['end_date'])) {
      $data['experience_to'] = (string) $exp0['end_date'];
    }
    if (empty($data['experience_role_description'])) {
      foreach (['role_description', 'description', 'summary', 'responsibilities', 'highlights'] as $k) {
        if (!empty($exp0[$k])) {
          $data['experience_role_description'] = (string) $exp0[$k];
          break;
        }
      }
    }
  }

  private function finalizeProfileData(array &$data, array $demographics, array $contact, array $row): void {
    if ($data['eeo_gender'] === '' && isset($demographics['gender'])) {
      $data['eeo_gender'] = (string) $demographics['gender'];
    }
    if ($data['eeo_ethnicity'] === '' && isset($demographics['race_ethnicity'])) {
      $data['eeo_ethnicity'] = (string) $demographics['race_ethnicity'];
    }
    if ($data['eeo_veteran'] === '' && isset($demographics['veteran_status'])) {
      $data['eeo_veteran'] = (string) $demographics['veteran_status'];
    }
    if ($data['disability_status'] === '' && isset($demographics['disability_status'])) {
      $data['disability_status'] = (string) $demographics['disability_status'];
    }

    if ($data['full_name']) {
      $parts = preg_split('/\s+/', trim($data['full_name']));
      $data['first_name'] = $parts[0] ?? '';
      $data['last_name']  = implode(' ', array_slice($parts, 1));
    }

    if (!empty($row['linkedin_url'])) {
      $data['linkedin'] = (string) $row['linkedin_url'];
    }
    elseif (!empty($contact['linkedin'])) {
      $data['linkedin'] = (string) $contact['linkedin'];
    }

    $data['work_authorized_us'] = $this->normalizeYesNo($data['work_authorized_us']);
    $data['requires_sponsorship'] = $this->normalizeYesNo($data['requires_sponsorship']);
    $data['age_18_or_older'] = $this->normalizeYesNo($data['age_18_or_older']);
    $data['prior_company_employment'] = $this->normalizeYesNo($data['prior_company_employment']);
    $data['willing_to_relocate'] = $this->normalizeYesNo($data['willing_to_relocate']);
    $data['restrictive_agreement'] = $this->normalizeYesNo($data['restrictive_agreement']);
    $data['phone_device_type'] = $this->normalizePhoneDeviceType($data['phone_device_type']);
    $data['eeo_gender'] = $this->normalizeGender($data['eeo_gender']);
    $data['eeo_ethnicity'] = $this->normalizeEthnicity($data['eeo_ethnicity']);
    $data['eeo_veteran'] = $this->normalizeVeteran($data['eeo_veteran']);
    $data['disability_status'] = $this->normalizeDisability($data['disability_status']);
  }

  private function normalizeYesNo(string $value): string {
    $v = strtolower(trim($value));
    if (in_array($v, ['yes', 'y', 'true', '1'], TRUE)) {
      return 'Yes';
    }
    if (in_array($v, ['no', 'n', 'false', '0'], TRUE)) {
      return 'No';
    }
    return trim($value);
  }

  private function normalizeGender(string $value): string {
    $v = strtolower(trim($value));
    return match ($v) {
      'male' => 'Male',
      'female' => 'Female',
      'non_binary', 'non-binary' => 'Non-binary',
      'prefer_not_to_say' => 'Prefer not to say',
      default => trim($value),
    };
  }

  private function normalizeEthnicity(string $value): string {
    $v = strtolower(trim($value));
    return match ($v) {
      'american_indian' => 'American Indian or Alaska Native',
      'asian' => 'Asian',
      'black' => 'Black or African American',
      'hispanic' => 'Hispanic or Latino',
      'native_hawaiian' => 'Native Hawaiian or Other Pacific Islander',
      'white' => 'White',
      'two_or_more' => 'Two or More Races',
      'prefer_not_to_say' => 'Prefer not to say',
      default => trim($value),
    };
  }

  private function normalizeVeteran(string $value): string {
    $v = strtolower(trim($value));
    return match ($v) {
      'not_veteran' => 'I am not a protected veteran',
      'veteran' => 'I identify as one or more of the classifications of protected veteran',
      'prefer_not_to_say' => 'Prefer not to say',
      default => trim($value),
    };
  }

  private function normalizeDisability(string $value): string {
    $v = strtolower(trim($value));
    return match ($v) {
      'no_disability' => 'No, I do not have a disability',
      'yes_disability' => 'Yes, I have a disability (or previously had a disability)',
      'prefer_not_to_say' => 'Prefer not to say',
      default => trim($value),
    };
  }

  private function normalizePhoneDeviceType(string $value): string {
    $v = strtolower(trim($value));
    return match ($v) {
      'mobile', 'cell', 'cell phone' => 'Mobile',
      'home', 'home phone' => 'Home',
      'work', 'office', 'work phone' => 'Work',
      'other' => 'Other',
      default => trim($value),
    };
  }

}

<?php

declare(strict_types=1);

namespace Drupal\nfr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for NFR enrollment pages.
 * 
 * Note: User registration is handled by Drupal's standard user registration
 * at /user/register with NFR-specific profile fields added.
 * Consent form is handled by NFRConsentForm.
 * User profile form is handled by NFRUserProfileForm.
 * Enrollment questionnaire is handled by NFRQuestionnaireForm.
 * Review & submit is handled by NFRReviewSubmitForm.
 */
class NFREnrollmentController extends ControllerBase {

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
    );
  }

  /**
   * Welcome page for authenticated users.
   *
   * @return array
   *   Render array.
   */
  public function welcome(): array {
    $uid = $this->currentUser()->id();
    $account = \Drupal\user\Entity\User::load($uid);
    $first_name = $account->get('field_first_name')->value ?? $account->getDisplayName();
    
    // Check enrollment status
    $enrollment_status = $this->getEnrollmentStatus($uid);
    
    return [
      '#theme' => 'nfr_enrollment_page',
      '#page_id' => 'welcome',
      '#content' => [
        '#markup' => $this->buildWelcomeContent($first_name, $enrollment_status),
      ],
      '#attached' => [
        'library' => ['nfr/enrollment'],
      ],
    ];
  }

  /**
   * Get enrollment status for user.
   */
  private function getEnrollmentStatus(int $uid): array {
    $status = [
      'consent' => false,
      'profile' => false,
      'questionnaire' => false,
      'complete' => false,
    ];

    // Check consent
    $consent = $this->database->select('nfr_consent', 'c')
      ->fields('c', ['consent_timestamp'])
      ->condition('uid', $uid)
      ->execute()
      ->fetchField();
    $status['consent'] = (bool) $consent;

    // Check profile
    $profile = $this->database->select('nfr_user_profile', 'p')
      ->fields('p', ['profile_completed'])
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc();
    $status['profile'] = !empty($profile['profile_completed']);

    // Check questionnaire
    $questionnaire = $this->database->select('nfr_questionnaire', 'q')
      ->fields('q', ['questionnaire_completed'])
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc();
    $status['questionnaire'] = !empty($questionnaire['questionnaire_completed']);

    $status['complete'] = $status['consent'] && $status['profile'] && $status['questionnaire'];

    return $status;
  }

  /**
   * Build welcome page content.
   */
  private function buildWelcomeContent(string $first_name, array $status): string {
    $html = '<div class="welcome-page">';
    
    // Header
    $html .= '<div class="welcome-header">';
    $html .= '<h1>' . $this->t('Welcome to the National Firefighter Registry, @name!', ['@name' => htmlspecialchars($first_name)]) . '</h1>';
    $html .= '<p class="welcome-subtitle">' . $this->t('Thank you for your commitment to advancing cancer research for firefighters.') . '</p>';
    $html .= '</div>';

    // Check if already enrolled
    if ($status['complete']) {
      $html .= '<div class="enrollment-complete-notice">';
      $html .= '<div class="notice-icon">✓</div>';
      $html .= '<div class="notice-content">';
      $html .= '<h2>' . $this->t('You\'re All Set!') . '</h2>';
      $html .= '<p>' . $this->t('You have already completed your enrollment in the National Firefighter Registry.') . '</p>';
      $html .= '<a href="/nfr/my-dashboard" class="button button--primary">' . $this->t('Go to My Dashboard') . '</a>';
      $html .= '</div>';
      $html .= '</div>';
    }
    else {
      // Show enrollment process
      $html .= '<div class="enrollment-process">';
      
      $html .= '<div class="before-proceeding">';
      $html .= '<h2>' . $this->t('Before Proceeding') . '</h2>';
      $html .= '<p>' . $this->t('To enroll in the NFR, you will need to complete the (1) consent form, (2) user profile, and (3) questionnaire. If needed, you can log out at any point in this process.') . '</p>';
      $html .= '</div>';

      $html .= '<div class="enrollment-steps">';
      
      // Step 1: Consent
      $consent_status = $status['consent'] ? 'complete' : 'pending';
      $html .= '<div class="enrollment-step step-' . $consent_status . '">';
      $html .= '<div class="step-number">1</div>';
      $html .= '<div class="step-content">';
      $html .= '<h3>' . $this->t('Informed Consent') . '</h3>';
      $html .= '<p>' . $this->t('Review and sign the informed consent form to participate in the NFR research study.') . '</p>';
      if (!$status['consent']) {
        $html .= '<a href="/nfr/consent" class="button button--primary">' . $this->t('Start Consent Form') . '</a>';
      } else {
        $html .= '<div class="step-complete-badge">✓ Complete</div>';
      }
      $html .= '</div>';
      $html .= '</div>';
      
      // Step 2: Profile
      $profile_status = $status['profile'] ? 'complete' : ($status['consent'] ? 'pending' : 'locked');
      $html .= '<div class="enrollment-step step-' . $profile_status . '">';
      $html .= '<div class="step-number">2</div>';
      $html .= '<div class="step-content">';
      $html .= '<h3>' . $this->t('User Profile') . '</h3>';
      $html .= '<p>' . $this->t('Provide your demographic information, contact details, and current fire department information.') . '</p>';
      if ($status['consent'] && !$status['profile']) {
        $html .= '<a href="/nfr/profile" class="button button--primary">' . $this->t('Complete Profile') . '</a>';
      } elseif ($status['profile']) {
        $html .= '<div class="step-complete-badge">✓ Complete</div>';
      } else {
        $html .= '<div class="step-locked">Complete step 1 first</div>';
      }
      $html .= '</div>';
      $html .= '</div>';
      
      // Step 3: Questionnaire
      $questionnaire_status = $status['questionnaire'] ? 'complete' : ($status['profile'] ? 'pending' : 'locked');
      $html .= '<div class="enrollment-step step-' . $questionnaire_status . '">';
      $html .= '<div class="step-number">3</div>';
      $html .= '<div class="step-content">';
      $html .= '<h3>' . $this->t('Enrollment Questionnaire') . '</h3>';
      $html .= '<p>' . $this->t('Complete a comprehensive health and occupational history questionnaire (approximately 30 minutes).') . '</p>';
      if ($status['profile'] && !$status['questionnaire']) {
        $html .= '<a href="/nfr/questionnaire" class="button button--primary">' . $this->t('Start Questionnaire') . '</a>';
      } elseif ($status['questionnaire']) {
        $html .= '<div class="step-complete-badge">✓ Complete</div>';
      } else {
        $html .= '<div class="step-locked">Complete steps 1 & 2 first</div>';
      }
      $html .= '</div>';
      $html .= '</div>';

      $html .= '</div>'; // .enrollment-steps
      $html .= '</div>'; // .enrollment-process

      // Progress summary
      $completed_steps = ($status['consent'] ? 1 : 0) + ($status['profile'] ? 1 : 0) + ($status['questionnaire'] ? 1 : 0);
      $html .= '<div class="progress-summary">';
      $html .= '<div class="progress-bar-container">';
      $html .= '<div class="progress-bar-label">' . $this->t('Enrollment Progress') . '</div>';
      $html .= '<div class="progress-bar">';
      $html .= '<div class="progress-bar-fill" style="width: ' . (($completed_steps / 3) * 100) . '%"></div>';
      $html .= '</div>';
      $html .= '<div class="progress-text">' . $completed_steps . ' ' . $this->t('of 3 steps complete') . '</div>';
      $html .= '</div>';
      $html .= '</div>';
    }

    // Additional information
    $html .= '<div class="welcome-info">';
    $html .= '<div class="info-box">';
    $html .= '<h3>' . $this->t('Need Help?') . '</h3>';
    $html .= '<ul>';
    $html .= '<li><a href="/nfr/faq">' . $this->t('Frequently Asked Questions') . '</a></li>';
    $html .= '<li><a href="/nfr/contact">' . $this->t('Contact the NFR Team') . '</a></li>';
    $html .= '<li><a href="/user/logout">' . $this->t('Log Out') . '</a></li>';
    $html .= '</ul>';
    $html .= '</div>';
    
    $html .= '<div class="info-box">';
    $html .= '<h3>' . $this->t('Privacy & Security') . '</h3>';
    $html .= '<p>' . $this->t('Your information is protected under federal law and will only be used for research purposes. You can withdraw from the study at any time.') . '</p>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '</div>'; // .welcome-page

    return $html;
  }

  /**
   * Enrollment confirmation page.
   *
   * @return array
   *   Render array.
   */
  public function confirmation(): array {
    $uid = $this->currentUser()->id();
    
    // Load participant data
    $profile = $this->database->select('nfr_user_profile', 'p')
      ->fields('p', ['participant_id', 'first_name', 'primary_email', 'created'])
      ->condition('uid', $uid)
      ->execute()
      ->fetchAssoc();

    if (!$profile) {
      $this->messenger()->addError($this->t('Profile not found. Please complete your enrollment.'));
      return new \Symfony\Component\HttpFoundation\RedirectResponse(\Drupal\Core\Url::fromRoute('nfr.consent')->toString());
    }

    $participant_id = $profile['participant_id'] ?? 'Pending';
    $first_name = $profile['first_name'] ?? '';
    $email = $profile['primary_email'] ?? '';
    $enrollment_date = date('F j, Y', $profile['created'] ?? time());

    return [
      '#theme' => 'nfr_enrollment_page',
      '#page_id' => 'confirmation',
      '#content' => [
        '#markup' => $this->buildConfirmationContent($participant_id, $first_name, $email, $enrollment_date),
      ],
      '#attached' => [
        'library' => ['nfr/enrollment'],
      ],
    ];
  }

  /**
   * Build confirmation page content.
   */
  private function buildConfirmationContent(string $participant_id, string $first_name, string $email, string $date): string {
    $dashboard_url = \Drupal\Core\Url::fromRoute('nfr.my_dashboard')->toString();
    
    $html = '<div class="confirmation-page">';
    $html .= '<div class="confirmation-header">';
    $html .= '<div class="success-icon">✓</div>';
    $html .= '<h1>' . $this->t('Thank You for Joining the National Firefighter Registry!') . '</h1>';
    $html .= '</div>';

    $html .= '<div class="confirmation-content">';
    
    $html .= '<div class="participant-id-box">';
    $html .= '<h2>' . $this->t('Your Participant ID') . '</h2>';
    $html .= '<div class="participant-id">' . htmlspecialchars($participant_id) . '</div>';
    $html .= '<p class="save-note">' . $this->t('Please save this ID for your records') . '</p>';
    $html .= '</div>';

    $html .= '<div class="next-steps">';
    $html .= '<h2>' . $this->t('What Happens Next?') . '</h2>';
    $html .= '<ul>';
    $html .= '<li>' . $this->t('We\'ve sent a confirmation email to <strong>@email</strong>', ['@email' => htmlspecialchars($email)]) . '</li>';
    $html .= '<li>' . $this->t('You can access your dashboard anytime to view or update your information') . '</li>';
    $html .= '<li>' . $this->t('We\'ll contact you annually for follow-up surveys') . '</li>';
    $html .= '<li>' . $this->t('If you\'re diagnosed with cancer, you can update your profile at any time') . '</li>';
    $html .= '<li>' . $this->t('Your participation helps protect firefighter health nationwide') . '</li>';
    $html .= '</ul>';
    $html .= '</div>';

    $html .= '<div class="confirmation-actions">';
    $html .= '<a href="' . $dashboard_url . '" class="button button--primary">' . 
      $this->t('Go to My Dashboard') . '</a>';
    $html .= '</div>';

    $html .= '<div class="additional-links">';
    $html .= '<h3>' . $this->t('Additional Resources') . '</h3>';
    $html .= '<ul>';
    $html .= '<li><a href="/nfr/faq">' . $this->t('Frequently Asked Questions') . '</a></li>';
    $html .= '<li><a href="/nfr/contact">' . $this->t('Contact the NFR Team') . '</a></li>';
    $html .= '<li><a href="/nfr">' . $this->t('Learn More About the NFR') . '</a></li>';
    $html .= '</ul>';
    $html .= '</div>';

    $html .= '</div>'; // .confirmation-content
    $html .= '</div>'; // .confirmation-page

    return $html;
  }

}


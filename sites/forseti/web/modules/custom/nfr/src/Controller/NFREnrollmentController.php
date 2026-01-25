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


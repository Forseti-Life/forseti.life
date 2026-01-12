<?php

namespace Drupal\safety_calculator\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Multi-step questionnaire form for safety assessment.
 */
class QuestionnaireStepForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'safety_calculator_questionnaire_step';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $step = 'safe') {
    // Get dimension info
    $dimension = $this->getDimensionInfo($step);
    
    if (!$dimension) {
      $this->messenger()->addError($this->t('Invalid assessment step.'));
      return new RedirectResponse(Url::fromRoute('safety_calculator.questionnaire')->toString());
    }

    // Store current step
    $form_state->set('current_step', $step);

    // Load existing responses from session
    $tempstore = \Drupal::service('tempstore.private')->get('safety_calculator');
    $responses = $tempstore->get('questionnaire_responses') ?? [];

    // Wrapper with progress bar
    $form['#prefix'] = $this->buildProgressBar($step);
    $form['#prefix'] .= '<div class="container my-5"><div class="card card-forseti p-4">';
    $form['#suffix'] = '</div></div>';

    // Dimension header
    $form['header'] = [
      '#markup' => sprintf(
        '<div class="text-center mb-4">
          <img src="%s" alt="%s" class="questionnaire-dimension-icon mb-3">
          <h2>%s</h2>
          <p class="lead">%s</p>
        </div>',
        $dimension['icon'],
        $dimension['name'],
        $dimension['name'],
        $dimension['subtitle']
      ),
    ];

    // Get questions for this dimension
    $questions = $this->getQuestionsForDimension($step);

    foreach ($questions as $key => $question) {
      $form[$key] = [
        '#type' => 'fieldset',
        '#title' => $question['question'],
        '#attributes' => ['class' => ['mb-4']],
      ];

      if (!empty($question['description'])) {
        $form[$key]['description'] = [
          '#markup' => '<p class="text-muted small">' . $question['description'] . '</p>',
        ];
      }

      $form[$key][$key . '_rating'] = [
        '#type' => 'radios',
        '#title' => $this->t('Rating'),
        '#options' => $this->getRatingOptions(),
        '#default_value' => $responses[$key . '_rating'] ?? NULL,
        '#required' => TRUE,
        '#attributes' => ['class' => ['rating-radios']],
      ];

      if ($question['allow_comments']) {
        $form[$key][$key . '_comment'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Additional comments (optional)'),
          '#rows' => 2,
          '#default_value' => $responses[$key . '_comment'] ?? '',
          '#attributes' => ['class' => ['form-control']],
        ];
      }
    }

    // Navigation buttons
    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['d-flex', 'justify-content-between', 'mt-4']],
    ];

    // Previous button (if not first step)
    if ($prev_step = $this->getPreviousStep($step)) {
      $form['actions']['previous'] = [
        '#type' => 'submit',
        '#value' => $this->t('← Previous'),
        '#submit' => ['::submitPrevious'],
        '#limit_validation_errors' => [],
        '#attributes' => ['class' => ['btn', 'btn-secondary']],
      ];
    }
    else {
      $form['actions']['previous'] = [
        '#markup' => '<div></div>',
      ];
    }

    // Save & Continue button
    if ($next_step = $this->getNextStep($step)) {
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save & Continue →'),
        '#attributes' => ['class' => ['btn', 'btn-primary', 'btn-lg']],
      ];
    }
    else {
      // Last step - go to review
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Review & Calculate Score'),
        '#attributes' => ['class' => ['btn', 'btn-success', 'btn-lg']],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('current_step');
    
    // Save responses to session
    $tempstore = \Drupal::service('tempstore.private')->get('safety_calculator');
    $responses = $tempstore->get('questionnaire_responses') ?? [];
    
    // Merge current responses
    foreach ($form_state->getValues() as $key => $value) {
      if (strpos($key, '_rating') !== FALSE || strpos($key, '_comment') !== FALSE) {
        $responses[$key] = $value;
      }
    }
    
    $tempstore->set('questionnaire_responses', $responses);

    // Navigate to next step
    $next_step = $this->getNextStep($step);
    if ($next_step) {
      $form_state->setRedirect('safety_calculator.questionnaire_step', ['step' => $next_step]);
    }
    else {
      // Go to review page
      $form_state->setRedirect('safety_calculator.questionnaire_step', ['step' => 'review']);
    }

    $this->messenger()->addStatus($this->t('Progress saved! Moving to next section.'));
  }

  /**
   * Submit handler for previous button.
   */
  public function submitPrevious(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('current_step');
    $prev_step = $this->getPreviousStep($step);
    
    if ($prev_step) {
      $form_state->setRedirect('safety_calculator.questionnaire_step', ['step' => $prev_step]);
    }
  }

  /**
   * Build progress bar.
   */
  protected function buildProgressBar($current_step) {
    $steps = $this->getAllSteps();
    $current_index = array_search($current_step, $steps);
    $progress = round((($current_index + 1) / count($steps)) * 100);

    return sprintf(
      '<div class="container my-4">
        <div class="progress" style="height: 30px;">
          <div class="progress-bar bg-primary" role="progressbar" style="width: %d%%" aria-valuenow="%d" aria-valuemin="0" aria-valuemax="100">
            <span class="fw-bold">Step %d of %d (%d%%)</span>
          </div>
        </div>
      </div>',
      $progress,
      $progress,
      $current_index + 1,
      count($steps),
      $progress
    );
  }

  /**
   * Get all assessment steps.
   */
  protected function getAllSteps() {
    return ['safe', 'energized', 'connected', 'free', 'capable', 'useful', 'whole'];
  }

  /**
   * Get next step.
   */
  protected function getNextStep($current) {
    $steps = $this->getAllSteps();
    $index = array_search($current, $steps);
    return $index !== FALSE && isset($steps[$index + 1]) ? $steps[$index + 1] : NULL;
  }

  /**
   * Get previous step.
   */
  protected function getPreviousStep($current) {
    $steps = $this->getAllSteps();
    $index = array_search($current, $steps);
    return $index !== FALSE && $index > 0 ? $steps[$index - 1] : NULL;
  }

  /**
   * Get dimension info.
   */
  protected function getDimensionInfo($step) {
    $dimensions = [
      'safe' => [
        'name' => $this->t('Safe'),
        'subtitle' => $this->t('Security & Protection'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_safe.png',
      ],
      'energized' => [
        'name' => $this->t('Energized'),
        'subtitle' => $this->t('Vitality & Basic Needs'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_energized.png',
      ],
      'connected' => [
        'name' => $this->t('Connected'),
        'subtitle' => $this->t('Community & Belonging'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_connected.png',
      ],
      'free' => [
        'name' => $this->t('Free'),
        'subtitle' => $this->t('Autonomy & Rights'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_free.png',
      ],
      'capable' => [
        'name' => $this->t('Capable'),
        'subtitle' => $this->t('Mastery & Development'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_capable.png',
      ],
      'useful' => [
        'name' => $this->t('Useful'),
        'subtitle' => $this->t('Purpose & Contribution'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_useful.png',
      ],
      'whole' => [
        'name' => $this->t('Whole'),
        'subtitle' => $this->t('Holistic Health & Identity'),
        'icon' => '/themes/custom/forseti/images/logos/originals/forseti_whole.png',
      ],
    ];

    return $dimensions[$step] ?? NULL;
  }

  /**
   * Get rating options.
   */
  protected function getRatingOptions() {
    return [
      '1' => '1 - ' . $this->t('Very Poor'),
      '2' => '2',
      '3' => '3',
      '4' => '4',
      '5' => '5',
      '6' => '6',
      '7' => '7',
      '8' => '8',
      '9' => '9',
      '10' => '10 - ' . $this->t('Excellent'),
    ];
  }

  /**
   * Get questions for a dimension.
   */
  protected function getQuestionsForDimension($dimension) {
    $all_questions = [
      'safe' => [
        'police' => [
          'question' => $this->t('Police / Law Enforcement'),
          'description' => $this->t('Availability, response time, and effectiveness of local police services'),
          'allow_comments' => FALSE,
        ],
        'fire' => [
          'question' => $this->t('Fire Department / Fire Rescue'),
          'description' => $this->t('Fire prevention, suppression, and rescue services'),
          'allow_comments' => FALSE,
        ],
        'ems' => [
          'question' => $this->t('Emergency Medical Services (EMS/Ambulance)'),
          'description' => $this->t('Paramedics and emergency medical response'),
          'allow_comments' => FALSE,
        ],
        'dispatch' => [
          'question' => $this->t('Emergency Dispatch / 911 Services'),
          'description' => $this->t('Quality and reliability of emergency call handling'),
          'allow_comments' => FALSE,
        ],
        'emergency_management' => [
          'question' => $this->t('Emergency Management / Disaster Response'),
          'description' => $this->t('Preparedness and response to natural disasters and major emergencies'),
          'allow_comments' => FALSE,
        ],
        'public_health' => [
          'question' => $this->t('Public Health Emergency Response'),
          'description' => $this->t('Disease outbreak response and public health crises'),
          'allow_comments' => FALSE,
        ],
        'poison_control' => [
          'question' => $this->t('Poison Control Centers'),
          'description' => $this->t('Access to poison control information and emergency guidance'),
          'allow_comments' => FALSE,
        ],
        'mental_health_crisis' => [
          'question' => $this->t('Mental Health Crisis Teams'),
          'description' => $this->t('Specialized response for mental health emergencies'),
          'allow_comments' => FALSE,
        ],
        'road_services' => [
          'question' => $this->t('Emergency Road / Highway Services'),
          'description' => $this->t('Road maintenance, accident response, and traffic management'),
          'allow_comments' => FALSE,
        ],
        'public_works' => [
          'question' => $this->t('Public Works Emergency Response'),
          'description' => $this->t('Infrastructure maintenance and emergency repairs'),
          'allow_comments' => FALSE,
        ],
        'utilities' => [
          'question' => $this->t('Utility Emergency Services'),
          'description' => $this->t('Emergency response for water, power, and gas services'),
          'allow_comments' => FALSE,
        ],
        'search_rescue' => [
          'question' => $this->t('Search and Rescue (SAR)'),
          'description' => $this->t('Missing persons, wilderness rescue, and recovery operations'),
          'allow_comments' => FALSE,
        ],
        'hazmat' => [
          'question' => $this->t('Hazardous Materials (HAZMAT) Response'),
          'description' => $this->t('Chemical spills, toxic materials, and environmental hazards'),
          'allow_comments' => FALSE,
        ],
        'bomb_squad' => [
          'question' => $this->t('Bomb Squad / Explosive Ordnance Disposal'),
          'description' => $this->t('Handling suspicious packages and explosive threats'),
          'allow_comments' => FALSE,
        ],
        'coast_guard' => [
          'question' => $this->t('Coast Guard / Maritime Emergency Services'),
          'description' => $this->t('Water rescue and maritime safety (if applicable)'),
          'allow_comments' => FALSE,
        ],
        'emergency_shelters' => [
          'question' => $this->t('Emergency Shelters'),
          'description' => $this->t('Availability of emergency housing during disasters'),
          'allow_comments' => FALSE,
        ],
        'food_water_distribution' => [
          'question' => $this->t('Emergency Food / Water Distribution'),
          'description' => $this->t('Emergency supplies during crises'),
          'allow_comments' => FALSE,
        ],
        'emergency_housing' => [
          'question' => $this->t('Emergency Housing Assistance'),
          'description' => $this->t('Temporary housing support for displaced residents'),
          'allow_comments' => FALSE,
        ],
        'crisis_counseling' => [
          'question' => $this->t('Crisis Counseling Services'),
          'description' => $this->t('Mental health support during emergencies and trauma'),
          'allow_comments' => FALSE,
        ],
      ],
      // Add questions for other dimensions
      'energized' => [
        'housing_quality' => [
          'question' => $this->t('How would you rate the quality and affordability of housing?'),
          'description' => '',
          'allow_comments' => TRUE,
        ],
        'food_access' => [
          'question' => $this->t('How easy is it to access fresh, healthy food?'),
          'description' => $this->t('Grocery stores, farmers markets, etc.'),
          'allow_comments' => FALSE,
        ],
        'employment' => [
          'question' => $this->t('How available are employment opportunities?'),
          'description' => '',
          'allow_comments' => TRUE,
        ],
        'utilities' => [
          'question' => $this->t('How reliable are utilities and infrastructure?'),
          'description' => $this->t('Water, electricity, internet, etc.'),
          'allow_comments' => FALSE,
        ],
        'income_stability' => [
          'question' => $this->t('How financially stable is your household?'),
          'description' => '',
          'allow_comments' => FALSE,
        ],
        'transportation' => [
          'question' => $this->t('How accessible is reliable transportation?'),
          'description' => $this->t('Public transit, road conditions, parking'),
          'allow_comments' => FALSE,
        ],
      ],
      // Placeholder for other dimensions - to be expanded
      'connected' => [],
      'free' => [],
      'capable' => [],
      'useful' => [],
      'whole' => [],
    ];

    return $all_questions[$dimension] ?? [];
  }

}

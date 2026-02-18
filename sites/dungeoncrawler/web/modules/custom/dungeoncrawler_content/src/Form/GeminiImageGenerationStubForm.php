<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\dungeoncrawler_content\Service\GeminiImageGenerationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dashboard form for Gemini image generation integration stubbing.
 */
class GeminiImageGenerationStubForm extends FormBase implements ContainerInjectionInterface {

  /**
   * Gemini stub service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\GeminiImageGenerationService
   */
  protected GeminiImageGenerationService $geminiImageService;

  /**
   * Constructs a GeminiImageGenerationStubForm.
   */
  public function __construct(GeminiImageGenerationService $gemini_image_service) {
    $this->geminiImageService = $gemini_image_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.gemini_image_generator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_content_gemini_image_generation_stub_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Image prompt'),
      '#description' => $this->t('Describe the scene to generate. This is stored only in stub logs for now.'),
      '#required' => TRUE,
      '#rows' => 3,
    ];

    $form['style'] = [
      '#type' => 'select',
      '#title' => $this->t('Style'),
      '#options' => [
        'fantasy' => $this->t('Fantasy concept art'),
        'realistic' => $this->t('Realistic'),
        'pixel' => $this->t('Pixel art'),
        'illustration' => $this->t('Illustration'),
      ],
      '#default_value' => 'fantasy',
    ];

    $form['aspect_ratio'] = [
      '#type' => 'select',
      '#title' => $this->t('Aspect ratio'),
      '#options' => [
        '1:1' => '1:1',
        '16:9' => '16:9',
        '9:16' => '9:16',
        '4:3' => '4:3',
      ],
      '#default_value' => '1:1',
    ];

    $form['negative_prompt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Negative prompt (optional)'),
      '#maxlength' => 500,
    ];

    $form['campaign_context'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Campaign context (optional)'),
      '#description' => $this->t('Example: campaign_id=42, dungeon=obsidian-catacombs, room=R07'),
      '#maxlength' => 255,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Queue Gemini Stub Request'),
      '#button_type' => 'primary',
    ];

    $result = $form_state->get('gemini_stub_result');
    if (is_array($result)) {
      $form['result'] = [
        '#type' => 'details',
        '#title' => $this->t('Latest stub response'),
        '#open' => TRUE,
        'request' => [
          '#markup' => '<p><strong>' . $this->t('Request ID') . ':</strong> ' . Html::escape((string) $result['request_id']) . '</p>',
        ],
        'mode' => [
          '#markup' => '<p><strong>' . $this->t('Mode') . ':</strong> ' . Html::escape((string) $result['mode']) . '</p>',
        ],
        'status' => [
          '#markup' => '<p><strong>' . $this->t('Status') . ':</strong> ' . Html::escape((string) $result['status']) . '</p>',
        ],
      ];

      if (!empty($result['output']['text']) && is_string($result['output']['text'])) {
        $form['result']['text_output'] = [
          '#markup' => '<p><strong>' . $this->t('Model text response') . ':</strong> ' . Html::escape($result['output']['text']) . '</p>',
        ];
      }

      $image_data_uri = isset($result['output']['image_data_uri']) && is_string($result['output']['image_data_uri']) ? $result['output']['image_data_uri'] : '';
      if ($image_data_uri !== '' && $this->isSafeDataUriImage($image_data_uri)) {
        $form['result']['image_preview'] = [
          '#type' => 'html_tag',
          '#tag' => 'img',
          '#attributes' => [
            'src' => $image_data_uri,
            'alt' => $this->t('Gemini generated preview image'),
            'style' => 'max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px; margin-top: 8px;',
          ],
        ];
      }

      if (!empty($result['output']['image_url']) && is_string($result['output']['image_url'])) {
        $form['result']['image_url'] = [
          '#markup' => '<p><strong>' . $this->t('Image URL') . ':</strong> ' . Html::escape($result['output']['image_url']) . '</p>',
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $prompt = trim((string) $form_state->getValue('prompt'));
    if (mb_strlen($prompt) < 12) {
      $form_state->setErrorByName('prompt', $this->t('Prompt must be at least 12 characters for a usable generation request.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $payload = [
      'prompt' => $form_state->getValue('prompt'),
      'style' => $form_state->getValue('style'),
      'aspect_ratio' => $form_state->getValue('aspect_ratio'),
      'negative_prompt' => $form_state->getValue('negative_prompt'),
      'campaign_context' => $form_state->getValue('campaign_context'),
      'requested_by_uid' => (int) $this->currentUser()->id(),
    ];

    $result = $this->geminiImageService->generateImage($payload);
    $form_state->set('gemini_stub_result', $result);
    $form_state->setRebuild(TRUE);

    if (!empty($result['success'])) {
      $this->messenger()->addStatus($this->t('Gemini request @request_id completed in @mode mode.', [
        '@request_id' => $result['request_id'],
        '@mode' => (string) $result['mode'],
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Gemini request @request_id failed: @message', [
        '@request_id' => $result['request_id'] ?? 'unknown',
        '@message' => (string) ($result['message'] ?? 'Unknown error'),
      ]));
    }
  }

  /**
   * Validate that a data URI points to an image payload.
   */
  private function isSafeDataUriImage(string $uri): bool {
    if (strlen($uri) > 8 * 1024 * 1024) {
      return FALSE;
    }

    return (bool) preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,[A-Za-z0-9+\/=\r\n]+$/', $uri);
  }

}

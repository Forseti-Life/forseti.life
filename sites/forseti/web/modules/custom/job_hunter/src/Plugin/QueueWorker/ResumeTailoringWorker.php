<?php

namespace Drupal\job_hunter\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\job_hunter\Traits\JobHunterLoggerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resume Tailoring GenAI queue worker.
 *
 * Processes resume tailoring via AWS Bedrock in the background.
 *
 * @QueueWorker(
 *   id = "job_hunter_resume_tailoring",
 *   title = @Translation("Resume Tailoring GenAI"),
 *   cron = {"time" = 180}
 * )
 */
class ResumeTailoringWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use JobHunterLoggerTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $uid = $data['uid'];
    $job_id = $data['job_id'];
    $profile_json = $data['profile_json'];
    $job_data = $data['job_data'];

    $logger = \Drupal::logger('job_hunter');
    
    // Get username and job details for logging
    $user = \Drupal\user\Entity\User::load($uid);
    $username = $user ? $user->getAccountName() : "uid:$uid";
    
    $extracted = !empty($job_data['extracted_json']) ? json_decode($job_data['extracted_json'], TRUE) : [];
    $company = $extracted['company_name'] ?? 'Unknown Company';
    $job_title = $extracted['job_title'] ?? 'Unknown Position';
    
    $this->logInfo('🔄 Queue: Starting resume tailoring for @username → "@title" at @company (job @job_id)', [
      '@username' => $username,
      '@title' => $job_title,
      '@company' => $company,
      '@job_id' => $job_id,
    ]);

    $connection = \Drupal::database();

    try {
      // Update status to processing
      $this->updateTailoringStatus($connection, $uid, $job_id, 'processing');

      // Parse job data (extracted already parsed above for logging)
      $skills = !empty($job_data['skills_required_json']) ? json_decode($job_data['skills_required_json'], TRUE) : [];
      $keywords = !empty($job_data['keywords_json']) ? json_decode($job_data['keywords_json'], TRUE) : [];

      // Build the GenAI request payload
      $genai_payload = [
        'action' => 'generate_tailored_resume',
        'job_requisition' => [
          'id' => (int) $job_id,
          'extracted_json' => $extracted,
          'skills_required_json' => $skills,
          'keywords_json' => $keywords,
          'raw_posting_text' => $job_data['raw_posting_text'] ?? '',
        ],
        'user_resume' => [
          'consolidated_profile_json' => $profile_json,
        ],
      ];

      // Call AWS Bedrock
      $tailored_result = $this->callGenAiTailoringService($genai_payload);

      if (!$tailored_result || !isset($tailored_result['tailored_resume_json'])) {
        throw new \Exception('Failed to generate tailored resume from AI service');
      }

      // Save the tailored resume
      $now = time();
      $existing = $connection->select('jobhunter_tailored_resumes', 'tr')
        ->fields('tr', ['id'])
        ->condition('uid', $uid)
        ->condition('job_id', $job_id)
        ->execute()
        ->fetchField();

      if ($existing) {
        $connection->update('jobhunter_tailored_resumes')
          ->fields([
            'tailored_resume_json' => json_encode($tailored_result['tailored_resume_json']),
            'tailoring_status' => 'completed',
            'updated' => $now,
          ])
          ->condition('id', $existing)
          ->execute();
      }
      else {
        $connection->insert('jobhunter_tailored_resumes')
          ->fields([
            'uid' => $uid,
            'job_id' => $job_id,
            'tailored_resume_json' => json_encode($tailored_result['tailored_resume_json']),
            'tailoring_status' => 'completed',
            'created' => $now,
            'updated' => $now,
          ])
          ->execute();
      }

      $this->logInfo('✅ Queue: Resume tailoring complete for @username → "@title" at @company (job @job_id)', [
        '@username' => $username,
        '@title' => $job_title,
        '@company' => $company,
        '@job_id' => $job_id,
      ]);

    }
    catch (\Exception $e) {
      $this->logError('❌ Queue: Resume tailoring failed for @username → "@title" at @company (job @job_id): @error', [
        '@username' => $username,
        '@title' => $job_title,
        '@company' => $company,
        '@job_id' => $job_id,
        '@error' => $e->getMessage(),
      ]);

      // Update status to failed
      $this->updateTailoringStatus($connection, $uid, $job_id, 'failed');

      throw $e;
    }
  }

  /**
   * Update or create tailoring status record.
   */
  private function updateTailoringStatus($connection, $uid, $job_id, $status) {
    $now = time();
    $existing = $connection->select('jobhunter_tailored_resumes', 'tr')
      ->fields('tr', ['id'])
      ->condition('uid', $uid)
      ->condition('job_id', $job_id)
      ->execute()
      ->fetchField();

    if ($existing) {
      $connection->update('jobhunter_tailored_resumes')
        ->fields([
          'tailoring_status' => $status,
          'updated' => $now,
        ])
        ->condition('id', $existing)
        ->execute();
    }
    else {
      $connection->insert('jobhunter_tailored_resumes')
        ->fields([
          'uid' => $uid,
          'job_id' => $job_id,
          'tailoring_status' => $status,
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }
  }

  /**
   * Call AWS Bedrock for resume tailoring.
   */
  private function callGenAiTailoringService(array $payload) {
    try {
      $sdk = new \Aws\Sdk([
        'region' => 'us-west-2',
        'version' => 'latest',
      ]);

      $bedrock = $sdk->createBedrockRuntime();
      $prompt = $this->buildTailoredResumePrompt($payload);

      $this->logInfo('Queue: Calling AWS Bedrock Claude for resume tailoring');

      $response = $bedrock->invokeModel([
        'modelId' => 'anthropic.claude-3-5-sonnet-20240620-v1:0',
        'body' => json_encode([
          'anthropic_version' => 'bedrock-2023-05-31',
          'max_tokens' => 40000,
          'messages' => [
            [
              'role' => 'user',
              'content' => $prompt,
            ],
          ],
        ]),
      ]);

      $result = json_decode($response['body']->getContents(), TRUE);

      if (isset($result['content'][0]['text'])) {
        $ai_response = $result['content'][0]['text'];
        $stop_reason = $result['stop_reason'] ?? 'unknown';
        
        // Check if response was truncated due to max_tokens limit
        if ($stop_reason === 'max_tokens') {
          $this->logError('❌ Resume tailoring hit max_tokens limit! Response truncated at @len chars. Increase max_tokens to fix this.', [
            '@len' => strlen($ai_response),
          ]);
        }
        
        // Debug: Log first 500 chars of response
        $this->logInfo('Queue: AI response preview (stop_reason: @reason): @preview', [
          '@reason' => $stop_reason,
          '@preview' => substr($ai_response, 0, 500),
        ]);
        
        $json_str = $this->extractJsonFromResponse($ai_response);

        if ($json_str) {
          $tailored_resume = json_decode($json_str, TRUE);

          if (json_last_error() === JSON_ERROR_NONE && $tailored_resume) {
            $this->logInfo('Queue: Successfully generated tailored resume JSON');

            return [
              'tailored_resume_json' => $tailored_resume,
              'tailoring_guidance' => $tailored_resume['tailoring_metadata']['guidance'] ?? NULL,
            ];
          }
          
          // Log JSON parse error
          $this->logError('Queue: JSON parse error: @error', [
            '@error' => json_last_error_msg(),
          ]);
        }
        else {
          $this->logError('Queue: extractJsonFromResponse returned null. Response length: @len', [
            '@len' => strlen($ai_response),
          ]);
        }

        $this->logError('Queue: Failed to parse tailored resume JSON from AI response');
        return NULL;
      }

      $this->logError('Queue: Unexpected API response format from Bedrock');
      return NULL;

    }
    catch (\Exception $e) {
      $this->logError('Queue: GenAI API call failed: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Build the prompt for generating a tailored resume JSON.
   */
  private function buildTailoredResumePrompt(array $payload) {
    $job = $payload['job_requisition'] ?? [];
    $resume = $payload['user_resume']['consolidated_profile_json'] ?? [];

    $job_title = $job['extracted_json']['position']['title'] ?? $job['extracted_json']['job_title'] ?? 'the position';
    $company_name = $job['extracted_json']['company']['name'] ?? $job['extracted_json']['company_name'] ?? 'the company';
    $job_skills = json_encode($job['skills_required_json'] ?? [], JSON_PRETTY_PRINT);
    $job_keywords = json_encode($job['keywords_json'] ?? [], JSON_PRETTY_PRINT);
    $job_description = $job['raw_posting_text'] ?? '';
    $resume_json = json_encode($resume, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $job_id = $job['id'] ?? 0;

    return <<<PROMPT
You are an expert resume tailoring AI. Your task is to create a tailored version of the candidate's resume optimized for a specific job posting.

## Job Information
**Position:** {$job_title}
**Company:** {$company_name}

**Required Skills:**
{$job_skills}

**Key Keywords:**
{$job_keywords}

**Job Description:**
{$job_description}

## Candidate's Current Resume (JSON)
{$resume_json}

## Your Task

Generate a TAILORED version of the candidate's resume as a JSON object. The output must:

1. **Match the RESUME_JSON_SCHEMA.md structure** exactly with these sections:
   - `schema_version`: "1.0"
   - `tailoring_metadata`: Object with job_id, job_title, company, tailored_at timestamp, and guidance array
   - `contact_info`: Keep unchanged from original
   - `executive_profile`: Rewrite summary to emphasize relevant experience for this role
   - `strategic_differentiators`: Prioritize/reword to match job requirements
   - `professional_experience`: Reorder achievements, emphasize relevant technologies/metrics
   - `consulting_practice`: Include if relevant to role
   - `early_career`: Include if relevant
   - `education`: Keep unchanged
   - `technical_expertise`: Reorder categories to prioritize job-relevant skills
   - `leadership_philosophy`: Tailor if relevant
   - `demonstration_projects`: Include if relevant
   - `publications`: Include if candidate has publications and they're relevant to the role
   - `patents`: Include if candidate has patents and they're relevant to the role
   - `certifications`: Include if candidate has certifications and they're relevant to the role
   - `awards_and_honors`: Include if relevant to demonstrate excellence in the field
   - `languages`: Include if job requires or values language skills

2. **Tailoring Guidelines:**
   - Incorporate keywords from the job posting naturally
   - Prioritize achievements that match required skills
   - Quantified metrics should be preserved and highlighted when relevant
   - Technologies mentioned in job posting should be emphasized
   - Maintain professional tone and factual accuracy
   - DO NOT fabricate information - only reorganize and emphasize existing content
   - For publications, patents, certifications, awards, and languages: only include if they exist in source resume AND are relevant to the position

3. **Add tailoring_metadata section:**
   ```json
   "tailoring_metadata": {
     "job_id": {$job_id},
     "job_title": "{$job_title}",
     "company": "{$company_name}",
     "tailored_at": "ISO-8601 timestamp",
     "match_score": 0-100,
     "guidance": [
       "Key suggestion 1",
       "Key suggestion 2"
     ],
     "emphasized_skills": ["skill1", "skill2"],
     "emphasized_achievements": ["achievement summary 1"]
   }
   ```

## Output Format

Return ONLY valid JSON. No markdown code blocks, no explanatory text. The JSON should be parseable directly.
Start your response with { and end with }.

PROMPT;
  }

  /**
   * Extract JSON from AI response that may contain markdown or text.
   */
  private function extractJsonFromResponse($response) {
    $response_text = trim($response);
    
    if (empty($response_text)) {
      return NULL;
    }

    // Normalize responses that contain literal escape sequences (e.g. "\n")
    // without actual newlines. This indicates the JSON was returned as a
    // string-escaped payload and must be unescaped before decoding.
    $has_literal_newlines = strpos($response_text, "\\n") !== FALSE;
    $has_actual_newlines = strpos($response_text, "\n") !== FALSE;
    if ($has_literal_newlines && !$has_actual_newlines) {
      $response_text = stripcslashes($response_text);
      $response_text = trim($response_text);
      \Drupal::logger('job_hunter')->warning('🟡 Normalized escaped JSON response (literal \\n sequences detected)');
    }
    
    // If the response starts with { and ends with }, try parsing it directly first
    if ($response_text[0] === '{' && $response_text[strlen($response_text) - 1] === '}') {
      // Test if it's valid JSON by trying to decode it
      $test_decode = json_decode($response_text, TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $response_text; // It's already valid JSON!
      }
      // Log why direct parsing failed
      \Drupal::logger('job_hunter')->warning('🟡 Direct JSON parse failed: @error, Last 200 chars: @end', [
        '@error' => json_last_error_msg(),
        '@end' => substr($response_text, -200),
      ]);
    }
    else {
      // Log why we didn't try direct parsing
      $first_char = isset($response_text[0]) ? $response_text[0] : 'EMPTY';
      $last_char = strlen($response_text) > 0 ? $response_text[strlen($response_text) - 1] : 'EMPTY';
      \Drupal::logger('job_hunter')->warning('🟡 Skipped direct parse. First: @first, Last: @last, Last 100 chars: @end', [
        '@first' => $first_char,
        '@last' => $last_char,
        '@end' => substr($response_text, -100),
      ]);
    }
    
    // Try markdown code fence
    if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/s', $response_text, $matches)) {
      return trim($matches[1]);
    }
    
    // Find balanced JSON using brace counting (handles truncated responses)
    $start_pos = strpos($response_text, '{');
    if ($start_pos === FALSE) {
      return NULL;
    }

    $depth = 0;
    $in_string = FALSE;
    $escape_next = FALSE;
    $len = strlen($response_text);

    for ($i = $start_pos; $i < $len; $i++) {
      $char = $response_text[$i];

      if ($escape_next) {
        $escape_next = FALSE;
        continue;
      }
      if ($char === '\\' && $in_string) {
        $escape_next = TRUE;
        continue;
      }
      if ($char === '"') {
        $in_string = !$in_string;
        continue;
      }
      if ($in_string) {
        continue;
      }
      if ($char === '{') {
        $depth++;
      }
      elseif ($char === '}') {
        $depth--;
        if ($depth === 0) {
          return substr($response_text, $start_pos, $i - $start_pos + 1);
        }
      }
    }

    // If we got here, brace counting failed but response looks like JSON
    // Log the final state for debugging
    \Drupal::logger('job_hunter')->warning('🟡 Brace counting failed. Final depth: @depth, in_string: @str, last 100 chars: @end', [
      '@depth' => $depth,
      '@str' => $in_string ? 'YES' : 'NO',
      '@end' => substr($response_text, -100),
    ]);

    return NULL;
  }

}

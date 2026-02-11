<?php

namespace Drupal\job_hunter\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\file\Entity\File;
use Drupal\job_hunter\Traits\QueueWorkerBaseTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resume GenAI parsing queue worker.
 *
 * Processes resume parsing via AWS Bedrock in the background.
 *
 * @QueueWorker(
 *   id = "job_hunter_genai_parsing",
 *   title = @Translation("Resume GenAI Parsing"),
 *   cron = {"time" = 120}
 * )
 */
class ResumeGenAiParsingWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use QueueWorkerBaseTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The AI API service.
   *
   * @var \Drupal\ai_conversation\Service\AIApiService
   */
  protected $aiApiService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    $instance->aiApiService = $container->get('ai_conversation.ai_api_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $uid = $data['uid'];
    $resume_id = $data['resume_id'];
    $file_id = $data['file_id'];
    $extracted_text = $data['extracted_text'];
    $filename = $data['filename'];

    $logger = \Drupal::logger('job_hunter');
    $logger->info('🔄 Queue: Starting GenAI parsing for @filename (user @uid)', [
      '@filename' => $filename,
      '@uid' => $uid,
    ]);

    try {
      // Update status to processing
      $connection = \Drupal::database();
      $connection->update('jobhunter_resume_parsed_data')
        ->fields(['status' => 'processing', 'changed' => \Drupal::time()->getRequestTime()])
        ->condition('resume_file_id', $file_id)
        ->condition('uid', $uid)
        ->execute();

      // Call GenAI parsing
      $result = $this->parseResumeProdMode($extracted_text, $filename, $uid);
      $parsed_data = $result['parsed_data'];
      $raw_responses = $result['raw_responses'];

      // Store successful result with raw responses for debugging
      // Concatenate all chunk responses for storage
      $all_raw_responses = '';
      foreach ($raw_responses as $chunk_name => $raw_response) {
        $all_raw_responses .= "=== $chunk_name ===\n" . $raw_response . "\n\n";
      }
      
      $connection->update('jobhunter_resume_parsed_data')
        ->fields([
          'parsed_data' => json_encode($parsed_data),
          'raw_genai_response_core' => $all_raw_responses,
          'raw_genai_response_experience' => json_encode($raw_responses),
          'status' => 'complete',
          'error_message' => NULL,
          'changed' => \Drupal::time()->getRequestTime(),
        ])
        ->condition('resume_file_id', $file_id)
        ->condition('uid', $uid)
        ->execute();

      $logger->info('✅ Queue: GenAI parsing complete for @filename', ['@filename' => $filename]);

      // Check if all queued items are complete before consolidating
      $pending_count = $connection->select('jobhunter_resume_parsed_data', 'rpd')
        ->condition('uid', $uid)
        ->condition('status', ['queued', 'processing'], 'IN')
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($pending_count == 0) {
        // All files complete - consolidate all parsed data
        $logger->info('🔄 Queue: All files complete for user @uid, running consolidation', ['@uid' => $uid]);
        $this->consolidateAllParsedData($uid);
      } else {
        $logger->info('⏳ Queue: @count files still pending for user @uid, deferring consolidation', [
          '@count' => $pending_count,
          '@uid' => $uid,
        ]);
      }

    } catch (\Exception $e) {
      $logger->error('❌ Queue: GenAI parsing failed for @filename: @error', [
        '@filename' => $filename,
        '@error' => $e->getMessage(),
      ]);

      // Store error status
      $connection = \Drupal::database();
      $connection->update('jobhunter_resume_parsed_data')
        ->fields([
          'status' => 'error',
          'error_message' => $e->getMessage(),
          'changed' => \Drupal::time()->getRequestTime(),
        ])
        ->condition('resume_file_id', $file_id)
        ->condition('uid', $uid)
        ->execute();

      // Re-throw - if it's a SuspendQueueException, preserve it
      throw $e;
    }
  }

  /**
   * Parse resume using GenAI (chunked approach).
   * 
   * @return array
   *   Array with 'parsed_data' and 'raw_responses' keys.
   */
  private function parseResumeProdMode($extracted_text, $filename, $uid) {
    $logger = \Drupal::logger('job_hunter');

    // Get username for logging
    $user = \Drupal\user\Entity\User::load($uid);
    $username = $user ? $user->getAccountName() : "uid:$uid";
    
    // Track raw responses for debugging
    $raw_responses = [];
    
    // Split resume into chunks of max 10,000 characters at natural breaks
    $chunks = $this->chunkResumeText($extracted_text, 10000);
    $logger->info('📊 Resume split into @count chunks for processing', ['@count' => count($chunks)]);
    
    // Parse each chunk and collect all experience data
    $all_experiences = [];
    $core_data = NULL;
    $chunk_num = 0;
    
    foreach ($chunks as $chunk) {
      $chunk_num++;
      $logger->info('🔄 Queue Chunk @num/@total: Processing @chars characters', [
        '@num' => $chunk_num,
        '@total' => count($chunks),
        '@chars' => strlen($chunk),
      ]);
      
      // Parse this chunk for both core profile and experience
      $chunk_prompt = $this->buildChunkPrompt($chunk, $filename);
      $result = $this->callBedrockAndParse($chunk_prompt, "chunk_$chunk_num", $filename, $username, $uid);
      $chunk_data = $result['parsed_data'];
      $raw_responses["chunk_$chunk_num"] = $result['raw_response'];
      
      if (!$chunk_data) {
        $logger->warning('⚠️ Chunk @num failed to parse, skipping', ['@num' => $chunk_num]);
        continue;
      }
      
      // First chunk with core data wins
      if (!$core_data && !empty($chunk_data['contact_info'])) {
        $core_data = $chunk_data;
        // Remove experience from core data as we'll merge separately
        unset($core_data['professional_experience']);
        $logger->info('✅ Core profile data extracted from chunk @num', ['@num' => $chunk_num]);
      }
      
      // Collect experience from all chunks
      if (!empty($chunk_data['professional_experience'])) {
        $all_experiences = array_merge($all_experiences, $chunk_data['professional_experience']);
        $logger->info('✅ Found @count jobs in chunk @num', [
          '@count' => count($chunk_data['professional_experience']),
          '@num' => $chunk_num,
        ]);
      }
    }

    if (!$core_data) {
      // Suspend queue - GenAI may have succeeded but JSON parsing failed
      throw new SuspendQueueException('Failed to parse core profile sections from any chunk. Check logs for JSON parsing errors. Clear cache if prompt needs adjustment.');
    }

    // Add all collected experiences to final data
    $core_data['professional_experience'] = $all_experiences;
    $logger->info('✅ Total jobs collected: @count', ['@count' => count($all_experiences)]);

    return [
      'parsed_data' => $core_data,
      'raw_responses' => $raw_responses,
    ];
  }

  /**
   * Split resume text into chunks of max_chars, breaking at newlines.
   * 
   * @param string $text
   *   The full resume text.
   * @param int $max_chars
   *   Maximum characters per chunk (default 10000).
   * 
   * @return array
   *   Array of text chunks.
   */
  private function chunkResumeText($text, $max_chars = 10000) {
    $chunks = [];
    $current_chunk = '';
    $lines = explode("\n", $text);
    
    foreach ($lines as $line) {
      // If adding this line would exceed max, save current chunk and start new one
      if (strlen($current_chunk) + strlen($line) + 1 > $max_chars && strlen($current_chunk) > 0) {
        $chunks[] = $current_chunk;
        $current_chunk = $line;
      } else {
        $current_chunk .= ($current_chunk ? "\n" : '') . $line;
      }
    }
    
    // Add the last chunk if not empty
    if (strlen($current_chunk) > 0) {
      $chunks[] = $current_chunk;
    }
    
    return $chunks;
  }

  /**
   * Call AWS Bedrock via AIApiService and parse JSON response.
   * 
   * @param int $max_tokens
   *   Maximum tokens for response (default 20000 for chunked processing)
   * 
   * @return array
   *   Array with 'parsed_data' and 'raw_response' keys.
   */
  private function callBedrockAndParse($prompt, $chunk_name, $filename = '', $username = '', $uid = 0, $max_tokens = 20000) {
    $logger = \Drupal::logger('job_hunter');

    $context_msg = '';
    if ($filename && $username) {
      $context_msg = " for $filename (user $username)";
    }

    $logger->info('⏳ Queue @chunk: Sending request to GenAI API via AIApiService (max_tokens: @tokens)@context', [
      '@chunk' => $chunk_name,
      '@tokens' => $max_tokens,
      '@context' => $context_msg,
    ]);

    // Use centralized AIApiService
    $result = $this->aiApiService->invokeModelDirect(
      $prompt,
      'job_hunter',
      'resume_parsing',
      [
        'uid' => $uid,
        'filename' => $filename,
        'chunk' => $chunk_name,
        'queue' => 'job_hunter_genai_parsing',
        'item_key' => "resume_parsing_{$uid}_{$filename}_{$chunk_name}",
      ],
      [
        'max_tokens' => $max_tokens,
      ]
    );

    if (!$result['success']) {
      $logger->error('❌ Queue @chunk: AIApiService call failed: @error@context', [
        '@chunk' => $chunk_name,
        '@error' => $result['error'] ?? 'Unknown error',
        '@context' => $context_msg,
      ]);
      return [
        'parsed_data' => NULL,
        'raw_response' => $result['error'] ?? 'Unknown error',
      ];
    }

    $response_text = $result['response'];
    $stop_reason = $result['stop_reason'];

    // Check if response was truncated due to max_tokens limit
    if ($stop_reason === 'max_tokens') {
      $logger->error('❌ Queue @chunk hit max_tokens limit! Response truncated at @len chars@context. Increase max_tokens to fix this.', [
        '@chunk' => $chunk_name,
        '@len' => strlen($response_text),
        '@context' => $context_msg,
      ]);
    }

    $logger->info('🔍 Queue @chunk response: @len chars, stop_reason: @reason@context', [
      '@chunk' => $chunk_name,
      '@len' => strlen($response_text),
      '@reason' => $stop_reason,
      '@context' => $context_msg,
    ]);

    $json_text = $this->extractJsonFromResponse($response_text);

    if ($json_text) {
      $parsed_data = json_decode($json_text, TRUE);
      if (json_last_error() === JSON_ERROR_NONE && is_array($parsed_data)) {
        $logger->info('✅ Queue @chunk JSON parsed successfully: @keys top-level keys', [
          '@chunk' => $chunk_name,
          '@keys' => count($parsed_data),
        ]);
        return [
          'parsed_data' => $parsed_data,
          'raw_response' => $response_text,
        ];
      } else {
        $logger->error('🔴 Queue @chunk JSON decode error: @error. JSON length: @len chars, First 500 chars: @sample', [
          '@chunk' => $chunk_name,
          '@error' => json_last_error_msg(),
          '@len' => strlen($json_text),
          '@sample' => substr($json_text, 0, 500),
        ]);
      }
    } else {
      // Log why extraction failed
      $starts_with_brace = (strpos(trim($response_text), '{') === 0) ? 'YES' : 'NO';
      $logger->error('🔴 Queue @chunk failed to extract JSON. Response length: @len chars, Starts with brace: @brace, Sample (first 500 chars): @sample', [
        '@chunk' => $chunk_name,
        '@len' => strlen($response_text),
        '@brace' => $starts_with_brace,
        '@sample' => substr($response_text, 0, 500),
      ]);
    }

    return [
      'parsed_data' => NULL,
      'raw_response' => $response_text,
    ];
  }

  /**
   * Consolidate ALL parsed resume data for a user.
   *
   * This is called only when all queued items are complete,
   * ensuring we have all data before building the consolidated profile.
   */
  private function consolidateAllParsedData($uid) {
    try {
      $connection = \Drupal::database();
      $logger = \Drupal::logger('job_hunter');

      // Get all completed parsed data for this user
      $results = $connection->select('jobhunter_resume_parsed_data', 'rpd')
        ->fields('rpd', ['parsed_data', 'resume_file_id'])
        ->condition('uid', $uid)
        ->condition('status', 'complete')
        ->orderBy('created', 'ASC')  // Oldest first, newest overwrites
        ->execute()
        ->fetchAll();

      if (empty($results)) {
        $logger->warning('Queue: No completed parsed data found for user @uid', ['@uid' => $uid]);
        return;
      }

      $consolidated = [];
      $professional_experiences = [];
      $source_files = [];

      foreach ($results as $row) {
        $parsed_data = json_decode($row->parsed_data, TRUE);
        if (!$parsed_data || !is_array($parsed_data)) {
          continue;
        }

        // Track source filenames for status display
        if (!empty($parsed_data['extraction_metadata']['source_filename'])) {
          $source_files[] = $parsed_data['extraction_metadata']['source_filename'];
        } else {
          // Fallback: get filename from file entity
          $file = \Drupal\file\Entity\File::load($row->resume_file_id);
          if ($file) {
            $source_files[] = $file->getFilename();
          }
        }

        // Collect professional experiences from all resumes
        if (!empty($parsed_data['professional_experience'])) {
          $professional_experiences = array_merge(
            $professional_experiences,
            $parsed_data['professional_experience']
          );
          unset($parsed_data['professional_experience']);
        }

        // Merge other sections (newer overwrites older for same keys)
        $consolidated = array_merge($consolidated, $parsed_data);
      }

      // De-duplicate professional experiences by company+title+start_date
      $unique_experiences = [];
      $seen_keys = [];
      foreach ($professional_experiences as $exp) {
        $key = ($exp['company'] ?? '') . '|' . ($exp['title'] ?? '') . '|' . ($exp['start_date'] ?? '');
        if (!isset($seen_keys[$key])) {
          $seen_keys[$key] = TRUE;
          $unique_experiences[] = $exp;
        }
      }

      // Sort by start_date descending (most recent first)
      usort($unique_experiences, function($a, $b) {
        return ($b['start_date'] ?? '') <=> ($a['start_date'] ?? '');
      });

      $consolidated['professional_experience'] = $unique_experiences;
      $consolidated['last_updated'] = date('c');
      $consolidated['resume_count'] = count($results);
      
      // Build extraction_metadata with source_files for status tracking
      $consolidated['extraction_metadata'] = [
        'source_files' => array_unique($source_files),
        'consolidated_at' => date('c'),
        'resume_count' => count($results),
      ];

      // Save consolidated profile
      $connection->update('jobhunter_job_seeker')
        ->fields(['consolidated_profile_json' => json_encode($consolidated)])
        ->condition('uid', $uid)
        ->execute();

      $logger->info('✅ Queue: Consolidated @count resumes for user @uid (@exp experiences)', [
        '@count' => count($results),
        '@uid' => $uid,
        '@exp' => count($unique_experiences),
      ]);

    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Queue: Failed to consolidate all parsed data: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * Build chunk parsing prompt (handles both core and experience).
   */
  private function buildChunkPrompt($chunk_text, $filename) {
    $timestamp = date('c');
    $char_count = strlen($chunk_text);
    
    return <<<PROMPT
You are a professional resume parser. Extract ALL information from this resume chunk.

IMPORTANT: This is part of a larger resume that has been split into chunks. Extract whatever information is present in this chunk. Some fields may not be present - return null or empty arrays for missing data.

REQUIREMENTS:
1. Preserve ALL information - do not summarize
2. Use YYYY-MM format for dates
3. Use null for missing optional fields
4. Return ONLY valid JSON conforming to RFC 8259 - NO markdown code blocks, USE proper JSON escaping (\n for newlines, \" for quotes)
5. For professional experience: Extract complete job entries even if split across chunks

JSON SCHEMA:
{
  "schema_version": "1.0",
  "extraction_metadata": {
    "source_filename": "{$filename}",
    "extracted_at": "{$timestamp}",
    "character_count": {$char_count}
  },
  "contact_info": {
    "full_name": "First Last",
    "credentials": ["MBA", "PMP"],
    "headline": "Professional title/tagline",
    "location": {"city": "City", "state": "ST"},
    "phone": "(xxx) xxx-xxxx",
    "email": "email@example.com",
    "websites": [{"type": "linkedin|github|personal", "url": "https://..."}]
  },
  "executive_profile": {
    "summary": "Full executive summary text",
    "industry_focus": ["industry1", "industry2"],
    "key_metrics": [{"metric": "name", "value": "XXM+", "context": "explanation"}]
  },
  "strategic_differentiators": [
    {"title": "Title", "description": "Description"}
  ],
  "consulting_practice": {
    "company": "Company Name",
    "title": "Title",
    "start_date": "YYYY-MM",
    "end_date": null,
    "description": "Description",
    "notable_engagements": [{"client": "Client", "role": "Role", "description": "Desc"}]
  },
  "early_career": {
    "period": "YYYY-YYYY",
    "summary": "Summary text",
    "positions": [{"company": "Company", "duration": "X years", "focus": "Role desc"}]
  },
  "education": [
    {"institution": "University", "degree": "Degree Name", "abbreviation": "MBA", "field": "Field", "end_date": "YYYY-MM"}
  ],
  "technical_expertise": {
    "categories": [{"name": "Category", "skills": ["skill1", "skill2"]}]
  },
  "leadership_philosophy": {
    "statement": "Philosophy text",
    "key_themes": ["theme1", "theme2"]
  },
  "demonstration_projects": [
    {"name": "Project", "url": "https://...", "technologies": ["tech1"], "description": "Desc"}
  ],
  "professional_experience": [
    {
      "company": "Company Name",
      "title": "Job Title",
      "employment_type": "direct|consulting",
      "via_company": null,
      "start_date": "YYYY-MM",
      "end_date": "YYYY-MM or null if current",
      "location": "City, ST",
      "company_context": "Brief company description if provided",
      "responsibility_categories": [
        {
          "category": "Category Name",
          "achievements": [
            {
              "text": "Full bullet point text",
              "metrics": ["\$3.2M revenue", "30% improvement"],
              "technologies": ["Python", "AWS"],
              "keywords": ["AI strategy", "data governance"]
            }
          ]
        }
      ]
    }
  ]
}

RESUME CHUNK:
---
{$chunk_text}
---

Return the JSON object with whatever sections are present in this chunk. Use null or empty arrays for missing sections.
PROMPT;
  }

  /**
   * Build core profile prompt.
   */
  private function buildCoreProfilePrompt($extracted_text, $filename) {
    $timestamp = date('c');
    $char_count = strlen($extracted_text);

    return <<<PROMPT
You are a professional resume parser. Extract the CORE PROFILE sections from this resume into JSON.

IMPORTANT: Do NOT include professional_experience in this response. That will be extracted separately.

REQUIREMENTS:
1. Preserve ALL information - do not summarize
2. Use YYYY-MM format for dates
3. Use null for missing optional fields
4. Return ONLY valid JSON conforming to RFC 8259 - NO markdown code blocks, USE proper JSON escaping (\n for newlines, \" for quotes)

JSON SCHEMA:
{
  "schema_version": "1.0",
  "extraction_metadata": {
    "source_filename": "{$filename}",
    "extracted_at": "{$timestamp}",
    "character_count": {$char_count}
  },
  "contact_info": {
    "full_name": "First Last",
    "credentials": ["MBA", "PMP"],
    "headline": "Professional title/tagline",
    "location": {"city": "City", "state": "ST"},
    "phone": "(xxx) xxx-xxxx",
    "email": "email@example.com",
    "websites": [{"type": "linkedin|github|personal", "url": "https://..."}]
  },
  "executive_profile": {
    "summary": "Full executive summary text",
    "industry_focus": ["industry1", "industry2"],
    "key_metrics": [{"metric": "name", "value": "XXM+", "context": "explanation"}]
  },
  "strategic_differentiators": [
    {"title": "Title", "description": "Description"}
  ],
  "consulting_practice": {
    "company": "Company Name",
    "title": "Title",
    "start_date": "YYYY-MM",
    "end_date": null,
    "description": "Description",
    "notable_engagements": [{"client": "Client", "role": "Role", "description": "Desc"}]
  },
  "early_career": {
    "period": "YYYY-YYYY",
    "summary": "Summary text",
    "positions": [{"company": "Company", "duration": "X years", "focus": "Role desc"}]
  },
  "education": [
    {"institution": "University", "degree": "Degree Name", "abbreviation": "MBA", "field": "Field", "end_date": "YYYY-MM"}
  ],
  "technical_expertise": {
    "categories": [{"name": "Category", "skills": ["skill1", "skill2"]}]
  },
  "leadership_philosophy": {
    "statement": "Philosophy text",
    "key_themes": ["theme1", "theme2"]
  },
  "demonstration_projects": [
    {"name": "Project", "url": "https://...", "technologies": ["tech1"], "description": "Desc"}
  ]
}

RESUME TEXT:
---
{$extracted_text}
---

Return the JSON object with all core profile sections. Do NOT include professional_experience.
PROMPT;
  }

  /**
   * Build professional experience prompt.
   */
  private function buildProfessionalExperiencePrompt($extracted_text, $filename) {
    return <<<PROMPT
You are a professional resume parser. Extract ONLY the professional work experience from this resume.

REQUIREMENTS:
1. Preserve ALL job details and achievements - do not summarize
2. Extract metrics (dollar amounts, percentages, team sizes) into metrics arrays
3. Identify technologies mentioned in each achievement
4. Extract searchable keywords from each achievement
5. Use YYYY-MM format for dates
6. Return ONLY valid JSON conforming to RFC 8259 - USE proper JSON escaping (\n for newlines, \" for quotes)

JSON SCHEMA:
{
  "professional_experience": [
    {
      "company": "Company Name",
      "title": "Job Title",
      "employment_type": "direct|consulting",
      "via_company": null,
      "start_date": "YYYY-MM",
      "end_date": "YYYY-MM or null if current",
      "location": "City, ST",
      "company_context": "Brief company description if provided",
      "responsibility_categories": [
        {
          "category": "Category Name",
          "achievements": [
            {
              "text": "Full bullet point text",
              "metrics": ["\$3.2M revenue", "30% improvement"],
              "technologies": ["Python", "AWS"],
              "keywords": ["AI strategy", "data governance"]
            }
          ]
        }
      ]
    }
  ]
}

RESUME TEXT:
---
{$extracted_text}
---

Return the JSON object with professional_experience array containing ALL jobs and achievements.
PROMPT;
  }

}

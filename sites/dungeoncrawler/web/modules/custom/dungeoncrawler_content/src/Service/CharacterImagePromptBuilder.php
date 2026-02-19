<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Builds guardrailed prompts for character portrait generation.
 */
class CharacterImagePromptBuilder {

  /**
   * Default negative prompt for portrait generation.
   */
  private const DEFAULT_NEGATIVE_PROMPT = 'text, watermark, logo, signature, blurry, low quality, deformed';

  /**
   * Builds a provider-ready portrait prompt from character data.
   *
   * @param array $character_data
   *   Character data payload.
   * @param string $user_prompt
   *   Optional user-provided prompt guidance.
   *
   * @return string
   *   The prompt text.
   */
  public function buildPortraitPrompt(array $character_data, string $user_prompt = ''): string {
    $lines = [
      'Create a high-fantasy character portrait for a tabletop RPG.',
      'No text, logos, watermarks, or copyrighted characters.',
      'Keep a clear silhouette, consistent lighting, and game-ready detail.',
      'Portrait framing: head and shoulders, neutral background.',
    ];

    $attribute_lines = $this->buildAttributeLines($character_data);
    if (!empty($attribute_lines)) {
      $lines[] = 'Character attributes:';
      $lines = array_merge($lines, $attribute_lines);
    }

    $resolved_user_prompt = trim($user_prompt);
    if ($resolved_user_prompt !== '') {
      $lines[] = 'User direction:';
      $lines[] = $resolved_user_prompt;
    }

    return implode("\n", $lines);
  }

  /**
   * Returns the default negative prompt.
   */
  public function getDefaultNegativePrompt(): string {
    return self::DEFAULT_NEGATIVE_PROMPT;
  }

  /**
   * Builds a list of character attribute lines.
   *
   * @param array $character_data
   *   Character data payload.
   *
   * @return array
   *   Prompt-ready attribute lines.
   */
  private function buildAttributeLines(array $character_data): array {
    $lines = [];
    $map = [
      'Name' => $this->stringValue($character_data['name'] ?? ''),
      'Ancestry' => $this->stringValue($character_data['ancestry'] ?? ''),
      'Class' => $this->stringValue($character_data['class'] ?? ''),
      'Background' => $this->stringValue($character_data['background'] ?? ''),
      'Alignment' => $this->stringValue($character_data['alignment'] ?? ''),
      'Deity' => $this->stringValue($character_data['deity'] ?? ''),
      'Age' => $this->stringValue($character_data['age'] ?? ''),
      'Gender/Pronouns' => $this->stringValue($character_data['gender'] ?? ''),
      'Concept' => $this->stringValue($character_data['concept'] ?? ''),
      'Appearance' => $this->stringValue($character_data['appearance'] ?? ''),
      'Personality' => $this->stringValue($character_data['personality'] ?? ''),
      'Backstory' => $this->stringValue($character_data['backstory'] ?? ''),
    ];

    foreach ($map as $label => $value) {
      if ($value !== '') {
        $lines[] = "- {$label}: {$value}";
      }
    }

    $ability_line = $this->buildAbilityLine($character_data['abilities'] ?? []);
    if ($ability_line !== '') {
      $lines[] = "- Abilities: {$ability_line}";
    }

    return $lines;
  }

  /**
   * Builds a compact ability summary line.
   *
   * @param array $abilities
   *   Ability map.
   *
   * @return string
   *   Summary line or empty string.
   */
  private function buildAbilityLine(array $abilities): string {
    if (!is_array($abilities)) {
      return '';
    }

    $order = ['str', 'dex', 'con', 'int', 'wis', 'cha'];
    $parts = [];
    foreach ($order as $key) {
      if (!array_key_exists($key, $abilities)) {
        continue;
      }
      $value = is_numeric($abilities[$key]) ? (int) $abilities[$key] : NULL;
      if ($value === NULL) {
        continue;
      }
      $parts[] = strtoupper($key) . ' ' . $value;
    }

    return implode(', ', $parts);
  }

  /**
   * Normalizes a value to a trimmed string.
   */
  private function stringValue($value): string {
    if (!is_scalar($value)) {
      return '';
    }

    return trim((string) $value);
  }

}

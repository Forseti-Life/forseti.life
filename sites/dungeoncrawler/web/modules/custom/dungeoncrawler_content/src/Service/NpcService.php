<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/**
 * Campaign NPC CRUD, social mechanics, and AI-prompt context.
 *
 * Provides the canonical NPC entity layer for named campaign characters
 * (allies, contacts, merchants, villains, quest-givers). This is distinct from
 * dc_npc_psychology, which handles in-session attitude matrices for all
 * entities (including dungeon creatures). dc_npc is the GM-authored catalog.
 *
 * Tables: dc_npc (entity), dc_npc_history (audit trail for AC-005).
 */
class NpcService {

  /** Valid NPC roles (AC-001). */
  const VALID_ROLES = ['ally', 'contact', 'merchant', 'villain', 'neutral'];

  /** Valid attitude values — subset of NpcPsychologyService::ATTITUDE_LADDER. */
  const VALID_ATTITUDES = ['friendly', 'indifferent', 'unfriendly', 'hostile'];

  /** Attitude ladder ordered from best to worst for step-change logic (AC-002). */
  const ATTITUDE_ORDER = ['friendly', 'indifferent', 'unfriendly', 'hostile'];

  public function __construct(
    protected readonly Connection $database,
    protected readonly AccountInterface $currentUser
  ) {}

  // ── CRUD ───────────────────────────────────────────────────────────────────

  /**
   * Create a new NPC for a campaign.
   *
   * @param int $campaign_id
   * @param array $data
   *   Required: name, role.
   *   Optional: attitude, level, perception, armor_class, hit_points,
   *             fort_save, ref_save, will_save, lore_notes, dialogue_notes.
   *
   * @return array  Created NPC record.
   * @throws \InvalidArgumentException  On validation failure.
   */
  public function createNpc(int $campaign_id, array $data): array {
    $this->validateCampaignAccess($campaign_id);

    $name = trim($data['name'] ?? '');
    if ($name === '') {
      throw new \InvalidArgumentException('name is required', 400);
    }

    $role = $data['role'] ?? 'neutral';
    if (!in_array($role, self::VALID_ROLES, TRUE)) {
      throw new \InvalidArgumentException(
        'role must be one of: ' . implode(', ', self::VALID_ROLES), 400
      );
    }

    $attitude = $data['attitude'] ?? 'indifferent';
    if (!in_array($attitude, self::VALID_ATTITUDES, TRUE)) {
      throw new \InvalidArgumentException(
        'attitude must be one of: ' . implode(', ', self::VALID_ATTITUDES), 400
      );
    }

    $now = time();
    $fields = [
      'campaign_id'   => $campaign_id,
      'name'          => $name,
      'role'          => $role,
      'attitude'      => $attitude,
      'level'         => (int) ($data['level'] ?? 1),
      'perception'    => (int) ($data['perception'] ?? 0),
      'armor_class'   => (int) ($data['armor_class'] ?? 10),
      'hit_points'    => (int) ($data['hit_points'] ?? 0),
      'fort_save'     => (int) ($data['fort_save'] ?? 0),
      'ref_save'      => (int) ($data['ref_save'] ?? 0),
      'will_save'     => (int) ($data['will_save'] ?? 0),
      'lore_notes'    => $data['lore_notes'] ?? '',
      'dialogue_notes' => $data['dialogue_notes'] ?? '',
      'entity_ref'    => $data['entity_ref'] ?? '',
      'created'       => $now,
      'updated'       => $now,
    ];

    $npc_id = (int) $this->database->insert('dc_npc')->fields($fields)->execute();
    $fields['id'] = $npc_id;

    return $fields;
  }

  /**
   * Return a single NPC scoped to a campaign.
   *
   * @param int $campaign_id
   * @param int $npc_id
   *
   * @return array|null
   */
  public function getNpc(int $campaign_id, int $npc_id): ?array {
    $row = $this->database->select('dc_npc', 'n')
      ->fields('n')
      ->condition('id', $npc_id)
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  /**
   * Return all NPCs for a campaign (AC-005).
   *
   * @param int $campaign_id
   *
   * @return array[]
   */
  public function getCampaignNpcs(int $campaign_id): array {
    return $this->database->select('dc_npc', 'n')
      ->fields('n')
      ->condition('campaign_id', $campaign_id)
      ->orderBy('name')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Update mutable NPC fields.
   *
   * @param int $campaign_id
   * @param int $npc_id
   * @param array $data  Fields to update.
   *
   * @return array  Updated NPC record.
   * @throws \InvalidArgumentException  On access denied or not found.
   */
  public function updateNpc(int $campaign_id, int $npc_id, array $data): array {
    $this->validateCampaignAccess($campaign_id);

    $existing = $this->getNpc($campaign_id, $npc_id);
    if ($existing === NULL) {
      throw new \InvalidArgumentException("NPC {$npc_id} not found in campaign {$campaign_id}", 404);
    }

    $allowed = ['name', 'role', 'attitude', 'level', 'perception', 'armor_class',
                'hit_points', 'fort_save', 'ref_save', 'will_save',
                'lore_notes', 'dialogue_notes', 'entity_ref'];
    $update = [];
    foreach ($allowed as $field) {
      if (array_key_exists($field, $data)) {
        $update[$field] = $data[$field];
      }
    }

    if (isset($update['role']) && !in_array($update['role'], self::VALID_ROLES, TRUE)) {
      throw new \InvalidArgumentException('Invalid role', 400);
    }
    if (isset($update['attitude']) && !in_array($update['attitude'], self::VALID_ATTITUDES, TRUE)) {
      throw new \InvalidArgumentException('Invalid attitude', 400);
    }

    if (!empty($update)) {
      $update['updated'] = time();
      $this->database->update('dc_npc')
        ->fields($update)
        ->condition('id', $npc_id)
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }

    return $this->getNpc($campaign_id, $npc_id) ?? $existing;
  }

  /**
   * Delete a campaign NPC and its history.
   *
   * @param int $campaign_id
   * @param int $npc_id
   *
   * @throws \InvalidArgumentException  On access denied or not found.
   */
  public function deleteNpc(int $campaign_id, int $npc_id): void {
    $this->validateCampaignAccess($campaign_id);

    $existing = $this->getNpc($campaign_id, $npc_id);
    if ($existing === NULL) {
      throw new \InvalidArgumentException("NPC {$npc_id} not found", 404);
    }

    $this->database->delete('dc_npc_history')->condition('npc_id', $npc_id)->execute();
    $this->database->delete('dc_npc')
      ->condition('id', $npc_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();
  }

  // ── AC-002: Social mechanics ───────────────────────────────────────────────

  /**
   * Apply a social skill check result to an NPC's attitude.
   *
   * - Diplomacy success → attitude improves one step.
   * - Deception detected → attitude worsens one step.
   *
   * @param int $campaign_id
   * @param int $npc_id
   * @param string $check_type   'diplomacy' or 'deception'.
   * @param int $dc              Influence DC.
   * @param int $result          Player's total check result.
   * @param int $session_id      Current session ID (0 if outside session).
   *
   * @return array  ['npc' => updated npc, 'attitude_changed' => bool, 'old_attitude' => str, 'new_attitude' => str]
   * @throws \InvalidArgumentException
   */
  public function applySocialCheck(
    int $campaign_id,
    int $npc_id,
    string $check_type,
    int $dc,
    int $result,
    int $session_id = 0
  ): array {
    $npc = $this->getNpc($campaign_id, $npc_id);
    if ($npc === NULL) {
      throw new \InvalidArgumentException("NPC {$npc_id} not found", 404);
    }

    $check_type = strtolower($check_type);
    if (!in_array($check_type, ['diplomacy', 'deception'], TRUE)) {
      throw new \InvalidArgumentException("check_type must be 'diplomacy' or 'deception'", 400);
    }

    $old_attitude = $npc['attitude'];
    $idx = array_search($old_attitude, self::ATTITUDE_ORDER, TRUE);
    if ($idx === FALSE) {
      $idx = 1; // default to indifferent
    }

    $success = ($result >= $dc);
    $attitude_changed = FALSE;
    $new_attitude = $old_attitude;

    if ($check_type === 'diplomacy' && $success) {
      // Improve by one step (lower index = better).
      $new_idx = max(0, $idx - 1);
      $new_attitude = self::ATTITUDE_ORDER[$new_idx];
      $attitude_changed = ($new_attitude !== $old_attitude);
    }
    elseif ($check_type === 'deception' && !$success) {
      // Detected deception — worsens by one step.
      $new_idx = min(count(self::ATTITUDE_ORDER) - 1, $idx + 1);
      $new_attitude = self::ATTITUDE_ORDER[$new_idx];
      $attitude_changed = ($new_attitude !== $old_attitude);
    }

    if ($attitude_changed) {
      $this->database->update('dc_npc')
        ->fields(['attitude' => $new_attitude, 'updated' => time()])
        ->condition('id', $npc_id)
        ->execute();

      $trigger = sprintf('%s DC %d (rolled %d)%s',
        ucfirst($check_type), $dc, $result,
        $success ? '' : ' — detected'
      );
      $this->logHistory($npc_id, $campaign_id, 'attitude', $old_attitude, $new_attitude, $session_id, $trigger);

      $npc['attitude'] = $new_attitude;
      $npc['updated'] = time();
    }

    return [
      'npc' => $npc,
      'attitude_changed' => $attitude_changed,
      'old_attitude' => $old_attitude,
      'new_attitude' => $new_attitude,
      'check_succeeded' => $success,
    ];
  }

  // ── AC-005: History ────────────────────────────────────────────────────────

  /**
   * Log an NPC change event for the campaign history trail.
   *
   * @param int $npc_id
   * @param int $campaign_id
   * @param string $change_type  attitude|relationship|note
   * @param string $old_value
   * @param string $new_value
   * @param int $session_id
   * @param string $trigger
   */
  public function logHistory(
    int $npc_id,
    int $campaign_id,
    string $change_type,
    string $old_value,
    string $new_value,
    int $session_id = 0,
    string $trigger = ''
  ): void {
    $this->database->insert('dc_npc_history')
      ->fields([
        'npc_id'      => $npc_id,
        'campaign_id' => $campaign_id,
        'session_id'  => $session_id,
        'change_type' => $change_type,
        'old_value'   => $old_value,
        'new_value'   => $new_value,
        'trigger'     => $trigger,
        'created'     => time(),
      ])
      ->execute();
  }

  /**
   * Return the full history trail for an NPC.
   *
   * @param int $npc_id
   *
   * @return array[]
   */
  public function getHistory(int $npc_id): array {
    return $this->database->select('dc_npc_history', 'h')
      ->fields('h')
      ->condition('npc_id', $npc_id)
      ->orderBy('created', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  // ── AC-003: AI prompt data ─────────────────────────────────────────────────

  /**
   * Build AI-prompt-friendly NPC context for all campaign NPCs.
   *
   * Returns compact arrays with the fields the AI GM needs (AC-003):
   * name, role, current attitude, lore notes, dialogue notes.
   *
   * @param int $campaign_id
   *
   * @return array[]
   */
  public function buildAiPromptData(int $campaign_id): array {
    $npcs = $this->getCampaignNpcs($campaign_id);
    return array_map(static function (array $npc): array {
      return [
        'name'       => $npc['name'],
        'role'       => $npc['role'],
        'attitude'   => $npc['attitude'],
        'lore'       => $npc['lore_notes'] ?? '',
        'dialogue'   => $npc['dialogue_notes'] ?? '',
        'level'      => (int) $npc['level'],
        'entity_ref' => $npc['entity_ref'] ?? '',
      ];
    }, $npcs);
  }

  // ── Access guard ───────────────────────────────────────────────────────────

  /**
   * Assert the current user owns the given campaign.
   *
   * @throws \InvalidArgumentException  With HTTP 403 on access failure.
   */
  protected function validateCampaignAccess(int $campaign_id): void {
    $uid = (int) $this->currentUser->id();
    if ($uid === 0) {
      throw new \InvalidArgumentException('Access denied', 403);
    }

    $owner = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['uid'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchField();

    if ($owner === FALSE) {
      throw new \InvalidArgumentException("Campaign {$campaign_id} not found", 404);
    }

    // Allow site admins to bypass ownership check.
    if ((int) $owner !== $uid && !$this->currentUser->hasPermission('administer dungeoncrawler content')) {
      throw new \InvalidArgumentException('Access denied to campaign', 403);
    }
  }

}

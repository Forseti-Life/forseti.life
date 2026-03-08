<?php
/**
 * @file
 * Verifies feat effects persist into campaign instance state_data JSON.
 *
 * Run with:
 *   drush php:script web/modules/custom/dungeoncrawler_content/tests/feat_effect_persistence_test.php
 */

$GLOBALS['test_pass'] = 0;
$GLOBALS['test_fail'] = 0;
$GLOBALS['test_errors'] = [];

function assert_true($condition, $label) {
  if ($condition) {
    $GLOBALS['test_pass']++;
    echo "  ✓ {$label}\n";
  }
  else {
    $GLOBALS['test_fail']++;
    $GLOBALS['test_errors'][] = $label;
    echo "  ✗ FAIL: {$label}\n";
  }
}

function assert_equals($expected, $actual, $label) {
  if ($expected === $actual) {
    $GLOBALS['test_pass']++;
    echo "  ✓ {$label}\n";
  }
  else {
    $GLOBALS['test_fail']++;
    $GLOBALS['test_errors'][] = "{$label} (expected: " . var_export($expected, TRUE) . ", got: " . var_export($actual, TRUE) . ")";
    echo "  ✗ FAIL: {$label} (expected: " . var_export($expected, TRUE) . ", got: " . var_export($actual, TRUE) . ")\n";
  }
}

echo "=== Feat Effect Persistence Test ===\n\n";

$db = \Drupal::database();
/** @var \Drupal\dungeoncrawler_content\Service\CharacterStateService $character_state */
$character_state = \Drupal::service('dungeoncrawler_content.character_state_service');

$campaign_id = (int) $db->select('dc_campaigns', 'c')
  ->fields('c', ['id'])
  ->orderBy('id', 'DESC')
  ->range(0, 1)
  ->execute()
  ->fetchField();

if ($campaign_id < 1) {
  echo "No campaigns found; cannot run campaign-instance persistence test.\n";
  throw new \RuntimeException('No campaigns found for feat persistence test.');
}

$uid = (int) \Drupal::currentUser()->id();
if ($uid < 1) {
  $uid = 1;
}

$now = time();
$temp_name = 'Feat Persistence Test Character';
$instance_id = 'feat-persist-' . substr(hash('sha256', uniqid('', TRUE)), 0, 12);

$character_data = [
  'step' => 8,
  'name' => $temp_name,
  'level' => 1,
  'ancestry' => 'human',
  'class' => 'rogue',
  'feats' => [
    ['type' => 'general', 'id' => 'fleet', 'name' => 'Fleet', 'level' => 1],
    ['type' => 'class', 'id' => 'reach-spell', 'name' => 'Reach Spell', 'level' => 1],
    ['type' => 'ancestry', 'id' => 'orc-sight', 'name' => 'Orc Sight', 'level' => 1],
  ],
  'hit_points' => ['current' => 18, 'max' => 18],
  'hero_points' => 1,
];

$row_id = 0;

try {
  $row_id = (int) $db->insert('dc_campaign_characters')
    ->fields([
      'uuid' => $instance_id,
      'campaign_id' => $campaign_id,
      'character_id' => 0,
      'instance_id' => $instance_id,
      'uid' => $uid,
      'name' => $temp_name,
      'level' => 1,
      'ancestry' => 'human',
      'class' => 'rogue',
      'hp_current' => 18,
      'hp_max' => 18,
      'armor_class' => 15,
      'experience_points' => 0,
      'position_q' => 0,
      'position_r' => 0,
      'last_room_id' => '',
      'character_data' => json_encode($character_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
      'state_data' => NULL,
      'status' => 1,
      'created' => $now,
      'changed' => $now,
      'updated' => $now,
      'type' => 'pc',
    ])
    ->execute();

  assert_true($row_id > 0, 'Temporary campaign instance row created');

  // Trigger a state mutation that persists through CharacterStateService::saveState().
  $turn_state = $character_state->startNewTurn((string) $row_id);
  assert_equals(3, (int) ($turn_state['actionsRemaining'] ?? -1), 'startNewTurn executed');

  $persisted = $db->select('dc_campaign_characters', 'c')
    ->fields('c', ['state_data'])
    ->condition('id', $row_id)
    ->execute()
    ->fetchField();

  $state = json_decode((string) $persisted, TRUE) ?: [];

  assert_true(!empty($state), 'state_data JSON persisted');
  assert_true(isset($state['features']['featEffects']), 'features.featEffects persisted');
  assert_true(isset($state['actions']['availableActions']['feat']), 'actions.availableActions.feat persisted');
  assert_true(isset($state['resources']['featResources']), 'resources.featResources persisted');
  assert_true(isset($state['spells']['featAugments']), 'spells.featAugments persisted');
  assert_true(isset($state['senses']) && is_array($state['senses']), 'senses persisted');

  $applied = $state['features']['featEffects']['applied_feats'] ?? [];
  assert_true(in_array('fleet', $applied, TRUE), 'fleet appears in applied feat list');
  assert_true(in_array('reach-spell', $applied, TRUE), 'reach-spell appears in applied feat list');
  assert_true(in_array('orc-sight', $applied, TRUE), 'orc-sight appears in applied feat list');

  $speed_bonus = (int) ($state['features']['featEffects']['derived_adjustments']['speed_bonus'] ?? 0);
  $speed_total = (int) ($state['movement']['speed']['total'] ?? 0);
  assert_equals(5, $speed_bonus, 'Fleet speed bonus persisted as +5');
  assert_equals(30, $speed_total, 'Total movement speed persisted as 30');

  $metamagic = $state['spells']['featAugments']['metamagic'] ?? [];
  $has_reach_spell = FALSE;
  foreach ($metamagic as $entry) {
    if (($entry['id'] ?? '') === 'reach-spell') {
      $has_reach_spell = TRUE;
      break;
    }
  }
  assert_true($has_reach_spell, 'Reach Spell metamagic persisted');

  $sense_ids = array_map(function ($sense) {
    return $sense['id'] ?? '';
  }, $state['senses'] ?? []);
  assert_true(in_array('darkvision', $sense_ids, TRUE), 'Darkvision sense persisted from orc-sight');
}
catch (\Throwable $e) {
  assert_true(FALSE, 'Unexpected exception: ' . $e->getMessage());
}
finally {
  if ($row_id > 0) {
    $db->delete('dc_campaign_characters')
      ->condition('id', $row_id)
      ->execute();
    echo "\nTemporary test row deleted (id={$row_id}).\n";
  }
}

echo "\n=== Feat Effect Persistence Test Summary ===\n";
echo "Passed: {$GLOBALS['test_pass']}\n";
echo "Failed: {$GLOBALS['test_fail']}\n";

if (!empty($GLOBALS['test_errors'])) {
  echo "\nFailures:\n";
  foreach ($GLOBALS['test_errors'] as $error) {
    echo " - {$error}\n";
  }
  throw new \RuntimeException('Feat effect persistence test failed.');
}

echo "\nAll assertions passed.\n";

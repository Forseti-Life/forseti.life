<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Dungeon Crawler Content settings.
 */
class DungeonCrawlerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dungeoncrawler_content.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dungeoncrawler_content_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dungeoncrawler_content.settings');

    $form['game_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('⚔️ Dungeon Settings'),
    ];

    $form['game_settings']['max_level'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Adventurer Level'),
      '#default_value' => $config->get('max_level') ?? 100,
      '#min' => 1,
      '#max' => 999,
      '#description' => $this->t('The maximum level an adventurer can reach in the dungeon.'),
    ];

    $form['game_settings']['difficulty_levels'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Dungeon Depth Tiers'),
      '#default_value' => $config->get('difficulty_levels') ?? "Shallow Halls\nTwisting Corridors\nDeep Caverns\nThe Underdark\nThe Abyss",
      '#description' => $this->t('One dungeon depth tier per line. Deeper tiers have stronger AI-generated monsters and better loot.'),
    ];

    $form['game_settings']['rarity_tiers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Item Rarity Tiers'),
      '#default_value' => $config->get('rarity_tiers') ?? "Common\nUncommon\nRare\nEpic\nLegendary",
      '#description' => $this->t('One rarity tier per line, from lowest to highest. Determines loot drop colors and AI generation parameters.'),
    ];

    $form['ai_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('🧠 AI Generation Settings'),
    ];

    $form['ai_settings']['room_persistence'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rooms are permanent after first generation'),
      '#default_value' => $config->get('room_persistence') ?? TRUE,
      '#description' => $this->t('When enabled, AI-generated rooms become permanent world fixtures after first exploration.'),
    ];

    $form['ai_settings']['monster_permadeath'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable monster permadeath for mortal creatures'),
      '#default_value' => $config->get('monster_permadeath') ?? TRUE,
      '#description' => $this->t('When enabled, mortal monsters that are slain stay dead permanently. Respawning creatures are unaffected.'),
    ];

    $form['display_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('🗺️ Display Settings'),
    ];

    $form['display_settings']['items_per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Items per page'),
      '#default_value' => $config->get('items_per_page') ?? 12,
      '#min' => 4,
      '#max' => 100,
      '#description' => $this->t('Number of dungeon rooms, items, or creatures to display per page in listings.'),
    ];

    $form['display_settings']['show_game_stats'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show adventure statistics on content pages'),
      '#default_value' => $config->get('show_game_stats') ?? TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('dungeoncrawler_content.settings')
      ->set('max_level', $form_state->getValue('max_level'))
      ->set('difficulty_levels', $form_state->getValue('difficulty_levels'))
      ->set('rarity_tiers', $form_state->getValue('rarity_tiers'))
      ->set('room_persistence', $form_state->getValue('room_persistence'))
      ->set('monster_permadeath', $form_state->getValue('monster_permadeath'))
      ->set('items_per_page', $form_state->getValue('items_per_page'))
      ->set('show_game_stats', $form_state->getValue('show_game_stats'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

<?php

namespace Drupal\denormalizer\Form;


use Drupal\Core\Database\Database;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Denormalizer Settings Form.
 */
class DenormalizerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'denormalizer_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['denormalizer.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('denormalizer.settings');

    $form['denormalizer_sql_mode'] = [
      '#title' => $this->t('SQL mode'),
      '#type' => 'radios',
      '#options' => [
        'views' => $this->t('Create views'),
        'tables' => $this->t('Create tables'),
      ],
      '#description' => $this->t('Use views or tables for generating normalized data.'),
      '#default_value' => $config->get('denormalizer_sql_mode'),
    ];

    $form['denormalizer_db'] = [
      '#title' => $this->t('DB'),
      '#type' => 'radios',
      '#options' => [
        'local' => $this->t('Use local database'),
        'external' => $this->t('Create external database'),
      ],
      '#description' => $this->t('Which database to use'),
      '#default_value' => $config->get('denormalizer_db'),
    ];

    $form['denormalizer_view_prefix'] = [
      '#title' => $this->t('View prefix'),
      '#type' => 'textfield',
      /*'#default_value' => $config->get([
        'denormalizer_view_prefix',
        'snowflake_',
      ]),*/
    ];

    $form['denormalizer_db_prefix'] = [
      '#title' => $this->t('Database prefix'),
      '#type' => 'textfield',
      //'#default_value' => $config->get(['denormalizer_db_prefix', 'dw_']),
    ];

    $form['denormalizer_cron_enabled'] = [
      '#title' => $this->t('Run on cron?'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('denormalizer_cron_enabled'),
      '#description' => $this->t('The denormalized views or tables will be updated incrementally on cron.'),
    ];

    $form['denormalizer_run_every'] = [
      '#title' => $this->t('Run every'),
      '#type' => 'textfield',
      '#default_value' => $config->get('denormalizer_run_every'),
      '#description' => $this->t('Time between runs.'),
    ];

    $form['denormalizer_reload_every'] = [
      '#title' => $this->t('Reload every'),
      '#type' => 'textfield',
      '#default_value' => $config->get('denormalizer_reload_every'),
      '#description' => t('Time between reloads.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $prefix = Database::getConnection()->tablePrefix();

    $no_prefix = $form_state['values']['denormalizer_db'] == 'local' && empty($form_state['values']['denormalizer_view_prefix']);
    $same_prefix = $form_state['values']['denormalizer_db'] == 'local' && ($form_state['values']['denormalizer_view_prefix'] == $prefix);
    if ($no_prefix || $same_prefix) {
      $form_state->setErrorByName('denormalizer_view_prefix', $this->t('Using the local database requires a view prefix. Otherwise your tables will be overwritten!'));
    }

    if ($form_state['values']['denormalizer_db'] == 'external' && empty($form_state['values']['denormalizer_db_prefix'])) {
      $form_state->setErrorByName('denormalizer_db_prefix', $this->t( 'Database prefix is required if using an external database.'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('denormalizer.settings')
      // Set the submitted configuration setting
      ->set('denormalizer_sql_mode', $form_state->getValue('denormalizer_sql_mode'))
      ->set('denormalizer_db', $form_state->getValue('denormalizer_db'))
      ->set('denormalizer_view_prefix', $form_state->getValue('denormalizer_view_prefix'))
      ->set('denormalizer_db_prefix', $form_state->getValue('denormalizer_db_prefix'))
      ->set('denormalizer_cron_enabled', $form_state->getValue('denormalizer_cron_enabled'))
      ->set('denormalizer_run_every', $form_state->getValue('denormalizer_run_every'))
      ->set('denormalizer_reload_every', $form_state->getValue('denormalizer_reload_every'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

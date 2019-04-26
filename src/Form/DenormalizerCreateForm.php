<?php

namespace Drupal\denormalizer\Form;

use Drupal\Denormalizer\Denormalizer;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines a confirmation form to confirm denormalization.
 */
class DenormalizerCreateForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $denormalizer = new Denormalizer();
    $denormalizer->build();
    $denormalizer->execute();
    variable_set('denormalizer_last_run', REQUEST_TIME);
    watchdog('denormalizer', 'Ran denormalizer.', array(), WATCHDOG_INFO);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "denormalizer_create_form";
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
      //@todo this URL needs to be updated
    return new Url('denormalizer.create');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Ready? This will create database views of normalized data.');
  }

}
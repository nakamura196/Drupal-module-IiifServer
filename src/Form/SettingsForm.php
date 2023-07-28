<?php

namespace Drupal\IiifServer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'IiifServer_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['IiifServer.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('IiifServer.settings');

    $form['description_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description Field'),
      '#default_value' => $config->get('description_field'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('IiifServer.settings')
      ->set('description_field', $form_state->getValue('description_field'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
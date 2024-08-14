<?php

namespace Drupal\iiif_server\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iiif_server_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['iiif_server.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('iiif_server.settings');

    $form['description_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description Field'),
      '#default_value' => $config->get('description_field'),
    ];

    $form['iiifserver_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IIIF Server Field'),
      '#default_value' => $config->get('iiifserver_field'),
    ];

    $form['iiifserver_manifest_rights_property'] = [
      '#type' => 'textfield',
      '#title' => $this->t('iiifserver_manifest_rights_property'),
      '#default_value' => $config->get('iiifserver_manifest_rights_property'),
    ];

    $form['iiifserver_manifest_attribution_property'] = [
      '#type' => 'textfield',
      '#title' => $this->t('iiifserver_manifest_attribution_property'),
      '#default_value' => $config->get('iiifserver_manifest_attribution_property'),
    ];

    $form['iiifserver_manifest_attribution_default'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default attribution'),
      '#default_value' => $config->get('iiifserver_manifest_attribution_default'),
    ];

    $form["iiifserver_manifest_rights_text"] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default license text (only for iiif 2.0)'),
      '#default_value' => $config->get("iiifserver_manifest_rights_text"),
    ];

    $form["iiifserver_manifest_viewing_direction_property"] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property to use for viewing direction'),
      '#default_value' => $config->get("iiifserver_manifest_viewing_direction_property"),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('iiif_server.settings')
      ->set('description_field', $form_state->getValue('description_field'))
      ->save();

    $this->config('iiif_server.settings')
      ->set('iiifserver_field', $form_state->getValue('iiifserver_field'))
      ->save();

      $this->config('iiif_server.settings')
      ->set('iiifserver_manifest_rights_property', $form_state->getValue('iiifserver_manifest_rights_property'))
      ->save();

      $this->config('iiif_server.settings')
      ->set('iiifserver_manifest_attribution_property', $form_state->getValue('iiifserver_manifest_attribution_property'))
      ->save();

    $this->config('iiif_server.settings')
      ->set('iiifserver_manifest_attribution_default', $form_state->getValue('iiifserver_manifest_attribution_default'))
      ->save();

      $this->config('iiif_server.settings')
      ->set('iiifserver_manifest_rights_text', $form_state->getValue('iiifserver_manifest_rights_text'))
      ->save();

      $this->config('iiif_server.settings')
      ->set("iiifserver_manifest_viewing_direction_property", $form_state->getValue("iiifserver_manifest_viewing_direction_property"))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
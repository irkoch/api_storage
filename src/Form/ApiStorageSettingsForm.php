<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class ApiStorageSettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_storage_settings_form';
  }

  public function getEditableConfigNames() {
    return [
      'api_storage.settings'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $storage_client_manager = \Drupal::getContainer()->get('plugin.manager.api_storage.entity_storage_client');
    $config = $this->config('api_storage.settings');

    foreach($storage_client_manager->getDefinitions() as $id => $definition) {
      foreach((array) $definition['resources_url'] as $bundle_id => $url) {
        $bundle = \Drupal::entityTypeManager()->getStorage($bundle_id);
        $form[$bundle_id . '_fieldset'] = ['#type' => 'fieldset', '#title' => $bundle->getEntityType()->getLabel()];
        $resources_url = $config->get($bundle_id . '_resources_url');
        $access_token = $config->get($bundle_id . '_access_token');

        $form[$bundle_id . '_fieldset'][$bundle_id . '_resources_url'] = [
          '#type'     => 'textfield',
          '#title'    => $definition['name'],
          '#default_value' => $resources_url ? $resources_url : $url,
          '#required' => TRUE,
        ];

        $form[$bundle_id . '_fieldset'][$bundle_id . '_access_token'] = [
          '#type'     => 'textfield',
          '#title'    => $this->t('API access token'),
          '#default_value' => $access_token ? $access_token : $definition['access_token'][$bundle_id],
          '#required' => TRUE
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValues() as $key => $value) {
      if (preg_match('/resources_url/i', $key) && !UrlHelper::isValid($value)) {
        $form_state->setErrorByName($key, $this->t('Url is not valid'));
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('api_storage.settings');
    foreach($form_state->getValues() as $key => $value) {
      if (preg_match('/resources_url|access_token$/i', $key)) {
        $config->set($key, $value);
      }
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }
}
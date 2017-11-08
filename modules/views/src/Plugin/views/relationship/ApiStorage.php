<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage_views\Plugin\relationship;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;

/**
 * Views relationship plugin for api storage Endpoints.
 *
 * @ViewRelationship("api_storage")
 */
class ApiStorage extends RelationshipPluginBase {
  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['requried']['#access'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $table_data = Views::viewData()->get($this->definition['base']);
    $this->query->addRelationship($table_data['table']['base']['ugc_storage_base_table_endpoint_id']);
  }
}
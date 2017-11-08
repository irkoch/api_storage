<?php
/**
 * @file
 * Contains \Drupal\api_storage\ApiEndpointBase.
 */

namespace Drupal\api_storage;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class ApiEndpointBase extends PluginBase implements ApiEndpointPluginInterface, ContainerFactoryPluginInterface {

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  public function getName() {
    return $this->pluginDefinition['machine_name'];
  }

  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  public function label() {
    return $this->pluginDefinition['label'];
  }

  public function getResourceUri() {
    return $this->pluginDefinition['resource_uri'];
  }

  public function getFields() {
    $fields = [];
    $types = [
      'integer' => ['id' => 'numeric'],
      'int' => ['id' => 'numeric'],
      'float' => [ 'id' => 'numeric', 'float' => TRUE ],
      'standard' => [ 'id' => 'standard' ],
      'string' => [ 'id' => 'standard' ],
      'date' => ['id' => 'date_fulldate'],
      'datetime' => ['id' => 'date_fulldate'],
      'decimal' => [ 'id' => 'numeric' ],
      'boolean' => ['id' => 'boolean'],
    ];

    $field_definitions = $this->getFieldDefinitions();
    foreach ($this->getAttributes() as $name => $type) {
      $fields[$name] = $types[$type];
      if (isset($field_definitions[$name])) {
        $fields[$name] += $field_definitions[$name];
      }
    }

    return $fields;
  }

  public function getAttributes() {
    return $this->pluginDefinition['attributes'];
  }

  public function getFieldDefinitions() {
    return $this->pluginDefinition['field_definitions'];
  }

  /**
   * @return mixed
   */
  public abstract function getDefaultField();

  /**
   * Build attributes as fields.
   */
  public abstract function buildFieldDefinitions();
}
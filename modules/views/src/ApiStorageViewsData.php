<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage_views;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\views\EntityViewsData;

class ApiStorageViewsData extends EntityViewsData {

  /**
   * @var \Drupal\api_storage\FidelioBaseTableEndpointPluginManager
   */
  protected $endpointPluginManager;

  public function __construct(EntityTypeInterface $entity_type,
                              SqlEntityStorageInterface $storage_controller,
                              EntityManagerInterface $entity_manager,
                              ModuleHandlerInterface $module_handler,
                              TranslationInterface $translation_manager)
  {
    parent::__construct($entity_type, $storage_controller, $entity_manager, $module_handler, $translation_manager);

    $this->endpointPluginManager = \Drupal::service('plugin.manager.api_storage_endpoint');
  }

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $base_table = $this->entityType->getBaseTable();

    $data[$base_table]['table']['base'] += [
      'query_id' => 'api_storage',
      'provider' => 'api_storage_views'
    ];

    $entity_type_id = $this->entityType->getBundleEntityType();

    foreach ($this->endpointPluginManager->getDefinitions() as $plugin_id => $plugin_definition) {
      if ($plugin_definition['entity_type_id'] == $entity_type_id) {
        $data = array_replace_recursive($data, $this->getPluginViewsData($plugin_id));
      }
    }

    return $data;
  }

  /**
   * Defines endpoint plugin views data.
   *
   * @param string $plugin_id
   */
  protected function getPluginViewsData($plugin_id) {
    $data = [];

    $base_table = $this->entityType->getBaseTable();
    $plugin_base_table = sprintf('%s_%s', $base_table, $plugin_id);

    $plugin_definition = $this->endpointPluginManager->getDefinition($plugin_id);
    $endpoint_plugin = $this->endpointPluginManager->createInstance($plugin_id);
    $data[$plugin_base_table]['table'] = [
      'group' => $endpoint_plugin->label(),
      'provider' => $plugin_definition['provider'],
      'entity type' => $this->entityType->id(),
      'entity revision' => FALSE,
      'base' => [
        'title' => $endpoint_plugin->getName(),
        'query_id' => 'api_storage',
        'endpoint_id' => $plugin_id,
        'help' => $endpoint_plugin->getDescription(),
        'defaults' => [
          'field' => $endpoint_plugin->getDefaultField(),
        ],
      ],
      'join' => [
        $base_table => [
          'left_field' => 'id',
          'field' => 'id',
          'type' => 'INNER'
        ]
      ]
    ];

    if ($this->entityType->isRevisionable()) {
      $data[$plugin_base_table['table']]['entity revision'] = $this->entityType->getRevisionTable();
    }

    $field_definitions = $endpoint_plugin->buildFieldDefinitions();
    // Tack on all the field definitions.
    foreach ($endpoint_plugin->getFields() as $name => $field) {
      $field_data = [];
      if (isset($field_definitions[$name]) && $fd = $field_definitions[$name]) {
        $field_data = $this->mapSingleFieldViewsData(
          $base_table,
          $name,
          $fd->getType(),
          'value',
          TRUE,
          FALSE, $fd
        );
      }

      $data[$plugin_base_table][$name] = $field_data + [
        'title' => isset($field['label']) ? $this->t($field['label']) : $name,
        'help' => isset($field['help']) ? $this->t($field['help']) : '',
        'entity field' => $name,
        'field' => [ 'id' => 'field' ],
        'argument' => [
          'field' => $name,
          'id' => $field['id'],
        ],
        'filter' => [
          $name => $field['id']
        ]
      ];
    }

    // Tack on the default uid
    // Filter by Drupal uid
    $data[$plugin_base_table]['uid'] = [
      'title' => t('User id'),
      'help' => t('Drupal user id, not to be confused with Fidelio profile id.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'uid',
      ],
    ];

    // Loop over all base table plugins that are not this current one and setup
    // a relationship to them.
    foreach (array_diff(array_keys($this->endpointPluginManager->getDefinitions()), [$plugin_id]) as $relationship_name) {
      $relationship_endpoint = $this->endpointPluginManager->createInstance($relationship_name);
      $data[$plugin_base_table][$relationship_name] = [
        'relationship' => [
          'title' => $relationship_endpoint->getName(),
          'help' => $relationship_endpoint->getDescription(),
          'label' => $relationship_endpoint->label(),
          'id' => 'api_storage',
          'plugin_id' => $relationship_name,
          'base' => sprintf('%s_%s', $base_table, $relationship_name),
        ],
      ];
    }

    return $data;
  }
}
<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

class ApiClientStorageManager extends DefaultPluginManager {
  /**
   * ApiClientStorageManager constructor.
   *
   * @param bool|string $subdir
   * @param \Traversable $namespaces
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   * @param null $plugin_interface
   * @param string $plugin_definition_annotation_name
   * @param array $additional_annotation_namespaces
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler)
  {
    parent::__construct(
      'Plugin/EntityStorageClient',
      $namespaces,
      $module_handler,
      'Drupal\api_storage\ApiClientStorageInterface',
      'Drupal\api_storage\Annotation\ApiClientStorage'
    );

    $this->alterInfo('api_client_storage_info');
    $this->setCacheBackend($cache_backend, 'api_client_storage');
  }
}
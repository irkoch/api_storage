<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

class ApiEndpointManager extends DefaultPluginManager {

  /**
   * Constructs a new Drupal\Core\Plugin\DefaultPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Endpoint',
      $namespaces,
      $module_handler,
      'Drupal\api_storage\ApiEndpointPluginInterface',
      'Drupal\api_storage\Annotation\ApiEndpoint'
    );

    $this->alterInfo('api_endpoint');
    $this->setCacheBackend($cache_backend, 'api_endpoints_plugins');

    //$this->getDiscovery()->getDefinitions()
    //$this->factory = new DefaultFactory($this->getDiscovery());
  }

  public function getPluginId() {
    return null;
  }

  public function getPluginDefinition() {
    return null;
  }
}
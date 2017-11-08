<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modify the serialization services.
 */
class ApiStorageServiceProvider extends ServiceProviderBase {
  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container->getDefinition('serialization.json')
      ->addTag('api_storage_response_decoder')
      ->addTag('api_storge_request_encoder');

    //$container->getDefinition('serialization.phpserialize')->addTag('fidelio_entity_response_decoder');
    //$container->getDefinition('serialization.yaml')->addTag('fidelio_entity_response_decoder');
  }
}
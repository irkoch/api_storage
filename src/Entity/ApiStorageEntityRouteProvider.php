<?php
/**
 * @file
 * Contains \Drupal\api_storage\Entity\FidelioEntityRouteProvider.
 */

namespace Drupal\api_storage\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\EntityRouteProviderInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for fidelio entity.
 */
class ApiStorageEntityRouteProvider implements EntityRouteProviderInterface {
  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entityType) {
    $route_collection = new RouteCollection();
    $bundle_id = $entityType->id();
    $route_base = sprintf('/api-storage/%s/{%s}', preg_replace('/_/', '-', $bundle_id), $bundle_id);
    $route = (new Route($route_base))
      ->addDefaults([
        '_entity_view'  => $bundle_id . '.full',
        '_title'        => 'View ' . $bundle_id,
      ])
      ->setRequirement('_entity_access', $bundle_id . '.view');

    $route_collection->add('entity.' . $bundle_id . '.canonical', $route);

    $route = (new Route($route_base . '/edit'))
      ->setDefault('_entity_form', $bundle_id . '.edit')
      ->setRequirement('_entity_access', $bundle_id . '.update')
      ->setOption('_' . $bundle_id . '_operation_route', TRUE);

    $route_collection->add('entity.' . $bundle_id . '.edit_form', $route);

    return $route_collection;
  }
}
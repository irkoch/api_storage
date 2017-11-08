<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage\Entity;

use Drupal\api_storage\ApiEndpointManager;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Utility\Xss;

/**
 * Define an Fidelio entity type list class builder.
 */
class ApiStorageEntityTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * The url generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  protected $endpointManager;

  /**
   * Constructs a ExternalEntityTypeListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator service.
   */
  public function __construct(ApiEndpointManager $endpoint_manager,EntityTypeInterface $entity_type, EntityStorageInterface $storage, UrlGeneratorInterface $url_generator) {
    parent::__construct($entity_type, $storage);
    $this->urlGenerator = $url_generator;
    $this->endpointManager = $endpoint_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('plugin.manager.api_storage_endpoint'),
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = t('Label');
    $header['description'] = array(
      'data' => t('Description'),
      'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
    );
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title'] = array(
      'data' => $entity->label(),
      'class' => array('menu-label'),
    );
    $row['description'] = Xss::filterAdmin($entity->getDescription());
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    // Place the edit operation after the operations added by field_ui.module
    // which have the weights 15, 20, 25.
    if (isset($operations['edit'])) {
      $operations['edit']['weight'] = 30;
    }
    return $operations;
  }

  public function load() {
    $entities = parent::load();
    $definitions = $this->endpointManager->getDefinitions();
    foreach($definitions as $plugin => $endpoint) {
      if (empty($endpoint['entity_type_id']) || !($endpoint['entity_type_id'] == $this->entityType->id())) {
        unset($entities[$plugin]);
        continue;
      }

      if(empty($entities[$plugin])) {
        $entity = $this->storage->create();
        $entity->setPlugin($plugin);
        $entity->save();
        $entities[$plugin] = $entity;
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['#empty'] = t('No external types available. <a href="@link">Add external type</a>.', array(
      '@link' => '',
    ));
    return $build;
  }
}
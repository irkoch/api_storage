<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage\Entity\Query\External;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryFactoryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\api_storage\RequestEncoderFactoryInterface;
use Drupal\api_storage\ResponseDecoderFactoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Factory class creating entity query objects for the external backend.
 *
 * @see \Drupal\api_storage\Entity\Query\External\Query
 */
class QueryFactory implements QueryFactoryInterface {

  /**
   * The namespace of this class, the parent class etc.
   *
   * @var array
   */
  protected $namespaces;

  /**
   * The external storage client manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $storageClientManager;

  /**
   * The decoder.
   *
   * @var \Drupal\api_storage\ResponseDecoderFactoryInterface
   */
  protected $decoder;

  /**
   * The encoder.
   *
   * @var \Drupal\api_storage\RequestEncoderFactoryInterface
   */
  protected $encoder;

  /**
   * The HTTP client to fetch the data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Stores the entity manager used by the query.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a QueryFactory object.
   */
  public function __construct(PluginManagerInterface $storage_client_manager, ResponseDecoderFactoryInterface $decoder, RequestEncoderFactoryInterface $encoder, ClientInterface $http_client, EntityManagerInterface $entity_manager) {
    $this->namespaces = QueryBase::getNamespaces($this);
    $this->storageClientManager = $storage_client_manager;
    $this->decoder = $decoder;
    $this->encoder = $encoder;
    $this->httpClient = $http_client;
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function get(EntityTypeInterface $entity_type, $conjunction) {
    if ($conjunction == 'OR') {
      throw new QueryException("External entity queries do not support OR conditions.");
    }
    $class = QueryBase::getClass($this->namespaces, 'Query');

    return new $class($entity_type, $conjunction, $this->namespaces, $this->storageClientManager, $this->decoder, $this->encoder, $this->httpClient, $this->entityManager);
  }

  /**
   * {@inheritdoc}
   */
  public function getAggregate(EntityTypeInterface $entity_type, $conjunction) {
    throw new QueryException("External entity queries do not support aggragate queries.");
  }

}
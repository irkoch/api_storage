<?php
/**
 * @file
 * Contains \Drupal\api_storage\ApiEntityStorage.
 */

namespace Drupal\api_storage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\api_storage\Entity\FidelioEntityType;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Component\Plugin\PluginManagerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Entity\ContentEntityStorageBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Cache\CacheBackendInterface;

class ApiEntityStorage extends ContentEntityStorageBase implements SqlEntityStorageInterface {
  /**
   * The external storage client manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $storageClientManager;

  /**
   * Storage client instances.
   *
   * @var \Drupal\api_storage\ExternalEntityStorageClientInterface[]
   */
  protected $storageClients = [];

  /**
   * The decoder.
   *
   * @var \Drupal\api_storage\ResponseDecoderFactoryInterface
   */
  protected $decoder;

  /**
   * The HTTP client to fetch the data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  protected $baseTable = 'api_storage';

  protected $tableMapping;

  protected $apiStorageSettings = [];

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager'),
      $container->get('cache.entity'),
      $container->get('plugin.manager.api_storage.entity_storage_client'),
      $container->get('api_storage.entity_storage_client.response_decoder_factory'),
      $container->get('http_client'),
      \Drupal::config('api_storage.settings'),
      \Drupal::logger('ugc_fidelio')
    );
  }

  /**
   * Constructs a new ExternalEntityStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend to be used.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $storage_client_manager
   *   The storage client manager.
   */
  public function __construct(EntityTypeInterface $entity_type,
                              EntityManagerInterface $entity_manager,
                              CacheBackendInterface $cache,
                              PluginManagerInterface $storage_client_manager,
                              ResponseDecoderFactoryInterface $decoder,
                              ClientInterface $http_client, $api_storage_settings,
                              LoggerChannelInterface $logger_channel)
  {
    parent::__construct($entity_type, $entity_manager, $cache);
    $this->storageClientManager = $storage_client_manager;
    $this->decoder = $decoder;
    $this->httpClient = $http_client;
    $this->apiStorageSettings = $api_storage_settings;
    $this->logger = $logger_channel;
  }

  public function getHttpClient() {
    return $this->httpClient;
  }

  /**
   * Get the storage client for a bundle.
   *
   * @param string $bundle_id
   *   The bundle to get the storage client for.
   *
   * @return \Drupal\api_storage\ExternalEntityStorageClientInterface
   *   The external entity storage client.
   */
  protected function getStorageClient($bundle_id) {
    if (!isset($this->storageClients[$bundle_id])) {
      /**
       * @var $bundle FidelioEntityType
       */
      $bundle = $this->entityManager->getStorage($this->getEntityType()->getBundleEntityType())->load($bundle_id);
      $config = [
        'http_client' => $this->httpClient,
        'decoder' => $this->decoder,
        'endpoint' => $bundle->getEndpoint(),
        'format' => $bundle->getFormat(),
        'http_headers' => [],
        'parameters' => $bundle->getParameters(),
        'bundle' => $bundle
      ];

      $api_key_settings = $bundle->getApiKeySettings();
      if (!empty($api_key_settings['header_name']) && !empty($api_key_settings['key'])) {
        $config['http_headers'][$api_key_settings['header_name']] = $api_key_settings['key'];
      }
      $this->storageClients[$bundle_id] = $this->storageClientManager->createInstance(
        $bundle->getClient(),
        $config
      );
    }
    return $this->storageClients[$bundle_id];
  }

  /**
   * Acts on entities before they are deleted and before hooks are invoked.
   *
   * Used before the entities are deleted and before invoking the delete hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   An array of entities.
   *
   * @throws EntityStorageException
   */
  public function preDelete(array $entities) {
    foreach ($entities as $entity) {
      $bundle = $this->entityManager->getStorage('fidelio_entity_type')->load($entity->bundle());
      if ($bundle && $bundle->isReadOnly()) {
        throw new EntityStorageException($this->t('Can not delete read-only external entities.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    // Do the actual delete.
    foreach ($entities as $entity) {
      $this->getStorageClient($entity->bundle())->delete($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) {
    return @parent::loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
    $entities_from_cache = [];

    if (is_array(reset($ids))) {
      $entities_from_storage = $this->getResultEntities($ids);
    }
    else {
      $entities_from_cache = $this->getFromPersistentCache($ids);

      // Load any remaining entities from the database.
      if ($entities_from_storage = $this->getFromStorage($ids)) {
//        $this->invokeStorageLoadHook($entities_from_storage);
        $this->setPersistentCache($entities_from_storage);
      }
    }

    return $entities_from_cache + $entities_from_storage;
  }

  /**
   * @param $ids
   * @return array
   */
  protected function getFromStorage($ids) {
    $entities = [];
    foreach ($ids as $id) {
      if (preg_match('/^([a-z0-9_]+)-([\w-]+)/i', $id, $matches)) {
        $bundle = $matches[1];
        $external_id = $matches[2];
        if ($external_id) {
          $external_entity = $this->getStorageClient($bundle)->load($external_id);
          if ($external_entity) {
            $entities[$id] = $this->create([$this->entityType->getKey('bundle') => $bundle])
              ->mapObject($external_entity)
              ->enforceIsNew(FALSE);
          }
        }
      }
    }

    return $entities;
  }

  /**
   * @param array $result
   * @return array
   */
  public function getResultEntities(array $result) {
    $entities = [];
    $bundle_key = $this->entityType->getKey('bundle');
    foreach ($result as $id => $item) {
      $entities[$id] = $this->create([$bundle_key => $item[$bundle_key]])
        ->mapObject((object) $item)->enforceIsNew(FALSE);
    }

    return $entities;
  }


  /**
   * Acts on an entity before the presave hook is invoked.
   *
   * Used before the entity is saved and before invoking the presave hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @throws EntityStorageException
   */
  public function preSave(\Drupal\Core\Entity\EntityInterface $entity) {
    $bundle = $this->entityManager->getStorage('fidelio_entity_type')->load($entity->bundle());
    if ($bundle && $bundle->isReadOnly()) {
      throw new EntityStorageException($this->t('Can not save read-only external entities.'));
    }
  }
  /**
   * {@inheritdoc}
   */
  protected function doSave($id, \Drupal\Core\Entity\EntityInterface $entity) {
    return $this->getStorageClient($entity->bundle())->save($entity);
  }

  protected function doPreSave(EntityInterface $entity) {
    $this->preSave($entity);
    return $entity->getOriginalId() ? $entity->getOriginalId() : $entity->id();
    //return parent::doPreSave($entity);
  }

  protected function doPostSave(EntityInterface $entity, $update) {
    //parent::doPostSave($entity, $update);
  }

  /**
   * {@inheritdoc}
i   */
  protected function getQueryServiceName() {
    return 'api_storage.entity.query';
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity) {
    return !$entity->isNew();
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteFieldItems($entities) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteRevisionFieldItems(ContentEntityInterface $revision) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadRevisionFieldItems($revision_id) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = array()) {
  }

  /**
   * {@inheritdoc}
   */
  protected function readFieldItemsToPurge(FieldDefinitionInterface $field_definition, $batch_size) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  protected function purgeFieldItems(ContentEntityInterface $entity, FieldDefinitionInterface $field_definition) {
  }

  /**
   * {@inheritdoc}
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    return $as_bool ? 0 : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasData() {
    return FALSE;
  }

  public function getTableMapping(array $storage_definitions = NULL) {
    $table_mapping = $this->tableMapping;

    // If we are using our internal storage definitions, which is our main use
    // case, we can statically cache the computed table mapping. If a new set
    // of field storage definitions is passed, for instance when comparing old
    // and new storage schema, we compute the table mapping without caching.
    // @todo Clean-up this in https://www.drupal.org/node/2274017 so we can
    //   easily instantiate a new table mapping whenever needed.
    if (!isset($this->tableMapping) || $storage_definitions) {
      $definitions = $storage_definitions ?: $this->entityManager->getFieldStorageDefinitions($this->entityTypeId);
      $table_mapping = new DefaultTableMapping($this->entityType, $definitions);

//      $shared_table_definitions = array_filter($definitions, function (FieldStorageDefinitionInterface $definition) use ($table_mapping) {
//        return $table_mapping->allowsSharedTableStorage($definition);
//      });

      $shared_table_definitions = $definitions;

      $key_fields = array_values(array_filter(array($this->idKey, $this->bundleKey, $this->uuidKey, $this->langcodeKey)));
      $all_fields = array_keys($shared_table_definitions);
      $revisionable_fields = [];
//      $revisionable_fields = array_keys(array_filter($shared_table_definitions, function (FieldStorageDefinitionInterface $definition) {
//        return $definition->isRevisionable();
//      }));
      // Make sure the key fields come first in the list of fields.
      $all_fields = array_merge($key_fields, array_diff($all_fields, $key_fields));

      // Nodes have all three of these fields, while custom blocks only have
      // log.
      // @todo Provide automatic definitions for revision metadata fields in
      //   https://www.drupal.org/node/2248983.
      $revision_metadata_fields = array_intersect(array(
        'revision_timestamp',
        'revision_uid',
        'revision_log',
      ), $all_fields);

      $revisionable = $this->entityType->isRevisionable();
      $translatable = $this->entityType->isTranslatable();
      if (!$revisionable && !$translatable) {
        // The base layout stores all the base field values in the base table.
        $table_mapping->setFieldNames($this->baseTable, $all_fields);
      }
      elseif ($revisionable && !$translatable) {
        // The revisionable layout stores all the base field values in the base
        // table, except for revision metadata fields. Revisionable fields
        // denormalized in the base table but also stored in the revision table
        // together with the entity ID and the revision ID as identifiers.
        $table_mapping->setFieldNames($this->baseTable, array_diff($all_fields, $revision_metadata_fields));
        $revision_key_fields = array($this->idKey, $this->revisionKey);
        $table_mapping->setFieldNames($this->revisionTable, array_merge($revision_key_fields, $revisionable_fields));
      }
      elseif (!$revisionable && $translatable) {
        // Multilingual layouts store key field values in the base table. The
        // other base field values are stored in the data table, no matter
        // whether they are translatable or not. The data table holds also a
        // denormalized copy of the bundle field value to allow for more
        // performant queries. This means that only the UUID is not stored on
        // the data table.
        $table_mapping
          ->setFieldNames($this->baseTable, $key_fields)
          ->setFieldNames($this->dataTable, array_values(array_diff($all_fields, array($this->uuidKey))));
      }
      elseif ($revisionable && $translatable) {
        // The revisionable multilingual layout stores key field values in the
        // base table, except for language, which is stored in the revision
        // table along with revision metadata. The revision data table holds
        // data field values for all the revisionable fields and the data table
        // holds the data field values for all non-revisionable fields. The data
        // field values of revisionable fields are denormalized in the data
        // table, as well.
        $table_mapping->setFieldNames($this->baseTable, array_values($key_fields));

        // Like in the multilingual, non-revisionable case the UUID is not
        // in the data table. Additionally, do not store revision metadata
        // fields in the data table.
        $data_fields = array_values(array_diff($all_fields, array($this->uuidKey), $revision_metadata_fields));
        $table_mapping->setFieldNames($this->dataTable, $data_fields);

        $revision_base_fields = array_merge(array($this->idKey, $this->revisionKey, $this->langcodeKey), $revision_metadata_fields);
        $table_mapping->setFieldNames($this->revisionTable, $revision_base_fields);

        $revision_data_key_fields = array($this->idKey, $this->revisionKey, $this->langcodeKey);
        $revision_data_fields = array_diff($revisionable_fields, $revision_metadata_fields, array($this->langcodeKey));
        $table_mapping->setFieldNames($this->revisionDataTable, array_merge($revision_data_key_fields, $revision_data_fields));
      }

      // Add dedicated tables.
//      $dedicated_table_definitions = array_filter($definitions, function (FieldStorageDefinitionInterface $definition) use ($table_mapping) {
//        return $table_mapping->requiresDedicatedTableStorage($definition);
//      });
      $dedicated_table_definitions = $definitions;

      $extra_columns = array(
        'bundle',
        'deleted',
        'entity_id',
        'revision_id',
        'langcode',
        'delta',
      );
      foreach ($dedicated_table_definitions as $field_name => $definition) {
        $tables = [$table_mapping->getDedicatedDataTableName($definition)];
        if ($revisionable && $definition->isRevisionable()) {
          $tables[] = $table_mapping->getDedicatedRevisionTableName($definition);
        }
        foreach ($tables as $table_name) {
          $table_mapping->setFieldNames($table_name, array($field_name));
          $table_mapping->setExtraColumns($table_name, $extra_columns);
        }
      }

      // Cache the computed table mapping only if we are using our internal
      // storage definitions.
      if (!$storage_definitions) {
        $this->tableMapping = $table_mapping;
      }
    }

    return $table_mapping;
  }

  public function getResourcesUrl($endpoint) {
    $resources_url = $this->apiStorageSettings->get(
      $this->getEntityType()->getBundleEntityType() . '_resources_url'
    );

    return rtrim($resources_url, '/') . '/' . trim($endpoint, '/');
  }

  /**
   * @param $endpoint
   * @param array $data
   * @return array
   */
  public function getData($endpoint, $parameters = []) {
    $result = ['result' => 0, 'response' => ''];
    $resource_url = $this->getResourcesUrl($endpoint);

    try {
      $response = $this->httpClient->get(
        $resource_url,
        [
          'headers' => $this->getHttpHeaders(),
          'query' => $parameters,
          'connect_timeout' => 5
        ]
      );
    } catch (RequestException $e) {
      $result = $this->responseOnError($e, $resource_url);
    }

    if (isset($response) && in_array($response->getStatusCode(), [200, 201])) {
      $object = (object) $this->decoder->getDecoder('json')->decode($response->getBody());
      $result = [
        'result' => 1,
        'response' => $object
      ];
    }

    return $result;
  }

  /**
   * @param $endpoint
   * @param array $data
   * @return array
   */
  public function postData($endpoint, array $data) {
    $result = ['result' => 0, 'response' => NULL];
    $resources_url = $this->getResourcesUrl($endpoint);

    try {
      $response = $this->httpClient->post(
        $resources_url,
        [
          'json' => $data,
          'headers' => $this->getHttpHeaders()
        ]
      );
    } catch (RequestException $e) {
      $result = $this->responseOnError($e, $resources_url, $data);
    }
    finally {
      if (!isset($result['status_code'])) {
        $result['status_code'] = $response->getStatusCode();
      }
    }

    if (isset($response) && in_array($response->getStatusCode(), [200, 201, 204])) {
      $object = (object) $this->decoder->getDecoder('json')->decode($response->getBody());
      $result = [
        'result' => 1,
        'response' => $object,
        'status_code' => $response->getStatusCode()
      ];
    }

    return $result;
  }

  /**
   * @param $endpoint
   * @param array $data
   * @return array
   */
  public function putData($endpoint, array $data) {
    $result = ['result' => 0, 'response' => NULL, 'code' => 200];
    $resources_url = $this->getResourcesUrl($endpoint);

    try {
      $response = $this->httpClient->put(
        $resources_url,
        [
          'json' => $data,
          'headers' => $this->getHttpHeaders()
        ]
      );
    } catch (RequestException $e) {
      $result = $this->responseOnError($e, $resources_url, $data);
    }

    if (isset($response) && in_array($response->getStatusCode(), [200, 201, 202])) {
      $object = (object) $this->decoder->getDecoder('json')->decode($response->getBody());
      $result = [
        'result' => 1,
        'response' => $object
      ];
    }

    return $result;
  }

  /**
   * @param $endpoint
   * @param array $data
   * @return array
   */
  public function deleteData($endpoint) {
    $result = ['result' => 0, 'response' => ''];
    $resources_url = $this->getResourcesUrl($endpoint);

    try {
      $response = $this->httpClient->delete(
        $resources_url,
        [
          'headers' => $this->getHttpHeaders()
        ]
      );
    } catch (RequestException $e) {
      $result = $this->responseOnError($e, $resources_url);
    }

    if (isset($response) && $response->getStatusCode() == 204) {
      $result['result'] = 1;
    }

    return $result;
  }

  /**
   *
   */
  public function getHttpHeaders() {
    return [
      'X-Api-Key' => $this->apiStorageSettings->get($this->getEntityType()->getBundleEntityType() . '_access_token'),
      'Accept'    => 'application/json',
    ];
  }

  /**
   * @param \GuzzleHttp\Exception\RequestException $exception
   * @param $resources_url
   * @param $data
   * @return array
   */
  protected function responseOnError(RequestException $exception, $resources_url, $data = []) {
    $this->logger->error(sprintf('Fidelio Api Error on : %s with data : %s', $resources_url, \json_encode($data)));

    if($exception->getResponse()) {
      $response_body = (string) $exception->getResponse()->getBody();
      $response_body = $this->decoder->getDecoder('json')->decode($response_body);
    }
    else {
      return [
        'status_code' => 500,
        'exception' => [
          'code'  => 500,
        ],
        'message' => sprintf('response on error %s', \json_encode($data)),
        'result' => FALSE
      ];
    }

    return [
      'status_code'  => $exception->getCode(),
      'exception' => [
        'code' => $response_body['Code'],
        'type' => isset($response_body['ExceptionType']) ? $response_body['ExceptionType'] : $exception->getResponse()->getReasonPhrase(),
      ],
      'message' => $response_body['Message'],
      'result'  => FALSE,
    ];
  }
}
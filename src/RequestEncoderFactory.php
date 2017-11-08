<?php
/**
 * @file
 * Contains
 */

namespace Drupal\api_storage;

use Drupal\Component\Serialization\SerializationInterface;

/**
 * Factory for request encoder.
 */
class RequestEncoderFactory implements RequestEncoderFactoryInterface {
  /**
   * The encoders.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface[]
   */
  protected $encoders = [];

  /**
   * {@inheritdoc}
   */
  public function addEncoder(SerializationInterface $encoder) {
    $this->encoders[$encoder->getFileExtension()] = $encoder;
  }

  /**
   * {@inheritdoc}
   */
  public function getEncoder($format) {
    return isset($this->encoders[$format]) ? $this->encoders[$format] : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFormat($format) {
    return isset($this->encoders[$format]);
  }

  /**
   * {@inheritdoc}
   */
  public function supportedFormats() {
    return array_keys($this->encoders);
  }
}
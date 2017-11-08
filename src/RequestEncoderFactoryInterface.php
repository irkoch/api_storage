<?php
/**
 * @file
 * Contains \Drupal\api_storage\ResponseEncoderFactory.
 */

namespace Drupal\api_storage;

use Drupal\Component\Serialization\SerializationInterface;

/**
 * Factory for response encoders.
 */
interface RequestEncoderFactoryInterface {

  /**
   * Add a encoder.
   *
   * @param \Drupal\Component\Serialization\SerializationInterface $encoder
   *   The encoder.
   */
  public function addEncoder(SerializationInterface $encoder);

  /**
   * Get a encoder for a certain format.
   *
   * @param string $format
   *   The format to get the encoder for.
   *
   * @return \Drupal\Component\Serialization\SerializationInterface|bool
   *   The encoder if it exists, FALSE otherwise.
   */
  public function getEncoder($format);

  /**
   * Checks if a format is supported.
   *
   * @param string $format
   *   The format to check.
   *
   * @return bool
   *   Whether or not the given format is supported.
   */
  public function supportsFormat($format);

  /**
   * Gets the supported formats.
   *
   * @return string[]
   *   An array with the supported formats.
   */
  public function supportedFormats();
}

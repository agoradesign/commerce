<?php

namespace Drupal\commerce_payment\Plugin\Commerce\PaymentType;

use Drupal\Core\Plugin\PluginBase;

/**
 * Defines the class for payment types.
 */
class PaymentType extends PluginBase implements PaymentTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

}

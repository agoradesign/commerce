<?php

namespace Drupal\commerce_payment\Plugin\Commerce\PaymentType;

/**
 * Defines the interface for payment types.
 */
interface PaymentTypeInterface {

  /**
   * Gets the payment type ID.
   *
   * @return string
   *   The payment type ID.
   */
  public function getId();

  /**
   * Gets the translated label.
   *
   * @return string
   *   The translated label.
   */
  public function getLabel();

}

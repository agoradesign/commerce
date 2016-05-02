<?php

namespace Drupal\commerce_payment;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the list builder for payment gateways.
 */
class PaymentGatewayListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  protected $entitiesKey = 'gateways';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_payment_gateways';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Payment gateway');
    $header['label'] = $this->t('Mode');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $entity */
    $gateway_plugin = $entity->getPlugin();
    $modes = $gateway_plugin->getSupportedModes();
    $mode = $gateway_plugin->getMode();
    $row['label'] = $entity->label();
    $row['mode'] = $modes ? $modes[$mode] : $this->t('N/A');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $entities = $this->load();
    // If there's less than 2 gateways, disable dragging.
    if (count($entities) <= 1) {
      unset($this->weightKey);
    }
    return parent::render();
  }

}

<?php

namespace Drupal\commerce_checkout\Entity;

use Drupal\commerce_checkout\CheckoutFlowPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the checkout flow entity class.
 *
 * @ConfigEntityType(
 *   id = "commerce_checkout_flow",
 *   label = @Translation("Checkout flow"),
 *   handlers = {
 *     "list_builder" = "Drupal\commerce_checkout\CheckoutFlowListBuilder",
 *     "form" = {
 *       "add" = "Drupal\commerce_checkout\Form\CheckoutFlowForm",
 *       "edit" = "Drupal\commerce_checkout\Form\CheckoutFlowForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *       "create" = "Drupal\entity\Routing\CreateHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "commerce_checkout_flow",
 *   admin_permission = "administer checkout flows",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/commerce/config/checkout-flows/add",
 *     "edit-form" = "/admin/commerce/config/checkout-flows/manage/{commerce_product_attribute}",
 *     "delete-form" = "/admin/commerce/config/checkout-flows/manage/{commerce_product_attribute}/delete",
 *     "overview-form" = "/admin/commerce/config/checkout-flows/manage/{commerce_product_attribute}/overview",
 *     "collection" =  "/admin/commerce/config/checkout-flows"
 *   }
 * )
 */
class CheckoutFlow extends ConfigEntityBundleBase implements CheckoutFlowInterface {

  /**
   * The checkout flow ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The checkout flow label.
   *
   * @var string
   */
  protected $label;

  /**
   * The plugin ID.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The plugin settings.
   *
   * @var array
   */
  protected $settings = [];

  /**
   * The plugin collection that holds the checkout flow plugin.
   *
   * @var \Drupal\commerce_checkout\CheckoutFlowPluginCollection
   */
  protected $pluginCollection;

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    return $this->getPluginCollection()->get($this->plugin);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return [
      'settings' => $this->getPluginCollection(),
    ];
  }

  /**
   * Gets the plugin collection that holds the checkout flow plugin.
   *
   * Ensures the plugin collection is initialized before returning it.
   *
   * @return \Drupal\commerce_checkout\CheckoutFlowPluginCollection
   *   The plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      $plugin_manager = \Drupal::service('plugin.manager.commerce_checkout_flow');
      $this->pluginCollection = new CheckoutFlowPluginCollection($plugin_manager, $this->plugin, $this->settings, $this->id);
    }
    return $this->pluginCollection;
  }

}

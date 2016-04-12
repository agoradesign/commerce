<?php

/**
 * @file
 * Contains \Drupal\commerce_cart\Tests\AddToCartFormTest.
 */

namespace Drupal\commerce_cart\Tests;

use Drupal\commerce_order\Entity\LineItemInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Tests\OrderTestBase;
use Drupal\commerce_product\Entity\ProductAttribute;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\commerce_product\Entity\ProductVariationTypeInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests the add to cart form.
 *
 * @group commerce
 */
class AddToCartFormTest extends OrderTestBase {

  /**
   * The cart order to test against.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $cart;

  /**
   * The cart manager for test cart operations.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The attribute field manager.
   *
   * @var \Drupal\commerce_product\ProductAttributeFieldManagerInterface
   */
  protected $attributeFieldManager;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'commerce_cart',
    'node',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer products',
      'access content',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->cart = \Drupal::service('commerce_cart.cart_provider')->createCart('default', $this->store);
    $this->cartManager = \Drupal::service('commerce_cart.cart_manager');
    $this->attributeFieldManager = \Drupal::service('commerce_product.attribute_field_manager');
  }

  /**
   * Test adding a product to the cart.
   */
  public function testProductAddToCartForm() {
    // Get the existing product page and submit Add to cart form.
    $this->postAddToCart($this->variation->getProduct());

    // Check if the quantity was increased for the existing line item.
    $this->cart = Order::load($this->cart->id());
    $line_items = $this->cart->getLineItems();
    $this->assertLineItemInOrder($this->variation, $line_items[0]);
  }

  /**
   * Tests adding a product to the cart when there are multiple variations.
   */
  public function testMultipleVariationsAddToCartForm() {
    /** @var \Drupal\commerce_product\Entity\ProductVariationTypeInterface $variation_type */
    $variation_type = ProductVariationType::load($this->variation->bundle());
    $attributes = $this->createAttributeSet($variation_type, 'test', [
      'test1' => 'Testing 1',
      'test2' => 'Testing 2',
      'test3' => 'Testing 3',
    ]);

    // Reload the variation since we have a new field.
    $this->variation = ProductVariation::load($this->variation->id());
    $product = $this->variation->getProduct();

    // Update first variation to have the attribute's value.
    $this->variation->set('attribute_test', $attributes['test1']->id());
    $this->variation->save();

    $variation2 = $this->createEntity('commerce_product_variation', [
      'type' => $variation_type->id(),
      'sku' => $this->randomMachineName(),
      'price' => [
        'amount' => 999,
        'currency_code' => 'USD',
      ],
      'attribute_test' => $attributes['test2']->id(),
    ]);
    $variation2->save();

    $variation3 = $this->createEntity('commerce_product_variation', [
      'type' => $variation_type->id(),
      'sku' => $this->randomMachineName(),
      'price' => [
        'amount' => 999,
        'currency_code' => 'USD',
      ],
      'attribute_test' => $attributes['test3']->id(),
    ]);
    $variation3->save();

    $product->variations->appendItem($variation2);
    $product->variations->appendItem($variation3);
    $product->save();

    // Run the original add to cart test, ensure base variation is added.
    // Get the existing product page and submit Add to cart form.
    $this->postAddToCart($product);
    $this->assertEqual($variation3->attribute_test->target_id, $attributes['test3']->id());
    $this->drupalPostAjaxForm(NULL, [
      'purchased_entity[0][attributes][attribute_test]' => $variation3->attribute_test->target_id,
    ], 'purchased_entity[0][attributes][attribute_test]');
    $this->postAddToCart($product, [
      'purchased_entity[0][attributes][attribute_test]' => $variation3->attribute_test->target_id,
    ]);

    // Check if the quantity was increased for the existing line item.
    $this->cart = Order::load($this->cart->id());
    $line_items = $this->cart->getLineItems();

    $this->assertLineItemInOrder($this->variation, $line_items[0]);
    $this->assertLineItemInOrder($variation3, $line_items[1]);
  }

  /**
   * Tests that attribute field is disabled if only one value.
   */
  public function testProductAttributeDisabledIfOne() {
    /** @var \Drupal\commerce_product\Entity\ProductVariationTypeInterface $variation_type */
    $variation_type = ProductVariationType::load($this->variation->bundle());

    $size_attributes = $this->createAttributeSet($variation_type, 'size', [
      'small' => 'Small',
      'medium' => 'Medium',
      'large' => 'Large',
    ]);
    $color_attributes = $this->createAttributeSet($variation_type, 'color', [
      'red' => 'Red',
    ]);

    // Reload the variation since we have new fields.
    $this->variation = ProductVariation::load($this->variation->id());

    // Get the product so we can append new variations.
    $product = $this->variation->getProduct();

    // Update first variation to have the attribute's value.
    $this->variation->set('attribute_size', $size_attributes['small']->id());
    $this->variation->set('attribute_color', $color_attributes['red']->id());
    $this->variation->save();

    $attribute_values_matrix = [
      ['medium', 'red'],
      ['large', 'red'],
    ];

    $variations = [
      $this->variation,
    ];
    // Generate variations off of the attributes values matrix.
    foreach ($attribute_values_matrix as $key => $value) {
      $variation = $this->createEntity('commerce_product_variation', [
        'type' => $variation_type->id(),
        'sku' => $this->randomMachineName(),
        'price' => [
          'amount' => 999,
          'currency_code' => 'USD',
        ],
        'attribute_size' => $size_attributes[$value[0]]->id(),
        'attribute_color' => $color_attributes[$value[1]]->id(),
      ]);
      $variations[] = $variation;
      $product->variations->appendItem($variation);
    }

    $product->save();

    $this->drupalGet($product->toUrl());
    $selects = $this->xpath('//select[@data-drupal-selector=:data_drupal_selector and @disabled]', [
      ':data_drupal_selector' => 'edit-purchased-entity-0-attributes-attribute-color',
    ]);
    $this->assertTrue(isset($selects[0]));
  }

  /**
   * Tests adding a product to the cart when there are multiple variations.
   */
  public function testMultipleVariationsMultipleAttributes() {
    /** @var \Drupal\commerce_product\Entity\ProductVariationTypeInterface $variation_type */
    $variation_type = ProductVariationType::load($this->variation->bundle());

    $size_attributes = $this->createAttributeSet($variation_type, 'size', [
      'small' => 'Small',
      'medium' => 'Medium',
      'large' => 'Large',
    ]);
    $color_attributes = $this->createAttributeSet($variation_type, 'color', [
      'red' => 'Red',
      'blue' => 'Blue',
    ]);

    // Reload the variation since we have new fields.
    $this->variation = ProductVariation::load($this->variation->id());

    // Get the product so we can append new variations.
    $product = $this->variation->getProduct();

    // Update first variation to have the attribute's value.
    $this->variation->set('attribute_size', $size_attributes['small']->id());
    $this->variation->set('attribute_color', $color_attributes['red']->id());
    $this->variation->save();

    $attribute_values_matrix = [
      ['small', 'blue'],
      ['medium', 'red'],
      ['medium', 'blue'],
      ['large', 'red'],
      ['large', 'blue'],
    ];

    $variations = [
      $this->variation,
    ];
    // Generate variations off of the attributes values matrix.
    foreach ($attribute_values_matrix as $key => $value) {
      $variation = $this->createEntity('commerce_product_variation', [
        'type' => $variation_type->id(),
        'sku' => $this->randomMachineName(),
        'price' => [
          'amount' => 999,
          'currency_code' => 'USD',
        ],
        'attribute_size' => $size_attributes[$value[0]]->id(),
        'attribute_color' => $color_attributes[$value[1]]->id(),
      ]);
      $variations[] = $variation;
      $product->variations->appendItem($variation);
    }

    $product->save();

    // There is no Medium, Red.
    $this->assertAttributeDoesNotExist('edit-purchased-entity-0-attributes-attribute-size', $size_attributes['medium']->id());
    $this->postAddToCart($this->variation->getProduct());

    // Trigger AJAX by changing color attribute.
    $this->drupalPostAjaxForm(NULL, [
      'purchased_entity[0][attributes][attribute_color]' => $color_attributes['red']->id(),
    ], 'purchased_entity[0][attributes][attribute_color]');
    // Trigger AJAX by changing size attribute.
    $this->drupalPostAjaxForm(NULL, [
      'purchased_entity[0][attributes][attribute_size]' => $size_attributes['large']->id(),
    ], 'purchased_entity[0][attributes][attribute_size]');

    $this->assertAttributeExists('edit-purchased-entity-0-attributes-attribute-color', $color_attributes['red']->id());
    $this->assertAttributeExists('edit-purchased-entity-0-attributes-attribute-color', $color_attributes['blue']->id());

    $this->postAddToCart($product, [
      'purchased_entity[0][attributes][attribute_color]' => $color_attributes['red']->id(),
      'purchased_entity[0][attributes][attribute_size]' => $size_attributes['large']->id(),
    ]);

    // Check if the quantity was increased for the existing line item.
    $this->cart = Order::load($this->cart->id());
    $line_items = $this->cart->getLineItems();

    $this->assertLineItemInOrder($variations[0], $line_items[0]);
    $this->assertLineItemInOrder($variations[3], $line_items[1]);
  }

  /**
   * Tests an uneven attribute matrix.
   *
   * 6in-10in are in color "Red", however Green is only 8in-10in.
   */
  public function testAttributeDependencies() {
    /** @var \Drupal\commerce_product\Entity\ProductVariationTypeInterface $variation_type */
    $variation_type = ProductVariationType::load($this->variation->bundle());

    $size_attributes = $this->createAttributeSet($variation_type, 'size', [
      '6' => '6in',
      '7' => '7in',
      '8' => '8in',
      '9' => '9in',
      '10' => '10in',
    ]);
    $color_attributes = $this->createAttributeSet($variation_type, 'color', [
      'red' => 'Red',
      'green' => 'Green',
    ]);

    // Reload the variation since we have new fields.
    $this->variation = ProductVariation::load($this->variation->id());

    // Get the product so we can append new variations.
    $product = $this->variation->getProduct();

    // Update first variation to have the attribute's value.
    $this->variation->set('attribute_size', $size_attributes['6']->id());
    $this->variation->set('attribute_color', $color_attributes['red']->id());
    $this->variation->save();

    $attribute_values_matrix = [
      ['7', 'red'],
      ['8', 'red'],
      ['9', 'red'],
      ['10', 'red'],
      ['8', 'green'],
      ['9', 'green'],
      ['10', 'green'],
    ];

    $variations = [
      $this->variation,
    ];
    // Generate variations off of the attributes values matrix.
    foreach ($attribute_values_matrix as $key => $value) {
      $variation = $this->createEntity('commerce_product_variation', [
        'type' => $variation_type->id(),
        'sku' => $this->randomMachineName(),
        'price' => [
          'amount' => 999,
          'currency_code' => 'USD',
        ],
        'attribute_size' => $size_attributes[$value[0]]->id(),
        'attribute_color' => $color_attributes[$value[1]]->id(),
      ]);
      $variations[] = $variation;
      $product->variations->appendItem($variation);
    }

    $product->save();

    $this->drupalGet($product->toUrl());
    $this->drupalPostAjaxForm(NULL, [
      'purchased_entity[0][attributes][attribute_color]' => $color_attributes['red']->id(),
    ], 'purchased_entity[0][attributes][attribute_color]');

    $this->drupalPostAjaxForm(NULL, [
      'purchased_entity[0][attributes][attribute_size]' => $size_attributes['9']->id(),
    ], 'purchased_entity[0][attributes][attribute_size]');
    // Assert that our color attribute persisted when changing the size.
    $this->assertAttributeSelected('edit-purchased-entity-0-attributes-attribute-color', $color_attributes['red']->id());
    $this->assertAttributeExists('edit-purchased-entity-0-attributes-attribute-color', $color_attributes['green']->id());

    $this->drupalPostAjaxForm(NULL, [
      'purchased_entity[0][attributes][attribute_size]' => $size_attributes['7']->id(),
    ], 'purchased_entity[0][attributes][attribute_size]');
    // Assert that our color attribute persisted when changing the size.
    $this->assertAttributeSelected('edit-purchased-entity-0-attributes-attribute-color', $color_attributes['red']->id());
    $this->assertAttributeExists('edit-purchased-entity-0-attributes-attribute-color', $color_attributes['green']->id());

    $this->drupalPostAjaxForm(NULL, [
      'purchased_entity[0][attributes][attribute_color]' => $color_attributes['green']->id(),
    ], 'purchased_entity[0][attributes][attribute_color]');
    // Assert that our size attribute persisted when changing the color.
    $this->assertAttributeSelected('edit-purchased-entity-0-attributes-attribute-size', $size_attributes['7']->id());
  }

  /**
   * Tests ability to expose line item fields on the add to cart form.
   */
  public function testExposedLineItemFields() {
    /** @var \Drupal\Core\Entity\Entity\EntityFormDisplay $line_item_form_display */
    $line_item_form_display = EntityFormDisplay::load('commerce_line_item.product_variation.add_to_cart');
    $line_item_form_display->setComponent('quantity', [
      'type' => 'number',
    ]);
    $line_item_form_display->save();

    // Get the existing product page and submit Add to cart form.
    $this->postAddToCart($this->variation->getProduct(), [
      'quantity[0][value]' => 3,
    ]);
    // Check if the quantity was increased for the existing line item.
    $this->cart = Order::load($this->cart->id());
    $line_items = $this->cart->getLineItems();

    $this->assertLineItemInOrder($this->variation, $line_items[0], 3);
  }

  /**
   * Creates an attribute field and set of attribute values.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationTypeInterface $variation_type
   *   The variation type.
   * @param string $name
   *   The attribute field name.
   * @param array $options
   *   Associative array of key name values. [red => Red].
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of attribute entities.
   */
  protected function createAttributeSet(ProductVariationTypeInterface $variation_type, $name, array $options) {
    $attribute = ProductAttribute::create([
      'id' => $name,
      'label' => $name,
    ]);
    $attribute->save();
    $this->attributeFieldManager->createField($attribute, $variation_type->id());

    $attribute_set = [];
    foreach ($options as $key => $value) {
      $attribute_set[$key] = $this->createAttributeValue($name, $value);
    }

    return $attribute_set;
  }

  /**
   * Creates an attribute value.
   *
   * @param string $attribute
   *   The attribute id.
   * @param string $name
   *   The attribute value name.
   *
   * @return \Drupal\commerce_product\Entity\ProductAttributeValueInterface
   *   The attribute value entity.
   */
  protected function createAttributeValue($attribute, $name) {
    $attribute_value = $this->createEntity('commerce_product_attribute_value', [
      'attribute' => $attribute,
      'name' => $name,
    ]);
    $attribute_value->save();

    return $attribute_value;
  }

  /**
   * Posts the add to cart form for a product.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product.
   * @param array $edit
   *   The form array.
   *
   * @throws \Exception
   */
  protected function postAddToCart(ProductInterface $product, array $edit = []) {
    $this->drupalGet('product/' . $product->id());
    $this->assertField('edit-submit', t('Add to cart button exists.'));
    $this->drupalPostForm(NULL, $edit, t('Add to cart'));
  }

  /**
   * Asserts that an attribute option is selected.
   *
   * @param string $selector
   *   The element selector.
   * @param string $option
   *   The option.
   */
  protected function assertAttributeSelected($selector, $option) {
    $options = $this->xpath('//select[@data-drupal-selector=:data_drupal_selector]//option[@selected="selected" and @value=:option]', [
      ':data_drupal_selector' => $selector,
      ':option' => $option,
    ]);
    $this->assertFalse(empty($options), 'The attribute is selected');
  }

  /**
   * Asserts that an attribute option does exist.
   *
   * @param string $selector
   *   The element selector.
   * @param string $option
   *   The option.
   */
  protected function assertAttributeExists($selector, $option) {
    $options = $this->xpath('//select[@data-drupal-selector=:data_drupal_selector]//option[@value=:option]', [
      ':data_drupal_selector' => $selector,
      ':option' => $option,
    ]);
    $this->assertFalse(empty($options), 'The attribute is not available');
  }

  /**
   * Asserts that an attribute option does not exist.
   *
   * @param string $selector
   *   The element selector.
   * @param string $option
   *   The option.
   */
  protected function assertAttributeDoesNotExist($selector, $option) {
    $options = $this->xpath('//select[@data-drupal-selector=:data_drupal_selector]//option[@value=:option]', [
      ':data_drupal_selector' => $selector,
      ':option' => $option,
    ]);
    $this->assertTrue(empty($options), 'The attribute is not available');
  }

  /**
   * Assert the line item in the order is correct.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The purchased product variation.
   * @param \Drupal\commerce_order\Entity\LineItemInterface $line_item
   *   The line item.
   * @param int $quantity
   *   The quantity.
   */
  protected function assertLineItemInOrder(ProductVariationInterface $variation, LineItemInterface $line_item, $quantity = 1) {
    $this->assertEqual($line_item->getTitle(), $variation->getLineItemTitle());
    $this->assertTrue(($line_item->getQuantity() == $quantity), t('The product @product has been added to cart with quantity of @quantity.', [
      '@product' => $line_item->getTitle(),
      '@quantity' => $line_item->getQuantity(),
    ]));
  }

}

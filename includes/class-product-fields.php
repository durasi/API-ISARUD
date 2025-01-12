<?php
if (!defined('ABSPATH')) {
   exit;
}

class ApiIsarudProductFields {
   public function __construct() {
       add_action('woocommerce_product_options_inventory_product_data', array($this, 'add_marketplace_fields'));
       add_action('woocommerce_process_product_meta', array($this, 'save_marketplace_fields'));
       add_action('woocommerce_product_bulk_edit_start', array($this, 'add_bulk_edit_fields'));
       add_action('woocommerce_product_bulk_edit_save', array($this, 'save_bulk_edit_fields'));
   }

   public function add_marketplace_fields() {
       wp_nonce_field('isarud_save_marketplace_fields', 'isarud_marketplace_fields_nonce');

       echo '<div class="options_group">';
       
       $fields = array(
           '_trendyol_barcode' => array(
               'label' => __('Trendyol Barkod', 'api-isarud'),
               'desc' => __('Trendyol ürün barkod numarası', 'api-isarud')
           ),
           '_n11_product_id' => array(
               'label' => __('N11 Ürün ID', 'api-isarud'),
               'desc' => __('N11 ürün ID numarası', 'api-isarud')
           ),
           '_hepsiburada_product_id' => array(
               'label' => __('HepsiBurada Ürün ID', 'api-isarud'),
               'desc' => __('HepsiBurada ürün ID numarası', 'api-isarud')
           ),
           '_amazon_asin' => array(
               'label' => __('Amazon ASIN', 'api-isarud'),
               'desc' => __('Amazon ürün ASIN numarası', 'api-isarud')
           ),
           '_pazarama_product_id' => array(
               'label' => __('Pazarama Ürün ID', 'api-isarud'),
               'desc' => __('Pazarama ürün ID numarası', 'api-isarud')
           ),
           '_etsy_listing_id' => array(
               'label' => __('Etsy Listing ID', 'api-isarud'),
               'desc' => __('Etsy ürün listing ID numarası', 'api-isarud')
           ),
           '_shopify_product_id' => array(
               'label' => __('Shopify Product ID', 'api-isarud'),
               'desc' => __('Shopify ürün ID numarası', 'api-isarud')
           ),
           '_shopify_variant_id' => array(
               'label' => __('Shopify Variant ID', 'api-isarud'),
               'desc' => __('Shopify varyant ID numarası', 'api-isarud')
           ),
           '_shopify_inventory_item_id' => array(
               'label' => __('Shopify Inventory Item ID', 'api-isarud'),
               'desc' => __('Shopify envanter öğesi ID numarası', 'api-isarud')
           ),
           '_shopify_location_id' => array(
               'label' => __('Shopify Location ID', 'api-isarud'),
               'desc' => __('Shopify konum ID numarası', 'api-isarud')
           )
       );

       foreach ($fields as $id => $field) {
           woocommerce_wp_text_input(array(
               'id' => $id,
               'label' => $field['label'],
               'desc_tip' => true,
               'description' => $field['desc'],
               'custom_attributes' => array('autocomplete' => 'off')
           ));
       }

       echo '</div>';
   }

   public function save_marketplace_fields($post_id) {
       if (!isset($_POST['isarud_marketplace_fields_nonce']) || 
           !wp_verify_nonce(sanitize_key(wp_unslash($_POST['isarud_marketplace_fields_nonce'])), 'isarud_save_marketplace_fields')) {
           return;
       }

       $fields = array(
           '_trendyol_barcode', '_n11_product_id', '_hepsiburada_product_id',
           '_amazon_asin', '_pazarama_product_id', '_etsy_listing_id',
           '_shopify_product_id', '_shopify_variant_id', '_shopify_inventory_item_id',
           '_shopify_location_id'
       );

       foreach ($fields as $field) {
           if (isset($_POST[$field])) {
               $value = sanitize_text_field(wp_unslash($_POST[$field]));
               update_post_meta($post_id, $field, $value);

               if (!empty($value)) {
                   $this->update_product_mapping($post_id, $field, $value);
               }
           }
       }
   }

   private function update_product_mapping($product_id, $field, $marketplace_id) {
       $marketplace = str_replace(array('_', 'product_id', 'barcode', 'asin'), '', ltrim($field, '_'));
       $cache_key = "isarud_mapping_{$product_id}_{$marketplace}";
       $mapping_id = wp_cache_get($cache_key, 'isarud');

       if ($mapping_id === false) {
           $mapping_id = get_post_meta($product_id, "_isarud_{$marketplace}_mapping_id", true);
       }

       $mapping_data = array(
           'marketplace_product_id' => sanitize_text_field($marketplace_id),
           'last_sync' => current_time('mysql')
       );

       if ($mapping_id) {
           $mapping_data['ID'] = absint($mapping_id);
           wp_update_post($mapping_data);
       } else {
           $mapping_data = array_merge($mapping_data, array(
               'post_type' => 'isarud_mapping',
               'post_status' => 'publish',
               'post_title' => sprintf('Product #%d - %s', $product_id, $marketplace),
               'meta_input' => array(
                   'woo_product_id' => absint($product_id),
                   'marketplace' => sanitize_text_field($marketplace)
               )
           ));
           
           $mapping_id = wp_insert_post($mapping_data);
           if (!is_wp_error($mapping_id)) {
               update_post_meta($product_id, "_isarud_{$marketplace}_mapping_id", $mapping_id);
               wp_cache_set($cache_key, $mapping_id, 'isarud');
           }
       }
   }

   public function add_bulk_edit_fields() {
       wp_nonce_field('isarud_bulk_edit_fields', 'isarud_bulk_edit_nonce');
       
       $fields = array(
           '_trendyol_barcode' => __('Trendyol Barkod', 'api-isarud'),
           '_n11_product_id' => __('N11 Ürün ID', 'api-isarud'),
           '_hepsiburada_product_id' => __('HepsiBurada Ürün ID', 'api-isarud'),
           '_amazon_asin' => __('Amazon ASIN', 'api-isarud'),
           '_pazarama_product_id' => __('Pazarama Ürün ID', 'api-isarud'),
           '_etsy_listing_id' => __('Etsy Listing ID', 'api-isarud'),
           '_shopify_product_id' => __('Shopify Ürün ID', 'api-isarud')
       );

       echo '<div class="inline-edit-group">';
       foreach ($fields as $name => $label) {
           printf(
               '<label class="alignleft"><span class="title">%1$s</span><span class="input-text-wrap"><input type="text" name="%2$s" class="text" value=""></span></label>',
               esc_html($label),
               esc_attr($name)
           );
       }
       echo '</div>';
   }

   public function save_bulk_edit_fields($product) {
       if (!isset($_REQUEST['isarud_bulk_edit_nonce']) || 
           !wp_verify_nonce(sanitize_key(wp_unslash($_REQUEST['isarud_bulk_edit_nonce'])), 'isarud_bulk_edit_fields')) {
           return;
       }

       $fields = array(
           '_trendyol_barcode', '_n11_product_id', '_hepsiburada_product_id',
           '_amazon_asin', '_pazarama_product_id', '_etsy_listing_id',
           '_shopify_product_id'
       );

       foreach ($fields as $field) {
           if (isset($_REQUEST[$field])) {
               $value = sanitize_text_field(wp_unslash($_REQUEST[$field]));
               update_post_meta($product->get_id(), $field, $value);
               
               if (!empty($value)) {
                   $this->update_product_mapping($product->get_id(), $field, $value);
               }
           }
       }
   }
}
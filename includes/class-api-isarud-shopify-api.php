<?php
if (!defined('ABSPATH')) {
   exit;
}

class ApiIsarudShopifyApi {
   private $shop_url;
   private $access_token;
   private $api_version = '2024-01';

   public function __construct($shop_url, $access_token) {
       $this->shop_url = rtrim(sanitize_url($shop_url), '/');
       $this->access_token = sanitize_text_field($access_token);
   }

   private function write_debug_log($type, $message) {
       if (defined('WP_DEBUG') && WP_DEBUG) {
           if (function_exists('wp_debug_log')) {
               wp_debug_log(
                   sprintf(
                       '[API ISARUD - Shopify] %s: %s', 
                       sanitize_text_field($type), 
                       sanitize_text_field($message)
                   )
               );
           }
       }
   }

   private function make_request($endpoint, $method = 'GET', $data = null) {
       if (!$this->shop_url || !$this->access_token) {
           throw new Exception(
               // translators: Error message when Shopify API credentials are incomplete
               esc_html__('Shopify API bilgileri eksik', 'api-isarud')
           );
       }

       $endpoint = sanitize_text_field($endpoint);
       $url = "{$this->shop_url}/admin/api/{$this->api_version}/" . ltrim($endpoint, '/');
       
       $headers = array(
           'X-Shopify-Access-Token' => $this->access_token,
           'Content-Type' => 'application/json',
           'User-Agent' => 'API-ISARUD/1.0'
       );

       $args = array(
           'headers' => $headers,
           'method'  => $method,
           'timeout' => 45,
           'sslverify' => true
       );

       if ($data && in_array($method, array('POST', 'PUT'), true)) {
           array_walk_recursive($data, 'sanitize_text_field');
           $args['body'] = wp_json_encode($data);
       }

       $response = wp_remote_request($url, $args);

       if (is_wp_error($response)) {
           $error_message = $response->get_error_message();
           $this->write_debug_log('API Bağlantı Hatası', $error_message);
           
           // translators: %s is the specific error message from the Shopify API connection
           throw new Exception(
               sprintf(
                   /* translators: %s is the detailed connection error message from the Shopify API */
                   esc_html__('Shopify API Bağlantı Hatası: %s', 'api-isarud'),
                   esc_html($error_message)
               )
           );
       }

       $body = wp_remote_retrieve_body($response);
       $http_code = wp_remote_retrieve_response_code($response);

       if ($http_code < 200 || $http_code >= 300) {
           $this->write_debug_log('API Yanıt Hatası', sprintf("HTTP %d: %s", $http_code, $body));
           
           // translators: %1$d is the HTTP status code, %2$s is the detailed error message from the Shopify API
           throw new Exception(
               sprintf(
                   /* translators: %1$d is the HTTP status code returned by the Shopify API, %2$s is the detailed error message */
                   esc_html__('Shopify API Hatası: HTTP %1$d - %2$s', 'api-isarud'),
                   esc_html($http_code),
                   esc_html($body)
               )
           );
       }

       $decoded_body = json_decode($body);
       if (json_last_error() !== JSON_ERROR_NONE) {
           $this->write_debug_log('JSON Decode Hatası', json_last_error_msg());
           throw new Exception(
               esc_html__('API yanıtı geçersiz format içeriyor', 'api-isarud')
           );
       }

       return $decoded_body;
   }

   public function get_product($product_id) {
       $product_id = absint($product_id);
       if (!$product_id) {
           throw new Exception(
               esc_html__('Geçersiz ürün ID', 'api-isarud')
           );
       }
       return $this->make_request("products/$product_id.json");
   }

   public function update_inventory_level($inventory_item_id, $location_id, $quantity) {
       if (!$inventory_item_id || !$location_id) {
           throw new Exception(
               esc_html__('Envanter bilgileri eksik', 'api-isarud')
           );
       }

       return $this->make_request('inventory_levels/set.json', 'POST', array(
           'inventory_item_id' => sanitize_text_field($inventory_item_id),
           'location_id' => sanitize_text_field($location_id),
           'available' => absint($quantity)
       ));
   }

   public function update_variant($variant_id, $data) {
       $variant_id = absint($variant_id);
       if (!$variant_id) {
           throw new Exception(
               esc_html__('Geçersiz varyant ID', 'api-isarud')
           );
       }

       array_walk_recursive($data, 'sanitize_text_field');
       return $this->make_request("variants/$variant_id.json", 'PUT', array(
           'variant' => $data
       ));
   }

   public function get_orders($params = array()) {
       array_walk_recursive($params, 'sanitize_text_field');
       $query = http_build_query($params);
       return $this->make_request("orders.json?$query");
   }

   public function get_locations() {
       return $this->make_request('locations.json');
   }

   public function prepare_product_data($woo_product) {
       if (!$woo_product || !is_a($woo_product, 'WC_Product')) {
           return null;
       }

       $meta_fields = array(
           'product_id' => '_shopify_product_id',
           'variant_id' => '_shopify_variant_id',
           'inventory_item_id' => '_shopify_inventory_item_id',
           'location_id' => '_shopify_location_id'
       );

       $product_data = array();
       foreach ($meta_fields as $key => $meta_key) {
           $value = get_post_meta($woo_product->get_id(), $meta_key, true);
           if (empty($value)) {
               return null;
           }
           $product_data[$key] = sanitize_text_field($value);
       }

       $product_data['quantity'] = $woo_product->get_stock_quantity();
       $product_data['price'] = $woo_product->get_regular_price('edit');
       $product_data['sku'] = $woo_product->get_sku();

       return $product_data;
   }

   public function batch_update_inventory($items) {
       if (!is_array($items) || empty($items)) {
           throw new Exception(
               esc_html__('Güncellenecek ürün bilgisi gerekli', 'api-isarud')
           );
       }

       foreach ($items as $item) {
           try {
               if (empty($item['inventory_item_id']) || empty($item['location_id'])) {
                   continue;
               }

               $this->update_inventory_level(
                   $item['inventory_item_id'],
                   $item['location_id'],
                   absint($item['quantity'])
               );
           } catch (Exception $e) {
               $this->write_debug_log('Toplu Güncelleme Hatası', $e->getMessage());
               throw $e;
           }
       }
   }
}
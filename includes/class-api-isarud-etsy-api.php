<?php
if (!defined('ABSPATH')) {
   exit;
}

class ApiIsarudEtsyApi {
   private $api_key;
   private $access_token;
   private $shop_id;
   private $base_url = 'https://openapi.etsy.com/v3/';

   public function __construct($api_key, $access_token, $shop_id) {
       $this->api_key = sanitize_text_field($api_key);
       $this->access_token = sanitize_text_field($access_token);
       $this->shop_id = sanitize_text_field($shop_id);
   }

   private function write_debug_log($type, $message) {
       if (defined('WP_DEBUG') && WP_DEBUG) {
           if (function_exists('wp_debug_log')) {
               wp_debug_log(
                   sprintf(
                       '[API ISARUD - Etsy] %s: %s', 
                       sanitize_text_field($type), 
                       sanitize_text_field($message)
                   )
               );
           }
       }
   }

   private function make_request($endpoint, $method = 'GET', $data = null) {
       if (!$this->api_key || !$this->access_token || !$this->shop_id) {
           throw new Exception(
               // translators: Error message when Etsy API credentials are incomplete
               esc_html__('Etsy API bilgileri eksik', 'api-isarud')
           );
       }

       $endpoint = sanitize_text_field($endpoint);
       $url = $this->base_url . ltrim($endpoint, '/');
       
       $headers = array(
           'Authorization' => 'Bearer ' . $this->access_token,
           'x-api-key' => $this->api_key,
           'Accept' => 'application/json',
           'Content-Type' => 'application/json',
           'User-Agent' => 'API-ISARUD/1.0'
       );

       $args = array(
           'headers' => $headers,
           'method'  => $method,
           'timeout' => 45,
           'sslverify' => true
       );

       if ($data && in_array($method, array('POST', 'PUT', 'PATCH'), true)) {
           array_walk_recursive($data, 'sanitize_text_field');
           $args['body'] = wp_json_encode($data);
       }

       $response = wp_remote_request($url, $args);

       if (is_wp_error($response)) {
           $error_message = $response->get_error_message();
           $this->write_debug_log('API Bağlantı Hatası', $error_message);
           
           // translators: %s is the specific error message from the Etsy API connection
           throw new Exception(
               sprintf(
                   /* translators: %s is the detailed connection error message from the Etsy API */
                   esc_html__('Etsy API Bağlantı Hatası: %s', 'api-isarud'),
                   esc_html($error_message)
               )
           );
       }

       $body = wp_remote_retrieve_body($response);
       $http_code = wp_remote_retrieve_response_code($response);

       if ($http_code < 200 || $http_code >= 300) {
           $this->write_debug_log('API Yanıt Hatası', sprintf("HTTP %d: %s", $http_code, $body));
           
           // translators: %1$d is the HTTP status code, %2$s is the detailed error message from the Etsy API
           throw new Exception(
               sprintf(
                   /* translators: %1$d is the HTTP status code returned by the Etsy API, %2$s is the detailed error message */
                   esc_html__('Etsy API Hatası: HTTP %1$d - %2$s', 'api-isarud'),
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

   public function get_listings($limit = 25, $offset = 0) {
       $params = array(
           'limit' => min(100, absint($limit)),
           'offset' => absint($offset)
       );

       return $this->make_request(
           "application/shops/{$this->shop_id}/listings?" . http_build_query($params)
       );
   }

   public function get_listing($listing_id) {
       if (empty($listing_id)) {
           throw new Exception(
               esc_html__('Listing ID gerekli', 'api-isarud')
           );
       }
       return $this->make_request("application/listings/" . sanitize_text_field($listing_id));
   }

   public function update_inventory($listing_id, $products) {
       if (empty($listing_id)) {
           throw new Exception(
               esc_html__('Listing ID gerekli', 'api-isarud')
           );
       }

       if (!is_array($products)) {
           $products = array($products);
       }

       $data = array(
           'products' => array_map(function($product) {
               return array(
                   'sku' => sanitize_text_field($product['sku']),
                   'property_values' => isset($product['properties']) ? 
                       array_map('sanitize_text_field', $product['properties']) : array(),
                   'offerings' => array(
                       array(
                           'quantity' => absint($product['quantity']),
                           'price' => array(
                               'amount' => floatval($product['price']),
                               'divisor' => 100,
                               'currency_code' => 'USD'
                           )
                       )
                   )
               );
           }, $products)
       );

       return $this->make_request("application/listings/$listing_id/inventory", 'PUT', $data);
   }

   public function get_receipts($limit = 25, $offset = 0) {
       $params = array(
           'limit' => min(100, absint($limit)),
           'offset' => absint($offset)
       );

       return $this->make_request(
           "application/shops/{$this->shop_id}/receipts?" . http_build_query($params)
       );
   }

   public function prepare_product_data($woo_product) {
       if (!$woo_product || !is_a($woo_product, 'WC_Product')) {
           return null;
       }

       $etsy_listing_id = get_post_meta($woo_product->get_id(), '_etsy_listing_id', true);
       if (empty($etsy_listing_id)) {
           return null;
       }

       return array(
           'listing_id' => sanitize_text_field($etsy_listing_id),
           'sku' => sanitize_text_field($woo_product->get_sku()),
           'quantity' => absint($woo_product->get_stock_quantity()),
           'price' => floatval($woo_product->get_regular_price('edit')) * 100,
           'properties' => array()
       );
   }

   public function create_listing($data) {
       if (empty($data)) {
           throw new Exception(
               esc_html__('Ürün bilgisi gerekli', 'api-isarud')
           );
       }

       return $this->make_request('application/listings', 'POST', $data);
   }

   public function update_listing($listing_id, $data) {
       if (empty($listing_id)) {
           throw new Exception(
               esc_html__('Listing ID gerekli', 'api-isarud')
           );
       }

       return $this->make_request("application/listings/$listing_id", 'PUT', $data);
   }

   public function delete_listing($listing_id) {
       if (empty($listing_id)) {
           throw new Exception(
               esc_html__('Listing ID gerekli', 'api-isarud')
           );
       }

       return $this->make_request("application/listings/$listing_id", 'DELETE');
   }
}
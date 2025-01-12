<?php
if (!defined('ABSPATH')) {
   exit;
}

class ApiIsarudHepsiburadaApi {
   private $username;
   private $password;
   private $merchant_id;
   private $base_url = 'https://mpop.hepsiburada.com/api/';

   public function __construct($username, $password, $merchant_id) {
       $this->username = sanitize_text_field($username);
       $this->password = sanitize_text_field($password);
       $this->merchant_id = sanitize_text_field($merchant_id);
   }

   private function write_debug_log($type, $message) {
       if (defined('WP_DEBUG') && WP_DEBUG) {
           if (function_exists('wp_debug_log')) {
               wp_debug_log(
                   sprintf(
                       '[API ISARUD - HepsiBurada] %s: %s', 
                       sanitize_text_field($type), 
                       sanitize_text_field($message)
                   )
               );
           }
       }
   }

   private function make_request($endpoint, $method = 'GET', $data = null) {
       if (!$this->username || !$this->password || !$this->merchant_id) {
           throw new Exception(
               // translators: Error message when HepsiBurada API credentials are incomplete
               esc_html__('HepsiBurada API bilgileri eksik', 'api-isarud')
           );
       }

       $endpoint = sanitize_text_field($endpoint);
       $url = $this->base_url . $endpoint;
       
       $headers = array(
           'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
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
           
           // translators: %s is the specific error message from the HepsiBurada API connection
           throw new Exception(
               sprintf(
                   /* translators: %s is the specific error message from the failed API connection */
                   esc_html__('HepsiBurada API Bağlantı Hatası: %s', 'api-isarud'),
                   esc_html($error_message)
               )
           );
       }

       $body = wp_remote_retrieve_body($response);
       $http_code = wp_remote_retrieve_response_code($response);

       if ($http_code !== 200) {
           $this->write_debug_log('API Yanıt Hatası', sprintf("HTTP %d: %s", $http_code, $body));
           
           // translators: %1$d is the HTTP status code, %2$s is the detailed error message from the API
           throw new Exception(
               sprintf(
                   /* translators: %1$d is the HTTP status code returned by the API, %2$s is the detailed error message */
                   esc_html__('HepsiBurada API Hatası: HTTP %1$d - %2$s', 'api-isarud'),
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
       if (empty($product_id)) {
           throw new Exception(
               esc_html__('Ürün ID gerekli', 'api-isarud')
           );
       }
       return $this->make_request("merchants/{$this->merchant_id}/products/" . sanitize_text_field($product_id));
   }

   public function update_stock($items) {
       if (empty($items)) {
           throw new Exception(
               esc_html__('Güncellenecek ürün bilgisi gerekli', 'api-isarud')
           );
       }

       if (!is_array($items)) {
           $items = array($items);
       }

       $data = array(
           'items' => array_map(function($item) {
               return array(
                   'merchantId' => $this->merchant_id,
                   'productId' => sanitize_text_field($item['product_id']),
                   'quantity' => absint($item['quantity']),
                   'stockCode' => isset($item['stock_code']) ? sanitize_text_field($item['stock_code']) : ''
               );
           }, $items)
       );

       return $this->make_request(
           "merchants/{$this->merchant_id}/products/inventory",
           'POST',
           $data
       );
   }

   public function batch_update_stocks($products) {
       if (empty($products)) {
           return null;
       }

       $chunks = array_chunk($products, 50);
       $results = array();

       foreach ($chunks as $chunk) {
           try {
               $result = $this->update_stock($chunk);
               $results[] = $result;
           } catch (Exception $e) {
               $this->write_debug_log('Toplu Güncelleme Hatası', $e->getMessage());
               throw $e;
           }
       }

       return $results;
   }

   public function get_products($offset = 0, $limit = 50) {
       $params = array(
           'offset' => absint($offset),
           'limit' => min(200, absint($limit))
       );
       
       return $this->make_request(
           "merchants/{$this->merchant_id}/products?" . http_build_query($params)
       );
   }

   public function update_price($items) {
       if (empty($items)) {
           throw new Exception(
               esc_html__('Fiyat bilgisi gerekli', 'api-isarud')
           );
       }

       if (!is_array($items)) {
           $items = array($items);
       }

       $data = array(
           'items' => array_map(function($item) {
               return array(
                   'merchantId' => $this->merchant_id,
                   'productId' => sanitize_text_field($item['product_id']),
                   'price' => floatval($item['price']),
                   'listPrice' => isset($item['list_price']) ? floatval($item['list_price']) : floatval($item['price'])
               );
           }, $items)
       );

       return $this->make_request(
           "merchants/{$this->merchant_id}/products/price",
           'POST',
           $data
       );
   }

   public function get_orders($status = null, $offset = 0, $limit = 50) {
       $params = array(
           'offset' => absint($offset),
           'limit' => min(200, absint($limit))
       );

       if ($status) {
           $params['status'] = sanitize_text_field($status);
       }

       return $this->make_request(
           "merchants/{$this->merchant_id}/orders?" . http_build_query($params)
       );
   }

   public function prepare_product_data($woo_product) {
       if (!$woo_product || !is_a($woo_product, 'WC_Product')) {
           return null;
       }

       $hb_product_id = get_post_meta($woo_product->get_id(), '_hepsiburada_product_id', true);
       if (empty($hb_product_id)) {
           return null;
       }

       return array(
           'product_id' => sanitize_text_field($hb_product_id),
           'quantity' => absint($woo_product->get_stock_quantity()),
           'stock_code' => sanitize_text_field($woo_product->get_sku()),
           'price' => floatval($woo_product->get_price('edit')),
           'list_price' => floatval($woo_product->get_regular_price('edit'))
       );
   }
}
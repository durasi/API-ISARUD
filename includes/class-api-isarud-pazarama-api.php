<?php
if (!defined('ABSPATH')) {
   exit;
}

class ApiIsarudPazaramaApi {
   private $api_key;
   private $api_secret;
   private $seller_id;
   private $base_url = 'https://api.pazarama.com/seller/';
   private $access_token = null;
   private $token_expires = null;

   public function __construct($api_key, $api_secret, $seller_id) {
       $this->api_key = sanitize_text_field($api_key);
       $this->api_secret = sanitize_text_field($api_secret);
       $this->seller_id = sanitize_text_field($seller_id);
   }

   private function get_access_token() {
       if ($this->access_token && $this->token_expires > time()) {
           return $this->access_token;
       }

       // translators: %s is the specific error message from the Pazarama API connection attempt
       $error_message_template = esc_html__('Pazarama API Bağlantı Hatası: %s', 'api-isarud');

       $response = wp_remote_post($this->base_url . 'oauth/token', array(
           'headers' => array(
               'Content-Type' => 'application/x-www-form-urlencoded'
           ),
           'body' => array(
               'grant_type' => 'client_credentials',
               'client_id' => $this->api_key,
               'client_secret' => $this->api_secret
           ),
           'timeout' => 45,
           'sslverify' => true
       ));

       if (is_wp_error($response)) {
           $error_message = $response->get_error_message();
           $this->write_debug_log('Token Alma Hatası', $error_message);
           
           // translators: %s is the specific error message from the failed API connection
           throw new Exception(
               sprintf(
                   // translators: %s is the specific error message from the Pazarama API connection attempt
                   esc_html__('Pazarama API Bağlantı Hatası: %s', 'api-isarud'), 
                   esc_html($error_message)
               )
           );
       }

       $body = json_decode(wp_remote_retrieve_body($response));
       if (!isset($body->access_token)) {
           $this->write_debug_log('Token Alma Hatası', 'Token alınamadı');
           throw new Exception(
               esc_html__('Pazarama API token alınamadı', 'api-isarud')
           );
       }

       $this->access_token = sanitize_text_field($body->access_token);
       $this->token_expires = time() + absint($body->expires_in);

       return $this->access_token;
   }

   private function make_request($endpoint, $method = 'GET', $data = null) {
       if (!$this->api_key || !$this->api_secret || !$this->seller_id) {
           throw new Exception(
               esc_html__('Pazarama API bilgileri eksik', 'api-isarud')
           );
       }

       // translators: %1$d is the HTTP status code, %2$s is the detailed error body from the API
       $error_message_template = esc_html__('Pazarama API Hatası: HTTP %1$d - %2$s', 'api-isarud');

       $endpoint = sanitize_text_field($endpoint);
       $url = $this->base_url . ltrim($endpoint, '/');
       
       $headers = array(
           'Authorization' => 'Bearer ' . $this->get_access_token(),
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
           
           // translators: %s is the specific error message from the failed API connection
           throw new Exception(
               sprintf(
                   // translators: %s is the specific error message from the Pazarama API connection attempt
                   esc_html__('Pazarama API Bağlantı Hatası: %s', 'api-isarud'), 
                   esc_html($error_message)
               )
           );
       }

       $body = wp_remote_retrieve_body($response);
       $http_code = wp_remote_retrieve_response_code($response);

       if ($http_code < 200 || $http_code >= 300) {
           $this->write_debug_log('API Yanıt Hatası', sprintf("HTTP %d: %s", $http_code, $body));
           
           // translators: %1$d is the HTTP status code, %2$s is the detailed error message from the API
           throw new Exception(
               sprintf(
                   // translators: %1$d is the HTTP status code, %2$s is the detailed error body from the API
                   esc_html__('Pazarama API Hatası: HTTP %1$d - %2$s', 'api-isarud'),
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

   /**
    * Güvenli hata günlüğü için geliştirilmiş log fonksiyonu
    * 
    * @param string $type Hata türü
    * @param string $message Hata mesajı
    */
   private function write_debug_log($type, $message) {
       // WordPress'in dahili günlük mekanizmasını kullan
       if (defined('WP_DEBUG') && WP_DEBUG) {
           if (function_exists('wp_debug_log')) {
               wp_debug_log(
                   sprintf(
                       '[Pazarama API] %s: %s', 
                       sanitize_text_field($type), 
                       sanitize_text_field($message)
                   )
               );
           }
       }
   }

   // Diğer metodlar aynı kalacak
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
                   'productId' => sanitize_text_field($item['product_id']),
                   'price' => floatval($item['price']),
                   'listPrice' => floatval($item['list_price'] ?? $item['price'])
               );
           }, $items)
       );

       return $this->make_request('prices/update', 'POST', $data);
   }

   public function get_orders($status = null, $page = 1, $limit = 50) {
       $params = array(
           'page' => max(1, absint($page)),
           'limit' => min(200, absint($limit))
       );

       if ($status) {
           $params['status'] = sanitize_text_field($status);
       }

       return $this->make_request('orders?' . http_build_query($params));
   }

   public function prepare_product_data($woo_product) {
       if (!$woo_product || !is_a($woo_product, 'WC_Product')) {
           return null;
       }

       $pazarama_product_id = get_post_meta($woo_product->get_id(), '_pazarama_product_id', true);
       if (empty($pazarama_product_id)) {
           return null;
       }

       return array(
           'product_id' => sanitize_text_field($pazarama_product_id),
           'quantity' => absint($woo_product->get_stock_quantity()),
           'stock_code' => sanitize_text_field($woo_product->get_sku()),
           'price' => floatval($woo_product->get_price('edit')),
           'list_price' => floatval($woo_product->get_regular_price('edit'))
       );
   }
}
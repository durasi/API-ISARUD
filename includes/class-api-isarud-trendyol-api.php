<?php
/**
* Trendyol API Integration
*/

if (!defined('ABSPATH')) {
   exit;
}

class ApiIsarudTrendyolApi {
   private $api_key;
   private $api_secret;
   private $supplier_id;
   private $base_url = 'https://api.trendyol.com/sapigw/suppliers/';

   public function __construct($api_key, $api_secret, $supplier_id) {
       $this->api_key = sanitize_text_field($api_key);
       $this->api_secret = sanitize_text_field($api_secret);
       $this->supplier_id = sanitize_text_field($supplier_id);
   }

   private function write_debug_log($type, $message) {
       if (defined('WP_DEBUG') && WP_DEBUG) {
           if (function_exists('wp_debug_log')) {
               wp_debug_log(
                   sprintf(
                       '[API ISARUD - Trendyol] %s: %s', 
                       sanitize_text_field($type), 
                       sanitize_text_field($message)
                   )
               );
           }
       }
   }

   private function make_request($endpoint, $method = 'GET', $data = null) {
       if (!$this->api_key || !$this->api_secret || !$this->supplier_id) {
           throw new Exception(
               // translators: Error message when Trendyol API credentials are incomplete
               esc_html__('Trendyol API bilgileri eksik', 'api-isarud')
           );
       }

       // Endpoint güvenlik kontrolü
       $endpoint = sanitize_text_field($endpoint);
       
       $url = $this->base_url . $endpoint;
       
       $headers = array(
           'Authorization' => 'Basic ' . base64_encode($this->api_key . ':' . $this->api_secret),
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
           // Veri güvenlik kontrolü
           if (is_array($data)) {
               array_walk_recursive($data, 'sanitize_text_field');
           }
           $args['body'] = wp_json_encode($data);
       }

       $response = wp_remote_request($url, $args);

       if (is_wp_error($response)) {
           $error_message = $response->get_error_message();
           $this->write_debug_log('API Bağlantı Hatası', $error_message);
           
           // translators: %s is the specific error message from the Trendyol API connection
           throw new Exception(
               sprintf(
                   /* translators: %s is the detailed connection error message from the Trendyol API */
                   esc_html__('Trendyol API Bağlantı Hatası: %s', 'api-isarud'),
                   esc_html($error_message)
               )
           );
       }

       $body = wp_remote_retrieve_body($response);
       $http_code = wp_remote_retrieve_response_code($response);

       if ($http_code !== 200) {
           $this->write_debug_log('API Yanıt Hatası', sprintf("HTTP %d: %s", $http_code, $body));
           
           // translators: %1$d is the HTTP status code, %2$s is the detailed error message from the Trendyol API
           throw new Exception(
               sprintf(
                   /* translators: %1$d is the HTTP status code returned by the Trendyol API, %2$s is the detailed error message */
                   esc_html__('Trendyol API Hatası: HTTP %1$d - %2$s', 'api-isarud'),
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

   public function get_product($barcode) {
       if (empty($barcode)) {
           throw new Exception(
               esc_html__('Barkod bilgisi gerekli', 'api-isarud')
           );
       }
       return $this->make_request($this->supplier_id . '/products?barcode=' . urlencode($barcode));
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
                   'barcode' => sanitize_text_field($item['barcode']),
                   'quantity' => absint($item['stock']),
                   'salePrice' => isset($item['sale_price']) ? floatval($item['sale_price']) : null,
                   'listPrice' => isset($item['list_price']) ? floatval($item['list_price']) : null
               );
           }, $items)
       );

       return $this->make_request($this->supplier_id . '/products/price-and-inventory', 'POST', $data);
   }

   public function get_products($page = 1, $size = 50, $approved = true) {
       $endpoint = $this->supplier_id . '/products';
       $params = array(
           'page' => max(1, absint($page)),
           'size' => min(100, absint($size))
       );
       
       if ($approved) {
           $params['approved'] = 'true';
       }

       return $this->make_request($endpoint . '?' . http_build_query($params));
   }

   public function get_orders($status = 'Created', $page = 0, $size = 50) {
       $endpoint = $this->supplier_id . '/orders';
       $params = array(
           'status' => sanitize_text_field($status),
           'page' => max(0, absint($page)),
           'size' => min(200, absint($size))
       );

       return $this->make_request($endpoint . '?' . http_build_query($params));
   }

   public function batch_update_products($products) {
       if (empty($products)) {
           return null;
       }

       $chunks = array_chunk($products, 100);
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

   public function prepare_product_data($woo_product) {
       if (!$woo_product || !is_a($woo_product, 'WC_Product')) {
           return null;
       }

       $barcode = get_post_meta($woo_product->get_id(), '_trendyol_barcode', true);
       if (empty($barcode)) {
           return null;
       }

       return array(
           'barcode' => sanitize_text_field($barcode),
           'stock' => $woo_product->get_stock_quantity(),
           'sale_price' => $woo_product->get_sale_price('edit'),
           'list_price' => $woo_product->get_regular_price('edit')
       );
   }
}
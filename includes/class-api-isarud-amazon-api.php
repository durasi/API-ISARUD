<?php
if (!defined('ABSPATH')) {
   exit;
}

class ApiIsarudAmazonApi {
   private $access_key;
   private $secret_key;
   private $seller_id;
   private $marketplace_id;
   private $base_url = 'https://mws.amazonservices.com.tr/';

   public function __construct($access_key, $secret_key, $seller_id, $marketplace_id) {
       $this->access_key = sanitize_text_field($access_key);
       $this->secret_key = sanitize_text_field($secret_key);
       $this->seller_id = sanitize_text_field($seller_id);
       $this->marketplace_id = sanitize_text_field($marketplace_id);
   }

   private function write_debug_log($type, $message) {
       if (defined('WP_DEBUG') && WP_DEBUG) {
           if (function_exists('wp_debug_log')) {
               wp_debug_log(
                   sprintf(
                       '[API ISARUD - Amazon] %s: %s', 
                       sanitize_text_field($type), 
                       sanitize_text_field($message)
                   )
               );
           }
       }
   }

   private function sign($string_to_sign) {
       return base64_encode(hash_hmac('sha256', $string_to_sign, $this->secret_key, true));
   }

   private function make_request($action, $params = array()) {
       if (!$this->access_key || !$this->secret_key || !$this->seller_id || !$this->marketplace_id) {
           throw new Exception(
               // translators: Error message when Amazon API credentials are incomplete
               esc_html__('Amazon API bilgileri eksik', 'api-isarud')
           );
       }

       $params = array_merge(array(
           'Action' => sanitize_text_field($action),
           'Version' => '2009-01-01',
           'AWSAccessKeyId' => $this->access_key,
           'SellerId' => $this->seller_id,
           'MarketplaceId' => $this->marketplace_id,
           'SignatureMethod' => 'HmacSHA256',
           'SignatureVersion' => '2',
           'Timestamp' => gmdate('Y-m-d\TH:i:s\Z')
       ), $params);

       array_walk_recursive($params, 'sanitize_text_field');
       ksort($params);

       $string_to_sign = "POST\n";
       $string_to_sign .= wp_parse_url($this->base_url, PHP_URL_HOST) . "\n";
       $string_to_sign .= "/\n";
       $string_to_sign .= http_build_query($params);

       $params['Signature'] = $this->sign($string_to_sign);

       $args = array(
           'method' => 'POST',
           'timeout' => 45,
           'sslverify' => true,
           'headers' => array(
               'Content-Type' => 'application/x-www-form-urlencoded',
               'User-Agent' => 'API-ISARUD/1.0'
           ),
           'body' => $params
       );

       $response = wp_remote_post($this->base_url, $args);

       if (is_wp_error($response)) {
           $error_message = $response->get_error_message();
           $this->write_debug_log('API Bağlantı Hatası', $error_message);
           
           // translators: %s is the specific connection error message returned by the Amazon API
           throw new Exception(
               sprintf(
                   /* translators: %s is the specific connection error message returned by the Amazon API */
                   esc_html__('Amazon API Hatası: %s', 'api-isarud'), 
                   esc_html($error_message)
               )
           );
       }

       $body = wp_remote_retrieve_body($response);
       $http_code = wp_remote_retrieve_response_code($response);

       if ($http_code !== 200) {
           $this->write_debug_log('API Yanıt Hatası', sprintf("HTTP %d: %s", $http_code, $body));
           
           // translators: %1$d is the HTTP status code returned by the Amazon API, %2$s is the detailed error message
           throw new Exception(
               sprintf(
                   /* translators: %1$d is the HTTP status code returned by the Amazon API, %2$s is the detailed error message */
                   esc_html__('Amazon API Hatası: HTTP %1$d - %2$s', 'api-isarud'),
                   esc_html($http_code),
                   esc_html($body)
               )
           );
       }

       $xml = simplexml_load_string($body);
       if ($xml === false) {
           $this->write_debug_log('XML Parse Hatası', 'Geçersiz XML yanıtı');
           throw new Exception(
               esc_html__('API yanıtı geçersiz format içeriyor', 'api-isarud')
           );
       }

       return $xml;
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

       $messages = array();
       foreach ($items as $item) {
           $messages[] = array(
               'MessageID' => uniqid(),
               'OperationType' => 'Update',
               'Inventory' => array(
                   'SKU' => sanitize_text_field($item['sku']),
                   'Quantity' => absint($item['quantity']),
                   'FulfillmentLatency' => isset($item['latency']) ? absint($item['latency']) : 1
               )
           );
       }

       return $this->make_request('SubmitInventoryUpdate', array(
           'Messages' => $messages
       ));
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

       $messages = array();
       foreach ($items as $item) {
           $messages[] = array(
               'MessageID' => uniqid(),
               'OperationType' => 'Update',
               'Price' => array(
                   'SKU' => sanitize_text_field($item['sku']),
                   'StandardPrice' => array(
                       '_value' => floatval($item['price']),
                       'currency' => 'TRY'
                   )
               )
           );
       }

       return $this->make_request('SubmitPriceUpdate', array(
           'Messages' => $messages
       ));
   }

   public function get_orders($created_after = null, $order_status = array('Unshipped', 'PartiallyShipped')) {
       $params = array(
           'CreatedAfter' => $created_after ?? gmdate('Y-m-d\TH:i:s\Z', strtotime('-7 days')),
           'OrderStatus' => array_map('sanitize_text_field', $order_status)
       );

       return $this->make_request('ListOrders', $params);
   }

   public function get_product_by_asin($asin) {
       if (empty($asin)) {
           throw new Exception(
               esc_html__('ASIN gerekli', 'api-isarud')
           );
       }

       return $this->make_request('GetMatchingProduct', array(
           'ASINList' => array(sanitize_text_field($asin))
       ));
   }

   public function prepare_product_data($woo_product) {
       if (!$woo_product || !is_a($woo_product, 'WC_Product')) {
           return null;
       }

       $amazon_asin = get_post_meta($woo_product->get_id(), '_amazon_asin', true);
       if (empty($amazon_asin)) {
           return null;
       }

       return array(
           'sku' => sanitize_text_field($woo_product->get_sku() ?: $amazon_asin),
           'quantity' => absint($woo_product->get_stock_quantity()),
           'price' => floatval($woo_product->get_regular_price('edit')),
           'asin' => sanitize_text_field($amazon_asin)
       );
   }

   public function request_inventory_report() {
       return $this->make_request('RequestReport', array(
           'ReportType' => '_GET_MERCHANT_LISTINGS_DATA_'
       ));
   }

   public function get_report_status($report_id) {
       if (empty($report_id)) {
           throw new Exception(
               esc_html__('Rapor ID gerekli', 'api-isarud')
           );
       }

       return $this->make_request('GetReportRequestStatus', array(
           'ReportRequestId' => sanitize_text_field($report_id)
       ));
   }

   public function get_report($report_id) {
       if (empty($report_id)) {
           throw new Exception(
               esc_html__('Rapor ID gerekli', 'api-isarud')
           );
       }

       return $this->make_request('GetReport', array(
           'ReportId' => sanitize_text_field($report_id)
       ));
   }
}
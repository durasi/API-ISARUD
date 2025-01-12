<?php
if (!defined('ABSPATH')) {
   exit;
}

class ApiIsarudN11Api {
   private $api_key;
   private $api_secret;
   private $client;
   private $base_url = 'https://api.n11.com/ws/';

   public function __construct($api_key, $api_secret) {
       $this->api_key = sanitize_text_field($api_key);
       $this->api_secret = sanitize_text_field($api_secret);
   }

   private function write_debug_log($type, $message) {
       if (defined('WP_DEBUG') && WP_DEBUG) {
           if (function_exists('wp_debug_log')) {
               wp_debug_log(
                   sprintf(
                       '[API ISARUD - N11] %s: %s', 
                       sanitize_text_field($type), 
                       sanitize_text_field($message)
                   )
               );
           }
       }
   }

   private function init_client($service) {
       if (!$this->api_key || !$this->api_secret) {
           throw new Exception(
               // translators: Error message when N11 API credentials are incomplete
               esc_html__('N11 API bilgileri eksik', 'api-isarud')
           );
       }

       try {
           $this->client = new SoapClient($this->base_url . sanitize_text_field($service) . '?wsdl', array(
               'trace' => 1,
               'encoding' => 'UTF-8',
               'cache_wsdl' => WSDL_CACHE_NONE,
               'stream_context' => stream_context_create(array(
                   'ssl' => array(
                       'verify_peer' => false,
                       'verify_peer_name' => false
                   )
               )),
               'user_agent' => 'API-ISARUD/1.0'
           ));
       } catch (Exception $e) {
           $this->write_debug_log('SOAP İstemci Hatası', $e->getMessage());
           
           // translators: %s is the specific error message from the SOAP client connection attempt
           throw new Exception(
               sprintf(
                   /* translators: %s is the detailed connection error message from the SOAP client */
                   esc_html__('N11 API Bağlantı Hatası: %s', 'api-isarud'), 
                   esc_html($e->getMessage())
               )
           );
       }
   }

   private function get_auth_data() {
       return array(
           'appKey' => $this->api_key,
           'appSecret' => $this->api_secret
       );
   }

   public function get_product($product_id) {
       if (empty($product_id)) {
           throw new Exception(
               esc_html__('Ürün ID gerekli', 'api-isarud')
           );
       }

       $this->init_client('productService');

       $request = array_merge($this->get_auth_data(), array(
           'productId' => sanitize_text_field($product_id)
       ));

       try {
           $response = $this->client->GetProductByProductId($request);
           if ($response->result->status !== 'success') {
               $this->write_debug_log('Ürün Getirme Hatası', $response->result->errorMessage);
               
               // translators: %s is the error message from the N11 API when product retrieval fails
               $product_error_template = esc_html__('N11 Ürün Getirme Hatası: %s', 'api-isarud');
               throw new Exception(
                   sprintf(
                       /* translators: %s is the specific error message explaining why product retrieval failed */
                       $product_error_template,
                       esc_html($response->result->errorMessage)
                   )
               );
           }
           return $response->product;
       } catch (Exception $e) {
           $this->write_debug_log('Ürün Getirme Hatası', $e->getMessage());
           throw $e;
       }
   }

   public function update_stock($product_id, $quantity, $version = null) {
       if (empty($product_id)) {
           throw new Exception(
               esc_html__('Ürün ID gerekli', 'api-isarud')
           );
       }

       $this->init_client('productStockService');

       $request = array_merge($this->get_auth_data(), array(
           'productSellerCode' => sanitize_text_field($product_id),
           'quantity' => absint($quantity)
       ));

       if ($version !== null) {
           $request['version'] = sanitize_text_field($version);
       }

       try {
           $response = $this->client->UpdateStockBySellerCode($request);
           if ($response->result->status !== 'success') {
               $this->write_debug_log('Stok Güncelleme Hatası', $response->result->errorMessage);
               
               // translators: %s is the error message from the N11 API during stock update
               $stock_error_template = esc_html__('N11 Stok Güncelleme Hatası: %s', 'api-isarud');
               throw new Exception(
                   sprintf(
                       /* translators: %s is the specific error message explaining why stock update failed */
                       $stock_error_template,
                       esc_html($response->result->errorMessage)
                   )
               );
           }
           return $response->result;
       } catch (Exception $e) {
           $this->write_debug_log('Stok Güncelleme Hatası', $e->getMessage());
           throw $e;
       }
   }

   public function batch_update_stocks($products) {
       if (empty($products)) {
           throw new Exception(
               esc_html__('Güncellenecek ürün bilgisi gerekli', 'api-isarud')
           );
       }

       $this->init_client('productStockService');

       $stocks = array();
       foreach ($products as $product) {
           $stocks[] = array(
               'sellerCode' => sanitize_text_field($product['product_id']),
               'quantity' => absint($product['quantity']),
               'version' => isset($product['version']) ? sanitize_text_field($product['version']) : null
           );
       }

       $request = array_merge($this->get_auth_data(), array(
           'productStockList' => $stocks
       ));

       try {
           $response = $this->client->UpdateStockByStockSellerCode($request);
           if ($response->result->status !== 'success') {
               $this->write_debug_log('Toplu Güncelleme Hatası', $response->result->errorMessage);
               
               // translators: %s is the error message from the N11 API during bulk stock update
               $bulk_stock_error_template = esc_html__('N11 Toplu Stok Güncelleme Hatası: %s', 'api-isarud');
               throw new Exception(
                   sprintf(
                       /* translators: %s is the specific error message explaining why bulk stock update failed */
                       $bulk_stock_error_template,
                       esc_html($response->result->errorMessage)
                   )
               );
           }
           return $response->result;
       } catch (Exception $e) {
           $this->write_debug_log('Toplu Güncelleme Hatası', $e->getMessage());
           throw $e;
       }
   }

   public function update_price($product_id, $price, $discount_price = null) {
       if (empty($product_id)) {
           throw new Exception(
               esc_html__('Ürün ID gerekli', 'api-isarud')
           );
       }

       $this->init_client('productService');

       $request = array_merge($this->get_auth_data(), array(
           'productSellerCode' => sanitize_text_field($product_id),
           'price' => floatval($price),
           'currencyType' => 'TL'
       ));

       if ($discount_price !== null) {
           $request['displayPrice'] = floatval($price);
           $request['price'] = floatval($discount_price);
       }

       try {
           $response = $this->client->UpdateProductPriceBySellerCode($request);
           if ($response->result->status !== 'success') {
               $this->write_debug_log('Fiyat Güncelleme Hatası', $response->result->errorMessage);
               
               // translators: %s is the error message from the N11 API during price update
               $price_error_template = esc_html__('N11 Fiyat Güncelleme Hatası: %s', 'api-isarud');
               throw new Exception(
                   sprintf(
                       /* translators: %s is the specific error message explaining why price update failed */
                       $price_error_template,
                       esc_html($response->result->errorMessage)
                   )
               );
           }
           return $response->result;
       } catch (Exception $e) {
           $this->write_debug_log('Fiyat Güncelleme Hatası', $e->getMessage());
           throw $e;
       }
   }

   public function get_orders($status = null, $page = 0, $size = 100) {
       $this->init_client('orderService');

       $request = array_merge($this->get_auth_data(), array(
           'pagingData' => array(
               'currentPage' => absint($page),
               'pageSize' => min(200, absint($size))
           )
       ));

       if ($status) {
           $request['status'] = sanitize_text_field($status);
       }

       try {
           $response = $this->client->OrderList($request);
           if ($response->result->status !== 'success') {
               $this->write_debug_log('Sipariş Listesi Hatası', $response->result->errorMessage);
               
               // translators: %s is the error message from the N11 API when retrieving order list
               $order_list_error_template = esc_html__('N11 Sipariş Listesi Hatası: %s', 'api-isarud');
               throw new Exception(
                   sprintf(
                       /* translators: %s is the specific error message explaining why order list retrieval failed */
                       $order_list_error_template,
                       esc_html($response->result->errorMessage)
                   )
               );
           }
           return $response->orderList;
       } catch (Exception $e) {
           $this->write_debug_log('Sipariş Listesi Hatası', $e->getMessage());
           throw $e;
       }
   }

   public function prepare_product_data($woo_product) {
       if (!$woo_product || !is_a($woo_product, 'WC_Product')) {
           return null;
       }

       $n11_product_id = get_post_meta($woo_product->get_id(), '_n11_product_id', true);
       if (empty($n11_product_id)) {
           return null;
       }

       return array(
           'product_id' => sanitize_text_field($n11_product_id),
           'quantity' => absint($woo_product->get_stock_quantity()),
           'version' => get_post_meta($woo_product->get_id(), '_n11_product_version', true)
       );
   }
}
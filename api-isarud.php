<?php
/**
* Plugin Name: API ISARUD
* Plugin URI: https://www.isarud.com/products/api-isarud
* Description: Pazaryeri API entegrasyonları ile stok yönetimi
* Version: 1.0.0
* Requires at least: 5.0
* Requires PHP: 8.0
* Author: ISARUD
* Author URI: https://www.isarud.com
* Text Domain: api-isarud
* License: GPL v2 or later
*/

// Güvenlik kontrolü
defined('ABSPATH') or die;

// Plugin aktivasyon kontrolü
function api_isarud_activation_check() {
    if (!class_exists('WooCommerce')) {
        // Hata mesajı göster
        add_action('admin_notices', 'api_isarud_admin_notice');
        
        // Eklentiyi deaktive et
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        deactivate_plugins(plugin_basename(__FILE__));
        
        // Aktivasyon hatasını önle
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

// Admin uyarı mesajı
function api_isarud_admin_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('API ISARUD eklentisi için WooCommerce gereklidir. Lütfen önce WooCommerce\'i yükleyin ve aktifleştirin.', 'api-isarud'); ?></p>
    </div>
    <?php
}

// Aktivasyon hook'u
register_activation_hook(__FILE__, 'api_isarud_activation_check');

// Plugin sınıfı
class ApiIsarud {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // WooCommerce kurulu mu diye kontrol et
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Admin menüsü ve ayarlar
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Ajax işlemleri
        add_action('wp_ajax_isarud_sync_stock', array($this, 'sync_stock'));

        // Ürün alanları
        if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-product-fields.php')) {
            require_once plugin_dir_path(__FILE__) . 'includes/class-product-fields.php';
            new ApiIsarudProductFields();
        }
    }

    public function enqueue_admin_scripts() {
        // Enqueue CSS with version number
        wp_enqueue_style(
            'api-isarud-admin', 
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            array(), // No dependencies
            '1.0.0' // Plugin version for cache busting
        );

        // Enqueue JS with version number and load in footer
        wp_enqueue_script(
            'api-isarud-admin', 
            plugin_dir_url(__FILE__) . 'assets/js/admin.js', 
            array('jquery'), // Dependencies
            '1.0.0', // Plugin version for cache busting
            true // Load in footer
        );

        // Localize script with AJAX parameters
        wp_localize_script('api-isarud-admin', 'apiIsarud', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('isarud_ajax_nonce')
        ));
    }

    // Aktivasyon fonksiyonu
    public function activate() {
        // Veritabanı tablolarını oluştur
        $this->create_tables();
        // Varsayılan ayarları kaydet
        $this->save_default_settings();
    }

    // Deaktivasyon fonksiyonu
    public function deactivate() {
        // Gerekli temizleme işlemleri
    }

    // Veritabanı tablolarını oluşturma
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // API kimlik bilgileri tablosu
        $table_name = $wpdb->prefix . 'isarud_api_credentials';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            marketplace varchar(50) NOT NULL,
            api_key varchar(255) NOT NULL,
            api_secret varchar(255) NOT NULL,
            seller_id varchar(100),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Ürün eşleştirme tablosu       
        $table_name = $wpdb->prefix . 'isarud_product_mapping';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            woo_product_id bigint(20) NOT NULL,
            marketplace varchar(50) NOT NULL,
            marketplace_product_id varchar(100) NOT NULL,
            last_sync datetime,
            PRIMARY KEY  (id),
            KEY woo_product_id (woo_product_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Senkronizasyon log tablosu
        $table_name = $wpdb->prefix . 'isarud_sync_log';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            marketplace varchar(50) NOT NULL,
            action varchar(50) NOT NULL,            
            status varchar(20) NOT NULL,
            message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)  
        ) $charset_collate;";
        dbDelta($sql);
    }

    // Admin menüsü ekleme
    public function add_admin_menu() {
        add_menu_page(
            __('API ISARUD', 'api-isarud'),
            __('API ISARUD', 'api-isarud'),
            'manage_options',
            'api-isarud',
            array($this, 'admin_page'),
            'dashicons-networking',
            56
        );

        // Alt menüler
        add_submenu_page(
            'api-isarud',
            __('API Ayarları', 'api-isarud'),
            __('API Ayarları', 'api-isarud'),
            'manage_options',
            'api-isarud-settings',
            array($this, 'settings_page')            
        );
    }

    // Admin ana sayfa
    public function admin_page() {
        include plugin_dir_path(__FILE__) . 'templates/admin-page.php';        
    }

    // Ayarlar sayfası
    public function settings_page() {
        include plugin_dir_path(__FILE__) . 'templates/settings-page.php';
    }

    // Ayarları kaydetme
    public function register_settings() {
       $api_options = array(
           'type' => 'array',
           'sanitize_callback' => array($this, 'sanitize_api_options')
       );

       register_setting('api_isarud_options', 'api_isarud_trendyol', $api_options);
       register_setting('api_isarud_options', 'api_isarud_n11', $api_options); 
       register_setting('api_isarud_options', 'api_isarud_hepsiburada', $api_options);
       register_setting('api_isarud_options', 'api_isarud_amazon', $api_options);
       register_setting('api_isarud_options', 'api_isarud_pazarama', $api_options);
       register_setting('api_isarud_options', 'api_isarud_etsy', $api_options);
       register_setting('api_isarud_options', 'api_isarud_shopify', $api_options);
    }

    // Ayar değerlerini temizle
    public function sanitize_api_options($options) {
       foreach ($options as $key => $value) {
           $options[$key] = sanitize_text_field($value);
       }
       return $options;
    }

    // Stok senkronizasyonu
    public function sync_stock() {
        // Güvenlik kontrolü
        check_ajax_referer('isarud_ajax_nonce', 'nonce');

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (!$product_id) {
            wp_send_json_error('Geçersiz ürün ID');
        }

        try {
            // Tüm pazaryerlerinde stok güncelle  
            $this->sync_trendyol_stock($product_id);
            $this->sync_n11_stock($product_id);             
            $this->sync_hepsiburada_stock($product_id);
            $this->sync_amazon_stock($product_id);
            $this->sync_pazarama_stock($product_id);
            $this->sync_etsy_stock($product_id);
            $this->sync_shopify_stock($product_id);

            wp_send_json_success('Stok başarıyla güncellendi');
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    // Trendyol stok güncelleme
    private function sync_trendyol_stock($product_id) {
        $api_settings = get_option('api_isarud_trendyol');
        if (empty($api_settings['api_key']) || empty($api_settings['api_secret']) || empty($api_settings['seller_id'])) {
            throw new Exception('Trendyol API bilgileri eksik');
        }

        try {
            // Trendyol API sınıfını yükle
            require_once plugin_dir_path(__FILE__) . 'includes/class-api-isarud-trendyol-api.php';
            
            // API bağlantısını başlat
            $api = new ApiIsarudTrendyolApi(
                $api_settings['api_key'],
                $api_settings['api_secret'],
                $api_settings['seller_id']
            );

            // WooCommerce ürününü al
            $woo_product = wc_get_product($product_id);
            if (!$woo_product) {
                throw new Exception('WooCommerce ürünü bulunamadı');
            }

            // Ürün verisini hazırla
            $product_data = $api->prepare_product_data($woo_product);
            if (!$product_data) {
                throw new Exception('Trendyol barkodu bulunamadı');  
            }

            // Stok güncelle
            $result = $api->update_stock($product_data);

            // Log kaydı oluştur
            $log_data = array(
                'product_id' => $product_id,
                'marketplace' => 'trendyol',
                'action' => 'stock_update',
                'status' => 'success',
                'message' => 'Stok başarıyla güncellendi',
                'created_at' => current_time('mysql')
            );
            $this->insert_log($log_data);

            // Son senkronizasyon zamanını güncelle
            update_option('api_isarud_trendyol_last_sync', current_time('mysql'));

            return $result;
        } catch (Exception $e) {
            // Hata logla
            $log_data = array(
                'product_id' => $product_id,  
                'marketplace' => 'trendyol',
                'action' => 'stock_update',
                'status' => 'error',
                'message' => $e->getMessage(),
                'created_at' => current_time('mysql')
            );
            $this->insert_log($log_data);

            throw $e;
        }
    }

    // N11 stok güncelleme
    private function sync_n11_stock($product_id) {
        $api_settings = get_option('api_isarud_n11');
        if (empty($api_settings['api_key']) || empty($api_settings['api_secret'])) {
            throw new Exception('N11 API bilgileri eksik');
        }

        try {
            // N11 API sınıfını yükle
            require_once plugin_dir_path(__FILE__) . 'includes/class-api-isarud-n11-api.php';
            
            // API bağlantısını başlat
            $api = new ApiIsarudN11Api(
                $api_settings['api_key'],
                $api_settings['api_secret']
            );

            // WooCommerce ürününü al
            $woo_product = wc_get_product($product_id);
            if (!$woo_product) {
                throw new Exception('WooCommerce ürünü bulunamadı');
            }

            // Ürün verisini hazırla
            $product_data = $api->prepare_product_data($woo_product);
            if (!$product_data) {
                throw new Exception('N11 ürün ID\'si bulunamadı');
            }

            // Stok güncelle
            $result = $api->update_stock(
                $product_data['product_id'],
                $product_data['quantity'],
                $product_data['version']
            );

            // Başarılı sonucu logla
            $log_data = array(
                'product_id' => $product_id,
                'marketplace' => 'n11',
                'action' => 'stock_update',
                'status' => 'success',
                'message' => 'Stok başarıyla güncellendi',
                'created_at' => current_time('mysql')
            );
            $this->insert_log($log_data);

            // Son senkronizasyon zamanını güncelle 
            update_option('api_isarud_n11_last_sync', current_time('mysql'));

            return $result;
        } catch (Exception $e) {
            // Hatayı logla
            $log_data = array(
                'product_id' => $product_id,
                'marketplace' => 'n11',  
                'action' => 'stock_update',
                'status' => 'error',
                'message' => $e->getMessage(),
                'created_at' => current_time('mysql')
            );
            $this->insert_log($log_data);

            throw $e;
        }
    }

    // HepsiBurada stok güncelleme
    private function sync_hepsiburada_stock($product_id) {
        $api_settings = get_option('api_isarud_hepsiburada');
        if (empty($api_settings['username']) || empty($api_settings['password']) || empty($api_settings['merchant_id'])) {
            throw new Exception('HepsiBurada API bilgileri eksik');
        }

        try {
            // HepsiBurada API sınıfını yükle
            require_once plugin_dir_path(__FILE__) . 'includes/class-api-isarud-hepsiburada-api.php';
            
            // API bağlantısını başlat
            $api = new ApiIsarudHepsiburadaApi(
                $api_settings['username'],
                $api_settings['password'],
                $api_settings['merchant_id']
            );

            // WooCommerce ürününü al
            $woo_product = wc_get_product($product_id);
            if (!$woo_product) {
                throw new Exception('WooCommerce ürünü bulunamadı');
            }

            // Ürün verisini hazırla
            $product_data = $api->prepare_product_data($woo_product);
            if (!$product_data) {
                throw new Exception('HepsiBurada ürün ID\'si bulunamadı');
            }

            // Stok güncelle
            $result = $api->update_stock($product_data);

            // Başarılı sonucu logla
            $log_data = array(
                'product_id' => $product_id,
                'marketplace' => 'hepsiburada',
                'action' => 'stock_update',
                'status' => 'success',
                'message' => 'Stok başarıyla güncellendi',
                'created_at' => current_time('mysql')
            );
            $this->insert_log($log_data);

            // Son senkronizasyon zamanını güncelle
            update_option('api_isarud_hepsiburada_last_sync', current_time('mysql'));

            return $result;
        } catch (Exception $e) {
            // Hatayı logla    
            $log_data = array(
                'product_id' => $product_id,
                'marketplace' => 'hepsiburada',
                'action' => 'stock_update', 
                'status' => 'error',
                'message' => $e->getMessage(),
                'created_at' => current_time('mysql')  
            );
            $this->insert_log($log_data);

            throw $e;
        }
    }

    // Amazon stok güncelleme
    private function sync_amazon_stock($product_id) {
        $api_settings = get_option('api_isarud_amazon');
        if (empty($api_settings['access_key']) || empty($api_settings['secret_key']) || empty($api_settings['seller_id']) || empty($api_settings['marketplace_id'])) {
            throw new Exception('Amazon API bilgileri eksik');
        }

        try {
        // Amazon API sınıfını yükle
        require_once plugin_dir_path(__FILE__) . 'includes/class-api-isarud-amazon-api.php';
        
        // API bağlantısını başlat
        $api = new ApiIsarudAmazonApi(
            $api_settings['access_key'],
            $api_settings['secret_key'],
            $api_settings['seller_id'],
            $api_settings['marketplace_id']
        );

        // WooCommerce ürününü al
        $woo_product = wc_get_product($product_id);
        if (!$woo_product) {
            throw new Exception('WooCommerce ürünü bulunamadı');
        }

        // Ürün verisini hazırla
        $product_data = $api->prepare_product_data($woo_product);
        if (!$product_data) {
            throw new Exception('Amazon ASIN bulunamadı');
        }

        // Stok güncelle
        $result = $api->update_stock($product_data);

        // Başarılı sonucu logla
        $log_data = array(
            'product_id' => $product_id,
            'marketplace' => 'amazon',
            'action' => 'stock_update',
            'status' => 'success', 
            'message' => 'Stok başarıyla güncellendi',
            'created_at' => current_time('mysql')
        );
        $this->insert_log($log_data);

        // Son senkronizasyon zamanını güncelle  
        update_option('api_isarud_amazon_last_sync', current_time('mysql'));
        
        return $result;
            } catch (Exception $e) {
                // Hatayı logla
                $log_data = array(
                    'product_id' => $product_id,
                    'marketplace' => 'amazon',
                    'action' => 'stock_update',
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'created_at' => current_time('mysql')
                );  
                $this->insert_log($log_data);

                throw $e;
            }
        }

        // Pazarama stok güncelleme
        private function sync_pazarama_stock($product_id) {
            $api_settings = get_option('api_isarud_pazarama');
            if (empty($api_settings['api_key']) || empty($api_settings['api_secret']) || empty($api_settings['seller_id'])) {
                throw new Exception('Pazarama API bilgileri eksik');
            }

            try {
                // Pazarama API sınıfını yükle
                require_once plugin_dir_path(__FILE__) . 'includes/class-api-isarud-pazarama-api.php';
                
                // API bağlantısını başlat
                $api = new ApiIsarudPazaramaApi(
                    $api_settings['api_key'],
                    $api_settings['api_secret'],
                    $api_settings['seller_id']    
                );

                // WooCommerce ürününü al
                $woo_product = wc_get_product($product_id);
                if (!$woo_product) {
                    throw new Exception('WooCommerce ürünü bulunamadı');
                }

                // Ürün verisini hazırla    
                $product_data = $api->prepare_product_data($woo_product);
                if (!$product_data) {
                    throw new Exception('Pazarama ürün ID\'si bulunamadı'); 
                }

                // Stok güncelle
                $result = $api->update_stock($product_data);

                // Başarılı sonucu logla
                $log_data = array(
                    'product_id' => $product_id,
                    'marketplace' => 'pazarama',
                    'action' => 'stock_update',
                    'status' => 'success',
                    'message' => 'Stok başarıyla güncellendi',
                    'created_at' => current_time('mysql')
                );
                $this->insert_log($log_data);

                // Son senkronizasyon zamanını güncelle
                update_option('api_isarud_pazarama_last_sync', current_time('mysql'));

                return $result;
            } catch (Exception $e) {
                // Hatayı logla      
                $log_data = array(
                    'product_id' => $product_id,  
                    'marketplace' => 'pazarama',
                    'action' => 'stock_update',
                    'status' => 'error',
                    'message' => $e->getMessage(), 
                    'created_at' => current_time('mysql')
                );
                $this->insert_log($log_data);
                
                throw $e;
            }
        }

        // Etsy stok senkronizasyonu
        private function sync_etsy_stock($product_id) {
            $api_settings = get_option('api_isarud_etsy');
            if (empty($api_settings['api_key']) || empty($api_settings['access_token']) || empty($api_settings['shop_id'])) {
                throw new Exception('Etsy API bilgileri eksik');
            }

            try {
                // Etsy API sınıfını yükle  
                require_once plugin_dir_path(__FILE__) . 'includes/class-api-isarud-etsy-api.php';
                
                // API bağlantısını başlat
                $api = new ApiIsarudEtsyApi(
                    $api_settings['api_key'],
                    $api_settings['access_token'],
                    $api_settings['shop_id']
                );

                // WooCommerce ürününü al
                $woo_product = wc_get_product($product_id);
                if (!$woo_product) {
                    throw new Exception('WooCommerce ürünü bulunamadı');
                }

                // Ürün verisini hazırla
                $product_data = $api->prepare_product_data($woo_product);
                if (!$product_data) {
                    throw new Exception('Etsy listing ID bulunamadı');
                }
                
                // Stok güncelle
                $result = $api->update_inventory($product_data['listing_id'], $product_data);

                // Başarılı sonucu logla
                $log_data = array(
                    'product_id' => $product_id,
                    'marketplace' => 'etsy', 
                    'action' => 'stock_update',
                    'status' => 'success',
                    'message' => 'Stok başarıyla güncellendi',
                    'created_at' => current_time('mysql')
                );
                $this->insert_log($log_data);

                // Son senkronizasyon zamanını güncelle
                update_option('api_isarud_etsy_last_sync', current_time('mysql'));
                
                return $result;
            } catch (Exception $e) {
                // Hatayı logla
                $log_data = array(
                    'product_id' => $product_id,
                    'marketplace' => 'etsy',
                    'action' => 'stock_update',
                    'status' => 'error',  
                    'message' => $e->getMessage(),
                    'created_at' => current_time('mysql')
                );
                $this->insert_log($log_data);
                
                throw $e;
            }
        }

        // Shopify stok senkronizasyonu 
        private function sync_shopify_stock($product_id) {
            $api_settings = get_option('api_isarud_shopify');
            if (empty($api_settings['shop_url']) || empty($api_settings['access_token'])) {
                throw new Exception('Shopify API bilgileri eksik');
            }

            try {
                // Shopify API sınıfını yükle
                require_once plugin_dir_path(__FILE__) . 'includes/class-api-isarud-shopify-api.php';
                
                // API bağlantısını başlat
                $api = new ApiIsarudShopifyApi(
                    $api_settings['shop_url'],
                    $api_settings['access_token']
                );

                // WooCommerce ürününü al  
                $woo_product = wc_get_product($product_id);
                if (!$woo_product) {
                    throw new Exception('WooCommerce ürünü bulunamadı');
                }

                // Ürün verisini hazırla
                $product_data = $api->prepare_product_data($woo_product);
                if (!$product_data) {
                    throw new Exception('Shopify ürün bilgileri bulunamadı');
                }

                // Stok güncelle
                $result = $api->update_inventory_level(
                    $product_data['inventory_item_id'],
                    $product_data['location_id'],
                    $product_data['quantity']
                );

                // Başarılı sonucu logla
                $log_data = array(
                    'product_id' => $product_id,
                    'marketplace' => 'shopify',
                    'action' => 'stock_update',
                    'status' => 'success',
                    'message' => 'Stok başarıyla güncellendi',
                    'created_at' => current_time('mysql')
                );
                $this->insert_log($log_data);

                // Son senkronizasyon zamanını güncelle
                update_option('api_isarud_shopify_last_sync', current_time('mysql'));

                return $result;
            } catch (Exception $e) {
                // Hatayı logla
                $log_data = array(
                    'product_id' => $product_id,
                    'marketplace' => 'shopify',
                    'action' => 'stock_update',
                    'status' => 'error',
                    'message' => $e->getMessage(), 
                    'created_at' => current_time('mysql')  
                );
                $this->insert_log($log_data);

                throw $e;
            }
        }

        // Log kayıtları oluşturma
        private function insert_log($data) {
            $log_post = array(
                'post_type'   => 'isarud_log',
                'post_status' => 'publish',
                'post_author' => 1,
                'meta_input'  => array(
                    'marketplace' => sanitize_text_field($data['marketplace']),
                    'action'      => sanitize_text_field($data['action']),
                    'status'      => sanitize_text_field($data['status']),
                    'message'     => sanitize_textarea_field($data['message'])
                ),  
            );
            wp_insert_post($log_post);
        }

        // WooCommerce kontrol metodu
        public function check_woocommerce() {
            if (!class_exists('WooCommerce')) {
                deactivate_plugins(plugin_basename(__FILE__));
                add_action('admin_notices', function() {
                    ?>
                    <div class="notice notice-error">
                        <p><?php esc_html_e('API ISARUD eklentisi için WooCommerce gereklidir. Lütfen WooCommerce\'i yükleyin ve aktifleştirin.', 'api-isarud'); ?></p>
                    </div>
                    <?php
                });
            }
        }

        private function save_default_settings() {
            // Trendyol varsayılan ayarları
            if (false === get_option('api_isarud_trendyol')) {
                add_option('api_isarud_trendyol', array(
                    'api_key' => '',
                    'api_secret' => '',
                    'seller_id' => ''
                ));
            }

            // N11 varsayılan ayarları
            if (false === get_option('api_isarud_n11')) {
                add_option('api_isarud_n11', array(
                    'api_key' => '',
                    'api_secret' => ''
                ));
            }

            // HepsiBurada varsayılan ayarları
            if (false === get_option('api_isarud_hepsiburada')) {
                add_option('api_isarud_hepsiburada', array(
                    'username' => '',
                    'password' => '',
                    'merchant_id' => ''
                ));
            }

            // Amazon varsayılan ayarları
            if (false === get_option('api_isarud_amazon')) {
                add_option('api_isarud_amazon', array(
                    'access_key' => '',
                    'secret_key' => '',
                    'seller_id' => '',
                    'marketplace_id' => ''  
                ));
            }

            // Pazarama varsayılan ayarları
            if (false === get_option('api_isarud_pazarama')) {
                add_option('api_isarud_pazarama', array(
                    'api_key' => '',
                    'api_secret' => '',
                    'seller_id' => ''
                ));   
            }

            // Etsy varsayılan ayarları
            if (false === get_option('api_isarud_etsy')) {
                add_option('api_isarud_etsy', array(
                    'api_key' => '',
                    'access_token' => '',
                    'shop_id' => ''
                ));
            }

            // Shopify varsayılan ayarları
            if (false === get_option('api_isarud_shopify')) {
                add_option('api_isarud_shopify', array(
                    'shop_url' => '',
                    'access_token' => ''
                ));
            }

            // Son senkronizasyon zamanları için varsayılan değerler
            $marketplaces = array('trendyol', 'n11', 'hepsiburada', 'amazon', 'pazarama', 'etsy', 'shopify');
            foreach ($marketplaces as $marketplace) {
                if (false === get_option("api_isarud_{$marketplace}_last_sync")) {
                    add_option("api_isarud_{$marketplace}_last_sync", '');
                }
            }
        }
    }
// Plugin başlangıcı
function api_isarud_init() {
    if (class_exists('WooCommerce')) {
        return ApiIsarud::get_instance();
    }
}
add_action('plugins_loaded', 'api_isarud_init');
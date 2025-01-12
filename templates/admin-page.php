<?php 
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-marketplace-settings.php';

$marketplaces = ApiIsarudMarketplaceSettings::get_marketplaces();
?>
<div class="wrap">
   <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
   
   <div class="notice notice-info is-dismissible">
       <p><?php echo esc_html__('Tüm pazaryeri entegrasyonlarını bu sayfadan yönetebilirsiniz.', 'api-isarud'); ?></p>
   </div>

   <div class="card">
       <h2><?php echo esc_html__('Pazaryeri Entegrasyonları', 'api-isarud'); ?></h2>
       <table class="wp-list-table widefat fixed striped">
           <thead>
               <tr>
                   <th><?php echo esc_html__('Pazaryeri', 'api-isarud'); ?></th>
                   <th><?php echo esc_html__('Durum', 'api-isarud'); ?></th>
                   <th><?php echo esc_html__('Son Senkronizasyon', 'api-isarud'); ?></th>
                   <th><?php echo esc_html__('İşlemler', 'api-isarud'); ?></th>
               </tr>
           </thead>
           <tbody>
               <?php foreach ($marketplaces as $key => $marketplace) :
                   $settings = get_option("api_isarud_{$key}");
                   
                   $is_active = match ($key) {
                       'etsy' => !empty($settings['api_key']) && !empty($settings['access_token']) && !empty($settings['shop_id']),
                       'shopify' => !empty($settings['shop_url']) && !empty($settings['access_token']),
                       default => !empty($settings['api_key']) && !empty($settings['api_secret'])
                   };
                   
                   $last_sync = get_option("api_isarud_{$key}_last_sync");
               ?>
               <tr>
                   <td><?php echo esc_html($marketplace['name']); ?></td>
                   <td>
                       <span class="status-<?php echo $is_active ? 'active' : 'inactive'; ?>">
                           <?php echo $is_active ? esc_html__('Aktif', 'api-isarud') : esc_html__('Pasif', 'api-isarud'); ?>
                       </span>
                   </td>
                   <td>
                       <?php echo $last_sync ? esc_html(gmdate('d.m.Y H:i:s', strtotime($last_sync))) : '-'; ?>
                   </td>
                   <td>
                       <button class="button sync-stock" 
                               data-marketplace="<?php echo esc_attr($key); ?>"
                               <?php echo !$is_active ? 'disabled' : ''; ?>>
                           <?php echo esc_html__('Stok Güncelle', 'api-isarud'); ?>
                       </button>
                   </td>
               </tr>
               <?php endforeach; ?>
           </tbody>
       </table>
   </div>

   <div class="card">
       <h2><?php echo esc_html__('Son İşlem Logları', 'api-isarud'); ?></h2>
       <table class="wp-list-table widefat fixed striped">
           <thead>
               <tr>
                   <th><?php echo esc_html__('Tarih', 'api-isarud'); ?></th>
                   <th><?php echo esc_html__('Pazaryeri', 'api-isarud'); ?></th>
                   <th><?php echo esc_html__('İşlem', 'api-isarud'); ?></th>
                   <th><?php echo esc_html__('Durum', 'api-isarud'); ?></th>
                   <th><?php echo esc_html__('Mesaj', 'api-isarud'); ?></th>
               </tr>
           </thead>
           <tbody>
               <?php
               $logs = get_transient('isarud_sync_logs');

               if (!$logs) {
                   $logs = new WP_Query(array(
                       'post_type' => 'isarud_log',
                       'posts_per_page' => 10,
                       'orderby' => 'date',
                       'order' => 'DESC'
                   ));
                   
                   set_transient('isarud_sync_logs', $logs, HOUR_IN_SECONDS);
               }

               if ($logs->have_posts()) :
                   while ($logs->have_posts()) : $logs->the_post();
                       $log_meta = get_post_meta(get_the_ID());
               ?>
               <tr>
                   <td><?php echo esc_html(gmdate('d.m.Y H:i:s', get_the_time('U'))); ?></td>
                   <td><?php echo esc_html($log_meta['marketplace'][0]); ?></td>
                   <td><?php echo esc_html($log_meta['action'][0]); ?></td>
                   <td><?php echo esc_html($log_meta['status'][0]); ?></td>
                   <td><?php echo esc_html($log_meta['message'][0]); ?></td>
               </tr>
               <?php 
                   endwhile;
               else :
               ?>
               <tr>
                   <td colspan="5"><?php echo esc_html__('Henüz log kaydı bulunmuyor.', 'api-isarud'); ?></td>
               </tr>
               <?php 
                   endif;
                   wp_reset_postdata(); 
               ?>
           </tbody>
       </table>
   </div>

   <div class="card">
       <h2><?php echo esc_html__('Destek ve Güncellemeler', 'api-isarud'); ?></h2>
       <div class="support-links">
           <div class="link-box">
               <h3><?php echo esc_html__('Son Güncellemeler', 'api-isarud'); ?></h3>
               <p><?php echo esc_html__('Eklentinin son sürümü ve yenilikler hakkında bilgi alın.', 'api-isarud'); ?></p>
               <a href="https://www.isarud.com/products/api-isarud" target="_blank" class="button button-secondary">
                   <?php echo esc_html__('Güncellemeleri Görüntüle', 'api-isarud'); ?>
               </a>
           </div>
           <div class="link-box">
               <h3><?php echo esc_html__('Bağış Yapın', 'api-isarud'); ?></h3>
               <p><?php echo esc_html__('Eklentinin geliştirilmesine destek olun.', 'api-isarud'); ?></p>
               <a href="https://donate.stripe.com/dR69Dl5HqcsR3N65kl" target="_blank" class="button button-primary">
                   <?php echo esc_html__('Bağış Yap', 'api-isarud'); ?>
               </a>
           </div>
       </div>
   </div>
</div>
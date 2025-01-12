<?php
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-marketplace-settings.php';

$marketplaces = ApiIsarudMarketplaceSettings::get_marketplaces();
?>
<div class="wrap">
   <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
   
   <form method="post" action="options.php">
       <?php 
       settings_fields('api_isarud_options'); 
       do_settings_sections('api_isarud_options'); 
       ?>

       <?php foreach ($marketplaces as $key => $marketplace) :
           $options = get_option("api_isarud_{$key}", array());
           $is_active = !empty($options['api_key']) || !empty($options['access_key']) || 
                       !empty($options['username']) || !empty($options['shop_url']);
       ?>
       <div class="card">
           <div class="card-header">
               <h2>
                   <?php echo esc_html($marketplace['name']); ?> 
                   <?php echo esc_html__('API Ayarları', 'api-isarud'); ?>
                   <span class="status-indicator <?php echo $is_active ? 'active' : 'inactive'; ?>">
                       <?php echo $is_active ? esc_html__('Aktif', 'api-isarud') : esc_html__('Pasif', 'api-isarud'); ?>
                   </span>
               </h2>
           </div>
           <table class="form-table">
               <?php foreach ($marketplace['fields'] as $field_key => $field) : 
                   $field_id = esc_attr("api_isarud_{$key}_{$field_key}");
                   $field_name = esc_attr("api_isarud_{$key}[{$field_key}]");
               ?>
               <tr>
                   <th scope="row">
                       <label for="<?php echo esc_attr($field_id); ?>">
                           <?php echo esc_html($field['label']); ?>
                       </label>
                   </th>
                   <td>
                       <input type="<?php echo esc_attr($field['type']); ?>"
                              name="<?php echo esc_attr($field_name); ?>"
                              id="<?php echo esc_attr($field_id); ?>"
                              value="<?php echo esc_attr($options[$field_key] ?? ''); ?>"
                              class="regular-text">
                       <p class="description"><?php echo esc_html($field['desc']); ?></p>
                   </td>
               </tr>
               <?php endforeach; ?>
           </table>
       </div>
       <?php endforeach; ?>

       <?php submit_button(esc_html__('Ayarları Kaydet', 'api-isarud')); ?>
   </form>
</div>
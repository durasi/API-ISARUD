<?php
if (!defined('ABSPATH')) {
   exit;
}

class ApiIsarudMarketplaceSettings {
   public static function get_marketplaces() {
       return array(
           'trendyol' => array(
               'name' => 'Trendyol',
               'fields' => array(
                   'api_key' => array(
                       'label' => 'API Key',
                       'type' => 'text',
                       'desc' => 'Trendyol Seller API anahtarınız'
                   ),
                   'api_secret' => array(
                       'label' => 'API Secret',
                       'type' => 'password', 
                       'desc' => 'Trendyol Seller API gizli anahtarınız'
                   ),
                   'seller_id' => array(
                       'label' => 'Satıcı ID',
                       'type' => 'text',
                       'desc' => 'Trendyol mağaza ID\'niz'
                   )
               )
           ),
           'n11' => array(
               'name' => 'N11',
               'fields' => array(
                   'api_key' => array(
                       'label' => 'API Key',
                       'type' => 'text',
                       'desc' => 'N11 API anahtarınız'
                   ),
                   'api_secret' => array(
                       'label' => 'API Secret',
                       'type' => 'password',
                       'desc' => 'N11 API gizli anahtarınız'
                   )
               )
           ),
           'hepsiburada' => array(
               'name' => 'HepsiBurada',
               'fields' => array(
                   'username' => array(
                       'label' => 'Kullanıcı Adı',
                       'type' => 'text',
                       'desc' => 'HepsiBurada entegrasyon kullanıcı adınız'
                   ),
                   'password' => array(
                       'label' => 'Şifre',
                       'type' => 'password',
                       'desc' => 'HepsiBurada entegrasyon şifreniz'
                   ),
                   'merchant_id' => array(
                       'label' => 'Merchant ID',
                       'type' => 'text',
                       'desc' => 'HepsiBurada mağaza ID\'niz'
                   )
               )
           ),
           'amazon' => array(
               'name' => 'Amazon',
               'fields' => array(
                   'access_key' => array(
                       'label' => 'Access Key',
                       'type' => 'text',
                       'desc' => 'Amazon MWS Access Key'
                   ),
                   'secret_key' => array(
                       'label' => 'Secret Key',
                       'type' => 'password',
                       'desc' => 'Amazon MWS Secret Key'
                   ),
                   'seller_id' => array(
                       'label' => 'Satıcı ID',
                       'type' => 'text',
                       'desc' => 'Amazon Seller ID'
                   ),
                   'marketplace_id' => array(
                       'label' => 'Marketplace ID',
                       'type' => 'text',
                       'desc' => 'Amazon Marketplace ID (TR: A1UNQM1LXV2WWF)'
                   )
               )
           ),
           'pazarama' => array(
               'name' => 'Pazarama',
               'fields' => array(
                   'api_key' => array(
                       'label' => 'API Key',
                       'type' => 'text',
                       'desc' => 'Pazarama API anahtarınız'
                   ),
                   'api_secret' => array(
                       'label' => 'API Secret',
                       'type' => 'password',
                       'desc' => 'Pazarama API gizli anahtarınız'
                   ),
                   'seller_id' => array(
                       'label' => 'Satıcı ID',
                       'type' => 'text',
                       'desc' => 'Pazarama mağaza ID\'niz'
                   )
               )
           ),
           'etsy' => array(
               'name' => 'Etsy',
               'fields' => array(
                   'api_key' => array(
                       'label' => 'API Key',
                       'type' => 'text',
                       'desc' => 'Etsy API anahtarınız'
                   ),
                   'access_token' => array(
                       'label' => 'Access Token',
                       'type' => 'password',
                       'desc' => 'Etsy OAuth 2.0 Access Token'
                   ),
                   'shop_id' => array(
                       'label' => 'Shop ID',
                       'type' => 'text',
                       'desc' => 'Etsy mağaza ID\'niz'
                   )
               )
           ),
           'shopify' => array(
               'name' => 'Shopify',
               'fields' => array(
                   'shop_url' => array(
                       'label' => 'Shop URL',
                       'type' => 'text',
                       'desc' => 'Shopify mağaza URL\'niz (Örnek: https://magazaniz.myshopify.com)'
                   ),
                   'access_token' => array(
                       'label' => 'Access Token',
                       'type' => 'password',
                       'desc' => 'Shopify Admin API Access Token'
                   )
               )
           )
       );
   }
}
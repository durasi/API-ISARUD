=== API ISARUD ===
Contributors: durasi
Tags: woocommerce, pazar yeri, api, entegrasyon, trendyol
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce mağazanızı Trendyol, N11, Hepsiburada, Amazon, Etsy, Shopify ve Pazarama gibi popüler pazar yerleri ile sorunsuz bir şekilde entegre edin.

== Description ==

API ISARUD, çevrimiçi mağazanızı popüler pazar yerleri ile entegre ederek yönetimi kolaylaştıran güçlü bir WooCommerce eklentisidir. Trendyol, N11, Hepsiburada, Amazon, Etsy, Shopify ve Pazarama desteğiyle, WordPress yönetim panelinizden doğrudan stok yönetimi yapabilir, fiyatları senkronize edebilir, siparişleri takip edebilir ve ürün eşleştirmesi yapabilirsiniz.

**Temel Özellikler:**
* Tüm pazar yerlerinde merkezi stok yönetimi
* Otomatik envanter senkronizasyonu
* Varyasyonlu ürün desteği ile toplu ürün güncellemeleri
* Sipariş takibi ve durum güncellemeleri
* OAuth 2.0 ve REST/SOAP desteği ile güvenli API bağlantıları
* Detaylı istatistikler ve hata kayıtları içeren kullanıcı dostu yönetim paneli
* Çoklu dil desteği (Türkçe ve İngilizce)

== Harici Hizmetler ==

Bu eklenti, ürün verilerini senkronize etmek için çeşitli pazar yeri API'larına bağlanır:

= Trendyol =
* Hizmet: Trendyol Satıcı Merkezi API
* Amaç: Ürün ve stok yönetimi
* Gönderilen Veriler: Ürün detayları, stok seviyeleri, fiyatlar
* API Dokümantasyonu: https://developers.trendyol.com/
* Hizmet Şartları: https://www.trendyol.com/sozlesmeler/satici-uye-sozlesmesi

= N11 =
* Hizmet: N11 Satıcı API
* Amaç: Ürün ve stok yönetimi  
* Gönderilen Veriler: Ürün detayları, stok seviyeleri, fiyatlar
* API Dokümantasyonu: https://api.n11.com/ws/
* Hizmet Şartları: https://www.n11.com/genel/satici-uyelik-sozlesmesi-76

= HepsiBurada = 
* Hizmet: HepsiBurada Pazar Yeri API
* Amaç: Ürün ve stok yönetimi
* Gönderilen Veriler: Ürün detayları, stok seviyeleri, fiyatlar  
* API Dokümantasyonu: https://developers.hepsiburada.com/
* Hizmet Şartları: https://www.hepsiburada.com/satici-sozlesmeleri

= Amazon =
* Hizmet: Amazon MWS API  
* Amaç: Ürün ve stok yönetimi
* Gönderilen Veriler: Ürün detayları, stok seviyeleri, fiyatlar
* API Dokümantasyonu: https://developer-docs.amazon.com/  
* Hizmet Şartları: https://sellercentral.amazon.com/gp/help/external/G1791

= Pazarama =
* Hizmet: Pazarama Satıcı API
* Amaç: Ürün ve stok yönetimi
* Gönderilen Veriler: Ürün detayları, stok seviyeleri, fiyatlar
* API Dokümantasyonu: https://developer.pazarama.com/  
* Hizmet Şartları: https://www.pazarama.com/satici-sozlesmesi

= Etsy =  
* Hizmet: Etsy Open API
* Amaç: Ürün ve stok yönetimi 
* Gönderilen Veriler: Ürün detayları, stok seviyeleri, fiyatlar
* API Dokümantasyonu: https://developers.etsy.com/  
* Hizmet Şartları: https://www.etsy.com/legal/sellers/

= Shopify =
* Hizmet: Shopify Admin API  
* Amaç: Ürün ve stok yönetimi
* Gönderilen Veriler: Ürün detayları, stok seviyeleri, fiyatlar 
* API Dokümantasyonu: https://shopify.dev/api/admin
* Hizmet Şartları: https://www.shopify.com/legal/terms   

== Kurulum ==

1. Eklenti dosyalarını `/wp-content/plugins/` dizinine yükleyin
2. WordPress'in 'Eklentiler' menüsünden eklentiyi etkinleştirin
3. Pazar yeri ayarlarını yapılandırmak için WordPress yönetim panelindeki "API ISARUD" menüsüne gidin  
4. WooCommerce ürünlerinizi istediğiniz pazar yerleri ile eşleştirin
5. Stok senkronizasyonunu başlatın

== Sürüm Geçmişi ==

= 1.0.0 =
* Trendyol, N11, Hepsiburada, Amazon, Etsy, Shopify ve Pazarama desteği ile ilk sürüm
* Stok yönetimi, fiyat senkronizasyonu, sipariş takibi, ürün eşleştirme ve güvenli API bağlantıları özellikleri

== Yükseltme Bildirimi ==

= 1.0.0 =  
İlk sürüm. Lütfen yüklemeden önce WordPress ve WooCommerce sürümlerinizin minimum gereksinimleri karşıladığından emin olun.
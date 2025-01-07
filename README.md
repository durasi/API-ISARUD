# API-ISARUD
Wordpress tabalı web siteler için WooCommerce destekli; Trendyol, Hepsiburada, N11, Pazarama, Amazon, Etsy, Shopify Pazar Yerleri için API Yönetim sistemi.

## Desteklenen Pazaryerleri

1. **Trendyol**
   * API entegrasyonu
   * Stok yönetimi
   * Fiyat senkronizasyonu
   * Sipariş takibi
   * Ürün eşleştirme

2. **N11**
   * SOAP API entegrasyonu
   * Stok güncelleme
   * Fiyat yönetimi
   * Sipariş listesi
   * Ürün eşleştirme

3. **HepsiBurada**
   * API entegrasyonu
   * Stok kontrolü
   * Fiyat senkronizasyonu
   * Sipariş yönetimi
   * Ürün eşleştirme

4. **Amazon**
   * MWS API bağlantısı
   * Stok güncelleme
   * Fiyat güncelleme
   * Sipariş listesi
   * Ürün raporları
   * ASIN bazlı ürün eşleştirme

5. **Pazarama**
   * API entegrasyonu
   * Stok yönetimi
   * Fiyat senkronizasyonu
   * Sipariş takibi
   * Ürün eşleştirme

6. **Etsy**
   * OAuth 2.0 API bağlantısı
   * Stok güncelleme
   * Fiyat güncelleme
   * Ürün listeleme
   * Sipariş yönetimi
   * Ürün varyasyonları

7. **Shopify**
   * REST API bağlantısı
   * Envanter yönetimi
   * Fiyat güncelleme
   * Ürün senkronizasyonu
   * Sipariş yönetimi
   * Location bazlı stok takibi

## Temel Özellikler
### Ürün Yönetimi
* Merkezi stok yönetimi
* Otomatik stok senkronizasyonu
* Toplu ürün güncelleme
* Varyasyonlu ürün desteği
* Ürün eşleştirme sistemi

### Sipariş Yönetimi
* Otomatik sipariş alma
* Sipariş durumu güncelleme
* Kargo takibi
* Sipariş geçmişi

### API Entegrasyonları
* Güvenli API bağlantıları
* OAuth 2.0 desteği
* SOAP/REST API desteği
* Çoklu kimlik doğrulama yöntemleri

### Panel Özellikleri
* Kullanıcı dostu arayüz
* Detaylı istatistikler
* Pazaryeri bazlı ayarlar
* API durum kontrolleri
* Hata logları takibi

### Güvenlik ve Performans
* Şifrelenmiş API anahtarları
* Güvenli veri transferi
* Optimize edilmiş API istekleri
* Otomatik hata yönetimi
* Detaylı loglama sistemi

## Teknik Detaylar
### Sistem Gereksinimleri
* WordPress 5.0 veya üzeri
* WooCommerce 5.0 veya üzeri
* PHP 8.0 veya üzeri
* HTTPS desteği

### Veritabanı Tabloları
* API kimlik bilgileri tablosu
* Ürün eşleştirme tablosu
* Senkronizasyon log tablosu

### Kod Yapısı
* Nesne yönelimli mimari
* MVC tasarım deseni
* WordPress kodlama standartları
* PSR-4 autoloading desteği

## Kurulum
1. Eklenti dosyalarını `/wp-content/plugins/` dizinine yükleyin
2. WordPress admin panelinden eklentiyi aktifleştirin
3. API ISARUD menüsünden pazaryeri ayarlarını yapılandırın
4. WooCommerce ürünlerinizi pazaryerleri ile eşleştirin
5. Stok senkronizasyonunu başlatın

## Lisans
Bu eklenti GPL v2 veya sonraki sürümleri ile lisanslanmıştır.

---
Bu eklenti [Seçkin Sefa Durası](https://www.seckin.ws) tarafından geliştirilmiştir.

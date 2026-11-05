<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/AdminI18n.php';
require_once __DIR__ . '/../src/Admin.php';
if (!function_exists('e')) { function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }

$locales = ['tr','en','de','fr','es','it','pt','nl','ru','ar','zh','ja','ko','fa'];
$keys = ['management','welcome','email','password','login','logout','open_site','workspace','skip','language','save_language','language_saved'];
$dictionary = admin_dictionary();
$failures = [];
foreach ($locales as $locale) {
    $_REQUEST['lang'] = $locale;
    foreach (['new','quoted','proposed','confirmed','rejected','draft','review','published','queued','running','completed','failed','sent','pending'] as $status) {
        if (str_contains(admin_status($status), '>'.$status.'<')) $failures[] = $locale.'.status.'.$status;
    }
    foreach (['dashboard','requests','appointments','content','content-models','editor','media','gallery','seo','settings','email-templates','notification-rules','integrations','security','users','jobs','audit','backup','mail','languages','themes','licenses','modules','locations','taxonomies','legal'] as $section) {
        if (trim(admin_section_title($section, $locale)) === '') $failures[] = $locale.'.section.'.$section;
    }
    [$mailSubject, $mailBody] = admin_request_mail_text($locale, 'TofiDesk', 'REQ-001', 'confirmed', '2026-01-01', '10:00', '100', 'EUR');
    if (trim($mailSubject) === '' || trim($mailBody) === '') $failures[] = $locale.'.request-mail';
    foreach ($keys as $key) {
        if (!isset($dictionary[$locale][$key]) || trim((string)$dictionary[$locale][$key]) === '') $failures[] = $locale.'.'.$key;
    }
}
if ($failures) { fwrite(STDERR, "Missing admin translations: ".implode(', ', $failures).PHP_EOL); exit(1); }
foreach ($locales as $locale) {
    foreach (['intro','error','verification','enabled','first_admin','every_product','global'] as $key) {
        if (trim(admin_login_t($key, $locale)) === '') $failures[] = $locale.'.login.'.$key;
    }
    $sample = admin_translate_html('<label>Başlık</label><button>Kaydet</button><span>Yayın durumu</span>', $locale);
    if ($locale !== 'tr' && str_contains($sample, 'Başlık') && str_contains($sample, 'Kaydet')) $failures[] = $locale.'.static-ui';
    $workspaceSample = admin_translate_html('<h2>Marka ve iletişim</h2><h2>Yeni içerik oluştur</h2><button>Ayarları kaydet</button><label>Ürün sektörü</label>', $locale);
    if ($locale !== 'tr' && (str_contains($workspaceSample, 'Marka ve iletişim') || str_contains($workspaceSample, 'Yeni içerik oluştur') || str_contains($workspaceSample, 'Ürün sektörü'))) $failures[] = $locale.'.workspace-ui';
    $operationsSample = admin_translate_html('<h2>Güvenli güncelleme</h2><h2>Lisans merkezi</h2><h2>Bildirim kuralları</h2><button>Tema kaydet</button><h2>Sunucu ön kontrolü</h2>', $locale);
    if ($locale !== 'tr' && (str_contains($operationsSample, 'Güvenli güncelleme') || str_contains($operationsSample, 'Lisans merkezi') || str_contains($operationsSample, 'Bildirim kuralları') || str_contains($operationsSample, 'Tema kaydet') || str_contains($operationsSample, 'Sunucu ön kontrolü'))) $failures[] = $locale.'.operations-ui';
    $workflowSample = admin_translate_html('<h2>Hesap güvenliği</h2><button>2FA kurulumu başlat</button><h2>Takvime Etkinlik Ekle</h2><button>Etkinliği Kaydet</button><h2>Yeni içerik oluştur</h2><button>Dil sürümünü kaydet</button><h2>Yasal metinler</h2>', $locale);
    if ($locale !== 'tr' && (str_contains($workflowSample, 'Hesap güvenliği') || str_contains($workflowSample, '2FA kurulumu başlat') || str_contains($workflowSample, 'Takvime Etkinlik Ekle') || str_contains($workflowSample, 'Etkinliği Kaydet') || str_contains($workflowSample, 'Yeni içerik oluştur') || str_contains($workflowSample, 'Dil sürümünü kaydet') || str_contains($workflowSample, 'Yasal metinler'))) $failures[] = $locale.'.workflow-ui';
    $deepSample = admin_translate_html('<h2>Ana Sayfa Bölümleri</h2><h2>Harita Görünümü</h2><h2>Özel dil paketleri</h2><h2>Güncelleme politikası</h2><button>SMTP testini kuyruğa al</button><button>SMS/WhatsApp testini kuyruğa al</button><button>Üretim işlerini incele ↗</button><h2>KONTROLLÜ ÜRETİM</h2><h2>Sektör içerik stüdyosu</h2><button>Sektör kaydını oluştur ↗</button><h2>Kategori ve etiket altyapısı</h2><button>Taksonomiyi kaydet</button><button>Terimi kaydet</button>', $locale);
    if ($locale !== 'tr' && preg_match('/Ana Sayfa Bölümleri|Harita Görünümü|Özel dil paketleri|Güncelleme politikası|SMTP testini kuyruğa al|SMS\/WhatsApp testini kuyruğa al|Üretim işlerini incele ↗|KONTROLLÜ ÜRETİM|Sektör içerik stüdyosu|Sektör kaydını oluştur ↗|Kategori ve etiket altyapısı|Taksonomiyi kaydet|Terimi kaydet/u', $deepSample)) $failures[] = $locale.'.deep-ui';
    $commerceSample = admin_translate_html('<h2>Ürün kalite incelemesi</h2><h2>Yayınlama kontrolü</h2><button>Yayınla</button><span>Uyumluluk</span><span>Paket hash kontrolü</span><span>Aktivasyon anormallikleri</span>', $locale);
    if ($locale !== 'tr' && preg_match('/Ürün kalite incelemesi|Yayınlama kontrolü|Yayınla|Uyumluluk|Paket hash kontrolü|Aktivasyon anormallikleri/u', $commerceSample)) $failures[] = $locale.'.commerce-release-ui';
    $fieldsSample = admin_translate_html('<label>Başlık</label><label>Açıklama</label><button>Kaydet</button><span>Yayın durumu</span><span>Gereksinimler</span><span>Dokümantasyon</span>', $locale);
    if ($locale !== 'tr' && preg_match('/Başlık|Açıklama|Kaydet|Yayın durumu|Gereksinimler|Dokümantasyon/u', $fieldsSample)) $failures[] = $locale.'.field-ui';
    $nav = implode(' ', array_map(static fn(string $section): string => admin_section_title($section, $locale), ['dashboard','requests','content','themes','modules','licenses','settings','security','backup']));
    if ($locale !== 'tr' && preg_match('/Genel Bakış|Teklif Talepleri|İçerik Stüdyosu|Tema Kütüphanesi|Lisanslar|İşletme ve Entegrasyonlar|Hesap Güvenliği|Yedekleme/u', $nav)) $failures[] = $locale.'.nav';
    $flash = admin_translate_html('Ayarlar kaydedildi. Gizli anahtarlar sunucu ortamından okunur.', $locale);
    if ($locale !== 'tr' && str_contains($flash, 'Ayarlar kaydedildi')) $failures[] = $locale.'.flash';
    if (trim(admin_t('footer', $locale)) === '' || (!in_array($locale, ['tr','en'], true) && admin_t('footer', $locale) === admin_t('footer', 'en'))) $failures[] = $locale.'.footer';
}
if ($failures) { fwrite(STDERR, "Missing admin UI translations: ".implode(', ', $failures).PHP_EOL); exit(1); }
echo count($locales).' locales × full admin shell/status/section/mail coverage passed.'.PHP_EOL;

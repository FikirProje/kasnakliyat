# TofiDesk paket sınırları

Bu projede üç ayrı dağıtım vardır. Paketler birbirinin içine yüklenmez.

| Paket | Hedef | İçerik |
|---|---|---|
| `product-tofidesk-license-center.zip` | `product.tofidesk.com` | Ürün vitrini, tema/yazılım satış kataloğu, müşteri hesabı, sipariş, lisans, indirme, release ve güncelleme merkezi |
| `product-tofidesk-app-deployment.zip` | `kasnakliyat.com` veya müşterinin alan adı | CMS uygulaması, yönetim paneli, sektör modülleri, tema çalıştırma ve lisans istemcisi |
| `product-tofidesk-marketplace.zip` | Yeni müşteri kurulumu | Kas Nakliyat markası ve müşteri verileri temizlenmiş genel TofiDesk CMS |

## Yükleme kuralı

ZIP içeriği doğrudan ilgili domainin `httpdocs` köküne çıkarılır. ZIP dosyasının
kendisini veya üst klasörünü `httpdocs` içine bırakmayın. Plesk document root
`public/` olarak seçilirse yalnızca hosting yönergesindeki public düzeni kullanılır;
aynı kurulumda hem kök `index.php` hem de farklı bir uygulamanın `public/` klasörü
karıştırılmaz.

`product.tofidesk.com` hiçbir zaman Kas Nakliyat uygulama ZIP’iyle kurulmaz.
`kasnakliyat.com` hiçbir zaman lisans merkezi ZIP’iyle kurulmaz.

<?php
declare(strict_types=1);
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $path = envv('DB_PATH', storage_path('app.sqlite'));
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL;');
        $pdo->exec("CREATE TABLE IF NOT EXISTS calendar_events(id INTEGER PRIMARY KEY,title TEXT NOT NULL,start_date TEXT NOT NULL,end_date TEXT NOT NULL,notes TEXT NOT NULL DEFAULT '',color TEXT NOT NULL DEFAULT '#087F8C',created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    }
    return $pdo;
}
function query(string $sql, array $params = []): PDOStatement { $statement = db()->prepare($sql); $statement->execute($params); return $statement; }
function rows(string $sql, array $params = []): array { return query($sql, $params)->fetchAll(); }
function row(string $sql, array $params = []): ?array { return query($sql, $params)->fetch() ?: null; }
function setting(string $key, string $default = ''): string {
    static $defaults;
    $defaults ??= is_file(ROOT . '/config/site.php') ? require ROOT . '/config/site.php' : [];
    // A partially restored/new database may not have reached migration 1 yet.
    // Settings are optional at bootstrap; never turn the diagnostic/install
    // path into a second exception while trying to render an error page.
    $table = db()->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='settings'")->fetchColumn();
    if (!$table) return (string)($defaults[$key] ?? $default);
    $r = row('SELECT value FROM settings WHERE key=?', [$key]);
    return $r ? (string)$r['value'] : (string)($defaults[$key] ?? $default);
}
function save_setting(string $key, string $value): void {
    db()->exec('CREATE TABLE IF NOT EXISTS settings(key TEXT PRIMARY KEY,value TEXT NOT NULL)');
    query('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value', [$key, $value]);
}
function migrate(): void {
    db()->exec('CREATE TABLE IF NOT EXISTS migrations(version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL)');
    if (row('SELECT version FROM migrations WHERE version=1')) { migrate_extensions(); return; }
    db()->beginTransaction();
    try {
        db()->exec(<<<'SQL'
CREATE TABLE settings(key TEXT PRIMARY KEY,value TEXT NOT NULL);
CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT NOT NULL,email TEXT NOT NULL UNIQUE COLLATE NOCASE,password_hash TEXT NOT NULL,role TEXT NOT NULL CHECK(role IN ('admin','editor','operator')),totp_secret TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE requests(id INTEGER PRIMARY KEY,reference TEXT NOT NULL UNIQUE,type TEXT NOT NULL CHECK(type IN ('quote','appointment')),name TEXT NOT NULL,email TEXT NOT NULL,phone TEXT NOT NULL,origin TEXT NOT NULL,destination TEXT NOT NULL,cargo TEXT NOT NULL,preferred_date TEXT NOT NULL,slot TEXT NOT NULL DEFAULT '',notes TEXT NOT NULL DEFAULT '',lang TEXT NOT NULL DEFAULT 'tr',status TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','quoted','proposed','confirmed','rejected')),price TEXT NOT NULL DEFAULT '',admin_note TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE UNIQUE INDEX unique_confirmed_slot ON requests(preferred_date,slot) WHERE type='appointment' AND status='confirmed' AND slot<>'';
CREATE INDEX requests_created ON requests(created_at);
CREATE TABLE articles(id INTEGER PRIMARY KEY,kind TEXT NOT NULL DEFAULT 'article' CHECK(kind IN ('article','service','page')),source_lang TEXT NOT NULL DEFAULT 'tr',category TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE translations(id INTEGER PRIMARY KEY,article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,lang TEXT NOT NULL,title TEXT NOT NULL DEFAULT '',slug TEXT NOT NULL,excerpt TEXT NOT NULL DEFAULT '',body TEXT NOT NULL DEFAULT '',seo_title TEXT NOT NULL DEFAULT '',seo_description TEXT NOT NULL DEFAULT '',keywords TEXT NOT NULL DEFAULT '',status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','review','published')),scheduled_at TEXT,source_revision INTEGER NOT NULL DEFAULT 1,revision INTEGER NOT NULL DEFAULT 1,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(article_id,lang),UNIQUE(lang,slug));
CREATE TABLE redirects(id INTEGER PRIMARY KEY,lang TEXT NOT NULL,old_slug TEXT NOT NULL,new_slug TEXT NOT NULL,UNIQUE(lang,old_slug));
CREATE TABLE media(id INTEGER PRIMARY KEY,filename TEXT NOT NULL UNIQUE,original_name TEXT NOT NULL,mime TEXT NOT NULL,size INTEGER NOT NULL,width INTEGER NOT NULL,height INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE media_translations(media_id INTEGER NOT NULL REFERENCES media(id) ON DELETE CASCADE,lang TEXT NOT NULL,title TEXT NOT NULL DEFAULT '',alt TEXT NOT NULL DEFAULT '',caption TEXT NOT NULL DEFAULT '',PRIMARY KEY(media_id,lang));
CREATE TABLE request_media(request_id INTEGER NOT NULL REFERENCES requests(id) ON DELETE CASCADE,media_id INTEGER NOT NULL REFERENCES media(id) ON DELETE CASCADE,PRIMARY KEY(request_id,media_id));
CREATE TABLE jobs(id INTEGER PRIMARY KEY,article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,lang TEXT NOT NULL,mode TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'queued',prompt TEXT NOT NULL DEFAULT '',result TEXT,error TEXT,attempts INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE mail_queue(id INTEGER PRIMARY KEY,request_id INTEGER REFERENCES requests(id) ON DELETE SET NULL,recipient TEXT NOT NULL,subject TEXT NOT NULL,body TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'queued',attempts INTEGER NOT NULL DEFAULT 0,last_error TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,sent_at TEXT);
CREATE TABLE audit_logs(id INTEGER PRIMARY KEY,user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,action TEXT NOT NULL,detail TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE login_attempts(id INTEGER PRIMARY KEY,ip TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE rate_limits(bucket TEXT PRIMARY KEY,count INTEGER NOT NULL,started_at INTEGER NOT NULL);
SQL);
        query('INSERT INTO migrations(version,applied_at) VALUES(1,datetime(\'now\'))');
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_extensions();
}
function migrate_extensions(): void {
    if (row('SELECT version FROM migrations WHERE version=2')) { migrate_media_purpose(); return; }
    db()->beginTransaction();
    try {
        db()->exec('ALTER TABLE translations ADD COLUMN cover_media_id INTEGER REFERENCES media(id) ON DELETE SET NULL');
        db()->exec('ALTER TABLE mail_queue ADD COLUMN claimed_at TEXT');
        query('INSERT INTO migrations(version,applied_at) VALUES(2,datetime(\'now\'))');
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_media_purpose();
}
function migrate_media_purpose(): void {
    if (row('SELECT version FROM migrations WHERE version=3')) { migrate_gallery(); return; }
    db()->beginTransaction();
    try {
        db()->exec("ALTER TABLE media ADD COLUMN purpose TEXT NOT NULL DEFAULT 'customer' CHECK(purpose IN ('customer','library'))");
        query('INSERT INTO migrations(version,applied_at) VALUES(3,datetime(\'now\'))');
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_gallery();
}
function migrate_gallery(): void {
    if (row('SELECT version FROM migrations WHERE version=4')) { migrate_example_content(); return; }
    db()->beginTransaction();
    try {
        db()->exec("CREATE TABLE gallery_items(id INTEGER PRIMARY KEY,media_id INTEGER NOT NULL UNIQUE REFERENCES media(id) ON DELETE CASCADE,status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','published')),sort_order INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        db()->exec('CREATE INDEX gallery_visible ON gallery_items(status,sort_order DESC,id DESC)');
        query('INSERT INTO migrations(version,applied_at) VALUES(4,datetime(\'now\'))');
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_example_content();
}
function migrate_example_content(): void {
    if (row('SELECT version FROM migrations WHERE version=5')) { migrate_example_content_formatting(); return; }
    $examples = [
        [
            'category'=>'Taşıma rehberi',
            'tr'=>['Taşıma öncesi yükünüzü nasıl hazırlarsınız?','tasima-oncesi-yuk-hazirligi','Taşıma talebinizi daha net hazırlamanıza yardımcı olacak pratik bir kontrol listesi.',"Yükünüzün türünü, yaklaşık miktarını, alınacağı ve bırakılacağı noktayı önceden not edin. Hassas ya da ölçüsü büyük parçaları ayrıca belirtin.\n\nUygun tarih aralığını ve erişim koşullarını paylaşmak, talebin daha sağlıklı değerlendirilmesine yardımcı olur. Bu yazı genel bir hazırlık rehberidir; her taşıma için kapsam ve koşullar ayrıca teyit edilmelidir.",'Taşıma öncesi yük hazırlığı | Kaş Nakliyat','Yük taşıma talebi öncesinde paylaşılabilecek temel bilgiler.','nakliye hazırlığı, yük taşıma, Kaş'],
            'en'=>['How to prepare a cargo request','preparing-a-cargo-request','A practical checklist for preparing a clear transport request.',"Note the type and approximate quantity of the cargo, as well as the pickup and delivery points. Mention fragile or oversized pieces separately.\n\nSharing a suitable date range and access conditions helps the request be reviewed more clearly. This is a general preparation guide; scope and conditions are confirmed separately for each transport.",'Preparing a cargo request | Kaş Nakliyat','Key details to prepare before requesting cargo transport.','cargo preparation, transport request, Kaş']
        ],
        [
            'category'=>'Planlama',
            'tr'=>['Teklif talebinde hangi bilgiler faydalıdır?','teklif-talebinde-gerekli-bilgiler','Doğru kapsamla fiyatlandırma için yararlı bilgileri bir araya getirin.',"Kalkış ve varış noktası, yükün türü, tercih edilen tarih ve iletişim bilgileri teklif talebinin temelini oluşturur.\n\nMerdiven, kat, araç yaklaşımı veya zaman kısıtı gibi erişimi etkileyen ayrıntıları eklemek değerlendirmeyi kolaylaştırır. Talep göndermek otomatik onay veya kesin fiyat anlamına gelmez.",'Teklif talebinde gerekli bilgiler | Kaş Nakliyat','Nakliye teklif talebini hazırlamak için temel bilgiler.','nakliye teklifi, taşıma planlama, Kaş'],
            'en'=>['What helps when requesting a quote?','details-for-a-transport-quote','Gather the details that help define a transport request clearly.',"Pickup and delivery points, cargo type, preferred date and contact details form the basis of a quote request.\n\nAccess details such as stairs, floors, vehicle approach or time constraints can make review easier. Submitting a request is not an automatic confirmation or a final price.",'Details for a transport quote | Kaş Nakliyat','Useful details to prepare for a transport quote request.','transport quote, planning, Kaş']
        ],
        [
            'category'=>'Randevu',
            'tr'=>['Randevu talebi nasıl değerlendirilir?','randevu-talebi-nasil-degerlendirilir','Talep, uygunluk ve ayrıntılar incelendikten sonra netleştirilir.',"Randevu talebiniz alındığında tercih edilen tarih ve saat, taşıma kapsamı ve gerekli erişim ayrıntıları birlikte değerlendirilir.\n\nKesinleşen bir zaman ancak işletme onayı sonrasında oluşur. Böylece takvim çakışmaları ve eksik bilgiler nedeniyle oluşabilecek yanlış anlaşılmalar önlenir.",'Randevu talebi değerlendirme süreci | Kaş Nakliyat','Nakliye randevu talebinin değerlendirme süreci hakkında genel bilgi.','nakliye randevusu, taşıma talebi, Kaş'],
            'en'=>['How is an appointment request reviewed?','how-an-appointment-request-is-reviewed','The request is confirmed after availability and details are reviewed.',"When an appointment request arrives, the preferred date and time, the transport scope and the access details are considered together.\n\nA time becomes confirmed only after the business approves it. This helps prevent scheduling conflicts and misunderstandings caused by missing details.",'Appointment request review | Kaş Nakliyat','General information about reviewing a transport appointment request.','transport appointment, request review, Kaş']
        ],
    ];
    if (product_profile()['name'] === '' && product_profile()['location'] === '') {
        $examples = [
            ['category'=>'Product guidance','tr'=>['Dijital ürün seçerken nelere dikkat edilir?','dijital-urun-secme-rehberi','İhtiyacınıza uygun yazılım, tema veya eklentiyi seçmek için pratik bir kontrol listesi.',"İhtiyaçlarınızı, teknik gereksinimlerinizi, lisans kapsamını ve destek süresini karşılaştırın. Demo, dokümantasyon ve güncelleme politikasını inceleyerek daha bilinçli bir karar verebilirsiniz.",'Dijital ürün seçme rehberi | TofiDesk','Yazılım, tema ve eklenti seçerken kontrol edilmesi gerekenler.','yazılım seçimi, tema seçimi, dijital ürün'], 'en'=>['How to choose a digital product','how-to-choose-a-digital-product','A practical checklist for choosing software, a theme or an add-on for your needs.',"Compare your needs, technical requirements, licence scope and support period. Review the demo, documentation and update policy before making an informed decision.",'How to choose a digital product | TofiDesk','What to check when choosing software, themes and add-ons.','software selection, theme selection, digital products']],
            ['category'=>'Platform guide','tr'=>['Sürüm ve lisans bilgileri nasıl değerlendirilir?','surum-ve-lisans-bilgilerini-degerlendirme','Sürüm geçmişi, güncelleme kapsamı ve lisans haklarını birlikte inceleyin.',"Bir ürünü satın almadan önce mevcut sürümü, değişiklik günlüğünü, güncelleme süresini, domain limitini ve destek kapsamını kontrol edin. Bu bilgiler kurulumdan sonraki deneyimi doğrudan etkiler.",'Sürüm ve lisans bilgileri | TofiDesk','Ürün sürümü, güncelleme ve lisans koşullarını anlama rehberi.','sürüm geçmişi, lisans kapsamı, yazılım güncellemesi'], 'en'=>['How to review version and licence information','how-to-review-version-and-licence-information','Review the release history, update coverage and licence rights together.',"Before buying a product, check its current version, changelog, update period, domain limit and support coverage. These details directly affect the experience after installation.",'Version and licence information | TofiDesk','A guide to understanding releases, updates and licence terms.','release history, licence scope, software updates']]
        ];
    }
    db()->beginTransaction();
    try {
        foreach ($examples as $example) {
            query("INSERT INTO articles(kind,source_lang,category,created_at,updated_at) VALUES('article','tr',?,datetime('now'),datetime('now'))", [$example['category']]);
            $articleId = (int) db()->lastInsertId();
            foreach (['tr','en'] as $lang) {
                [$title,$slug,$excerpt,$body,$seoTitle,$seoDescription,$keywords] = $example[$lang];
                query("INSERT INTO translations(article_id,lang,title,slug,excerpt,body,seo_title,seo_description,keywords,status,scheduled_at,source_revision,revision,updated_at) VALUES(?,?,?,?,?,?,?,?,?,'published',NULL,1,1,datetime('now'))", [$articleId,$lang,$title,$slug,$excerpt,$body,$seoTitle,$seoDescription,$keywords]);
            }
        }
        query('INSERT INTO migrations(version,applied_at) VALUES(5,datetime(\'now\'))');
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_example_content_formatting();
}
function migrate_example_content_formatting(): void {
    if (row('SELECT version FROM migrations WHERE version=6')) { migrate_language_packages(); return; }
    db()->beginTransaction();
    try {
        foreach (rows("SELECT id,body FROM translations WHERE slug IN ('tasima-oncesi-yuk-hazirligi','preparing-a-cargo-request','teklif-talebinde-gerekli-bilgiler','details-for-a-transport-quote','randevu-talebi-nasil-degerlendirilir','how-an-appointment-request-is-reviewed')") as $translation) {
            query('UPDATE translations SET body=?,updated_at=datetime(\'now\') WHERE id=?', [str_replace('\\n', "\n", (string)$translation['body']), $translation['id']]);
        }
        query('INSERT INTO migrations(version,applied_at) VALUES(6,datetime(\'now\'))');
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_language_packages();
}

function migrate_language_packages(): void {
    if (row('SELECT version FROM migrations WHERE version=7')) { migrate_moving_service(); return; }
    db()->beginTransaction();
    try {
        db()->exec("CREATE TABLE site_languages(code TEXT PRIMARY KEY,native_name TEXT NOT NULL,english_name TEXT NOT NULL,direction TEXT NOT NULL CHECK(direction IN ('ltr','rtl')),status TEXT NOT NULL DEFAULT 'disabled' CHECK(status IN ('enabled','disabled')),ui_status TEXT NOT NULL DEFAULT 'draft' CHECK(ui_status IN ('draft','review','published')),ui_catalog TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        query("INSERT INTO migrations(version,applied_at) VALUES(7,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_moving_service();
}

/** A real, editable starting service rather than an unsupported business claim. */
function migrate_moving_service(): void {
    if (row('SELECT version FROM migrations WHERE version=8')) { migrate_inbound_mail(); return; }
    db()->beginTransaction();
    try {
        query("INSERT INTO articles(kind,source_lang,category,created_at,updated_at) VALUES('service','tr','Ev taşımacılığı',datetime('now'),datetime('now'))");
        $id=(int)db()->lastInsertId();
        $tr=['Şablon ve yazılım kurulumu','software-and-theme-setup','Ürün kurulumu öncesinde gereksinimleri ve dokümantasyonu kontrol edin.',"Kurulumdan önce PHP sürümünü, gerekli uzantıları, depolama izinlerini ve lisans kapsamını kontrol edin. Dokümantasyondaki adımları izlemek, güvenli ve sorunsuz bir başlangıç sağlar.",'Şablon ve yazılım kurulumu | TofiDesk','Yazılım ve tema kurulumu öncesi teknik kontrol listesi.','yazılım kurulumu, tema kurulumu, teknik gereksinimler'];
        $en=['Software and theme setup','software-and-theme-setup','Check the requirements and documentation before installing a product.',"Before installation, check the PHP version, required extensions, storage permissions and licence scope. Following the documentation provides a safer and smoother start.",'Software and theme setup | TofiDesk','A technical checklist before installing software and themes.','software setup, theme setup, technical requirements'];
        if (product_profile()['name'] !== '' || product_profile()['location'] !== '') { $tr=['Kaş şehir içi ev taşımacılığı','kas-sehir-ici-ev-tasimaciligi','Kaş içindeki ev taşıma talebiniz için güzergâh, eşya ve erişim ayrıntılarını birlikte değerlendirelim.',"Kaş içinde ev taşıma talebiniz için taşınacak eşyayı, kat ve erişim koşullarını, adresleri ve tercih ettiğiniz tarihi paylaşabilirsiniz. Değerlendirme sonrasında kapsam ve uygunluk netleştirilir.\n\nBu sayfa başlangıç hizmet metnidir. İşletmeniz paketleme, montaj, sigorta veya asansörlü taşıma sunuyorsa bunları yalnız doğrulanmış kapsamınıza göre düzenleyin.",'Kaş şehir içi ev taşımacılığı | Kaş Nakliyat','Kaş içi ev taşıma talebinde paylaşılabilecek temel bilgiler.','Kaş ev taşımacılığı, şehir içi nakliyat, evden eve nakliyat']; $en=['Local home moving in Kaş','local-home-moving-kas','Share your addresses, household items and access details for a move within Kaş.',"For a home-moving enquiry within Kaş, share the items to be moved, floors and access conditions, addresses and your preferred date. Scope and availability are clarified after review.\n\nThis is starter service copy. Only describe packing, assembly, insurance or lift services if your business has confirmed that it provides them.",'Local home moving in Kaş | Kaş Nakliyat','Useful details to prepare for a local home-moving enquiry in Kaş.','Kaş home moving, local transport, household move']; }
        foreach(['tr'=>$tr,'en'=>$en] as $lang=>$data) query("INSERT INTO translations(article_id,lang,title,slug,excerpt,body,seo_title,seo_description,keywords,status,source_revision,revision,updated_at) VALUES(?,?,?,?,?,?,?,?,?,'published',1,1,datetime('now'))",[$id,$lang,...$data]);
        query("INSERT INTO migrations(version,applied_at) VALUES(8,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_inbound_mail();
}

function migrate_inbound_mail(): void {
    if (row('SELECT version FROM migrations WHERE version=9')) { migrate_themes(); return; }
    db()->beginTransaction();
    try {
        db()->exec("CREATE TABLE inbound_messages(id INTEGER PRIMARY KEY,uidvalidity TEXT NOT NULL,uid TEXT NOT NULL,message_id TEXT NOT NULL DEFAULT '',request_id INTEGER REFERENCES requests(id) ON DELETE SET NULL,sender TEXT NOT NULL,subject TEXT NOT NULL,body TEXT NOT NULL,received_at TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(uidvalidity,uid))");
        db()->exec('CREATE INDEX inbound_messages_request ON inbound_messages(request_id,received_at DESC)');
        query("INSERT INTO migrations(version,applied_at) VALUES(9,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_themes();
}

function migrate_themes(): void {
    $profile = product_profile();
    $themeName = trim((string)($profile['name'] ?? '')) !== '' ? trim((string)$profile['name']).' Starter Theme' : 'TofiDesk Starter Theme';
    if (row('SELECT version FROM migrations WHERE version=10')) {
        query("UPDATE themes SET name=?,locale='global' WHERE name='Default Transport' AND NOT EXISTS(SELECT 1 FROM themes WHERE locale='global')", [$themeName]);
        migrate_user_preferences();
        return;
    }
    db()->beginTransaction();
    try {
        db()->exec("CREATE TABLE themes(id INTEGER PRIMARY KEY,name TEXT NOT NULL UNIQUE,locale TEXT NOT NULL DEFAULT 'en',primary_color TEXT NOT NULL,accent_color TEXT NOT NULL,custom_css TEXT NOT NULL DEFAULT '',status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','published')),is_active INTEGER NOT NULL DEFAULT 0 CHECK(is_active IN (0,1)),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        query("INSERT INTO themes(name,locale,primary_color,accent_color,custom_css,status,is_active) VALUES(?,'global','#0D2E42','#FF9149','','published',1)", [$themeName]);
        query("INSERT INTO migrations(version,applied_at) VALUES(10,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_user_preferences();
}

function migrate_user_preferences(): void {
    if (row('SELECT version FROM migrations WHERE version=11')) { migrate_notifications(); return; }
    db()->beginTransaction();
    try {
        db()->exec("CREATE TABLE user_preferences(user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, preference_key TEXT NOT NULL, preference_value TEXT NOT NULL DEFAULT '', updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(user_id,preference_key))");
        query("INSERT INTO migrations(version,applied_at) VALUES(11,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_notifications();
}

function migrate_notifications(): void {
    if (row('SELECT version FROM migrations WHERE version=12')) { migrate_request_locale(); return; }
    db()->beginTransaction();
    try {
        db()->exec("CREATE TABLE notification_queue(id INTEGER PRIMARY KEY,channel TEXT NOT NULL CHECK(channel IN ('sms','whatsapp')),recipient TEXT NOT NULL,message TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'queued',attempts INTEGER NOT NULL DEFAULT 0,last_error TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,sent_at TEXT,claimed_at TEXT)");
        db()->exec('CREATE INDEX notification_queue_status ON notification_queue(status,created_at)');
        query("INSERT INTO migrations(version,applied_at) VALUES(12,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_request_locale();
}

function migrate_request_locale(): void {
    if (row('SELECT version FROM migrations WHERE version=13')) { migrate_generic_demo_locales(); return; }
    db()->beginTransaction();
    try {
        $columns = rows('PRAGMA table_info(requests)');
        $hasLang = false;
        foreach ($columns as $column) if ((string)$column['name'] === 'lang') $hasLang = true;
        if (!$hasLang) db()->exec("ALTER TABLE requests ADD COLUMN lang TEXT NOT NULL DEFAULT 'tr'");
        query("UPDATE requests SET lang='tr' WHERE lang IS NULL OR lang=''");
        query("INSERT INTO migrations(version,applied_at) VALUES(13,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_generic_demo_locales();
}

/** Keep the generic marketplace starter useful in every supported storefront language.
 *  This migration is deliberately skipped for configured customer/demo profiles.
 */
function migrate_generic_demo_locales(): void {
    if (row('SELECT version FROM migrations WHERE version=14')) { migrate_inbound_mail_enrichment(); return; }
    $profile = product_profile();
    db()->beginTransaction();
    try {
        if (trim((string)($profile['name'] ?? '')) === '' && trim((string)($profile['location'] ?? '')) === '') {
            $content = [
                'dijital-urun-secme-rehberi' => [
                    'tr'=>['Dijital ürün seçerken nelere dikkat edilir?','dijital-urun-secme-rehberi','İhtiyacınıza uygun yazılım, tema veya eklentiyi seçmek için pratik bir kontrol listesi.','İhtiyaçlarınızı, teknik gereksinimlerinizi, lisans kapsamını ve destek süresini karşılaştırın. Demo, dokümantasyon ve güncelleme politikasını inceleyerek daha bilinçli bir karar verebilirsiniz.','Dijital ürün seçme rehberi | TofiDesk','Yazılım, tema ve eklenti seçerken kontrol edilmesi gerekenler.','yazılım seçimi, tema seçimi, dijital ürün'],
                    'en'=>['How to choose a digital product','how-to-choose-a-digital-product','A practical checklist for choosing software, a theme or an add-on for your needs.','Compare your needs, technical requirements, licence scope and support period. Review the demo, documentation and update policy before making an informed decision.','How to choose a digital product | TofiDesk','What to check when choosing software, themes and add-ons.','software selection, theme selection, digital products'],
                    'de'=>['So wählen Sie ein digitales Produkt aus','digitale-produkte-auswaehlen','Eine praktische Checkliste für Software, Themes und Erweiterungen.','Vergleichen Sie Anforderungen, technische Voraussetzungen, Lizenzumfang und Supportzeitraum. Prüfen Sie Demo, Dokumentation und Update-Politik vor dem Kauf.','Digitale Produkte auswählen | TofiDesk','Worauf Sie bei Software, Themes und Erweiterungen achten sollten.','Software auswählen, Theme auswählen, digitale Produkte'],
                    'fr'=>['Comment choisir un produit numérique','choisir-un-produit-numerique','Une checklist pratique pour choisir un logiciel, un thème ou une extension.','Comparez vos besoins, les prérequis techniques, la licence et la durée du support. Consultez la démo, la documentation et la politique de mises à jour avant l’achat.','Choisir un produit numérique | TofiDesk','Les critères à vérifier avant de choisir un logiciel, un thème ou une extension.','choisir logiciel, choisir thème, produits numériques'],
                    'es'=>['Cómo elegir un producto digital','elegir-un-producto-digital','Una lista práctica para elegir software, temas o complementos.','Compare sus necesidades, requisitos técnicos, alcance de la licencia y periodo de soporte. Revise la demo, la documentación y la política de actualizaciones antes de comprar.','Cómo elegir un producto digital | TofiDesk','Qué revisar al elegir software, temas y complementos.','elegir software, elegir tema, productos digitales'],
                    'it'=>['Come scegliere un prodotto digitale','scegliere-un-prodotto-digitale','Una checklist pratica per scegliere software, temi o componenti aggiuntivi.','Confronta esigenze, requisiti tecnici, ambito della licenza e durata del supporto. Consulta demo, documentazione e politica degli aggiornamenti prima dell’acquisto.','Scegliere un prodotto digitale | TofiDesk','Cosa verificare prima di scegliere software, temi e componenti aggiuntivi.','scelta software, scelta tema, prodotti digitali'],
                    'pt'=>['Como escolher um produto digital','escolher-um-produto-digital','Uma lista prática para escolher software, temas ou extensões.','Compare as suas necessidades, requisitos técnicos, âmbito da licença e período de suporte. Consulte a demonstração, a documentação e a política de atualizações antes da compra.','Escolher um produto digital | TofiDesk','O que verificar ao escolher software, temas e extensões.','escolha de software, escolha de tema, produtos digitais'],
                    'ru'=>['Как выбрать цифровой продукт','vybrat-cifrovoj-produkt','Практический список для выбора программного обеспечения, темы или дополнения.','Сравните задачи, технические требования, условия лицензии и срок поддержки. Перед покупкой изучите демо, документацию и правила обновлений.','Как выбрать цифровой продукт | TofiDesk','Что проверить при выборе программного обеспечения, темы или дополнения.','выбор ПО, выбор темы, цифровые продукты'],
                    'ar'=>['كيف تختار منتجاً رقمياً','ikhtiyar-muntaj-raqami','قائمة عملية لاختيار البرامج أو القوالب أو الإضافات المناسبة.','قارن احتياجاتك والمتطلبات التقنية ونطاق الترخيص ومدة الدعم. راجع العرض التجريبي والتوثيق وسياسة التحديث قبل الشراء.','كيفية اختيار منتج رقمي | TofiDesk','ما يجب التحقق منه عند اختيار البرامج والقوالب والإضافات.','اختيار برنامج، اختيار قالب، منتجات رقمية'],
                    'fa'=>['چگونه یک محصول دیجیتال انتخاب کنیم','entekhab-mahsool-digital','راهنمایی کاربردی برای انتخاب نرم‌افزار، قالب یا افزونه مناسب.','نیازها، پیش‌نیازهای فنی، محدوده مجوز و مدت پشتیبانی را مقایسه کنید. پیش از خرید، دمو، مستندات و سیاست به‌روزرسانی را بررسی کنید.','انتخاب محصول دیجیتال | TofiDesk','مواردی که هنگام انتخاب نرم‌افزار، قالب و افزونه باید بررسی شوند.','انتخاب نرم‌افزار، انتخاب قالب، محصولات دیجیتال'],
                    'nl'=>['Een digitaal product kiezen','een-digitaal-product-kiezen','Een praktische checklist voor software, thema’s en uitbreidingen.','Vergelijk uw behoeften, technische vereisten, licentieomvang en supportperiode. Bekijk de demo, documentatie en het updatebeleid voordat u koopt.','Een digitaal product kiezen | TofiDesk','Waar u op let bij software, thema’s en uitbreidingen.','software kiezen, thema kiezen, digitale producten'],
                    'zh'=>['如何选择数字产品','ru-he-xuan-ze-shu-zi-chan-pin','选择软件、主题或扩展的实用清单。','比较您的需求、技术要求、许可范围和支持期限。购买前请查看演示、文档和更新政策。','如何选择数字产品 | TofiDesk','选择软件、主题和扩展时需要检查的内容。','软件选择，主题选择，数字产品'],
                    'ja'=>['デジタル製品の選び方','dejitaru-seihin-no-erabikata','ソフトウェア、テーマ、拡張機能を選ぶための実用的なチェックリストです。','必要な機能、技術要件、ライセンス範囲、サポート期間を比較してください。購入前にデモ、ドキュメント、更新方針を確認しましょう。','デジタル製品の選び方 | TofiDesk','ソフトウェア、テーマ、拡張機能を選ぶ際の確認事項。','ソフトウェア選び, テーマ選び, デジタル製品'],
                    'ko'=>['디지털 제품 선택 방법','dijiteol-jepum-seontaeg','소프트웨어, 테마, 애드온을 선택하기 위한 실용적인 체크리스트입니다.','필요 사항, 기술 요구사항, 라이선스 범위와 지원 기간을 비교하세요. 구매 전에 데모, 문서와 업데이트 정책을 확인하세요.','디지털 제품 선택 방법 | TofiDesk','소프트웨어, 테마와 애드온 선택 전에 확인할 사항.','소프트웨어 선택, 테마 선택, 디지털 제품']
                ],
                'surum-ve-lisans-bilgilerini-degerlendirme' => [
                    'tr'=>['Sürüm ve lisans bilgileri nasıl değerlendirilir?','surum-ve-lisans-bilgilerini-degerlendirme','Sürüm geçmişi, güncelleme kapsamı ve lisans haklarını birlikte inceleyin.','Satın almadan önce mevcut sürümü, değişiklik günlüğünü, güncelleme süresini, domain limitini ve destek kapsamını kontrol edin. Bu bilgiler kurulumdan sonraki deneyimi doğrudan etkiler.','Sürüm ve lisans bilgileri | TofiDesk','Ürün sürümü, güncelleme ve lisans koşullarını anlama rehberi.','sürüm geçmişi, lisans kapsamı, yazılım güncellemesi'],
                    'en'=>['How to review version and licence information','how-to-review-version-and-licence-information','Review the release history, update coverage and licence rights together.','Before buying, check the current version, changelog, update period, domain limit and support coverage. These details directly affect the experience after installation.','Version and licence information | TofiDesk','A guide to understanding releases, updates and licence terms.','release history, licence scope, software updates'],
                    'de'=>['Versions- und Lizenzinformationen verstehen','versions-und-lizenzinformationen','Prüfen Sie Versionsverlauf, Updates und Lizenzrechte gemeinsam.','Kontrollieren Sie vor dem Kauf Version, Änderungsprotokoll, Updatezeitraum, Domain-Limit und Supportumfang. Diese Angaben bestimmen die Erfahrung nach der Installation.','Versions- und Lizenzinformationen | TofiDesk','Leitfaden zu Versionen, Updates und Lizenzbedingungen.','Versionsverlauf, Lizenzumfang, Software-Updates'],
                    'fr'=>['Comprendre les versions et licences','comprendre-versions-et-licences','Examinez ensemble l’historique des versions, les mises à jour et les droits de licence.','Avant l’achat, vérifiez la version, le journal des changements, la période de mise à jour, la limite de domaines et le support. Ces éléments comptent après l’installation.','Versions et licences | TofiDesk','Comprendre les versions, les mises à jour et les conditions de licence.','historique versions, licence, mises à jour'],
                    'es'=>['Cómo revisar versiones y licencias','revisar-versiones-y-licencias','Revise juntos el historial de versiones, las actualizaciones y los derechos de licencia.','Antes de comprar, compruebe la versión, el registro de cambios, el periodo de actualizaciones, el límite de dominios y el soporte. Estos datos importan tras la instalación.','Versiones y licencias | TofiDesk','Guía para entender versiones, actualizaciones y condiciones de licencia.','historial de versiones, licencia, actualizaciones'],
                    'it'=>['Come valutare versioni e licenze','valutare-versioni-e-licenze','Esamina insieme cronologia delle versioni, aggiornamenti e diritti di licenza.','Prima dell’acquisto verifica versione, changelog, periodo di aggiornamento, limite dei domini e supporto. Questi dati incidono sull’esperienza dopo l’installazione.','Versioni e licenze | TofiDesk','Guida a versioni, aggiornamenti e condizioni di licenza.','cronologia versioni, licenza, aggiornamenti'],
                    'pt'=>['Como avaliar versões e licenças','avaliar-versoes-e-licencas','Analise o histórico de versões, as atualizações e os direitos de licença em conjunto.','Antes de comprar, confirme a versão, o changelog, o período de atualizações, o limite de domínios e o suporte. Estes dados são importantes depois da instalação.','Versões e licenças | TofiDesk','Guia para compreender versões, atualizações e condições de licença.','histórico de versões, licença, atualizações'],
                    'ru'=>['Как оценить версии и лицензии','ocenit-versii-i-licenzii','Изучите историю версий, обновления и права лицензии вместе.','Перед покупкой проверьте версию, список изменений, срок обновлений, лимит доменов и поддержку. Эти сведения важны после установки.','Версии и лицензии | TofiDesk','Руководство по версиям, обновлениям и условиям лицензии.','история версий, лицензия, обновления'],
                    'ar'=>['كيفية فهم الإصدارات والتراخيص','fahm-isda-rat-wa-tarakhis','راجع سجل الإصدارات والتحديثات وحقوق الترخيص معاً.','قبل الشراء تحقق من الإصدار وسجل التغييرات ومدة التحديث وحدود النطاقات ونطاق الدعم. تؤثر هذه المعلومات على تجربتك بعد التثبيت.','الإصدارات والتراخيص | TofiDesk','دليل لفهم الإصدارات والتحديثات وشروط الترخيص.','سجل الإصدارات، الترخيص، التحديثات'],
                    'fa'=>['بررسی نسخه و مجوز نرم‌افزار','barresi-noskhe-va-mojavez','تاریخچه نسخه، پوشش به‌روزرسانی و حقوق مجوز را با هم بررسی کنید.','پیش از خرید نسخه فعلی، گزارش تغییرات، مدت به‌روزرسانی، محدودیت دامنه و پشتیبانی را بررسی کنید. این موارد پس از نصب اهمیت زیادی دارند.','نسخه و مجوز | TofiDesk','راهنمای درک نسخه‌ها، به‌روزرسانی‌ها و شرایط مجوز.','تاریخچه نسخه، محدوده مجوز، به‌روزرسانی'],
                    'nl'=>['Versies en licenties beoordelen','versies-en-licenties-beoordelen','Bekijk versiegeschiedenis, updates en licentierechten samen.','Controleer vóór aankoop de versie, changelog, updateperiode, domeinlimiet en support. Deze gegevens zijn na installatie direct van belang.','Versies en licenties | TofiDesk','Een gids voor versies, updates en licentievoorwaarden.','versiegeschiedenis, licentie, software-updates'],
                    'zh'=>['如何查看版本和许可证信息','cha-kan-ban-ben-he-xu-ke-zheng','同时查看版本历史、更新范围和许可证权益。','购买前请确认当前版本、更新日志、更新期限、域名限制和支持范围。这些信息会直接影响安装后的使用体验。','版本和许可证信息 | TofiDesk','了解版本、更新和许可证条款的指南。','版本历史，许可证，软件更新'],
                    'ja'=>['バージョンとライセンスの確認方法','ba-jon-to-rai-sensu','バージョン履歴、更新範囲、ライセンス権利をまとめて確認しましょう。','購入前に現在のバージョン、変更履歴、更新期間、ドメイン制限、サポート範囲を確認してください。インストール後の利用に大きく関わります。','バージョンとライセンス | TofiDesk','バージョン、更新、ライセンス条件を理解するためのガイド。','バージョン履歴, ライセンス, 更新'],
                    'ko'=>['버전 및 라이선스 정보 확인 방법','beojeon-mic-lai-seonseu','버전 기록, 업데이트 범위와 라이선스 권리를 함께 확인하세요.','구매 전에 현재 버전, 변경 내역, 업데이트 기간, 도메인 제한과 지원 범위를 확인하세요. 이 정보는 설치 후 사용 경험에 직접 영향을 줍니다.','버전 및 라이선스 | TofiDesk','버전, 업데이트와 라이선스 조건을 이해하는 안내서.','버전 기록, 라이선스, 소프트웨어 업데이트']
                ],
                'software-and-theme-setup' => [
                    'tr'=>['Yazılım ve tema kurulumu','software-and-theme-setup','Kurulumdan önce teknik gereksinimleri ve dokümantasyonu kontrol edin.','PHP sürümünü, gerekli uzantıları, depolama izinlerini ve lisans kapsamını kurulumdan önce kontrol edin. Dokümantasyondaki adımları izlemek güvenli ve sorunsuz bir başlangıç sağlar.','Yazılım ve tema kurulumu | TofiDesk','Yazılım ve tema kurulumu öncesi teknik kontrol listesi.','yazılım kurulumu, tema kurulumu, teknik gereksinimler'],
                    'en'=>['Software and theme setup','software-and-theme-setup','Check the technical requirements and documentation before installation.','Before installing, check the PHP version, required extensions, storage permissions and licence scope. Following the documentation provides a safer, smoother start.','Software and theme setup | TofiDesk','A technical checklist before installing software and themes.','software setup, theme setup, technical requirements'],
                    'de'=>['Software und Theme installieren','software-und-theme-installieren','Prüfen Sie vor der Installation Anforderungen und Dokumentation.','Kontrollieren Sie PHP-Version, Erweiterungen, Speicherrechte und Lizenzumfang. Die Dokumentation sorgt für einen sicheren und reibungslosen Start.','Software- und Theme-Installation | TofiDesk','Technische Checkliste vor der Installation von Software und Themes.','Softwareinstallation, Theme-Installation, Anforderungen'],
                    'fr'=>['Installer un logiciel ou un thème','installer-logiciel-ou-theme','Vérifiez les prérequis techniques et la documentation avant l’installation.','Contrôlez la version PHP, les extensions, les droits de stockage et la licence. Suivre la documentation assure un démarrage plus sûr et plus fluide.','Installation logiciel et thème | TofiDesk','Checklist technique avant l’installation d’un logiciel ou d’un thème.','installation logiciel, installation thème, prérequis'],
                    'es'=>['Instalar software y temas','instalar-software-y-temas','Compruebe los requisitos técnicos y la documentación antes de instalar.','Revise la versión de PHP, las extensiones, los permisos de almacenamiento y la licencia. Seguir la documentación ofrece un inicio más seguro y fluido.','Instalación de software y temas | TofiDesk','Lista técnica antes de instalar software y temas.','instalación software, instalación tema, requisitos'],
                    'it'=>['Installare software e temi','installare-software-e-temi','Controlla i requisiti tecnici e la documentazione prima dell’installazione.','Verifica versione PHP, estensioni, permessi di archiviazione e licenza. Seguire la documentazione offre un avvio più sicuro e semplice.','Installazione software e temi | TofiDesk','Checklist tecnica prima di installare software e temi.','installazione software, installazione tema, requisiti'],
                    'pt'=>['Instalar software e temas','instalar-software-e-temas','Verifique os requisitos técnicos e a documentação antes da instalação.','Confirme a versão do PHP, extensões, permissões de armazenamento e licença. Seguir a documentação proporciona um início mais seguro e simples.','Instalação de software e temas | TofiDesk','Lista técnica antes de instalar software e temas.','instalação de software, instalação de tema, requisitos'],
                    'ru'=>['Установка программ и тем','ustanovka-programm-i-tem','Перед установкой проверьте технические требования и документацию.','Проверьте версию PHP, расширения, права хранилища и условия лицензии. Следование документации обеспечит безопасный и понятный запуск.','Установка программ и тем | TofiDesk','Технический список перед установкой программ и тем.','установка ПО, установка темы, требования'],
                    'ar'=>['تثبيت البرامج والقوالب','thabit-baramij-wa-qawalib','تحقق من المتطلبات التقنية والتوثيق قبل التثبيت.','تحقق من إصدار PHP والإضافات وصلاحيات التخزين ونطاق الترخيص. اتباع التوثيق يمنحك بداية أكثر أماناً وسلاسة.','تثبيت البرامج والقوالب | TofiDesk','قائمة تقنية قبل تثبيت البرامج والقوالب.','تثبيت البرامج، تثبيت القوالب، المتطلبات'],
                    'fa'=>['نصب نرم‌افزار و قالب','nasb-narmafzar-va-ghaleb','پیش از نصب، نیازمندی‌های فنی و مستندات را بررسی کنید.','نسخه PHP، افزونه‌ها، دسترسی ذخیره‌سازی و محدوده مجوز را بررسی کنید. پیروی از مستندات شروعی امن‌تر و روان‌تر فراهم می‌کند.','نصب نرم‌افزار و قالب | TofiDesk','چک‌لیست فنی پیش از نصب نرم‌افزار و قالب.','نصب نرم‌افزار، نصب قالب، نیازمندی‌ها'],
                    'nl'=>['Software en thema installeren','software-en-thema-installeren','Controleer technische vereisten en documentatie vóór installatie.','Controleer PHP-versie, extensies, opslagrechten en licentieomvang. De documentatie volgen zorgt voor een veilige en soepele start.','Software en thema installeren | TofiDesk','Technische checklist voor software- en themainstallatie.','software installeren, thema installeren, vereisten'],
                    'zh'=>['安装软件和主题','an-zhuang-ruan-jian-he-zhu-ti','安装前请检查技术要求和文档。','请确认 PHP 版本、所需扩展、存储权限和许可证范围。按照文档操作可以更安全、更顺利地开始。','软件和主题安装 | TofiDesk','安装软件和主题前的技术清单。','软件安装，主题安装，技术要求'],
                    'ja'=>['ソフトウェアとテーマのインストール','sofutouea-to-tema-no-insutoru','インストール前に技術要件とドキュメントを確認してください。','PHPのバージョン、必要な拡張機能、ストレージ権限、ライセンス範囲を確認しましょう。ドキュメントに従うことで安全でスムーズに開始できます。','ソフトウェアとテーマのインストール | TofiDesk','ソフトウェアとテーマを導入する前の技術チェックリスト。','ソフトウェア導入, テーマ導入, 技術要件'],
                    'ko'=>['소프트웨어 및 테마 설치','sopeuteuweeo-mic-tema-seolchi','설치 전에 기술 요구사항과 문서를 확인하세요.','PHP 버전, 필수 확장, 저장소 권한과 라이선스 범위를 확인하세요. 문서에 따르면 더 안전하고 원활하게 시작할 수 있습니다.','소프트웨어 및 테마 설치 | TofiDesk','소프트웨어와 테마 설치 전 기술 체크리스트.','소프트웨어 설치, 테마 설치, 기술 요구사항']
                ]
            ];
            foreach ($content as $sourceSlug => $locales) {
                $article = row('SELECT id FROM articles WHERE id IN (SELECT article_id FROM translations WHERE slug=?) LIMIT 1', [$sourceSlug]);
                if (!$article) continue;
                foreach ($locales as $lang => $data) {
                    if (row('SELECT id FROM translations WHERE article_id=? AND lang=?', [$article['id'], $lang])) continue;
                    query("INSERT INTO translations(article_id,lang,title,slug,excerpt,body,seo_title,seo_description,keywords,status,scheduled_at,source_revision,revision,updated_at) VALUES(?,?,?,?,?,?,?,?,?,'published',NULL,1,1,datetime('now'))", [$article['id'],$lang,...$data]);
                }
            }
        }
        query("INSERT INTO migrations(version,applied_at) VALUES(14,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_inbound_mail_enrichment();
}

function migrate_inbound_mail_enrichment(): void {
    if (row('SELECT version FROM migrations WHERE version=15')) { migrate_platform_core(); return; }
    db()->beginTransaction();
    try {
        $columns = [];
        foreach (rows('PRAGMA table_info(inbound_messages)') as $column) $columns[(string)$column['name']] = true;
        foreach (['in_reply_to', 'references_header', 'thread_key'] as $column) {
            if (!isset($columns[$column])) db()->exec("ALTER TABLE inbound_messages ADD COLUMN {$column} TEXT NOT NULL DEFAULT ''");
        }
        if (!isset($columns['attachment_count'])) db()->exec('ALTER TABLE inbound_messages ADD COLUMN attachment_count INTEGER NOT NULL DEFAULT 0');
        db()->exec("CREATE TABLE IF NOT EXISTS inbound_attachments(id INTEGER PRIMARY KEY,message_id INTEGER NOT NULL REFERENCES inbound_messages(id) ON DELETE CASCADE,filename TEXT NOT NULL,content_type TEXT NOT NULL,size INTEGER NOT NULL,sha256 TEXT NOT NULL,storage_path TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        db()->exec('CREATE INDEX IF NOT EXISTS inbound_attachments_message ON inbound_attachments(message_id)');
        db()->exec('CREATE INDEX IF NOT EXISTS inbound_messages_thread ON inbound_messages(thread_key,received_at DESC)');
        query("INSERT INTO migrations(version,applied_at) VALUES(15,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_platform_core();
}

/**
 * Sector-neutral platform primitives. The branded Kas Nakliyat content stays
 * in the legacy tables; these registries are the extension boundary used by
 * future themes, modules and sector packs.
 */
function migrate_platform_core(): void {
    if (row('SELECT version FROM migrations WHERE version=16')) { migrate_platform_module_settings(); return; }
    db()->beginTransaction();
    try {
        db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS platform_modules(
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    version TEXT NOT NULL DEFAULT '1.0.0',
    kind TEXT NOT NULL DEFAULT 'core' CHECK(kind IN ('core','sector','theme','plugin')),
    manifest_json TEXT NOT NULL DEFAULT '{}',
    status TEXT NOT NULL DEFAULT 'enabled' CHECK(status IN ('enabled','disabled','quarantined')),
    source TEXT NOT NULL DEFAULT 'first-party',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS platform_hooks(
    id INTEGER PRIMARY KEY,
    module_id INTEGER NOT NULL REFERENCES platform_modules(id) ON DELETE CASCADE,
    event TEXT NOT NULL,
    priority INTEGER NOT NULL DEFAULT 100,
    handler TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'enabled' CHECK(status IN ('enabled','disabled')),
    UNIQUE(module_id,event,handler)
);
CREATE TABLE IF NOT EXISTS content_types(
    id INTEGER PRIMARY KEY,
    module_id INTEGER REFERENCES platform_modules(id) ON DELETE SET NULL,
    slug TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    schema_json TEXT NOT NULL DEFAULT '{}',
    status TEXT NOT NULL DEFAULT 'enabled' CHECK(status IN ('enabled','disabled')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS content_fields(
    id INTEGER PRIMARY KEY,
    content_type_id INTEGER NOT NULL REFERENCES content_types(id) ON DELETE CASCADE,
    slug TEXT NOT NULL,
    label TEXT NOT NULL,
    field_type TEXT NOT NULL CHECK(field_type IN ('text','textarea','number','boolean','date','datetime','url','media','json','location','relation')),
    config_json TEXT NOT NULL DEFAULT '{}',
    sort_order INTEGER NOT NULL DEFAULT 0,
    UNIQUE(content_type_id,slug)
);
CREATE TABLE IF NOT EXISTS content_entries(
    id INTEGER PRIMARY KEY,
    content_type_id INTEGER NOT NULL REFERENCES content_types(id) ON DELETE RESTRICT,
    slug TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','review','published','archived')),
    owner_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    location_id INTEGER,
    data_json TEXT NOT NULL DEFAULT '{}',
    published_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(content_type_id,slug)
);
CREATE TABLE IF NOT EXISTS content_entry_translations(
    id INTEGER PRIMARY KEY,
    entry_id INTEGER NOT NULL REFERENCES content_entries(id) ON DELETE CASCADE,
    locale TEXT NOT NULL,
    title TEXT NOT NULL DEFAULT '',
    summary TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '',
    seo_json TEXT NOT NULL DEFAULT '{}',
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','review','published')),
    revision INTEGER NOT NULL DEFAULT 1,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(entry_id,locale)
);
CREATE TABLE IF NOT EXISTS taxonomies(
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    hierarchical INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'enabled' CHECK(status IN ('enabled','disabled'))
);
CREATE TABLE IF NOT EXISTS taxonomy_terms(
    id INTEGER PRIMARY KEY,
    taxonomy_id INTEGER NOT NULL REFERENCES taxonomies(id) ON DELETE CASCADE,
    parent_id INTEGER REFERENCES taxonomy_terms(id) ON DELETE SET NULL,
    slug TEXT NOT NULL,
    name TEXT NOT NULL,
    locale TEXT NOT NULL DEFAULT 'tr',
    metadata_json TEXT NOT NULL DEFAULT '{}',
    UNIQUE(taxonomy_id,locale,slug)
);
CREATE TABLE IF NOT EXISTS content_term_links(
    entry_id INTEGER NOT NULL REFERENCES content_entries(id) ON DELETE CASCADE,
    term_id INTEGER NOT NULL REFERENCES taxonomy_terms(id) ON DELETE CASCADE,
    PRIMARY KEY(entry_id,term_id)
);
CREATE TABLE IF NOT EXISTS platform_locations(
    id INTEGER PRIMARY KEY,
    parent_id INTEGER REFERENCES platform_locations(id) ON DELETE CASCADE,
    country_code TEXT NOT NULL,
    level TEXT NOT NULL CHECK(level IN ('country','region','province','district','neighborhood','city','postal_area')),
    name TEXT NOT NULL,
    slug TEXT NOT NULL,
    postal_code TEXT NOT NULL DEFAULT '',
    latitude REAL,
    longitude REAL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    UNIQUE(country_code,level,parent_id,slug)
);
CREATE TABLE IF NOT EXISTS search_documents(
    id INTEGER PRIMARY KEY,
    entity_type TEXT NOT NULL,
    entity_id INTEGER NOT NULL,
    locale TEXT NOT NULL,
    title TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '',
    keywords TEXT NOT NULL DEFAULT '',
    location_id INTEGER REFERENCES platform_locations(id) ON DELETE SET NULL,
    status TEXT NOT NULL DEFAULT 'published' CHECK(status IN ('draft','published','hidden')),
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(entity_type,entity_id,locale)
);
CREATE INDEX IF NOT EXISTS search_documents_lookup ON search_documents(locale,status,entity_type,updated_at DESC);
CREATE TABLE IF NOT EXISTS platform_webhooks(
    id INTEGER PRIMARY KEY,
    module_id INTEGER REFERENCES platform_modules(id) ON DELETE CASCADE,
    event TEXT NOT NULL,
    target_url TEXT NOT NULL,
    secret_hash TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'enabled' CHECK(status IN ('enabled','disabled')),
    last_status INTEGER,
    last_error TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
SQL);
        $modules = [
            ['core-content','Content types and fields','core'],
            ['core-taxonomy','Categories, tags and relations','core'],
            ['core-location','Global location hierarchy','core'],
            ['core-search','Search and discovery index','core'],
            ['core-webhooks','REST and webhook events','core'],
        ];
        foreach ($modules as [$slug,$name,$kind]) query('INSERT OR IGNORE INTO platform_modules(slug,name,kind,manifest_json) VALUES(?,?,?,?)', [$slug,$name,$kind,json_encode(['api'=>1,'safe'=>true], JSON_UNESCAPED_SLASHES)]);
        $content = [
            ['page','Pages'],['listing','Listings'],['service','Services'],['product','Products'],['event','Events'],['booking','Bookings'],['directory','Directory entries'],
        ];
        $moduleId = (int)(row("SELECT id FROM platform_modules WHERE slug='core-content'")['id'] ?? 0);
        foreach ($content as [$slug,$label]) query('INSERT OR IGNORE INTO content_types(module_id,slug,label,schema_json) VALUES(?,?,?,?)', [$moduleId,$slug,$label,json_encode(['localized'=>true,'custom_fields'=>true], JSON_UNESCAPED_SLASHES)]);
        foreach ([['category','Categories',1],['tag','Tags',0],['feature','Features',0]] as [$slug,$name,$hierarchical]) query('INSERT OR IGNORE INTO taxonomies(slug,name,hierarchical) VALUES(?,?,?)',[$slug,$name,$hierarchical]);
        if (function_exists('country_profiles')) {
            foreach (array_keys(country_profiles()) as $country) {
                query('INSERT OR IGNORE INTO platform_locations(parent_id,country_code,level,name,slug) VALUES(NULL,?,?,?,?)', [$country, 'country', $country, strtolower($country)]);
            }
        }
        try { db()->exec("CREATE VIRTUAL TABLE IF NOT EXISTS search_documents_fts USING fts5(title,body,keywords,content='search_documents',content_rowid='id')"); } catch (Throwable) { /* LIKE fallback remains portable */ }
        if (function_exists('platform_sync_bundled_modules')) platform_sync_bundled_modules();
        query("INSERT INTO migrations(version,applied_at) VALUES(16,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_platform_module_settings();
}

function migrate_platform_module_settings(): void {
    if (row('SELECT version FROM migrations WHERE version=17')) { migrate_platform_menus_forms(); return; }
    db()->beginTransaction();
    try {
        db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS platform_module_settings(
    id INTEGER PRIMARY KEY,
    module_id INTEGER NOT NULL REFERENCES platform_modules(id) ON DELETE CASCADE,
    setting_key TEXT NOT NULL,
    value_json TEXT NOT NULL DEFAULT '{}',
    updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(module_id,setting_key)
);
CREATE INDEX IF NOT EXISTS platform_module_settings_lookup ON platform_module_settings(module_id,setting_key);
SQL);
        query("INSERT INTO migrations(version,applied_at) VALUES(17,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_platform_menus_forms();
}

function migrate_platform_menus_forms(): void {
    if (row('SELECT version FROM migrations WHERE version=18')) { migrate_platform_webhook_delivery(); return; }
    db()->beginTransaction();
    try {
        db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS platform_menus(
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL,
    locale TEXT NOT NULL,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'enabled' CHECK(status IN ('enabled','disabled')),
    UNIQUE(slug,locale)
);
CREATE TABLE IF NOT EXISTS platform_menu_items(
    id INTEGER PRIMARY KEY,
    menu_id INTEGER NOT NULL REFERENCES platform_menus(id) ON DELETE CASCADE,
    parent_id INTEGER REFERENCES platform_menu_items(id) ON DELETE CASCADE,
    label TEXT NOT NULL,
    target_url TEXT NOT NULL,
    target TEXT NOT NULL DEFAULT '_self' CHECK(target IN ('_self','_blank')),
    sort_order INTEGER NOT NULL DEFAULT 0,
    visible INTEGER NOT NULL DEFAULT 1 CHECK(visible IN (0,1)),
    metadata_json TEXT NOT NULL DEFAULT '{}'
);
CREATE INDEX IF NOT EXISTS platform_menu_items_lookup ON platform_menu_items(menu_id,parent_id,sort_order);
CREATE TABLE IF NOT EXISTS platform_forms(
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL,
    locale TEXT NOT NULL,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'enabled' CHECK(status IN ('enabled','disabled')),
    success_message TEXT NOT NULL DEFAULT '',
    settings_json TEXT NOT NULL DEFAULT '{}',
    UNIQUE(slug,locale)
);
CREATE TABLE IF NOT EXISTS platform_form_fields(
    id INTEGER PRIMARY KEY,
    form_id INTEGER NOT NULL REFERENCES platform_forms(id) ON DELETE CASCADE,
    slug TEXT NOT NULL,
    label TEXT NOT NULL,
    field_type TEXT NOT NULL CHECK(field_type IN ('text','textarea','email','url','number','boolean','date','datetime','json')),
    required INTEGER NOT NULL DEFAULT 0 CHECK(required IN (0,1)),
    config_json TEXT NOT NULL DEFAULT '{}',
    sort_order INTEGER NOT NULL DEFAULT 0,
    UNIQUE(form_id,slug)
);
CREATE TABLE IF NOT EXISTS platform_form_submissions(
    id INTEGER PRIMARY KEY,
    form_id INTEGER NOT NULL REFERENCES platform_forms(id) ON DELETE CASCADE,
    locale TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    source_ip_hash TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','processed','spam','archived')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS platform_form_submissions_lookup ON platform_form_submissions(form_id,status,created_at DESC);
SQL);
        query("INSERT INTO migrations(version,applied_at) VALUES(18,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_platform_webhook_delivery();
}

function migrate_platform_webhook_delivery(): void {
    if (row('SELECT version FROM migrations WHERE version=19')) { migrate_platform_adapters(); return; }
    db()->beginTransaction();
    try {
        $columns=array_map('strtolower',db()->query('PRAGMA table_info(platform_webhooks)')->fetchAll(PDO::FETCH_COLUMN,1));
        if (!in_array('secret_ciphertext',$columns,true)) db()->exec("ALTER TABLE platform_webhooks ADD COLUMN secret_ciphertext TEXT NOT NULL DEFAULT ''");
        db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS platform_webhook_deliveries(
    id INTEGER PRIMARY KEY,
    webhook_id INTEGER NOT NULL REFERENCES platform_webhooks(id) ON DELETE CASCADE,
    event TEXT NOT NULL,
    idempotency_key TEXT NOT NULL UNIQUE,
    payload_json TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'queued' CHECK(status IN ('queued','sending','delivered','failed')),
    attempts INTEGER NOT NULL DEFAULT 0,
    response_status INTEGER,
    last_error TEXT NOT NULL DEFAULT '',
    claimed_at TEXT,
    delivered_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS platform_webhook_deliveries_queue ON platform_webhook_deliveries(status,created_at);
SQL);
        query("INSERT INTO migrations(version,applied_at) VALUES(19,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_platform_adapters();
}

function migrate_platform_adapters(): void {
    if (row('SELECT version FROM migrations WHERE version=20')) { migrate_platform_runtime(); return; }
    db()->beginTransaction();
    try {
        db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS platform_adapters(
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    kind TEXT NOT NULL CHECK(kind IN ('ai','smtp','sms','whatsapp','maps','payment','storage','search')),
    name TEXT NOT NULL,
    version TEXT NOT NULL DEFAULT '1.0.0',
    status TEXT NOT NULL DEFAULT 'disabled' CHECK(status IN ('enabled','disabled')),
    config_json TEXT NOT NULL DEFAULT '{}',
    capabilities_json TEXT NOT NULL DEFAULT '[]',
    health_status TEXT NOT NULL DEFAULT 'unknown' CHECK(health_status IN ('unknown','ready','degraded','failed')),
    last_checked_at TEXT,
    last_error TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS platform_adapters_kind_status ON platform_adapters(kind,status);
SQL);
        query("INSERT INTO migrations(version,applied_at) VALUES(20,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_platform_runtime();
}

function migrate_platform_runtime(): void {
    if (row('SELECT version FROM migrations WHERE version=21')) { migrate_platform_discovery(); return; }
    db()->beginTransaction();
    try {
        db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS platform_cache(
    cache_key TEXT PRIMARY KEY,
    value_json TEXT NOT NULL,
    expires_at INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS platform_queue(
    id INTEGER PRIMARY KEY,
    queue_name TEXT NOT NULL,
    handler TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    idempotency_key TEXT UNIQUE,
    status TEXT NOT NULL DEFAULT 'queued' CHECK(status IN ('queued','running','completed','failed')),
    attempts INTEGER NOT NULL DEFAULT 0,
    available_at INTEGER NOT NULL DEFAULT 0,
    claimed_at TEXT,
    completed_at TEXT,
    last_error TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS platform_queue_ready ON platform_queue(status,available_at,created_at);
SQL);
        query("INSERT INTO migrations(version,applied_at) VALUES(21,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_platform_discovery();
}

function migrate_platform_discovery(): void {
    if (row('SELECT version FROM migrations WHERE version=22')) { migrate_platform_popularity(); return; }
    db()->beginTransaction();
    try {
        db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS platform_favorites(
    id INTEGER PRIMARY KEY,
    visitor_key TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id INTEGER NOT NULL,
    locale TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(visitor_key,entity_type,entity_id)
);
CREATE TABLE IF NOT EXISTS platform_comparisons(
    id INTEGER PRIMARY KEY,
    visitor_key TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(visitor_key,entity_type,entity_id)
);
CREATE TABLE IF NOT EXISTS platform_reviews(
    id INTEGER PRIMARY KEY,
    visitor_key TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id INTEGER NOT NULL,
    locale TEXT NOT NULL,
    rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5),
    title TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','published','rejected')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(visitor_key,entity_type,entity_id)
);
CREATE INDEX IF NOT EXISTS platform_favorites_lookup ON platform_favorites(visitor_key,entity_type);
CREATE INDEX IF NOT EXISTS platform_comparisons_lookup ON platform_comparisons(visitor_key,entity_type);
CREATE INDEX IF NOT EXISTS platform_reviews_public ON platform_reviews(entity_type,entity_id,status);
SQL);
        query("INSERT INTO migrations(version,applied_at) VALUES(22,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_platform_popularity();
}

function migrate_platform_popularity(): void {
    if (row('SELECT version FROM migrations WHERE version=23')) { migrate_platform_permissions(); return; }
    db()->beginTransaction();
    try {
        db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS platform_popularity(
    id INTEGER PRIMARY KEY,
    entity_type TEXT NOT NULL,
    entity_id INTEGER NOT NULL,
    locale TEXT NOT NULL DEFAULT '',
    views INTEGER NOT NULL DEFAULT 0,
    favorites INTEGER NOT NULL DEFAULT 0,
    downloads INTEGER NOT NULL DEFAULT 0,
    score REAL NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(entity_type,entity_id,locale)
);
CREATE INDEX IF NOT EXISTS platform_popularity_lookup ON platform_popularity(entity_type,locale,score DESC,updated_at DESC);
SQL);
        query("INSERT INTO migrations(version,applied_at) VALUES(23,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_platform_permissions();
}

function migrate_platform_permissions(): void {
    if (row('SELECT version FROM migrations WHERE version=24')) { migrate_platform_taxonomy_ownership(); return; }
    db()->beginTransaction();
    try {
        db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS platform_roles(
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'enabled' CHECK(status IN ('enabled','disabled')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS platform_permissions(
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'enabled' CHECK(status IN ('enabled','disabled')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS platform_role_permissions(
    role_id INTEGER NOT NULL REFERENCES platform_roles(id) ON DELETE CASCADE,
    permission_id INTEGER NOT NULL REFERENCES platform_permissions(id) ON DELETE CASCADE,
    PRIMARY KEY(role_id,permission_id)
);
CREATE INDEX IF NOT EXISTS platform_role_permissions_lookup ON platform_role_permissions(role_id,permission_id);
SQL);
        query("INSERT INTO migrations(version,applied_at) VALUES(24,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    migrate_platform_taxonomy_ownership();
}

function migrate_platform_taxonomy_ownership(): void {
    if (row('SELECT version FROM migrations WHERE version=25')) return;
    db()->beginTransaction();
    try {
        $hasModule = (bool)db()->query("SELECT 1 FROM pragma_table_info('taxonomies') WHERE name='module_id'")->fetchColumn();
        if (!$hasModule) db()->exec('ALTER TABLE taxonomies ADD COLUMN module_id INTEGER REFERENCES platform_modules(id) ON DELETE SET NULL');
        db()->exec('CREATE INDEX IF NOT EXISTS taxonomies_module_lookup ON taxonomies(module_id,status,slug)');
        query("INSERT INTO migrations(version,applied_at) VALUES(25,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
}

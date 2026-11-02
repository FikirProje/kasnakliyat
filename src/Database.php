<?php
declare(strict_types=1);
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $path = envv('DB_PATH', storage_path('app.sqlite'));
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL;');
    }
    return $pdo;
}
function query(string $sql, array $params = []): PDOStatement { $statement = db()->prepare($sql); $statement->execute($params); return $statement; }
function rows(string $sql, array $params = []): array { return query($sql, $params)->fetchAll(); }
function row(string $sql, array $params = []): ?array { return query($sql, $params)->fetch() ?: null; }
function setting(string $key, string $default = ''): string {
    static $defaults;
    $defaults ??= is_file(ROOT . '/config/site.php') ? require ROOT . '/config/site.php' : [];
    $r = row('SELECT value FROM settings WHERE key=?', [$key]);
    return $r ? (string)$r['value'] : (string)($defaults[$key] ?? $default);
}
function save_setting(string $key, string $value): void { query('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value', [$key, $value]); }
function migrate(): void {
    db()->exec('CREATE TABLE IF NOT EXISTS migrations(version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL)');
    if (row('SELECT version FROM migrations WHERE version=1')) { migrate_extensions(); return; }
    db()->beginTransaction();
    try {
        db()->exec(<<<'SQL'
CREATE TABLE settings(key TEXT PRIMARY KEY,value TEXT NOT NULL);
CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT NOT NULL,email TEXT NOT NULL UNIQUE COLLATE NOCASE,password_hash TEXT NOT NULL,role TEXT NOT NULL CHECK(role IN ('admin','editor','operator')),totp_secret TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE requests(id INTEGER PRIMARY KEY,reference TEXT NOT NULL UNIQUE,type TEXT NOT NULL CHECK(type IN ('quote','appointment')),name TEXT NOT NULL,email TEXT NOT NULL,phone TEXT NOT NULL,origin TEXT NOT NULL,destination TEXT NOT NULL,cargo TEXT NOT NULL,preferred_date TEXT NOT NULL,slot TEXT NOT NULL DEFAULT '',notes TEXT NOT NULL DEFAULT '',status TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','quoted','proposed','confirmed','rejected')),price TEXT NOT NULL DEFAULT '',admin_note TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
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
        $tr=['Kaş şehir içi ev taşımacılığı','kas-sehir-ici-ev-tasimaciligi','Kaş içindeki ev taşıma talebiniz için güzergâh, eşya ve erişim ayrıntılarını birlikte değerlendirelim.',"Kaş içinde ev taşıma talebiniz için taşınacak eşyayı, kat ve erişim koşullarını, adresleri ve tercih ettiğiniz tarihi paylaşabilirsiniz. Değerlendirme sonrasında kapsam ve uygunluk netleştirilir.\n\nBu sayfa başlangıç hizmet metnidir. İşletmeniz paketleme, montaj, sigorta veya asansörlü taşıma sunuyorsa bunları yalnız doğrulanmış kapsamınıza göre düzenleyin.",'Kaş şehir içi ev taşımacılığı | Kaş Nakliyat','Kaş içi ev taşıma talebinde paylaşılabilecek temel bilgiler.','Kaş ev taşımacılığı, şehir içi nakliyat, evden eve nakliyat'];
        $en=['Local home moving in Kaş','local-home-moving-kas','Share your addresses, household items and access details for a move within Kaş.',"For a home-moving enquiry within Kaş, share the items to be moved, floors and access conditions, addresses and your preferred date. Scope and availability are clarified after review.\n\nThis is starter service copy. Only describe packing, assembly, insurance or lift services if your business has confirmed that it provides them.",'Local home moving in Kaş | Kaş Nakliyat','Useful details to prepare for a local home-moving enquiry in Kaş.','Kaş home moving, local transport, household move'];
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
    if (row('SELECT version FROM migrations WHERE version=10')) {
        query("UPDATE themes SET locale='global' WHERE name='Default Transport' AND locale='en' AND NOT EXISTS(SELECT 1 FROM themes WHERE locale='global')");
        return;
    }
    db()->beginTransaction();
    try {
        db()->exec("CREATE TABLE themes(id INTEGER PRIMARY KEY,name TEXT NOT NULL UNIQUE,locale TEXT NOT NULL DEFAULT 'en',primary_color TEXT NOT NULL,accent_color TEXT NOT NULL,custom_css TEXT NOT NULL DEFAULT '',status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','published')),is_active INTEGER NOT NULL DEFAULT 0 CHECK(is_active IN (0,1)),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        query("INSERT INTO themes(name,locale,primary_color,accent_color,custom_css,status,is_active) VALUES('Default Transport','global','#0D2E42','#FF9149','','published',1)");
        query("INSERT INTO migrations(version,applied_at) VALUES(10,datetime('now'))");
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
}

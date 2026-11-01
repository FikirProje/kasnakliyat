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
    if (row('SELECT version FROM migrations WHERE version=3')) return;
    db()->beginTransaction();
    try {
        db()->exec("ALTER TABLE media ADD COLUMN purpose TEXT NOT NULL DEFAULT 'customer' CHECK(purpose IN ('customer','library'))");
        query('INSERT INTO migrations(version,applied_at) VALUES(3,datetime(\'now\'))');
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
}

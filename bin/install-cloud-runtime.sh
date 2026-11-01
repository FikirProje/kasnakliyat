#!/bin/sh
# Non-root Debian 13 PHP runtime. APT validates signed metadata and package hashes.
# Requires network to deb.debian.org through the inherited session proxy.
set -eu
runtime_root=/workspace/.runtime
mkdir -p "$runtime_root/apt/lists/partial" "$runtime_root/apt/archives/partial" "$runtime_root/php/conf.d" "$runtime_root/bin"
cat > "$runtime_root/apt/sources.list" <<'SOURCES'
deb [signed-by=/usr/share/keyrings/debian-archive-keyring.gpg] https://deb.debian.org/debian trixie main
deb [signed-by=/usr/share/keyrings/debian-archive-keyring.gpg] https://deb.debian.org/debian-security trixie-security main
SOURCES
apt_run() {
    /usr/bin/apt-get \
        -o Dir::State::lists="$runtime_root/apt/lists" \
        -o Dir::Cache::archives="$runtime_root/apt/archives" \
        -o Dir::Etc::sourcelist="$runtime_root/apt/sources.list" \
        -o Dir::Etc::sourceparts=- \
        -o Debug::NoLocking=1 \
        -o APT::Sandbox::User="$(id -un)" "$@"
}
apt_run update
apt_run --download-only --assume-yes --no-install-recommends install \
    php8.4-cli php8.4-common php8.4-sqlite3 php8.4-mbstring php8.4-curl \
    php8.4-gd php8.4-intl php8.4-xml
for package in "$runtime_root"/apt/archives/*.deb; do
    dpkg-deb -x "$package" "$runtime_root/php"
done
cat > "$runtime_root/php/php.ini" <<'PHPINI'
[PHP]
date.timezone=Europe/Istanbul
display_errors=Off
log_errors=On
error_reporting=E_ALL
memory_limit=256M
post_max_size=20M
upload_max_filesize=10M
max_file_uploads=8
session.use_strict_mode=1
session.cookie_httponly=1
session.cookie_samesite=Lax
extension_dir=/workspace/.runtime/php/usr/lib/php/20240924
extension=ctype
extension=fileinfo
extension=iconv
extension=mbstring
extension=pdo
extension=pdo_sqlite
extension=sqlite3
extension=curl
extension=gd
extension=intl
extension=dom
extension=xml
extension=xmlreader
extension=xmlwriter
extension=simplexml
extension=tokenizer
PHPINI
cat > "$runtime_root/bin/php" <<'WRAPPER'
#!/bin/sh
export LD_LIBRARY_PATH="/workspace/.runtime/php/usr/lib/x86_64-linux-gnu${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
export PHP_INI_SCAN_DIR=/workspace/.runtime/php/conf.d
exec /workspace/.runtime/php/usr/bin/php8.4 -c /workspace/.runtime/php/php.ini "$@"
WRAPPER
chmod +x "$runtime_root/bin/php"
"$runtime_root/bin/php" -r '
$required=["pdo_sqlite","sqlite3","mbstring","fileinfo","curl","gd","intl","dom"];
foreach($required as $extension) {if(!extension_loaded($extension)){fwrite(STDERR,"Missing PHP extension: $extension\n"); exit(1);}}
$db=new PDO("sqlite::memory:");
$db->exec("CREATE TABLE verify (value TEXT)");
$db->exec("INSERT INTO verify VALUES (\"ready\")");
if($db->query("SELECT value FROM verify")->fetchColumn()!=="ready"){exit(1);}
echo "PHP ".PHP_VERSION." runtime ready with SQLite, GD, cURL, intl, and mbstring.\n";
'

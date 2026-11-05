<?php
declare(strict_types=1);

/** Static guard for the first-run country profile contract. */
$installer = file_get_contents(__DIR__.'/../src/Installer.php');
$i18n = file_get_contents(__DIR__.'/../src/InstallerI18n.php');
if ($installer === false || $i18n === false) throw new RuntimeException('Installer sources could not be read.');
foreach (['country_code','timezone','currency_code','unit_system','save_setting(\'timezone\'','save_setting(\'currency_code\'','save_setting(\'unit_system\''] as $needle) {
    if (!str_contains($installer, $needle)) throw new RuntimeException('Missing installer profile field: '.$needle);
}
foreach (['country','timezone','currency','units','profile_note','metric','imperial'] as $key) {
    if (substr_count($i18n, "'".$key."'=>") < 14) throw new RuntimeException('Installer profile translation is incomplete: '.$key);
}
echo "PASS application installer persists country, locale profile, currency, timezone and units\n";

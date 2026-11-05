<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/I18n.php';

$genericSamples = [
    'en' => 'Transport, cargo, logistics and moving software',
    'de' => 'Transport, Fracht, Umzug und Logistik',
    'fr' => 'Transport, chargement, déménagement et logistique',
    'es' => 'Transporte, carga, mudanza y logística',
];
foreach ($genericSamples as $locale => $sample) {
    $neutral = generic_public_text($sample, $locale);
    if (preg_match('/transport|cargo|logistics|moving|Transport|Fracht|Umzug|Logistik|transport|chargement|déménagement|logistique|Transporte|carga|mudanza|logística/u', $neutral)) {
        throw new RuntimeException('Generic public copy still contains sector-specific wording for '.$locale.': '.$neutral);
    }
}

$source = file_get_contents(__DIR__ . '/../src/Database.php');
if ($source === false) throw new RuntimeException('Database source could not be read.');
$locales = ['tr','en','de','fr','es','it','pt','ru','ar','fa','nl','zh','ja','ko'];
$migration = substr($source, strpos($source, 'function migrate_generic_demo_locales(): void'));
if ($migration === '') throw new RuntimeException('Generic demo locale migration is missing.');
foreach ($locales as $locale) {
    $count = preg_match_all("/'" . preg_quote($locale, '/') . "'=>/", $migration, $matches);
    if ($count < 3) throw new RuntimeException($locale . ' generic demo content is incomplete.');
}
foreach (['dijital-urun-secme-rehberi','surum-ve-lisans-bilgilerini-degerlendirme','software-and-theme-setup'] as $slug) {
    if (!str_contains($migration, "'" . $slug . "' =>")) throw new RuntimeException('Missing generic demo record: ' . $slug);
}
if (!str_contains($migration, "version=14") || !str_contains($migration, "VALUES(14")) {
    throw new RuntimeException('Generic demo migration is not versioned.');
}
echo "PASS generic marketplace demo has 3 records × 14 locales with SEO fields\n";

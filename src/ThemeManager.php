<?php
declare(strict_types=1);

/** Read-only, data-only theme SDK helpers. Theme packages never execute PHP during discovery. */
function theme_manifest_errors(array $manifest): array
{
    $errors = [];
    $slug = strtolower(trim((string)($manifest['slug'] ?? '')));
    $name = trim((string)($manifest['name'] ?? ''));
    $version = trim((string)($manifest['version'] ?? ''));
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,80}$/', $slug)) $errors[] = 'slug';
    if ($name === '' || mb_strlen($name) > 180) $errors[] = 'name';
    if (!preg_match('/^\d+\.\d+\.\d+([.-][a-z0-9.-]+)?$/i', $version)) $errors[] = 'version';
    if (isset($manifest['parent']) && $manifest['parent'] !== null && !preg_match('/^[a-z0-9][a-z0-9._-]{1,80}$/', (string)$manifest['parent'])) $errors[] = 'parent';
    foreach (['sections','tokens','requirements','translations'] as $key) if (isset($manifest[$key]) && !is_array($manifest[$key])) $errors[] = $key;
    $sections = (array)($manifest['sections'] ?? []);
    foreach ($sections as $section) {
        if (!is_array($section) || !preg_match('/^[a-z0-9][a-z0-9_-]{1,80}$/', (string)($section['slug'] ?? ''))) $errors[] = 'sections';
        if (isset($section['component']) && !preg_match('/^[a-z0-9][a-z0-9._\/-]{1,120}$/i', (string)$section['component'])) $errors[] = 'component';
    }
    foreach ((array)($manifest['translations'] ?? []) as $locale) if (!isset(core_locales()[(string)$locale])) $errors[] = 'translations';
    return array_values(array_unique(array_merge($errors,platform_manifest_dependency_errors(['requires'=>(array)($manifest['requirements']??[])]))));
}

function theme_discover_manifests(?string $directory = null): array
{
    $directory ??= ROOT.'/themes';
    if (!is_dir($directory)) return [];
    $found = [];
    foreach (glob(rtrim($directory, '/\\').'/*/theme.json') ?: [] as $file) {
        $raw = file_get_contents($file);
        $manifest = is_string($raw) ? json_decode($raw, true) : null;
        $found[] = is_array($manifest) ? ['path'=>$file, 'manifest'=>$manifest, 'errors'=>theme_manifest_errors($manifest)] : ['path'=>$file, 'manifest'=>null, 'errors'=>['json']];
    }
    return $found;
}

function theme_manifest_by_slug(string $slug): ?array
{
    foreach (theme_discover_manifests() as $item) if (($item['manifest']['slug'] ?? '') === $slug && $item['errors'] === []) return $item['manifest'];
    return null;
}

function theme_resolve_sections(string $slug, array $overrides = []): array
{
    $manifest = theme_manifest_by_slug($slug);
    if ($manifest === null) return [];
    $sections = [];
    if (!empty($manifest['parent'])) $sections = theme_resolve_sections((string)$manifest['parent']);
    foreach ((array)($manifest['sections'] ?? []) as $section) {
        if (!is_array($section) || empty($section['slug'])) continue;
        $key = (string)$section['slug'];
        $sectionOverrides = is_array($section['overrides'] ?? null) ? $section['overrides'] : [];
        unset($section['overrides']);
        $sections[$key] = array_replace($sections[$key] ?? [], $section, $sectionOverrides);
    }
    foreach ($overrides as $key => $value) if (is_array($value) && isset($sections[$key])) $sections[$key] = array_replace($sections[$key], $value);
    return $sections;
}

/** Resolve data-only design tokens across parent/child themes without executing package code. */
function theme_resolve_design_tokens(string $slug, array $overrides = []): array
{
    $manifest = theme_manifest_by_slug($slug);
    if ($manifest === null) return [];
    $tokens = [];
    if (!empty($manifest['parent'])) $tokens = theme_resolve_design_tokens((string)$manifest['parent']);
    $declared = is_array($manifest['tokens'] ?? null) ? $manifest['tokens'] : [];
    $tokens = array_replace($tokens, $declared, $overrides);
    return theme_validate_design_tokens($tokens) === [] ? $tokens : [];
}

function theme_validate_design_tokens(array $tokens): array
{
    $errors = [];
    foreach ($tokens as $key => $value) {
        if (!preg_match('/^[a-z][a-z0-9_-]{1,60}$/', (string)$key)) { $errors[] = 'token_key'; continue; }
        if (is_string($value) && (mb_strlen($value) > 120 || preg_match('/<|javascript:|data:/i', $value))) $errors[] = 'token_value';
        if (is_array($value) && count($value) > 20) $errors[] = 'token_array';
    }
    return array_values(array_unique($errors));
}

function theme_validate_demo_payload(array $payload): array
{
    $errors = [];
    if (count($payload) > 100) $errors[] = 'too_many_items';
    foreach ($payload as $key => $value) {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,60}$/i', (string)$key)) $errors[] = 'field_key';
        if (is_string($value) && mb_strlen($value) > 5000) $errors[] = 'field_length';
        if (is_array($value) && count($value) > 100) $errors[] = 'nested_items';
    }
    return array_values(array_unique($errors));
}

function theme_import_demo(string $slug, array $payload, ?int $ownerId = null): array
{
    if (theme_manifest_by_slug($slug) === null) throw new InvalidArgumentException('Theme manifest is missing or invalid.');
    $pages = $payload['pages'] ?? $payload['entries'] ?? null;
    if (!is_array($pages) || count($pages) > 50) throw new InvalidArgumentException('Demo must contain at most 50 content entries.');
    $created = [];
    db()->beginTransaction();
    try {
        foreach ($pages as $page) {
            if (!is_array($page)) throw new InvalidArgumentException('Demo entry must be an object.');
            $type = strtolower(trim((string)($page['type'] ?? 'page')));
            $entrySlug = strtolower(trim((string)($page['slug'] ?? '')));
            if (!preg_match('/^[a-z0-9][a-z0-9-]{1,100}$/', $entrySlug) || !platform_content_type($type)) throw new InvalidArgumentException('Demo content type or slug is invalid.');
            $contentType = platform_content_type($type);
            $data = is_array($page['data'] ?? null) ? $page['data'] : [];
            $existing = row('SELECT id FROM content_entries WHERE content_type_id=? AND slug=?', [(int)$contentType['id'],$entrySlug]);
            if ($existing) {
                $entryId=(int)$existing['id'];
                query("UPDATE content_entries SET data_json=?,owner_id=COALESCE(?,owner_id),status='draft',published_at=NULL,updated_at=datetime('now') WHERE id=?", [json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$ownerId,$entryId]);
            } else $entryId=platform_create_entry($type,$entrySlug,$data,$ownerId,null);
            $translations = $page['translations'] ?? [];
            if (!is_array($translations)) throw new InvalidArgumentException('Demo translations must be an object.');
            foreach ($translations as $locale => $translation) {
                if (!is_string($locale) || !is_array($translation) || !isset(core_locales()[$locale])) throw new InvalidArgumentException('Demo translation locale is invalid.');
                platform_save_translation($entryId,$locale,['title'=>$translation['title']??'','summary'=>$translation['summary']??'','body'=>$translation['body']??'','seo'=>is_array($translation['seo']??null)?$translation['seo']:[]],'draft');
            }
            $created[]=$entryId;
        }
        db()->commit();
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    return ['theme'=>$slug,'entry_ids'=>$created,'count'=>count($created),'status'=>'draft'];
}

function theme_safe_asset_path(string $themeSlug, string $relative): string
{
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,80}$/', $themeSlug) || str_contains($relative, "\0") || preg_match('#(^|[\\/])\.\.([\\/]|$)#', $relative)) throw new InvalidArgumentException('Unsafe theme asset path.');
    $base = realpath(ROOT.'/themes/'.$themeSlug);
    if ($base === false) throw new RuntimeException('Theme not found.');
    $candidate = $base.DIRECTORY_SEPARATOR.ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
    $parent = realpath(dirname($candidate));
    if ($parent === false || !str_starts_with($parent, $base)) throw new InvalidArgumentException('Asset path is outside the theme.');
    return $candidate;
}

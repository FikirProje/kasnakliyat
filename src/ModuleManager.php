<?php
declare(strict_types=1);

/**
 * Read-only manifest discovery and validation for first-party/approved packs.
 * Package code is never executed during discovery; hooks are enabled only
 * after an administrator-approved release has passed the product quality gate.
 */
function platform_requirement_matches(string $version, string $constraint): bool
{
    $constraint=trim($constraint); if($constraint==='') return true;
    if(preg_match('/^(>=|<=|>|<|=|~|\^)?\s*(\d+(?:\.\d+){0,2})$/',$constraint,$m)) {
        $op=$m[1]!==''?$m[1]:'='; $required=$m[2];
        if($op==='^'){ $parts=array_map('intval',explode('.',$required)); $upper=(($parts[0]??0)+1).'.0.0'; return version_compare($version,$required,'>=')&&version_compare($version,$upper,'<'); }
        if($op==='~'){ $parts=array_map('intval',explode('.',$required)); $upper=($parts[0]??0).'.'.(($parts[1]??0)+1).'.0'; return version_compare($version,$required,'>=')&&version_compare($version,$upper,'<'); }
        return version_compare($version,$required,$op);
    }
    return false;
}

function platform_manifest_dependency_errors(array $manifest): array
{
    $errors=[]; $requires=(array)($manifest['requires']??[]);
    foreach(['php','tofidesk','platform'] as $key) if(isset($requires[$key])) {
        $actual=$key==='php'?PHP_VERSION:envv('APP_VERSION','1.0.0');
        if(!platform_requirement_matches($actual,(string)$requires[$key])) $errors[]='requires_'.$key;
    }
    return $errors;
}

function platform_module_manifest_errors(array $manifest): array
{
    $errors = [];
    $slug = strtolower(trim((string)($manifest['slug'] ?? '')));
    $name = trim((string)($manifest['name'] ?? ''));
    $version = trim((string)($manifest['version'] ?? ''));
    $kind = (string)($manifest['kind'] ?? '');
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,80}$/', $slug)) $errors[] = 'slug';
    if ($name === '' || mb_strlen($name) > 180) $errors[] = 'name';
    if (!preg_match('/^\d+\.\d+\.\d+([.-][a-z0-9.-]+)?$/i', $version)) $errors[] = 'version';
    if (!in_array($kind, ['sector','plugin','theme'], true)) $errors[] = 'kind';
    foreach (['requires','content_types','taxonomies','hooks','permissions','translations','fields'] as $key) if (isset($manifest[$key]) && !is_array($manifest[$key])) $errors[] = $key;
    foreach ((array)($manifest['content_types'] ?? []) as $type) if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,80}$/', (string)$type)) $errors[] = 'content_types';
    foreach ((array)($manifest['taxonomies'] ?? []) as $taxonomy) if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,80}$/', (string)$taxonomy)) $errors[] = 'taxonomies';
    $allowedFieldTypes = ['text','textarea','number','boolean','date','datetime','url','media','json','location','relation'];
    foreach ((array)($manifest['fields'] ?? []) as $type => $fields) {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,80}$/', (string)$type) || !is_array($fields)) { $errors[] = 'fields'; continue; }
        foreach ($fields as $field) {
            if (!is_array($field) || !preg_match('/^[a-z0-9][a-z0-9_-]{1,80}$/', (string)($field['slug'] ?? '')) || trim((string)($field['label'] ?? '')) === '' || !in_array((string)($field['field_type'] ?? 'text'), $allowedFieldTypes, true)) $errors[] = 'fields';
        }
    }
    foreach ((array)($manifest['translations'] ?? []) as $locale) if (!isset(core_locales()[(string)$locale])) $errors[] = 'translations';
    foreach ((array)($manifest['hooks'] ?? []) as $event) if (!in_array((string)$event, platform_allowed_events(), true)) $errors[] = 'hooks';
    foreach ((array)($manifest['permissions'] ?? []) as $permission) if (!preg_match('/^[a-z][a-z0-9_.-]{1,100}$/', (string)$permission)) $errors[] = 'permissions';
    return array_values(array_unique(array_merge($errors,platform_manifest_dependency_errors($manifest))));
}

function platform_discover_module_manifests(?string $directory = null): array
{
    $directories = $directory === null ? [ROOT.'/modules', ROOT.'/plugins'] : [$directory];
    $found = [];
    foreach ($directories as $base) {
        if (!is_dir($base)) continue;
        foreach (array_merge(glob(rtrim($base, '/\\').'/*/module.json') ?: [], glob(rtrim($base, '/\\').'/*/plugin.json') ?: []) as $file) {
            $raw = file_get_contents($file);
            $manifest = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($manifest)) { $found[] = ['path'=>$file,'manifest'=>null,'errors'=>['json']]; continue; }
            $found[] = ['path'=>$file,'manifest'=>$manifest,'errors'=>platform_module_manifest_errors($manifest)];
        }
    }
    return $found;
}

function platform_sync_bundled_modules(): array
{
    $synced = [];
    foreach (platform_discover_module_manifests() as $item) {
        if ($item['errors'] !== [] || !is_array($item['manifest'])) continue;
        $manifest = $item['manifest'];
        $id = platform_register_module($manifest);
        foreach ((array)($manifest['content_types'] ?? []) as $type) {
            // A sector may specialise a generic core type (for example
            // `listing`). Transfer ownership from core only; never silently
            // steal a type already owned by another extension.
            query("INSERT INTO content_types(module_id,slug,label,schema_json) VALUES(?,?,?,?) ON CONFLICT(slug) DO UPDATE SET module_id=CASE WHEN content_types.module_id IS NULL OR content_types.module_id IN (SELECT id FROM platform_modules WHERE kind='core') THEN excluded.module_id ELSE content_types.module_id END, label=CASE WHEN content_types.module_id IS NULL OR content_types.module_id IN (SELECT id FROM platform_modules WHERE kind='core') THEN excluded.label ELSE content_types.label END, schema_json=CASE WHEN content_types.module_id IS NULL OR content_types.module_id IN (SELECT id FROM platform_modules WHERE kind='core') THEN excluded.schema_json ELSE content_types.schema_json END", [$id, $type, ucwords(str_replace(['-','_'], ' ', (string)$type)), json_encode(['module'=>$manifest['slug']], JSON_UNESCAPED_SLASHES)]);
        }
        $moduleEnabled = (string)(row('SELECT status FROM platform_modules WHERE id=?', [$id])['status'] ?? '') === 'enabled';
        if ($moduleEnabled) {
            foreach ((array)($manifest['fields'] ?? []) as $type => $fields) {
                foreach ((array)$fields as $field) platform_register_content_field((string)$type, (array)$field);
            }
        }
        $hasTaxonomyOwner = (bool)db()->query("SELECT 1 FROM pragma_table_info('taxonomies') WHERE name='module_id'")->fetchColumn();
        foreach ((array)($manifest['taxonomies'] ?? []) as $taxonomy) {
            $taxonomySlug = (string)$taxonomy;
            $taxonomyName = ucwords(str_replace(['-','_'], ' ', $taxonomySlug));
            if ($hasTaxonomyOwner) query('INSERT INTO taxonomies(slug,name,hierarchical,module_id) VALUES(?,?,?,?) ON CONFLICT(slug) DO UPDATE SET name=excluded.name,module_id=excluded.module_id,status=\'enabled\'', [$taxonomySlug, $taxonomyName, 0, $id]);
            else query('INSERT OR IGNORE INTO taxonomies(slug,name,hierarchical) VALUES(?,?,?)', [$taxonomySlug, $taxonomyName, 0]);
        }
        $synced[] = (string)$manifest['slug'];
    }
    return $synced;
}

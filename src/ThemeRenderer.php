<?php
declare(strict_types=1);

function theme_render_sections(string $slug, array $context = []): string
{
    $manifest = theme_manifest_by_slug($slug);
    if ($manifest === null) throw new InvalidArgumentException('Theme is unavailable.');
    $tokens = theme_resolve_design_tokens($slug, is_array($context['tokens'] ?? null) ? $context['tokens'] : []);
    if (theme_validate_design_tokens($tokens) !== []) throw new InvalidArgumentException('Theme design tokens are invalid.');
    $brand = trim((string)($context['brand'] ?? 'TofiDesk')) ?: 'TofiDesk';
    $locale = (string)($context['locale'] ?? 'en');
    $title = trim((string)($context['title'] ?? platform_search_labels('title',$locale))) ?: $brand;
    $description = trim((string)($context['description'] ?? platform_search_labels('intro',$locale)));
    $features = is_array($context['features'] ?? null) ? array_slice($context['features'],0,12) : [];
    if (!$features) $features = [['title'=>'Flexible content','text'=>'Build pages and products from reusable sections.'],['title'=>'Global by design','text'=>'Localize content, SEO and business rules for every market.'],['title'=>'Ready to extend','text'=>'Add reviewed sector modules without changing the core.']];
    $sections = theme_resolve_sections($slug, is_array($context['sections'] ?? null) ? $context['sections'] : []);
    $out = '<div class="td-theme td-theme-'.e($slug).'" lang="'.e($locale).'">';
    foreach ($sections as $section) {
        $sectionSlug = (string)($section['slug'] ?? '');
        $classes = 'td-section td-section-'.e($sectionSlug);
        $sectionTitle = trim((string)($section['title'] ?? $title)) ?: $title;
        $sectionDescription = trim((string)($section['description'] ?? $description)) ?: $description;
        if ($sectionSlug === 'header') $out .= '<div class="'.$classes.'"><strong>'.e($brand).'</strong><span>'.e($locale).'</span></div>';
        elseif ($sectionSlug === 'hero') $out .= '<section class="'.$classes.'"><p class="td-eyebrow">'.e($brand).'</p><h1>'.e($sectionTitle).'</h1><p>'.e($sectionDescription).'</p><a href="#td-features">'.e(platform_search_labels('submit',$locale)).' →</a></section>';
        elseif ($sectionSlug === 'feature_grid') {
            $out .= '<section id="td-features" class="'.$classes.'"><div class="td-grid">';
            foreach ($features as $feature) if (is_array($feature)) $out .= '<article><h2>'.e((string)($feature['title'] ?? 'Feature')).'</h2><p>'.e((string)($feature['text'] ?? '')).'</p></article>';
            $out .= '</div></section>';
        } elseif ($sectionSlug === 'content') $out .= '<section class="'.$classes.'"><h2>'.e($sectionTitle).'</h2><p>'.e($sectionDescription).'</p></section>';
        elseif ($sectionSlug === 'testimonials') $out .= '<section class="'.$classes.'"><blockquote>“'.e($sectionDescription).'”</blockquote></section>';
        elseif ($sectionSlug === 'cta') $out .= '<section class="'.$classes.'"><h2>'.e($sectionTitle).'</h2><p>'.e($sectionDescription).'</p><a href="#td-features">'.e(platform_search_labels('submit',$locale)).' →</a></section>';
        elseif ($sectionSlug === 'footer') $out .= '<footer class="'.$classes.'">'.e($brand).' · '.e($locale).'</footer>';
    }
    return $out.'</div>';
}

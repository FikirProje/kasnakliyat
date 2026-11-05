<?php
declare(strict_types=1);

if ($path !== '/theme-checkout') return;

// Compatibility endpoint only. Theme sales must use the same first-class
// product checkout as software sales; the old route created text-only orders
// that could not receive product_id, plan_id, releases or entitlements.
$slug = strtolower(trim((string)($_GET['product'] ?? '')));
// Never silently select a customer/demo product. The marketplace must receive
// an explicit product slug so software, themes and add-ons cannot be mixed.
if ($slug === '') {
    http_response_code(400);
    buyer_layout('Product selection required', '<div class="card"><h1>Product selection required</h1><p>Choose a published product from the marketplace before continuing.</p><a class="button" href="./shop">Back to marketplace</a></div>');
}
$product = one("SELECT slug FROM products WHERE slug=? AND kind='theme' AND status='published'", [$slug]);
if (!$product) {
    http_response_code(404);
    buyer_layout('Theme unavailable', '<div class="card"><h1>Theme unavailable</h1><p>This theme is not currently available for purchase.</p><a class="button" href="./shop">Back to marketplace</a></div>');
}
header('Location: ./product?slug='.rawurlencode((string)$product['slug']), true, 302);
exit;

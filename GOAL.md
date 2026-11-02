# TofiDesk Transportation — global sales and licensing goal

## Outcome

Ship a marketplace-ready Transport CMS and a separate TofiDesk sales, licensing and theme-delivery centre. A buyer can discover the product in their language, purchase it, receive a licence only after verified payment or marketplace purchase verification, install it on an allowed domain, receive the assigned theme and manage their account without support intervention.

## Completion criteria

- The public storefront, buyer account, checkout, licences, invoices, support and theme catalogue are available in Turkish, English, German, French, Spanish, Italian, Portuguese, Dutch, Russian, Arabic, Chinese, Japanese, Korean and Persian. Arabic and Persian render RTL.
- Browser language chooses the initial storefront locale; the visible language selector and the buyer profile can override it.
- A product order has an immutable order reference, amount, currency, tax/invoice fields, payment status and audit history.
- PayTR payment requests are created server-side. A licence is issued only after PayTR’s signed callback has been validated with environment-only merchant credentials. Failed, pending or spoofed callbacks never issue a licence.
- The buyer portal lists owned products, licence state, approved domains, invoices, payments, downloads and support tickets.
- CodeCanyon purchase codes are verified by the author-side Envato API before an equivalent licence is issued. Author credentials never enter buyer installations or Git.
- An administrator can publish, retire and assign data-only themes per locale. An eligible installed product receives only the theme assigned to its active licence.
- Product downloads, customer contact data, invoices and support messages are access-controlled, auditable and excluded from marketplace release archives.
- Storefront and buyer portal pass desktop, tablet, mobile, RTL, locale, payment-callback security and licensing integration tests before release.

## Delivery order

1. Localized storefront and locale preference.
2. Buyer accounts and authenticated portal.
3. Orders, invoices and support tickets.
4. PayTR signed checkout/callback integration and safe licence issuance.
5. Envato author-sale verification.
6. Theme catalogue, assignment and client delivery.
7. Full cross-device, 14-locale and payment security verification.

## Current state

The repository already contains the standalone licence-centre deployment package, public product page, contact/purchase request capture, administrator licence issuance, domain activation, localized-theme data delivery and the client-side licence panel. The completion criteria above remain the source of truth for the unfinished global sales work.

# Global TofiDesk marketplace requirements

## Product promise

The marketplace must support software, themes and add-ons for every industry in every built-in locale without assuming a Turkish company, Turkish address, Kaş, a specific currency, or a supplied brand asset.

## Language model

- Public site, installer, administration interface and outgoing email templates each have an independently selectable locale.
- Built-in locales: Turkish, English, German, French, Spanish, Italian, Portuguese, Dutch, Russian, Arabic, Chinese, Japanese, Korean and Persian.
- Arabic and Persian use RTL layouts.
- A locale may be enabled only after its complete interface dictionary has been reviewed. Content is independently drafted, reviewed and published per locale.

## Country profile during installation

The installer must collect or infer, with user confirmation: country, default locale, time zone, currency, date/time format, address label, phone country code and unit preferences. It must seed no customer data and create the first administrator in the selected installer language.

## Theme system

- Admins can manage logo, favicon, colour tokens, typography, hero media, social profiles, custom CSS and page-section visibility/order.
- Theme overrides are stored separately from application code and survive application upgrades.
- A child-theme folder supports file-level template overrides. Arbitrary PHP execution from the browser is deliberately excluded; code changes are made through the child theme under source control.

## Marketplace distribution

- Generic release archives contain no Kaş brand, customer details, database, credentials, supplied logo or supplied hero image.
- A separate Kaş profile contains the approved business configuration and content.
- The release includes installation guide, changelog, support policy, licence inventory, server requirements and automated test instructions.

## Integrations

- SMTP sending and request-reply queue are required.
- Inbound IMAP mailbox synchronization, message threading and attachment controls are a separate required module before claiming inbox management.
- Social profile links, sharing metadata, Open Graph, X cards, Schema.org, sitemap, canonical and hreflang must be configurable and testable.

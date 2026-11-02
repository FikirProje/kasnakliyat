# TofiDesk licence centre

Deploy `license-center/` separately at `https://tofidesk.com/transportation`. It is not included in the buyer archive. The buyer archive contains the client panel at `/admin/licenses`.

1. Put the licence centre behind HTTPS and set its database path outside the web root.
2. Create the first centre administrator through the one-time installation URL.
3. Publish a data-only theme and create a key with its permitted domain count.
4. The buyer enters the licence centre URL and key in their installed product's **Licence** page. The product sends its configured `APP_URL` host to the centre for activation/checking.

CodeCanyon/Envato purchase codes are buyer-provided credentials. They must be verified by an authenticated author-side Envato API request before a licence is issued. Do not automatically approve a code or store an Envato author token in a buyer installation.

Buyer verification and password-reset messages use `LC_MAIL_FROM` with the server's configured PHP mail transport. If it is empty or delivery fails, links are written as `0600` JSON files under the licence centre's private `storage/outbox/` directory for test-mode inspection; links are never written to application logs.

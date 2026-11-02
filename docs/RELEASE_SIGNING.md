# Release signing

Release packages are published from the license centre with a private OpenSSL key. The private key stays only on the release server; it must never be placed in an application ZIP or customer `.env`.

Generate a key pair on the release server:

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out release-private.pem
openssl rsa -in release-private.pem -pubout -out release-public.pem
```

Configure `RELEASE_SIGNING_PRIVATE_KEY` from the protected private-key secret when running `license-center/bin/release.php`. Configure the matching public key as `RELEASE_SIGNING_PUBLIC_KEY` on customer installations. The application verifies the public-key signature and the package SHA-256 before it can be applied.

For older releases, `RELEASE_SIGNING_SECRET` remains supported as a legacy HMAC fallback. New stable releases should use the private/public key pair.

# Changelog / Historique

## 1.0.4 — 2026-10-26

- Confirm self-service unlinking with a protected POST. Removing the last enabled provider requires proof of a working local Joomla password, with a profile link to set one or an option to link another provider. Disabled providers do not count; concurrent removals are serialized per user.
- Add a guided Joomla provider form: master URL, display name and credentials; generate OIDC endpoints and security settings automatically while preserving existing advanced options.
- Generate blank new-client credentials correctly and display them once to the creating administrator. Simplify the master form and explain both roles in French and English.

- Clear completed local-account confirmation return URLs so a later normal logout cannot revisit an expired relinking request. Preserve unrelated Joomla return settings.

## 1.0.3 — 2026-10-26

- Guide first-time linking of an existing email through local sign-in and automatic continuation; expose My external connections in the signed-in login module and profile.
- Resume the existing local-account confirmation after a repeated callback or click for an unlinked identity. Never replay the OAuth code or bypass local account ownership.
- Hide external sign-in buttons on the relinking login form, expire pending requests after ten minutes and clarify the client-site/password recovery instructions in French and English.

## 1.0.2 — 2026-10-26

- Respect Joomla's blocked-account flag for master sign-in, client authentication and account linking. An active account with a legacy activation value of `0` or a password-reset token can sign in normally. Blocked accounts, including registrations awaiting activation, remain refused.
- Translate identity-provider errors and return HTTP 403 when an account is unavailable.
- Correct the French wording for a previously unlinked identity.

## 1.0.1 — 2026-10-26

- Fix Joomla update discovery by declaring the package's `site` client in the update feed. Previously Joomla treated the update as an uninstalled administrator extension.
- Validate the feed target, all manifest versions, download URL and three package checksums during the build checks.
- Keep adaptive site favicons legible on white sign-in buttons when the browser uses dark mode.

## 1.0.0 — 2026-10-26

First packaged release derived from the local 0.1.0 prototype. One Joomla package for client and master roles, complete FR/EN interface and documentation, versioned update feed and reproducible build. Hybridauth 3.13.0 and Firebase PHP-JWT 7.2.1 are locked.

Adds validated OIDC signatures, PKCE, consent, one-use login tickets, safer account linking, Joomla registration enforcement, verified-email handling, encrypted-secret preservation, HTTPS server calls and Joomla 6 event/HTTP compatibility. Adds log CSV export, confirmation before purge, configurable retention, provider logos and custom favicons. Removes redundant login wording.

Première version distribuable issue du prototype local 0.1.0 : package commun maître/client, interface et documentation FR/EN, mises à jour, signatures OIDC vérifiées, PKCE, consentement, respect des inscriptions Joomla, liaison sécurisée des comptes, export et nettoyage des journaux, logos et favicons.

Real acceptance flows cover GitHub and Joomla. Google and self-hosted WordPress remain unverified with live services.

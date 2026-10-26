# Changelog / Historique

## 1.0.1 — 2026-10-26

- Fix Joomla update discovery by declaring the package's `site` client in the update feed. Previously Joomla treated the update as an uninstalled administrator extension.
- Validate the feed target, all manifest versions, download URL and three package checksums during the build checks.
- Keep adaptive site favicons legible on white sign-in buttons when the browser uses dark mode.

## 1.0.0 — 2026-10-26

First packaged release derived from the local 0.1.0 prototype. One Joomla package for client and master roles, complete FR/EN interface and documentation, versioned update feed and reproducible build. Hybridauth 3.13.0 and Firebase PHP-JWT 7.2.1 are locked.

Adds validated OIDC signatures, PKCE, consent, one-use login tickets, safer account linking, Joomla registration enforcement, verified-email handling, encrypted-secret preservation, HTTPS server calls and Joomla 6 event/HTTP compatibility. Adds log CSV export, confirmation before purge, configurable retention, provider logos and custom favicons. Removes redundant login wording.

Première version distribuable issue du prototype local 0.1.0 : package commun maître/client, interface et documentation FR/EN, mises à jour, signatures OIDC vérifiées, PKCE, consentement, respect des inscriptions Joomla, liaison sécurisée des comptes, export et nettoyage des journaux, logos et favicons.

Real acceptance flows cover GitHub and Joomla. Google and self-hosted WordPress remain unverified with live services.

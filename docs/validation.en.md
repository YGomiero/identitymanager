# Version 1.0.1 validation

After publication, Joomla's real updater successfully discovered, downloaded and installed the 1.0.1 GitHub release over 1.0.0. Settings, providers, clients and linked identities were unchanged. The other two local installations were upgraded to the same package with these data preserved. All three public release assets match the local build and checksum list. GitHub CI passed on PHP 8.3 and 8.4, and the 24 Joomla integration checks passed again after the real update.

[Français](validation.fr.md) — Checks performed on 26 October 2026.

## Functional results

- Fresh extension installation on a local Joomla 6.2.0 site, followed by reinstall/update; migration of two prototype 0.1.0 installations.
- Real GitHub authorization limited to email access: account creation, repeat login to the same member account and logout.
- Joomla-to-Joomla master login, consent, HTTPS token/profile exchange, client account creation and repeat login to the same account.
- Closing Joomla registration refuses external creation with a localized message; temporarily opening it permits creation; closing it again still allows the existing linked member to sign in.
- CSV archive downloaded through the administrator interface. Purge, CSRF rejection and ACL rejection tested inside a rolled-back transaction, retaining real logs.
- Non-repetitive buttons, GitHub logo, site favicon and missing-image fallback. Google has a bundled logo but live Google authentication was not tested.
- 42 isolated checks and 24 Joomla integration checks; PHP syntax, XML, parity of 26 INI files and 263 language keys checked. Composer audit found no advisories or abandoned dependencies; no direct dependency was outdated on the check date.

## Limits

Live Google, self-hosted WordPress, other catalog providers, all Joomla MFA modes, complex multisite deployments, high concurrency and every PHP/Joomla combination were not validated. The master is a documented OIDC subset without OpenID certification. The package targets Joomla 6 and PHP 8.3+; older Joomla versions are not declared compatible.

Sources: [Hybridauth](https://github.com/hybridauth/hybridauth/releases/tag/v3.13.0), [Google redirect rules](https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation).

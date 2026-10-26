# Version 1.0.4 validation

42 isolated checks and 100 Joomla integration checks passed. New scenarios cover last-provider removal, disabled alternatives, local password proof, attempt limiting, POST/CSRF, generated credentials and one-time display, and guided Joomla provider saving while preserving the secret and private CA. Test database mutations are rolled back. Unlink, profile and Joomla configuration screens were checked in the browser on the local site.

The live Joomla-to-Joomla flow and logout back to the home page were then replayed successfully. Joomla’s retained relink return is cleared without changing unrelated login destinations. The final package is installed on all three local sites with accounts and settings preserved.

## Version 1.0.3 validation

42 isolated checks and 65 Joomla integration checks passed. A real GitHub return followed by a repeated callback during relinking was verified in the browser: the client login form is displayed without a session error or looping external sign-in buttons. Tests cover confirmation by the original account, rejection of another account, expiration and rejection without a valid flow. The update preserves existing accounts and settings. The deliberately unlinked GitHub identity remains pending local confirmation.

The Joomla-to-Joomla flow was repeated with the existing test account. My external connections is visible on the signed-in homepage and opens provider management. First-time linking of an existing local account is covered by integration tests.

## Historical validation: 1.0.1

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

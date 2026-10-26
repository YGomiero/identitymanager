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

## JED Checker

The package was scanned with the local development JED Checker 3.0.0, then the official **2.4.4** rules. The latter ran under Joomla 6 with legacy filesystem compatibility aliases in the test launcher only; rule code was not altered.

| Check | Result |
|---|---|
| JED Checker 2.4.4 | 0 errors; 184 warnings, 219 notices |
| Local JED Checker 3.0.0 | 6 PH3 license-detection errors; 189 warnings, 219 notices |
| Manifests, declared files and update server | No errors |
| Development checker's PHP/Security rules | No errors |

The six development PH3 errors result from that checker looking for `<licence>` while the six Joomla manifests correctly declare `<license>`. The 2.4.4 rules recognize these licenses. The extension retains Joomla manifest syntax.

The 118 GPL warnings concern compatible MIT/BSD dependencies whose licenses remain bundled. Framework warnings concern third-party libraries. The 36 encoding warnings include legitimate OAuth/JWT/PKCE and encryption operations. The 16 JAMSS findings include `assert…` method names, token encoding and the CSV UTF-8 byte-order mark; occurrences were reviewed and contain no concealed executable code. The scanner's 216 language notices do not replace independent INI key, parity and placeholder checks.

See [per-rule summaries](jed-checker-summary.json). **Zero stable-checker errors do not constitute JED approval or an independent security audit.**

## Limits

Live Google, self-hosted WordPress, other catalog providers, all Joomla MFA modes, complex multisite deployments, high concurrency and every PHP/Joomla combination were not validated. The master is a documented OIDC subset without OpenID certification. The package targets Joomla 6 and PHP 8.3+; older Joomla versions are not declared compatible.

Sources: [Hybridauth](https://github.com/hybridauth/hybridauth/releases/tag/v3.13.0), [JED Checker 2.4.4](https://github.com/joomla-extensions/jedchecker/tree/2.4.4), [Google redirect rules](https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation).

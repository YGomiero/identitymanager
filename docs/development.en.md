# Development

[Français](development.fr.md)

## Build and checks

Use PHP 8.3+, Composer 2 and Python 3:

```sh
composer install --working-dir=component/site --no-dev --prefer-dist
composer audit --working-dir=component/site --no-dev
php tests/run.php
python tools/build.py
python tools/validate.py
```

The builder reads the package manifest version, creates five nested extension ZIPs, includes runtime dependencies and writes the installable package, update.xml and SHA256SUMS to `dist/`. Archive ordering and timestamps are fixed. Byte-for-byte reproduction requires the same dependency files, Composer-generated loader and compression implementation. Never include site configuration, credentials or local test fixtures.

`tests/run.php` runs isolated protocol/policy/CSV/icon regression tests using small Joomla API doubles. It includes real HS256 and RS256 JWT cryptography. This does not replace browser tests.

On a backed-up staging Joomla installation **with this package installed**, run `php tests/joomla.php /path/to/site administratorUsername`. The integration suite creates synthetic records inside one database transaction and rolls it back. It exercises actual account linking, revocation, PKCE replay rejection, log retention and purge permissions. It needs InnoDB and must not run on a live site. The existing administrator account is loaded for ACL checks; its password is not required or changed.

## Source map

| Location | Responsibility |
|---|---|
| `component/site/src/Controller/AuthController.php` | Browser flow state, provider callback and one-use Joomla login ticket |
| `component/site/src/Provider/` | OAuth/OIDC transport and signature checks, Hybridauth adapter and Joomla session storage |
| `component/site/src/Service/AuthService.php` | Account creation/linking policy at callback |
| `RegistrationPolicy.php`, `AccountLinkService.php` | Joomla registration gate, user/group protections and persistent links |
| `IdpService.php`, `Controller/IdpController.php` | Master authorization, consent, PKCE codes, token and UserInfo endpoints |
| `TokenEncryptionService.php`, `LogService.php` | Encrypted secrets and bounded log retention |
| `component/admin/src/Controller/LogsController.php` | Protected streaming CSV and purge |
| `plugins/` | Joomla event integration and provider catalogs |
| `package/`, `component/script.php` | Installation, defaults and additive schema migration |

UI strings live in en-GB and fr-FR INI files; retain matching keys and format placeholders. `tools/validate.py` checks syntax, XML, language coverage/parity and ZIP hygiene. Comments explain security boundaries rather than repeat every statement.

## Release procedure

1. Update all six manifests, changelog and `docs/releases/VERSION.md`; add upgrade handling when schema changes.
2. Run local checks, Composer audit, real browser flows and JED Checker. Record warnings and untested providers honestly.
3. Commit, push and wait for CI. Tag the validated commit with its numeric version.
4. Run the manual release workflow with the existing tag, or publish the validated local artifacts through GitHub Releases. Existing release assets must not be overwritten.
5. Verify public download hashes and update feed, then exercise Joomla update discovery and installation with backed-up staging settings.

GitHub CI checks PHP 8.3 and 8.4. That matrix covers isolated source checks, not every Joomla/PHP/provider deployment combination. Do not commit the vendor directory; retain composer.lock.

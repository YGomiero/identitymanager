# Installation and configuration

[Français](installation.fr.md)

## Install and update

Back up files and database, then upload `pkg_identitymanager-1.0.2.zip` through System → Install → Extensions. The first installation enables four plugins; upgrades preserve their enabled state. Providers and the identity-provider role are disabled by default. Enable only configured services.

The **same package** includes the site/administrator component, system buttons plugin, Joomla authentication plugin, Google catalog and Hybridauth catalog. Each site selects its role in configuration. Client installation does not automatically enable the master role.

Updates retain settings, providers, encrypted secrets and account links. Uninstalling deletes the extension's tables and data; export logs and take a backup first. Joomla user accounts remain present.

The package update feed is `https://github.com/YGomiero/identitymanager-joomla/releases/latest/download/update.xml`. Use Joomla's normal extension updater when a new release is available. Published release archives should never be replaced in place.

## Registration and linking

New accounts require both Joomla Users → Options → Allow User Registration and Identity Manager → Automatic account creation. Disabling either prevents account creation, including a callback started earlier. Existing linked members can still sign in. When registration is closed, buttons say “Sign in with” and no account-creation hint appears.

Verified email is required by default. Matching an existing email does not silently merge accounts: sign in locally, then add the provider from My external connections. Optional automatic email linking requires verified ownership and refuses administrator accounts. Automatic creation cannot grant a group with administrator access.

When Joomla requires administrator activation, register through its normal form first. When Joomla requires email self-activation, verified external email satisfies the email check; unverified external email cannot create an account in this mode. Blocked accounts and accounts awaiting activation cannot sign in.

## GitHub

Create a GitHub **OAuth App** in Developer settings. Import/open the GitHub provider (Hybridauth / `GitHub`), copy its displayed callback into GitHub's Authorization callback URL, and enter the app ID and secret. For alias `github`, the callback is `https://client.example/index.php?option=com_identitymanager&task=auth.callback&provider=github`. Use scope `user:email`; repository access is unnecessary. Enable and test as a guest. The integration queries the verified primary GitHub email even if the profile exposes a public email.

A blank secret field on edit preserves the current secret. Credentials belong to the individual installation and are never shipped in the package.

## Google

Create a Web application OAuth client in Google Cloud, configure consent and testing users, then configure the Google provider (Hybridauth / `Google`) with scope `openid email profile`. Copy the provider's exact displayed callback into Google's authorized redirect URIs.

Use an HTTPS domain accepted by Google. Local `.test` names are unsuitable for public web-client redirect rules; Google documents development exceptions for localhost. The extension does not disable HTTPS checks to bypass these rules. A localhost or public test-domain deployment requires separate configuration. Live Google acceptance testing is not claimed for this release.

[Official Google redirect rules](https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation)

## Connect two Joomla sites

On the **master**, explicitly enable the identity provider. Create a client with a unique ID, random secret of at least 32 characters, and exact callback from the receiving site. Use `openid profile email`. Leave “Trusted client” off to show member consent.

Leave the master's “Verified email addresses” setting off unless administrators guarantee genuine email verification for its members. Clients require verified email by default; asserting this without such a policy would undermine account linking.

On the **client**, configure an OIDC provider:

| Field | Example |
|---|---|
| Name / alias | Member portal / `portal` |
| Client ID and secret | The values registered on the master |
| Authorization URL | `https://master.example/?option=com_identitymanager&task=idp.authorize` |
| Token URL | `https://master.example/?option=com_identitymanager&task=idp.token` |
| UserInfo URL | `https://master.example/?option=com_identitymanager&task=idp.userinfo` |
| Issuer | `https://master.example` (no trailing slash) |
| Scope | `openid profile email` |
| JSON options | `{"id_token_alg":"HS256"}` |

The bundled master signs ID tokens using the client's shared secret (HS256), without JWKS. For an external RSA-based OIDC provider, use RS256 (default) and its HTTPS JWKS URL. Master access tokens use a separate server-only key, so clients cannot mint them.

Discovery is at `https://master.example/?option=com_identitymanager&task=idp.discovery`. Root URLs avoid SEF redirects of `index.php`; requests containing credentials or bearer tokens do not follow HTTP redirects. For private development certificates, add `"ca_file":"/path/local-ca.pem"` to client options. PHP must be able to read the proper trusted CA bundle. Certificate and hostname verification remain enabled.

## Favicons and logos

Google and GitHub use bundled logos. A linked Joomla or WordPress site can use its own HTTPS favicon URL in the provider form, or a local `/media/...` path. Remote images load directly from the configured site with no referrer and no third-party favicon service. To avoid a request to that site before sign-in, place an authorized copy in the client's media directory. Missing images fall back to a generic site icon.

## Logs and account management

Logs displays the latest 100 events. Export CSV includes all rows up to the start of the export, streamed in batches of 500. The archive uses UTF-8, semicolon separators, and spreadsheet-formula protection. Clear logs asks for confirmation and deletes the rows; exporting does not delete them. Both operations require component `core.admin` permission and a valid POST CSRF token.

Retention defaults to 90 days. Set 0 to disable expiry, or choose up to 3650 days. Cleanup is opportunistic, deleting at most 100 expired rows per new event; there is no background job on an idle site. Archive before clearing if needed. Logs contain timestamps, event/result, internal IDs, provider, IP and browser, never passwords or OAuth tokens. Protect downloaded archives and describe processing in the site's privacy information.

Members manage providers at `index.php?option=com_identitymanager&view=connections`; add a menu item to this view. Unlinking removes stored tokens and disables that external connection, but does not delete the Joomla account or revoke consent at the external provider. Keep a local login method before removing the last identity.

## Upgrade from prototype 0.1.0

The migration adds PKCE without replacing stored data. Old authorization codes become unusable; start a fresh login after updating. Explicitly enable the master role, change endpoints to HTTPS, verify the issuer and set `HS256` for an Identity Manager master. Use a random secret of at least 32 characters. Update master and clients together. Align Joomla and Identity Manager registration settings. Encrypted data requires the original Joomla `secret` when restoring a backup.

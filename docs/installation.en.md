# Installation and configuration

[Français](installation.fr.md)

## Install and update

Back up files and database, then upload `pkg_identitymanager-1.0.5.zip` through System → Install → Extensions. The first installation enables four plugins; upgrades preserve their enabled state. Providers and the identity-provider role are disabled by default. Enable only configured services.

The **same package** includes the site/administrator component, system buttons plugin, Joomla authentication plugin, Google catalog and Hybridauth catalog. Each site selects its role in configuration. Client installation does not automatically enable the master role.

Updates retain settings, providers, encrypted secrets and account links. Uninstalling deletes the extension's tables and data; export logs and take a backup first. Joomla user accounts remain present.

The package update feed is `https://github.com/YGomiero/identitymanager/releases/latest/download/update.xml`. Use Joomla's normal extension updater when a new release is available. Published release archives should never be replaced in place.

The GitHub repository, release downloads and Joomla update feed are public. No GitHub account is required to download the installable package or receive updates.

## Registration and linking

New accounts require both Joomla Users → Options → Allow User Registration and Identity Manager → Automatic account creation. Disabling either prevents account creation, including a callback started earlier. Existing linked members can still sign in. When registration is closed, buttons say “Sign in with” and no account-creation hint appears.

Verified email is required by default. Matching an existing email does not silently merge accounts: sign in locally, then add the provider from My external connections. Optional automatic email linking requires verified ownership and refuses administrator accounts. Automatic creation cannot grant a group with administrator access.

When Joomla requires administrator activation, register through its normal form first. When Joomla requires email self-activation, verified external email satisfies the email check; unverified external email cannot create an account in this mode. Blocked accounts and accounts awaiting activation cannot sign in.

When the email matches an existing local account, the client login form opens directly. Once that account is confirmed, linking completes without additional settings. After sign-in, My external connections appears in the login module and Joomla profile; no menu item is required. Opening the connections page as a guest also offers a login link.

## Choose the page after sign-in

Under **Content → Site Modules**, open the **Login** module and its **External sign-in** tab. **After external sign-in** offers:

- **Home page**: the default destination after sign-in or permitted account creation.
- **Menu item**: select an existing menu item accessible to the signed-in member.
- **Specific URL**: enter a path such as `/my-account` or a full URL on the same site. Query parameters and fragments are preserved.

Each module has its own setting. An unavailable destination or an off-site URL falls back to the home page. The choice is retained when the flow requires confirmation of an existing local Joomla account. Linking another provider while already signed in returns to My external connections.

On the master site, consent describes only the information requested: account identifier, profile (name and username), email address and verification status. The password is never shared with the client site.

## GitHub

Create a GitHub **OAuth App** in Developer settings. Import/open the GitHub provider (Hybridauth / `GitHub`), copy its displayed callback into GitHub's Authorization callback URL, and enter the app ID and secret. For alias `github`, the callback is `https://client.example/index.php?option=com_identitymanager&task=auth.callback&provider=github`. Use scope `user:email`; repository access is unnecessary. Enable and test as a guest. The integration queries the verified primary GitHub email even if the profile exposes a public email.

A blank secret field on edit preserves the current secret. Credentials belong to the individual installation and are never shipped in the package.

## Google

Create a Web application OAuth client in Google Cloud, configure consent and testing users, then configure the Google provider (Hybridauth / `Google`) with scope `openid email profile`. Copy the provider's exact displayed callback into Google's authorized redirect URIs.

Use an HTTPS domain accepted by Google. Local `.test` names are unsuitable for public web-client redirect rules; Google documents development exceptions for localhost. The extension does not disable HTTPS checks to bypass these rules. A localhost or public test-domain deployment requires separate configuration. Live Google acceptance testing is not claimed for this release.

[Official Google redirect rules](https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation)

## Connect two Joomla sites

The **master** acts as an identity provider and authenticates the member. The **client** receives proof of that identity and opens its own session without receiving the master password. OpenID Connect adds identity verification to OAuth 2.0. The same package can perform both roles.

1. On the **client**, open Providers → Add → **Sign in with accounts from another Joomla site**. Copy the displayed callback address and keep this page open.
2. On the **master**, enable the master role in Identity Manager options. Under Client applications → New application, name the client site and paste its exact callback address. Keep consent enabled, then save. Empty credentials are generated automatically.
3. The master displays its address, client ID and secret. **Copy the secret before leaving: it is displayed once**, within five minutes of creation. Never put it in a URL, public screenshot or GitHub issue.
4. Return to the **client**. Enter the master HTTPS address, button name and both credentials. The favicon is optional. Enable and save the provider.

Authorization, token and profile endpoints, scope and HS256 settings are prepared automatically. Standard setup needs no JSON editing. Advanced settings remain available for custom configurations, and existing options such as a private certificate authority are preserved.

By default the client requires a verified email. Enable the master’s verified-email assertion only when its administrator guarantees that members have actually verified their addresses. Do not change this guarantee merely to suppress a sign-in error. An email already present on the client requires confirmation of the local account while automatic linking remains disabled, as it is by default.

The built-in flow uses authorization codes with PKCE S256. Identity tokens use the client-specific secret (HS256); access tokens use a separate server key. This is a documented OIDC subset, without OpenID certification or universal interoperability claims. For another OIDC server, use the generic form with its supplied settings; RS256 and JWKS are available there.

Discovery is available at `https://master.example/?option=com_identitymanager&task=idp.discovery`. Root endpoints avoid `index.php` redirects on SEF sites. Credential-bearing exchanges do not follow HTTP redirects. For a private certificate authority, add `"ca_file":"/path/local-ca.pem"` in the client’s advanced options; PHP must be able to read the file. TLS verification remains enabled.

## Favicons and logos

Google and GitHub use bundled logos. A linked Joomla or WordPress site can use its own HTTPS favicon URL in the provider form, or a local `/media/...` path. Remote images load directly from the configured site with no referrer and no third-party favicon service. To avoid a request to that site before sign-in, place an authorized copy in the client's media directory. Missing images fall back to a generic site icon.

## Logs and account management

Logs displays the latest 100 events. Export CSV includes all rows up to the start of the export, streamed in batches of 500. The archive uses UTF-8, semicolon separators, and spreadsheet-formula protection. Clear logs asks for confirmation and deletes the rows; exporting does not delete them. Both operations require component `core.admin` permission and a valid POST CSRF token.

Retention defaults to 90 days. Set 0 to disable expiry, or choose up to 3650 days. Cleanup is opportunistic, deleting at most 100 expired rows per new event; there is no background job on an idle site. Archive before clearing if needed. Logs contain timestamps, event/result, internal IDs, provider, IP and browser, never passwords or OAuth tokens. Protect downloaded archives and describe processing in the site's privacy information.

Members manage providers at `index.php?option=com_identitymanager&view=connections`; a menu item is optional. Unlinking removes stored tokens and disables that external connection, but does not delete the Joomla account or revoke consent at the external provider. Before unlinking, the confirmation screen checks other linked and enabled providers. If this is the last provider, prove knowledge of your local Joomla password. You can set it in your profile while signed in, then return to My external connections, or link another provider. An automatically generated random password hash is not enough. When Joomla password authentication is disabled, another provider is required. Password attempts are rate limited and confirmation requires a protected POST request. Administrative revocation remains available for security interventions.

After unlinking, a successful external authentication opens the **client site** login form to confirm the original local account. This form does not offer another external sign-in. Repeated callbacks or clicks resume that confirmation for ten minutes without exchanging the OAuth code again or automatically restoring the link. For an identity already unlinked before this protection, a signed-out member can use Forgot your password? to recover local access; Joomla’s recovery email must be deliverable. Existing revoked links are not automatically restored. Use Log out to leave the site and later return through the same provider.

## Upgrade from prototype 0.1.0

The migration adds PKCE without replacing stored data. Old authorization codes become unusable; start a fresh login after updating. Explicitly enable the master role, change endpoints to HTTPS, verify the issuer and set `HS256` for an Identity Manager master. Use a random secret of at least 32 characters. Update master and clients together. Align Joomla and Identity Manager registration settings. Encrypted data requires the original Joomla `secret` when restoring a backup.

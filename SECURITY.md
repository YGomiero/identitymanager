# Security / Sécurité

Report suspected vulnerabilities privately to **extensions@gomiero.me**. Include the affected version, a minimal reproduction, expected impact and a safe sample without credentials, personal information or live tokens. Do not publish an exploit against a live site. Supported release line: 1.x; the 0.1.0 prototype is not supported.

Signalez une vulnérabilité en privé à **extensions@gomiero.me**, avec la version, les étapes de reproduction et l’impact attendu. Retirez les identifiants, données personnelles et jetons. La branche 1.x est suivie ; le prototype 0.1.0 n’est plus pris en charge.

## Controls / Protections

- HTTPS and certificate verification; no automatic redirect forwarding for OAuth/OIDC server requests.
- Session-bound state, nonce, PKCE, flow expiry and one-use Joomla login ticket.
- OIDC signature, algorithm, issuer, audience, expiration, nonce and UserInfo subject validation.
- Exact master callback allowlist, single-use codes and client-specific HS256 ID tokens; separate server-only access-token key.
- Joomla registration policy enforced at callback and account-creation boundary. No automatic privileged account linking or privileged group assignment.
- POST CSRF and administrator permission on log export/purge. Secrets encrypted using AES-256-GCM with a key derived from Joomla's secret. Keep the Joomla configuration and database backups protected together.
- Logs retain error identifiers rather than raw provider exceptions; transient adapter data is cleared from the Joomla session after use.

These controls are regression-tested but do not constitute an independent security audit, OIDC certification or JED approval. Hybridauth adapters may differ in protocol and verification capabilities; validate the selected provider before production use. MFA remains Joomla's responsibility; all MFA policy combinations have not been acceptance-tested.

Ces protections ne constituent ni un audit indépendant, ni une certification OIDC, ni une validation JED. Testez le fournisseur choisi et la politique MFA Joomla avant mise en production. Le rôle maître propose un sous-ensemble OIDC (code, PKCE, HS256, profil) : pas de gestion complète des sessions fédérées, de déconnexion globale, de jetons de renouvellement serveur ou de validation de conformité OpenID.

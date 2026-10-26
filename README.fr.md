# Identity Manager pour Joomla

[English](README.md) · [Télécharger](https://github.com/YGomiero/identitymanager-joomla/releases/latest) · [Installation](docs/installation.fr.md) · [Signaler un problème](https://github.com/YGomiero/identitymanager-joomla/issues) · [Sécurité](SECURITY.md)

![Identity Manager — un package, des identités connectées](docs/identitymanager.svg)

Connectez-vous à Joomla avec une identité externe, liez un compte existant ou créez un compte lorsque les paramètres du site l’autorisent. **Le même package** sert de client, de fournisseur d’identité Joomla (maître), ou assure les deux rôles.

- Joomla 6, PHP 8.3+, MySQL/MariaDB, HTTPS, extensions PHP cURL et OpenSSL.
- Interface et documentation en français et en anglais britannique.
- GitHub et Google via Hybridauth ; fournisseurs OAuth 2.0 / OpenID Connect configurables.
- Connexion entre Joomla avec code d’autorisation, PKCE et consentement facultatif.
- Favicons des sites liés, logos Google/GitHub intégrés et boutons accessibles.
- Liaison et révocation des identités, journaux, archive CSV, vidage et durée de conservation.
- Mise à jour Joomla avec empreintes SHA-256, SHA-384 et SHA-512.

Installez **`pkg_identitymanager-1.0.3.zip`**, pas l’archive du code source proposée par GitHub. Le package contient le composant, quatre plugins, les traductions et les dépendances. Composer n’est pas nécessaire sur le site destinataire.

## Les inscriptions respectent Joomla

La création exige **deux autorisations** : Utilisateurs → Paramètres → Autoriser l’inscription des utilisateurs, et Identity Manager → Création automatique de comptes. Désactiver l’une suffit à bloquer la création, même si une connexion avait commencé auparavant. Les comptes déjà liés continuent à se connecter. La validation par un administrateur impose une inscription préalable avec Joomla ; l’auto-activation par e-mail exige une adresse vérifiée par le fournisseur externe.

## Périmètre vérifié

La première version a été testée sur Joomla 6.2.0 / PHP 8.3.30 avec une véritable connexion GitHub et des connexions entre Joomla. Google est intégré, mais n’a pas été vérifié avec une application OAuth Google réelle. Une adresse locale en `.test` ne convient pas à l’URL de retour d’un client web Google. WordPress.com figure dans le catalogue Hybridauth ; un WordPress auto-hébergé exige une extension serveur OAuth/OIDC et n’a pas été testé.

Le catalogue ne garantit pas le fonctionnement de tous les fournisseurs. Consultez le [rapport de validation](docs/validation.fr.md), la [configuration](docs/installation.fr.md) et les [licences tierces](THIRD_PARTY_NOTICES.md).

Code sous GPL-2.0-or-later. Les dépendances et marques conservent leurs propres conditions. Aucune affiliation avec Joomla, Google ou GitHub n’est revendiquée.

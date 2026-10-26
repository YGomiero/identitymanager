# Développement

[English](development.en.md)

## Construire et contrôler

Avec PHP 8.3+, Composer 2 et Python 3 :

```sh
composer install --working-dir=component/site --no-dev --prefer-dist
composer audit --working-dir=component/site --no-dev
php tests/run.php
python tools/build.py
python tools/validate.py
```

Le constructeur lit la version du manifeste du package et prépare cinq ZIP internes, les dépendances d’exécution, le package installable, update.xml et SHA256SUMS dans `dist/`. Ordre des fichiers et dates ZIP sont fixes. Une reproduction binaire exacte exige les mêmes dépendances, l’autoloader généré par Composer et la même compression. N’intégrez jamais configuration de site, secrets ou données de test locales.

`tests/run.php` contrôle les protocoles, les inscriptions, les cellules CSV et les icônes avec de petits doubles des API Joomla. Les signatures HS256 et RS256 utilisent la bibliothèque réelle. Ces tests ne remplacent pas le navigateur.

Sur un Joomla de test sauvegardé **où le package est installé**, lancez `php tests/joomla.php /chemin/site identifiantAdministrateur`. Les données synthétiques sont écrites dans une transaction annulée à la fin. La suite vérifie la liaison, la révocation, PKCE, le refus de réutilisation des codes, la conservation des journaux et les droits de vidage. Elle exige InnoDB ; ne la lancez pas en production. L’administrateur existant sert à vérifier les droits, sans lecture ni changement de son mot de passe.

## Organisation du code

| Emplacement | Rôle |
|---|---|
| `component/site/src/Controller/AuthController.php` | État de session, retour fournisseur et ticket Joomla à usage unique |
| `component/site/src/Provider/` | Transport OAuth/OIDC, signatures, Hybridauth et stockage de session |
| `component/site/src/Service/AuthService.php` | Décision de liaison ou de création après authentification |
| `RegistrationPolicy.php`, `AccountLinkService.php` | Inscriptions Joomla, protections des comptes/groupes et liens persistants |
| `IdpService.php`, `Controller/IdpController.php` | Autorisation du maître, consentement, PKCE, jetons et profil |
| `TokenEncryptionService.php`, `LogService.php` | Secrets chiffrés et conservation des journaux |
| `component/admin/src/Controller/LogsController.php` | Export CSV et vidage protégés |
| `plugins/` | Événements Joomla et catalogues de fournisseurs |
| `package/`, `component/script.php` | Installation, réglages initiaux et migration additive |

Les textes sont dans les INI en-GB et fr-FR. Conservez les mêmes clés et paramètres de formatage. `tools/validate.py` vérifie la syntaxe, les XML, les langues et le contenu du ZIP. Les commentaires expliquent notamment les limites de sécurité.

## Publier une version

1. Mettez à jour les six manifestes, l’historique et `docs/releases/VERSION.md` ; prévoyez une migration si le schéma change.
2. Exécutez les contrôles locaux, l’audit Composer, les parcours réels et JED Checker. Documentez les avertissements et les services non testés.
3. Poussez le commit, attendez la CI puis créez le tag numérique correspondant à la version validée.
4. Lancez le workflow manuel de publication avec ce tag, ou publiez les artefacts locaux validés dans GitHub Releases. Ne remplacez pas des fichiers déjà publiés.
5. Vérifiez les téléchargements, empreintes, flux de mise à jour et installation via l’outil Joomla sur un site de test sauvegardé.

La CI GitHub contrôle PHP 8.3 et 8.4 sur les tests isolés. Elle ne valide pas toutes les combinaisons Joomla/PHP/fournisseur. Le répertoire vendor n’est pas versionné ; composer.lock l’est.

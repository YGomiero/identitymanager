# Installation et configuration

[English](installation.en.md)

## Installer ou mettre à jour

Sauvegardez les fichiers et la base. Dans Système → Installation → Extensions, installez le ZIP `pkg_identitymanager-1.0.3.zip`. La première installation active les quatre plugins ; une mise à jour conserve leur état. Tous les fournisseurs et le rôle maître sont désactivés par défaut. Activez uniquement les services configurés.

Un **seul package** contient le composant d’administration/site, le plugin système (boutons), le plugin d’authentification (session Joomla), le catalogue Google et le catalogue Hybridauth. Chaque site choisit son rôle dans sa configuration. Installer le package sur un client ne le transforme pas automatiquement en maître.

Les réglages, fournisseurs, secrets chiffrés et identités liées sont conservés lors d’une mise à jour. La désinstallation supprime les tables de l’extension et leurs données ; exportez les journaux et sauvegardez avant de désinstaller. Les comptes utilisateurs Joomla eux-mêmes restent présents.

Le flux de mise à jour est : `https://github.com/YGomiero/identitymanager-joomla/releases/latest/download/update.xml`. Après publication d’une nouvelle version, utilisez le système de mise à jour normal des extensions Joomla. Ne modifiez pas les ZIP d’une version déjà publiée.

## Autoriser les inscriptions

Dans Utilisateurs → Paramètres, activez l’inscription Joomla si vous souhaitez créer des membres. Activez également la création automatique dans les paramètres d’Identity Manager. Si un des deux réglages est fermé, aucun connecteur ne crée de compte. Le formulaire affiche alors « Se connecter avec » sans annonce d’inscription.

L’adresse e-mail vérifiée est exigée par défaut. Une adresse déjà utilisée ne fusionne pas automatiquement les comptes : le membre doit se connecter localement puis ajouter le service depuis « Mes connexions externes ». La liaison automatique par e-mail est une option distincte, désactivée par défaut ; elle exige une adresse vérifiée et refuse les comptes administrateurs. Aucun groupe disposant d’un accès à l’administration ne peut être attribué par création automatique.

Si Joomla exige une validation par un administrateur, utilisez d’abord son formulaire d’inscription. Si Joomla exige l’auto-activation par e-mail, une identité externe vérifiée remplit cette vérification ; aucune création avec e-mail non vérifié n’est acceptée dans ce mode. Les comptes bloqués ou en attente d’activation ne peuvent pas se connecter.

Si l’adresse e-mail correspond à un compte local existant, le formulaire de connexion du site client s’ouvre directement. Après confirmation de ce compte, la liaison est terminée sans réglage supplémentaire. Une fois connecté, le lien « Mes connexions externes » apparaît dans le module de connexion et sur le profil Joomla ; aucun élément de menu n’est nécessaire. L’accès direct à cette page propose aussi une connexion si le visiteur est déconnecté.

## GitHub

1. Créez une **OAuth App** dans les paramètres développeur GitHub.
2. Importez ou ouvrez le fournisseur GitHub, moteur Hybridauth, adaptateur `GitHub`.
3. Copiez l’URL de retour affichée dans le formulaire du fournisseur dans « Authorization callback URL » de GitHub. Pour l’alias `github` : `https://client.example/index.php?option=com_identitymanager&task=auth.callback&provider=github`.
4. Saisissez l’identifiant et le secret de l’application. Définissez le scope `user:email`, puis activez le fournisseur. Aucun accès aux dépôts n’est requis.
5. Testez depuis une session visiteur. Une adresse principale vérifiée est recherchée via l’API GitHub, même si le profil possède une adresse publique.

Un champ secret laissé vide à l’édition conserve le secret précédent. Les clés d’application restent propres à chaque installation : le package n’en contient aucune.

## Google

Créez un client OAuth « Application Web » dans Google Cloud, configurez l’écran de consentement et, en mode test, les utilisateurs autorisés. Ouvrez le fournisseur Google (Hybridauth / `Google`), renseignez les clés et le scope `openid email profile`. Copiez **exactement** son URL de retour affichée vers les URI de redirection autorisées de Google.

Utilisez un domaine HTTPS accepté par Google. Les noms locaux tels que `demofr.test` ne sont pas des domaines publics autorisés pour ce type de client ; Google documente une exception pour localhost dans les environnements de développement. L’extension ne désactive pas les validations HTTPS pour contourner ces règles. Une configuration localhost ou un domaine de test public demande une configuration distincte. Aucun succès Google réel n’est revendiqué pour cette version.

[Règles officielles Google](https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation)

## Relier deux sites Joomla

Sur le **maître**, activez le fournisseur d’identité dans les paramètres du composant. Créez une application cliente avec un identifiant unique, un secret aléatoire d’au moins 32 caractères et l’URL de retour exacte du client. Limitez les autorisations à `openid profile email`. Laissez « Client de confiance » désactivé pour présenter le consentement au membre.

Le réglage « Adresses e-mail vérifiées » du maître doit rester désactivé sauf si son administrateur garantit que les membres ont réellement vérifié leur adresse. Le client exige cette preuve par défaut ; activer ce réglage sans politique de vérification adéquate affaiblirait la liaison des comptes.

Sur le **client**, ajoutez un fournisseur OIDC :

| Champ | Exemple |
|---|---|
| Nom / alias | Mon espace membre / `mon-espace` |
| Identifiant et secret | Ceux de l’application déclarée sur le maître |
| Autorisation | `https://maitre.example/?option=com_identitymanager&task=idp.authorize` |
| Jeton | `https://maitre.example/?option=com_identitymanager&task=idp.token` |
| Profil | `https://maitre.example/?option=com_identitymanager&task=idp.userinfo` |
| Émetteur | `https://maitre.example` (sans barre finale) |
| Scope | `openid profile email` |
| Options JSON | `{"id_token_alg":"HS256"}` |

Le maître intégré signe les jetons d’identité avec le secret propre au client (HS256). Ne configurez pas de JWKS pour ce maître. Pour un autre fournisseur OIDC avec signatures RSA, utilisez RS256 (valeur par défaut) et son adresse JWKS HTTPS. Les jetons d’accès du maître utilisent une clé serveur distincte : un client ne peut pas les fabriquer.

La découverte est disponible à `https://maitre.example/?option=com_identitymanager&task=idp.discovery`. Les adresses à la racine évitent les redirections automatiques de `index.php` sur les sites utilisant la réécriture d’URL. Les requêtes contenant des secrets ou des jetons ne suivent pas les redirections HTTP.

Pour des certificats privés de développement, ajoutez `"ca_file":"/chemin/autorite-locale.pem"` aux options du client. Le fichier doit être lisible par PHP et contenir l’autorité de confiance appropriée. La vérification du certificat et du nom d’hôte reste active.

## Icônes et favicons

Google et GitHub utilisent les logos intégrés. Pour un site Joomla ou WordPress, le champ « Favicon du site lié » accepte l’adresse HTTPS de **son** favicon, ou un chemin local `/media/...`. Une URL distante charge l’image directement depuis le site concerné, sans service tiers de recherche de favicon et sans transmettre le référent. Pour éviter toute requête distante avant connexion, copiez une image autorisée dans les médias du client et utilisez son chemin local. Une image indisponible affiche une icône générique.

## Journaux et gestion des comptes

La page Journal affiche les 100 derniers événements. « Exporter une archive CSV » exporte tous les événements jusqu’au début du téléchargement, par lots de 500. L’export UTF-8 utilise le point-virgule et protège les valeurs pouvant être interprétées comme des formules. « Vider le journal » demande confirmation puis supprime les événements. L’export ne les supprime pas. Ces deux actions exigent le droit `core.admin` du composant et un jeton Joomla valide envoyé en POST.

La conservation vaut 90 jours par défaut ; configurez 0 pour la désactiver, jusqu’à 3650 jours autrement. Le nettoyage est opportuniste : chaque nouvel événement supprime au maximum 100 anciens enregistrements. Un site sans activité ne purge pas en arrière-plan. Archivez avant le vidage si nécessaire. Les journaux contiennent date, événement, résultat, identifiants internes, fournisseur, IP et navigateur, jamais les mots de passe ni les jetons OAuth. Conservez les archives dans un emplacement protégé et mentionnez ce traitement dans votre information de confidentialité.

Les visiteurs gèrent leurs services à `index.php?option=com_identitymanager&view=connections` (créez un élément de menu de ce type). Délier une identité efface ses jetons enregistrés et bloque cette connexion ; cela ne supprime pas le compte Joomla ni l’autorisation du côté du fournisseur. Conservez un moyen de connexion local avant de retirer le dernier service.

Après une déliaison, une authentification externe réussie ouvre le formulaire de connexion du **site client** pour confirmer le compte local d’origine. Ce formulaire ne propose pas de relancer la connexion externe. Un retour répété ou un second clic reprend cette confirmation pendant dix minutes, sans réutiliser le code OAuth ni réactiver la liaison automatiquement. Si le compte a été créé par un fournisseur, utilisez « Mot de passe perdu ? » pour définir un mot de passe local. Pour simplement quitter le site et revenir avec le même fournisseur, utilisez « Déconnexion ».

## Passage du prototype 0.1.0 à 1.0.3

La migration ajoute le support PKCE sans remplacer les données. Les anciens codes d’autorisation ne sont plus utilisables : commencez une nouvelle connexion après la mise à jour. Le rôle maître doit être explicitement activé ; mettez les URL en HTTPS, vérifiez l’émetteur, configurez `HS256` pour un maître Identity Manager et un secret d’au moins 32 caractères. Mettez à jour le maître et ses clients ensemble. Réglez les inscriptions Joomla et Identity Manager selon la politique voulue. Ne restaurez pas des données chiffrées sans la clé `secret` Joomla correspondante.

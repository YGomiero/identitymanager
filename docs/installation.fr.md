# Installation et configuration

[English](installation.en.md)

## Installer ou mettre à jour

Sauvegardez les fichiers et la base. Dans Système → Installation → Extensions, installez le ZIP `pkg_identitymanager-1.0.4.zip`. La première installation active les quatre plugins ; une mise à jour conserve leur état. Tous les fournisseurs et le rôle maître sont désactivés par défaut. Activez uniquement les services configurés.

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

Le **maître** est un fournisseur d’identité : il authentifie le membre. Le **client** reçoit une preuve de cette identité et ouvre sa propre session, sans recevoir le mot de passe du maître. OpenID Connect ajoute l’identification au protocole OAuth 2.0. Le même package peut remplir les deux rôles.

1. Sur le **client**, ouvrez Fournisseurs → Ajouter → **Se connecter avec les comptes d’un autre site Joomla**. Copiez l’adresse de retour affichée et gardez cette page ouverte.
2. Sur le **maître**, activez le rôle maître dans les paramètres d’Identity Manager. Dans Applications clientes → Nouvelle application, donnez un nom au site client et collez son adresse de retour exacte. Conservez la demande de consentement, puis enregistrez. Les identifiants laissés vides sont générés automatiquement.
3. Le maître affiche son adresse, l’identifiant client et le secret. **Copiez le secret avant de quitter cette page : il n’est affiché qu’une fois**, pendant une fenêtre de cinq minutes après la création. Ne le placez jamais dans une URL, une capture publique ou un ticket GitHub.
4. Revenez au **client**. Saisissez l’adresse HTTPS du maître, le nom du bouton et les deux identifiants. Le favicon est facultatif. Activez puis enregistrez le fournisseur.

Les adresses d’autorisation, de jeton et de profil ainsi que le scope et la signature HS256 sont préparés automatiquement. Aucun JSON n’est nécessaire pour une liaison standard. Les paramètres avancés restent accessibles pour modifier une configuration particulière ; les réglages existants, dont une autorité de certification privée, sont conservés.

Le client exige une adresse e-mail vérifiée par défaut. Le maître ne doit activer l’attestation d’e-mail vérifié que si l’administrateur garantit que ses membres ont réellement vérifié leur adresse. Ne modifiez pas cette garantie uniquement pour faire disparaître une erreur de connexion. Une adresse déjà présente sur le client demande la confirmation du compte local lorsque la liaison automatique reste désactivée, comme par défaut.

Le parcours intégré utilise le code d’autorisation avec PKCE S256. Les jetons d’identité utilisent le secret propre au client (HS256) ; les jetons d’accès utilisent une clé serveur distincte. Il s’agit d’un sous-ensemble OIDC, sans certification OpenID ni promesse de compatibilité universelle. Pour un serveur OIDC différent, utilisez le formulaire générique avec les paramètres qu’il fournit ; RS256 et JWKS y sont disponibles.

La découverte est disponible à `https://maitre.example/?option=com_identitymanager&task=idp.discovery`. Les adresses à la racine évitent les redirections de `index.php` sur les sites avec réécriture. Les échanges contenant des secrets ne suivent pas de redirection HTTP. Pour une autorité de certification privée, ajoutez `"ca_file":"/chemin/autorite-locale.pem"` aux options avancées du client ; PHP doit pouvoir lire le fichier. La vérification TLS reste active.

## Icônes et favicons

Google et GitHub utilisent les logos intégrés. Pour un site Joomla ou WordPress, le champ « Favicon du site lié » accepte l’adresse HTTPS de **son** favicon, ou un chemin local `/media/...`. Une URL distante charge l’image directement depuis le site concerné, sans service tiers de recherche de favicon et sans transmettre le référent. Pour éviter toute requête distante avant connexion, copiez une image autorisée dans les médias du client et utilisez son chemin local. Une image indisponible affiche une icône générique.

## Journaux et gestion des comptes

La page Journal affiche les 100 derniers événements. « Exporter une archive CSV » exporte tous les événements jusqu’au début du téléchargement, par lots de 500. L’export UTF-8 utilise le point-virgule et protège les valeurs pouvant être interprétées comme des formules. « Vider le journal » demande confirmation puis supprime les événements. L’export ne les supprime pas. Ces deux actions exigent le droit `core.admin` du composant et un jeton Joomla valide envoyé en POST.

La conservation vaut 90 jours par défaut ; configurez 0 pour la désactiver, jusqu’à 3650 jours autrement. Le nettoyage est opportuniste : chaque nouvel événement supprime au maximum 100 anciens enregistrements. Un site sans activité ne purge pas en arrière-plan. Archivez avant le vidage si nécessaire. Les journaux contiennent date, événement, résultat, identifiants internes, fournisseur, IP et navigateur, jamais les mots de passe ni les jetons OAuth. Conservez les archives dans un emplacement protégé et mentionnez ce traitement dans votre information de confidentialité.

Les visiteurs gèrent leurs services à `index.php?option=com_identitymanager&view=connections` (un élément de menu est facultatif). Délier une identité efface ses jetons enregistrés et bloque cette connexion ; cela ne supprime pas le compte Joomla ni l’autorisation du côté du fournisseur. Avant une déliaison, une page de confirmation vérifie les autres fournisseurs liés et activés. S’il ne reste que ce fournisseur, vous devez prouver que vous connaissez votre mot de passe Joomla local. Vous pouvez le définir dans votre profil pendant que vous êtes connecté, puis revenir à « Mes connexions externes », ou lier un autre fournisseur. Un mot de passe aléatoire créé automatiquement ne suffit pas. Si la connexion Joomla par mot de passe est désactivée, un autre fournisseur est nécessaire. Les essais de mot de passe sont limités ; la confirmation utilise une requête POST protégée. La révocation administrative reste possible pour une intervention de sécurité.

Après une déliaison, une authentification externe réussie ouvre le formulaire de connexion du **site client** pour confirmer le compte local d’origine. Ce formulaire ne propose pas de relancer la connexion externe. Un retour répété ou un second clic reprend cette confirmation pendant dix minutes, sans réutiliser le code OAuth ni réactiver la liaison automatiquement. Si une identité a déjà été déliée avant cette protection et que vous êtes déconnecté, utilisez « Mot de passe perdu ? » pour récupérer un accès local ; cela nécessite la réception du courriel de Joomla. Aucun ancien lien n’est réactivé automatiquement. Pour simplement quitter le site et revenir avec le même fournisseur, utilisez « Déconnexion ».

## Passage du prototype 0.1.0 à 1.0.4

La migration ajoute le support PKCE sans remplacer les données. Les anciens codes d’autorisation ne sont plus utilisables : commencez une nouvelle connexion après la mise à jour. Le rôle maître doit être explicitement activé ; mettez les URL en HTTPS, vérifiez l’émetteur, configurez `HS256` pour un maître Identity Manager et un secret d’au moins 32 caractères. Mettez à jour le maître et ses clients ensemble. Réglez les inscriptions Joomla et Identity Manager selon la politique voulue. Ne restaurez pas des données chiffrées sans la clé `secret` Joomla correspondante.

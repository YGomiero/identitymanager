# Validation de la version 1.0.4

42 contrôles isolés et 100 contrôles d’intégration Joomla réussis. Les nouveaux essais couvrent la déliaison du dernier fournisseur, les alternatives désactivées, la preuve du mot de passe local, la limitation des tentatives, POST/CSRF, les identifiants générés et leur affichage unique, ainsi que l’enregistrement du fournisseur Joomla simplifié avec conservation du secret et de l’autorité privée. Les modifications de test sont annulées en base. Les écrans de déliaison, de profil et de configuration Joomla ont été vérifiés dans le navigateur sur le site local.

La connexion réelle Joomla → Joomla et la déconnexion vers l’accueil ont ensuite été rejouées avec succès. Le retour de réassociation conservé par Joomla est nettoyé sans modifier les autres destinations de connexion. Le package final est installé sur les trois sites locaux avec conservation des comptes et réglages.

## Validation de la version 1.0.3

42 contrôles isolés et 65 contrôles d’intégration Joomla réussis. Retour GitHub réel, puis retour répété pendant la réassociation vérifiés dans le navigateur : le formulaire du site client est affiché sans erreur de session et sans bouton externe créant une boucle. Les tests couvrent la confirmation par le compte d’origine, le refus d’un autre compte, l’expiration et le rejet d’un retour sans session valide. Les comptes et réglages existants sont conservés par la mise à jour. La liaison GitHub volontairement déliée reste en attente de confirmation locale.

Le parcours Joomla → Joomla a été rejoué avec le compte de test existant. Le lien « Mes connexions externes » est visible sur la page d’accueil après connexion et ouvre la gestion des fournisseurs. La première liaison d’un compte local existant est couverte par les tests d’intégration.

## Historique : validation 1.0.1

[English](validation.en.md) — Contrôles du 26 octobre 2026.

## Résultats fonctionnels

- Installation neuve de l’extension sur un Joomla 6.2.0 local, puis réinstallation/mise à jour ; migration de deux installations du prototype 0.1.0.
- Mise à jour réelle de 1.0.0 vers 1.0.1 depuis le flux GitHub avec l’outil Joomla : détection du bon package, téléchargement et installation réussis ; réglages, fournisseurs, clients et identités liées inchangés. Le package 1.0.1 est également installé sur les deux autres sites locaux avec conservation de ces données.
- Véritable autorisation GitHub limitée à l’adresse e-mail : création du compte et reconnexion au même compte, puis déconnexion.
- Connexion entre deux Joomla : connexion au maître, consentement, échange HTTPS, création du compte client et reconnexion au même compte.
- Inscriptions Joomla fermées : création externe refusée avec message français ; inscriptions temporairement ouvertes : création réussie ; fermeture rétablie : connexion du compte existant réussie.
- Export CSV téléchargé depuis l’administration. Vidage et refus sans CSRF/droits testés dans une transaction annulée ; les vrais journaux sont conservés.
- Boutons sans répétition, logo GitHub, favicon du site et secours si l’image est absente. Google possède son logo intégré mais son authentification réelle n’a pas été testée.
- 42 contrôles isolés et 24 contrôles d’intégration Joomla ; syntaxe PHP, XML, parité des 26 fichiers de langue et 263 clés vérifiées. Audit Composer : aucun avis de sécurité ni dépendance abandonnée ; aucune dépendance directe obsolète à cette date.
- CI GitHub réussie sur PHP 8.3 et 8.4. Les trois fichiers publics de la release ont été téléchargés et comparés aux artefacts locaux ; les empreintes correspondent. Les 24 contrôles d’intégration ont été rejoués avec succès après la mise à jour effective.

## Limites connues

Google réel, WordPress auto-hébergé, autres fournisseurs du catalogue, tous les modes MFA Joomla, multisite complexe, haute concurrence et toutes les versions de PHP/Joomla ne sont pas validés par ces essais. Le maître est un sous-ensemble OIDC documenté, sans certification OpenID. Le ZIP vise Joomla 6 / PHP 8.3+ ; les anciens Joomla ne sont pas déclarés compatibles.

Sources : [Hybridauth](https://github.com/hybridauth/hybridauth/releases/tag/v3.13.0), [règles de redirection Google](https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation).

# Validation de la version 1.0.1

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

## JED Checker

Le package a été contrôlé avec le JED Checker 3.0.0 de développement installé sur le site de test, puis avec les règles officielles du tag **2.4.4**. Ces dernières ont été exécutées sous Joomla 6 avec des alias de compatibilité pour les anciennes classes de fichiers, dans le lanceur de test uniquement. Le code des règles n’a pas été modifié.

| Contrôle | Résultat |
|---|---|
| JED Checker 2.4.4 | 0 erreur ; 184 avertissements, 219 notices |
| JED Checker 3.0.0 local | 6 erreurs PH3 de détection de licence ; 189 avertissements, 219 notices |
| Manifestes, fichiers déclarés, serveur de mise à jour | Aucune erreur |
| PHP/Security du vérificateur 3.0 | Aucune erreur |

Les six erreurs PH3 du vérificateur de développement viennent de sa recherche de la balise `<licence>` alors que les six manifestes déclarent la balise Joomla `<license>`. Les règles 2.4.4 reconnaissent correctement ces licences. Le package conserve la syntaxe de manifeste Joomla.

Les 118 avertissements GPL concernent les dépendances MIT/BSD compatibles GPL dont les licences sont conservées. Les avertissements de framework concernent les bibliothèques tierces. Les 36 avertissements d’encodage concernent notamment OAuth, JWT, PKCE et le chiffrement. Les 16 alertes JAMSS incluent les noms de méthodes `assert…`, les conversions nécessaires aux jetons et le marqueur UTF-8 de l’export CSV ; les occurrences ont été examinées, sans code exécutable masqué. Les 216 notices de langue du scanner ne remplacent pas la vérification indépendante des fichiers INI, des clés et de leurs paramètres de formatage.

Les rapports synthétiques par règle sont dans [jed-checker-summary.json](jed-checker-summary.json). **Une analyse sans erreur de la version stable ne constitue pas une acceptation JED ni un audit de sécurité indépendant.**

## Limites connues

Google réel, WordPress auto-hébergé, autres fournisseurs du catalogue, tous les modes MFA Joomla, multisite complexe, haute concurrence et toutes les versions de PHP/Joomla ne sont pas validés par ces essais. Le maître est un sous-ensemble OIDC documenté, sans certification OpenID. Le ZIP vise Joomla 6 / PHP 8.3+ ; les anciens Joomla ne sont pas déclarés compatibles.

Sources : [Hybridauth](https://github.com/hybridauth/hybridauth/releases/tag/v3.13.0), [JED Checker 2.4.4](https://github.com/joomla-extensions/jedchecker/tree/2.4.4), [règles de redirection Google](https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation).

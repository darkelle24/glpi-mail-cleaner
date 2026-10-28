# Dépannage et diagnostic

[English](TROUBLESHOOTING.md) · [Retour au README](../README.fr.md)

## Plugin absent ou incompatible

Vérifiez `plugins/mailcleaner/setup.php`, l’orthographe, l’imbrication des dossiers et les droits de lecture. GLPI doit être en 12.0.x, PHP en 8.3 au minimum, et les dépendances DOM/CssSelector habituelles de GLPI disponibles. Utilisez la vraie racine de GLPI, pas son dossier `public`. Vérifiez qu’aucune copie ancienne n’existe aussi dans le marketplace.

Si la configuration est inaccessible, vérifiez l’activation du plugin et le droit de mise à jour de la configuration générale. L’URL est `<URL_GLPI>/plugins/mailcleaner/front/config.php` ; conservez l’éventuel préfixe de votre installation. Tous les accès doivent passer par le contrôleur public habituel de GLPI.

## Les signatures restent présentes

Vérifiez les paramètres enregistrés, la simulation et le fait que le mail crée bien un nouveau **suivi** collecté. Les tickets initiaux ne reçoivent pas de suppression de signature. Testez le vrai `before_html` capturé au hook.

- `no_match` : le HTML diffère de la règle. Outlook de bureau et le webmail peuvent produire des styles ou conteneurs différents.
- `ambiguous_cut` : une règle trouve aussi des repères dans l’ancien historique ; précisez-la plutôt que de choisir arbitrairement la première occurrence.
- `protected` : une citation, un marqueur, une pièce jointe ou une protection configurée empêche la suppression. Un logo incorporé converti peut protéger toute sa signature.
- `ratio_guard` : la signature occupe trop de texte ou de HTML. Une règle testée peut demander 100 % pour une réponse très courte ; les autres protections restent actives.
- Un en-tête Outlook De / Envoyé / À / Objet peut déjà figurer après la signature. Une coupure volontaire avec suppression explicite de l’historique peut le retirer s’il est après le repère et non protégé autrement.

## Les couleurs ne changent pas

Vérifiez l’option de couleurs, la désactivation de la simulation et la collecte d’un **nouveau mail**. Les anciens contenus ne sont pas réécrits. Les options de signature et de couleurs sont indépendantes. Examinez `colors_normalized`, `signature_status` et les versions HTML capturées. Les pixels des images et logos ne sont pas recolorés.

Si le HTML enregistré n’impose plus de couleur mais reste illisible, examinez séparément les styles du thème sur la page GLPI : un autre plugin ou thème personnalisé peut agir sur le conteneur.

## Aucune capture

1. Confirmez la version 2.0.0 sur la page et l’enregistrement de **Diagnostic temporaire** comme activé.
2. Relevez le **vrai chemin affiché sur la page**, issu de `GLPI_LOG_DIR`, sans supposer un chemin propre à une autre installation.
3. Cliquez sur **Tester l’écriture d’une capture fictive**. Le test utilise le même moteur et quota sans enregistrer de paramètres ni recevoir de mail.
4. En cas d’échec, vérifiez les droits d’écriture et de traversée du compte PHP, l’espace libre et l’absence de lien symbolique pour le dossier/verrou. Un test web réussi ne prouve pas qu’un worker de collecte distinct utilise le même compte ou les mêmes fichiers.
5. Si le quota de 20 fichiers est atteint, archivez ou retirez les anciennes captures. Désactiver la capture n’efface pas les fichiers.
6. Si le test fictif fonctionne mais pas les captures mail, confirmez l’activation du plugin dans le processus de collecte et la création effective d’un nouveau ticket ou suivi. Les saisies manuelles et tests d’aperçu ne produisent pas de capture mail.

Une ancienne version du formulaire indique du code non remplacé, un mauvais répertoire ou un cache PHP à recharger. Les erreurs d’écriture sont envoyées au journal PHP et forcées dans le journal technique si possible ; le traitement continue.

## Lire une capture

| Champ | Signification |
|---|---|
| `before_html` | Corps au hook du plugin, avant son intervention. |
| `proposed_html` | Résultat calculé. |
| `effective_html` | Corps laissé par ce hook pour la suite du traitement GLPI. |
| `matches`, `rules` | Sélecteurs et réglages évalués. |
| `status`, `signature_status` | Décisions globale et propre aux signatures. |
| `colors_normalized` | Indique si le résultat proposé normalise les couleurs. |
| `enabled`, `dry_run` | Activation des signatures et de la simulation. |
| `item_kind`, `ticket_id`, `collector_id` | Type et contexte. L’identifiant propre d’un ticket avant insertion n’est pas encore connu et vaut 0. |
| `body_limits` | Tailles initiales et indicateurs de troncature. |
| `diagnostic_test` | Vrai pour le test d’écriture fictif. |

En simulation, `effective_html` reste inchangé même si une proposition diffère. D’autres traitements GLPI ou plugins peuvent intervenir après le hook. La capture n’est pas le message MIME IMAP/POP original ; exportez un `.eml` depuis la boîte si cette comparaison est nécessaire.

Traitez les captures comme confidentielles. Ne publiez pas de corps de mails réels ou de coordonnées personnelles dans des tickets publics. Préférez un HTML minimal anonymisé, les versions plugin/GLPI/PHP, les réglages utiles, le statut et le résultat attendu. Les journaux et captures ne sont pas supprimés automatiquement à la désinstallation.

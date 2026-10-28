# Mail Cleaner pour GLPI

**Des suivis e-mail nettoyés. Des messages lisibles dans chaque thème GLPI.**

[English](README.md) · [Configuration](docs/CONFIGURATION.fr.md) · [Dépannage](docs/TROUBLESHOOTING.fr.md) · [Développement](docs/DEVELOPMENT.fr.md)

Mail Cleaner supprime les signatures HTML indésirables des suivis de tickets reçus par mail et peut faire hériter le texte des couleurs du thème GLPI de chaque lecteur. Définissez vos propres règles CSS, prévisualisez le résultat et utilisez les captures de diagnostic limitées pour examiner ce que transmet réellement le collecteur.

**Version :** 2.0.0 · **GLPI :** 12.0.x · **PHP :** 8.3 ou ultérieur · **Licence :** GPL-3.0-or-later

## Fonctionnalités

| Fonction | Utilité |
|---|---|
| Suppression des signatures | Retire les éléments HTML correspondant à plusieurs sélecteurs CSS configurables. |
| Coupure après un repère | Conserve un élément et retire tout ce qui suit, même hors de son parent. |
| Suppression facultative de l’historique | Autorise les coupures à retirer les citations reconnues et les en-têtes de réponse Outlook. |
| Couleurs du thème GLPI | Retire les couleurs et fonds de l’expéditeur pour utiliser le thème clair ou sombre de chaque lecteur. |
| Aperçu avant / après | Affiche les correspondances, décisions et sources HTML échappées sans créer de ticket ni enregistrer les règles. |
| Simulation | Calcule le résultat en conservant le contenu original. |
| Journal technique | Enregistre les statuts et compteurs sans le corps des messages. |
| Captures de diagnostic | Conserve le HTML avant, proposé et effectif avec les règles utilisées ; propose un test d’écriture fictif. |
| Anglais et français | Messages sources en anglais et traduction française compilée, selon la langue choisie dans GLPI. |

## Quels contenus sont traités ?

| Origine du contenu | Suppression des signatures | Couleurs du thème | Capture facultative |
|---|---|---|---|
| Nouveau ticket reçu par collecteur | Non | Oui, si activée | Oui |
| Nouveau suivi e-mail d’un ticket | Oui, si activée | Oui, si activée | Oui |
| Ticket ou suivi saisi manuellement | Non | Non | Non |
| Contenu existant, tâche ou solution | Non | Non | Non |

Le plugin utilise les champs internes `_mailgate` et `_auto_import` du collecteur, plutôt que le seul type de demande. Les collecteurs IMAP et POP partagent ce chemin. Un autre importeur fournissant volontairement ces champs pourrait aussi correspondre. Aucun fichier du cœur n’est modifié. Le contenu nettoyé enregistré peut apparaître dans les notifications GLPI suivantes.

## Installation

1. Ouvrez le répertoire racine de votre installation GLPI : celui qui contient `bin/`, `src/` et `public/`.
2. Si le dossier **`plugins`** n’y existe pas, créez-le.
3. Dans `plugins`, créez un dossier nommé exactement **`mailcleaner`**.
4. Copiez les fichiers et dossiers du plugin dans `plugins/mailcleaner/`. Copiez le **contenu** du dépôt ou du dossier de l’archive, pas un dossier supplémentaire englobant.

L’arborescence finale doit être :

```text
<RACINE_GLPI>/
├── bin/
├── public/
├── src/
└── plugins/
    └── mailcleaner/
        ├── setup.php
        ├── hook.php
        ├── bootstrap.php
        ├── front/
        ├── src/
        └── locales/
```

5. Assurez-vous que le compte exécutant GLPI peut lire les fichiers et traverser les dossiers. Le code du plugin n’a pas besoin d’être modifiable.
6. Dans GLPI, ouvrez **Configuration → Plugins**, puis **installez** et **activez Mail Cleaner**.
7. Ouvrez sa configuration depuis la liste des plugins. Le droit de mise à jour de la configuration générale est requis.

Ne placez pas le plugin directement dans `public/` et évitez `plugins/mailcleaner/mailcleaner/setup.php`. L’emplacement correct est **`plugins/mailcleaner/setup.php`**. Aucune installation de dépendance n’est nécessaire : le plugin utilise DOM et Symfony CssSelector fournis par GLPI. Évitez les copies en double dans `plugins` et `marketplace`.

## Première configuration

Une nouvelle installation démarre avec le nettoyage des signatures **désactivé**, les couleurs du thème **désactivées**, la capture **désactivée** et la simulation **activée**.

1. Collez un corps HTML entrant représentatif dans **Aperçu avant / après**.
2. Ajoutez les sélecteurs des signatures ou les repères de coupure et testez le résultat.
3. Activez les fonctions souhaitées. Conservez la simulation pour un premier test de collecte.
4. Contrôlez le résultat, puis désactivez **Simulation** et enregistrez pour modifier les prochains imports.

L’aperçu utilise les paramètres affichés, même si le traitement est désactivé, sans les enregistrer. **Enregistrer la configuration** est une action distincte. Les tickets et suivis existants ne sont pas réécrits.

## Exemples de règles

Supprimer des conteneurs de signature précis :

```css
.gmail_signature
div.moz-signature
[id^="Signature"]
div.company-signature > table:not(.keep)
```

Couper après un repère Outlook reconnu ou un paragraphe de style Outlook :

```css
#appendonsend
p[style*="margin-bottom:6.0pt"][style*="line-height:12.0pt"]
```

Placez ces règles dans **Couper tout ce qui suit ces repères CSS**, pas dans la liste de suppression normale. Utilisez une ligne par règle. Les lignes commençant par `//` sont des commentaires. Ces exemples ne détectent pas toutes les signatures : vérifiez-les sur le HTML capturé depuis votre collecteur. Un identifiant dédié dans votre modèle de signature est plus fiable qu’un style de mise en forme.

Si une règle de repère correspond plusieurs fois, le nettoyage est annulé. Un repère absent ne produit aucune coupure. `#appendonsend` peut aussi exister sans signature : la coupure retire alors quand même l’historique suivant. Activez la suppression explicite de l’historique seulement si c’est souhaité.

## Des mails lisibles en thème clair et sombre

Activez **Utiliser les couleurs du thème GLPI pour le texte des mails** pour empêcher les couleurs de l’expéditeur, par exemple du noir sur un fond GLPI sombre, de contrôler l’affichage. Le plugin retire les couleurs du texte et des fonds, les attributs de couleur anciens et les feuilles de style du mail. Le thème de **chaque lecteur** fournit alors les couleurs ; aucune couleur blanche fixe n’est enregistrée et les préférences utilisateur ne sont pas modifiées.

Le texte, les liens, tableaux, images et références de pièces jointes restent présents. La mise en forme inline, comme le gras, les marges et les dimensions, est conservée lorsqu’elle est valide. Retirer des feuilles de style peut aussi retirer d’autres mises en forme. Les images elles-mêmes ne sont pas recolorées : un logo noir dans une image reste noir. Cette option est indépendante de la suppression des signatures et respecte la simulation.

## Protections et limites

- Les marqueurs GLPI `=-=-=-=` et `=_=_=_=` sont toujours protégés. Vous pouvez ajouter vos propres marqueurs.
- Les liens de pièces jointes GLPI et placeholders d’images reconnus sont protégés. Cela peut aussi conserver une signature contenant un logo incorporé converti en document.
- Les citations sont protégées sauf si la suppression explicite de l’historique est activée pour les coupures. Les autres sélecteurs de protection restent applicables.
- Un résultat vide est refusé. La limite de suppression vaut **80 %** par défaut et accepte **1 à 100 %**. À 100 %, seul le contrôle proportionnel est levé ; les autres protections restent actives.
- Limites : **1 Mio** par message, **50 règles** par liste, **512 octets** par sélecteur et **100 nœuds** candidats à la suppression.
- Les règles invalides, repères ambigus, dépassements et résultats dangereux conservent le contenu original. La normalisation des couleurs peut fonctionner indépendamment lorsqu’une suppression de signature est refusée ; l’aperçu présente les deux décisions.

Le texte brut et les exemples HTML échappés sont conservés. Lorsqu’un HTML change, sa sérialisation DOM peut normaliser les entités, guillemets ou erreurs de structure. Le plugin n’est ni un assainisseur HTML ni un détecteur sémantique du texte utile. Une règle trop large peut retirer du contenu légitime : testez vos exemples. Consultez la [configuration détaillée](docs/CONFIGURATION.fr.md).

## Diagnostic

Le journal technique facultatif est `GLPI_LOG_DIR/mailcleaner.log`. Il contient les identifiants, le mode, le statut et les compteurs, sans le corps des messages. Son écriture normale suit le réglage GLPI des journaux de fichiers ; les erreurs de capture sont aussi envoyées au journal PHP.

Pour un cas difficile, activez **Diagnostic temporaire** et envoyez un nouveau mail collecté. Les captures sont écrites dans **`GLPI_LOG_DIR/mailcleaner-debug/`**. La configuration affiche le chemin réel et propose **Tester l’écriture d’une capture fictive**.

Chaque capture contient `before_html`, `proposed_html`, `effective_html`, les correspondances et décisions. Il s’agit du contenu au hook du plugin, **après le traitement natif du collecteur GLPI**, pas du MIME original ni d’une garantie de la valeur finale en base après tous les autres hooks. Limites : **20 fichiers** et **256 Kio par version du corps**, avec indication de troncature. Lorsque le quota est atteint, les captures s’arrêtent mais le traitement continue.

**Les captures contiennent le contenu des mails.** Désactivez-les après reproduction, conservez les journaux hors de la racine web publique et retirez les fichiers après analyse. Les fichiers sont privés avec les permissions Unix habituelles ; ils ne sont pas supprimés automatiquement. Les tests fictifs utilisent aussi un emplacement de capture. Pour le mail brut, exportez le message en `.eml` depuis la boîte. Consultez le [dépannage](docs/TROUBLESHOOTING.fr.md).

## Langue

L’interface suit la langue choisie dans GLPI. Les catalogues anglais (`en_GB`) et français (`fr_FR`) sont fournis en `.po` modifiables et `.mo` prêts à l’emploi. Aucune compilation n’est nécessaire à l’installation. Les identifiants techniques, clés JSON et journaux restent en anglais.

## Mise à jour et migration

Pour une mise à jour habituelle, désactivez Mail Cleaner, remplacez le contenu de `plugins/mailcleaner/`, rechargez le code PHP en cours si nécessaire et terminez l’éventuelle action de mise à jour proposée par GLPI avant réactivation. Sauvegardez vos règles et vérifiez un aperçu après la mise à jour.

**Depuis Mail Signature Cleaner (`mailsignaturecleaner`) :**

1. Désactivez l’ancien plugin. **Ne le désinstallez pas encore** : cela supprimerait ses règles enregistrées.
2. Installez Mail Cleaner dans le nouveau dossier `plugins/mailcleaner/`.
3. Lors de sa première installation, Mail Cleaner copie les paramètres reconnus de l’ancien plugin s’il ne possède pas encore de configuration. Une configuration Mail Cleaner existante n’est jamais écrasée.
4. Vérifiez les règles et options importées, puis activez Mail Cleaner. Un seul nettoyeur doit être actif.
5. Après vérification, l’ancien plugin peut être désinstallé et son dossier retiré. Ses anciens journaux et captures ne sont ni déplacés ni supprimés ; les nouveaux utilisent le nom `mailcleaner`.

La désactivation conserve les paramètres. La désinstallation supprime **uniquement ses propres paramètres** ; elle ne restaure pas les signatures retirées et ne supprime ni tickets, pièces jointes, journaux ni captures.

## Développement et compatibilité

Le code a été vérifié contre les sources de GLPI **12.0.0** et déclare la compatibilité **12.0.x**. Revalidez le contrat interne du collecteur après une mise à jour GLPI. GLPI 10, 11 et 12.1 ou ultérieur sont hors du périmètre déclaré de cette version.

Le [guide de développement](docs/DEVELOPMENT.fr.md) présente les tests, la compilation des traductions, les sources et les limites de validation. Le [journal des versions](CHANGELOG.fr.md) détaille les changements. Un test réel GLPI/base/boîte mail reste nécessaire sur votre installation.

## Licence

[GNU GPL version 3 ou ultérieure](LICENSE). Le texte de licence original est fourni en anglais.

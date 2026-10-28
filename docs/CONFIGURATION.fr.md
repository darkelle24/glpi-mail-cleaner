# Référence de configuration

[English](CONFIGURATION.md) · [Retour au README](../README.fr.md)

## Options

| Paramètre | Défaut | Effet |
|---|---|---|
| Nettoyer les signatures des suivis collectés | Désactivé | Active les suppressions et coupures sur les suivis e-mail entrants. |
| Utiliser les couleurs du thème GLPI | Désactivé | Normalise les couleurs des tickets et suivis entrants, indépendamment des signatures. |
| Simulation | Activée | Calcule le résultat sans modifier le contenu enregistré. Concerne les deux fonctions. |
| Supprimer aussi l’historique cité | Désactivé | Autorise les coupures à viser les citations et en-têtes reconnus. N’affaiblit pas les suppressions normales. |
| Diagnostic temporaire | Désactivé | Écrit des instantanés HTML limités des tickets et suivis créés par collecteur. |
| Journal technique | Désactivé | Écrit les statuts et compteurs sans corps de message, selon le réglage des journaux GLPI. |

L’aperçu évalue toujours les règles affichées. Il n’active pas le traitement, n’enregistre pas la configuration et ne crée aucun ticket. Le test d’écriture utilise un texte fictif sans enregistrer le formulaire. Utilisez **Enregistrer la configuration** pour conserver vos choix.

## Règles CSS de suppression

Un sélecteur par ligne ; une virgule peut regrouper plusieurs sélecteurs. Les lignes vides et celles commençant par `//` sont ignorées. Les correspondances sont évaluées sur le même document ; les cibles imbriquées sont retirées une seule fois. Une règle normale supprime uniquement le sous-arbre ciblé.

```css
.gmail_signature
#company-signature
[data-signature="company"]
table.signature tr:nth-child(2)
div.signature:not(.keep) > table
```

Les sélecteurs sont traités par Symfony CssSelector fourni par GLPI. Les classes, identifiants, attributs, combinateurs enfant/descendant/frère et pseudo-classes prises en charge fonctionnent. `:has()`, les pseudo-éléments et les états de navigateur comme `:hover` ne sont pas pris en charge par Symfony 7.4 ciblé. Les règles invalides sont refusées à l’enregistrement. Les expressions XPath et le code PHP ne sont pas acceptés.

## Règles de coupure

Une liste distincte conserve un repère et retire le contenu suivant dans l’ordre du document. Le parcours visite aussi les frères suivants de chaque ancêtre jusqu’au corps du message : une mention hors du parent immédiat de la signature peut être retirée sans supprimer la réponse précédente.

```css
#appendonsend
p[style*="margin-bottom:6.0pt"][style*="line-height:12.0pt"]
```

Ces exemples couvrent certains formats Outlook. Une égalité exacte sur `style="..."` peut échouer si Outlook réécrit ou réordonne les déclarations. Les recherches par sous-chaîne tolèrent des déclarations supplémentaires, mais dépendent toujours des valeurs réelles. Un identifiant maîtrisé est préférable.

Chaque règle doit correspondre à zéro ou un repère. Plusieurs correspondances annulent le nettoyage. Un repère situé dans une citation protégée est refusé, même lorsque la suppression de l’historique est active, pour éviter une coupure depuis une ancienne signature. Un repère absent ne produit aucun effet.

La suppression de l’historique lève la protection des nœuds reconnus `blockquote`, `.gmail_quote`, `.moz-cite-prefix` et `#divRplyFwdMsg`, uniquement lorsqu’ils sont visés par une coupure. Les pièces jointes, marqueurs et autres sélecteurs de protection restent protégés. Selon le HTML, un descendant protégé peut conserver un bloc parent plus large. La coupure n’est donc pas une troncature inconditionnelle des octets.

## Contenus protégés

Les marqueurs GLPI `=-=-=-=` et `=_=_=_=` sont obligatoires et ne peuvent pas être désactivés dans le formulaire. Les marqueurs supplémentaires sont du texte littéral, un par ligne. Les cibles contenant un marqueur, y compris dans un commentaire HTML, sont conservées ; le résultat final vérifie le nombre d’occurrences.

Les pièces jointes reconnues comprennent les liens/images utilisant `document.send.php`, `[data-glpi-document]` et les placeholders d’images avec un `id` sans `src`. Leurs descendants et ancêtres sont protégés. Un logo de signature converti par GLPI peut ainsi rester présent.

Sélecteurs facultatifs protégés par défaut :

```css
blockquote
.gmail_quote
.moz-cite-prefix
#divRplyFwdMsg
```

Une règle obligatoire protège également `blockquote`, sauf en cas de suppression explicite de l’historique par coupure. La protection empêche la suppression, pas la normalisation des couleurs du contenu conservé.

## Pourcentage de suppression

Le contrôle utilise le maximum de la réduction du texte et de celle du HTML sérialisé. La limite vaut 80 % par défaut. Une réponse très courte suivie d’une grande signature peut demander un seuil supérieur. À 100 %, le contrôle proportionnel autorise cette réduction, mais les vérifications du résultat vide, des marqueurs, pièces jointes, ambiguïtés et tailles restent actives.

Ce réglage ne garantit pas que chaque caractère restant est légitime. Utilisez des règles précises et des aperçus réels.

## Couleurs du thème

La normalisation retire les couleurs du texte, fonds et images de fond, attributs anciens, ombres de texte, propriétés pertinentes de remplissage/contour du texte, variables CSS, remises à zéro, filtres et feuilles de style de l’expéditeur. Les autres styles inline valides et le contenu sont conservés. Les feuilles de style peuvent contenir une mise en page qui disparaît avec les couleurs. Les styles inline invalides sont retirés par prudence.

Le HTML hérite du thème GLPI du lecteur. GLPI conserve le contrôle de l’apparence des liens. Le compte exécutant le collecteur ne fixe pas une couleur enregistrée. Un logo coloré dans une image reste inchangé. Aucun contenu historique n’est réécrit.

## Limites et statuts

| Limite | Valeur |
|---|---|
| Taille du message | 1 Mio |
| Sélecteurs | 50 par liste |
| Longueur d’un sélecteur | 512 octets |
| Nœuds candidats à la suppression | 100 |
| Marqueurs textuels | Marqueurs GLPI obligatoires, dans la limite validée de 30 lignes / 4096 octets |
| Pourcentage de suppression | 1 à 100 %, défaut 80 % |
| Captures | 20 fichiers ; 256 Kio par version du corps HTML |

| Statut | Signification |
|---|---|
| `cleaned` | Le traitement des signatures a accepté une suppression. |
| `colors_normalized` | Couleurs retirées ; `signature_status` présente la décision distincte pour les signatures. |
| `no_match` | Aucune règle ne correspond. |
| `protected` | Le contenu ciblé ou le repère est protégé. |
| `ambiguous_cut` / `invalid_cut` | Plusieurs repères pour une règle, ou repère hors du corps. |
| `empty_guard` / `ratio_guard` | Résultat vide ou pourcentage de suppression dépassé. |
| `marker_guard` | Le nombre d’occurrences d’un marqueur serait modifié. |
| `too_many_matches` / `too_large` | Limite de nœuds ou taille dépassée. |
| `plain_text` | Texte brut conservé. |
| `unsafe_declaration` / `parse_failed` | Déclaration refusée ou HTML impossible à analyser. |
| `error` | Échec du traitement des signatures ; entrée conservée. |
| `not_requested` | Suppression des signatures non demandée pour ce passage. |
| `unchanged` | Aucun changement effectif. |

Le collecteur transforme déjà le HTML avant le plugin. Vérifiez les règles sur `before_html` capturé, pas uniquement sur le HTML inspecté dans le webmail. Un texte contenant des balises littérales peut être interprété comme HTML ; les exemples entièrement échappés sont conservés. Sans transformation acceptée, le message original reste identique octet pour octet. Après transformation, la sérialisation DOM peut normaliser sa présentation.

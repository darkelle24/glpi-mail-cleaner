# Développement, traductions et validation

[English](DEVELOPMENT.md) · [Retour au README](../README.fr.md)

## Organisation du dépôt

```text
setup.php              Métadonnées et hooks GLPI
hook.php               Installation/désinstallation et traitement mail
bootstrap.php          Chargement explicite, sans vendor propre au plugin
src/Settings.php       Valeurs par défaut et validation
src/Cleaner.php        Signatures, coupures et protections
src/Colors.php         Normalisation des couleurs
src/Capture.php        Captures JSON privées et limitées
src/I18n.php           Adaptateur gettext GLPI
front/config.php       Administration authentifiée et aperçu
locales/               PO, MO et modèle de traduction anglais/français
tests/                 Régressions autonomes et contrats d’interface
tools/compile_locales.py
```

La configuration utilise le contexte `plugin:mailcleaner` dans `glpi_configs`, sans table supplémentaire. L’installation importe uniquement les anciennes clés reconnues lorsque le nouveau contexte est vide. La désinstallation n’efface jamais l’ancien contexte, les messages, pièces jointes ou captures.

## Exécuter les tests

Définissez `GLPI_AUTOLOAD` avec le chemin absolu du `vendor/autoload.php` de GLPI, puis lancez :

```sh
php tests/run.php
php tests/migration.php
php tests/frontend.php en_GB
php tests/frontend.php fr_FR
php tests/frontend.php en_GB diagnostic
php tests/frontend.php fr_FR diagnostic
php tests/frontend.php fr_FR preview
php tests/frontend.php fr_FR invalid
```

Le lanceur de régressions demande la dépendance CssSelector. En développement, `CSS_SELECTOR_SOURCE` peut désigner ses sources Symfony. Les tests chargent les dépendances sans démarrer GLPI, utilisent des doubles des classes du cœur et créent seulement des fichiers temporaires isolés. Ils ne se connectent ni à une base ni à une boîte mail. Les tests d’interface lisent les MO livrés via un adaptateur gettext de test ; la production utilise le traducteur de GLPI.

## Traductions

L’anglais est la langue source. Les identifiants, commentaires, statuts techniques et journaux restent en anglais. Les messages d’interface passent par `I18n::t()` et le domaine GLPI `__('message', 'mailcleaner')`. GLPI charge la traduction compilée selon la langue choisie par l’utilisateur ; le plugin n’ajoute ni sélecteur de langue distinct ni français forcé.

Mettez à jour `locales/mailcleaner.pot` et les entrées `.po` anglaises/françaises lorsque vous ajoutez un message. Conservez les mêmes paramètres de formatage. Compilez avec Python 3 :

```sh
python tools/compile_locales.py
```

Le compilateur utilise la bibliothèque standard, accepte le format PO singulier employé ici, valide les traductions/paramètres et vérifie le résultat avec `gettext.GNUTranslations`. Versionnez les PO et MO. L’utilisateur final n’a besoin ni de Python, ni d’outil gettext, ni d’installation de dépendances. D’autres langues peuvent partir du modèle et employer les noms de locale GLPI.

## Audit des sources

Le contrat GLPI a été vérifié sur **12.0.0**, commit `cd812fbc82eaa8d0356d0fb650842069c1c18f4e` :

- [MailCollector](https://github.com/glpi-project/glpi/blob/12.0.0/src/MailCollector.php) : entrées `_mailgate`, `_auto_import`, création `Ticket::add()` et réponse `ITILFollowup::add()`.
- [CommonDBTM](https://github.com/glpi-project/glpi/blob/12.0.0/src/CommonDBTM.php#L1348), [Hooks](https://github.com/glpi-project/glpi/blob/12.0.0/src/Glpi/Plugin/Hooks.php#L452), [Plugin](https://github.com/glpi-project/glpi/blob/12.0.0/src/Plugin.php) : `PRE_ITEM_ADD` par classe avant préparation/insertion, liens `CONFIG_PAGE` et chargement des langues.
- [Config](https://github.com/glpi-project/glpi/blob/12.0.0/src/Config.php) : API des contextes ; [NotificationTargetTicket](https://github.com/glpi-project/glpi/blob/12.0.0/src/NotificationTargetTicket.php#L43) : délimiteurs de réponse.
- [Conversion des images](https://github.com/glpi-project/glpi/blob/12.0.0/src/Ticket.php#L5382) : placeholders avec `id` sans `src`.
- [CheckCsrfListener](https://github.com/glpi-project/glpi/blob/12.0.0/src/Glpi/Kernel/Listener/ControllerListener/CheckCsrfListener.php) : protection d’origine ; anciennes API CSRF dépréciées. Le formulaire ajoute son propre jeton de session et vérifie `config/UPDATE`.
- [Styles rich text](https://github.com/glpi-project/glpi/blob/12.0.0/css/includes/components/_richtext.scss) : héritage du thème du lecteur sans couleur fixée par le collecteur.
- [Verrou Composer](https://github.com/glpi-project/glpi/blob/12.0.0/composer.lock) : Symfony CssSelector 7.4.18, commit `fecf40067fc8d8880ea87b8ac227600b9aadd1d0` ; [documentation des sélecteurs](https://symfony.com/doc/7.4/components/css_selector.html).

Ces sources définissent le contrat ciblé. Les champs internes peuvent évoluer : revalidez-les pour les futures versions de GLPI. N’ajoutez pas de hooks de collecteur hypothétiques.

## Limites de validation

Contrôles de version : 93 assertions passent sous PHP 8.3.36 et 8.5.12 ; 9 contrôles de migration/identité et 8 scénarios d’interface bilingue passent sous PHP 8.3.36. Les deux MO de 86 messages sont relus par gettext Python. La syntaxe, les liens locaux, la couverture des traductions et les archives sont également vérifiés. Aucune capture réelle d’utilisateur n’est incluse dans le dépôt.

Les régressions couvrent les règles, parcours de coupure, marqueurs, protections des citations/pièces jointes, entrées mal formées, quotas, couleurs, simulation, exclusion des saisies manuelles et cycle d’installation. Les tests de migration et d’interface bilingue couvrent le renommage et les catalogues compilés.

Cela ne remplace pas une validation complète GLPI/base/IMAP ni une certification visuelle des thèmes. Avant production, installez sur une instance de test, ouvrez l’administration en anglais et français, testez des HTML représentatifs, collectez un ticket et une réponse, comparez des comptes en thème clair/sombre, vérifiez pièces jointes/notifications et retour au comportement natif après désactivation. Seuls les futurs imports sont concernés.

## Contribuer

Pour signaler un problème, fournissez un HTML minimal anonymisé, les réglages utiles, la sortie attendue et le statut réel. Ne publiez jamais de capture réelle, identifiant secret ou corps de mail privé. Une modification du traitement doit inclure une régression pertinente ; un nouveau message d’interface demande la mise à jour des deux catalogues et de la documentation.

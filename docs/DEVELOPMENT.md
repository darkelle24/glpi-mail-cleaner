# Development, translations and validation

[Français](DEVELOPMENT.fr.md) · [Back to README](../README.md)

## Repository layout

```text
setup.php              GLPI metadata and hooks
hook.php               install/uninstall and mail-only processing
bootstrap.php          explicit loading; no plugin vendor directory
src/Settings.php       defaults and validation
src/Cleaner.php        signature removal, cut-after and safeguards
src/Colors.php         sender color normalization
src/Capture.php        bounded private JSON captures
src/I18n.php           GLPI gettext adapter
front/config.php       authenticated administration and preview
locales/               English/French PO, MO and translation template
tests/                 standalone regression and UI-contract tests
tools/compile_locales.py
```

The plugin configuration context is `plugin:mailcleaner` in `glpi_configs`; no extra table is created. Installation imports known legacy keys only when the new context is empty. Uninstallation never deletes the legacy context, messages, attachments or captures.

## Run tests

Set `GLPI_AUTOLOAD` to the absolute path of your GLPI `vendor/autoload.php`, then run:

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

The regression runner needs the CssSelector dependency. As a development alternative, set `CSS_SELECTOR_SOURCE` to a Symfony CssSelector source checkout. The tests load dependencies without starting GLPI, use contract doubles for core classes, and create only isolated temporary diagnostic files. They do not connect to a database or mailbox. Frontend tests read the shipped MO catalogs through a test gettext adapter; production uses GLPI's own translator.

## Translation workflow

English is the source language. Keep identifiers, code comments, machine-readable statuses and logs in English. User-facing strings go through `I18n::t()` and GLPI's `__('message', 'mailcleaner')` domain. GLPI loads the compiled locale for the user's selected language; there is no separate language switch or hardcoded French fallback in the plugin.

Update `locales/mailcleaner.pot` and the matching English/French `.po` entries when adding messages. Keep format placeholders identical. Compile with Python 3:

```sh
python tools/compile_locales.py
```

The standard-library compiler accepts the singular PO format used here, validates translations/placeholders, and verifies the output with `gettext.GNUTranslations`. Commit both PO and MO files. Installed users do not need Python, gettext tools or dependency installation. Additional languages can use the template with GLPI locale filenames.

## Source audit

The GLPI contract was checked against **12.0.0**, commit `cd812fbc82eaa8d0356d0fb650842069c1c18f4e`:

- [MailCollector](https://github.com/glpi-project/glpi/blob/12.0.0/src/MailCollector.php): `_mailgate`, `_auto_import`, initial `Ticket::add()` and reply `ITILFollowup::add()` inputs.
- [CommonDBTM](https://github.com/glpi-project/glpi/blob/12.0.0/src/CommonDBTM.php#L1348), [Hooks](https://github.com/glpi-project/glpi/blob/12.0.0/src/Glpi/Plugin/Hooks.php#L452), [Plugin](https://github.com/glpi-project/glpi/blob/12.0.0/src/Plugin.php): class-scoped `PRE_ITEM_ADD` before preparation and insertion; `CONFIG_PAGE` links and locale loading.
- [Config](https://github.com/glpi-project/glpi/blob/12.0.0/src/Config.php): configuration context APIs; [NotificationTargetTicket](https://github.com/glpi-project/glpi/blob/12.0.0/src/NotificationTargetTicket.php#L43): reply delimiters.
- [Ticket image conversion](https://github.com/glpi-project/glpi/blob/12.0.0/src/Ticket.php#L5382): image placeholders with `id` and no `src`.
- [CheckCsrfListener](https://github.com/glpi-project/glpi/blob/12.0.0/src/Glpi/Kernel/Listener/ControllerListener/CheckCsrfListener.php): origin protection; legacy CSRF token APIs are deprecated. The form also checks its own session token and `config/UPDATE` rights.
- [Rich text styles](https://github.com/glpi-project/glpi/blob/12.0.0/css/includes/components/_richtext.scss): reader-theme inheritance without a fixed collector-selected color.
- [Composer lock](https://github.com/glpi-project/glpi/blob/12.0.0/composer.lock): Symfony CssSelector 7.4.18, commit `fecf40067fc8d8880ea87b8ac227600b9aadd1d0`; [selector documentation](https://symfony.com/doc/7.4/components/css_selector.html).

These sources define the targeted contract. Internal collector fields can change; recheck them for future GLPI releases. Do not add hypothetical collector hooks.

## Validation boundary

Release checks: 93 regression assertions pass on PHP 8.3.36 and 8.5.12; 9 migration/identity checks and 8 bilingual frontend scenarios pass on PHP 8.3.36. Both 86-message MO catalogs round-trip through Python's gettext reader. Source syntax, local documentation links, locale coverage and archive contents are also checked. No real user capture is included in the repository.

The regression suite covers removal rules, cut traversal, markers, quote/attachment protections, malformed input, quotas, color inheritance, dry run, manual-entry exclusion and installation lifecycle. Migration and bilingual frontend checks cover the renamed plugin and compiled locales.

This is not a full GLPI/database/IMAP or visual theme certification. Before production, install on a test instance, open administration in English and French, preview representative bodies, collect a new ticket and reply, compare light/dark accounts, check attachments and notifications, and verify disabling the plugin restores native behavior. Only future imports are affected.

## Contributing

Use a minimal anonymized HTML reproduction, relevant settings, expected output and actual status when reporting a problem. Never include real captures, credentials or private email bodies in a public repository. Changes to transformation logic should include meaningful regression cases; changes to user-facing strings should update both catalogs and documentation.

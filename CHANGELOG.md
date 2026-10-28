# Changelog

[Français](CHANGELOG.fr.md) · [README](README.md)

## 2.0.0 — 2026-10-28

- Renamed the plugin from Mail Signature Cleaner to **Mail Cleaner**.
- Changed the technical key, directory, PHP namespace, configuration context, log names and capture folder to `mailcleaner` / `MailCleaner`.
- Added first-install migration of recognized legacy settings without modifying the old configuration or overwriting an existing new configuration.
- Reworked administration with English source messages and native GLPI English/French gettext catalogs, including compiled MO files.
- Added English and French GitHub documentation with standard GLPI folder installation instructions.
- Retained selector removal, cut-after, optional history removal, theme colors, previews, dry run, safeguards and bounded diagnostics.

The key rename requires disabling the old plugin before enabling the new one. Read the migration steps in the README; uninstalling the old plugin before importing settings removes those settings.

## Earlier development

Versions 1.0–1.2 used the Mail Signature Cleaner name. They introduced selector-based signature removal, cross-parent cut-after rules, optional quote/header removal, diagnostic captures, synthetic write tests, the 100% threshold and per-reader GLPI theme color inheritance.

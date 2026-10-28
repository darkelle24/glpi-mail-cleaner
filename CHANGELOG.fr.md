# Journal des versions

[English](CHANGELOG.md) · [README](README.fr.md)

## 2.0.0 — 28 octobre 2026

- Renommage de Mail Signature Cleaner en **Mail Cleaner**.
- Nouvelle clé technique, nouveau dossier, espace de noms PHP, contexte de configuration, journaux et dossier de captures : `mailcleaner` / `MailCleaner`.
- Migration des anciens réglages reconnus lors de la première installation, sans modifier l’ancienne configuration ni écraser la nouvelle si elle existe.
- Administration réorganisée avec messages sources anglais et catalogues gettext GLPI anglais/français, MO compilés inclus.
- Documentation GitHub en anglais et français, installation par dossiers GLPI standard.
- Conservation des suppressions CSS, coupures, historique facultatif, couleurs du thème, aperçus, simulation, protections et diagnostics limités.

Le changement de clé demande de désactiver l’ancien plugin avant d’activer le nouveau. Suivez la migration du README ; désinstaller l’ancien plugin avant l’import efface ses réglages.

## Développement antérieur

Les versions 1.0 à 1.2 portaient le nom Mail Signature Cleaner. Elles ont introduit les suppressions CSS, coupures hors du parent, suppression facultative des citations/en-têtes, captures, tests d’écriture fictifs, seuil à 100 % et héritage du thème GLPI de chaque lecteur.

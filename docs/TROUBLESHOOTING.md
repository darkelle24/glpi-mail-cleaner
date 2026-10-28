# Troubleshooting and diagnostics

[Français](TROUBLESHOOTING.fr.md) · [Back to README](../README.md)

## The plugin is missing or incompatible

Check `plugins/mailcleaner/setup.php`, spelling, directory nesting and read permissions. GLPI must be 12.0.x, PHP at least 8.3, and the standard GLPI DOM/CssSelector dependencies available. Use the actual GLPI root, not its `public` directory. Make sure an older copy is not also present in the marketplace directory.

If the configuration is inaccessible, check that the plugin is enabled and the account has general setup update rights. The page URL is `<GLPI_URL>/plugins/mailcleaner/front/config.php`; keep your GLPI URL prefix if one is used. All access should go through GLPI's regular public front controller.

## Signatures remain

Check saved settings, dry run, and whether the message created a new collected **followup**. Initial tickets do not receive signature removal. Preview the actual `before_html` captured at the hook.

- `no_match`: the mail HTML differs from the rule. Desktop Outlook and webmail can produce different styles or containers.
- `ambiguous_cut`: a rule also finds anchors in old history; narrow the rule rather than relying on a first-match guess.
- `protected`: a quote, marker, attachment or configured protection prevents removal. A converted inline logo can protect its entire signature container.
- `ratio_guard`: the signature occupies too much text or HTML. A tested rule may need 100% for a very short reply; other safeguards remain.
- An Outlook From / Sent / To / Subject header can already be present after the signature. A deliberate cut-after rule with explicit history removal can remove it when it is after the anchor and not otherwise protected.

## Theme colors do not change

Check that the color option is enabled, dry run is off, and a **new mail** was collected. Old records are not rewritten. The signature and color options are independent. Review `colors_normalized`, `signature_status` and the HTML versions in a capture. Image pixels and logos are not recolored.

If the stored HTML no longer sets a text color but the display is still unreadable, inspect the GLPI page's theme styles separately: another plugin or custom theme may affect the surrounding container.

## No capture files

1. Confirm the configuration page shows version 2.0.0 and that **Temporary diagnostics** is saved as enabled.
2. Read the **actual capture path displayed on the page**. It comes from `GLPI_LOG_DIR`; do not assume an installation-specific path.
3. Click **Test writing a synthetic capture**. It uses the same writer and file quota, without saving settings or receiving a mail.
4. If writing fails, check the PHP account's write/traversal permissions, free space and whether the directory or lock is a symlink. A successful web test does not prove that a separate collector worker uses the same account or filesystem.
5. If the 20-file quota is reached, archive or remove old captures before retrying. Disabling capture does not remove the files.
6. If the synthetic test succeeds but mail captures are absent, confirm the plugin is active on the process running the collector and that the email actually became a new ticket or followup. Manual entries and preview tests do not trigger mail captures.

An older page version indicates stale code, the wrong installation directory, or a running PHP cache that needs refreshing. Capture write failures are sent to PHP's error log and forced into the technical log when possible; processing continues.

## Read a capture

| Field | Meaning |
|---|---|
| `before_html` | Body at the plugin hook, before this plugin changes it. |
| `proposed_html` | Calculated result. |
| `effective_html` | Body left by this hook for subsequent GLPI processing. |
| `matches`, `rules` | Selectors and settings evaluated for the message. |
| `status`, `signature_status` | Overall and signature-specific decisions. |
| `colors_normalized` | Whether the proposed result normalizes sender colors. |
| `enabled`, `dry_run` | Signature processing and dry-run settings. |
| `item_kind`, `ticket_id`, `collector_id` | Item type and context. A ticket's own ID is not known before initial insertion and is 0. |
| `body_limits` | Original byte counts and truncation flags. |
| `diagnostic_test` | True for the synthetic write test. |

In dry run, `effective_html` stays unchanged even when a proposal differs. Other GLPI processing or plugins can still run after this hook. The capture is not the original IMAP/POP MIME message; export an `.eml` from the mailbox if that comparison is required.

Treat captures as confidential. Avoid posting real email bodies or personal contact details in public issues. Share a minimal anonymized HTML example, the plugin/GLPI/PHP versions, relevant settings, status and expected result instead. Logs and captures are not automatically deleted by uninstallation.

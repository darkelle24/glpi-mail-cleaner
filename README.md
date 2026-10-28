# Mail Cleaner for GLPI

**Cleaner email followups. Readable messages in every GLPI theme.**

[Français](README.fr.md) · [Configuration](docs/CONFIGURATION.md) · [Troubleshooting](docs/TROUBLESHOOTING.md) · [Development](docs/DEVELOPMENT.md)

Mail Cleaner is a GLPI plugin that removes unwanted HTML signatures from incoming ticket followups and can make email text inherit each reader's GLPI theme colors. Define your own CSS rules, preview the result, and use bounded diagnostic captures to inspect what the collector actually delivers.

**Version:** 2.0.0 · **GLPI:** 12.0.x · **PHP:** 8.3 or newer · **License:** GPL-3.0-or-later

## Features

| Feature | What it does |
|---|---|
| Signature removal | Removes HTML elements matching multiple configurable CSS selectors. |
| Cut after an anchor | Keeps a selected element and removes everything following it, including content outside its parent. |
| Optional history removal | Allows cut-after rules to remove recognized quoted history and Outlook reply headers. |
| GLPI theme colors | Removes sender text colors and backgrounds so the message follows each reader's light or dark theme. |
| Before / after preview | Shows matched selectors, processing decisions and escaped HTML without creating a ticket or saving rules. |
| Dry run | Calculates the result while retaining the original content. |
| Technical logging | Records status and counters without message bodies. |
| Diagnostic captures | Saves before, proposed and effective HTML with the rules used; includes a synthetic write test. |
| English and French | English source strings and a compiled French translation, selected through GLPI's language setting. |

## What gets processed?

| Content source | Signature removal | Theme colors | Optional capture |
|---|---|---|---|
| New ticket received by a mail collector | No | Yes, when enabled | Yes |
| New email followup on a ticket | Yes, when enabled | Yes, when enabled | Yes |
| Manually entered ticket or followup | No | No | No |
| Existing content, tasks or solutions | No | No | No |

The plugin uses the collector's internal `_mailgate` and `_auto_import` fields, rather than the request type alone. IMAP and POP collectors share this path. Other importers deliberately supplying the same fields could also match. No core files are changed. Stored cleaned content may subsequently appear in GLPI notifications.

## Installation

1. Open the root directory of your GLPI installation: the directory containing `bin/`, `src/` and `public/`.
2. If the **`plugins`** directory does not exist there, create it.
3. Inside `plugins`, create a directory named exactly **`mailcleaner`**.
4. Copy the plugin files and directories into `plugins/mailcleaner/`. Copy the **contents** of the repository or release folder, not another enclosing folder.

The final layout must be:

```text
<GLPI_ROOT>/
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

5. Ensure the account running GLPI can read the files and traverse the directories. The plugin source does not need to be writable.
6. In GLPI, open **Setup → Plugins**, then **install** and **enable Mail Cleaner**.
7. Open the plugin's configuration from the plugins page. General setup update rights are required.

Do not place the plugin directly in `public/`, and avoid `plugins/mailcleaner/mailcleaner/setup.php`. The correct location is **`plugins/mailcleaner/setup.php`**. No dependency installation is required: the plugin uses DOM and Symfony CssSelector already supplied by GLPI. Do not keep duplicate copies under both `plugins` and `marketplace`.

## First setup

New installations start with signature cleaning **off**, theme colors **off**, diagnostic capture **off**, and dry run **on**.

1. Paste a representative incoming HTML body into **Before / after preview**.
2. Add selectors for signature blocks or cut-after anchors and preview the result.
3. Enable the processing features you want. Leave dry run enabled for an initial collector test.
4. Check the result, then disable **Dry run** and save to apply changes to future imports.

The preview uses the displayed settings, even while processing is disabled. It does not save them. **Save settings** is a separate action. Existing tickets and followups are not rewritten.

## Example rules

Remove specific signature containers:

```css
.gmail_signature
div.moz-signature
[id^="Signature"]
div.company-signature > table:not(.keep)
```

Cut after a recognized Outlook anchor, or an Outlook paragraph style:

```css
#appendonsend
p[style*="margin-bottom:6.0pt"][style*="line-height:12.0pt"]
```

Put these rules in **Cut everything after these CSS anchors**, not in the normal removal list. Rules use one line each. Lines starting with `//` are comments. These are examples, not universal signature detection: verify them against the HTML captured from your collector. A dedicated identifier in your own signature template is more reliable than formatting styles.

If one anchor rule matches several elements, cleaning is canceled. A missing anchor makes no cut. `#appendonsend` can also occur in messages without a signature: a cut there still removes the following history. Enable explicit history removal only when that is intended.

## Readable email in light and dark themes

Enable **Use GLPI theme colors for email text** to stop sender colors such as black text on a dark GLPI background from controlling the display. The plugin removes text color and background declarations, legacy color attributes and sender stylesheets. It lets **each reader's own theme** provide the text colors; it never stores a fixed white color or changes a user's preferences.

Text, links, tables, images and attachment references remain. Inline formatting such as bold, margins and dimensions is retained where valid. Removing sender stylesheets can remove other formatting too. Images themselves are not recolored: a black logo inside an image stays black. The option works independently of signature removal and respects dry run.

## Safeguards and limits

- GLPI reply markers `=-=-=-=` and `=_=_=_=` are always protected. Add your own text markers if needed.
- Recognized GLPI attachment links and image placeholders are protected. This can also retain a signature containing a converted inline logo.
- Quotes are protected unless explicit history removal is enabled for cut-after rules. Custom protected selectors still apply.
- An empty result is rejected. A configurable removal limit defaults to **80%** and accepts **1–100%**. At 100%, only the percentage check is lifted; other safeguards remain.
- Messages are limited to **1 MiB**, selector lists to **50 rules**, each selector to **512 bytes**, and removal candidates to **100 nodes**.
- Invalid rules, ambiguous anchors, size limits and unsafe processing outcomes keep the original content. Color normalization can still run independently when signature removal is refused; the preview reports both decisions.

Plain text and escaped HTML examples are retained. When HTML changes, DOM serialization can normalize entities, quotes and malformed markup. The plugin is not an HTML sanitizer or a semantic detector of useful text. A broad rule can remove legitimate content: test your own examples. More details are in [Configuration](docs/CONFIGURATION.md).

## Diagnostics

The optional technical log is `GLPI_LOG_DIR/mailcleaner.log`. It contains identifiers, mode, status and counters, without message bodies. Its regular output follows GLPI's file logging setting; capture write failures are also sent to the PHP error log.

For a difficult case, enable **Temporary diagnostics** and send a new collector email. Captures are written to **`GLPI_LOG_DIR/mailcleaner-debug/`**. The configuration page shows the actual path and provides **Test writing a synthetic capture**.

A capture includes `before_html`, `proposed_html`, `effective_html`, rule matches and processing decisions. It is the content at the plugin hook, **after GLPI's native collector processing**, not the original MIME message or a guarantee of the final database value after all other hooks. Capture is capped at **20 files**, with **256 KiB per body version** and explicit truncation flags. At the limit, capture stops; processing continues.

**Captures contain email content.** Disable capture after reproduction, keep the log directory outside the public web root, and remove captures after analysis. Files are private under normal Unix permissions; they are not deleted automatically. Synthetic tests also use a capture slot. For the raw original, export the message as `.eml` from the mailbox. See [Troubleshooting](docs/TROUBLESHOOTING.md).

## Language

The interface follows the language selected in GLPI. English (`en_GB`) and French (`fr_FR`) catalogs are included as editable `.po` files and ready-to-use `.mo` files. No compilation is needed to install the plugin. Technical identifiers, JSON keys and logs stay in English.

## Updating and migrating

For a regular update, disable Mail Cleaner, replace the contents of `plugins/mailcleaner/`, refresh the running PHP code if required, and complete any update action offered by GLPI before enabling it again. Keep a backup of your rules and verify a preview after the update.

**Moving from Mail Signature Cleaner (`mailsignaturecleaner`):**

1. Disable the old plugin. **Do not uninstall it yet**: uninstalling deletes its stored rules.
2. Install Mail Cleaner in the new `plugins/mailcleaner/` directory.
3. On first installation, Mail Cleaner copies recognized settings from the old plugin if it has no settings of its own. Existing Mail Cleaner settings are never overwritten.
4. Verify the imported rules and processing options, then enable Mail Cleaner. Keep only one cleaner active.
5. Once verified, the old plugin can be uninstalled and its directory removed. Old logs and captures are not moved or deleted; new ones use the `mailcleaner` name.

Disabling Mail Cleaner preserves its settings. Uninstalling deletes **only its own settings**; it does not restore removed signatures or delete tickets, attachments, logs or captures.

## Development and compatibility

The implementation was checked against GLPI **12.0.0** sources and declares compatibility with **12.0.x**. Recheck the internal collector contract after updating GLPI. GLPI 10, 11 and 12.1 or later are outside this release's declared compatibility.

[Development](docs/DEVELOPMENT.md) covers tests, translation compilation, source references and validation limits. [Changelog](CHANGELOG.md) lists the release changes. A real GLPI/database/mailbox integration test is still required for your installation.

## License

[GNU GPL version 3 or later](LICENSE).

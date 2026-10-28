# Configuration reference

[Français](CONFIGURATION.fr.md) · [Back to README](../README.md)

## Options

| Setting | Default | Effect |
|---|---|---|
| Clean signatures from collected followups | Off | Enables removal and cut-after rules on incoming ticket followups. |
| Use GLPI theme colors | Off | Normalizes colors on incoming tickets and followups, independently of signature cleaning. |
| Dry run | On | Calculates processing results but retains the original stored input. Applies to both processing features. |
| Also remove quoted history | Off | Lets cut-after targets include recognized quote containers and reply headers. Does not weaken normal removal rules. |
| Temporary diagnostics | Off | Writes bounded HTML snapshots for collector-created tickets and followups. |
| Technical log | Off | Writes status and counters without message bodies, subject to GLPI's logging configuration. |

Preview always evaluates the rules displayed in the form. It does not enable processing, save settings or create a ticket. Diagnostic write tests use synthetic text and do not save the form. Use **Save settings** to persist your choices.

## CSS removal rules

One selector per line; a comma can group selectors on one line. Blank lines and lines starting with `//` are ignored. Matches are evaluated against the same document and overlapping targets are removed once. A removal rule deletes only its matched subtree.

```css
.gmail_signature
#company-signature
[data-signature="company"]
table.signature tr:nth-child(2)
div.signature:not(.keep) > table
```

Selectors are handled by Symfony CssSelector supplied by GLPI. Classes, identifiers, attributes, child/descendant/sibling combinators and supported pseudo-classes work. `:has()`, pseudo-elements and browser-dependent states such as `:hover` are not supported by the targeted Symfony 7.4 version. Invalid rules are rejected when saved. XPath expressions and PHP code are not accepted.

## Cut-after rules

Use a separate list to keep an anchor and remove later content in document order. The traversal also visits the following siblings of each ancestor up to the message body: it can remove a disclaimer outside the signature's immediate parent without removing earlier reply text.

```css
#appendonsend
p[style*="margin-bottom:6.0pt"][style*="line-height:12.0pt"]
```

These examples cover some Outlook formats. An exact `style="..."` comparison can fail when Outlook rewrites or reorders declarations. Attribute substring matches tolerate extra declarations but still depend on the actual style values. An identifier you control is preferable.

Each rule must match zero or one anchor. Several matches for one rule cancel the cleaning pass. An anchor inside a protected quote is rejected, even when history removal is enabled, to avoid cutting from a signature in an old message. A missing anchor does nothing.

History removal lifts protection for recognized `blockquote`, `.gmail_quote`, `.moz-cite-prefix` and `#divRplyFwdMsg` nodes only when they are targeted by a cut-after rule. Attachments, markers and other protected selectors remain protected. Depending on the surrounding HTML, a protected descendant can retain a larger parent block. Cut-after is therefore not an unconditional byte truncation.

## Protected content

The GLPI markers `=-=-=-=` and `=_=_=_=` are mandatory and cannot be disabled through the form. Additional markers are literal text, one per line. Candidates containing a marker, including inside HTML comments, are retained; the final result is checked for marker counts.

Recognized attachment nodes include links/images using `document.send.php`, `[data-glpi-document]` and image placeholders with an `id` but no `src`. Their descendants and ancestors are protected. This may retain an inline signature logo converted by GLPI.

Default optional protected selectors:

```css
blockquote
.gmail_quote
.moz-cite-prefix
#divRplyFwdMsg
```

Quotes are also protected by a mandatory `blockquote` rule unless explicit cut-after history removal applies. Protection covers removal, not theme color normalization inside retained content.

## Removal percentage

The percentage check uses the larger of the text reduction and serialized HTML reduction. It defaults to 80%. Very short replies followed by large signatures may need a higher threshold. At 100%, the percentage check permits that reduction, but the empty-result, marker, attachment, ambiguity and size checks remain.

This setting is not a guarantee that every remaining character is legitimate. Use narrowly targeted rules and real previews.

## Theme colors

Color normalization removes sender text colors, backgrounds and background images, legacy color attributes, text shadows, relevant text-fill/stroke settings, CSS variables, resets, filters and sender stylesheets. It preserves remaining valid inline formatting and content. Stylesheets may contain layout rules that are lost along with their colors. Invalid inline styles are removed conservatively.

The HTML inherits the current reader's GLPI theme. Link appearance remains controlled by GLPI. The account running the collector does not choose a fixed stored color. A colored logo embedded in an image is unaffected. No historical content is updated.

## Limits and statuses

| Limit | Value |
|---|---|
| Message size | 1 MiB |
| Selectors | 50 per list |
| Selector length | 512 bytes |
| Candidate removal nodes | 100 |
| Extra text markers | Mandatory GLPI markers plus up to the validated total of 30 lines / 4096 bytes |
| Removal percentage | 1–100%, default 80% |
| Diagnostic captures | 20 files; 256 KiB per HTML body version |

| Status | Meaning |
|---|---|
| `cleaned` | Signature processing accepted a removal. |
| `colors_normalized` | Sender colors were removed; `signature_status` reports the separate signature decision. |
| `no_match` | No rule matched. |
| `protected` | Matched content or the selected anchor was protected. |
| `ambiguous_cut` / `invalid_cut` | Multiple anchors for one rule, or an anchor outside the body. |
| `empty_guard` / `ratio_guard` | Empty result or removal percentage exceeded. |
| `marker_guard` | A protected marker count would change. |
| `too_many_matches` / `too_large` | Node count or input size limit exceeded. |
| `plain_text` | Plain text kept as received. |
| `unsafe_declaration` / `parse_failed` | Rejected declaration or unparseable HTML body. |
| `error` | Signature processing failed and kept the input. |
| `not_requested` | Signature removal was not requested for this processing pass. |
| `unchanged` | No effective change. |

The collector already transforms HTML before the plugin runs. Rule matches must be checked against captured `before_html`, not only browser-inspected webmail markup. HTML containing literal tag examples can be interpreted as markup; wholly escaped examples are retained. The original message is preserved byte-for-byte if no transformation is accepted. Once transformed, DOM serialization may normalize formatting.

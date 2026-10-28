<?php
use Glpi\Kernel\Kernel;
use MailCleaner\Capture;
use MailCleaner\Cleaner;
use MailCleaner\I18n;
use MailCleaner\Settings;

// GLPI must authenticate and bootstrap this route before loading it.
if (!class_exists(Kernel::class, false) || !defined('GLPI_ROOT')) {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/bootstrap.php';
Session::checkRight('config', UPDATE);
if (!Plugin::isPluginActive('mailcleaner')) {
    http_response_code(403);
    exit;
}
header('Cache-Control: no-store');

function mc_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function mc_t(string $message, mixed ...$args): string
{
    return mc_escape(I18n::t($message, ...$args));
}

$settings = Settings::load();
$savedCapture = $settings['debug_capture'] === '1';
$sample = '<p>' . mc_t('Hello, the issue is resolved. Thank you for your help.') . '</p>'
    . '<div class="gmail_signature">' . mc_t('Kind regards, Alice') . '</div>';
$result = null;
$message = '';
$error = '';
$_SESSION['mc_form_token'] ??= bin2hex(random_bytes(32));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!is_string($_POST['mc_token'] ?? null) || !hash_equals($_SESSION['mc_form_token'], $_POST['mc_token'])) {
        http_response_code(403);
        exit(mc_t('Your session has expired or the request is invalid. Reload this page.'));
    }
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'diagnostic') {
            $diagnosticSettings = $settings;
            $diagnosticSettings['debug_capture'] = '1';
            $input = ['content' => '<p>Synthetic capture test. No user email.</p>', '_mc_diagnostic_test' => true];
            $diagnostic = ['html' => $input['content'], 'status' => 'diagnostic_test', 'removed' => 0,
                'protected' => 0, 'matches' => [], 'warnings' => []];
            try {
                $name = Capture::write($input, $diagnosticSettings, $diagnostic, $input['content']);
                $message = $name === null
                    ? I18n::t('No capture was written: the 20-file limit was reached or the folder cannot be listed.')
                    : I18n::t('Capture written to: %s', Capture::directory() . '/' . $name);
                if ($name !== null) {
                    $message .= ' ' . I18n::t('This confirms write access for the web process, not receipt of an email by the collector.');
                }
            } catch (Throwable $e) {
                $error = I18n::t('Capture write failed: %s', I18n::error($e->getMessage()));
            }
        } else {
            $settings = Settings::validate($_POST);
            if ($action === 'save') {
                Config::setConfigurationValues(Settings::CONTEXT, $settings);
                $savedCapture = $settings['debug_capture'] === '1';
                $message = I18n::t('Settings saved.');
            } elseif ($action === 'test') {
                if (!is_string($_POST['sample'] ?? null) || strlen($_POST['sample']) > Cleaner::MAX_BYTES) {
                    throw new InvalidArgumentException('The test HTML must not exceed 1 MiB.');
                }
                $sample = $_POST['sample'];
                $result = (new Cleaner())->clean($sample, $settings);
                $message = I18n::t('Preview generated using the displayed rules. Settings were not saved.');
            } else {
                throw new InvalidArgumentException('Unknown action.');
            }
        }
    } catch (InvalidArgumentException $e) {
        $error = I18n::error($e->getMessage());
        foreach (array_keys(Settings::defaults()) as $key) {
            if (is_string($_POST[$key] ?? null) && strlen($_POST[$key]) <= 26000) {
                $settings[$key] = $_POST[$key];
            }
        }
        if (is_string($_POST['sample'] ?? null) && strlen($_POST['sample']) <= Cleaner::MAX_BYTES) {
            $sample = $_POST['sample'];
        }
    }
}
Html::header('Mail Cleaner', '', 'config', 'plugins');
echo '<div class="container-xl"><h1>Mail Cleaner</h1><p>' . mc_t('Version: %s', '2.0.0') . '</p>';
echo '<p>' . mc_t('Clean signatures from collected followups and use GLPI theme colors for incoming tickets and followups. Manual entries are excluded.') . '</p>';
foreach ([$message => 'success', $error => 'danger'] as $text => $kind) {
    if ($text !== '') {
        echo '<div class="alert alert-' . $kind . '" role="status">' . mc_escape($text) . '</div>';
    }
}
echo '<form method="post" action="' . mc_escape($CFG_GLPI['root_doc'] . '/plugins/mailcleaner/front/config.php') . '">'
    . '<input type="hidden" name="mc_token" value="' . mc_escape($_SESSION['mc_form_token']) . '">';
echo '<h2>' . mc_t('Processing options') . '</h2>';
$options = [
    'enabled' => 'Clean signatures from collected followups',
    'inherit_mail_colors' => 'Use GLPI theme colors for email text (tickets and followups)',
    'dry_run' => 'Dry run: keep the original ticket and followup content',
    'cut_remove_quotes' => 'Cut after anchor: also remove quoted history and Outlook reply headers',
    'debug_capture' => 'Temporary diagnostics: capture HTML before and after processing (20 files maximum)',
    'log' => 'Write a technical log without message bodies',
];
foreach ($options as $key => $label) {
    echo '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="' . $key . '" name="'
        . $key . '" value="1"' . ($settings[$key] === '1' ? ' checked' : '') . '><label class="form-check-label" for="'
        . $key . '">' . mc_t($label) . '</label></div>';
}
echo '<p>' . mc_t('Theme colors work independently of signature cleaning. Sender colors, backgrounds and stylesheets are removed so each reader uses their own GLPI theme. Images are not recolored. Existing tickets are unchanged.') . '</p>';
echo '<h2>' . mc_t('Cleaning rules') . '</h2>';
$fields = [
    'selectors' => 'CSS selectors to remove (one per line)',
    'cut_after_selectors' => 'Cut everything after these CSS anchors (one per line, optional)',
    'protect_selectors' => 'Protected CSS selectors (one per line)',
    'markers' => 'Protected text markers (one per line)',
];
foreach ($fields as $key => $label) {
    echo '<div class="mb-3"><label class="form-label" for="' . $key . '">' . mc_t($label) . '</label>'
        . '<textarea class="form-control font-monospace" id="' . $key . '" name="' . $key . '" rows="4">'
        . mc_escape($settings[$key]) . '</textarea></div>';
}
echo '<p>' . mc_t('Removal deletes the matched element and its children. Cut-after keeps the anchor and removes following content across parent boundaries. Multiple matches for one anchor rule cancel cleaning. Lines starting with // are comments.') . '</p>';
echo '<p>' . mc_t('Examples:') . ' <code>.gmail_signature</code>, <code>div.signature &gt; table</code>, '
    . '<code>[id^="Signature"]</code>, <code>div.signature:not(.keep)</code>. '
    . mc_t('Pseudo-elements, browser states and :has() are not supported.') . '</p>';
echo '<label class="form-label" for="max_removed_percent">' . mc_t('Maximum content removed (%)') . '</label>'
    . '<input class="form-control mb-2" type="number" min="1" max="100" id="max_removed_percent" name="max_removed_percent" value="'
    . mc_escape($settings['max_removed_percent']) . '">';
echo '<p>' . mc_t('100% allows a long signature after a short reply. Empty-result, attachment and GLPI marker protections still apply. Use this limit only with a precisely tested rule.') . '</p>';
echo '<p>' . mc_t('Quotes are protected unless explicit history removal is enabled for cut-after rules. These safeguards cannot identify every meaningful part of an email: test real examples first.') . '</p>';
echo '<button class="btn btn-primary mb-4" type="submit" name="action" value="save">' . mc_t('Save settings') . '</button>';
echo '<h2>' . mc_t('Before / after preview') . '</h2><p>'
    . mc_t('Paste the HTML received by GLPI after collector processing. Preview works while disabled or in dry run, saves no settings and creates no ticket or followup.') . '</p>';
echo '<label class="form-label" for="sample">' . mc_t('Test HTML') . '</label><textarea class="form-control font-monospace" id="sample" name="sample" rows="8">'
    . mc_escape($sample) . '</textarea><button class="btn btn-secondary my-3" type="submit" name="action" value="test">'
    . mc_t('Preview without saving') . '</button>';
echo '<h2>' . mc_t('Capture diagnostics') . '</h2><p>' . mc_t('Capture folder: %s', Capture::directory()) . '<br>'
    . mc_t('Saved capture setting: %s', I18n::t($savedCapture ? 'Enabled' : 'Disabled')) . '</p>';
echo '<p>' . mc_t('Captures contain message bodies, rules and results, including in dry run. Limits: 20 files and 256 KiB per body version. This is not the original MIME email. Disable capture after testing and remove the files after analysis.') . '</p>';
echo '<button class="btn btn-secondary mb-3" type="submit" name="action" value="diagnostic">'
    . mc_t('Test writing a synthetic capture') . '</button></form>';
if ($result !== null) {
    $statuses = [
        'cleaned' => 'Cleaning completed', 'no_match' => 'No matching element',
        'colors_normalized' => 'Sender colors removed; the GLPI theme is inherited',
        'ambiguous_cut' => 'Skipped: an anchor rule matched more than once', 'invalid_cut' => 'Skipped: anchor outside the message body',
        'protected' => 'Matched content is protected', 'empty_guard' => 'Skipped: the result would be empty',
        'ratio_guard' => 'Skipped: removal limit exceeded', 'marker_guard' => 'Skipped: a protected marker would change',
        'too_many_matches' => 'Skipped: more than 100 matched elements', 'too_large' => 'Message exceeds the size limit',
        'plain_text' => 'Plain text kept unchanged', 'parse_failed' => 'Could not parse the HTML body',
        'unsafe_declaration' => 'HTML/XML declaration rejected', 'error' => 'Processing error',
        'unchanged' => 'Unchanged', 'not_requested' => 'Not requested',
    ];
    echo '<div class="alert alert-info">' . mc_t($statuses[$result['status']] ?? $result['status']) . '<br>'
        . mc_t('Signature processing: %s', I18n::t($statuses[$result['signature_status']] ?? $result['signature_status'])) . '<br>'
        . mc_t('Removed blocks: %d; protected matches: %d', $result['removed'], $result['protected']) . '</div>';
    foreach ($result['warnings'] as $warning) {
        echo '<div class="alert alert-warning">' . mc_t($warning) . '</div>';
    }
    echo '<table class="table"><thead><tr><th>' . mc_t('Selector') . '</th><th>' . mc_t('Matches') . '</th></tr></thead><tbody>';
    foreach ($result['matches'] as $selector => $count) {
        $label = str_starts_with($selector, 'After: ') ? I18n::t('After: %s', substr($selector, 7)) : $selector;
        echo '<tr><td><code>' . mc_escape($label) . '</code></td><td>' . $count . '</td></tr>';
    }
    echo '</tbody></table><div class="row">';
    foreach (['Before' => $sample, 'After' => $result['html']] as $label => $content) {
        echo '<section class="col-md-6"><h3>' . mc_t($label) . '</h3><pre class="border rounded p-3 text-wrap">'
            . mc_escape($content) . '</pre></section>';
    }
    echo '</div><p>' . mc_t('HTML is displayed as escaped source. Email scripts, styles and tracking pixels are not executed by this preview.') . '</p>';
}
echo '</div>';
Html::footer();

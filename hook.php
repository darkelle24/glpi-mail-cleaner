<?php
use MailCleaner\Cleaner;
use MailCleaner\Capture;
use MailCleaner\Settings;

function plugin_mailcleaner_install(): bool
{
    require_once __DIR__ . '/bootstrap.php';
    if (Config::getConfigurationValues(Settings::CONTEXT) === []) {
        // Copy only known settings; never delete or change the legacy context.
        $legacy = Config::getConfigurationValues('plugin:mailsignaturecleaner');
        $defaults = Settings::defaults();
        $knownLegacy = array_intersect_key($legacy, $defaults);
        Config::setConfigurationValues(Settings::CONTEXT, array_replace($defaults, $knownLegacy));
    }
    return true;
}

function plugin_mailcleaner_uninstall(): bool
{
    require_once __DIR__ . '/bootstrap.php';
    Config::deleteConfigurationValues(Settings::CONTEXT, array_keys(Settings::defaults()));
    return true;
}

function plugin_mailcleaner_pre_item_add($item): void
{
    require_once __DIR__ . '/bootstrap.php';
    $followup = $item instanceof ITILFollowup;
    $ticket = $item instanceof Ticket;
    if ((!$followup && !$ticket) || !is_array($item->input)) {
        return;
    }
    $input = $item->input;
    // _mailgate + _auto_import are carried by MailCollector::buildTicket().
    // Request type alone also occurs in manually entered followups: do not use it.
    if (($followup && ($input['itemtype'] ?? '') !== 'Ticket')
        || (int) ($input['_mailgate'] ?? 0) <= 0
        || empty($input['_auto_import'])
        || !is_string($input['content'] ?? null)) {
        return;
    }
    try {
        $settings = Settings::load();
        if (($settings['enabled'] !== '1' || $ticket)
            && $settings['inherit_mail_colors'] !== '1' && $settings['debug_capture'] !== '1') {
            return;
        }
        $result = (new Cleaner())->clean($input['content'], $settings, $followup && $settings['enabled'] === '1');
        if (($settings['enabled'] === '1' || $settings['inherit_mail_colors'] === '1')
            && $settings['dry_run'] !== '1' && $result['changed']) {
            $item->input['content'] = $result['html'];
        }
        if ($settings['debug_capture'] === '1') {
            try {
                $captureInput = $input;
                $captureInput['_mc_item_kind'] = $ticket ? 'Ticket' : 'ITILFollowup';
                Capture::write($captureInput, $settings, $result, $item->input['content']);
            } catch (\Throwable $captureError) {
                // Diagnostic I/O errors must never interrupt collection or cleaning.
                $captureFailure = 'Diagnostic capture failed: ' . $captureError->getMessage();
                error_log('[Mail Cleaner] ' . $captureFailure);
                Toolbox::logInFile('mailcleaner', $captureFailure . "\n", true, false);
            }
        }
        if ($settings['log'] === '1') {
            // Do not log selectors, sender, subject, or message content.
            Toolbox::logInFile('mailcleaner', sprintf(
                "collector=%d ticket=%d mode=%s status=%s removed=%d protected=%d\n",
                (int) $input['_mailgate'], (int) ($input['items_id'] ?? 0),
                ($settings['enabled'] !== '1' && $settings['inherit_mail_colors'] !== '1')
                    ? 'disabled' : ($settings['dry_run'] === '1' ? 'simulation' : 'active'),
                $result['status'], $result['removed'], $result['protected']
            ));
        }
    } catch (\Throwable $e) {
        // Fail open: collector and message must survive a plugin failure.
        Toolbox::logInFile('mailcleaner', "Cleaning skipped: internal error.\n");
    }
}

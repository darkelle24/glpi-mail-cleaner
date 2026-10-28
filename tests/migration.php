<?php
/** Isolated settings-context tests; never starts GLPI or writes a database. */
require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/setup.php';
use MailCleaner\Settings;

class Config
{
    public static array $contexts = [];
    public static function getConfigurationValues($context): array { return self::$contexts[$context] ?? []; }
    public static function setConfigurationValues($context, array $values): void { self::$contexts[$context] = $values; }
    public static function deleteConfigurationValues($context, array $keys): void {
        foreach ($keys as $key) { unset(self::$contexts[$context][$key]); }
    }
}
$checks = 0;
function verify(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException($name); }
    $checks++;
}
$legacyContext = 'plugin:mailsignaturecleaner';
$legacy = ['selectors' => '#company', 'enabled' => '1', 'dry_run' => '0',
    'inherit_mail_colors' => '1', 'unrecognized_key' => 'do-not-copy'];
Config::$contexts[$legacyContext] = $legacy;
plugin_mailcleaner_install();
verify(Settings::CONTEXT === 'plugin:mailcleaner', 'new context');
verify(Config::$contexts[Settings::CONTEXT]['selectors'] === '#company'
    && Config::$contexts[Settings::CONTEXT]['inherit_mail_colors'] === '1', 'known settings imported');
verify(!isset(Config::$contexts[Settings::CONTEXT]['unrecognized_key']), 'unknown legacy keys ignored');
verify(Config::$contexts[$legacyContext] === $legacy, 'legacy context untouched');
verify(array_keys(Config::$contexts[Settings::CONTEXT]) === array_keys(Settings::defaults()), 'missing defaults supplied');
Config::$contexts[Settings::CONTEXT]['selectors'] = '#new-rules';
plugin_mailcleaner_install();
verify(Config::$contexts[Settings::CONTEXT]['selectors'] === '#new-rules', 'existing new context never overwritten');
plugin_mailcleaner_uninstall();
verify(Config::$contexts[Settings::CONTEXT] === [] && Config::$contexts[$legacyContext] === $legacy,
    'uninstall deletes only the new settings');
Config::$contexts = [];
plugin_mailcleaner_install();
verify(Config::$contexts[Settings::CONTEXT] === Settings::defaults(), 'fresh installation uses safe defaults');
verify(plugin_version_mailcleaner()['name'] === 'Mail Cleaner'
    && plugin_version_mailcleaner()['version'] === '2.0.0', 'renamed metadata');
echo "PASS: $checks migration and identity checks\n";

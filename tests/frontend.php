<?php
namespace Glpi\Kernel { class Kernel {} }
namespace {
/** Contract test with actual shipped MO files and lightweight GLPI adapters. */
$language = $argv[1] ?? 'en_GB';
$scenario = $argv[2] ?? 'get';
if (!in_array($language, ['en_GB', 'fr_FR'], true)) {
    throw new \InvalidArgumentException('Use en_GB or fr_FR.');
}
$bytes = file_get_contents(dirname(__DIR__) . '/locales/' . $language . '.mo');
$header = unpack('Vmagic/Vrevision/Vcount/Voriginal/Vtranslation/Vhash_size/Vhash_offset', $bytes);
if ($header['magic'] !== 0x950412de) { throw new \RuntimeException('Invalid MO file.'); }
$catalog = [];
for ($i = 0; $i < $header['count']; $i++) {
    $keyInfo = unpack('Vlength/Voffset', substr($bytes, $header['original'] + 8 * $i, 8));
    $valueInfo = unpack('Vlength/Voffset', substr($bytes, $header['translation'] + 8 * $i, 8));
    $catalog[substr($bytes, $keyInfo['offset'], $keyInfo['length'])]
        = substr($bytes, $valueInfo['offset'], $valueInfo['length']);
}
function __(string $message, string $domain = 'core'): string
{
    global $catalog;
    if ($domain !== 'mailcleaner') { throw new \RuntimeException('Wrong translation domain.'); }
    return $catalog[$message] ?? $message;
}
if ($language === 'fr_FR' && __('Save settings', 'mailcleaner') === 'Save settings') {
    throw new \RuntimeException('French catalog was not loaded.');
}
$autoload = getenv('GLPI_AUTOLOAD');
if ($autoload && is_file($autoload)) {
    // Locate this standalone dependency without loading GLPI globals/functions,
    // which would conflict with the explicit core adapters used by this test.
    $source = dirname($autoload) . '/symfony/css-selector';
} else {
    $source = getenv('CSS_SELECTOR_SOURCE');
}
if ($source) {
    spl_autoload_register(static function (string $class) use ($source): void {
        $prefix = 'Symfony\\Component\\CssSelector\\';
        if (str_starts_with($class, $prefix)) {
            require $source . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    });
}
define('UPDATE', 2);
define('GLPI_ROOT', dirname(__DIR__));
$temporary = sys_get_temp_dir() . '/mc-ui-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
define('GLPI_LOG_DIR', $temporary);
require dirname(__DIR__) . '/bootstrap.php';
class Session {
    public static bool $checked = false;
    public static function checkRight($key, $right): void {
        if ($key !== 'config' || $right !== UPDATE) { throw new \RuntimeException('Wrong permission.'); }
        self::$checked = true;
    }
}
class Plugin {
    public static function isPluginActive($key): bool {
        if ($key !== 'mailcleaner') { throw new \RuntimeException('Wrong plugin identity.'); }
        return true;
    }
}
class Config {
    public static function getConfigurationValues($context): array { return \MailCleaner\Settings::defaults(); }
    public static function setConfigurationValues($context, $values): void {
        throw new \RuntimeException('Preview and diagnostic must not save settings.');
    }
}
class Html {
    public static function header(...$args): void {}
    public static function footer(): void {}
}
$_SESSION = ['glpilanguage' => $language, 'mc_form_token' => 'test-token'];
$_SERVER['REQUEST_METHOD'] = $scenario === 'get' ? 'GET' : 'POST';
$_POST = [];
if ($scenario !== 'get') {
    $_POST = array_replace(\MailCleaner\Settings::defaults(), [
        'mc_token' => 'test-token', 'action' => $scenario === 'diagnostic' ? 'diagnostic' : 'test',
        'sample' => '<p>Reply</p><div class="gmail_signature">Alice</div><script>alert(1)</script>',
    ]);
    if ($scenario === 'invalid') { $_POST['selectors'] = 'div['; }
}
$CFG_GLPI = ['root_doc' => '/helpdesk'];
ob_start();
require dirname(__DIR__) . '/front/config.php';
$html = ob_get_clean();
if (!Session::$checked || !str_contains($html, 'Mail Cleaner') || !str_contains($html, '2.0.0')
    || !str_contains($html, '/helpdesk/plugins/mailcleaner/front/config.php')
    || !str_contains($html, __('Save settings', 'mailcleaner'))) {
    throw new \RuntimeException('Missing identity, route, permission or translated label.');
}
if (str_contains($html, '<script>')) { throw new \RuntimeException('Raw sample HTML executed in preview.'); }
if ($scenario === 'preview' && !str_contains($html, __('Cleaning completed', 'mailcleaner'))) {
    throw new \RuntimeException('Preview status not translated.');
}
if ($scenario === 'invalid' && !str_contains($html,
    htmlspecialchars(sprintf(__('Invalid or unsupported CSS selector: %s', 'mailcleaner'), 'div['), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))) {
    throw new \RuntimeException('Validation error not translated.');
}
$files = glob($temporary . '/mailcleaner-debug/capture-*.json');
if ($scenario === 'diagnostic') {
    if (count($files) !== 1) { throw new \RuntimeException('Synthetic capture missing.'); }
    $record = json_decode(file_get_contents($files[0]), true, flags: JSON_THROW_ON_ERROR);
    if (!$record['diagnostic_test'] || $record['plugin_version'] !== '2.0.0') {
        throw new \RuntimeException('Capture identity not updated.');
    }
}
foreach ($files as $file) { unlink($file); }
if (is_dir($temporary . '/mailcleaner-debug')) {
    unlink($temporary . '/mailcleaner-debug/.capture.lock');
    rmdir($temporary . '/mailcleaner-debug');
}
rmdir($temporary);
echo "PASS: $language $scenario frontend contract with compiled translations\n";
}

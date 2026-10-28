<?php
namespace MailCleaner;

/** Use GLPI's gettext domain; standalone tools fall back to the English msgid. */
final class I18n
{
    public static function t(string $message, mixed ...$arguments): string
    {
        $translated = function_exists('__') ? \__($message, 'mailcleaner') : $message;
        return $arguments === [] ? $translated : vsprintf($translated, $arguments);
    }

    public static function error(string $message): string
    {
        $prefix = 'Invalid or unsupported CSS selector: ';
        if (str_starts_with($message, $prefix)) {
            return self::t('Invalid or unsupported CSS selector: %s', substr($message, strlen($prefix)));
        }
        return self::t($message);
    }
}

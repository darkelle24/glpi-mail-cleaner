<?php
namespace MailCleaner;

final class Settings
{
    public const CONTEXT = 'plugin:mailcleaner';

    public static function defaults(): array
    {
        return ['enabled' => '0', 'dry_run' => '1', 'log' => '0',
            'selectors' => ".gmail_signature\n#Signature\ndiv.moz-signature",
            'cut_after_selectors' => '',
            'cut_remove_quotes' => '0',
            'debug_capture' => '0',
            'inherit_mail_colors' => '0',
            'protect_selectors' => "blockquote\n.gmail_quote\n.moz-cite-prefix\n#divRplyFwdMsg",
            'markers' => "=-=-=-=\n=_=_=_=", 'max_removed_percent' => '80'];
    }

    public static function load(): array
    {
        return array_replace(self::defaults(), \Config::getConfigurationValues(self::CONTEXT));
    }

    public static function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []),
            static fn(string $s): bool => $s !== '' && !str_starts_with($s, '//')));
    }

    public static function validate(array $data): array
    {
        $out = self::defaults();
        foreach (['enabled', 'dry_run', 'log', 'cut_remove_quotes', 'debug_capture', 'inherit_mail_colors'] as $key) {
            $out[$key] = ($data[$key] ?? '') === '1' ? '1' : '0';
        }
        $converter = new \Symfony\Component\CssSelector\CssSelectorConverter();
        $data += ['cut_after_selectors' => ''];
        foreach (['selectors', 'cut_after_selectors', 'protect_selectors'] as $key) {
            if (!is_string($data[$key] ?? null) || strlen($data[$key]) > 26000) {
                throw new \InvalidArgumentException('The selector list is missing or too long.');
            }
            $lines = self::lines($data[$key]);
            if (count($lines) > 50) {
                throw new \InvalidArgumentException('Use no more than 50 selectors per list.');
            }
            foreach ($lines as $line) {
                if (strlen($line) > 512) {
                    throw new \InvalidArgumentException('A selector exceeds 512 bytes.');
                }
                try {
                    $converter->toXPath($line);
                } catch (\Throwable $e) {
                    throw new \InvalidArgumentException('Invalid or unsupported CSS selector: ' . $line);
                }
            }
            $out[$key] = implode("\n", $lines);
        }
        $markers = $data['markers'] ?? '';
        if (!is_string($markers) || strlen($markers) > 4096 || count(self::lines($markers)) > 30) {
            throw new \InvalidArgumentException('Use no more than 30 markers and 4096 bytes.');
        }
        // GLPI delimiters are mandatory even if omitted from the UI.
        $out['markers'] = implode("\n", array_unique(array_merge(
            ['=-=-=-=', '=_=_=_='], self::lines($markers)
        )));
        $percent = filter_var($data['max_removed_percent'] ?? null, FILTER_VALIDATE_INT);
        if ($percent === false || $percent < 1 || $percent > 100) {
            throw new \InvalidArgumentException('The removal limit must be between 1 and 100 percent.');
        }
        $out['max_removed_percent'] = (string) $percent;
        return $out;
    }
}

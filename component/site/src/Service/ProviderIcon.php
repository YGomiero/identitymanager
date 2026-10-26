<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Yann\Component\IdentityManager\Site\Service;
\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

/** Decorative provider logos and administrator-selected site favicons. */
final class ProviderIcon
{
    public static function validUrl(string $url): bool
    {
        // Local media avoids a third-party image request. Reject protocol-relative paths.
        if (preg_match('~^/[A-Za-z0-9_./%-]+$~D', $url) && !str_starts_with($url, '//') && !str_contains($url, '..')) { return true; }
        $parts = parse_url($url);
        return filter_var($url, FILTER_VALIDATE_URL) && ($parts['scheme'] ?? '') === 'https'
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['fragment']);
    }

    public static function render(array $provider): string
    {
        $options = json_decode((string) ($provider['options_json'] ?? '{}'), true) ?: [];
        $adapter = strtolower((string) ($options['adapter'] ?? $provider['alias'] ?? ''));
        $base = Uri::root(true) . '/media/com_identitymanager/images/';
        $default = $base . 'site.svg';
        $custom = (string) ($options['icon_url'] ?? '');
        $url = match ($adapter) { 'github' => $base . 'github.svg', 'google' => $base . 'google.png', default => self::validUrl($custom) ? $custom : $default };
        $escape = static fn($value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        return '<span class="identitymanager-icon" aria-hidden="true"><img src="' . $escape($url) . '" alt="" width="20" height="20" referrerpolicy="no-referrer" data-identitymanager-fallback="' . $escape($default) . '"></span>';
    }
}

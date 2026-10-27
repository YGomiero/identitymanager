<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Yann\Component\IdentityManager\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/** Keeps a module's server-owned destination through OAuth and local account confirmation. */
final class LoginRedirect
{
    public function remember(string $alias, int $moduleId): void
    {
        $app = Factory::getApplication();
        $app->getSession()->set('identitymanager.destination.' . $alias, [
            'login' => (bool) $app->getIdentity()->guest,
            'module_id' => max(0, $moduleId),
            'expires' => time() + 600,
        ]);
    }

    /** Consume once, including when transferring the context into a pending local confirmation. */
    public function take(string $alias): ?array
    {
        $session = Factory::getApplication()->getSession();
        $context = $session->get('identitymanager.destination.' . $alias);
        $session->clear('identitymanager.destination.' . $alias);
        return is_array($context) ? $context : null;
    }

    public function finish(string $alias): string
    {
        return $this->resolve($this->take($alias));
    }

    public function resolve(?array $context): string
    {
        // Linking from an already authenticated account remains an account-management operation.
        if (!$context || empty($context['login'])) {
            return Route::_('index.php?option=com_identitymanager&view=connections', false);
        }
        $home = Uri::root();
        if (($context['expires'] ?? 0) < time()) { return $home; }
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $id = (int) ($context['module_id'] ?? 0);
        $module = $db->setQuery('SELECT params,access,publish_up,publish_down FROM #__modules WHERE id=' . $id
            . " AND module='mod_login' AND client_id=0 AND published=1")->loadObject();
        // Login starts as a guest: an "Guests" module legitimately disappears after sign-in.
        $guestLevels = (new \Joomla\CMS\User\User())->getAuthorisedViewLevels();
        if (!$module || !in_array((int) $module->access, $guestLevels, true)) { return $home; }
        $now = Factory::getDate()->toSql();
        foreach (['publish_up' => true, 'publish_down' => false] as $field => $future) {
            $date = $module->$field;
            if ($date && $date !== '0000-00-00 00:00:00' && ($future ? $date > $now : $date < $now)) { return $home; }
        }
        $params = new Registry($module->params);
        switch ($params->get('identitymanager_redirect', 'home')) {
            case 'menu':
                $item = $app->getMenu()->getItem((int) $params->get('identitymanager_menu', 0));
                if (!$item || !in_array((int) $item->access, $app->getIdentity()->getAuthorisedViewLevels(), true)
                    || !in_array($item->type, ['component', 'url'], true)) { return $home; }
                $url = $item->type === 'url' ? $item->link : Route::_('index.php?Itemid=' . (int) $item->id
                    . ($item->language !== '*' ? '&lang=' . rawurlencode($item->language) : ''), false);
                return self::localUrl($url, $home) ?? $home;
            case 'url':
                return self::localUrl((string) $params->get('identitymanager_url', ''), $home) ?? $home;
            default:
                return $home;
        }
    }

    /** Only local HTTP(S) destinations; never consume a visitor-supplied return URL. */
    public static function localUrl(string $url, string $root): ?string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) { return null; }
        $probe = $url;
        for ($i = 0; $i < 4; $i++) {
            if (preg_match('/[\x00-\x1f\x7f\\\\]/', $probe) || str_starts_with($probe, '//')) { return null; }
            $decoded = rawurldecode($probe);
            if ($decoded === $probe) { break; }
            $probe = $decoded;
        }
        $base = parse_url($root);
        $parts = parse_url($url);
        if (!$parts || !$base || isset($parts['user']) || isset($parts['pass'])) { return null; }
        if (isset($parts['scheme']) || isset($parts['host'])) {
            if (!in_array($parts['scheme'] ?? '', ['https', 'http'], true)
                || strtolower($parts['host'] ?? '') !== strtolower($base['host'] ?? '')
                || ($parts['scheme'] ?? '') !== ($base['scheme'] ?? '')
                || ($parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80)) !== ($base['port'] ?? ($base['scheme'] === 'https' ? 443 : 80))) { return null; }
            return $url;
        }
        // Reject disguised schemes even if percent-encoded, and ambiguous path traversals.
        if (preg_match('~(^|/)\.\.(/|$)|^[^/?#]*:~', $probe)) { return null; }
        $origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
        return str_starts_with($url, '/') ? $origin . $url : rtrim($root, '/') . '/' . $url;
    }
}

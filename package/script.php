<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die;

/** Enable the integration on first installation; updates preserve plugin states. */
class pkg_identitymanagerInstallerScript
{
    public function preflight($type, $parent): bool
    {
        if ($type === 'uninstall') { return true; }
        return version_compare(JVERSION, '6.0.0', '>=') && version_compare(PHP_VERSION, '8.3.0', '>=') && extension_loaded('curl') && extension_loaded('openssl');
    }
    public function postflight($type, $parent): void
    {
        if ($type !== 'install') { return; }
        $db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
        foreach ([['system','identitymanager'], ['authentication','identitymanager'], ['identitymanager','google'], ['identitymanager','hybridauth']] as [$group,$element]) {
            $query = $db->createQuery()->update('#__extensions')->set('enabled = 1')->where('type = ' . $db->quote('plugin'))->where('folder = ' . $db->quote($group))->where('element = ' . $db->quote($element));
            $db->setQuery($query)->execute();
        }
    }
}

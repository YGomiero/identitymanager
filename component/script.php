<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die;

/** Upgrade the prototype schema without replacing providers, secrets or links. */
class com_identitymanagerInstallerScript
{
    public function preflight($type, $parent): bool
    {
        if ($type === 'uninstall') { return true; }
        return version_compare(JVERSION, '6.0.0', '>=') && version_compare(PHP_VERSION, '8.3.0', '>=') && extension_loaded('curl') && extension_loaded('openssl');
    }
    public function postflight($type, $parent): void
    {
        if ($type === 'uninstall') { return; }
        $db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
        $columns = $db->getTableColumns('#__identitymanager_oauth_codes');
        if (!isset($columns['code_challenge'])) {
            $db->setQuery("ALTER TABLE " . $db->quoteName('#__identitymanager_oauth_codes') . " ADD code_challenge VARCHAR(128) NOT NULL DEFAULT ''")->execute();
        }
    }
}

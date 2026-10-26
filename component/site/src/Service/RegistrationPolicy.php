<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Yann\Component\IdentityManager\Site\Service;
\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;

/** Joomla's registration switch is authoritative, including during OAuth callbacks. */
final class RegistrationPolicy
{
    public static function allowsCreation(): bool
    {
        return (bool) ComponentHelper::getParams('com_users')->get('allowUserRegistration', 0)
            && (bool) ComponentHelper::getParams('com_identitymanager')->get('auto_create_users', 0);
    }
}

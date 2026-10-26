<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$db = Factory::getContainer()->get('DatabaseDriver');
$query = $db->createQuery()
    ->select('a.*, u.username, u.name AS joomla_name')
    ->from($db->quoteName('#__identitymanager_accounts', 'a'))
    ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = a.user_id')
    ->order($db->quoteName('a.created_at') . ' DESC');
$db->setQuery($query);
$accounts = $db->loadObjectList();
$token = Session::getFormToken();
?>
<table class="table">
    <thead><tr><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_USER_9F8A2'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_PROVIDER_7CEEE'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_EXTERNAL_ID_997E2'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_EMAIL_84ADD'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_STATUS_BAE7D'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_LAST_LOGIN_43DAB'); ?></th><th></th></tr></thead>
    <tbody>
        <?php foreach ($accounts as $account) : ?>
            <tr>
                <td>
                    <?php if ($account->username) : ?>
                        <?php echo htmlspecialchars(($account->joomla_name ?: '') . ' (' . $account->username . ')', ENT_QUOTES, 'UTF-8'); ?>
                    <?php else : ?>
                        <span class="text-danger"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_MISSING_JOOMLA_USER_24A52'); ?></span>
                        <span class="text-muted">(<?php echo (int) $account->user_id; ?>)</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($account->provider_name, ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($account->provider_user_id, ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($account->email, ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int) $account->revoked ? \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_REVOKED_85F17') : \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ACTIVE_A733B'); ?></td>
                <td><?php echo htmlspecialchars((string) $account->last_login_at, ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <?php if (!(int) $account->revoked) : ?>
                        <a class="btn btn-sm btn-outline-danger" href="<?php echo Route::_('index.php?option=com_identitymanager&task=account.revoke&id=' . (int) $account->id . '&' . $token . '=1'); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_REVOKE_0BE72'); ?></a>
                    <?php endif; ?>
                    <?php if ((int) $account->revoked || !$account->username) : ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_identitymanager&task=account.delete&id=' . (int) $account->id . '&' . $token . '=1'); ?>" onclick="return confirm(<?php echo htmlspecialchars(json_encode(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_DELETE_THIS_LINKED_IDENTITY_83D62')), ENT_QUOTES, 'UTF-8'); ?>);"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_DELETE_F6FDB'); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

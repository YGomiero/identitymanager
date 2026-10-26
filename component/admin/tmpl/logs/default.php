<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$db = Factory::getContainer()->get('DatabaseDriver');
$query = $db->createQuery()->select('*')->from($db->quoteName('#__identitymanager_logs'))->order($db->quoteName('created_at') . ' DESC');
$db->setQuery($query, 0, 100);
$logs = $db->loadObjectList();
?>
<p><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_LOGS_HELP'); ?></p>
<?php if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_identitymanager')) : ?>
<div class="d-flex gap-2 mb-3">
    <form method="post" action="index.php?option=com_identitymanager&amp;task=logs.export">
        <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
        <button class="btn btn-primary" type="submit"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_LOGS_EXPORT'); ?></button>
    </form>
    <form method="post" action="index.php?option=com_identitymanager&amp;task=logs.clear" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_LOGS_CONFIRM')), ENT_QUOTES, 'UTF-8'); ?>)">
        <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
        <button class="btn btn-outline-danger" type="submit"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_LOGS_CLEAR'); ?></button>
    </form>
</div>
<?php endif; ?>
<table class="table">
    <thead><tr><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_DATE_EB9A4'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_EVENT_AD891'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_STATUS_BAE7D'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_USER_9F8A2'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_PROVIDER_7CEEE'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_MESSAGE_68F41'); ?></th><th>IP</th></tr></thead>
    <tbody>
        <?php foreach ($logs as $log) : ?>
            <tr>
                <td><?php echo htmlspecialchars($log->created_at, ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_LOG_' . strtoupper($log->event_type)), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_LOG_' . strtoupper($log->status)), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int) $log->user_id; ?></td>
                <td><?php echo htmlspecialchars($log->provider_name, ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_($log->message), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($log->ip_address, ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$db = Factory::getContainer()->get('DatabaseDriver');
$query = $db->createQuery()->select('*')->from($db->quoteName('#__identitymanager_clients'))->order($db->quoteName('name') . ' ASC');
$db->setQuery($query);
$clients = $db->loadObjectList();
$token = Session::getFormToken();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="m-0"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_CLIENT_APPLICATIONS_97045'); ?></h2>
    <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_identitymanager&task=client.add'); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_NEW_CLIENT_APPLICATION_29AE1'); ?></a>
</div>

<table class="table">
    <thead><tr><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_NAME_709A2'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_CLIENT_ID_A766C'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_STATUS_BAE7D'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_TRUSTED_99F7E'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_SCOPE_4651A'); ?></th><th class="text-end"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ACTIONS_C3CD6'); ?></th></tr></thead>
    <tbody>
        <?php foreach ($clients as $client) : ?>
            <tr>
                <td><a href="<?php echo Route::_('index.php?option=com_identitymanager&view=client&id=' . (int) $client->id); ?>"><?php echo htmlspecialchars($client->name, ENT_QUOTES, 'UTF-8'); ?></a></td>
                <td><code><?php echo htmlspecialchars($client->client_id, ENT_QUOTES, 'UTF-8'); ?></code></td>
                <td><?php echo (int) $client->enabled ? '<span class="badge bg-success">' . \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ENABLED_DF174') . '</span>' : '<span class="badge bg-secondary">' . \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_DISABLED_F4F44') . '</span>'; ?></td>
                <td><?php echo (int) $client->trusted ? \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_YES_5397E') : \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_NO_816C5'); ?></td>
                <td><?php echo htmlspecialchars($client->scope, ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo Route::_('index.php?option=com_identitymanager&view=client&id=' . (int) $client->id); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_EDIT_53016'); ?></a>
                    <a class="btn btn-sm btn-outline-danger" href="<?php echo Route::_('index.php?option=com_identitymanager&task=client.delete&id=' . (int) $client->id . '&' . $token . '=1'); ?>" onclick="return confirm(<?php echo htmlspecialchars(json_encode(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_DELETE_THIS_CLIENT_APPLICATION_9BAC7')), ENT_QUOTES, 'UTF-8'); ?>);"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_DELETE_F6FDB'); ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

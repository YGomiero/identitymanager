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
$query = $db->createQuery()
    ->select('p.*, COUNT(a.id) AS linked_count, SUM(CASE WHEN a.revoked = 0 THEN 1 ELSE 0 END) AS active_linked_count')
    ->from($db->quoteName('#__identitymanager_providers', 'p'))
    ->leftJoin($db->quoteName('#__identitymanager_accounts', 'a') . ' ON a.provider_id = p.id')
    ->group($db->quoteName('p.id'))
    ->order($db->quoteName('p.name') . ' ASC');
$db->setQuery($query);
$providers = $db->loadObjectList();
$token = Session::getFormToken();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="m-0"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_CONFIGURED_PROVIDERS_DAA17'); ?></h2>
    <div>
        <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_identitymanager&view=adapters'); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ADAPTERS_E07A4'); ?></a>
        <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_identitymanager&view=catalog'); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ADD_PROVIDER_51922'); ?></a>
    </div>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_NAME_709A2'); ?></th>
                <th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ALIAS_04259'); ?></th>
                <th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_TYPE_3DEB7'); ?></th>
                <th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_STATUS_BAE7D'); ?></th>
                <th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_LINKED_IDENTITIES_64A29'); ?></th>
                <th class="text-end"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ACTIONS_C3CD6'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($providers as $provider) : ?>
                <tr>
                    <td>
                        <a href="<?php echo Route::_('index.php?option=com_identitymanager&view=provider&id=' . (int) $provider->id); ?>">
                            <?php echo htmlspecialchars($provider->name, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </td>
                    <td><code><?php echo htmlspecialchars($provider->alias, ENT_QUOTES, 'UTF-8'); ?></code></td>
                    <td><?php echo htmlspecialchars(strtoupper($provider->type), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) $provider->enabled ? '<span class="badge bg-success">' . \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ENABLED_DF174') . '</span>' : '<span class="badge bg-secondary">' . \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_DISABLED_F4F44') . '</span>'; ?></td>
                    <td>
                        <?php echo (int) $provider->active_linked_count; ?> <?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_ACTIVE_COUNT'); ?>
                        <?php if ((int) $provider->linked_count > (int) $provider->active_linked_count) : ?>
                            <span class="text-muted">(<?php echo (int) $provider->linked_count - (int) $provider->active_linked_count; ?> <?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_REVOKED_COUNT'); ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo Route::_('index.php?option=com_identitymanager&view=provider&id=' . (int) $provider->id); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_EDIT_53016'); ?></a>
                        <?php if ((int) $provider->active_linked_count === 0) : ?>
                            <a class="btn btn-sm btn-outline-danger" href="<?php echo Route::_('index.php?option=com_identitymanager&task=provider.delete&id=' . (int) $provider->id . '&' . $token . '=1'); ?>" onclick="return confirm(<?php echo htmlspecialchars(json_encode(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_DELETE_THIS_PROVIDER_6F225')), ENT_QUOTES, 'UTF-8'); ?>);"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_DELETE_F6FDB'); ?></a>
                        <?php else : ?>
                            <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="<?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_REVOKE_ACTIVE_IDENTITIES_BEFORE_DELETING_7E285'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_DELETE_F6FDB'); ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

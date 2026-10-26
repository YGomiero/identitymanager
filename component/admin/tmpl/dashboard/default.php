<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

$db = Factory::getContainer()->get('DatabaseDriver');
$counts = [];
foreach (['providers' => '#__identitymanager_providers', 'accounts' => '#__identitymanager_accounts', 'logs' => '#__identitymanager_logs'] as $key => $table) {
    $query = $db->createQuery()->select('COUNT(*)')->from($db->quoteName($table));
    $db->setQuery($query);
    $counts[$key] = (int) $db->loadResult();
}
?>
<p class="alert alert-info"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_IDP_HINT'); ?></p>
<div class="row g-3">
    <div class="col-md-4"><div class="card"><div class="card-body"><h2><?php echo $counts['providers']; ?></h2><p><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_PROVIDERS_87B7C'); ?></p><a href="<?php echo Route::_('index.php?option=com_identitymanager&view=providers'); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_MANAGE_PROVIDERS_DCB19'); ?></a></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><h2><?php echo $counts['accounts']; ?></h2><p><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_LINKED_ACCOUNTS_E7CFC'); ?></p><a href="<?php echo Route::_('index.php?option=com_identitymanager&view=accounts'); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_REVIEW_IDENTITIES_A3438'); ?></a></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><h2><?php echo $counts['logs']; ?></h2><p><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_AUDIT_LOGS_676E5'); ?></p><a href="<?php echo Route::_('index.php?option=com_identitymanager&view=logs'); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_OPEN_LOGS_E1FC5'); ?></a></div></div></div>
</div>

<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Yann\Component\IdentityManager\Site\Service\ConnectionRemovalService;

$app = Factory::getApplication();
$user = $app->getIdentity();
if ($user->guest) {
    echo '<p>' . Text::_('COM_IDENTITYMANAGER_CONNECTIONS_LOGIN_REQUIRED') . '</p>';
    return;
}
try {
    $status = (new ConnectionRemovalService())->status($app->input->getInt('id'), (int) $user->id);
} catch (\RuntimeException $e) {
    echo '<p>' . Text::_('COM_IDENTITYMANAGER_ERROR_CONNECTION_NOT_FOUND') . '</p>';
    return;
}
$last = !$status['alternatives'];
$escape = static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
$connections = Route::_('index.php?option=com_identitymanager&view=connections');
?>
<div class="identitymanager-unlink">
    <h1><?php echo Text::sprintf('COM_IDENTITYMANAGER_UNLINK_TITLE', $escape($status['account']->provider_name)); ?></h1>
    <?php if ($last) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_IDENTITYMANAGER_UNLINK_LAST_HELP'); ?></div>
        <p><?php echo Text::_('COM_IDENTITYMANAGER_UNLINK_PASSWORD_HELP'); ?></p>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <?php if ($status['local_password_enabled']) : ?>
                <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_users&view=profile&layout=edit'); ?>"><?php echo Text::_('COM_IDENTITYMANAGER_SET_LOCAL_PASSWORD'); ?></a>
            <?php endif; ?>
            <a class="btn btn-outline-primary" href="<?php echo $connections; ?>#identitymanager-add-provider"><?php echo Text::_('COM_IDENTITYMANAGER_LINK_ANOTHER_IDENTITY'); ?></a>
        </div>
    <?php else : ?>
        <p><?php echo Text::_('COM_IDENTITYMANAGER_UNLINK_OTHER_AVAILABLE'); ?></p>
    <?php endif; ?>
    <?php if (!$last || $status['local_password_enabled']) : ?>
        <form method="post" action="<?php echo Route::_('index.php?option=com_identitymanager&task=auth.revoke'); ?>">
            <?php if ($last) : ?>
                <p><?php echo Text::sprintf('COM_IDENTITYMANAGER_LOCAL_USERNAME', $escape($user->username)); ?></p>
                <label for="identitymanager-local-password" class="form-label"><?php echo Text::_('COM_IDENTITYMANAGER_LOCAL_PASSWORD'); ?></label>
                <input id="identitymanager-local-password" class="form-control mb-3" type="password" name="local_password" autocomplete="current-password" required>
            <?php endif; ?>
            <input type="hidden" name="id" value="<?php echo (int) $status['account']->id; ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
            <button class="btn btn-outline-danger" type="submit"><?php echo Text::_('COM_IDENTITYMANAGER_CONFIRM_UNLINK'); ?></button>
            <a class="btn btn-link" href="<?php echo $connections; ?>"><?php echo Text::_('COM_IDENTITYMANAGER_UI_CANCEL_77DFD'); ?></a>
        </form>
    <?php else : ?>
        <p><?php echo Text::_('COM_IDENTITYMANAGER_LOCAL_PASSWORD_DISABLED'); ?></p>
        <a href="<?php echo $connections; ?>"><?php echo Text::_('COM_IDENTITYMANAGER_UI_CANCEL_77DFD'); ?></a>
    <?php endif; ?>
</div>

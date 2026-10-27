<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
$request = Factory::getApplication()->getSession()->get('identitymanager.consent');
if (!is_array($request) || $request['expires'] < time() || Factory::getApplication()->getIdentity()->guest) { echo Text::_('COM_IDENTITYMANAGER_ERROR_STATE_INVALID'); return; }
$scopes = explode(' ', (string) $request['scope']);
// Describe only the claims requested by the client; keep protocol names out of the public wording.
$scopeLabels = [
    'openid' => 'COM_IDENTITYMANAGER_CONSENT_IDENTITY',
    'profile' => 'COM_IDENTITYMANAGER_CONSENT_PROFILE',
    'email' => 'COM_IDENTITYMANAGER_CONSENT_EMAIL',
];
?>
<h1><?php echo Text::_('COM_IDENTITYMANAGER_CONSENT_TITLE'); ?></h1>
<p><?php echo Text::sprintf('COM_IDENTITYMANAGER_CONSENT_BODY', htmlspecialchars($request['client']['name'], ENT_QUOTES, 'UTF-8')); ?></p>
<ul>
    <?php foreach ($scopeLabels as $scope => $label) : ?>
        <?php if (in_array($scope, $scopes, true)) : ?>
            <li><?php echo Text::_($label); ?></li>
        <?php endif; ?>
    <?php endforeach; ?>
</ul>
<p><?php echo Text::_('COM_IDENTITYMANAGER_CONSENT_PASSWORD_PRIVATE'); ?></p>
<form method="post" action="<?php echo Route::_('index.php?option=com_identitymanager&task=idp.consent'); ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
    <button class="btn btn-primary" name="allow" value="1"><?php echo Text::_('COM_IDENTITYMANAGER_CONSENT_ALLOW'); ?></button>
    <button class="btn btn-secondary" name="allow" value="0"><?php echo Text::_('COM_IDENTITYMANAGER_CONSENT_DENY'); ?></button>
</form>

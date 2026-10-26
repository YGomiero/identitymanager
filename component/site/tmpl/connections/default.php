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
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$app = Factory::getApplication();
$user = $app->getIdentity();

if ($user->guest) {
    echo '<p>' . Text::_('COM_IDENTITYMANAGER_CONNECTIONS_LOGIN_REQUIRED') . '</p>';
    $return = base64_encode(\Joomla\CMS\Uri\Uri::root() . 'index.php?option=com_identitymanager&view=connections');
    echo '<p><a class="btn btn-primary" href="' . Route::_('index.php?option=com_users&view=login&return=' . rawurlencode($return)) . '">' . Text::_('COM_IDENTITYMANAGER_BACK_TO_LOGIN') . '</a></p>';
    return;
}

$params = ComponentHelper::getParams('com_identitymanager');
$title = trim((string) $params->get('connections_title', ''));
$intro = trim((string) $params->get('connections_intro', ''));

$db = Factory::getContainer()->get('DatabaseDriver');
$query = $db->createQuery()
    ->select('*')
    ->from($db->quoteName('#__identitymanager_accounts'))
    ->where($db->quoteName('user_id') . ' = ' . (int) $user->id)
    ->order($db->quoteName('created_at') . ' DESC');
$db->setQuery($query);
$accounts = $db->loadObjectList();

$query = $db->createQuery()
    ->select('*')
    ->from($db->quoteName('#__identitymanager_providers'))
    ->where($db->quoteName('enabled') . ' = 1')
    ->order($db->quoteName('name') . ' ASC');
$db->setQuery($query);
$providers = $db->loadObjectList();
$activeProviderIds = [];

foreach ($accounts as $account) {
    if (!(int) $account->revoked) {
        $activeProviderIds[(int) $account->provider_id] = true;
    }
}

$availableProviders = array_filter($providers, static function ($provider) use ($activeProviderIds) {
    return empty($activeProviderIds[(int) $provider->id]);
});
?>
<div class="identitymanager-connections">
    <h1><?php echo htmlspecialchars($title ?: Text::_('COM_IDENTITYMANAGER_CONNECTIONS_TITLE'), ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if ($intro !== '') : ?>
        <div class="identitymanager-connections__intro">
            <?php echo HTMLHelper::_('content.prepare', $intro); ?>
        </div>
    <?php endif; ?>
    <div class="identitymanager-list">
        <?php foreach ($accounts as $account) : ?>
            <div class="identitymanager-account">
                <div>
                    <strong><?php echo htmlspecialchars($account->provider_name, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                    <span><?php echo htmlspecialchars($account->email ?: $account->display_name, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ((int) $account->revoked) : ?>
                        <span class="badge bg-warning text-dark"><?php echo Text::_('COM_IDENTITYMANAGER_ACCOUNT_REVOKED'); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!(int) $account->revoked) : ?>
                    <a class="btn btn-outline-danger" href="<?php echo Route::_('index.php?option=com_identitymanager&view=unlink&id=' . (int) $account->id); ?>"><?php echo Text::_('COM_IDENTITYMANAGER_REVOKE'); ?></a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="identitymanager-social" id="identitymanager-add-provider">
        <p class="identitymanager-social__title"><?php echo Text::_('COM_IDENTITYMANAGER_LINK_ANOTHER_IDENTITY'); ?></p>
        <?php if ($availableProviders) : ?>
            <div class="identitymanager-social__buttons">
                <?php foreach ($availableProviders as $provider) : ?>
                    <a class="identitymanager-button" href="<?php echo Route::_('index.php?option=com_identitymanager&task=auth.start&provider=' . rawurlencode($provider->alias)); ?>">
                        <?php echo \Yann\Component\IdentityManager\Site\Service\ProviderIcon::render((array) $provider); ?>
                        <?php echo Text::sprintf('COM_IDENTITYMANAGER_CONTINUE_WITH_PROVIDER', htmlspecialchars($provider->name, ENT_QUOTES, 'UTF-8')); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="text-muted"><?php echo Text::_('COM_IDENTITYMANAGER_NO_MORE_PROVIDERS'); ?></p>
        <?php endif; ?>
    </div>
</div>

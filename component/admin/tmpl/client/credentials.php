<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$app = Factory::getApplication();
if (!$app->getIdentity()->authorise('core.admin', 'com_identitymanager')) {
    throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
}
$app->setHeader('Cache-Control', 'no-store, private', true);
$app->setHeader('Referrer-Policy', 'no-referrer', true);
$data = $app->getSession()->get('identitymanager.client.created');
$app->getSession()->clear('identitymanager.client.created');
if (!is_array($data) || $data['expires'] <= time() || $data['user_id'] !== (int) $app->getIdentity()->id) {
    echo '<p>' . Text::_('COM_IDENTITYMANAGER_CREDENTIALS_EXPIRED') . '</p>';
    return;
}
$escape = static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
?>
<div class="card"><div class="card-body">
    <h2><?php echo Text::_('COM_IDENTITYMANAGER_CREDENTIALS_TITLE'); ?></h2>
    <p><?php echo Text::_('COM_IDENTITYMANAGER_CREDENTIALS_HELP'); ?></p>
    <div class="alert alert-warning"><?php echo Text::_('COM_IDENTITYMANAGER_CREDENTIALS_ONCE'); ?></div>
    <?php foreach (['site' => ['COM_IDENTITYMANAGER_JOOMLA_SITE_URL', rtrim(Uri::root(), '/')], 'client' => ['COM_IDENTITYMANAGER_UI_CLIENT_ID_A766C', $data['client_id']], 'secret' => ['COM_IDENTITYMANAGER_UI_CLIENT_SECRET_4B468', $data['secret']]] as $key => $field) : ?>
        <label class="form-label" for="im-created-<?php echo $key; ?>"><?php echo Text::_($field[0]); ?></label>
        <input id="im-created-<?php echo $key; ?>" class="form-control mb-3" readonly autocomplete="off" value="<?php echo $escape($field[1]); ?>">
    <?php endforeach; ?>
    <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_identitymanager&view=clients'); ?>"><?php echo Text::_('COM_IDENTITYMANAGER_CREDENTIALS_DONE'); ?></a>
</div></div>

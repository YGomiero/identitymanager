<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$escape = static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
$callback = Uri::root() . 'index.php?option=com_identitymanager&task=auth.callback&provider=' . rawurlencode($provider->alias);
?>
<form method="post" action="<?php echo Route::_('index.php?option=com_identitymanager&task=provider.save'); ?>">
    <div class="card">
        <div class="card-header"><h2 class="h4 mb-0"><?php echo Text::_('COM_IDENTITYMANAGER_JOOMLA_CONNECT'); ?></h2></div>
        <div class="card-body">
            <p><?php echo Text::_('COM_IDENTITYMANAGER_JOOMLA_INTRO'); ?></p>
            <ol class="mb-4">
                <li><?php echo Text::_('COM_IDENTITYMANAGER_JOOMLA_STEP_MASTER'); ?></li>
                <li><?php echo Text::_('COM_IDENTITYMANAGER_JOOMLA_STEP_CALLBACK'); ?></li>
                <li><?php echo Text::_('COM_IDENTITYMANAGER_JOOMLA_STEP_CREDENTIALS'); ?></li>
            </ol>
            <label class="form-label" for="im-callback"><?php echo Text::_('COM_IDENTITYMANAGER_CALLBACK_URL'); ?></label>
            <input class="form-control mb-4" id="im-callback" readonly value="<?php echo $escape($callback); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="im-joomla-site"><?php echo Text::_('COM_IDENTITYMANAGER_JOOMLA_SITE_URL'); ?></label>
                    <input class="form-control" id="im-joomla-site" name="joomla_site" type="url" required placeholder="https://site.example" value="<?php echo $escape($joomlaSite ?? ''); ?>">
                    <div class="form-text"><?php echo Text::_('COM_IDENTITYMANAGER_JOOMLA_SITE_HELP'); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="im-joomla-name"><?php echo Text::_('COM_IDENTITYMANAGER_UI_DISPLAY_NAME_C7874'); ?></label>
                    <input class="form-control" id="im-joomla-name" name="name" required value="<?php echo $escape($provider->name); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="im-joomla-client"><?php echo Text::_('COM_IDENTITYMANAGER_UI_CLIENT_ID_A766C'); ?></label>
                    <input class="form-control" id="im-joomla-client" name="client_id" required autocomplete="off" value="<?php echo $escape($provider->client_id); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="im-joomla-secret"><?php echo Text::_('COM_IDENTITYMANAGER_UI_CLIENT_SECRET_4B468'); ?></label>
                    <input class="form-control" id="im-joomla-secret" name="client_secret" type="password" autocomplete="new-password" minlength="32" <?php echo $provider->id ? '' : 'required'; ?>>
                    <?php if ($provider->id) : ?><div class="form-text"><?php echo Text::_('COM_IDENTITYMANAGER_UI_LEAVE_BLANK_TO_KEEP_EXISTING_SECRET_3876C'); ?></div><?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="im-joomla-icon"><?php echo Text::_('COM_IDENTITYMANAGER_ICON_URL'); ?></label>
                    <input class="form-control" id="im-joomla-icon" name="icon_url" value="<?php echo $escape($options['icon_url'] ?? ''); ?>">
                    <div class="form-text"><?php echo Text::_('COM_IDENTITYMANAGER_ICON_HELP'); ?></div>
                </div>
                <div class="col-12"><label class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="enabled" value="1" <?php echo $provider->enabled ? 'checked' : ''; ?>>
                    <span class="form-check-label"><?php echo Text::_('COM_IDENTITYMANAGER_UI_ENABLED_DF174'); ?></span>
                </label></div>
            </div>
            <p class="form-text mt-3"><?php echo Text::_('COM_IDENTITYMANAGER_JOOMLA_AUTOMATIC'); ?></p>
            <?php if ($provider->id) : ?>
                <a href="<?php echo Route::_('index.php?option=com_identitymanager&view=provider&mode=manual&id=' . (int) $provider->id); ?>"><?php echo Text::_('COM_IDENTITYMANAGER_UI_ADVANCED_PROVIDER_SETTINGS_226F3'); ?></a>
            <?php endif; ?>
        </div>
        <div class="card-footer d-flex gap-2">
            <input type="hidden" name="id" value="<?php echo (int) $provider->id; ?>">
            <input type="hidden" name="alias" value="<?php echo $escape($provider->alias); ?>">
            <input type="hidden" name="setup" value="joomla">
            <?php echo HTMLHelper::_('form.token'); ?>
            <button class="btn btn-primary" type="submit"><?php echo Text::_('COM_IDENTITYMANAGER_UI_SAVE_PROVIDER_BDF21'); ?></button>
            <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_identitymanager&view=providers'); ?>"><?php echo Text::_('COM_IDENTITYMANAGER_UI_CANCEL_77DFD'); ?></a>
        </div>
    </div>
</form>

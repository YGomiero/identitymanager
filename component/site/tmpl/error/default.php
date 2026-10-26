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

$message = Factory::getApplication()->input->get('message', '', 'raw');
$translated = str_starts_with($message, 'COM_IDENTITYMANAGER_') ? Text::_($message) : $message;
?>
<div class="com-identitymanager-error">
    <h1><?php echo Text::_('COM_IDENTITYMANAGER_ERROR_TITLE'); ?></h1>
    <p><?php echo htmlspecialchars($translated, ENT_QUOTES, 'UTF-8'); ?></p>
    <p><a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_users&view=login'); ?>"><?php echo Text::_('COM_IDENTITYMANAGER_BACK_TO_LOGIN'); ?></a></p>
</div>

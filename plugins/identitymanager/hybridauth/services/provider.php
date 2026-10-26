<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Yann\Plugin\IdentityManager\Hybridauth\Extension\Hybridauth;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (Container $container) {
            $plugin = new Hybridauth(
                $container->get(DispatcherInterface::class),
                (array) PluginHelper::getPlugin('identitymanager', 'hybridauth')
            );
            $plugin->setApplication(Factory::getApplication());

            return $plugin;
        });
    }
};

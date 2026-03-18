<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Console.updatefromcli
 *
 * @copyright   Copyright (C) 2026 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Console\Updatefromcli\Extension\UpdatefromcliConsolePlugin;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                // Get plugin configuration from database
                $config = (array) PluginHelper::getPlugin('console', 'updatefromcli');

                // Get event dispatcher for plugin events
                $subject = $container->get(DispatcherInterface::class);

                // Create plugin instance with dependencies
                $plugin = new UpdatefromcliConsolePlugin($subject, $config);
                $plugin->setApplication(Factory::getApplication());
                return $plugin;
            }
        );
    }
};

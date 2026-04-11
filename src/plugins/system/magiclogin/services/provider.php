<?php

/**
 * @package    Joomla.Plugin
 * @subpackage System.magiclogin
 *
 * @author     Alikon <alikon@alikonweb.it>
 *
 * @copyright  (C) 2025, Alikonweb <https://www.alikonweb.it>. All rights reserved.
 * @license    GNU General Public License version 3 or later; see LICENSE
 * @link       https://www.alikonweb.it
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\System\MagicLogin\Extension\MagicLogin;

/**
 * Service provider for the Magic Login system plugin
 *
 * This service provider registers the MagicLogin plugin with Joomla's dependency injection container,
 * configuring all necessary dependencies including the application, database, and event dispatcher.
 *
 * @since  1.0.0
 */
return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * This method configures the MagicLogin plugin instance with all required dependencies:
     * - Event dispatcher for handling Joomla events
     * - Plugin configuration from the database
     * - Application instance for request handling
     * - Database driver for token storage
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                // Get plugin configuration from database
                $config = (array) PluginHelper::getPlugin('system', 'magiclogin');

                // Get event dispatcher for plugin events
                $subject = $container->get(DispatcherInterface::class);

                // Create plugin instance with dependencies
                $plugin = new MagicLogin($subject, $config);
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get('DatabaseDriver'));

                return $plugin;
            }
        );
    }
};

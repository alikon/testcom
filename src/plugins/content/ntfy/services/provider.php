<?php

\defined('_JEXEC') or die;

use Alikonweb\Plugin\Content\Ntfy\Extension\Ntfy;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                // Get plugin configuration from database
                $config = (array) PluginHelper::getPlugin('content', 'ntfy');

                // Get event dispatcher for plugin events
                $subject = $container->get(DispatcherInterface::class);

                // Create plugin instance with dependencies
                $plugin = new Ntfy($subject, $config);
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get('DatabaseDriver'));
                $plugin->setUserFactory($container->get(UserFactoryInterface::class));

                return $plugin;
            }
        );
    }
};

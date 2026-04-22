<?php

/**
 * @package     Joomla.Module
 * @subpackage  Module.changelog
 *
 * @copyright   Copyright (C) 2026 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\Service\Provider\HelperFactory;
use Joomla\CMS\Extension\Service\Provider\Module;
use Joomla\CMS\Extension\Service\Provider\ModuleDispatcherFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new ModuleDispatcherFactory('\\Alikonweb\\Module\\Changelog'));
        $container->registerServiceProvider(new HelperFactory('\\Alikonweb\\Module\\Changelog\\Site\\Helper'));
        $container->registerServiceProvider(new Module());
    }
};

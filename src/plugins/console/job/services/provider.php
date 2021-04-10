<?php
/**
 * @package   job
 * @copyright Copyright (C)2021 Alikon
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;


use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Console\Job\Extension\Job;

return new class implements ServiceProviderInterface
{
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	public function register(Container $container)
	{
		$container->set(
			PluginInterface::class,
			function (Container $container)
			{
				$plugin = PluginHelper::getPlugin('console', 'job');

				return new Job(
					$container->get(DispatcherInterface::class),
					(array) $plugin
				);
			}
		);
	}
};

<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_jobs
 *
 * @copyright   Copyright (C) 2020 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;

/**
 * Class Com_JobsInstallerScript
 *
 * @since  4.0.0
 */
class Com_JobsInstallerScript extends InstallerScript
{
	/**
	 * The extension name. This should be set in the installer script.
	 *
	 * @var    string
	 * @since  4.0.0
	 */
	protected $extension = 'com_jobs';
	/**
	 * Minimum PHP version required to install the extension
	 *
	 * @var    string
	 * @since  4.0.0
	 */
	protected $minimumPhp = '7.2.5';
	/**
	 * Minimum Joomla! version required to install the extension
	 *
	 * @var    string
	 * @since  4.0.0
	 */
	protected $minimumJoomla = '4.0.0-dev';
	/**
	 * @var  Joomla\CMS\Application\CMSApplication  Holds the application object
	 *
	 * @since ?
	 */
	private $app;


	/**
	 *  Constructor
	 *
	 * @since ?
	 */
	public function __construct()
	{
		$this->app = Factory::getApplication();
	}

	/**
	 * method to run before an install/update/uninstall method
	 *
	 * @param   string                                        $type   'install', 'update' or 'discover_install'
	 * @param   Joomla\CMS\Installer\Adapter\ComponentAdapter $parent Installerobject
	 *
	 * @return  boolean  false will terminate the installation
	 *
	 * @since ?
	 */
	public function preflight($type, $parent)
	{
	}

	/**
	 * Method to install the component
	 *
	 * @param   Joomla\CMS\Installer\Adapter\ComponentAdapter $parent Installerobject
	 *
	 * @return void
	 *
	 * @since ?
	 */
	public function install($parent)
	{
	}

	/**
	 * Method to uninstall the component
	 *
	 * @param   Joomla\CMS\Installer\Adapter\ComponentAdapter $parent Installerobject
	 *
	 * @return void
	 *
	 * @since ?
	 */
	public function uninstall($parent)
	{
	}

	/**
	 * method to update the component
	 *
	 * @param   Joomla\CMS\Installer\Adapter\ComponentAdapter $parent Installerobject
	 *
	 * @return void
	 *
	 * @since ?
	 */
	public function update($parent)
	{
	}

	/**
	 * method to run after an install/update/uninstall method
	 *
	 * @param   string                                        $type   'install', 'update' or 'discover_install'
	 * @param   Joomla\CMS\Installer\Adapter\ComponentAdapter $parent Installerobject
	 *
	 * @return void
	 *
	 * @since ?
	 */
	public function postflight($type, $parent)
	{
	}
}

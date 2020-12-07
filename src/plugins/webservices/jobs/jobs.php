<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Webservices.Jobs
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\ApiRouter;
use Joomla\Router\Route;

/**
 * Web Services adapter for com_jobs.
 *
 * @since  4.0.0
 */
class PlgWebservicesJobs extends CMSPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  4.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Allowed verbs
	 *
	 * @var    array
	 * @since  4.0.0
	 */
	protected $allowedVerbs = [];

	/**
	 * Allow public GET .
	 *
	 * @var    boolean
	 * @since  4.0.0
	 */
	protected $allowPublic = false;

	/**
	 * Constructor.
	 *
	 * @param   object  $subject  The object to observe.
	 * @param   array   $config   An optional associative array of configuration settings.
	 *
	 * @since  4.0.0
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		$this->allowedVerbs = $this->params->get('restverbs', []);
		$this->allowPublic  = $this->params->get('public', false);
	}

	/**
	 * Registers com_installer's API's routes in the application
	 *
	 * @param   ApiRouter       $router  The API Routing object
	 * @param   ApiApplication  $object  The API Application object
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	public function onBeforeApiRoute(&$router, $object)
	{
		if (!in_array($object->input->getMethod(), $this->allowedVerbs))
		{
			return;
		}

		$defaults    = ['component' => 'com_jobs', 'public' => $this->allowPublic];

		$routes = [
			new Route(['GET'], 'v1/jobs', 'jobs.displayList', [], $defaults),
			//new Route(['GET'], 'v1/jobs/start/:id', 'jobs.start', ['id' => '(\d+)'], $defaults)
			new Route(['GET'], 'v1/jobs/start/:id', 'jobs.executeTask', ['id' => '(\d+)'], $defaults)
		];

		$router->addRoutes($routes);
	}
}

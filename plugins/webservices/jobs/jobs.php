<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Webservices.Jobs
 *
 * @copyright   Copyright (C) 2020 Alikon, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\ApiRouter;
use Joomla\Router\Route;
use Joomla\Registry\Registry;


/**
 * Web Services adapter for com_jobs.
 *
 * @since  4.0.0
 */
class PlgWebservicesJobs extends CMSPlugin
{
	/**
	 * Database object.
	 *
	 * @var    DatabaseDriver
	 * @since  __DEPLOY_VERSION__
	 */
	protected $db;

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
		$this->allowPublic  = $this->params->get('public', true);
		$this->limit        = $this->params->get('limit', 0);
		$this->taskid       = $this->params->get('taskid', 0);
	}

	/**
	 * Registers com_jobs API's routes in the application
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
		// qui

		$defaults    = ['component' => 'com_jobs', 'public' => $this->allowPublic];

		$routes = [
			new Route(['GET'], 'v1/jobs', 'jobs.displayList', [], $defaults),
			new Route(['GET'], 'v1/jobs/start', 'jobs.executeTask', [], $defaults),
			new Route(['GET'], 'v1/jobs/:name/start', 'jobs.executeTask', ['name' => '(\w+)'], $defaults),
			new Route(['POST'], 'v1/jobs/login', 'jobs.credentials', [], $defaults),
		];

		$router->addRoutes($routes);
	}
}

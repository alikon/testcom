<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Job.startafriend
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\Component\Jobs\Administrator\Jobs\JobsPlugin;
/**
 * Joomla! Job One plugin
 *
 * An example for a job plugin
 *
 * @since  __DEPLOY_VERSION__
 */
class PlgJobStartafriend extends JobsPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  __DEPLOY_VERSION__
	 */
	protected $autoloadLanguage = true;

	/**
	 * Application object.
	 *
	 * @var    ApplicationCms
	 * @since  __DEPLOY_VERSION__
	 */
	protected $app;

	/**
	 * Database object.
	 *
	 * @var    DatabaseDriver
	 * @since  __DEPLOY_VERSION__
	 */
	protected $db;

	/**
	 * Status for the process
	 *
	 * @var    array
	 * @since  __DEPLOY_VERSION__
	 */
	protected $snapshot;

	/**
	 * The log check and rotation code event.
	 *
	 * @param   boolean  $options  The plugin options
	 *
	 * @return  array  status of the execution
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onExecuteScheduledTask($options = false) : array
	{
		$this->snapshot['startTime'] = microtime(true);

		// Pseudo Lock
		if (!$this->acquireLock($this->_name, $this->_type, $this->params, $options))
		{
			return $this->snapshot;
		}

		// Execute the job startAFriend task
		try
		{
			$this->startAFriendTask();
		}
		catch (\Exception $e)
		{
			$this->snapshot['status'] = self::JOB_KO_RUN;
		}

		// Update job execution data
		$this->releaseLock($this->_name, $this->_type);

		return $this->snapshot;
	}

	/**
	 * Start the scheduler on a friend site.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function startAFriendTask()
	{
		$content = [];
		$options = new Registry;
		$options->set('Content-Type', 'application/json');
		
		$headers = array('Authorization' => 'Bearer ' . $this->params->get('key'));

		// Don't let the request take longer than 120 seconds to avoid page timeout issues
		try
		{
			$response = HttpFactory::getHttp($options)->get($this->params->get('url'), $headers, 120);
		}
		catch (\Exception $e)
		{
			$this->snapshot['status'] = self::JOB_KO_RUN;
		}
		return $response;
	}

}

<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Job.jobthree
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\Component\Jobs\Administrator\Jobs\JobsPlugin;
/**
 * Joomla! Job Three plugin
 *
 * An example for a job plugin
 *
 * @since  __DEPLOY_VERSION__
 */
class PlgJobJobthree extends JobsPlugin
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
		$eid = PluginHelper::getPlugin($this->_type, $this->_name);

		// Pseudo Lock
		if (!$this->acquireLock($this->_name, $this->_type, $this->params, $options, $eid))
		{
			return $this->snapshot;
		}

		// Execute the job THREE task
		try
		{
			$this->jobthreeTask();
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
	 * The log check and rotation code event.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function jobthreeTask()
	{
		$sleepSenconds = 1000000 * $this->params->get('sleep', 1);

		// Sleep for sleep seconds
		usleep($sleepSenconds);

		if ($this->params->get('simulate', 0))
		{
			// Simulate error
			throw new RuntimeException('Test the failure: ');
		}
	}

}

<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Job.expiredconsent
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
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
class PlgJobExpiredconsent extends JobsPlugin
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

		// Execute the job consent expiration task
		try
		{
			$this->consentExpirationTask();
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
	public function consentExpirationTask()
	{
		// Load the parameters.
		$expire = (int) $this->params->get('consentexpiration', 365);
		$now    = Factory::getDate()->toSql();
		$period = '-' . $expire;
		$db     = $this->db;
		$query  = $db->getQuery(true);

		$query->select($db->quoteName(['id', 'user_id']))
			->from($db->quoteName('#__privacy_consents'))
			->where($query->dateAdd($db->quote($now), $period, 'DAY') . ' > ' . $db->quoteName('created'))
			->where($db->quoteName('subject') . ' = ' . $db->quote('PLG_SYSTEM_PRIVACYCONSENT_SUBJECT'))
			->where($db->quoteName('state') . ' = 1');

		$db->setQuery($query);

		try
		{
			$users = $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			return false;
		}

		// Do not process further if no expired consents found
		if (empty($users))
		{
			return true;
		}

		// Push a notification to the site's super users
		/** @var MessageModel $messageModel */
		$messageModel = $this->app->bootComponent('com_messages')->getMVCFactory()->createModel('Message', 'Administrator');

		foreach ($users as $user)
		{
			$userId = (int) $user->id;
			$query = $db->getQuery(true)
				->update($db->quoteName('#__privacy_consents'))
				->set($db->quoteName('state') . ' = 0')
				->where($db->quoteName('id') . ' = :userid')
				->bind(':userid', $userId, ParameterType::INTEGER);
			$db->setQuery($query);

			try
			{
				$db->execute();
			}
			catch (RuntimeException $e)
			{
				return false;
			}

			$messageModel->notifySuperUsers(
				Text::_('PLG_SYSTEM_PRIVACYCONSENT_NOTIFICATION_USER_PRIVACY_EXPIRED_SUBJECT'),
				Text::sprintf('PLG_SYSTEM_PRIVACYCONSENT_NOTIFICATION_USER_PRIVACY_EXPIRED_MESSAGE', Factory::getUser($user->user_id)->username)
			);
		}

		return true;
	}

}

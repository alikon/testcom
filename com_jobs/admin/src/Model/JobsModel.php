<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_jobs
 *
 * @copyright   Copyright (C) 2020 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jobs\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\ParameterType;
use Joomla\CMS\Access\Exception\AuthenticationFailed;
use Joomla\CMS\User\UserHelper;
use Joomla\CMS\User\User;

/**
 * Methods supporting a list of jobs.
 *
 * @since  __DEPLOY_VERSION__
 */
class JobsModel extends ListModel
{
	/**
	 * Constructor.
	 *
	 * @param   array                $config   An optional associative array of configuration settings.
	 * @param   MVCFactoryInterface  $factory  The factory.
	 *
	 * @see     \JControllerLegacy
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct($config = array(), MVCFactoryInterface $factory = null)
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id', 'a.id',
				'exitcode', 'a.exitcode',
				'duration', 'a.duration',
				'taskname', 'a.taskname',
				'jobid', 'a.jobid',
				'taskid', 'a.taskid',
				'lastdate', 'a.lastdate',
				'nextdate', 'a.nextdate',
				'exitcode',
			);
		}

		parent::__construct($config, $factory);
	}

	/**
	 * Removes all of the jobs from the table.
	 *
	 * @return  boolean result of operation
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function purge()
	{
		try
		{
			$this->getDbo()->truncateTable('#__tasks');
		}
		catch (\Exception $e)
		{
			return false;
		}

		return true;
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function populateState($ordering = 'a.lastdate', $direction = 'desc')
	{
		// Load the parameters.
		$params = ComponentHelper::getParams('com_jobs');
		$this->setState('params', $params);

		// List state information.
		parent::populateState($ordering, $direction);
	}

	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param   string  $id  A prefix for the store id.
	 *
	 * @return  string  A store id.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.state');
		$id .= ':' . $this->getState('filter.exitcode');

		return parent::getStoreId($id);
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return  \JDatabaseQuery
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select(
			$this->getState(
				'list.select',
				'a.*'
			)
		);
		$query->from($db->quoteName('#__tasks', 'a'));

		// Filter the items over the exit code.
		$exitCode = $this->getState('filter.exitcode');

		if (is_numeric($exitCode))
		{
			$exitCode = (int) $exitCode;
			if ($exitCode >=0)
			{
				$query->where($db->quoteName('a.exitcode') . ' = :exitcode')
					->bind(':exitcode', $exitCode, ParameterType::INTEGER);
			}
			else
			{
				$query->whereNotIn($db->quoteName('a.exitcode'), [0, 123], ParameterType::INTEGER);
			}
		}

		// Filter the items over the search string if set.
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$ids = (int) substr($search, 3);
				$query->where($db->quoteName('a.id') . ' = :id');
				$query->bind(':id', $ids, ParameterType::INTEGER);
			}
			else
			{
				$search = '%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%');
				$query->where($db->quoteName('taskname') . ' LIKE :taskname')
					->bind(':taskname', $search);
			}
		}

		// Add the list ordering clause.
		$query->order($db->escape($this->getState('list.ordering', 'a.lastdate')) . ' ' . $db->escape($this->getState('list.direction', 'DESC')));

		return $query;
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return  \JDatabaseQuery
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addJob($info)
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		$created = Factory::getDate()->toSql();

		$columns = [
		'taskname',
		'duration',
		'jobid',
		'taskid',
		'exitcode',
		'lastdate',
		'nextdate',
		];

		$values = [
		':taskname',
		':duration',
		':jobid',
		':taskid',
		':exitcode',
		':lastdate',
		':nextdate',
		];

		$query
			->insert($db->quoteName('#__tasks'), false)
			->columns($db->quoteName($columns))
			->values(implode(', ', $values))
			->bind(':taskname', $info['job'])
			->bind(':duration', $info['duration'], ParameterType::INTEGER)
			->bind(':jobid', $info['eid'], ParameterType::INTEGER)
			->bind(':taskid', $info['runned'], ParameterType::INTEGER)
			->bind(':exitcode', $info['status'], ParameterType::INTEGER)
			->bind(':lastdate', $created)
			->bind(':nextdate', $info['nextrun']);

		$db->setQuery($query);
		$db->execute();
	}

	/**
	 * Method to delete rows.
	 *
	 * @return  boolean  Returns true on success, false on failure.
	 */
	public function delete($pks)
	{
		$user       = Factory::getUser();

		$allow = $user->authorise('core.delete', 'com_jobs');

		if ($allow)
		{
			// Delete jobs from list
			$db    = $this->getDbo();
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__tasks'))
				->whereIn($db->quoteName('id'), $pks);

			$db->setQuery($query);
			$this->setError((string) $query);

			try
			{
				$db->execute();
			}
			catch (\RuntimeException $e)
			{
				$this->setError($e->getMessage());

				return false;
			}
		}
		else
		{
			Factory::getApplication()->enqueueMessage(Text::_('JERROR_CORE_DELETE_NOT_PERMITTED'), 'error');
		}

		return true;
	}

	/**
	 * Method to delete rows.
	 *
	 * @return  boolean  Returns true on success, false on failure.
	 */
	public function start($jobs = [] ): array
	{
		if (empty($jobs))
		{
			// The job plugin group
			PluginHelper::importPlugin('job');
		}

		foreach ($jobs as $job)
		{
			// The job plugin group
			PluginHelper::importPlugin('job', $job);
		}

		PluginHelper::importPlugin('actionlog');

		// Trigger the ExecuteTask event
		$results = Factory::getApplication()->triggerEvent('onExecuteScheduledTask', ['force' => false]);

		foreach ($results as $result)
		{
			Factory::getApplication()->triggerEvent('onAfterScheduledTask', [$result]);
		}

		return $results;
	}

	public function credentials($credentials = [] ): array
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'password', 'profile_value']))
			->from($db->quoteName('#__users'))
			->join(
				'INNER',
				$db->quoteName('#__user_profiles', 'p'),
				$db->quoteName('id') . ' = ' . $db->quoteName('p.user_id')
			)
			->where($db->quoteName('username') . ' = :username')
			->where($db->quoteName('p.profile_key') . ' = ' . $db->quote('joomlatoken.token'))
			->bind(':username', $credentials['username']);

		$db->setQuery($query);
		$result = $db->loadObject();
		
		if ($result)
		{
			$match = UserHelper::verifyPassword($credentials['password'], $result->password, $result->id);

			if ($match === true)
			{
				$user = User::getInstance($result->id);
				
				//return [$user->id];
				//return [$result->profile_value];
				return [$this->getTokenForDisplay($user->id, $result->profile_value, 'sha256')];
			}
			else
			{
				throw new AuthenticationFailed;
			}
		}
	
		throw new AuthenticationFailed;
	}

	/**
	 * Returns the token formatted suitably for the user to copy.
	 *
	 * @param   integer  $userId     The user id for token
	 * @param   string   $tokenSeed  The token seed data stored in the database
	 * @param   string   $algorithm  The hashing algorithm to use for the token (default: sha256)
	 *
	 * @return  string
	 * @since   4.0.0
	 */
	private function getTokenForDisplay(int $userId, string $tokenSeed,
		string $algorithm = 'sha256'
	): string
	{
		if (empty($tokenSeed))
		{
			return '';
		}

		try
		{
			$siteSecret = Factory::getApplication()->get('secret');
		}
		catch (\Exception $e)
		{
			$siteSecret = '';
		}

		// NO site secret? You monster!
		if (empty($siteSecret))
		{
			return '';
		}

		$rawToken  = base64_decode($tokenSeed);
		$tokenHash = hash_hmac($algorithm, $rawToken, $siteSecret);
		$message   = base64_encode("$algorithm:$userId:$tokenHash");

		return $message;
	}

}

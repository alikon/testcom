<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_jobs
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jobs\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Executed task Table for Jobs.
 *
 * @since  __DEPLOY_VERSION__
 */
class TaskTable extends Table
{
	/**
	 * Constructor
	 *
	 * @param   DatabaseDriver  $db  Database object.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(DatabaseDriver $db)
	{
		parent::__construct('#__tasks', 'id', $db);
	}

	/**
	 * Overloaded check function
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function check()
	{
		try
		{
			parent::check();
		}
		catch (\Exception $e)
		{
			$this->setError($e->getMessage());

			return false;
		}

		return true;
	}

	/**
	 * Overridden store method to set dates.
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function store($updateNulls = false)
	{
		return parent::store($updateNulls);
	}
}

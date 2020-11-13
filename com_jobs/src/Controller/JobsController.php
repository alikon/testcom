<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_jobs
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jobs\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\Utilities\ArrayHelper;

/**
 * Redirect link list controller class.
 *
 * @since  1.6
 */
class JobsController extends AdminController
{
	/**
	 * Proxy for getModel.
	 *
	 * @param   string  $name    The name of the model.
	 * @param   string  $prefix  The prefix of the model.
	 * @param   array   $config  An array of settings.
	 *
	 * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel The model instance
	 *
	 * @since   1.6
	 */
	public function getModel($name = 'Jobs', $prefix = 'Administrator', $config = array('ignore_request' => true))
	{
		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * Clean out the unpublished links.
	 *
	 * @return  void
	 *
	 * @since   3.5
	 */
	public function purge()
	{
		// Check for request forgeries.
		$this->checkToken();

		$model = $this->getModel('Jobs');

		if ($model->purge())
		{
			$message = Text::_('COM_JOBS_CLEAR_SUCCESS');
		}
		else
		{
			$message = Text::_('COM_JOBS_CLEAR_FAIL');
		}

		$this->setRedirect('index.php?option=com_jobs&view=jobs', $message);
	}

	/**
	 * Removes an item.
	 *
	 * Overrides Joomla\CMS\MVC\Controller\FormController::delete to check the core.admin permission.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function delete()
	{

		$ids = $this->input->get('cid', array(), 'array');

		if (!$this->app->getIdentity()->authorise('core.admin', $this->option))
		{
			throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
		elseif (empty($ids))
		{
			$this->setMessage(Text::_('COM_USERS_NO_LEVELS_SELECTED'), 'warning');
		}
		else
		{
			// Get the model.
			$model = $this->getModel();

			$ids = ArrayHelper::toInteger($ids);

			// Remove the items.
			if ($model->delete($ids))
			{
				$this->setMessage(Text::plural('COM_JOBS_N_ITEMS_DELETED', count($ids)));
			}
		}

		$this->setRedirect('index.php?option=com_jobs&view=jobs');
	}

	/**
	 * Method to toggle the featured setting of a list of contacts.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function run()
	{
		// Check for request forgeries
		$this->checkToken();

		$ids    = $this->input->get('cid', array(), 'array');
		$task   = $this->getTask();

		// Get the model.
		/** @var \Joomla\Component\Jobs\Administrator\Model\JobsModel $model */
		$model  = $this->getModel();

		// Run the jobs.
		if (!$model->execute())
		{
			$this->app->enqueueMessage($model->getError(), 'warning');
		}

		$message = Text::plural('COM_JOBS_N_ITEMS_EXECUTED', 1);

		$this->setRedirect('index.php?option=com_jobs&view=jobs', $message);
	}
}

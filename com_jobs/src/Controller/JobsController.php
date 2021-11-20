<?php
/**
 * @package     Joomla.API
 * @subpackage  com_jobs
 *
 * @copyright   Copyright (C) 2020 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jobs\Api\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Language\Text;
use Joomla\Component\Jobs\Api\View\Jobs\JsonapiView;
use Joomla\CMS\Filter\InputFilter;


/**
 * The jobs controller
 *
 * @since  4.0.0
 */
class JobsController extends ApiController
{
	/**
	 * The content type of the item.
	 *
	 * @var    string
	 * @since  4.0.0
	 */
	protected $contentType = 'jobs';

	/**
	 * The default view for the display method.
	 *
	 * @var    string
	 * @since  4.0.0
	 */
	protected $default_view = 'jobs';

	/**
	 * Method to start executing jobs.
	 *
	 * @return  static  A \JControllerLegacy object to support chaining.
	 *
	 * @since   4.0.0
	 */
	public function start()
	{
		/** @var JobsModel $model */
		$model = $this->getModel($this->contentType);

		if (!$model)
		{
			throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_MODEL_CREATE'));
		}

		$recordId = $this->input->getInt('id');

		if (!$recordId)
		{
			throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_RECORD'), 404);
		}

		$cid = [$recordId];

		// Execute the jobs task
		try
		{
			$data = $model->start($cid);
		}
		catch (\Exception $e)
		{
			throw new \RuntimeException(Text::plural('COM_JOBS_N_ITEMS_EXECUTE', count($cid)));
		}

		$view->setModel($model, true);

		$view->document = $this->app->getDocument();

		$view->displayListTypes();

	}

	/**
	 * Return module items types
	 *
	 * @return  static  A \JControllerLegacy object to support chaining.
	 *
	 * @since   4.0.0
	 */
	public function executeTask()
	{
		$viewType   = $this->app->getDocument()->getType();
		$viewName   = $this->input->get('view', $this->default_view);
		$viewLayout = $this->input->get('layout', 'default', 'string');
		$filter        = InputFilter::getInstance();
		$apiFilterInfo = $this->input->get('id', '', 'string');

		try
		{
			/** @var JsonapiView $view */
			$view = $this->getView(
				$viewName,
				$viewType,
				'',
				['base_path' => $this->basePath, 'layout' => $viewLayout, 'contentType' => $this->contentType]
			);
		}
		catch (\Exception $e)
		{
			throw new \RuntimeException($e->getMessage());
		}

		/** @var \Joomla\Component\Jobs\Administrator\Model\JobsModel $model */
		$model = $this->getModel();

		if (!$model)
		{
			throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_MODEL_CREATE'));
		}

		$model->setState('id', $filter->clean($apiFilterInfo, 'STRING'));

		$view->setModel($model, true);

		$view->document = $this->app->getDocument();

		$view->executeTask();

		return $this;
	}

	public function credentials()
	{	
		$viewType   = $this->app->getDocument()->getType();
		$viewName   = $this->input->get('view', $this->default_view);
		$viewLayout = $this->input->get('layout', 'default', 'string');
		$filter        = InputFilter::getInstance();
		try
		{
			/** @var JsonapiView $view */
			$view = $this->getView(
				$viewName,
				$viewType,
				'',
				['base_path' => $this->basePath, 'layout' => $viewLayout, 'contentType' => $this->contentType]
			);
		}
		catch (\Exception $e)
		{
			throw new \RuntimeException($e->getMessage());
		}

		/** @var \Joomla\Component\Jobs\Administrator\Model\JobsModel $model */
		$model = $this->getModel();
		$data = $this->input->get('data', json_decode($this->input->json->getRaw(), true), 'array');

		$view->setModel($model, true);

		$view->document = $this->app->getDocument();

		$view->credentials($data);

		return $this;
	}
}

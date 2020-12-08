<?php
/**
 * @package     Joomla.API
 * @subpackage  com_jobs
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jobs\Api\View\Jobs;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;
use Joomla\Utilities\ArrayHelper;

/**
 * The manage view
 *
 * @since  4.0.0
 */
class JsonapiView extends BaseApiView
{
	/**
	 * The fields to render item in the documents
	 *
	 * @var  array
	 * @since  4.0.0
	 */
	protected $fieldsToRenderList = [
		'id',
		'taskname',
		'duration',
		'versjobid',
		'taskid',
		'exitcode',
		'lastdate',
		'nextdate',
	];

	/**
	 * Execute and display a template script.
	 *
	 * @param   array|null  $items  Array of items
	 *
	 * @return  string
	 *
	 * @since   4.0.0
	 */
	public function displayList(array $items = null)
	{
		return parent::displayList();

	}

	/**
	 * Execute and display a list modules types.
	 *
	 * @return  string
	 *
	 * @since   4.0.0
	 */
	public function executeTask()
	{
		/** @var SelectModel $model */
		$model = $this->getModel();
		$param = [];

		if (!empty($model->getState('id')))
		{
			$param[] = $model->getState('id');
		}

		$items = $model->start($param);
		$runs  = [];

		foreach ($items as $item)
		{
			$item = ArrayHelper::toObject($item);
			$item->id = $item->eid;

			$runs[] = $item;
		}

		$this->fieldsToRenderList = ['eid', 'job', 'status', 'duration', 'startTime'];

		return parent::displayList($runs);
	}
}

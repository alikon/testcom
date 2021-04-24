<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_jobs
 *
 * @copyright   Copyright (C) 2020 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jobs\Administrator\View\Jobs;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Jobs\Administrator\Helper\JobsHelper;

/**
 * View class for a list of jobs.
 *
 * @since  __DEPLOY_VERSION__
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * True if "System - Scheduler Plugin" is enabled
	 *
	 * @var  boolean
	 */
	protected $enabled;

	/**
	 * An array of items
	 *
	 * @var  array
	 */
	protected $items;

	/**
	 * The pagination object
	 *
	 * @var    \Joomla\CMS\Pagination\Pagination
	 */
	protected $pagination;

	/**
	 * The model state
	 *
	 * @var  \JObject
	 */
	protected $state;

	/**
	 * The model state
	 *
	 * @var  \Joomla\Registry\Registry
	 */
	protected $params;

	/**
	 * Form object for search filters
	 *
	 * @var    \JForm
	 * @since  __DEPLOY_VERSION__
	 */
	public $filterForm;

	/**
	 * The active search filters
	 *
	 * @var    array
	 * @since  __DEPLOY_VERSION__
	 */
	public $activeFilters;

	/**
	 * Display the view.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  mixed  False if unsuccessful, otherwise void.
	 *
	 * @throws  GenericDataException
	 * @since   __DEPLOY_VERSION__
	 */
	public function display($tpl = null)
	{
		// Set variables
		$this->items                = $this->get('Items');
		$this->pagination           = $this->get('Pagination');
		$this->state                = $this->get('State');
		$this->filterForm           = $this->get('FilterForm');
		$this->activeFilters        = $this->get('ActiveFilters');
		$this->params               = ComponentHelper::getParams('com_jobs');

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$this->addToolbar();

		return parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function addToolbar()
	{
		$state = $this->get('State');
		$canDo = ContentHelper::getActions('com_jobs');

		$toolbar = Toolbar::getInstance('toolbar');

		ToolbarHelper::title(Text::_('COM_JOBS_MANAGER_LINKS'), 'loading redirect');

		$dropdown = $toolbar->dropdownButton('status-group')
			->text('JTOOLBAR_CHANGE_STATUS')
			->toggleSplit(false)
			->icon('icon-ellipsis-h')
			->buttonClass('btn btn-action')
			->listCheck(true);
		$childBar = $dropdown->getChildToolbar();

		if ($canDo->get('core.delete'))
		{
			$childBar->delete('jobs.delete')
				->text('JTOOLBAR_DELETE')
				->message('JGLOBAL_CONFIRM_DELETE')
				->listCheck(true);
			$toolbar->confirmButton('heart')
				->text('JEXECUTE')
				->message('COM_JOBS_CONFIRM_EXEC')
				->task('jobs.run');
			$toolbar->confirmButton('delete')
				->text('COM_JOBS_TOOLBAR_PURGE')
				->message('COM_JOBS_CONFIRM_PURGE')
				->task('jobs.purge');
		}

		if ($canDo->get('core.admin') || $canDo->get('core.options'))
		{
			$toolbar->preferences('com_jobs');
		}

		$toolbar->help('JHELP_COMPONENTS_JOBS_MANAGER');
	}
}

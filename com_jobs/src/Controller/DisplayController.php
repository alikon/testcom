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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Component\Jobs\Administrator\Helper\JobsHelper;

/**
 * Jobs master display controller.
 *
 * @since  __DEPLOY_VERSION__
 */
class DisplayController extends BaseController
{
	/**
	 * @var		string	The default view.
	 * @since   __DEPLOY_VERSION__
	 */
	protected $default_view = 'jobs';

	/**
	 * Method to display a view.
	 *
	 * @param   boolean  $cachable   If true, the view output will be cached.
	 * @param   mixed    $urlparams  An array of safe URL parameters and their variable types, for valid values see {@link \JFilterInput::clean()}.
	 *
	 * @return  static|boolean	 This object to support chaining or false on failure.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function display($cachable = false, $urlparams = false)
	{
		$view   = $this->input->get('view', 'jobs');
		$layout = $this->input->get('layout', 'default');
		$id     = $this->input->getInt('id');

		return parent::display();
	}
}

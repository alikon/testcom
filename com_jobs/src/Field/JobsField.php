<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_jobs
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jobs\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * A dropdown containing all valid job response codes.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_jobs
 * @since       __DEPLOY_VERSION__
 */
class JobsField extends ListField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  3.4
	 */
	protected $type = 'Jobs';

	/**
	 * A map of integer HTTP 1.1 response codes to the full HTTP Status for the headers.
	 *
	 * @var    object
	 * @since  3.4
	 */
	protected $responseMap = array(
		'0' => 'Executed',
		'1' => 'Not scheduled',
		'3' => 'Task Failure',

	);

	/**
	 * Method to get the field input markup.
	 *
	 * @return  string	The field input markup.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function getOptions()
	{
		$options = array();

		foreach ($this->responseMap as $key => $value)
		{
			$options[] = HTMLHelper::_('select.option', $key, $value);
		}

		// Merge any additional options in the XML definition.
		$options = array_merge(parent::getOptions(), $options);

		return $options;
	}
}

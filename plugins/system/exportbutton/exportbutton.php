<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.exportbutton
 *
 * @copyright   Copyright (C) 2021 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;

/**
 * Add a button to post a webservice
 *
 * @since  3.8.6
 */
class PlgSystemExportbutton extends CMSPlugin
{
	/**
	 * Application object
	 *
	 * @var    CMSApplication
	 * @since  3.8.6
	 */
	protected $app;

	/**
	 * Database driver
	 *
	 * @var    JDatabaseDriver
	 * @since  3.8.6
	 */
	protected $db;

	/**
	 * Render the button.
	 *
	 * @return  void
	 *
	 * @since  3.8.0
	 */
	public function onBeforeRender()
	{
		// Run in backend
		if ($this->app->isClient('administrator') === true)
		{
			// Get the input object
			$input = $this->app->input;

			// Get an instance of the Toolbar
			$toolbar = Toolbar::getInstance('toolbar');
			
			// Append button on Article
			if ($input->getCmd('option') === 'com_content' && $input->getCmd('view') === 'article')
			{
				$id = $input->get('id');

				// Add your custom button here
				$url = Route::_('index.php?option=com_ajax&group=export&plugin=content&format=json&id=' . $id);
				$toolbar->appendButton('Link', 'download', 'Export', $url);
			}

			// Append button on Category
			if ($input->getCmd('option') === 'com_categories' && $input->getCmd('view') === 'category')
			{
				$id = $input->get('id');
				$extension = str_replace('com_', '', $input->getCmd('extension'));

				// Add your custom button here
				$url = Route::_('index.php?option=com_ajax&group=export&plugin=category&format=json&id=' . $id . '&ext=' . $extension);
				$toolbar->appendButton('Link', 'download', 'Export', $url);
			}

			// Append button on Contact
			if ($input->getCmd('option') === 'com_contact' && $input->getCmd('view') === 'contact')
			{
				$id = $input->get('id');

				// Add your custom button here
				$url = Route::_('index.php?option=com_ajax&group=export&plugin=contact&format=json&id=' . $id);
				$toolbar->appendButton('Link', 'download', 'Export', $url);
			}

			// Append button on Tag
			if ($input->getCmd('option') === 'com_users' && $input->getCmd('view') === 'user')
			{
				$id = $input->get('id');

				// Add your custom button here
				$url = Route::_('index.php?option=com_ajax&group=export&plugin=user&format=json&id=' . $id);
				$toolbar->appendButton('Link', 'download', 'Export', $url);
			}
			// Append button on Tag
			if ($input->getCmd('option') === 'com_tags' && $input->getCmd('view') === 'tag')
			{
				$id = $input->get('id');

				// Add your custom button here
				$url = Route::_('index.php?option=com_ajax&group=export&plugin=tag&format=json&id=' . $id);
				$toolbar->appendButton('Link', 'download', 'Export', $url);
			}
		}
	}
	
}
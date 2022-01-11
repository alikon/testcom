<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  export.content
 *
 * @copyright   Copyright (C) 2021 Alikon, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarFactoryInterface;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Joomla! Export Content plugin
 *
 * An export content plugin
 *
 * @since  3.9
 */
class PlgSystemExportbutton extends CMSPlugin
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
	 * @since  3.9
	 */
	protected $app;

	/**
	 * Database object.
	 *
	 * @var    DatabaseDriver
	 * @since  3.9
	 */
	protected $db;

	/**
	 * URL to get the data.
	 *
	 * @var    string
	 * @since  3.9
	 */
	protected $getUrl = '';

	/**
	 * URL to send the data.
	 *
	 * @var    string
	 * @since  3.9
	 */
	protected $postUrl = '';
	/**
	 * Render the button.
	 *
	 * @return  void
	 *
	 * @since  __DEPLOY_VERSION__
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
			//$toolbar = Factory::getContainer()->get(ToolbarFactoryInterface::class)->createToolbar('toolbar');
			//ToolbarHelper::custom('actionlogs.exportSelectedLogs', 'download', '', 'COM_ACTIONLOGS_EXPORT_CSV', true);
			// Append button on Article
			if ($input->getCmd('option') === 'com_content' && $input->getCmd('view') === 'article')
			{
				$id = $input->get('id');

				// Add your custom button here
				$url = Route::_('index.php?option=com_ajax&group=system&plugin=exportbutton&format=json&id=' . $id);
				$toolbar->appendButton('Link', 'upload', 'Export', $url);
				//ToolbarHelper::custom('associations.clean', 'refresh', '', 'COM_ASSOCIATIONS_DELETE_ORPHANS', false, false);
				/*
				$toolbar->basicButton('upload')
					->attributes(['data-url' => $url])
					->task('history.delete')
					->buttonClass('btn btn-danger')
					->icon('icon-link')
					->text('Export')
					->listCheck(true);
				*/
			}
		}
	}
	/**
	 * First step to enter the sampledata. Content.
	 *
	 * @return  array or void  Will be converted into the JSON response to the module.
	 *
	 * @since  3.8.0
	 */
	public function onAjaxExportbutton()
	{
		$id  = $this->app->input->get('id');
		$domain = $this->params->get('url', 'http://localhost');
		$this->postUrl = $domain . '/api/index.php/v1/content/articles';
		$this->getUrl = $domain . '/api/index.php/v1/content';

		
		// Get an instance of the generic articles model
		$content = Factory::getApplication()->bootComponent('com_content')->getMVCFactory();
		$model = $content->createModel('Article', 'Administrator',['ignore_request' => true]);

		$item = $model->getItem($id);
		$item->catid = $this->params->get('catid');
		unset($item->created_by);

		try
		{
			$response = $this->sendData2($item);
		}
		catch (\Exception $e)
		{
			// There was an error sending data.
			$this->app->enqueueMessage(Text::_('Connecttion'), 'error');
			$this->app->redirect(Route::_('index.php?option=com_content&view=article&layout=edit&id=' . $id, false), 500);
			return;
		}
 
		if ($response->code !== 200)
		{
			$data = json_decode($response->body);
			$this->app->enqueueMessage(Text::_($response->code . ' - ' . $data->errors[0]->title), 'error');
			$this->app->redirect(Route::_('index.php?option=com_content&view=article&layout=edit&id=' . $id, false), $response->code);
		}

		$this->app->enqueueMessage(Text::_('Exported'), 'success');
		$this->app->redirect(Route::_('index.php?option=com_content&view=article&layout=edit&id=' . $id, false), 200);		
	}

	/**
	 * Send the data to the j4 server
	 *
	 * @return  boolean
	 *
	 * @since   3.9
	 *
	 * @throws  RuntimeException  If there is an error sending the data.
	 */
	private function sendData($item)
	{
		$response = $this->checkCategory($item->catid);
		
		if ($response->code !== 200)
		{
			return $response;
		}
	
		$content = json_encode($item);
		$options = new Registry;
		$options->set('Content-Type', 'application/json');
		
		if ($this->params->get('authorization') === 'Bearer')
		{
			$headers = array('Authorization' => 'Bearer ' . $this->params->get('key'));
		}

		if ($this->params->get('authorization') === 'X-Joomla-Token')
		{
			$headers = array('X-Joomla-Token' => $this->params->get('key'));
		}

		try
		{
			$response = HttpFactory::getHttp($options)->post($this->postUrl, $content, $headers, 300);
		}
		catch (\Exception $e)
		{		
			throw new RuntimeException($e->getMessage(), $e->getCode());
		}

		if ($response->code !== 200)
		{		
			return $response;
		}

		return $response;
	}

	/**
	 * Check category existence
	 *
	 * @return  boolean
	 *
	 * @since   3.9
	 *
	 * @throws  RuntimeException  If there is an error sending the data.
	 */
	private function checkCategory($catid)
	{
		$options = new Registry;
		$options->set('Content-Type', 'application/json');
		
		if ($this->params->get('authorization') === 'Bearer')
		{
			$headers = array('Authorization' => 'Bearer ' . $this->params->get('key'));
		}

		if ($this->params->get('authorization') === 'X-Joomla-Token')
		{
			$headers = array('X-Joomla-Token' => $this->params->get('key'));
		}		
		
		// Don't let the request take longer than 2 seconds to avoid page timeout issues
		return HttpFactory::getHttp($options)->get($this->getUrl . '/categories/'. $catid, $headers, 300);
	}

	/**
	 * Send the data to the j4 server
	 *
	 * @return  boolean
	 *
	 * @since   3.9
	 *
	 * @throws  RuntimeException  If there is an error sending the data.
	 */
	private function sendData2($item)
	{
		$response = $this->checkCategory($item->catid);
		
		if ($response->code !== 200)
		{
			return $response;
		}
	
	
		$options = new Registry;
		$options->set('Content-Type', 'application/json');
		
		if ($this->params->get('authorization') === 'Bearer')
		{
			$headers = array('Authorization' => 'Bearer ' . $this->params->get('key'));
		}

		if ($this->params->get('authorization') === 'X-Joomla-Token')
		{
			$headers = array('X-Joomla-Token' => $this->params->get('key'));
		}

		// Check if already exists
		$url = $this->params->get('url', 'http://localhost');
		$title = $item->title;
		$searchUrl = $url . '/api/index.php/v1/content/articles?filter[search]=' . urlencode($title);

		try
		{
			$response = HttpFactory::getHttp($options)->get($searchUrl, $headers, 3);
		}
		catch (\Exception $e)
		{
			return;
		}

		if ($response->code !== 200)
		{

			return;
		}

		$json = json_decode($response->body);
		$content = json_encode($item);

		if (count($json->data) > 0)
		{
			try
			{
				$artid=$json->data[0]->id;
				//var_dump($content);
				$response =  HttpFactory::getHttp($options)->patch($this->postUrl .'/' . $artid, $content, $headers, 3);
			}
			catch (\Exception $e)
			{

				return;
			}

			if ($response->code !== 200)
			{
				return;
			}

			return $response;
		}

		try
		{
			$response = HttpFactory::getHttp($options)->post($this->postUrl, $content, $headers, 3);			
		}
		catch (\Exception $e)
		{		
			throw new RuntimeException($e->getMessage(), $e->getCode());
		}

		if ($response->code !== 200)
		{		
			return $response;
		}

		return $response;
	}
}

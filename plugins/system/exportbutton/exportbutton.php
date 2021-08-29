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
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\Registry\Registry;

/**
 * Add a button to post a webservice
 *
 * @since  __DEPLOY_VERSION__
 */
class PlgSystemExportbutton extends CMSPlugin
{
	/**
	 * Application object
	 *
	 * @var    CMSApplication
	 * @since  __DEPLOY_VERSION__
	 */
	protected $app;

	/**
	 * Database driver
	 *
	 * @var    DatabaseDriver
	 * @since  __DEPLOY_VERSION__
	 */
	protected $db;

	/**
	 * URL to get the data.
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	protected $getUrl = '';

	/**
	 * URL to send the data.
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	protected $postUrl = '';

	/**
	 * URL to send the data.
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	protected $verb = '';

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
			
			// Append button on Article
			if ($input->getCmd('option') === 'com_content' && $input->getCmd('view') === 'article')
			{
				$id = $input->get('id');

				// Add your custom button here
				$url = Route::_('index.php?option=com_ajax&group=system&plugin=exportbutton&format=json&id=' . $id);
				$toolbar->appendButton('Link', 'upload', 'Export', $url);
			}
		}
	}

	/**
	 * First step to send the data. Content.
	 *
	 * @return  array or void  Will be converted into the JSON response to the module.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function onAjaxExportbutton()
	{
		$id  = $this->app->input->get('id');

		$domain = $this->params->get('url', 'http://localhost');
		$this->postUrl = $domain . '/api/index.php/v1/content/articles';
		$this->getUrl = $domain . '/api/index.php/v1/content';


		// Get an instance of the generic articles model
		JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/models', 'ArticleModel');
		$model = JModelLegacy::getInstance('Article', 'ContentModel', array('ignore_request' => true));

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
			$this->app->enqueueMessage(Text::_('Connection' . $e->getMessage()), 'error');
			$this->app->redirect(Route::_('index.php?option=com_content&view=article&layout=edit&id=' . $id, false), 500);
			return;
		}
 
		if ($response->code !== 200)
		{
			$this->app->enqueueMessage(Text::_($response->code . $response->body . ' - ' . $this->verb), 'error');
			$this->app->redirect(Route::_('index.php?option=com_content&view=article&layout=edit&id=' . $id, false), $response->code);
			return;
		}

		$this->app->enqueueMessage(Text::_('Exported:' . $this->verb), 'success');
		$this->app->redirect(Route::_('index.php?option=com_content&view=article&layout=edit&id=' . $id, false), 200);		
	}

	/**
	 * Check category existence
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
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
		try
		{
			$response = HttpFactory::getHttp($options)->get($this->getUrl . '/categories/'. $catid, $headers, 3);
		}
		catch (\Exception $e)
		{
			throw $e;
		}

		return $response;
	}

	/**
	 * Send the data to the j4 server
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 *
	 * @throws  RuntimeException  If there is an error sending the data.
	 */
	private function sendData2($item)
	{
		$this->verb ='get';
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
		$title = $item->title;
		$searchUrl = $this->getUrl . '/articles?filter[search]=' . urlencode($title);

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
				$this->verb ='patch';
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
				return response;
			}

			return $response;
		}

		try
		{
			$this->verb ='post';
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
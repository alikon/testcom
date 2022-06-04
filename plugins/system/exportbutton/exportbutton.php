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
	 * URL to send the data.
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	protected $verb = '';

		/**
	 * URL to send the data.
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	protected $options = '';

	/**
	 * URL to send the data.
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	protected $headers = [];

	/**
	 * URL to send the data.
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	protected $json = null;

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
		$this->options = new Registry;
		$this->options->set('Content-Type', 'application/json');
		
		if ($this->params->get('authorization') === 'Bearer')
		{
			$this->headers = array('Authorization' => 'Bearer ' . $this->params->get('key'));
		}

		if ($this->params->get('authorization') === 'X-Joomla-Token')
		{
			$this->headers = array('X-Joomla-Token' => $this->params->get('key'));
		}

		
		// Get an instance of the generic articles model
		$content = Factory::getApplication()->bootComponent('com_content')->getMVCFactory();
		/** @var Joomla\Component\Content\Administrator\Model\ArticleModel $model */
		$model = $content->createModel('Article', 'Administrator', ['ignore_request' => true]);

		$item = $model->getItem($id);
		$item->catid = $this->params->get('catid');
		unset($item->created_by);

		if ($this->sendData2($item))
		{
			// There was an error sending data.
			$this->app->enqueueMessage(Text::_('Exported to ' . $domain), 'success');
		}

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
	private function checkCategory($catid)
	{	
		// Don't let the request take longer than 2 seconds to avoid page timeout issues
		try
		{
			$response = HttpFactory::getHttp($this->options)->get($this->getUrl . '/categories/'. $catid, $this->headers, 5);
		}
		catch (\Exception $e)
		{
			$this->app->enqueueMessage(Text::_('CheckCat:' . $e->getMessage()), 'error');
			return false;
		}

		if ($response->code === 404)
		{
			$this->app->enqueueMessage(Text::_('Category not found' . $response->code), 'error');
			return false;
		}

		if ($response->code !== 200)
		{
			$this->app->enqueueMessage(Text::_('CheckCat:' . $response->code), 'error');
			return false;
		}
		
		return true;
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
	private function checkArticle($item)
	{	
		// Check if already exists
		$title = $item->title;
		$searchUrl = $this->getUrl . '/articles?filter[search]=' . urlencode($title);

		try
		{
			$response = HttpFactory::getHttp($this->options)->get($searchUrl, $this->headers, 10);
		}
		catch (\Exception $e)
		{
			$this->app->enqueueMessage(Text::_('SearchArt:' . $e->getMessage()), 'error');
			return false;
		}


		if ($response->code !== 200)
		{
			$this->app->enqueueMessage(Text::_('SearchArt:' . $response->code), 'error');
			return false;
		}

		$this->verb ='post';
		$this->json = json_decode($response->body);

		if (count($this->json->data) > 0)
		{
			$this->verb ='patch';
		}
	
		//var_dump(count($this->json->data));
		//$json= json_decode($response->body);
		//var_dump($json->meta->{"total-pages"});
		//exit();
		 
		return true;
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

		if (!$this->checkCategory($item->catid))
		{
			return false;
		}

		if (!$this->checkArticle($item))
		{
			return false;
		}

		$content = json_encode($item);

	
		if ($this->verb === 'patch')
		{
			try
			{
				$this->verb ='patch';
				$artid = $this->json->data[0]->id;
				//var_dump($content);
				$response =  HttpFactory::getHttp($this->options)->patch($this->postUrl .'/' . $artid, $content, $this->headers, 15);
			}
			catch (\Exception $e)
			{

				$this->app->enqueueMessage(Text::_('PatchArt:' . $e->getMessage()), 'error');
				return false;
			}

			if ($response->code !== 200)
			{
				$this->app->enqueueMessage(Text::_('PatchArt:' . $response->code), 'error');
				return false;
			}

			return true;
		}

		try
		{
			$this->verb ='post';
			$response = HttpFactory::getHttp($this->options)->post($this->postUrl, $content, $this->headers, 10);
		}
		catch (\Exception $e)
		{		
			//throw new RuntimeException($e->getMessage(), $e->getCode());
			$this->app->enqueueMessage(Text::_('PostArt:' . $e->getMessage()), 'error');
			return false;
		}
	
		if ($response->code !== 200)
		{		
			$this->app->enqueueMessage(Text::_('PostArt:' . $response->code), 'error');
			return false;
		}

		return true;
	}
}

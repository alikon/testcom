<?php
/**
 * @package     Joomla.Plugins
 * @subpackage  Task.Github
 *
 * @copyright   (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// Restrict direct access
defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Registry\Registry;

/**
 * Task plugin with routines that offer checks on files.
 * At the moment, offers a single routine to check and resize image files in a directory.
 *
 * @since  4.1.0
 */
class PlgTaskGithub extends CMSPlugin implements SubscriberInterface
{
	use TaskPluginTrait;

	/**
	 * @var string[]
	 *
	 * @since 4.1.0
	 */
	protected const TASKS_MAP = [
		'githubissues' => [
			'langConstPrefix' => 'PLG_TASK_GITHUB_TASK_ISSUES',
			'form'            => 'github_parameters',
			'method'          => 'pullGithubIssues',
		],
	];

	/**
	 * @var boolean
	 * @since 4.1.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * @inheritDoc
	 *
	 * @return string[]
	 *
	 * @since 4.1.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	/**
	 * @param   ExecuteTaskEvent  $event  The onExecuteTask event
	 *
	 * @return integer  The exit code
	 *
	 * @since 4.1.0
	 * @throws RuntimeException
	 * @throws LogicException
	 */
	protected function pullGithubIssues(ExecuteTaskEvent $event): int
	{
		//
		$params = $event->getArgument('params');
		$response = '';
		$image    = $params->imageurl;
		$image    = substr($image, 0, strpos($image,'#joomlaImage'));
		$url      = $params->url;
		$catid    = $params->catid;
		$title    = $params->title;


		$options  = new Registry;
		$options->set('Content-Type', 'application/json');
		
		
		if ($params->authorization === 'Bearer')
		{
			$headers = array('Authorization' => 'Bearer ' . $params->key);
		}

		if ($params->authorization === 'X-Joomla-Token')
		{
			$headers = array('X-Joomla-Token' => $params->key);
		}

		// Don't let the request take longer than 3 seconds to avoid timeout issues
		$apiurl    = 'https://api.github.com/repos/joomla/joomla-cms/issues';

		$postUrl   = $url . '/api/index.php/v1/content/articles';
		$searchUrl = $url . '/api/index.php/v1/content/articles?filter[search]=' . urlencode($title);
	
		try
		{
			$response = HttpFactory::getHttp($options)->get($apiurl, [], 3);
		}	
		catch (\Exception $e)
		{
			return TaskStatus::KNOCKOUT;
		}

		$info = json_decode($response->body);

		$article = [];
		$article['title'] = $title;
		$article['images'] = ["image_intro" => $image];
		$article['introtext'] ="";
		$article['fulltext'] ="";
		$info = array_slice($info, 0, 3);

		foreach ($info as $item)
		{
			$article['introtext'] .= '<p><h3>' . $item->title . '</h3>'
				.$item->body . '<a href="' . $item->html_url . '"> ' .  $item->user->login . '</a> ' . $item->updated_at .'</p>';

			$article['catid'] = $catid;
			$article['state'] = 1;

			// Set values which are always the same.
			$article['id']              = 0;
			$article['alias']           = ApplicationHelper::stringURLSafe($article['title']);
			$article['language']        = '*';
			$article['associations']    = [];
			$article['metakey']         = '';
			$article['metadesc']        = '';
			$article['xreference']      = '';		
		}
		
		try
		{
			$response = HttpFactory::getHttp($options)->get($searchUrl, $headers, 300);
		}
		catch (\Exception $e)
		{
			return TaskStatus::KNOCKOUT;
		}

		if ($response->code !== 200)
		{
			return TaskStatus::KNOCKOUT;
		}

		$json = json_decode($response->body);

		$content = json_encode($article);

		if (count($json->data) > 0)
		{
			try
			{
				$artid=$json->data[0]->id;
				$response =  HttpFactory::getHttp($options)->patch($postUrl .'/' . $artid, $content, $headers, 300);
			}
			catch (\Exception $e)
			{
				return TaskStatus::KNOCKOUT;

			}

			if ($response->code !== 200)
			{
				return TaskStatus::KNOCKOUT;
			}

			return TaskStatus::OK;
		}

		try
		{
			$response = HttpFactory::getHttp($options)->post($postUrl, $content, $headers, 300);
		}
		catch (\Exception $e)
		{
			return TaskStatus::KNOCKOUT;
		}


		if ($response->code !== 200)
		{
			return TaskStatus::KNOCKOUT;
		}

		return TaskStatus::OK;
	}
}

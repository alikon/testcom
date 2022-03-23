<?php
/**
 * @package     Joomla.Plugins
 * @subpackage  Task.STartAFriend
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
class PlgTaskStartafriend extends CMSPlugin implements SubscriberInterface
{
	use TaskPluginTrait;

	/**
	 * @var string[]
	 *
	 * @since 4.1.0
	 */
	protected const TASKS_MAP = [
		'startafriend' => [
			'langConstPrefix' => 'PLG_TASK_GITHUB_TASK_ISSUES',
			'form'            => 'start_parameters',
			'method'          => 'startafriend',
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
	protected function startafriend(ExecuteTaskEvent $event): int
	{
		//
		$params = $event->getArgument('params');
		$response = '';	
		$options  = new Registry;
		$options->set('Content-Type', 'application/json');		

		// Let the request take longer than 300 seconds to avoid timeout issues
		
		try
		{
			$response = HttpFactory::getHttp($options)->get($params->url, [], $params->timeout);
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

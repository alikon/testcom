<?php
/**
 * @package     Joomla.Plugins
 * @subpackage  System.Tasklog
 *
 * @copyright   (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// Restrict direct access
defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Task\Task;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Database\ParameterType;

/**
 * This plugin implements email notification functionality for Tasks configured through the Scheduler component.
 * Notification configuration is supported on a per-task basis, which can be set-up through the Task item form, made
 * possible by injecting the notification fields into the item form with a `onContentPrepareForm` listener.<br/>
 *
 * Notifications can be set-up on: task success, failure, fatal failure (task running too long or crashing the request),
 * or on _orphaned_ task routines (missing parent plugin - either uninstalled, disabled or no longer offering a routine
 * with the same ID).
 *
 * @since 4.1.0
 */
class PlgSystemTasklog extends CMSPlugin implements SubscriberInterface
{
	/**
	 * The task notification form. This form is merged into the task item form by {@see
	 * injectTaskNotificationFieldset()}.
	 *
	 * @var string
	 * @since 4.1.0
	 */
	private const TASK_LOG_FORM = 'task_log';

	/**
	 * @var  CMSApplication
	 * @since  4.1.0
	 */
	protected $app;

	/**
	 * @var  DatabaseInterface
	 * @since  4.1.0
	 */
	protected $db;

	/**
	 * @var boolean
	 * @since 4.1.0
	 */
	protected $autoloadLanguage = true;


	/**
	 * @inheritDoc
	 *
	 * @return array
	 *
	 * @since 4.1.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			//'onContentPrepareForm'  => 'injectTaskNotificationFieldset',
			'onTaskExecuteSuccess'    => 'logSuccess',
			'onTaskExecuteFailure'    => 'logFailure',
			'onTaskRoutineNotFound'   => 'logOrphan',
			'onTaskRecoverFailure'    => 'logFatalRecovery',
			'onTaskRoutineWillResume' => 'logWillResume',
		];
	}

	/**
	 * Inject fields to support configuration of post-execution notifications into the task item form.
	 *
	 * @param   EventInterface  $event  The onContentPrepareForm event.
	 *
	 * @return boolean True if successful.
	 *
	 * @since 4.1.0
	 */
	public function injectTaskNotificationFieldset(EventInterface $event): bool
	{
		/** @var Form $form */
		$form = $event->getArgument('0');

		if ($form->getName() !== 'com_scheduler.task')
		{
			return true;
		}

		$formFile = __DIR__ . "/forms/" . self::TASK_NOTIFICATION_FORM . '.xml';

		try
		{
			$formFile = Path::check($formFile);
		}
		catch (Exception $e)
		{
			// Log?
			return false;
		}

		if (!File::exists($formFile))
		{
			return false;
		}

		return $form->loadFile($formFile);
	}

	/**
	 * Send out email notifications on Task execution failure if task configuration allows it.
	 *
	 * @param   Event  $event  The onTaskExecuteFailure event.
	 *
	 * @return void
	 *
	 * @since 4.1.0
	 * @throws Exception
	 */
	public function logFailure(Event $event): void
	{
		/** @var Task $task */
		$task = $event->getArgument('subject');

		// @todo safety checks, multiple files [?]
		$outFile = $event->getArgument('subject')->snapshot['output_file'] ?? '';
		$data    = $this->getDataFromTask($event->getArgument('subject'));
		$this->logTask('plg_system_tasknotification.failure_mail', $data, $outFile);
	}

	/**
	 * Send out email notifications on orphaned task if task configuration allows.<br/>
	 * A task is `orphaned` if the task's parent plugin has been removed/disabled, or no longer offers a task
	 * with the same routine ID.
	 *
	 * @param   Event  $event  The onTaskRoutineNotFound event.
	 *
	 * @return void
	 *
	 * @since 4.1.0
	 * @throws Exception
	 */
	public function logOrphan(Event $event): void
	{
		/** @var Task $task */
		$task = $event->getArgument('subject');

		$data = $this->getDataFromTask($event->getArgument('subject'));
		$this->logTask('plg_system_tasknotification.orphan_mail', $data);
	}

	/**
	 * Send out email notifications on Task execution success if task configuration allows.
	 *
	 * @param   Event  $event  The onTaskExecuteSuccess event.
	 *
	 * @return void
	 *
	 * @since 4.1.0
	 * @throws Exception
	 */
	public function logSuccess(Event $event): void
	{
		/** @var Task $task */
		$task = $event->getArgument('subject');

		// @todo safety checks, multiple files [?]
		$outFile = $event->getArgument('subject')->snapshot['output_file'] ?? '';
		$data    = $this->getDataFromTask($event->getArgument('subject'));
		$this->logTask('plg_system_tasknotification.success_mail', $data, $outFile);
	}

	/**
	 * Send out email notifications on fatal recovery of task execution if task configuration allows.<br/>
	 * Fatal recovery indicated that the task either crashed the parent process or its execution lasted longer
	 * than the global task timeout (this is configurable through the Scheduler component configuration).
	 * In the latter case, the global task timeout should be adjusted so that this false positive can be avoided.
	 * This stands as a limitation of the Scheduler's current task execution implementation, which doesn't involve
	 * keeping track of the parent PHP process which could enable keeping track of the task's status.
	 *
	 * @param   Event  $event  The onTaskRecoverFailure event.
	 *
	 * @return void
	 *
	 * @since 4.1.0
	 * @throws Exception
	 */
	public function logFatalRecovery(Event $event): void
	{
		/** @var Task $task */
		$task = $event->getArgument('subject');

		$data = $this->getDataFromTask($event->getArgument('subject'));
		$this->logTask('plg_system_tasknotification.fatal_recovery_mail', $data);
	}

		/**
	 * Send out email notifications on fatal recovery of task execution if task configuration allows.<br/>
	 * Fatal recovery indicated that the task either crashed the parent process or its execution lasted longer
	 * than the global task timeout (this is configurable through the Scheduler component configuration).
	 * In the latter case, the global task timeout should be adjusted so that this false positive can be avoided.
	 * This stands as a limitation of the Scheduler's current task execution implementation, which doesn't involve
	 * keeping track of the parent PHP process which could enable keeping track of the task's status.
	 *
	 * @param   Event  $event  The onTaskRecoverFailure event.
	 *
	 * @return void
	 *
	 * @since 4.1.0
	 * @throws Exception
	 */
	public function logWillResume(Event $event): void
	{
		/** @var Task $task */
		$task = $event->getArgument('subject');

		$data = $this->getDataFromTask($event->getArgument('subject'));
		$this->logTask('plg_system_tasknotification.fatal_recovery_mail', $data);
	}
	/**
	 * @param   Task  $task  A task object
	 *
	 * @return array  An array of data to bind to a mail template.
	 *
	 * @since 4.1.0
	 */
	private function getDataFromTask(Task $task): array
	{
		$lockOrExecTime = Factory::getDate($task->get('locked') ?? $task->get('last_execution'))->format(Text::_('DATE_FORMAT_LC2'));

		return [
			'TASK_ID'        => $task->get('id'),
			'TASK_TITLE'     => $task->get('title'),
			'EXIT_CODE'      => $task->getContent()['status'] ?? Status::NO_EXIT,
			'EXEC_DATE_TIME' => $lockOrExecTime,
			'TASK_OUTPUT'    => $task->getContent()['output_body'] ?? '',
			'TASK_TIMES'     => $task->get('times_executed'),
			'TASK_DURATION'  => $task->getContent()['duration'],
		];
	}

	/**
	 * @param   string  $template    The mail template.
	 * @param   array   $data        The data to bind to the mail template.
	 * @param   string  $attachment  The attachment to send with the mail (@todo multiple)
	 *
	 * @return void
	 *
	 * @since 4.1.0
	 * @throws Exception
	 */
	private function logTask(string $template, array $data, string $attachment = ''): void
	{
		$app = $this->app;
		$db  = $this->db;

		/** @var \Joomla\Component\Actionlogs\Administrator\Model\ActionlogModel $model */
		$model = $this->app->bootComponent('com_scheduler')
			->getMVCFactory()->createModel('Task', 'Administrator', ['ignore_request' => true]);
		$taskInfo = $model->getItem( $data['TASK_ID']);

		// Get all users who are not blocked and have opted in for system mails.
		$query = $db->getQuery(true);

		$created = Factory::getDate()->toSql();

		$columns = [
		'taskname',
		'duration',
		'jobid',
		'taskid',
		'exitcode',
		'lastdate',
		'nextdate',
		];

		$values = [
		':taskname',
		':duration',
		':jobid',
		':taskid',
		':exitcode',
		':lastdate',
		':nextdate',
		];
		$duration= ($data['TASK_DURATION'] ?? 0);
		$query
			->insert($db->quoteName('#__tasks'), false)
			->columns($db->quoteName($columns))
			->values(implode(', ', $values))
			->bind(':taskname', $data['TASK_TITLE'])
			->bind(':duration', $duration)
			->bind(':jobid', $data['TASK_ID'], ParameterType::INTEGER)
			->bind(':taskid', $data['TASK_TIMES'], ParameterType::INTEGER)
			->bind(':exitcode', $data['EXIT_CODE'], ParameterType::INTEGER)
			->bind(':lastdate', $created)
			->bind(':nextdate', $taskInfo->next_execution);

		$db->setQuery($query);
		$db->execute();
	}
}

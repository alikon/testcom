<?php
/**
 * @package     Joomla.Plugins
 * @subpackage  Task.DelTrash
 *
 * @copyright   Copyright (C) 2021 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// Restrict direct access
defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Access\Access;
use Joomla\CMS\User\UserFactoryInterface;


/**
 * Task plugin with routines to delete the already trashed itens.
 *
 * @since  4.1.0
 */
class PlgTaskdeltrash extends CMSPlugin implements SubscriberInterface
{
	use TaskPluginTrait;

	/**
	 * @var string[]
	 * @since 4.1.0
	 */
	protected const TASKS_MAP = [
		'plg_task_delete_trash'             => [
			'langConstPrefix' => 'PLG_TASK_DELTRASH',
			'form'            => 'deltrash_parameters',
			'method'          => 'deleteTrash',
		],

	];

	/**
	 * The application object.
	 *
	 * @var  CMSApplication
	 * @since 4.1.0
	 */
	protected $app;

	/**
	 * @var  DatabaseInterface
	 * @since  4.1.0
	 */
	protected $db;

	/**
	 * Autoload the language file.
	 *
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
	 * @return void
	 *
	 * @since 4.1.0
	 * @throws Exception
	 */
	public function deleteTrash(ExecuteTaskEvent $event): int
	{
		$this->startRoutine($event);

		if (Factory::getApplication()->isClient('cli'))
		{
			$this->setGrant();
		}

		$this->delArticles();

		if (!$event->getArgument('params')->categories)
		{
			$this->endRoutine($event, Status::OK);
			return Status::OK;
		}
		
		$this->delCategories();

		$this->endRoutine($event, Status::OK);
		return Status::OK;
	}

	private function delCategories() : void
	{
		$cat = 0;
		$noleaf =0;
		$cmodel = $this->app->bootComponent('com_categories')
			->getMVCFactory()
			->createModel('Categories', 'Administrator', ['ignore_request' => true]);
		$cmodel->setState('filter.published', -2);
		$cmodel->setState('filter.extension', 'com_content');
		$cmodel->setState('category.extension', 'com_content');
		// Extract the component name
		$parts = explode('.', 'com_content');
		$cmodel->setState('category.component', $parts[0]);
		$this->app->input->set('extension', 'com_content');

		$ctrashed = $cmodel->getItems();

		$model = $this->app->bootComponent('com_categories')
			->getMVCFactory()
			->createModel('Category', 'Administrator', ['ignore_request' => true]);

		foreach ($ctrashed as $item)
		{
			if (!$model->delete($item->id)) 
			{
				$noleaf++;
			}

			$cat++;
		}

		$this->logTask(Text::sprintf('PLG_TASK_DELTRASH_CATEGORIES_DELETED', $cat - $noleaf), 'notice');
		$this->logTask(Text::sprintf('PLG_TASK_DELTRASH_NOLEAF', $noleaf), 'info');
	}

	private function delArticles() : void
	{
		$art = 0;
		/** @var \Joomla\Component\Content\Administrator\Model\ArticlesModel $model */
		$model = $this->app->bootComponent('com_content')
			->getMVCFactory()->createModel('Articles', 'Administrator', ['ignore_request' => true]);
		$model->setState('filter.published', -2);
		$atrashed = $model->getItems();

		/** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $model */
		$amodel = $this->app->bootComponent('com_content')
			->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);

		foreach ($atrashed as $item)
		{
			if ($amodel->delete($item->id))
			{
				$art++;
			}
		}

		$this->logTask(Text::sprintf('PLG_TASK_DELTRASH_ARTICLES', $art), 'notice');

	}

	private function setGrant() : void
	{
		// Get all usergroups with Super User access
		$db = $this->db;
		$query = $db->getQuery(true)
			->select([$db->qn('id')])
			->from($db->qn('#__usergroups'));
		$groups = $db->setQuery($query)->loadColumn();

		// Get the groups that are Super Users
		$groups = array_filter($groups, function ($gid) {
			return Access::checkGroup($gid, 'core.admin');
		});

		foreach ($groups as $gid)
		{
			$uids = Access::getUsersByGroup($gid);
			$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($uids[0]);
			$this->app->getSession()->set('user', $user);
			break;
		}
	}
}

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

		if ($event->getArgument('params')->articles ?? false)
		{
			$this->delArticles();
		}

		if ($event->getArgument('params')->categories ?? false)
		{
			$this->delCategories();
		}

		if ($event->getArgument('params')->modules ?? false)
		{
			$module = $event->getArgument('params')->moduletype ?? [];
			$this->delModules($module);
		}

		if ($event->getArgument('params')->redirects ?? false)
		{
			$purge = $event->getArgument('params')->redirectspurge ?? false;
			$this->delRedirects($purge);
		}

		if ($event->getArgument('params')->tags ?? false)
		{
			$this->delTags();
		}

		if ($event->getArgument('params')->tasks ?? false)
		{
			$this->delTasks();
		}

		if ($event->getArgument('params')->contacts ?? false)
		{
			$this->delContacts();
		}

		$this->delMenuItems();
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

	private function delModules(Array $type = []) : void
	{
		$mod = 0;
		$strashed = [];
		$atrashed = [];

		if (in_array('site', $type))
		{
			/** @var \Joomla\Component\Modules\Administrator\Model\ModuleModel $model */
			$model = $this->app->bootComponent('com_modules')->getMVCFactory()
				->createModel('Modules', 'Administrator', ['ignore_request' => true]);
			$model->setState('filter.state', -2);
			$strashed = $model->getItems();
		}

		if (in_array('admin', $type))
		{
			$gmodel = $this->app->bootComponent('com_modules')->getMVCFactory()
				->createModel('Modules', 'Administrator', ['ignore_request' => true]);
			$gmodel->setState('filter.client_id', 1);
			$gmodel->setState('client_id', 1);
			$gmodel->setState('filter.state', -2);
			$atrashed = $gmodel->getItems();
		}

		$trashed = array_merge($strashed, $atrashed);
		/** @var \Joomla\Component\Modules\Administrator\Model\ModuleModel $model */
		$mmodel = $this->app->bootComponent('com_modules')->getMVCFactory()
			->createModel('Module', 'Administrator', ['ignore_request' => true]);

		foreach ($trashed as $item)
		{
			if ($mmodel->delete($item->id))
			{
				$mod++;
			}
		}

		$this->logTask(Text::sprintf('PLG_TASK_DELTRASH_MODULES_DELETED', $mod), 'notice');

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

	private function delRedirects(Bool $purge = false) : void
	{
		$red = 0;
		/** @var \Joomla\Component\Redirect\Administrator\Model\LinksModel $model */
		$model = $this->app->bootComponent('com_redirect')
			->getMVCFactory()->createModel('Links', 'Administrator', ['ignore_request' => true]);

		if ($purge && $model->purge())
		{
			$this->logTask(Text::_('PLG_TASK_DELTRASH_REDIRECTS_PURGED'), 'notice');
		}

		$model->setState('filter.state', -2);
		$trashed = $model->getItems();

		$model = $this->app->bootComponent('com_redirect')
			->getMVCFactory()->createModel('Link', 'Administrator', ['ignore_request' => true]);

		foreach ($trashed as $item)
		{
			if ($model->delete($item->id))
			{
				$red++;
			}
		}

		$this->logTask(Text::sprintf('PLG_TASK_DELTRASH_REDIRECTS_TRASHED', $red), 'notice');
	}

	private function delTags() : void
	{
		$art = 0;
		/** @var \Joomla\Component\Content\Administrator\Model\ArticlesModel $model */
		$model = $this->app->bootComponent('com_tags')
			->getMVCFactory()->createModel('Tags', 'Administrator', ['ignore_request' => true]);
		$model->setState('filter.published', -2);
		$atrashed = $model->getItems();

		/** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $model */
		$amodel = $this->app->bootComponent('com_tags')
			->getMVCFactory()->createModel('Tag', 'Administrator', ['ignore_request' => true]);

		foreach ($atrashed as $item)
		{
			if ($amodel->delete($item->id))
			{
				$art++;
			}
		}

		$this->logTask(Text::sprintf('PLG_TASK_DELTRASH_TAGS', $art), 'notice');

	}

	private function delTasks() : void
	{
		$art = 0;
		/** @var \Joomla\Component\Content\Administrator\Model\ArticlesModel $model */
		$model = $this->app->bootComponent('com_scheduler')
			->getMVCFactory()->createModel('Tasks', 'Administrator', ['ignore_request' => true]);
		$model->setState('filter.state', -2);
		$atrashed = $model->getItems();

		/** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $model */
		$amodel = $this->app->bootComponent('com_scheduler')
			->getMVCFactory()->createModel('Task', 'Administrator', ['ignore_request' => true]);

		foreach ($atrashed as $item)
		{
			if ($amodel->delete($item->id))
			{
				$art++;
			}
		}

		$this->logTask(Text::sprintf('PLG_TASK_DELTRASH_TASKS', $art), 'notice');

	}

	private function delMenuItems(Array $type = []) : void
	{
		$art = 0;
		$strashed = [];
		$atrashed = [];

		if (in_array('admin', $type))
		{
			/** @var \Joomla\Component\Content\Administrator\Model\ArticlesModel $model */
			$model = $this->app->bootComponent('com_menus')
				->getMVCFactory()->createModel('Items', 'Administrator', ['ignore_request' => true]);
			$model->setState('filter.published', -2);
			$model->setState('filter.client_id', 1);
			$model->setState('client_id', 1);
			$atrashed = $model->getItems();
		}

		if (in_array('site', $type))
		{
			/** @var \Joomla\Component\Content\Administrator\Model\ArticlesModel $model */
			$model = $this->app->bootComponent('com_menus')
				->getMVCFactory()->createModel('Items', 'Administrator', ['ignore_request' => true]);
			$model->setState('filter.published', -2);
			$strashed = $model->getItems();
		}

		$trashed = array_merge($strashed, $atrashed);
		/** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $model */
		$mmodel = $this->app->bootComponent('com_menus')
			->getMVCFactory()->createModel('Item', 'Administrator', ['ignore_request' => true]);

		foreach ($trashed as $item)
		{
			if ($mmodel->delete($item->id))
			{
				$art++;
			}
		}

		$this->logTask(Text::sprintf('PLG_TASK_DELTRASH_MENUITEMS', $art), 'notice');

	}

	private function delContacts() : void
	{
		$art = 0;
		/** @var \Joomla\Component\Contact\Administrator\Model\ContactsModel $model */
		$model = $this->app->bootComponent('com_contact')
			->getMVCFactory()->createModel('Contacts', 'Administrator', ['ignore_request' => true]);
		$model->setState('filter.published', -2);
		$atrashed = $model->getItems();

		/** @var \Joomla\Component\Contact\Administrator\Model\ContactModel $model */
		$amodel = $this->app->bootComponent('com_contact')
			->getMVCFactory()->createModel('Contact', 'Administrator', ['ignore_request' => true]);

		foreach ($atrashed as $item)
		{
			if ($amodel->delete($item->id))
			{
				$art++;
			}
		}

		$this->logTask(Text::sprintf('PLG_TASK_DELTRASH_CONTACTS_DELETED', $art), 'notice');

	}

}

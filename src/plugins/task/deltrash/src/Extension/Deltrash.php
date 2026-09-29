<?php

/**
 * @package     Joomla.Plugins
 * @subpackage  Task.DelTrash
 *
 * @copyright   Copyright (C) 2021 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Alikonweb\Plugin\Task\Deltrash\Extension;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class Deltrash extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var array<string, array<string, string>>
     * @since 1.0.0
     */
    protected const TASKS_MAP = [
        'plg_task_deltrash' => [
            'langConstPrefix' => 'PLG_TASK_DELTRASH',
            'form'            => 'deltrash_parameters',
            'method'          => 'deleteTrash',
        ],
    ];

    /**
     * The application object.
     *
     * @var  CMSApplication
     * @since 1.0.0
     */
    protected $app;

    /**
     * Autoload the language file.
     *
     * @var boolean
     * @since 1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 1.0.0
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
     * Executes the delete-trash task based on the configured parameters.
     *
     * @param   ExecuteTaskEvent  $event  The onExecuteTask event.
     *
     * @return  integer  The task status code.
     *
     * @since   1.0.0
     */
    public function deleteTrash(ExecuteTaskEvent $event): int
    {
        // Remember the current identity so we can restore it afterwards
        $session      = $this->app->getSession();
        $previousUser = $this->app->getIdentity();

        if (!$this->setGrant()) {
            $this->logTask(Text::_('PLG_TASK_DELTRASH_GRANT_FAILED'), 'error');

            return Status::KNOCKOUT;
        }

        $params    = $event->getArgument('params');
        $hasErrors = false;

        // Build the list of enabled sub-routines as closures
        $routines = [];

        if ($params->articles ?? false) {
            $routines['articles'] = fn() => $this->deleteArticles();
        }

        if ($params->categories ?? false) {
            $components = $params->components ?? [];

            foreach ($components as $component) {
                $routines['categories:' . $component] = fn() => $this->delCategories($component);
            }
        }

        if ($params->modules ?? false) {
            $module = $params->moduletype ?? [];
            $routines['modules'] = fn() => $this->delModules($module);
        }

        if ($params->redirects ?? false) {
            $purge = $params->redirectspurge ?? false;
            $routines['redirects'] = fn() => $this->delRedirects($purge);
        }

        if ($params->tags ?? false) {
            $routines['tags'] = fn() => $this->delTags();
        }

        if ($params->tasks ?? false) {
            $routines['tasks'] = fn() => $this->delTasks();
        }

        if ($params->contacts ?? false) {
            $routines['contacts'] = fn() => $this->delContacts();
        }

        if ($params->menus ?? false) {
            $menus = $params->menutype ?? [];
            $routines['menus'] = fn() => $this->delMenuItems($menus);
        }

        try {
            // Run each routine in isolation: one failure must not abort the rest
            foreach ($routines as $name => $routine) {
                try {
                    $routine();
                } catch (\Throwable $e) {
                    $hasErrors = true;
                    $this->logTask(
                        Text::sprintf('PLG_TASK_DELTRASH_ROUTINE_FAILED', $name, $e->getMessage()),
                        'error'
                    );
                }
            }
        } finally {
            // Restore the previous identity so a web-triggered run does not
            // hijack the current admin session
            if ($previousUser) {
                $session->set('user', $previousUser);
                $this->app->loadIdentity($previousUser);
            }
        }

        return $hasErrors ? Status::KNOCKOUT : Status::OK;
    }

    /**
     * Deletes trashed categories for a given component extension.
     *
     * @param   string  $component  The component extension (e.g. 'com_content').
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function delCategories(string $component): void
    {
        $factory = $this->app->bootComponent('com_categories')->getMVCFactory();

        /** @var \Joomla\Component\Categories\Administrator\Model\CategoriesModel $cmodel */
        $cmodel = $factory->createModel('Categories', 'Administrator', ['ignore_request' => true]);
        $cmodel->setState('filter.published', -2);
        $cmodel->setState('filter.extension', $component);
        $cmodel->setState('category.extension', $component);

        // Extract the component name
        $parts = explode('.', $component);
        $cmodel->setState('category.component', $parts[0]);
        $cmodel->setState('extension', $component);

        // The core content plugin (canDeleteCategories) reads 'extension' from the
        // request input; without it PHP 8.4 raises a null-array-offset deprecation.
        $input    = $this->app->input;
        $previous = $input->get('extension', null);
        $input->set('extension', $component);

        try {
            $ctrashed = $cmodel->getItems();

            /** @var \Joomla\Component\Categories\Administrator\Model\CategoryModel $model */
            $model = $factory->createModel('Category', 'Administrator', ['ignore_request' => true]);

            $result = $this->deleteItemsSafely(
                $ctrashed,
                fn(int $id) => $model->delete($id),
                fn() => $model->getError()
            );
        } finally {
            // Always restore the previous input value, even on exception
            $input->set('extension', $previous);
        }

        if ($result['deleted'] > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_CATEGORIES_DELETED', $component, $result['deleted']), 'info');
        }

        if ($result['failed'] > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_NOLEAF', $component, $result['failed']), 'info');
        }
    }

    /**
     * Deletes trashed modules for the given client types.
     *
     * @param   array  $type  Client types to process: 'site', 'admin', or both.
     *
     * @return  void
     *
     * @since   1.1.0
     */
    private function delModules(array $type = []): void
    {
        $factory  = $this->app->bootComponent('com_modules')->getMVCFactory();
        $strashed = [];
        $atrashed = [];

        if (\in_array('site', $type)) {
            /** @var \Joomla\Component\Modules\Administrator\Model\ModulesModel $model */
            $model = $factory->createModel('Modules', 'Administrator', ['ignore_request' => true]);
            $model->setState('filter.state', -2);
            $strashed = $model->getItems();
        }

        if (\in_array('admin', $type)) {
            /** @var \Joomla\Component\Modules\Administrator\Model\ModulesModel $gmodel */
            $gmodel = $factory->createModel('Modules', 'Administrator', ['ignore_request' => true]);
            $gmodel->setState('filter.client_id', 1);
            $gmodel->setState('client_id', 1);
            $gmodel->setState('filter.state', -2);
            $atrashed = $gmodel->getItems();
        }

        $trashed = array_merge($strashed, $atrashed);

        /** @var \Joomla\Component\Modules\Administrator\Model\ModuleModel $mmodel */
        $mmodel = $factory->createModel('Module', 'Administrator', ['ignore_request' => true]);

        $result = $this->deleteItemsSafely(
            $trashed,
            fn(int $id) => $mmodel->delete($id),
            fn() => $mmodel->getError()
        );

        if ($result['deleted'] > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_MODULES_DELETED', $result['deleted']), 'info');
        }
    }

    /**
     * Deletes trashed redirects and optionally purges all redirect records.
     *
     * @param   bool  $purge  Whether to purge all redirects before deleting trashed ones.
     *
     * @return  void
     *
     * @since   1.1.0
     */
    private function delRedirects(bool $purge = false): void
    {
        $factory = $this->app->bootComponent('com_redirect')->getMVCFactory();

        /** @var \Joomla\Component\Redirect\Administrator\Model\LinksModel $model */
        $model = $factory->createModel('Links', 'Administrator', ['ignore_request' => true]);

        if ($purge && $model->purge()) {
            $this->logTask(Text::_('PLG_TASK_DELTRASH_REDIRECTS_PURGED'), 'info');
        }

        $model->setState('filter.state', -2);
        $trashed = $model->getItems();

        /** @var \Joomla\Component\Redirect\Administrator\Model\LinkModel $lmodel */
        $lmodel = $factory->createModel('Link', 'Administrator', ['ignore_request' => true]);

        $result = $this->deleteItemsSafely(
            $trashed,
            fn(int $id) => $lmodel->delete($id),
            fn() => $lmodel->getError()
        );

        if ($result['deleted'] > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_REDIRECTS_TRASHED', $result['deleted']), 'info');
        }
    }

    /**
     * Deletes trashed tags.
     *
     * @return  void
     *
     * @since   1.2.0
     */
    private function delTags(): void
    {
        $factory = $this->app->bootComponent('com_tags')->getMVCFactory();

        /** @var \Joomla\Component\Tags\Administrator\Model\TagsModel $model */
        $model = $factory->createModel('Tags', 'Administrator', ['ignore_request' => true]);
        $model->setState('filter.published', -2);
        $model->setState('filter.extension', '');
        $atrashed = $model->getItems();

        /** @var \Joomla\Component\Tags\Administrator\Model\TagModel $amodel */
        $amodel = $factory->createModel('Tag', 'Administrator', ['ignore_request' => true]);

        $result = $this->deleteItemsSafely(
            $atrashed,
            fn(int $id) => $amodel->delete($id),
            fn() => $amodel->getError()
        );

        if ($result['deleted'] > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_TAGS', $result['deleted']), 'info');
        }
    }

    /**
     * Deletes trashed scheduled tasks.
     *
     * @return  void
     *
     * @since   1.2.0
     */
    private function delTasks(): void
    {
        $factory = $this->app->bootComponent('com_scheduler')->getMVCFactory();

        /** @var \Joomla\Component\Scheduler\Administrator\Model\TasksModel $model */
        $model = $factory->createModel('Tasks', 'Administrator', ['ignore_request' => true]);
        $model->setState('filter.state', -2);
        $atrashed = $model->getItems();

        /** @var \Joomla\Component\Scheduler\Administrator\Model\TaskModel $amodel */
        $amodel = $factory->createModel('Task', 'Administrator', ['ignore_request' => true]);

        $result = $this->deleteItemsSafely(
            $atrashed,
            fn(int $id) => $amodel->delete($id),
            fn() => $amodel->getError()
        );

        if ($result['deleted'] > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_TASKS', $result['deleted']), 'info');
        }
    }

    /**
     * Deletes trashed menu items for the given client types.
     *
     * @param   array  $type  Client types to process: 'site', 'admin', or both.
     *
     * @return  void
     *
     * @since   1.2.0
     */
    private function delMenuItems(array $type = []): void
    {
        $factory  = $this->app->bootComponent('com_menus')->getMVCFactory();
        $strashed = [];
        $atrashed = [];

        if (\in_array('admin', $type)) {
            /** @var \Joomla\Component\Menus\Administrator\Model\ItemsModel $model */
            $model = $factory->createModel('Items', 'Administrator', ['ignore_request' => true]);
            $model->setState('filter.published', -2);
            $model->setState('filter.client_id', 1);
            $model->setState('client_id', 1);
            $atrashed = $model->getItems();
        }

        if (\in_array('site', $type)) {
            /** @var \Joomla\Component\Menus\Administrator\Model\ItemsModel $model */
            $model = $factory->createModel('Items', 'Administrator', ['ignore_request' => true]);
            $model->setState('filter.published', -2);
            $strashed = $model->getItems();
        }

        $trashed = array_merge($strashed, $atrashed);

        /** @var \Joomla\Component\Menus\Administrator\Model\ItemModel $mmodel */
        $mmodel = $factory->createModel('Item', 'Administrator', ['ignore_request' => true]);

        $result = $this->deleteItemsSafely(
            $trashed,
            fn(int $id) => $mmodel->delete($id),
            fn() => $mmodel->getError()
        );

        if ($result['deleted'] > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_MENUITEMS', $result['deleted']), 'info');
        }
    }

    /**
     * Deletes trashed contacts.
     *
     * @return  void
     *
     * @since   1.3.0
     */
    private function delContacts(): void
    {
        $factory = $this->app->bootComponent('com_contact')->getMVCFactory();

        /** @var \Joomla\Component\Contact\Administrator\Model\ContactsModel $model */
        $model = $factory->createModel('Contacts', 'Administrator', ['ignore_request' => true]);
        $model->setState('filter.published', -2);
        $atrashed = $model->getItems();

        /** @var \Joomla\Component\Contact\Administrator\Model\ContactModel $amodel */
        $amodel = $factory->createModel('Contact', 'Administrator', ['ignore_request' => true]);

        $result = $this->deleteItemsSafely(
            $atrashed,
            fn(int $id) => $amodel->delete($id),
            fn() => $amodel->getError()
        );

        if ($result['deleted'] > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_CONTACTS_DELETED', $result['deleted']), 'info');
        }
    }

    /**
     * Loads a Super User identity into the application to grant elevated access.
     *
     * @return  boolean  True when a super user identity was loaded.
     *
     * @since   1.1.0
     */
    private function setGrant(): bool
    {
        $db = $this->getDatabase();

        // Only load users in groups that actually hold core.admin (Super User)
        // access, instead of iterating every unblocked user on the site.
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('u.id'))
            ->from($db->quoteName('#__users', 'u'))
            ->join(
                'INNER',
                $db->quoteName('#__user_usergroup_map', 'm'),
                $db->quoteName('m.user_id') . ' = ' . $db->quoteName('u.id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__usergroups', 'g'),
                $db->quoteName('g.id') . ' = ' . $db->quoteName('m.group_id')
            )
            ->where($db->quoteName('u.block') . ' = 0')
            ->where($db->quoteName('g.rules') . ' LIKE ' . $db->quote('%"core.admin"%'));

        $userIds = $db->setQuery($query)->loadColumn();

        $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);

        foreach ($userIds as $uid) {
            $user = $userFactory->loadUserById((int) $uid);

            // Authorise check instead of group guessing: is this user a Super User?
            if ($user->authorise('core.admin')) {
                $this->app->getSession()->set('user', $user);
                $this->app->loadIdentity($user);

                return true;
            }
        }

        $this->logTask(Text::_('PLG_TASK_DELTRASH_NO_SUPERUSER'), 'error');

        return false;
    }

    /**
     * Deletes trashed articles and their related records.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function deleteArticles(): void
    {
        // Language strings used by the associations cleanup below
        $language = $this->getApplication()->getLanguage();
        $language->load('com_associations', JPATH_ADMINISTRATOR, 'en-GB', false, true);
        $language->load('com_associations', JPATH_ADMINISTRATOR, null, true);

        $factory = $this->app->bootComponent('com_content')->getMVCFactory();

        /** @var \Joomla\Component\Content\Administrator\Model\ArticlesModel $listModel */
        $listModel = $factory->createModel('Articles', 'Administrator', ['ignore_request' => true]);
        $listModel->setState('filter.published', -2);
        $trashed = $listModel->getItems();

        if (empty($trashed)) {
            return;
        }

        /** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $articleModel */
        $articleModel = $factory->createModel('Article', 'Administrator', ['ignore_request' => true]);

        $deletedIds = [];

        // Model path: dispatches onContentBeforeDelete/onContentAfterDelete,
        // deletes the asset, #__content_frontpage, #__history, #__associations
        // and the #__workflow_associations row.
        $result = $this->deleteItemsSafely(
            $trashed,
            function (int $id) use ($articleModel, &$deletedIds) {
                if ($articleModel->delete([$id])) {
                    $deletedIds[] = $id;

                    return true;
                }

                return false;
            },
            fn() => $articleModel->getError()
        );

        // Core does not clean these on article delete - batched: 3 queries total.
        if (!empty($deletedIds)) {
            $this->deleteArticleAuxiliaryData($deletedIds);
        }

        // Global orphan cleanup - run once, not once per article.
        /** @var \Joomla\Component\Associations\Administrator\Model\AssociationsModel $assocModel */
        $assocModel = $this->app->bootComponent('com_associations')
            ->getMVCFactory()
            ->createModel('Associations', 'Administrator', ['ignore_request' => true]);
        $assocModel->clean();

        if ($result['deleted'] > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_ARTICLES', $result['deleted']), 'info');
        }

        if ($result['failed'] > 0) {
            $this->logTask(Text::sprintf('PLG_TASK_DELTRASH_ARTICLES_FAILED', $result['failed']), 'warning');
        }
    }

    /**
     * Removes the auxiliary rows that core does not clean when articles are deleted.
     *
     * @param   integer[]  $ids  The deleted article ids.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    private function deleteArticleAuxiliaryData(array $ids): void
    {
        $db = $this->getDatabase();

        // Tag mappings
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__contentitem_tag_map'))
            ->whereIn($db->quoteName('content_item_id'), $ids, ParameterType::INTEGER);
        $db->setQuery($query)->execute();

        // UCM content
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ucm_content'))
            ->whereIn($db->quoteName('core_content_item_id'), $ids, ParameterType::INTEGER);
        $db->setQuery($query)->execute();

        // UCM base
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ucm_base'))
            ->whereIn($db->quoteName('ucm_item_id'), $ids, ParameterType::INTEGER);
        $db->setQuery($query)->execute();
    }

    /**
     * Deletes items one by one, isolating failures so one bad item
     * does not abort the rest of the batch.
     *
     * @param   iterable  $items     Items with an ->id property
     * @param   callable  $deleteFn  Receives the item id, returns bool
     * @param   callable  $errorFn   Returns the last model error message
     *
     * @return  array{deleted: int, failed: int}
     *
     * @since   2.0.0
     */
    private function deleteItemsSafely(iterable $items, callable $deleteFn, callable $errorFn): array
    {
        $deleted = 0;
        $failed  = 0;

        foreach ($items as $item) {
            $id = (int) $item->id;

            try {
                if ($deleteFn($id)) {
                    $deleted++;
                } else {
                    $failed++;
                    $this->logTask(
                        Text::sprintf('PLG_TASK_DELTRASH_ITEM_FAILED', $id, $errorFn()),
                        'warning'
                    );
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->logTask(
                    Text::sprintf('PLG_TASK_DELTRASH_ITEM_FAILED', $id, $e->getMessage()),
                    'error'
                );
            }
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }
}

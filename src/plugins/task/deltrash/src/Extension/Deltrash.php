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
     * @var CMSApplication
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
     * @return integer The task status code.
     *
     * @since 1.0.0
     */
    public function deleteTrash(ExecuteTaskEvent $event): int
    {
        // Remember the current identity so it can be restored afterwards.
        $session      = $this->app->getSession();
        $previousUser = $this->app->getIdentity();

        if (!$this->setGrant()) {
            $this->logTask(Text::_('PLG_TASK_DELTRASH_GRANT_FAILED'), 'error');

            return Status::KNOCKOUT;
        }

        $params      = $event->getArgument('params');
        $hasErrors   = false;
        $failedItems = 0;

        // Build the list of enabled sub-routines as closures.
        // Each routine returns the number of items it failed to delete.
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
            // Run each routine in isolation: one failure must not abort the rest.
            foreach ($routines as $name => $routine) {
                try {
                    $failedItems += (int) $routine();
                } catch (\Throwable $e) {
                    $hasErrors = true;

                    $this->logTask(
                        Text::sprintf('PLG_TASK_DELTRASH_ROUTINE_FAILED', $name, $e->getMessage()),
                        'error'
                    );
                }
            }
        } finally {
            // Always restore the previous identity.
            //
            // This is important for CLI execution where there may be no
            // authenticated identity at the beginning of the task.
            $session->set('user', $previousUser);
            $this->app->loadIdentity($previousUser);
        }

        // Individual item failures are task failures. The other routines
        // are still allowed to complete, but the scheduler must not report
        // the task as successful when items remain in the trash.
        if ($failedItems > 0) {
            $hasErrors = true;

            $this->logTask(
                Text::sprintf('PLG_TASK_DELTRASH_ITEMS_REMAINING', $failedItems),
                'warning'
            );
        }

        return $hasErrors ? Status::KNOCKOUT : Status::OK;
    }

    /**
     * Deletes trashed categories for a given component extension.
     *
     * @param string $component The component extension.
     *
     * @return integer The number of items that failed to delete.
     *
     * @since 1.0.0
     */
    private function delCategories(string $component): int
    {
        $factory = $this->app->bootComponent('com_categories')->getMVCFactory();

        /** @var \Joomla\Component\Categories\Administrator\Model\CategoriesModel $cmodel */
        $cmodel = $factory->createModel('Categories', 'Administrator', ['ignore_request' => true]);
        $cmodel->setState('filter.published', -2);
        $cmodel->setState('filter.extension', $component);
        $cmodel->setState('category.extension', $component);

        $parts = explode('.', $component);
        $cmodel->setState('category.component', $parts[0]);
        $cmodel->setState('extension', $component);

        // The core content plugin reads "extension" from the request input.
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
            $input->set('extension', $previous);
        }

        if ($result['deleted'] > 0) {
            $this->logTask(
                Text::sprintf(
                    'PLG_TASK_DELTRASH_CATEGORIES_DELETED',
                    $component,
                    $result['deleted']
                ),
                'info'
            );
        }

        if ($result['failed'] > 0) {
            $this->logTask(
                Text::sprintf(
                    'PLG_TASK_DELTRASH_NOLEAF',
                    $component,
                    $result['failed']
                ),
                'warning'
            );
        }

        return $result['failed'];
    }

    /**
     * Deletes trashed modules for the given client types.
     *
     * @param array $type Client types to process.
     *
     * @return integer The number of items that failed to delete.
     *
     * @since 1.1.0
     */
    private function delModules(array $type = []): int
    {
        $factory   = $this->app->bootComponent('com_modules')->getMVCFactory();
        $strashed  = [];
        $atrashed  = [];

        if (\in_array('site', $type, true)) {
            /** @var \Joomla\Component\Modules\Administrator\Model\ModulesModel $model */
            $model = $factory->createModel('Modules', 'Administrator', ['ignore_request' => true]);
            $model->setState('filter.state', -2);
            $model->setState('client_id', 0);
            $strashed = $model->getItems();
        }

        if (\in_array('admin', $type, true)) {
            /** @var \Joomla\Component\Modules\Administrator\Model\ModulesModel $gmodel */
            $gmodel = $factory->createModel('Modules', 'Administrator', ['ignore_request' => true]);
            $gmodel->setState('filter.state', -2);
            $gmodel->setState('client_id', 1);
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
            $this->logTask(
                Text::sprintf('PLG_TASK_DELTRASH_MODULES_DELETED', $result['deleted']),
                'info'
            );
        }

        return $result['failed'];
    }

    /**
     * Deletes trashed redirects and optionally purges all redirect records.
     *
     * @param boolean $purge Whether to purge all redirects.
     *
     * @return integer The number of items that failed to delete.
     *
     * @since 1.1.0
     */
    private function delRedirects(bool $purge = false): int
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
            $this->logTask(
                Text::sprintf('PLG_TASK_DELTRASH_REDIRECTS_TRASHED', $result['deleted']),
                'info'
            );
        }

        return $result['failed'];
    }

    /**
     * Deletes trashed tags.
     *
     * @return integer The number of items that failed to delete.
     *
     * @since 1.2.0
     */
    private function delTags(): int
    {
        $factory = $this->app->bootComponent('com_tags')->getMVCFactory();

        /** @var \Joomla\Component\Tags\Administrator\Model\TagsModel $model */
        $model = $factory->createModel('Tags', 'Administrator', ['ignore_request' => true]);
        $model->setState('filter.published', -2);
        $model->setState('filter.extension', '');
        $trashed = $model->getItems();

        /** @var \Joomla\Component\Tags\Administrator\Model\TagModel $tagModel */
        $tagModel = $factory->createModel('Tag', 'Administrator', ['ignore_request' => true]);

        $result = $this->deleteItemsSafely(
            $trashed,
            fn(int $id) => $tagModel->delete($id),
            fn() => $tagModel->getError()
        );

        if ($result['deleted'] > 0) {
            $this->logTask(
                Text::sprintf('PLG_TASK_DELTRASH_TAGS', $result['deleted']),
                'info'
            );
        }

        return $result['failed'];
    }

    /**
     * Deletes trashed scheduled tasks.
     *
     * @return integer The number of items that failed to delete.
     *
     * @since 1.2.0
     */
    private function delTasks(): int
    {
        $factory = $this->app->bootComponent('com_scheduler')->getMVCFactory();

        /** @var \Joomla\Component\Scheduler\Administrator\Model\TasksModel $model */
        $model = $factory->createModel('Tasks', 'Administrator', ['ignore_request' => true]);
        $model->setState('filter.state', -2);
        $trashed = $model->getItems();

        /** @var \Joomla\Component\Scheduler\Administrator\Model\TaskModel $taskModel */
        $taskModel = $factory->createModel('Task', 'Administrator', ['ignore_request' => true]);

        $result = $this->deleteItemsSafely(
            $trashed,
            fn(int $id) => $taskModel->delete($id),
            fn() => $taskModel->getError()
        );

        if ($result['deleted'] > 0) {
            $this->logTask(
                Text::sprintf('PLG_TASK_DELTRASH_TASKS', $result['deleted']),
                'info'
            );
        }

        return $result['failed'];
    }

    /**
     * Deletes trashed menu items for the given client types.
     *
     * @param array $type Client types to process.
     *
     * @return integer The number of items that failed to delete.
     *
     * @since 1.2.0
     */
    private function delMenuItems(array $type = []): int
    {
        $factory  = $this->app->bootComponent('com_menus')->getMVCFactory();
        $strashed = [];
        $atrashed = [];

        if (\in_array('admin', $type, true)) {
            /** @var \Joomla\Component\Menus\Administrator\Model\ItemsModel $model */
            $model = $factory->createModel('Items', 'Administrator', ['ignore_request' => true]);
            $model->setState('filter.published', -2);
            $model->setState('filter.client_id', 1);
            $model->setState('client_id', 1);
            $atrashed = $model->getItems();
        }

        if (\in_array('site', $type, true)) {
            /** @var \Joomla\Component\Menus\Administrator\Model\ItemsModel $model */
            $model = $factory->createModel('Items', 'Administrator', ['ignore_request' => true]);
            $model->setState('filter.published', -2);
            $strashed = $model->getItems();
        }

        $trashed = array_merge($strashed, $atrashed);

        /** @var \Joomla\Component\Menus\Administrator\Model\ItemModel $itemModel */
        $itemModel = $factory->createModel('Item', 'Administrator', ['ignore_request' => true]);

        $result = $this->deleteItemsSafely(
            $trashed,
            fn(int $id) => $itemModel->delete($id),
            fn() => $itemModel->getError()
        );

        if ($result['deleted'] > 0) {
            $this->logTask(
                Text::sprintf('PLG_TASK_DELTRASH_MENUITEMS', $result['deleted']),
                'info'
            );
        }

        return $result['failed'];
    }

    /**
     * Deletes trashed contacts.
     *
     * @return integer The number of items that failed to delete.
     *
     * @since 1.3.0
     */
    private function delContacts(): int
    {
        $factory = $this->app->bootComponent('com_contact')->getMVCFactory();

        /** @var \Joomla\Component\Contact\Administrator\Model\ContactsModel $model */
        $model = $factory->createModel('Contacts', 'Administrator', ['ignore_request' => true]);
        $model->setState('filter.published', -2);
        $trashed = $model->getItems();

        /** @var \Joomla\Component\Contact\Administrator\Model\ContactModel $contactModel */
        $contactModel = $factory->createModel('Contact', 'Administrator', ['ignore_request' => true]);

        $result = $this->deleteItemsSafely(
            $trashed,
            fn(int $id) => $contactModel->delete($id),
            fn() => $contactModel->getError()
        );

        if ($result['deleted'] > 0) {
            $this->logTask(
                Text::sprintf('PLG_TASK_DELTRASH_CONTACTS_DELETED', $result['deleted']),
                'info'
            );
        }

        return $result['failed'];
    }

    /**
     * Loads a Super User identity into the application.
     *
     * @return boolean True when a Super User identity was loaded.
     *
     * @since 1.1.0
     */
    private function setGrant(): bool
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('u.id'))
            ->from($db->quoteName('#__users', 'u'))
            ->join(
                'INNER',
                $db->quoteName('#__user_usergroup_map', 'm'),
                $db->quoteName('m.user_id') . ' = ' . $db->quoteName('u.id')
            )
            ->where($db->quoteName('u.block') . ' = 0');

        $userIds = $db->setQuery($query)->loadColumn();

        $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);

        foreach ($userIds as $uid) {
            $user = $userFactory->loadUserById((int) $uid);

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
     * @return integer The number of items that failed to delete.
     *
     * @since 1.0.0
     */
    private function deleteArticles(): int
    {
        $language = $this->getApplication()->getLanguage();
        $language->load('com_associations', JPATH_ADMINISTRATOR, 'en-GB', false, true);
        $language->load('com_associations', JPATH_ADMINISTRATOR, null, true);

        $factory = $this->app->bootComponent('com_content')->getMVCFactory();

        /** @var \Joomla\Component\Content\Administrator\Model\ArticlesModel $listModel */
        $listModel = $factory->createModel('Articles', 'Administrator', ['ignore_request' => true]);
        $listModel->setState('filter.published', -2);
        $trashed = $listModel->getItems();

        if (empty($trashed)) {
            return 0;
        }

        /** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $articleModel */
        $articleModel = $factory->createModel('Article', 'Administrator', ['ignore_request' => true]);

        $deletedIds = [];

        $result = $this->deleteItemsSafely(
            $trashed,
            function (int $id) use ($articleModel, &$deletedIds): bool {
                $pks = [$id];

                if ($articleModel->delete($pks)) {
                    $deletedIds[] = $id;

                    return true;
                }

                return false;
            },
            fn() => $articleModel->getError()
        );

        // Core does not clean these rows on article delete.
        if (!empty($deletedIds)) {
            $this->deleteArticleAuxiliaryData($deletedIds);
        }

        /** @var \Joomla\Component\Associations\Administrator\Model\AssociationsModel $assocModel */
        $assocModel = $this->app->bootComponent('com_associations')
            ->getMVCFactory()
            ->createModel('Associations', 'Administrator', ['ignore_request' => true]);

        $assocModel->clean();

        if ($result['deleted'] > 0) {
            $this->logTask(
                Text::sprintf('PLG_TASK_DELTRASH_ARTICLES', $result['deleted']),
                'info'
            );
        }

        if ($result['failed'] > 0) {
            $this->logTask(
                Text::sprintf('PLG_TASK_DELTRASH_ARTICLES_FAILED', $result['failed']),
                'warning'
            );
        }

        return $result['failed'];
    }

    /**
     * Removes auxiliary rows that core does not clean when articles are deleted.
     *
     * @param integer[] $ids The deleted article IDs.
     *
     * @return void
     *
     * @since 2.0.0
     */
    private function deleteArticleAuxiliaryData(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));

        if ($ids === []) {
            return;
        }

        $db    = $this->getDatabase();
        $alias = 'com_content.article';

        $query = $db->getQuery(true)
            ->select($db->quoteName('type_id'))
            ->from($db->quoteName('#__content_types'))
            ->where($db->quoteName('type_alias') . ' = :alias')
            ->bind(':alias', $alias);

        $articleTypeId = (int) $db->setQuery($query)->loadResult();

        $db->transactionStart();

        try {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__contentitem_tag_map'))
                ->whereIn(
                    $db->quoteName('content_item_id'),
                    $ids,
                    ParameterType::INTEGER
                )
                ->where($db->quoteName('type_alias') . ' = :alias')
                ->bind(':alias', $alias);

            $db->setQuery($query)->execute();

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__ucm_content'))
                ->whereIn(
                    $db->quoteName('core_content_item_id'),
                    $ids,
                    ParameterType::INTEGER
                )
                ->where($db->quoteName('core_type_alias') . ' = :alias')
                ->bind(':alias', $alias);

            $db->setQuery($query)->execute();

            if ($articleTypeId > 0) {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__ucm_base'))
                    ->whereIn(
                        $db->quoteName('ucm_item_id'),
                        $ids,
                        ParameterType::INTEGER
                    )
                    ->where($db->quoteName('ucm_type_id') . ' = :typeId')
                    ->bind(':typeId', $articleTypeId, ParameterType::INTEGER);

                $db->setQuery($query)->execute();
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();

            $this->logTask(
                Text::sprintf('PLG_TASK_DELTRASH_AUX_CLEANUP_FAILED', $e->getMessage()),
                'warning'
            );
        }
    }

    /**
     * Deletes items one by one, isolating failures so one bad item
     * does not abort the rest of the batch.
     *
     * @param iterable $items Items with an ->id property.
     * @param callable $deleteFn Receives the item ID and returns bool.
     * @param callable $errorFn Returns the last model error message.
     *
     * @return array{deleted: int, failed: int}
     *
     * @since 2.0.0
     */
    private function deleteItemsSafely(
        iterable $items,
        callable $deleteFn,
        callable $errorFn
    ): array {
        $deleted = 0;
        $failed  = 0;

        foreach ($items as $item) {
            $id = (int) $item->id;

            try {
                if ($deleteFn($id)) {
                    $deleted++;

                    continue;
                }

                $failed++;

                $this->logTask(
                    Text::sprintf(
                        'PLG_TASK_DELTRASH_ITEM_FAILED',
                        $id,
                        $errorFn()
                    ),
                    'warning'
                );
            } catch (\Throwable $e) {
                $failed++;

                $this->logTask(
                    Text::sprintf(
                        'PLG_TASK_DELTRASH_ITEM_FAILED',
                        $id,
                        $e->getMessage()
                    ),
                    'error'
                );
            }
        }

        return [
            'deleted' => $deleted,
            'failed'  => $failed,
        ];
    }
}

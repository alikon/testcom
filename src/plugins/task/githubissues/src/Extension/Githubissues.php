<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.Githubissues
 *
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\Githubissues\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Database\ParameterType;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Registry\Registry;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Githubissues Task Plugin.
 *
 * Scheduled task that queries the GitHub Search API for issue and pull request
 * counts on the joomla/joomla-cms repository and stores the totals in a
 * database table (#__github_issues) for later reporting/analysis.
 *
 * It is wired into the Joomla Scheduler via TaskPluginTrait and exposes a
 * single routine key: "githubissues".
 *
 * @since  1.0.0
 */
final class Githubissues extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * Map of task keys to their configuration.
     *
     * - "githubissues" registers a task using:
     *   - language prefix: PLG_TASK_GITHUBISSUES
     *   - parameters form: github_parameters
     *   - execution method: getGithubIssues()
     *
     * @var array<string, array<string, string>>
     *
     * @since 4.1.0
     */
    protected const TASKS_MAP = [
        'githubissues' => [
            'langConstPrefix' => 'PLG_TASK_GITHUBISSUES',
            'form'            => 'github_parameters',
            'method'          => 'getGithubIssues',
        ],
    ];

    /**
     * Auto-load plugin language files.
     *
     * @var  boolean
     *
     * @since 4.1.0
     */
    protected $autoloadLanguage = true;
    /**
     * Database connection injected by DatabaseAwareTrait.
     *
     * @var  \Joomla\Database\DatabaseInterface
     *
     * @since 4.1.0
     */
    protected $db;

    /**
     * Returns the events this plugin subscribes to.
     *
     * - onTaskOptionsList:    exposes the "githubissues" routine to the Scheduler.
     * - onExecuteTask:        dispatches execution to getGithubIssues().
     * - onContentPrepareForm: enhances the Scheduler task form with plugin params.
     *
     * @return array<string, string>
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
     * Task routine: fetch and store GitHub issue/PR statistics.
     *
     * For the joomla/joomla-cms repository, this method performs four GitHub
     * Search API calls (with per_page=1 to minimise payload) and reads the
     * "total_count" field from each response:
     *
     * - open issues   (type:issue, state:open)
     * - closed issues (type:issue, state:closed)
     * - open PRs      (type:pr,    state:open)
     * - closed PRs    (type:pr,    state:closed)
     *
     * The resulting counts are then inserted into the #__github_issues table as:
     *   - execution (current timestamp, SQL format)
     *   - openi     (open issues)
     *   - closedi   (closed issues)
     *   - openp     (open pull requests)
     *   - closedp   (closed pull requests)
     *
     * Any HTTP or database failure results in a KNOCKOUT status so the Scheduler
     * can mark the task as failed.
     *
     * @param   ExecuteTaskEvent  $event  The onExecuteTask event carrying task context.
     *
     * @return int  TaskStatus::OK on success, TaskStatus::KNOCKOUT on failure.
     *
     * @since 4.1.0
     */
    protected function getGithubIssues(ExecuteTaskEvent $event): int
    {
        //
        $params = $event->getArgument('params');
        $response = '';

        $options  = new Registry;
        $options->set('Content-Type', 'application/json');

        // Don't let the request take longer than 3 seconds to avoid timeout issues
        $apiurl = 'https://api.github.com/search/issues?q=repo:joomla/joomla-cms+type:issue+state:open&per_page=1';
    
        try
        {
            $response = HttpFactory::getHttp($options)->get($apiurl, [], 3);
        }	
        catch (\Exception $e)
        {
            return TaskStatus::KNOCKOUT;
        }

        $openi = json_decode($response->body);

        // Don't let the request take longer than 3 seconds to avoid timeout issues
        $apiurl = 'https://api.github.com/search/issues?q=repo:joomla/joomla-cms+type:issue+state:closed&per_page=1';
    
        try
        {
            $response = HttpFactory::getHttp($options)->get($apiurl, [], 3);
        }	
        catch (\Exception $e)
        {
            return TaskStatus::KNOCKOUT;
        }

        $closedi = json_decode($response->body);

        // Don't let the request take longer than 3 seconds to avoid timeout issues
        $apiurl = 'https://api.github.com/search/issues?q=repo:joomla/joomla-cms+type:pr+state:open&per_page=1';
    
        try
        {
            $response = HttpFactory::getHttp($options)->get($apiurl, [], 3);
        }	
        catch (\Exception $e)
        {
            return TaskStatus::KNOCKOUT;
        }

        $openp = json_decode($response->body);

        // Don't let the request take longer than 3 seconds to avoid timeout issues
        $apiurl = 'https://api.github.com/search/issues?q=repo:joomla/joomla-cms+type:pr+state:closed&per_page=1';
    
        try
        {
            $response = HttpFactory::getHttp($options)->get($apiurl, [], 3);
        }	
        catch (\Exception $e)
        {
            return TaskStatus::KNOCKOUT;
        }

        $closedp = json_decode($response->body);

        $date = Factory::getDate()->toSql();

        $query = $this->db->getQuery(true);
        $query->insert($this->db->quoteName('#__github_issues'))
            ->columns(
                [
                    $this->db->quoteName('execution'),
                    $this->db->quoteName('openi'),
                    $this->db->quoteName('closedi'),
                    $this->db->quoteName('openp'),
                    $this->db->quoteName('closedp'),
                ]
            )
            ->values(':execution, :openi, :closedi, :openp, :closedp')
            ->bind(':openi', $openi->total_count, ParameterType::INTEGER)
            ->bind(':closedi', $closedi->total_count, ParameterType::INTEGER)
            ->bind(':openp', $openp->total_count, ParameterType::INTEGER)
            ->bind(':closedp', $closedp->total_count, ParameterType::INTEGER)
            ->bind(':execution', $date);

        $this->db->setQuery($query);
        
        try
        {
            $this->db->execute();

        }
        catch (\Exception $e)
        {
            return TaskStatus::KNOCKOUT;
        }

        return TaskStatus::OK;
    }
}

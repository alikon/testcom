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

use Joomla\Database\ParameterType;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Registry\Registry;
use Joomla\CMS\Factory;

/**
 * Task plugin with routines that offer checks on files.
 * At the moment, offers a single routine to check and resize image files in a directory.
 *
 * @since  4.1.0
 */
class PlgTaskGithubIssues extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var string[]
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
     * @var boolean
     * @since 4.1.0
     */
    protected $autoloadLanguage = true;

    /**
     * Database object.
     *
     * @var    JDatabaseDriver
     * @since  __DEPLOY_VERSION__
     */
    protected $db;

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

        // Validate GitHub API JSON shape
        if (!$this->isValidGithubCountPayload($openi)) {
            Log::add('GitHubIssues: invalid open issues payload received from GitHub API', Log::WARNING);
            return TaskStatus::KNOCKOUT;
        }

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

        // Validate GitHub API JSON shape
        if (!$this->isValidGithubCountPayload($closedi)) {
            Log::add('GitHubIssues: invalid closed issues payload received from GitHub API', Log::WARNING);
            return TaskStatus::KNOCKOUT;
        }

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

        // Validate GitHub API JSON shape
        if (!$this->isValidGithubCountPayload($openp)) {
            Log::add('GitHubIssues: invalid open pull requests payload received from GitHub API', Log::WARNING);
            return TaskStatus::KNOCKOUT;
        }

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

        // Validate GitHub API JSON shape
        if (!$this->isValidGithubCountPayload($closedp)) {
            Log::add('GitHubIssues: invalid closed pull requests payload received from GitHub API', Log::WARNING);
            return TaskStatus::KNOCKOUT;
        }

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

    /**
     * Validate a GitHub /search/issues payload has a numeric total_count.
     *
     * @param  mixed  $decoded
     *
     * @return bool
     */
    private function isValidGithubCountPayload($decoded): bool
    {
        return \is_object($decoded)
            && property_exists($decoded, 'total_count')
            && \is_numeric($decoded->total_count);
    }
}

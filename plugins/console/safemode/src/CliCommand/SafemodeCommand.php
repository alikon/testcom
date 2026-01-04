<?php
namespace Joomla\Plugin\Console\Safemode\CliCommand;

defined('_JEXEC') or die;

use Joomla\Console\Command\AbstractCommand;
use Joomla\CMS\Factory;
use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Log\Log;

use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Plugin\System\Safemode\Helper\SafemodeHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects
/**
class SafemodeCommand extends AbstractCommand
{
    protected static $defaultName = 'safe:mode';

    protected function configure(): void
    {
        $this->setDescription('SafeMode CLI control: on|off|status');
        $this->addOption(
            'action',
            'a',
            \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
            'Which action to perform: on, off or status',
            'status'
        );
        $this->addOption(
            'dry-run',
            'd',
            \Symfony\Component\Console\Input\InputOption::VALUE_NONE,
            'Log actions only (no changes applied)'
        );
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $db        = Factory::getDbo();
        $stateFile = JPATH_ROOT . '/tmp/safemode-disabled.json';

        // Initialize logger
        Log::addLogger(
            ['text_file' => 'safemode.php'],
            Log::ALL,
            ['safemode']
        );

        

        $action  = $input->getOption('action') ?? 'status';
        $dryRun  = (bool) $input->getOption('dry-run');

        // Excluded plugins (never disable/enable these)
        $excluded = [
            ['folder' => 'system', 'element' => 'safemode'],
            ['folder' => 'console', 'element' => 'safemode'],
        ];
        $excludeSql = [];
        foreach ($excluded as $p) {
            $excludeSql[] = '(folder = ' . $db->quote($p['folder']) .
                            ' AND element = ' . $db->quote($p['element']) . ')';
        }

        // Core plugin IDs
        $coreIds = ExtensionHelper::getCoreExtensionIds();
        $coreIdsList = !empty($coreIds) ? implode(',', $coreIds) : '0';

        // Helper function to read disabled IDs from file
        $readDisabled = function () use ($stateFile) {
            return file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
        };

        // Helper function to write disabled IDs to file
        $writeDisabled = function ($ids) use ($stateFile) {
            file_put_contents($stateFile, json_encode($ids));
        };
        $clearDisabled = fn()=>file_exists($stateFile)?unlink($stateFile):null;

        $disabled = $readDisabled();
        $safeMode = !empty($disabled);

        switch ($action) {

            case 'status':
                $output->writeln('SafeMode: ' . ($safeMode ? 'ON' : 'OFF'));
                $output->writeln('Disabled plugins count: ' . count($disabled));
                foreach ($disabled as $id) {
                    $output->writeln("  - $id");
                }
                return 0;

            case 'on':
                if($safeMode){
                    $output->writeln('SafeMode already ON');
                    return 0;
                }

                // Get all non-core enabled plugins
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__extensions')
                    ->where('type=' . $db->quote('plugin'))
                    ->where('enabled=1')
                    ->where('protected=0')
                    ->where('extension_id NOT IN (' . $coreIdsList . ')');

                if ($excludeSql) {
                    $query->where('NOT (' . implode(' OR ', $excludeSql) . ')');
                }

                $db->setQuery($query);
                $plugins = $db->loadObjectList();

                if (!$plugins) {
                    $output->writeln('No non-core plugins to disable');
                    return 0;
                }

                $idsToDisable = [];
                foreach ($plugins as $plugin) {
                    $idsToDisable[] = (int) $plugin->extension_id;
                    $msg = "{$plugin->folder}:{$plugin->element} ({$plugin->name})";
                    $output->writeln($dryRun ? "[Dry-run] Would disable: $msg" : "Disabled: $msg");
                    Log::add($dryRun ? "[Dry-run] Would disable: $msg" : "Disabled: $msg",
                        $dryRun ? Log::INFO : Log::WARNING, 'safemode');
                }

                if (!$dryRun && $idsToDisable) {
                    $query = $db->getQuery(true)
                        ->update('#__extensions')
                        ->set('enabled=0')
                        ->where('extension_id IN (' . implode(',', $idsToDisable) . ')');
                    $db->setQuery($query)->execute();

                    // Save disabled plugins
                    $writeDisabled($idsToDisable);
                    $this->setAdminFlag(true);
                }

                $output->writeln('SafeMode is now ON');
                return 0;

            case 'off':
                if(!$safeMode){
                    $output->writeln('SafeMode already OFF');
                    return 0;
                }

                // Restore plugins that exist and are not excluded
                $query = $db->getQuery(true)
                    ->select('extension_id, folder, element, name')
                    ->from('#__extensions')
                    ->where('extension_id IN (' . implode(',', array_map('intval', $disabled)) . ')');

                if ($excludeSql) {
                    $query->where('NOT (' . implode(' OR ', $excludeSql) . ')');
                }

                $db->setQuery($query);
                $plugins = $db->loadObjectList();

                if ($plugins) {
                    $idsToRestore = [];
                    foreach ($plugins as $plugin) {
                        $idsToRestore[] = (int) $plugin->extension_id;
                        $msg = "{$plugin->folder}:{$plugin->element} ({$plugin->name})";
                        $output->writeln($dryRun ? "[Dry-run] Would restore: $msg" : "Restored: $msg");
                        Log::add($dryRun ? "[Dry-run] Would restore: $msg" : "Restored: $msg", Log::INFO, 'safemode');
                    }

                    if (!$dryRun && $idsToRestore) {
                        $query = $db->getQuery(true)
                            ->update('#__extensions')
                            ->set('enabled=1')
                            ->where('extension_id IN (' . implode(',', $idsToRestore) . ')');
                        $db->setQuery($query)->execute();
                        $clearDisabled();
                        $this->setAdminFlag(false);
                    }
                }

                $output->writeln('SafeMode is now OFF');
                return 0;
        }

        return 1;
    }

    private function setAdminFlag(bool $active): void
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('extension_id, params')
            ->from('#__extensions')
            ->where('element=' . $db->quote('safemode'))
            ->where('folder=' . $db->quote('system'));
        $db->setQuery($query);
        $plugin = $db->loadObject();
        if(!$plugin) return;

        $params = json_decode($plugin->params ?? '{}', true);
        $params['enabled_ui'] = $active ? 1 : 0;

        $query = $db->getQuery(true)
            ->update('#__extensions')
            ->set('params=' . $db->quote(json_encode($params)))
            ->where('extension_id=' . (int)$plugin->extension_id);
        $db->setQuery($query);
        $db->execute();
    }
}
 */
/**
 * SafeMode CLI Command
 *
 * @since  __DEPLOY_VERSION__
 */
final class SafemodeCommand extends AbstractCommand
{
    use DatabaseAwareTrait;

    /**
     * The default command name
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected static $defaultName = 'safe:mode';

    /**
     * @var CMSApplicationInterface
     * @since __DEPLOY_VERSION__
     */
    private $app;

    /**
     * SafeMode helper instance
     *
     * @var    SafemodeHelper
     * @since  __DEPLOY_VERSION__
     */
    private $helper;

   /**
     * SafemodeCommand constructor.
     *
     * @param  DatabaseInterface|null  $db  Optional DB instance, falls back to container/Factory.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(?DatabaseInterface $db = null)
    {
        parent::__construct();

        // Get DB from DI container or Factory if not injected
        if ($db === null) {
            // Joomla 4/5 style: use the container if available
            $container = Factory::getContainer();
            if ($container->has(DatabaseInterface::class)) {
                $db = $container->get(DatabaseInterface::class);
            } else {
                // Fallback
                $db = Factory::getDbo();
            }
        }

        $this->setDatabase($db);
    }

    /**
     * Configure the command.
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    protected function configure(): void
    {
        $this->setDescription('Manage SafeMode for plugins');
        $this->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action: on, off, or status', 'status');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Perform a dry run without making changes');
    }

    /**
     * Internal function to execute the command.
     *
     * @param   InputInterface   $input   The input to inject into the command.
     * @param   OutputInterface  $output  The output to inject into the command.
     *
     * @return  integer  The command exit code
     *
     * @since   __DEPLOY_VERSION__
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        $dryRun = $input->getOption('dry-run');

        // Initialize helper
        $this->helper = new SafemodeHelper($this->getDatabase());

        switch ($action) {
            case 'status':
                return $this->handleStatus($output);

            case 'on':
                return $this->handleOn($output, $dryRun);

            case 'off':
                return $this->handleOff($output, $dryRun);

            default:
                $output->writeln('<error>Invalid action. Use: on, off, or status</error>');
                return 1;
        }
    }

    /**
     * Handle the 'status' action
     *
     * @param   OutputInterface  $output  The output interface
     *
     * @return  int  Exit code
     *
     * @since   __DEPLOY_VERSION__
     */
    private function handleStatus(OutputInterface $output): int
    {
        $isActive = $this->helper->isSafeModeActive();
        
        if ($isActive) {
            $disabled = $this->helper->readDisabledIds();
            $output->writeln('<info>SafeMode is currently ON</info>');
            $output->writeln('Disabled plugin IDs: ' . implode(', ', $disabled));
        } else {
            $output->writeln('<info>SafeMode is currently OFF</info>');
        }

        return 0;
    }

    /**
     * Handle the 'on' action
     *
     * @param   OutputInterface  $output  The output interface
     * @param   bool            $dryRun  Dry run flag
     *
     * @return  int  Exit code
     *
     * @since   __DEPLOY_VERSION__
     */
    private function handleOn(OutputInterface $output, bool $dryRun): int
    {
        if ($this->helper->isSafeModeActive()) {
            $output->writeln('<comment>SafeMode already ON</comment>');
            return 0;
        }

        // Get plugins that will be disabled
        $plugins = $this->helper->getDisableablePlugins();

        if (empty($plugins)) {
            $output->writeln('<comment>No plugins to disable</comment>');
            return 0;
        }

        $output->writeln('<info>Plugins to disable:</info>');
        foreach ($plugins as $plugin) {
            $output->writeln(' - ' . $plugin->name . ' (ID: ' . $plugin->extension_id . ')');
        }

        if ($dryRun) {
            $output->writeln('<comment>[DRY RUN] No changes made</comment>');
            return 0;
        }

        // Disable plugins
        $this->helper->disablePlugins(false);
        
        // Set admin flag
        if (!$this->helper->setAdminFlag(true)) {
            $output->writeln('<warning>Warning: Failed to set admin UI flag</warning>');
        }

        $output->writeln('<info>SafeMode is now ON</info>');
        return 0;
    }

    /**
     * Handle the 'off' action
     *
     * @param   OutputInterface  $output  The output interface
     * @param   bool            $dryRun  Dry run flag
     *
     * @return  int  Exit code
     *
     * @since   __DEPLOY_VERSION__
     */
    private function handleOff(OutputInterface $output, bool $dryRun): int
    {
        if (!$this->helper->isSafeModeActive()) {
            $output->writeln('<comment>SafeMode already OFF</comment>');
            return 0;
        }

        // Get plugins that will be restored
        $disabled = $this->helper->readDisabledIds();
        $plugins = $this->helper->getRestorablePlugins($disabled);

        if (!empty($plugins)) {
            $output->writeln('<info>Plugins to restore:</info>');
            foreach ($plugins as $plugin) {
                $output->writeln(' - ' . $plugin->name . ' (ID: ' . $plugin->extension_id . ')');
            }
        } else {
            $output->writeln('<comment>No plugins to restore</comment>');
        }

        if ($dryRun) {
            $output->writeln('<comment>[DRY RUN] No changes made</comment>');
            return 0;
        }

        // Restore plugins (this also clears the state file)
        $this->helper->restorePlugins(false);

        // Clear admin flag
        if (!$this->helper->setAdminFlag(false)) {
            $output->writeln('<warning>Warning: Failed to clear admin UI flag</warning>');
        }

        $output->writeln('<info>SafeMode is now OFF</info>');
        return 0;
    }
}

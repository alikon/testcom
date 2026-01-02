<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Console.Safemode
 *
 * @copyright   (C) 2024 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Console\Safemode\CliCommand;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\System\Safemode\Helper\SafemodeHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

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
     * Instantiate the command.
     *
     * @param   DispatcherInterface  $dispatcher  The event dispatcher
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct(DispatcherInterface $dispatcher)
    {
        parent::__construct();
        $this->app = Factory::getApplication();
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

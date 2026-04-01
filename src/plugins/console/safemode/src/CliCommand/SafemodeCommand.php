<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Console.safemode
 *
 * @copyright   Copyright (C) 2026 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Console\Safemode\CliCommand;

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Console\Command\AbstractCommand;
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

        // Load plugin language files
        $app      = Factory::getApplication();
        $language = $app->getLanguage();
        $language->load('plg_console_safemode', JPATH_PLUGINS . '/console/safemode', 'en-GB', false, true);

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
        $action       = $input->getOption('action');
        $dryRun       = $input->getOption('dry-run');

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
                $output->writeln('<error>' . Text::_('PLG_CONSOLE_SAFEMODE_INVALID_ACTION') . '</error>');
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
            $output->writeln('<info>' . Text::_('PLG_CONSOLE_SAFEMODE_STATUS_ON') . '</info>');
            $output->writeln(\sprintf(Text::_('PLG_CONSOLE_SAFEMODE_DISABLED_IDS'), implode(', ', $disabled)));
        } else {
            $output->writeln('<info>' . Text::_('PLG_CONSOLE_SAFEMODE_STATUS_OFF') . '</info>');
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
            $output->writeln('<comment>' . Text::_('PLG_CONSOLE_SAFEMODE_ALREADY_ON') . '</comment>');
            return 0;
        }

        $plugins = $this->helper->getDisableablePlugins();

        if (empty($plugins)) {
            $output->writeln('<comment>' . Text::_('PLG_CONSOLE_SAFEMODE_NO_PLUGINS_DISABLE') . '</comment>');
            return 0;
        }

        $output->writeln('<info>' . Text::_('PLG_CONSOLE_SAFEMODE_PLUGINS_TO_DISABLE') . '</info>');
        foreach ($plugins as $plugin) {
            $output->writeln(' - ' . $plugin->name . ' (ID: ' . $plugin->extension_id . ')');
        }

        if ($dryRun) {
            $output->writeln('<comment>' . Text::_('PLG_CONSOLE_SAFEMODE_DRY_RUN') . '</comment>');
            return 0;
        }

        $this->helper->disablePlugins(false);

        if (!$this->helper->setAdminFlag(true)) {
            $output->writeln('<warning>' . Text::_('PLG_CONSOLE_SAFEMODE_WARN_ADMIN_FLAG') . '</warning>');
        }

        $output->writeln('<info>' . Text::_('PLG_CONSOLE_SAFEMODE_NOW_ON') . '</info>');
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
            $output->writeln('<comment>' . Text::_('PLG_CONSOLE_SAFEMODE_ALREADY_OFF') . '</comment>');
            return 0;
        }

        $disabled = $this->helper->readDisabledIds();
        $plugins  = $this->helper->getRestorablePlugins($disabled);

        if (!empty($plugins)) {
            $output->writeln('<info>' . Text::_('PLG_CONSOLE_SAFEMODE_PLUGINS_TO_RESTORE') . '</info>');
            foreach ($plugins as $plugin) {
                $output->writeln(' - ' . $plugin->name . ' (ID: ' . $plugin->extension_id . ')');
            }
        } else {
            $output->writeln('<comment>' . Text::_('PLG_CONSOLE_SAFEMODE_NO_PLUGINS_RESTORE') . '</comment>');
        }

        if ($dryRun) {
            $output->writeln('<comment>' . Text::_('PLG_CONSOLE_SAFEMODE_DRY_RUN') . '</comment>');
            return 0;
        }

        $this->helper->restorePlugins(false);

        if (!$this->helper->setAdminFlag(false)) {
            $output->writeln('<warning>' . Text::_('PLG_CONSOLE_SAFEMODE_WARN_CLEAR_FLAG') . '</warning>');
        }

        $output->writeln('<info>' . Text::_('PLG_CONSOLE_SAFEMODE_NOW_OFF') . '</info>');
        return 0;
    }
}

<?php
namespace Joomla\Plugin\Console\Safemode\CliCommand;

defined('_JEXEC') or die;

use Joomla\Console\Command\AbstractCommand;
use Joomla\CMS\Factory;
use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Log\Log;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.safemode
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Extension\ExtensionHelper;

final class PlgSystemSafemode extends CMSPlugin
{
    protected $app;

    // Path to store disabled plugin IDs
    protected $stateFile;

    public function __construct(&$subject, $config = [])
    {
        parent::__construct($subject, $config);
        $this->stateFile = JPATH_ROOT . '/tmp/safemode-disabled.json';
    }

    public function onAfterInitialise(): void
    {
        $app   = $this->app;
        $db    = Factory::getDbo();
        $input = $app->input;

        // Logger
        Log::addLogger(['text_file' => 'safemode.php'], Log::ALL, ['safemode']);

        // ------------------------------
        // Admin-only check for UI banner
        // ------------------------------
        $isAdminUI = $app->isClient('administrator');
        if ($isAdminUI) {
            $user = $app->getIdentity();
            if (!$user || !$user->authorise('core.admin')) {
                return;
            }
        }

        // ------------------------------
        // Determine SafeMode
        // ------------------------------
        $safeMode = (bool) $this->params->get('enabled_ui', 0);
        // Optional URL override
        $safeMode = $safeMode || $input->getBool('safemodeon', false);
        $dryRun   = (bool) $this->params->get('dry_run', 0) || $input->getBool('dryrun', false);

        // Excluded plugins
        $excluded = [
            ['folder'=>'system','element'=>'safemode'],
            ['folder'=>'console','element'=>'safemode'],
        ];

        $excludeSql = [];
        foreach ($excluded as $p) {
            $excludeSql[] = '(folder='.$db->quote($p['folder']).' AND element='.$db->quote($p['element']).')';
        }

        // Helper functions for reading/writing disabled plugins
        $readDisabled  = function () { 
            return file_exists($this->stateFile) ? json_decode(file_get_contents($this->stateFile), true) : []; 
        };
        $writeDisabled = function ($ids) { 
            file_put_contents($this->stateFile, json_encode($ids)); 
        };
        $clearDisabled = function () { 
            if (file_exists($this->stateFile)) unlink($this->stateFile); 
        };

        // Restore plugins if SafeMode off
        $disabled = $readDisabled();
        if (!$safeMode && !empty($disabled)) {
            $query = $db->getQuery(true)
                ->select('extension_id, folder, element, name')
                ->from('#__extensions')
                ->where('extension_id IN ('.implode(',', $disabled).')');

            if ($excludeSql) {
                $query->where('NOT ('.implode(' OR ', $excludeSql).')');
            }

            $db->setQuery($query);
            $restored = $db->loadObjectList();

            foreach ($restored as $plugin) {
                $msg = $plugin->folder.':'.$plugin->element.' ('.$plugin->name.')';
                Log::add($dryRun ? 'Dry-run: would restore plugin: '.$msg : 'Safemode OFF. Restored plugin: '.$msg, Log::INFO, 'safemode');
            }

            if (!$dryRun && $restored) {
                $query = $db->getQuery(true)
                    ->update('#__extensions')
                    ->set('enabled=1')
                    ->where('extension_id IN ('.implode(',', array_map('intval', $disabled)).')');
                $db->setQuery($query)->execute();
                $clearDisabled();
            }
        }

        // Disable non-core plugins if SafeMode on
        if ($safeMode && empty($disabled)) {
            $coreIds = ExtensionHelper::getCoreExtensionIds();
            $coreIdsList = $coreIds ? implode(',', $coreIds) : '0';

            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__extensions')
                ->where('type='.$db->quote('plugin'))
                ->where('enabled=1')
                ->where('extension_id NOT IN ('.$coreIdsList.')');

            if ($excludeSql) {
                $query->where('NOT ('.implode(' OR ', $excludeSql).')');
            }

            $db->setQuery($query);
            $plugins = $db->loadObjectList();

            if (!$plugins) {
                Log::add('Safemode ON. No plugins to disable.', Log::INFO, 'safemode');
            } else {
                $idsToDisable = [];
                foreach ($plugins as $plugin) {
                    $idsToDisable[] = $plugin->extension_id;
                    $msg = $plugin->folder.':'.$plugin->element.' ('.$plugin->name.')';
                    Log::add($dryRun ? 'Dry-run: would disable plugin: '.$msg : 'Safemode ON. Disabled plugin: '.$msg, $dryRun ? Log::INFO : Log::WARNING, 'safemode');
                }

                if (!$dryRun) {
                    $query = $db->getQuery(true)
                        ->update('#__extensions')
                        ->set('enabled=0')
                        ->where('extension_id IN ('.implode(',', $idsToDisable).')');
                    $db->setQuery($query)->execute();
                    $writeDisabled($idsToDisable);
                }
            }
        }

        // Show admin banner if SafeMode active
        $isActive = ($safeMode || !empty($readDisabled()));
        if ($isActive) {
            $this->showAdminBanner();
        }
    }

    private function showAdminBanner(): void
    {
        $this->app->enqueueMessage(
            '⚠️ SAFEMODE ACTIVE: All non-core plugins are disabled.',
            'warning'
        );
    }
}

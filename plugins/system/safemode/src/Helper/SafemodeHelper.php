<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.safemode
 *
 * @copyright   (C) 2024 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\Safemode\Helper;

use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * SafeMode Helper Class
 *
 * Provides shared functionality for SafeMode state management across
 * system plugin and CLI command implementations.
 *
 * @since  __DEPLOY_VERSION__
 */
class SafemodeHelper
{
    /**
     * Path to the state file
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    private string $stateFile;

    /**
     * Database instance
     *
     * @var    DatabaseInterface
     * @since  __DEPLOY_VERSION__
     */
    private DatabaseInterface $db;

    /**
     * List of plugins to exclude from SafeMode operations
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    private array $excludedPlugins = [
        'plg_system_safemode',
        'plg_console_safemode'
    ];

    /**
     * Flag to track if logger has been registered
     *
     * @var    bool
     * @since  __DEPLOY_VERSION__
     */
    private static bool $loggerRegistered = false;

    /**
     * Constructor
     *
     * @param   DatabaseInterface|null  $db         Database instance
     * @param   string|null            $stateFile  Custom state file path
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct(?DatabaseInterface $db = null, ?string $stateFile = null)
    {
        $this->db = $db ?? Factory::getDbo();
        $this->stateFile = $stateFile ?? JPATH_ROOT . '/tmp/safemode-disabled.json';
        $this->ensureLoggerRegistered();
    }

    /**
     * Ensure logger is registered only once
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    private function ensureLoggerRegistered(): void
    {
        if (!self::$loggerRegistered) {
            Log::addLogger(['text_file' => 'safemode.php'], Log::ALL, ['safemode']);
            self::$loggerRegistered = true;
        }
    }

    /**
     * Read disabled plugin IDs from state file
     *
     * @return  array  Array of integer plugin IDs
     *
     * @since   __DEPLOY_VERSION__
     */
    public function readDisabledIds(): array
    {
        if (!file_exists($this->stateFile)) {
            return [];
        }

        $contents = @file_get_contents($this->stateFile);
        if ($contents === false || $contents === '') {
            Log::add(
                'SafeMode: failed to read state file "' . $this->stateFile . '"',
                Log::WARNING,
                'safemode'
            );
            return [];
        }

        $decoded = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            Log::add(
                'SafeMode: invalid JSON in state file "' . $this->stateFile . '": ' . json_last_error_msg(),
                Log::WARNING,
                'safemode'
            );
            return [];
        }

        // Normalize to an array of integer IDs
        $ids = [];
        foreach ($decoded as $id) {
            if (is_int($id) || ctype_digit((string) $id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * Write disabled plugin IDs to state file
     *
     * @param   array  $ids  Array of plugin IDs
     *
     * @return  bool  True on success, false on failure
     *
     * @since   __DEPLOY_VERSION__
     */
    public function writeDisabledIds(array $ids): bool
    {
        // Ensure we store a de-duplicated list of integer IDs only
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $payload = json_encode($ids);

        if ($payload === false) {
            Log::add(
                'SafeMode: failed to encode disabled plugin state to JSON for "' . $this->stateFile . '".',
                Log::WARNING,
                'safemode'
            );
            return false;
        }

        if (@file_put_contents($this->stateFile, $payload) === false) {
            Log::add(
                'SafeMode: failed to write disabled plugin state file "' . $this->stateFile . '".',
                Log::WARNING,
                'safemode'
            );
            return false;
        }

        return true;
    }

    /**
     * Clear the state file
     *
     * @return  bool  True on success, false on failure
     *
     * @since   __DEPLOY_VERSION__
     */
    public function clearDisabledIds(): bool
    {
        if (!file_exists($this->stateFile)) {
            return true;
        }

        if (@unlink($this->stateFile) === false) {
            Log::add(
                'SafeMode: failed to delete disabled plugin state file "' . $this->stateFile . '".',
                Log::WARNING,
                'safemode'
            );
            return false;
        }

        return true;
    }

    /**
     * Check if SafeMode is currently active
     *
     * @return  bool  True if SafeMode is active
     *
     * @since   __DEPLOY_VERSION__
     */
    public function isSafeModeActive(): bool
    {
        $disabled = $this->readDisabledIds();
        return !empty($disabled);
    }

    /**
     * Get list of plugins that can be disabled
     *
     * @return  array  Array of plugin objects
     *
     * @since   __DEPLOY_VERSION__
     */
    public function getDisableablePlugins(): array
    {
        $coreIds = ExtensionHelper::getCoreExtensionIds();
        $coreIdsList = !empty($coreIds) ? implode(',', array_map('intval', $coreIds)) : '0';
        $excludeList = "'" . implode("','", $this->excludedPlugins) . "'";

        $query = $this->db->getQuery(true)
            ->select('extension_id, folder, element, name')
            ->from('#__extensions')
            ->where('type = ' . $this->db->quote('plugin'))
            ->where('enabled = 1')
            ->where('extension_id NOT IN (' . $coreIdsList . ')')
            ->where('CONCAT(' . $this->db->quote('plg_') . ', folder, ' . $this->db->quote('_') . ', element) NOT IN (' . $excludeList . ')');

        $this->db->setQuery($query);
        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Get list of plugins that can be restored
     *
     * @param   array  $disabledIds  Array of disabled plugin IDs
     *
     * @return  array  Array of plugin objects
     *
     * @since   __DEPLOY_VERSION__
     */
    public function getRestorablePlugins(array $disabledIds): array
    {
        if (empty($disabledIds)) {
            return [];
        }

        $idsList = implode(',', array_map('intval', $disabledIds));
        $excludeList = "'" . implode("','", $this->excludedPlugins) . "'";

        $query = $this->db->getQuery(true)
            ->select('extension_id, folder, element, name')
            ->from('#__extensions')
            ->where('type = ' . $this->db->quote('plugin'))
            ->where('enabled = 0')
            ->where('extension_id IN (' . $idsList . ')')
            ->where('CONCAT(' . $this->db->quote('plg_') . ', folder, ' . $this->db->quote('_') . ', element) NOT IN (' . $excludeList . ')');

        $this->db->setQuery($query);
        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Disable plugins and save state
     *
     * @param   bool  $dryRun  If true, only log actions without making changes
     *
     * @return  array  Array of disabled plugin objects
     *
     * @since   __DEPLOY_VERSION__
     */
    public function disablePlugins(bool $dryRun = false): array
    {
        $plugins = $this->getDisableablePlugins();

        if (empty($plugins)) {
            return [];
        }

        $names = array_map(fn($p) => $p->name, $plugins);
        Log::add('SafeMode: disabling plugins: ' . implode(', ', $names), Log::INFO, 'safemode');

        if (!$dryRun) {
            $ids = array_column($plugins, 'extension_id');
            $this->writeDisabledIds($ids);

            $idsList = implode(',', array_map('intval', $ids));
            $query = $this->db->getQuery(true)
                ->update('#__extensions')
                ->set('enabled = 0')
                ->where('extension_id IN (' . $idsList . ')');
            $this->db->setQuery($query);
            $this->db->execute();
        }

        return $plugins;
    }

    /**
     * Restore previously disabled plugins
     *
     * @param   bool  $dryRun  If true, only log actions without making changes
     *
     * @return  array  Array of restored plugin objects
     *
     * @since   __DEPLOY_VERSION__
     */
    public function restorePlugins(bool $dryRun = false): array
    {
        $disabled = $this->readDisabledIds();
        $plugins = $this->getRestorablePlugins($disabled);

        if (empty($plugins)) {
            // Even if no plugins to restore, clear state if not dry run
            if (!$dryRun && !empty($disabled)) {
                $this->clearDisabledIds();
            }
            return [];
        }

        $names = array_map(fn($p) => $p->name, $plugins);
        Log::add('SafeMode: restoring plugins: ' . implode(', ', $names), Log::INFO, 'safemode');

        if (!$dryRun) {
            $restoreIds = array_column($plugins, 'extension_id');
            $restoreIdsList = implode(',', array_map('intval', $restoreIds));
            
            $query = $this->db->getQuery(true)
                ->update('#__extensions')
                ->set('enabled = 1')
                ->where('extension_id IN (' . $restoreIdsList . ')');
            $this->db->setQuery($query);
            $this->db->execute();

            $this->clearDisabledIds();
        }

        return $plugins;
    }

    /**
     * Set or unset the admin UI flag for the SafeMode system plugin
     *
     * @param   bool  $active  Whether to activate the admin flag
     *
     * @return  bool  True on success, false on failure
     *
     * @since   __DEPLOY_VERSION__
     */
    public function setAdminFlag(bool $active): bool
    {
        $query = $this->db->getQuery(true)
            ->select('extension_id, params')
            ->from('#__extensions')
            ->where('type = ' . $this->db->quote('plugin'))
            ->where('folder = ' . $this->db->quote('system'))
            ->where('element = ' . $this->db->quote('safemode'));

        $this->db->setQuery($query);
        $plugin = $this->db->loadObject();

        if (!$plugin) {
            Log::add('SafeMode: system plugin not found', Log::WARNING, 'safemode');
            return false;
        }

        $params = json_decode($plugin->params, true) ?: [];
        $params['enabled_ui'] = $active ? 1 : 0;

        $query = $this->db->getQuery(true)
            ->update('#__extensions')
            ->set('params = ' . $this->db->quote(json_encode($params)))
            ->where('extension_id = ' . (int) $plugin->extension_id);

        $this->db->setQuery($query);
        
        try {
            $this->db->execute();
            return true;
        } catch (\Exception $e) {
            Log::add('SafeMode: failed to set admin flag: ' . $e->getMessage(), Log::ERROR, 'safemode');
            return false;
        }
    }

    /**
     * Get the state file path
     *
     * @return  string
     *
     * @since   __DEPLOY_VERSION__
     */
    public function getStateFile(): string
    {
        return $this->stateFile;
    }
}

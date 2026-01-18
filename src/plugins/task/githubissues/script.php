
<?php

/**
 * @package    Joomla.Plugin
 * @subpackage Task.Githubissues
 *
 * @author     Alikon <alikon@alikonweb.it>
 *
 * @copyright  (C) 2025, Alikonweb <https://wwww.alikonweb.it>. All rights reserved.
 * @license    GNU General Public License version 3 or later; see LICENSE
 * @link       https://www.alikonweb.it
 */

\defined('_JEXEC') or die();

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * Installer service provider of plg_system_magiclogin plugin.
 *
 * @since  1.0.0
 */
return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since  1.0.0
     */
    public function register(Container $container)
    {
        $container->set(
            InstallerScriptInterface::class,
            new class (
                $container->get(AdministratorApplication::class)
            ) implements InstallerScriptInterface {
                /**
                 * Minimum Joomla version to check
                 *
                 * @var    string
                 * @since  1.0.0
                 */
                protected $minimumJoomla = '5.0.0';

                /**
                 * Minimum PHP version to check
                 *
                 * @var    string
                 * @since  1.0.0
                */
                protected $minimumPhp = '8.1.0';

                /**
                 * The application object
                 *
                 * @var AdministratorApplication
                 * @since  1.0.0
                 */
                private AdministratorApplication $app;

                /**
                 * True when we have to update the searchable fields
                 *
                 * @var boolean
                 * @since  1.0.0
                 */
                private $updateSearchable = false;

                /**
                 * Constructor
                 *
                 * @param AdministratorApplication $app The application object
                 *
                 * @since  1.0.0
                 */
                public function __construct(AdministratorApplication $app)
                {
                    $this->app = $app;
                }

                /**
                 * Function called after the extension is installed.
                 *
                 * @param   InstallerAdapter  $adapter  The adapter calling this method
                 *
                 * @return  boolean  True on success
                 *
                 * @since  1.0.0
                 */
                public function install(InstallerAdapter $adapter): bool
                {
                    
                    $this->createTable();
                    return true;
                }

                /**
                 * Function called after the extension is updated.
                 *
                 * @param   InstallerAdapter  $adapter  The adapter calling this method
                 *
                 * @return  boolean  True on success
                 *
                 * @since  1.0.0
                 */
                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Function called after the extension is uninstalled.
                 *
                 * @param   InstallerAdapter  $adapter  The adapter calling this method
                 *
                 * @return  boolean  True on success
                 *
                 * @since  1.0.0
                 */
                public function uninstall(InstallerAdapter $adapter): bool
                {
                    
                    $this->dropTable();
                    return true;
                }

                /**
                 * Function called before extension installation/update/removal procedure commences.
                 *
                 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
                 * @param   InstallerAdapter  $adapter  The adapter calling this method
                 *
                 * @return  boolean  True on success
                 *
                 * @since  1.0.0
                 */
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type !== 'uninstall') {
                        // Check for the minimum PHP version before continuing
                        if (!empty($this->minimumPhp) && version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
                            Log::add(
                                Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPhp),
                                Log::WARNING,
                                'jerror'
                            );

                            return false;
                        }

                        // Check for the minimum Joomla version before continuing
                        if (!empty($this->minimumJoomla) && version_compare(JVERSION, $this->minimumJoomla, '<')) {
                            Log::add(
                                Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomla),
                                Log::WARNING,
                                'jerror'
                            );

                            return false;
                        }
                    }

                    return true;
                }

                /**
                 * Function called after extension installation/update/removal procedure commences.
                 *
                 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
                 * @param   InstallerAdapter  $adapter  The adapter calling this method
                 *
                 * @return  boolean  True on success
                 *
                 * @since  1.0.0
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    if (!\in_array($type, ['install', 'discover_install'])) {
                        return true;
                    }
                    // Auto-publish plugin
                    $adapter->extension->enabled = 1;
                    $adapter->extension->store();

                    echo Text::_('PLG_SYSTEM_MAGICLOGIN_INSTALLERSCRIPT_POSTFLIGHT_INSTALL');

                    return true;
                }
                /**
                 * Create the #__jo_safemode table.
                 *
                 * @return void
                 */
                private function createTable()
                {
                    try {
                        $db = Factory::getContainer()->get(DatabaseDriver::class);

                        // Check if the table already exists
                        $query = $db->getQuery(true)
                            ->select('COUNT(*)')
                            ->from($db->quoteName('information_schema.tables'))
                            ->where($db->quoteName('table_name') . ' = ' . $db->quote('#__github_issues'))
                            ->where($db->quoteName('table_schema') . ' = DATABASE()');

                        $db->setQuery($query);
                        $tableExists = $db->loadResult();

                        if (!$tableExists) {
                            // Create the #__github_issues table 
                            $query = 'CREATE TABLE IF NOT EXISTS `#__github_issues` (
                                      `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                                      `execution` datetime DEFAULT NULL ,
                                      `openi` smallint(6) NOT NULL DEFAULT 0,
                                      `closedi` smallint(6) NOT NULL DEFAULT 0,
                                      `openp` smallint(6) NOT NULL DEFAULT 0,
                                      `closedp` smallint(6) NOT NULL DEFAULT 0
                                      PRIMARY KEY (`id`),
                                      KEY `idx_execution` (`execution`)
                                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

                            $db->setQuery($query);
                            $db->execute();

                        }
                    } catch (\Exception $e) {
                        Factory::getApplication()->enqueueMessage('Error creating #__github_issues table: ' . $e->getMessage(), 'error');
                    }
                }

                /**
                 * Drop the #__github_issues table.
                 *
                 * @return void
                 */
                private function dropTable()
                {
                    try {
                        $db = Factory::getContainer()->get(DatabaseDriver::class);

                        // Drop the #__github_issues table
                        $query = 'DROP TABLE IF EXISTS #__github_issues';
                        $db->setQuery($query);
                        $db->execute();
                    } catch (\Exception $e) {
                        Factory::getApplication()->enqueueMessage('Error dropping #__github_issues table: ' . $e->getMessage(), 'error');
                    }
                }
            }
        );
    }
};

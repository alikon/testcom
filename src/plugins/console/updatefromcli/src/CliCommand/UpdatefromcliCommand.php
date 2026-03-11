<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Console.updatefromcli
 *
 * @copyright   Copyright (C) 2026 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Console\Updatefromcli\CliCommand;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Updater\Updater;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects
/**
 * Updatefromcli CLI Command
 *
 * @since  __DEPLOY_VERSION__
 */
final class UpdatefromcliCommand extends AbstractCommand
{
    /**
     * The default command name
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected static $defaultName = 'extension:update';

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

        $symfonyStyle->title('Extension Updates');

        if ($eid = $input->getOption('eid')) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            // Validate extension exists
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('extension_id') . ' = :eid')
                ->bind(':eid', $eid, \Joomla\Database\ParameterType::INTEGER);

            $db->setQuery($query);

            if (!(int) $db->loadResult()) {
                $symfonyStyle->error('Extension ID not found');
                return Command::FAILURE;
            }

			// Load core and installer language files
            $language = $this->getApplication()->getLanguage();
			$language->load('lib_joomla', JPATH_ADMINISTRATOR);
            $language->load('com_installer', JPATH_ADMINISTRATOR, 'en-GB', false, true);
            $language->load('com_installer', JPATH_ADMINISTRATOR, null, true);

            // Find updates.
            /** @var UpdateModel $model */
            $model = $this->getApplication()->bootComponent('com_installer')
                ->getMVCFactory()->createModel('Update', 'Administrator', ['ignore_request' => true]);

            // Purge the table before checking
            $model->purge();
            $model->findUpdates();
            $extensions = $model->getItems();
            $update     = [];

            foreach ($extensions as $extension) {
                if ($extension->extension_id === (int) $eid) {
                    $update[] = $extension;
                    break;
                }
            }

            if (0 === \count($update)) {
                $symfonyStyle->success('There are no available updates');
                return Command::SUCCESS;
            }

            $symfonyStyle->note('There are available updates to apply');

            $extensions = $this->getExtensionInfo($update);
            $symfonyStyle->table(['Extension ID', 'Name', 'Location', 'Type', 'Installed','Available', 'Folder'], $extensions);


            // Get the minimum stability.
            $params            = ComponentHelper::getComponent('com_installer')->getParams();
            $minimum_stability = (int) $params->get('minimum_stability', Updater::STABILITY_STABLE);
            $model->update([$update[0]->update_id], $minimum_stability);

            if ($model->getState('result')) {
                $symfonyStyle->note($update[0]->name . ' has been updated to ' . $update[0]->version);

                return Command::SUCCESS;
            }

            $symfonyStyle->error($update[0]->name . ' has not been updated to ' . $update[0]->version);

            return Command::FAILURE;
        }

        $symfonyStyle->error('Invalid argument supplied for command.');

        return Command::FAILURE;
    }

    /**
     * Transforms extension arrays into required form
     *
     * @param   array  $extensions  Array of extensions
     *
     * @return array
     *
     * @since __DEPLOY_VERSION__
     */
    protected function getExtensionInfo(array $extensions): array
    {
        $extInfo = [];

        foreach ($extensions as $extension) {
            $extInfo[] = [
                $extension->extension_id,
                $extension->name,
                $extension->client_translated,
                $extension->type,
                $extension->current_version,
                $extension->version,
                $extension->folder_translated,
            ];
        }

        return $extInfo;
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
        $this->addOption('eid', null, InputOption::VALUE_REQUIRED, 'The id of the extension');
        $help = "<info>%command.name%</info> command perform extension updates
		\nUsage: <info>php %command.full_name%</info>";

        $this->setDescription('Perform extension updates');
        $this->setHelp($help);
    }
}

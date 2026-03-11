<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Console.updatefromcli
 *
 * @copyright   Copyright (C) 2026 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Plugin\Console\Updatefromcli\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Application\ApplicationEvents;
use Joomla\Plugin\Console\Updatefromcli\CliCommand\UpdatefromcliCommand;

class UpdatefromcliConsolePlugin extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Register commands BEFORE the CLI app executes
            ApplicationEvents::BEFORE_EXECUTE => 'registerCommands',
        ];
    }

    public function registerCommands(): void
    {
        $this->getApplication()->addCommand(new UpdatefromcliCommand());
    }
}


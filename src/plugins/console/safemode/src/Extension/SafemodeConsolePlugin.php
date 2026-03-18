<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Console.safemode
 *
 * @copyright   Copyright (C) 2026 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Console\Safemode\Extension;

\defined('_JEXEC') or die;

use Joomla\Application\ApplicationEvents;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\Console\Safemode\CliCommand\SafemodeCommand;

class SafemodeConsolePlugin extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Register commands BEFORE the CLI app executes
            ApplicationEvents::BEFORE_EXECUTE => 'registerCommands',
            //\Joomla\Application\ApplicationEvents::BEFORE_EXECUTE => 'registerCommands',
        ];
    }

    public function registerCommands(): void
    {
        $this->getApplication()->addCommand(new SafemodeCommand());
        //$app = $this->getApplication();
        //$app->addCommand(new SafemodeCommand());
    }
}

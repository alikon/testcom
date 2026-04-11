<?php
/**
 * @package     Joomla.Module
 * @subpackage  Module.portfolio
 *
 * @copyright   Copyright (C) 2026 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\GithubPortfolio\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;

\defined("_JEXEC") or die;

class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        return $data;
    }
}

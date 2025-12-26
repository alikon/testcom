<?php

namespace Joomla\Module\GithubPortfolio\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;

defined("_JEXEC") or die;

class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();
        
        return $data;
    }
}

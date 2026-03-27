<?php

namespace Alikonweb\Module\Changelog\Site\Dispatcher;


use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;

\defined("_JEXEC") or die;

class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();
         
        // Get the GitHub URL from params
        $githubUrl = $data['params']->get('xml_url', 'https://raw.githubusercontent.com/alikon/testcom/main/src/modules/mod_changelog/changelog.xml');

        $helper = $this->getHelperFactory()->getHelper('ChangelogHelper');
        // Fetch the data
        $data['list'] = $helper->getChangelogData($githubUrl);

        return $data;
    }
}
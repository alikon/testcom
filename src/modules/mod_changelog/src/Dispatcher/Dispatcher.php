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
        //container = Factory::getContainer();
        //$helperFactory = $container->get(\Joomla\CMS\Extension\Service\Provider\HelperFactory::class);
        
        //$helper = $helperFactory->getHelper('ChangelogHelper', 'Alikonweb\\Module\\Changelog');
         
        // Get the GitHub URL from params
        $githubUrl = $data['params']->get('xml_url', 'https://raw.githubusercontent.com/alikon/testcom/main/src/plugins/task/deltrash/changelog.xml');

        $helper = $this->getHelperFactory()->getHelper('ChangelogHelper');
        // Fetch the data
        $list = $helper->getChangelogData($githubUrl);
        $data['list'] = $list;
        return $data;
    }
}
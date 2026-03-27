<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;

$module = $displayData['module'] ?? null;
$params = $displayData['params'] ?? null;

if (!$params) {
    return;
}
// Get the Helper Factory directly from the Container
// Use the exact namespace you defined in provider.php
$container = Factory::getContainer();
$helperFactory = $container->get(\Joomla\CMS\Extension\Service\Provider\HelperFactory::class);
$helper = $helperFactory->getHelper('ChangelogHelper', 'Alikonweb\\Module\\Changelog');

// Get the GitHub URL from params
$githubUrl = $params->get('xml_url', 'https://raw.githubusercontent.com/alikon/testcom/main/src/plugins/task/deltrash/changelog.xml');

// Fetch the data
$list = $helper->getChangelogData($githubUrl);
var_dump ($list);
// Ensure $list is an array
if (empty($list)) {
    $list = [];
}

// Render the layout
require ModuleHelper::getLayoutPath('mod_changelog', $params->get('layout', 'default'));
<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  mod_custom
 *
 * @copyright   (C) 2009 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Module\Github\Site\Helper\GithubHelper;

$data = GithubHelper::getData();
//var_dump($data['openi']);
require ModuleHelper::getLayoutPath('mod_github', $params->get('layout', 'default'));

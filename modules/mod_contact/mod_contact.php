<?php

/**
 * @package     Joomla.Site
 * @subpackage  mod_contact
 *
 * @copyright   (C) 2023 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Module\Contact\Site\Helper\ContactHelper;

$cacheid                   = md5($module->id);
$cacheparams               = new \stdClass();
$cacheparams->cachemode    = 'id';
$cacheparams->class        = ContactHelper::class;
$cacheparams->method       = 'getList';
$cacheparams->methodparams = $params;
$cacheparams->modeparams   = $cacheid;

$list = ModuleHelper::moduleCache($module, $params, $cacheparams);


//$list = ContactHelper::getList($params);

require ModuleHelper::getLayoutPath('mod_contact', $params->get('layout', 'default'));

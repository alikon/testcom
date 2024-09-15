<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  mod_github
 *
 * @copyright   (C) 2009 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

if (!$list) {
    return;
}

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
//$wa = $this->app->getDocument()->getWebAssetManager();
HTMLHelper::_('script', 'https://cdn.jsdelivr.net/npm/apexcharts');

$issues= [];
$items = array_slice($list, -2);
$perci = (($items[0]->value / $items[1]->value) * 100) -100;
$percp = (($items[0]->value2 / $items[1]->value2) * 100) -100;
//$issues->openissue= new stdClass();
// Pass data to javascript
Factory::getApplication()->getDocument()->addScriptOptions(
    'a-export',
    [
        'gitdata' => $list,
        'percenti' => round($perci, 2),
        'percentp' => round($percp, 2),
    ]
);
$wa->registerAndUseScript('mod_github', 'mod_github/Timeseries.js');
$canvasId = $module->id;
$interval = $params->get('timeframe1', 0);
$endpoint = $params->get('endpoint', 'cms_version');

?>
<div class="result"></div>
<div id="chartissues"></div>
<div id="chartprs"></div>
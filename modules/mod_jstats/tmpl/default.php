<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  mod_custom
 *
 * @copyright   (C) 2009 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
//$wa = $this->app->getDocument()->getWebAssetManager();
HTMLHelper::_('script', 'https://cdn.jsdelivr.net/npm/apexcharts');

$wa->registerAndUseScript('mod_jstats', 'mod_jstats/jdev-stats.js');
$canvasId = $module->id;
$interval = $params->get('timeframe1', 'year');
$endpoint = $params->get('endpoint', 'cms_version');

?>
<div class="result"></div>
<div id="chart"></div>
<div class="row justify-content-center">
    <div class="col-6">
        <label>
            Choose a timeframe:
            <select class="ice-cream" name="ice-cream">
                <option value="0">Select One …</option>
                <option value="1">Yesterday</option>
                <option value="7">Last Week</option>
                <option value="30">Last Month</option>
                <option value="120">Last Quarter</option>
                <option value="180">Last Semester</option>
                <option value="360">Last year</option>
            </select>
        </label>
    </div>
    <div class="col-6">
        <label>
            Choose a series:
            <select class="series" name="series">
                <option value="cms_version">Select One…</option>
                <option value="php_version">PHP</option>
                <option value="cms_version">Joomla</option>
                <option value="db_type">Database</option>
            </select>
        </label>
    </div>
</div>
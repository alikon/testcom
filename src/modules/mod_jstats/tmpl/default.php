<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_jstats
 *
 * @copyright   (C) 2024 Alikonweb.it. <https://www.alikonweb.it>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * @var \Joomla\CMS\WebAsset\WebAssetManager $wa
 * @var \Joomla\Registry\Registry             $params
 * @var object                                $module
 */
/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$app = Factory::getApplication();
$wa  = $app->getDocument()->getWebAssetManager();

$wa->registerAndUseScript(
    'apexcharts',
    'https://cdn.jsdelivr.net/npm/apexcharts',
    ['version' => 'auto'],
    ['defer' => true]
);
$wa->registerAndUseScript('mod_jstats', 'mod_jstats/jdev-stats.js', [], ['defer' => true], ['apexcharts']);

// then pass both values to the JS so it does not rely on hardcoded defaults.
$intervalMap = [
    'alltime'  => 0,
    'day'      => 1,
    'week'     => 7,
    'month'    => 30,
    'quarter'  => 120,
    'semester' => 180,
    'year'     => 360,
    '2year'    => 720,
];
$intervalParam = $params->get('timeframe1', 'year');
$intervalValue  = $intervalMap[$intervalParam] ?? 360;
$endpoint       = $params->get('endpoint', 'cms_version');

// Pass config to JS via a data attribute on the wrapper (avoids inline JS).
$canvasId = 'mod-jstats-' . (int) $module->id;
?>

<div class="mod-jstats"
     id="<?php echo $canvasId; ?>"
     data-timeframe="<?php echo (int) $intervalValue; ?>"
     data-endpoint="<?php echo htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="result"></div>

    <div id="chart-<?php echo (int) $module->id; ?>" style="min-height:350px;"></div>

    <div class="row justify-content-center mt-3">
        <div class="col-6">
            <label for="ice-cream-<?php echo (int) $module->id; ?>">
                Choose a timeframe:
                <select class="ice-cream form-select"
                        id="ice-cream-<?php echo (int) $module->id; ?>"
                        name="ice-cream">
                    {{-- Bug fix: no "Select One" placeholder — default is driven by $intervalValue --}}
                    <option value="0"<?php echo $intervalValue === 0   ? ' selected' : ''; ?>>All time</option>
                    <option value="1"<?php echo $intervalValue === 1   ? ' selected' : ''; ?>>Yesterday</option>
                    <option value="7"<?php echo $intervalValue === 7   ? ' selected' : ''; ?>>Last Week</option>
                    <option value="30"<?php echo $intervalValue === 30  ? ' selected' : ''; ?>>Last Month</option>
                    <option value="120"<?php echo $intervalValue === 120 ? ' selected' : ''; ?>>Last Quarter</option>
                    <option value="180"<?php echo $intervalValue === 180 ? ' selected' : ''; ?>>Last Semester</option>
                    <option value="360"<?php echo $intervalValue === 360 ? ' selected' : ''; ?>>Last Year</option>
                    <option value="720"<?php echo $intervalValue === 720 ? ' selected' : ''; ?>>Last 2 Years</option>
                </select>
            </label>
        </div>
        <div class="col-6">
            <label for="series-<?php echo (int) $module->id; ?>">
                Choose a series:
                <select class="series form-select"
                        id="series-<?php echo (int) $module->id; ?>"
                        name="series">
                    <option value="cms_version"<?php echo $endpoint === 'cms_version' ? ' selected' : ''; ?>>Joomla</option>
                    <option value="php_version"<?php echo $endpoint === 'php_version' ? ' selected' : ''; ?>>PHP</option>
                    <option value="db_type"<?php echo $endpoint === 'db_type'      ? ' selected' : ''; ?>>Database</option>
                </select>
            </label>
        </div>
    </div>
</div>

<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  mod_custom
 *
 * @copyright   (C) 2009 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
use Joomla\CMS\Factory;

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('mod_github.admin', 'mod_github/barchart.css', [], [],[]);
//$wa->registerAndUseScript('mod_github.css', 'mod_github/sparkline.js', [], [],[]);
?>
<div id="chart"></div>
<?php

		$js = <<< JS
			document.addEventListener("DOMContentLoaded", () => {(function() {
			//------------------------------------------//
				var chartjson = {
					"title": "Joomla version adoption",
					"data": [
						{
							"name": "4.2",
							"score": 0.12
						},
						{
							"name": "4.1",
							"score": 1.48
						},
						{
							"name": "4.0",
							"score": 3.24
						},
						{
							"name": "3.10",
							"score": 6.04
						},
						{
							"name": "3.9",
							"score": 27.66
						},
						{
							"name": "3.8",
							"score": 17.92
						},
						{
							"name": "3.7",
							"score": 8.09
						},
						{
							"name": "3.6",
							"score": 23.12
						},
						{
							"name": "3.5",
							"score": 12.38
						}
					],
					"xtitle": "Secured Marks",
					"ytitle": "Marks",
					"ymax": 100,
					"ykey": 'score',
					"xkey": "name",
					"prefix": "%"
				}

				//chart colors
				var colors = ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen'];

				//constants
				var TROW = 'tr',
					TDATA = 'td';

				var chart = document.createElement('div');
				//create the chart canvas
				var barchart = document.createElement('table');
				//create the title row
				var titlerow = document.createElement(TROW);
				//create the title data
				var titledata = document.createElement(TDATA);
				//make the colspan to number of records
				titledata.setAttribute('colspan', chartjson.data.length + 1);
				titledata.setAttribute('class', 'charttitle');
				titledata.innerText = chartjson.title;
				titlerow.appendChild(titledata);
				barchart.appendChild(titlerow);
				chart.appendChild(barchart);

				//create the bar row
				var barrow = document.createElement(TROW);

				//lets add data to the chart
				for (var i = 0; i < chartjson.data.length; i++) {
					barrow.setAttribute('class', 'bars');
					var prefix = chartjson.prefix || '';

				//create the bar data
				var bardata = document.createElement(TDATA);
				var bar = document.createElement('div');
				bar.setAttribute('class', colors[i]);
				bar.style.height = chartjson.data[i][chartjson.ykey] + prefix;
				bardata.innerText = chartjson.data[i][chartjson.ykey] + prefix;

				bardata.appendChild(bar);
				barrow.appendChild(bardata);
			}

			//create legends
			var legendrow = document.createElement(TROW);
			var legend = document.createElement(TDATA);
			legend.setAttribute('class', 'legend');
			legend.setAttribute('colspan', chartjson.data.length);

			//add legend data
			for (var i = 0; i < chartjson.data.length; i++) {
				var legbox = document.createElement('span');
				legbox.setAttribute('class', 'legbox');
				var barname = document.createElement('span');
				barname.setAttribute('class', colors[i] + ' xaxisname');
				var bartext = document.createElement('span');
				bartext.innerText = chartjson.data[i][chartjson.xkey];
				legbox.appendChild(barname);
				legbox.appendChild(bartext);
				legend.appendChild(legbox);
			}
			barrow.appendChild(legend);
			barchart.appendChild(barrow);
			barchart.appendChild(legendrow);
			chart.appendChild(barchart);
			document.getElementById('chart').innerHTML = chart.outerHTML;
			//-------------------------------//
			})();
		});
		JS;

		$wa->addInlineScript($js);

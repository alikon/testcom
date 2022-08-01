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
use Joomla\CMS\HTML\HTMLHelper;

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
HTMLHelper::_('script', 'https://cdn.jsdelivr.net/npm/chart.js');
//$wa->registeAndUseScript('mod_matrix.admin', 'mod_matrix/admin-matrix.js');
$canvasId = $module->id;
$interval = $params->get('timeframe1', 0);
$endpoint = $params->get('endpoint', 'cms_version');

switch ($interval) {
	case 'day':
		$days=1;
		$title="Yesterday";
		break;
	case 'week':
		$days=7;
		$title="Last Week";
		break;
	case 'month':
		$days=30;
		$title="Last Month";
		break;
	case 'quarter':
		$days=120;
		break;
		$title="Last Quarter";
	case 'semester':
		$days=180;
		$title="Last Semester";
		break;
	case 'year':
		$days=360;
		$title="Last Year";
		break;
	case '2year':
		$days=720;
		$title="Last 2 Years";
		break;
	default:
		$days=0;
		$title="All Time";
		break;
}
?>

<div class="mod-custom custom">
	<div class="card">
		<div class="card-body">
			<canvas id="<?php echo $canvasId; ?>"></canvas>
		</div>
	</div>
</div>
<div class="card-footer">
</div>

	<?php
		

		$js = <<< JS
			//(function() {
			document.addEventListener("DOMContentLoaded", () => {
					// DOM elements
					
					
					async function updateQuote() {
						
							// Fetch a random quote from the Quotable API
							days =$days;
							const response = await fetch("https://developer.joomla.org/stats/$endpoint?timeframe=" + days);
							const data = await response.json();
							
							if (response.ok) {
								console.log(data.data.$endpoint);
								dataFilter = data.data.$endpoint
								
								if ('$endpoint'==='cms_version') {
									delete dataFilter["3.0"];
									delete dataFilter["3.1"];
									delete dataFilter["3.2"];
									delete dataFilter["3.3"];
									delete dataFilter["3.4"];

									console.log(typeof dataFilter);
									let arrSort ={}
									//console.log('pos310',dataFilter.indexOf("3.10"));
									let mkey;
									let myvalue;
									for (var i in dataFilter)
									{
										console.log('key',i);
										if (i == '3.10'){
											mykey = i
											myvalue = dataFilter[i]
											//alert('dataFilter[\''+i+'\'] is ' + dataFilter[i])
											//console.log('so310',mykey);
											continue
										}
										if (i == "4.0"){
											arrSort["3.10"] = myvalue
											arrSort[i] = dataFilter[i]
											//console.log('so40',arrSort[i]);
											continue
										}
										arrSort[i] = dataFilter[i]
									}
									//console.log('pos310',arrSort);
									dataFilter=arrSort;
								}

								val2 = Object.keys(dataFilter);
								key2 = Object.values(dataFilter);
								//if (val2 =0) delete dataFilter[key2];
								console.log('key2  - ' + key2) // key - value
								console.log('val2  - ' + val2) // key - value
								//console.log('count()',dataFilter.length)

						//
							} else {
				
							console.log(data);
							}
							const NAMED_COLORS =  {
								red: 'rgba(255, 99, 132, 0.8)',
								orange: 'rgba(255, 159, 64, 0.8)',
								yellow: 'rgba(255, 205, 86, 0.8)',
								green: 'rgba(75, 192, 192, 0.8)',
								blue: 'rgba(54, 162, 235, 0.8)',
								purple: 'rgba(153, 102, 255, 0.8)',
								grey: 'rgba(201, 203, 207, 0.8)'
							};
							const mydata = {
								labels: val2,
								datasets: [{
									label: 'Share %',
									//data: [8, 1.5, 3.2, 6, 27.7, 17.9],
									data : key2,
									backgroundColor: [
										NAMED_COLORS.blue,
										NAMED_COLORS.green,
										NAMED_COLORS.purple,
										NAMED_COLORS.yellow,
										NAMED_COLORS.orange,
										NAMED_COLORS.red,
										NAMED_COLORS.grey,
									],
								}]
							};
							const config = {
								type: 'bar',
								data: mydata,
								options: {
									responsive: true,
									plugins: {
										legend: {
											position: 'none',
										},
										title: {
											display: true,
											text: '$title'
										}
									}
								}
							};
							const myChart = new Chart(
								document.getElementById($canvasId),
								config
							);

					}
					// call updateQuote once when page loads
					updateQuote();
				});
			//})();
		JS;
		
		$wa->addInlineScript($js);

	?>


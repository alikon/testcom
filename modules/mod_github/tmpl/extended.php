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
$wa->registerAndUseStyle('mod_github.admin', 'mod_github/sparkline.css', [], [],[]);
$wa->registerAndUseScript('mod_github.css', 'mod_github/sparkline.js', [], [],[]);

//$datio = implode(',', [67,89,90,12,45,34,98,23,78,32,121,84]);
$datio = implode(',', $data['openi']);
$datic = implode(',', $data['closedi']);
$datpo = implode(',', $data['openp']);
$datpc = implode(',', $data['closedp']);
$issueopen   = implode(',', $data['issueopen']);
$issueclosed = implode(',', $data['issueclosed']);
$pullopen    = implode(',', $data['pullopen']);
$pullclosed  = implode(',', $data['pullclosed']);
//var_dump($data['issueopen']);exit();
$showoi = $params->get('showoi', '');
$showci = $params->get('showci', '');
$showop = $params->get('showop', '');
$showcp = $params->get('showcp', '');
?>
<?php if ($showoi) : ?>
<div class="table-responsive">
	<table class="table table-bordered">
		<tr>
			<td>Issue Open</td>
		</tr>
		<tr>
			<td>
				<div class="spark">
					<svg class="btc" width="480" height="100" stroke-width="2" stroke="red" fill="rgba(255, 0, 0, .2)"></svg>
					<span class="xtooltip" hidden="true"></span>
				</div>
			</td>
		</tr>
	</table>
</div>
<?php endif; ?>
<?php if ($showci) : ?>
<div class="table-responsive">
	<table class="table table-bordered">
	<tr>
		<td><div class="card-footer">Issue Closed</div></td>
	</tr>
	<tr>
		<td>
			<div class="spark">
				<svg class="eth" width="480" height="100" stroke-width="2" stroke="green" fill="rgba(0, 255, 0, .2)"></svg>
				<span class="xtooltip" hidden="true"></span>
			</div>
		</td>
	</tr>
	</table>
</div>
<?php endif; ?>
<?php if ($showop) : ?>
<div class="table-responsive">
	<table class="table table-bordered">
	<tr>
		<td>Pull Open</td>
	</tr>
	<tr>
		<td>
			<div class="spark">
				<svg class="po" width="480" height="100" stroke-width="2" stroke="yellow" fill="rgba(255, 255, 0, .2)"></svg>
				<span class="xtooltip" hidden="true"></span>
			</div>
		</td>
	</tr>
	</table>
</div>
<?php endif; ?>
<?php if ($showcp) : ?>
<div class="table-responsive">
	<table class="table table-bordered">
	<tr>
		<td>Pull Closed</td>
	</tr>
	<tr>
		<td>
			<div class="spark">
				<svg class="pc" width="320" height="100" stroke-width="2" stroke="blue" fill="rgba(0, 0, 255, .2)"></svg>
				<span class="xtooltip" hidden="true"></span>
			</div>
		</td>
	</tr>
	</table>
</div>
<?php endif; ?>
<!--
<div>
	Open &nbsp; <svg class="sparkline sparkline--red sparkline--filled" width="100" height="30" stroke-width="3"></svg>
</div>
<div>
	Closed <svg class="sparkline sparkline--blue sparkline--filled" width="100" height="30" stroke-width="3"></svg>
</div>
<div>
	Pull  &nbsp; &nbsp; &nbsp;<svg class="sparkline sparkline--pink sparkline--filled" width="100" height="30" stroke-width="3"></svg>
</div>
<div>
	Fixed &nbsp;&nbsp; <svg class="sparkline sparkline--green sparkline--filled" width="100" height="30" stroke-width="3"></svg>
</div>
-->
<?php

		$js = <<< JS
			document.addEventListener("DOMContentLoaded", () => {(function() {
				//------------------------------------------//
				function findClosest(target, tagName) {
					if (target.tagName === tagName) {
						return target;
					}

					while (target = target.parentNode) {
						if (target.tagName === tagName) {
							break;
						}
					}

					return target;
				}
				var btc = [$issueopen];
				var btc2 = [
					{ name: "Bitcoin", date: "2017-01-01", value: 967.6 },
					{ name: "Bitcoin", date: "2017-02-01", value: 957.02 },
					{ name: "Bitcoin", date: "2017-03-01", value: 1190.78 },
					{ name: "Bitcoin", date: "2017-04-01", value: 1071.48 },
					{ name: "Bitcoin", date: "2017-05-01", value: 1354.21 },
					{ name: "Bitcoin", date: "2017-06-01", value: 2308.08 },
					{ name: "Bitcoin", date: "2017-07-01", value: 2483.5 },
					{ name: "Bitcoin", date: "2017-08-01", value: 2839.18 },
					{ name: "Bitcoin", date: "2017-09-01", value: 4744.69 },
					{ name: "Bitcoin", date: "2017-10-01", value: 4348.09 },
					{ name: "Bitcoin", date: "2017-11-01", value: 6404.92 }
				];

				var eth = [$issueclosed];
				var eth2 = [
					{ name: "Ethereum", date: "2017-01-01", value: 8.3 },
					{ name: "Ethereum", date: "2017-02-01", value: 10.57 },
					{ name: "Ethereum", date: "2017-03-01", value: 15.73 },
					{ name: "Ethereum", date: "2017-04-01", value: 49.51 },
					{ name: "Ethereum", date: "2017-05-01", value: 85.69 },
					{ name: "Ethereum", date: "2017-06-01", value: 226.51 },
					{ name: "Ethereum", date: "2017-07-01", value: 246.65 },
					{ name: "Ethereum", date: "2017-08-01", value: 213.87 },
					{ name: "Ethereum", date: "2017-09-01", value: 386.61 },
					{ name: "Ethereum", date: "2017-10-01", value: 303.56 },
					{ name: "Ethereum", date: "2017-11-01", value: 298.21 }
				];

				var po = [$pullopen];
				var pc = [$pullclosed];
				var options = {
					onmousemove(event, datapoint) {
						var svg = findClosest(event.target, "svg");
						var tooltip = svg.nextElementSibling;
						var data = new Date(datapoint.date).toUTCString().replace(/^.*?, (.*?) \d{2}:\d{2}:\d{2}.*?$/, "$1");
						//var data = new Date(datapoint.date);
						
						//var dato = datapoint.value.toFixed(2);
						var dato = datapoint.realvalue;
						var offsetY = event.offsetY;
						var offsetX = event.offsetX + 20;
						tooltip.hidden = false;
						tooltip.textContent =data + ": " + dato;
						//tooltip.textContent = "USD";
						tooltip.style.top = offsetY + "px";
						tooltip.style.left = offsetX + "px";
					},
					onmouseout() {
						var svg = findClosest(event.target, "svg");
						var tooltip = svg.nextElementSibling;

						tooltip.hidden = true;
					}
				};

				if($showoi) sparkline.sparkline(document.querySelector(".btc"), btc, options);
				if($showci) sparkline.sparkline(document.querySelector(".eth"), eth, options);
				if($showop) sparkline.sparkline(document.querySelector(".po"), po, options);
				if($showcp) sparkline.sparkline(document.querySelector(".pc"), pc, options);
				//-------------------------------//
			})();
			});
		JS;
		
		$wa->addInlineScript($js);

	?>
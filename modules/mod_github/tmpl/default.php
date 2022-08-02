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
?>
<table>
	<thead>
		<tr>
			<th scope="col">Open Issues</th>
			<th scope="col">Closed Issues</th>
			<th scope="col">Open Pr's</th>
			<th scope="col">Closed Pr's</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><svg class="sparkline sparkline--red sparkline--filled" width="100" height="30" stroke-width="3"></svg></td>
			<td><svg class="sparkline sparkline--blue sparkline--filled" width="100" height="30" stroke-width="3"></svg></td>
			<td><svg class="sparkline sparkline--pink sparkline--filled" width="100" height="30" stroke-width="3"></svg></td>
			<td><svg class="sparkline sparkline--green sparkline--filled" width="100" height="30" stroke-width="3"></svg></td>
		</tr>
	</tbody>
</table>
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
				let issueso = [$datio];
				let issuesc = [$datic];
				let pullo = [$datpo];
				let pullc = [$datpc];
				//const dati = vettore.split(",");
				document.querySelectorAll(".sparkline--red").forEach(function(svg) {
					sparkline.sparkline(svg, issueso);
				});
				document.querySelectorAll(".sparkline--blue").forEach(function(svg) {
					sparkline.sparkline(svg, issuesc);
				});
				document.querySelectorAll(".sparkline--pink").forEach(function(svg) {
					sparkline.sparkline(svg, pullo);
				});
				document.querySelectorAll(".sparkline--green").forEach(function(svg) {
					sparkline.sparkline(svg, pullc);
				});
			//});
			})();
			});
		JS;
		
		$wa->addInlineScript($js);

	?>
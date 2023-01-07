<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  mod_openaidalle
 *
 * @copyright   Copyright (C) 2021 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
use Joomla\CMS\Factory;


/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

$canvasId  = $module->id;
$prompt    = $params->get('prompt', 'A puppy driving a motorbike');
$dimension = $params->get('dimension', '256x256');
$token     = $params->get('apitoken', 'sk-xxxx');
$folder    = $params->get('folder', '../images');

?>
<div class="container">
	<div class="row">
		<div class="col-lg-12">
			<div class="blu">
				<span id="load" style="display: none">
					<i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>
				</span>
			</div>
			<img id="dalle" src="" class="img-fluid" alt="">
			
			<h3>Prompt</h3>
			<p id="prompt-text">
				<?php echo $prompt; ?>
			</p>
			<p id="saved-msg">
			</p>
			<p>
			<div class="btn-group">
				<div id="dall-e" class='btn btn-danger'><i class='icon-image fa-lg' aria-hidden='true'></i>&nbsp; Generate </div>
				<div id="write-e" class='btn btn-success' style="display: none"><i class='icon-save fa-lg' aria-hidden='true'></i>&nbsp; Save </div>
				<div class="blu">
				<span id="save" style="display: none">
					<i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>
				</span>
			</div>
			</div>
			</p>
		</div>
	</div>
</div>
<?php
$js = <<< JS
		//
		function showLoader(id){
			var loader = document.getElementById(id);
			loader.style.display = 'block';
		}
		function hideLoader(id){
			var loader = document.getElementById(id);
			loader.style.display = 'none';
		}
		//
		function OpenaiFetchAPI() {
			showLoader('load');
			// mock

			var url = "https://api.openai.com/v1/images/generations";
			var bearer = 'Bearer ' + "$token"

			fetch(url, {
				method: 'POST',
				headers: {
					'Authorization': bearer,
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					"prompt": "$prompt",
					"n": 1,
					"size": "$dimension",
					"response_format": "b64_json"
				})
			}).then(response => {
				return response.json()
			}).then(data=>{
				console.log('QUI',data.data[0].b64_json)
				document.getElementById("dalle").src = 'data:image/png;base' + '64,' + data.data[0].b64_json
				hideLoader('load')
				showLoader('write-e');
			}).catch(error => {
				console.log('Something bad happened ' + error.message)
				hideLoader('load')
				document.getElementById("saved-msg").innerHTML = 'Something bad happened ' + error.message
					setTimeout(function(){
						document.getElementById("saved-msg").innerHTML = '';
					}, 5000);
			});

		}
		//
		function writeFile() {
			b64 = document.getElementById("dalle").src
			showLoader('save');
			var url = "index.php?option=com_ajax&module=openaidalle&method=getData&format=json&dir=$folder";

			fetch(url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					'imagedata': b64,
				})
			}).then(response => {
				console.log(response);
				return response.json()
			}).then(data=>{
				console.log('Data->', data);
				
				if (data.file) {
					document.getElementById("saved-msg").innerHTML = 'Image saved ' + data.file
					setTimeout(function(){
						document.getElementById("saved-msg").innerHTML = '';
					}, 3000);
				}
				hideLoader('save');
			}).catch(error => {
				console.log('Something bad happened ' + error.message)
			});
			
		}
		//(function() {
		document.addEventListener("DOMContentLoaded", () => {
			hideLoader('load');
			/********************************************/
			document.getElementById('dall-e').addEventListener('click', function () {
				console.log("Generate DALL-E");
				OpenaiFetchAPI();
			});

			document.getElementById('write-e').addEventListener('click', function () {
				console.log("Save DALL-E");
				writeFile();
			});

		});
			//})();
		JS;

		$wa->addInlineScript($js);

?>

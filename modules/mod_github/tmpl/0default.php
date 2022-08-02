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

//$wa->registerAndUseScript('mod_matrix.admin', 'mod_matrix/admin-matrix.js');
?>

<div class="mod-custom custom">
<div class="card">
  <div class="card-body">
	<blockquote class="blockquote mb-0">
	  <p class="lead">loading...</p>
	  <footer class="blockquote-footer">
		<cite title="Source Title"></cite>
	  </footer>
	</blockquote>
  </div>
  <div class="card-footer">
	<button id="buttonQuote" class="btn btn-primary">New Quote</button>
  </div>
</div>
</div>
	<?php
		

		$js = <<< JS
			//(function() {
			document.addEventListener("DOMContentLoaded", () => {
					// DOM elements
					const button = document.getElementById("buttonQuote");
					const quote = document.querySelector("blockquote p");
					const cite = document.querySelector("blockquote cite");
					stats = [];
					
					function updateData() {
						getIssues();
					}
					async function getIssues() {
						const quote = document.querySelector("blockquote p");
						// Fetch open issues from Github API
						const response = await fetch("https://api.github.com/search/issues?q=repo:joomla/joomla-cms+type:issue+state:open&per_page=1");
						const data = await response.json();
						if (response.ok) {
						// Update DOM elements
							quote.textContent = "Open Issues:" + data.total_count;
							console.log(data.total_count);
						} else {
							quote.textContent = "An error occured";
							console.log(data);
						}
					
						// Fetch closed issues from Github API
						const response2 = await fetch("https://api.github.com/search/issues?q=repo:joomla/joomla-cms+type:issue+state:closed&per_page=1");
						const data2 = await response2.json();
						if (response2.ok) {
						// Update DOM elements
							quote.textContent = quote.textContent + " Closed Issues:" + data2.total_count;
							console.log(data2.total_count);
						} else {
							quote.textContent = "An error occured";
							console.log(data2);
						}
					}
					// Attach an event listener to the `button`
					button.addEventListener("click", updateData);

					// call updateData once when page loads
					updateData();
					});
			//})();
		JS;
		
		$wa->addInlineScript($js);

	?>


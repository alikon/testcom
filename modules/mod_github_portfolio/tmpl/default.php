<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $app->getDocument()->getWebAssetManager();
$wa->useScript('core');

$username = $params->get('github_username', 'alikon');
$maxItems = (int) $params->get('max_items', 6);
$moduleId = 'github-portfolio-' . $module->id;
?>

<div class="container py-5 mod-github-portfolio">
    <div class="row text-center mb-5">
        <div class="col-12">
            <h2>My Recent GitHub Contributions</h2>
            <p class="lead">Merged, Open and Draft Pull Requests.</p>
        </div>
    </div>

    <div class="row" id="<?php echo $moduleId; ?>">
        <p class="text-center text-muted col-12">Loading GitHub Portfolio...</p>
    </div>
    
    <div class="row text-center mt-4">
        <div class="col-12">
            <a href="https://github.com/<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-lg">
                Visit My GitHub Profile
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const GITHUB_USERNAME = <?php echo json_encode($username); ?>;
    const MAX_ITEMS = <?php echo $maxItems; ?>;
    const CACHE_TIME = <?php echo (int) $params->get('cache_time', 15) * 60000; ?>;
    const container = document.getElementById(<?php echo json_encode($moduleId); ?>);

    if (!container) {
        console.error("Error: Cannot find element with ID '" + <?php echo json_encode($moduleId); ?> + "'.");
        return;
    }

    const cacheKey = 'github_' + GITHUB_USERNAME + '_' + MAX_ITEMS;
    const cached = localStorage.getItem(cacheKey);
    const cacheTime = localStorage.getItem(cacheKey + '_time');
    
    if (cached && cacheTime && (Date.now() - parseInt(cacheTime)) < CACHE_TIME) {
        renderData(JSON.parse(cached));
        return;
    }

    const apiUrl = `https://api.github.com/search/issues?q=author:${GITHUB_USERNAME}+type:pr&sort=updated&order=desc&per_page=${MAX_ITEMS}`;
    
    function formatDate(isoString) {
        if (!isoString) return 'N/A';
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(isoString).toLocaleDateString('en-US', options);
    }

    function renderData(data) {
        const items = data.items || [];
        let htmlOutput = '';
        
        items.forEach(pr => {
            const repoUrl = pr.repository_url.replace('https://api.github.com/repos', 'https://github.com');
            const repoName = pr.repository_url.split('/').pop();
            const title = pr.title;
            const link = pr.html_url;

            let statusLabel = 'Under Review';
            let dateText = `Last Updated: ${formatDate(pr.updated_at)}`;

            if (pr.pull_request && pr.pull_request.merged_at) { 
                statusLabel = 'Merged';
                dateText = `Merged on: ${formatDate(pr.pull_request.merged_at)}`;
            } else if (pr.draft === true) {
                statusLabel = 'Draft';
            } else if (pr.state === 'closed') {
                statusLabel = 'Closed';
                dateText = `Closed on: ${formatDate(pr.closed_at)}`;
            }

            htmlOutput += `
                <div class="col-lg-4 col-md-6 col-sm-12 mb-4">
                    <div class="p-4 rounded shadow h-100" style="background-color: #f8f9fa; border: 1px solid #dee2e6;">
                        <h4>${title}</h4>
                        <p class="small">
                            <span style="font-weight: 600;">&#9679; Status: ${statusLabel}</span> 
                        </p>
                        <p class="small">
                            <span>Repository:</span> 
                            <a href="${repoUrl}" target="_blank">${repoName}</a>
                        </p>
                        <p class="small">
                            <span class="fw-bold">${dateText}</span>
                        </p>
                        <a href="${link}" class="btn btn-primary btn-sm mt-3" target="_blank">
                            View PR on GitHub &raquo;
                        </a>
                    </div>
                </div>
            `;
        });

        container.innerHTML = htmlOutput || '<p class="text-center text-muted col-12">No Pull Requests found.</p>';
    }

    fetch(apiUrl)
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        })
        .then(data => {
            localStorage.setItem(cacheKey, JSON.stringify(data));
            localStorage.setItem(cacheKey + '_time', Date.now().toString());
            renderData(data);
        })
        .catch(error => {
            console.error("Error fetching GitHub data:", error);
            container.innerHTML = `<p class="text-center text-danger col-12">Unable to load Portfolio. API Error: ${error.message}.</p>`;
        });
});
</script>
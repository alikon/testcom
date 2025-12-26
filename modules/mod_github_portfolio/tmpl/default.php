<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$app = Factory::getApplication();
$wa  = $app->getDocument()->getWebAssetManager();

// Core JS if you still need it elsewhere
$wa->useScript('core');

// Register & use dedicated JS asset
$wa->registerAndUseScript(
    'mod_github_portfolio.github-portfolio',
    'mod_github_portfolio/github-portfolio.js',
    [],
    ['defer' => true]
);

$username  = $params->get('github_username', 'alikon');
$maxItems  = (int) $params->get('max_items', 6);
$cacheTime = (int) $params->get('cache_time', 15) * 60000; // minutes -> ms
$moduleId  = 'github-portfolio-' . (int) $module->id;
?>

<div class="container py-5 mod-github-portfolio">
    <div class="row text-center mb-5">
        <div class="col-12">
            <h2>My Recent GitHub Contributions</h2>
            <p class="lead">Merged, Open and Draft Pull Requests.</p>
        </div>
    </div>

    <div
        class="row"
        id="<?php echo htmlspecialchars($moduleId, ENT_QUOTES, 'UTF-8'); ?>"
        data-github-portfolio="1"
        data-github-username="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
        data-max-items="<?php echo (int) $maxItems; ?>"
        data-cache-time="<?php echo (int) $cacheTime; ?>"
    >
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

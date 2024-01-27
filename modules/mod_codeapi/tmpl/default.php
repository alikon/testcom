<?php

/**
 * @package     Joomla.Site
 * @subpackage  mod_codeapi
 *
 * @copyright   Copyright (C) 2024 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

$modId     = 'mod-codeapi' . $module->id;
$startcode = $params->get('startcode');

HTMLHelper::_('script', 'https://unpkg.com/@antonz/runno@0.6.1/dist/runno.js');
HTMLHelper::_('script', 'https://unpkg.com/@antonz/codapi@0.12.2/dist/engine/wasi.js');
HTMLHelper::_('script', 'https://unpkg.com/@antonz/codapi@0.12.2/dist/snippet.js');
//$wa->registerAndUseStyle( "https://unpkg.com/@highlightjs/cdn-assets@11.9.0/styles/default.min.css");
//HTMLHelper::_('script', 'https://unpkg.com/@highlightjs/cdn-assets@11.9.0/highlight.min.js');
HTMLHelper::_('script', "https://unpkg.com/@highlightjs/cdn-assets@11.9.0/highlight.min.js");
?>
<link rel="stylesheet" href="https://unpkg.com/@antonz/codapi@0.12.2/dist/snippet.css">
<link rel="stylesheet" href="https://unpkg.com/@highlightjs/cdn-assets@11.9.0/styles/atom-one-dark.css">
<script>hljs.highlightAll();</script>
<?php if ($params->get('list_available') === 'sql') : ?>
    <?php echo '<script id="' . $modId. 'main.sql" type="text/plain">'?>
    <?php echo $startcode; ?>
    <?php echo '</script>' ?>
<?php endif; ?>
<div id="<?php echo $modId; ?>">
    <?php echo $module->content; ?>
</div>
<?php if ($params->get('list_available') === 'php') : ?>
    <codapi-snippet engine="wasi" sandbox="php" editor="basic"></codapi-snippet>
<?php endif; ?>
<?php if ($params->get('list_available') === 'python') : ?>
    <codapi-snippet engine="wasi" sandbox="python" editor="basic"></codapi-snippet>
<?php endif; ?>
<?php if ($params->get('list_available') === 'fetch') : ?>
    <codapi-snippet engine="browser" sandbox="fetch" editor="basic"></codapi-snippet>
<?php endif; ?>
<?php if ($params->get('list_available') === 'javascript') : ?>
    <codapi-snippet engine="browser" sandbox="javascript" editor="basic"></codapi-snippet>
<?php endif; ?>
<?php if ($params->get('list_available') === 'sql') : ?>
    <codapi-snippet
        engine="wasi"
        sandbox="sqlite"
        editor="basic"
        init-delay="1000"
        template="#<?php echo $modId; ?>main.sql"
    >
    </codapi-snippet>
<?php endif; ?>
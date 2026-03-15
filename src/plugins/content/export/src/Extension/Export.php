<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.export
 *
 * @copyright   Copyright (C) 2023 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Content\Export\Extension;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Toolbar\Toolbar;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Add a button to post a webservice
 *
 * @since  __DEPLOY_VERSION__
 */
//class PlgContentExport extends CMSPlugin
final class Export extends CMSPlugin
{
    /**
     * Application object
     *
     * @var    CMSApplication
     * @since  __DEPLOY_VERSION__
     */
    protected $app;

    /**
     * URL to get the data.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected $getUrl = '';

    /**
     * URL to send the data.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected $postUrl = '';

    /**
     * Render the button.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onBeforeRender(): void
    {
        // Run in backend
        if ($this->app->isClient('administrator') === true) {
            // Append button on Article
            if ($this->app->input->getCmd('option') === 'com_content' && $this->app->input->getCmd('view') === 'article') {
                if ($this->params->get('authorization') === 'Bearer') {
                    $auth = 'Authorization';
                    $key  = 'Bearer ' . $this->params->get('key');
                }

                if ($this->params->get('authorization') === 'X-Joomla-Token') {
                    $auth = 'X-Joomla-Token';
                    $key  = $this->params->get('key');
                }

                $id            = $this->app->input->get('id');
                $domain        = $this->params->get('url', 'http://localhost');
                $this->postUrl = $domain . '/api/index.php/v1/content/articles';
                $this->getUrl  = $domain . '/api/index.php/v1/content';

                // Get an instance of the Toolbar
                $toolbar = Toolbar::getInstance('toolbar');
                $toolbar->appendButton('Link', 'upload', 'Export', '#');

                // Get an instance of the generic article model
                $content = $this->app->bootComponent('com_content')->getMVCFactory();
                /** @var Joomla\Component\Content\Administrator\Model\ArticleModel $model */
                $model       = $content->createModel('Article', 'Administrator', ['ignore_request' => true]);
                $item        = $model->getItem($id);
                $item->catid = $this->params->get('catid');
                $item->state = $this->params->get('state', 0);
                unset($item->created_by, $item->typeAlias, $item->asset_id, $item->tagsHelper);




                $wa = $this->app->getDocument()->getWebAssetManager();

                // Load language strings for JavaScript
                $this->loadLanguage();

                Text::script('PLG_CONTENT_EXPORT_CATEGORY_CHECK');
                Text::script('PLG_CONTENT_EXPORT_CATEGORY_CHECK_ERROR');
                Text::script('PLG_CONTENT_EXPORT_NETWORK_ERROR');
                Text::script('PLG_CONTENT_EXPORT_CORS_GET_ERROR');
                Text::script('PLG_CONTENT_EXPORT_CORS_POST_ERROR');
                Text::script('PLG_CONTENT_EXPORT_CORS_PATCH_ERROR');
                Text::script('PLG_CONTENT_EXPORT_ARTICLE_SAVE_REQUIRED');
                Text::script('PLG_CONTENT_EXPORT_ARTICLE_UPDATE_REQUIRED');
                Text::script('PLG_CONTENT_EXPORT_ARTICLE_CREATED');
                Text::script('PLG_CONTENT_EXPORT_ARTICLE_NOT_CREATED');
                Text::script('PLG_CONTENT_EXPORT_ARTICLE_EXPORTED');
                Text::script('PLG_CONTENT_EXPORT_ARTICLE_CHECK');
                Text::script('PLG_CONTENT_EXPORT_INVALID_CONFIG_OBJECT');
                Text::script('PLG_CONTENT_EXPORT_INVALID_CONFIG_REQUIRED');

                // Pass data to javascript
                $this->app->getDocument()->addScriptOptions(
                    'a-export',
                    [
                        'apiKey'  => $key,
                        'catid'   => $this->params->get('catid'),
                        'get'     => $this->getUrl,
                        'post'    => $this->postUrl,
                        'auth'    => $auth,
                        'title'   => $item->title,
                        'article' => $item,
                    ]
                );
                $wa->registerAndUseScript('plg_content_export', 'plg_content_export/aexport.js', [], ['defer' => true], []);
            }
        }
    }
}

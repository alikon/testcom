<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.export
 *
 * @copyright   Copyright (C) 2023 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Alikonweb\Plugin\Content\Export\Extension;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Toolbar\Toolbar;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Add a button to post a webservice, from either the single article view
 * or the articles list view (bulk export).
 *
 * @since  __DEPLOY_VERSION__
 */
final class Export extends CMSPlugin
{
    /**
     * Maximum number of article IDs accepted in a single bulk export
     * AJAX request, to avoid abuse / resource exhaustion.
     *
     * @var    integer
     * @since  __DEPLOY_VERSION__
     */
    private const MAX_BULK_IDS = 200;

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
     * Render the button, on both the single article view and the
     * articles list view.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onBeforeRender(): void
    {
        // Run in backend only
        if ($this->app->isClient('administrator') !== true) {
            return;
        }

        $option = $this->app->input->getCmd('option');
        $view   = $this->app->input->getCmd('view');

        if ($option !== 'com_content' || ($view !== 'article' && $view !== 'articles')) {
            return;
        }

        $auth = '';
        $key  = '';

        if ($this->params->get('authorization') === 'Bearer') {
            $auth = 'Authorization';
            $key  = 'Bearer ' . $this->params->get('key');
        }

        if ($this->params->get('authorization') === 'X-Joomla-Token') {
            $auth = 'X-Joomla-Token';
            $key  = $this->params->get('key');
        }

        $domain        = $this->params->get('url', 'http://localhost');
        $this->postUrl = $domain . '/api/index.php/v1/content/articles';
        $this->getUrl  = $domain . '/api/index.php/v1/content';

        // Get an instance of the Toolbar and add the export button
        $toolbar = Toolbar::getInstance('toolbar');
        $toolbar->appendButton('Link', 'upload', 'Export', '#');

        // Build the options array shared by both views
        $scriptOptions = [
            'apiKey'    => $key,
            'catid'     => $this->getConfiguredCatId(),
            'get'       => $this->getUrl,
            'post'      => $this->postUrl,
            'auth'      => $auth,
            'view'      => $view,
            'maxBulk'   => self::MAX_BULK_IDS,
        ];

        // For the single article view, pre-load the current article data
        if ($view === 'article') {
            $id      = $this->app->input->getInt('id');
            $content = $this->app->bootComponent('com_content')->getMVCFactory();
            /** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $model */
            $model = $content->createModel('Article', 'Administrator', ['ignore_request' => true]);
            $item  = $model->getItem($id);

            // Category and publish state are always enforced by the plugin
            // configuration, never left to whatever the article currently has.
            $item->catid = $this->getConfiguredCatId();
            $item->state = $this->getConfiguredState();
            unset($item->created_by, $item->typeAlias, $item->asset_id, $item->tagsHelper);

            $scriptOptions['title']   = $item->title;
            $scriptOptions['article'] = $item;
        }

        $wa = $this->app->getDocument()->getWebAssetManager();

        // Load language strings for JavaScript
        $this->loadLanguage();

        Text::script('PLG_CONTENT_EXPORT_CATEGORY_CHECK');
        Text::script('PLG_CONTENT_EXPORT_CATEGORY_CHECK_ERROR');
        Text::script('PLG_CONTENT_EXPORT_CATEGORY_NETWORK_ERROR');
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
        Text::script('PLG_CONTENT_EXPORT_ARTICLE_CHECKING');
        Text::script('PLG_CONTENT_EXPORT_ARTICLE_CHECK_HTTP_ERROR');
        Text::script('PLG_CONTENT_EXPORT_ARTICLE_VERIFY_NETWORK_ERROR');
        Text::script('PLG_CONTENT_EXPORT_ARTICLE_CREATE_NETWORK_ERROR');
        Text::script('PLG_CONTENT_EXPORT_ARTICLE_UPDATE_NETWORK_ERROR');
        Text::script('PLG_CONTENT_EXPORT_ARTICLE_UPDATE_HTTP_ERROR');
        Text::script('PLG_CONTENT_EXPORT_ARTICLE_MISSING_TITLE');
        Text::script('PLG_CONTENT_EXPORT_INVALID_CONFIG_OBJECT');
        Text::script('PLG_CONTENT_EXPORT_INVALID_CONFIG_REQUIRED');
        Text::script('PLG_CONTENT_EXPORT_BULK_NO_SELECTION');
        Text::script('PLG_CONTENT_EXPORT_BULK_TOO_MANY_SELECTED');
        Text::script('PLG_CONTENT_EXPORT_BULK_SELECTED');
        Text::script('PLG_CONTENT_EXPORT_BULK_LOCAL_HTTP_ERROR');
        Text::script('PLG_CONTENT_EXPORT_BULK_NO_ARTICLES');
        Text::script('PLG_CONTENT_EXPORT_BULK_COMPLETE');
        Text::script('PLG_CONTENT_EXPORT_BULK_FATAL_ERROR');

        // Pass data to javascript
        $this->app->getDocument()->addScriptOptions('a-export', $scriptOptions);
        $wa->registerAndUseScript('plg_content_export', 'plg_content_export/aexport.js', [], ['defer' => true], []);
    }

    /**
     * AJAX handler used by the 'articles' list view to fetch a sanitised
     * export payload for a set of selected article IDs.
     *
     * The category and publish state applied to the exported payload are
     * always enforced from the plugin configuration and never trusted
     * from client input. Only users allowed to edit content may call
     * this endpoint.
     *
     * @return  array
     *
     * @throws  \Exception
     *
     * @since   __DEPLOY_VERSION__
     */
    public function onAjaxExport(): array
    {
        if (!$this->app->checkToken('POST')) {
            throw new \Exception(Text::_('JINVALID_TOKEN'), 403);
        }

        $user = $this->app->getIdentity();

        // Gate the whole endpoint behind a real ACL check: a valid CSRF
        // token alone is not authorization to read article bodies.
        if ($user === null || $user->guest || (!$user->authorise('core.edit', 'com_content') && !$user->authorise('core.edit.own', 'com_content'))) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $ids = $this->app->input->post->get('ids', [], 'ARRAY');

        if (empty($ids) || !\is_array($ids)) {
            throw new \Exception(Text::_('PLG_CONTENT_EXPORT_BULK_NO_IDS'), 400);
        }

        // Sanitise: only positive integers, deduplicated.
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));

        if (empty($ids)) {
            throw new \Exception(Text::_('PLG_CONTENT_EXPORT_BULK_NO_IDS'), 400);
        }

        if (\count($ids) > self::MAX_BULK_IDS) {
            throw new \Exception(Text::sprintf('PLG_CONTENT_EXPORT_BULK_TOO_MANY_IDS', self::MAX_BULK_IDS), 400);
        }

        $db    = Factory::getDbo();
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['id', 'title', 'alias', 'introtext', 'fulltext', 'language', 'metakey', 'metadesc', 'images']))
            ->from($db->quoteName('#__content'))
            ->whereIn($db->quoteName('id'), $ids)
            // Never export trashed content, regardless of what was selected client-side.
            ->where($db->quoteName('state') . ' != -2');

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        $articles    = [];
        $pluginCatid = $this->getConfiguredCatId();
        $pluginState = $this->getConfiguredState();

        foreach ($rows as $row) {
            $exportItem            = new \stdClass();
            $exportItem->title     = (string) $row->title;
            $exportItem->alias     = (string) $row->alias;
            $exportItem->introtext = (string) $row->introtext;
            $exportItem->fulltext  = (string) $row->fulltext;

            // Category and publish state are always enforced by the plugin,
            // never taken from the article itself.
            $exportItem->catid    = $pluginCatid;
            $exportItem->state    = $pluginState;
            $exportItem->language = !empty($row->language) ? $row->language : '*';

            if (!empty($row->metakey)) {
                $exportItem->metakey = $row->metakey;
            }

            if (!empty($row->metadesc)) {
                $exportItem->metadesc = $row->metadesc;
            }

            if (!empty($row->images)) {
                $imagesObj          = json_decode($row->images);
                $exportItem->images = json_last_error() === JSON_ERROR_NONE ? $imagesObj : $row->images;
            }

            $articles[] = $exportItem;
        }

        return array_values($articles);
    }

    /**
     * Returns the category ID configured for this plugin instance.
     * No arbitrary magic default: if it is not configured, 0 is
     * returned and callers/JS validation should treat that as "not set".
     *
     * @return  integer
     *
     * @since   __DEPLOY_VERSION__
     */
    private function getConfiguredCatId(): int
    {
        return (int) $this->params->get('catid', 0);
    }

    /**
     * Returns the publish state configured for this plugin instance.
     *
     * @return  integer
     *
     * @since   __DEPLOY_VERSION__
     */
    private function getConfiguredState(): int
    {
        return (int) $this->params->get('state', 0);
    }
}

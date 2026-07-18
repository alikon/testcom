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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Factory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Add a button to post a webservice
 *
 * @since  __DEPLOY_VERSION__
 */
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
            $option = $this->app->input->getCmd('option');
            $view   = $this->app->input->getCmd('view');

            if ($option === 'com_content' && ($view === 'article' || $view === 'articles')) {
                
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

                // Get an instance of the Toolbar ed esponiamo il bottone
                $toolbar = Toolbar::getInstance('toolbar');
                $toolbar->appendButton('Link', 'upload', 'Export', '#');

                // Prepariamo l'array delle opzioni per il JS
                $scriptOptions = [
                    'apiKey' => $key,
                    'catid'  => (int) $this->params->get('catid', 24),
                    'get'    => $this->getUrl,
                    'post'   => $this->postUrl,
                    'auth'   => $auth,
                    'view'   => $view, 
                ];

                // Se siamo nel singolo articolo, carichiamo i dati dell'articolo corrente
                if ($view === 'article') {
                    $id = $this->app->input->get('id');
                    $content = $this->app->bootComponent('com_content')->getMVCFactory();
                    /** @var Joomla\Component\Content\Administrator\Model\ArticleModel $model */
                    $model       = $content->createModel('Article', 'Administrator', ['ignore_request' => true]);
                    $item        = $model->getItem($id);
                    
                    // FORZATURA CATEGORIA E STATO DA PLUGIN PER ARTICOLO SINGOLO
                    $item->catid = (int) $this->params->get('catid', 24);
                    $item->state = (int) $this->params->get('state', 0);
                    unset($item->created_by, $item->typeAlias, $item->asset_id, $item->tagsHelper);

                    $scriptOptions['title']   = $item->title;
                    $scriptOptions['article'] = $item;
                }

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
                $this->app->getDocument()->addScriptOptions('a-export', $scriptOptions);
                $wa->registerAndUseScript('plg_content_export', 'plg_content_export/aexport.js', [], ['defer' => true], []);
            }
        }
    }

    /**
     * Gestisce la richiesta AJAX proveniente dalla vista 'articles' (esportazione di massa).
     *
     * @return  array  I dati estratti degli articoli pronti per il JS
     * @throws  \Exception
     */
    public function onAjaxExport(): array
    {
        if (!$this->app->checkToken('POST')) {
            throw new \Exception(Text::_('JINVALID_TOKEN'), 403);
        }

        $ids = $this->app->input->post->get('ids', [], 'ARRAY');

        if (empty($ids) || !\is_array($ids)) {
            throw new \Exception('Nessun ID ricevuto dal server locale.', 400);
        }

        $ids = array_map('intval', $ids);

        $db    = Factory::getDbo();
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['id', 'title', 'alias', 'introtext', 'fulltext', 'language', 'metakey', 'metadesc', 'images']))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        $articles = [];
        $pluginCatid = (int) $this->params->get('catid', 24);
        $pluginState = (int) $this->params->get('state', 0);

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $exportItem = new \stdClass();
                $exportItem->title     = (string) $row->title;
                $exportItem->alias     = (string) $row->alias;
                $exportItem->introtext = (string) $row->introtext;
                $exportItem->fulltext  = (string) $row->fulltext;
                
                // FORZATURA ASSOLUTA DEI VALORI DA PLUGIN
                $exportItem->catid     = $pluginCatid;
                $exportItem->state     = $pluginState;
                $exportItem->language  = !empty($row->language) ? $row->language : '*';
                
                if (!empty($row->metakey)) $exportItem->metakey = $row->metakey;
                if (!empty($row->metadesc)) $exportItem->metadesc = $row->metadesc;
                
                // Gestione e decodifica nativa dell'oggetto immagini per le API
                if (!empty($row->images)) {
                    $imagesObj = json_decode($row->images);
                    $exportItem->images = json_last_error() === JSON_ERROR_NONE ? $imagesObj : $row->images;
                }

                $articles[] = $exportItem;
            }
        }

        return array_values($articles);
    }
}

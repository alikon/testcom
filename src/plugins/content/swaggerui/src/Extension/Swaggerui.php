<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.swaggerui
 *
 * @copyright   Copyright (C) 2024 alikonweb.it. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Content\Swaggerui\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * SwaggerUI Content Plugin
 *
 * Integrates Swagger UI into Joomla 5 articles via the {swaggerui} tag.
 *
 * @since  2.0.0
 */
class Swaggerui extends CMSPlugin implements SubscriberInterface
{
    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   2.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    /**
     * Transforms the {swaggerui} tag in article content into a Swagger UI HTML block.
     *
     * @param   Event  $event  The onContentPrepare event
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function onContentPrepare(Event $event): void
    {
        [$context, $article, $params, $page] = array_values($event->getArguments());

        if (empty($article->text) || strpos($article->text, '{swaggerui') === false) {
            return;
        }

        $regex = '/\{swaggerui\s+(.*?)\}/i';

        $article->text = preg_replace_callback($regex, function ($matches) {
            $attributes = $this->parseAttributes($matches[1]);

            $url    = $attributes['url'] ?? 'https://petstore.swagger.io/v2/swagger.json';
            $source = $attributes['source'] ?? $this->params->get('assets_source', 'local');

            // Carica gli Asset dal file JSON unico
            $this->loadSwaggerAssets($source);

            return $this->generateSwaggerHtml($url);

        }, $article->text);
    }

    /**
     * Loads Swagger UI assets via the WebAssetManager or direct document injection.
     *
     * @param   string  $source  Asset source: 'local' or 'cdn'
     *
     * @return  void
     *
     * @since   2.0.0
     */
    private function loadSwaggerAssets(string $source = 'local'): void
    {
        $app      = Factory::getApplication();
        $document = $app->getDocument();

        if (!($document instanceof HtmlDocument)) {
            return;
        }

        $wa = $document->getWebAssetManager();
        //$wa->useScript('core');
        $wa->getRegistry()->addRegistryFile('media/plg_content_swaggerui/joomla.asset.json');

        // 1. Usa i nomi asset differenziati in base alla sorgente (CDN vs Local)
        if ($source === 'cdn') {
            $wa->useStyle('plg_content_swaggerui.cdn-css')
               ->useScript('plg_content_swaggerui.cdn-bundle')
               ->useScript('plg_content_swaggerui.cdn-preset');
        } else {
            $document->addStyleSheet(Factory::getApplication()->get('uri.base.full') . 'media/plg_content_swaggerui/css/swagger-ui.css');
            $document->addScript(Factory::getApplication()->get('uri.base.full') . 'media/plg_content_swaggerui/js/swagger-ui-bundle.js', ['defer' => true]);
            $document->addScript(Factory::getApplication()->get('uri.base.full') . 'media/plg_content_swaggerui/js/swagger-ui-standalone-preset.js', ['defer' => true]);
        }
    }

    /**
     * Generates the Swagger UI container HTML and inline initialisation script.
     *
     * @param   string  $url  The OpenAPI spec URL to load
     *
     * @return  string  The HTML container markup
     *
     * @since   2.0.0
     */
    private function generateSwaggerHtml(string $url): string
    {
        $document = Factory::getApplication()->getDocument();

        // Script di inizializzazione
        $initScript = "
        (function() {
            var initSwagger = function() {
                // Controlla che gli oggetti Swagger siano disponibili
                if (typeof SwaggerUIBundle !== 'undefined' && typeof SwaggerUIStandalonePreset !== 'undefined') {
                    const ui = SwaggerUIBundle({
                        url: '" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "',
                        dom_id: '#swagger-ui-container',
                        deepLinking: true,
                        presets: [
                            SwaggerUIBundle.presets.apis,
                            SwaggerUIStandalonePreset
                        ],
                        plugins: [
                            SwaggerUIBundle.plugins.DownloadUrl
                        ],
                        layout: 'StandaloneLayout'
                    });
                } else {
                    // Riprova tra 100ms se non ancora caricato
                    setTimeout(initSwagger, 100);
                }
            };
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initSwagger);
            } else {
                initSwagger();
            }
        })();
        ";

        // Aggiunge lo script al documento
        $document->addScriptDeclaration($initScript);

        // Ritorna il contenitore HTML
        return '<div id="swagger-ui-container" class="swagger-ui-wrapper" style="margin: 20px 0;"></div>';
    }

    /**
     * Parses key="value" attribute pairs from a shortcode string.
     *
     * @param   string  $text  The raw attribute string from the tag
     *
     * @return  array
     *
     * @since   2.0.0
     */
    private function parseAttributes(string $text): array
    {
        $attributes = [];
        $pattern    = '/(\w+)\s*=\s*["\']([^"\']+)["\']/';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[$match[1]] = $match[2];
            }
        }
        return $attributes;
    }
}

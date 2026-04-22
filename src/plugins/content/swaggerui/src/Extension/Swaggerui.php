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
 * Plugin per integrare Swagger UI negli articoli di Joomla 5
 */
class Swaggerui extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    /**
     * Metodo principale per trasformare il tag {swaggerui ...} in HTML
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
     * Carica gli asset di Swagger UI usando il WebAssetManager e il file JSON unico
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
            //$wa->useStyle('plg_content_swaggerui.local-css')
            //   ->useScript('plg_content_swaggerui.local-bundle')
            //   ->useScript('plg_content_swaggerui.local-preset');
            $document->addStyleSheet(Factory::getApplication()->get('uri.base.full') . 'media/plg_content_swaggerui/css/swagger-ui.css');
            $document->addScript(Factory::getApplication()->get('uri.base.full') . 'media/plg_content_swaggerui/js/swagger-ui-bundle.js', ['defer' => true]);
            $document->addScript(Factory::getApplication()->get('uri.base.full') . 'media/plg_content_swaggerui/js/swagger-ui-standalone-preset.js', ['defer' => true]);
        }

        // 2. Se decidi di usare un file JS esterno per l'init invece del declaration inline:
        // $wa->useScript('plg_content_swaggerui.init');
    }

    /**
     * Genera l'HTML e lo script di inizializzazione inline
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

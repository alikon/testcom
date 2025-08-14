<?php

namespace Joomla\Plugin\Content\Aigenerator\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Session\Session;

defined('_JEXEC') or die;

final class Aigenerator extends CMSPlugin
{
    protected $autoloadLanguage = true;
    protected $cache;
    
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        
        // Initialize cache (15 minutes lifetime)
        $this->cache = Cache::getInstance('output', [
            'defaultgroup' => 'plg_content_aigenerator',
            'caching' => true,
            'lifetime' => $this->params->get('cache_time', 15) * 60
        ]);
    }
    
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        // Only process articles
        if ($context !== 'com_content.article') {
            return;
        }
        
        // Don't process if there's no text
        if (empty($article->text)) {
            return;
        }
        
        // Check if we should process this article
        if (!$this->shouldProcessArticle($article)) {
            return;
        }
        
        // Generate cache ID based on article content
        $cacheId = 'ai_summary_' . md5($article->text);
        
        // Try to get from cache first
        $summary = $this->cache->get($cacheId);
        
        if ($summary === false) {
            // Not in cache, we'll load via AJAX
            $summary = '';
            $this->prepareForAjaxLoad($article, $cacheId);
        }
        
        // Add the summary to the article
        $this->addSummaryToArticle($article, $summary);
    }
    
    protected function shouldProcessArticle($article)
    {
        // Check category restrictions
        $categories = $this->params->get('categories', []);
        if (!empty($categories) && !in_array($article->catid, $categories)) {
            return false;
        }
        
        // Check if processing is enabled
        return (bool) $this->params->get('enabled', 1);
    }
    
    protected function prepareForAjaxLoad(&$article, $cacheId)
    {
        $app = Factory::getApplication();
        $doc = $app->getDocument();
        
        // Add JavaScript
        $doc->addScriptOptions('plg_content_aigenerator', [
            'apiUrl' => Uri::base() . 'index.php?option=com_ajax&plugin=aigenerator&format=raw',
            'token' => Session::getFormToken(),
            'articleId' => $article->id,
            'cacheId' => $cacheId,
            'loadingText' => Text::_('PLG_CONTENT_AIGENERATOR_LOADING')
        ]);
        
        $doc->addScript(Uri::root(true) . '/media/plg_content_aigenerator/js/aigenerator.js');
        
        // Add loading placeholder
        $article->text .= '<div id="ai-summary-loading-'.$article->id.'" class="ai-summary-loading">'
            . Text::_('PLG_CONTENT_AIGENERATOR_LOADING') . '</div>';
    }
    
    public function onAjaxAigenerator()
    {
        $app = Factory::getApplication();
        $input = $app->input;
        
        // Check token
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));
        
        $articleId = $input->getInt('article_id');
        $cacheId = $input->getString('cache_id');
        
        if (empty($articleId) {
            throw new Exception(Text::_('PLG_CONTENT_AIGENERATOR_NO_ARTICLE'));
        }
        
        // Get article content
        $article = $this->getArticleContent($articleId);
        
        if (!$article) {
            throw new Exception(Text::_('PLG_CONTENT_AIGENERATOR_ARTICLE_NOT_FOUND'));
        }
        
        // Generate summary
        $summary = $this->generateAISummary($article->text);
        
        if (!$summary) {
            throw new Exception(Text::_('PLG_CONTENT_AIGENERATOR_GENERATION_FAILED'));
        }
        
        // Store in cache
        $this->cache->store($summary, $cacheId);
        
        // Return JSON response
        $app->setHeader('Content-Type', 'application/json');
        echo json_encode(['summary' => $summary]);
        $app->close();
    }
    
    protected function getArticleContent($articleId)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'introtext', 'fulltext']))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = ' . (int) $articleId);
        
        $db->setQuery($query);
        return $db->loadObject();
    }
    
    protected function generateAISummary($content)
    {
        $apiKey = $this->params->get('api_key');
        $apiUrl = $this->params->get('api_url', 'https://models.github.ai/inference/chat/completions');
        $model = $this->params->get('model', 'deepseek/DeepSeek-V3-0324');
        
        if (empty($apiKey)) {
            Log::add('AI Generator Plugin: No API key configured', Log::WARNING, 'aigenerator');
            return false;
        }
        
        try {
            $http = HttpFactory::getHttp();
            
            $prompt = $this->params->get('prompt', 'Summarize this article in 3-5 sentences while preserving key information:');
            
            $data = [
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant that summarizes content.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt . "\n\n" . strip_tags($content)
                    ]
                ],
                'temperature' => (float) $this->params->get('temperature', 0.7),
                'top_p' => (float) $this->params->get('top_p', 1.0),
                'max_tokens' => (int) $this->params->get('max_tokens', 1000),
                'model' => $model
            ];
            
            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];
            
            $response = $http->post($apiUrl, json_encode($data), $headers, 30);
            
            if ($response->code === 200) {
                $body = json_decode($response->body);
                return $body->choices[0]->message->content ?? false;
            } else {
                Log::add(
                    Text::sprintf('PLG_CONTENT_AIGENERATOR_API_ERROR', $response->code, $response->body),
                    Log::ERROR,
                    'aigenerator'
                );
                return false;
            }
        } catch (Exception $e) {
            Log::add(
                Text::sprintf('PLG_CONTENT_AIGENERATOR_EXCEPTION', $e->getMessage()),
                Log::ERROR,
                'aigenerator'
            );
            return false;
        }
    }
    
    protected function addSummaryToArticle(&$article, $summary)
    {
        if (empty($summary)) {
            return;
        }
        
        $position = $this->params->get('summary_position', 'before');
        $showHeading = (bool) $this->params->get('show_heading', 1);
        $heading = Text::_('PLG_CONTENT_AIGENERATOR_HEADING');
        
        $summaryHtml = '<div class="ai-summary alert alert-info">';
        
        if ($showHeading) {
            $summaryHtml .= '<h3>' . $heading . '</h3>';
        }
        
        $summaryHtml .= '<div class="ai-summary-content">' . $summary . '</div>';
        $summaryHtml .= '</div>';
        
        if ($position === 'before') {
            $article->text = $summaryHtml . $article->text;
        } else {
            $article->text = $article->text . $summaryHtml;
        }
    }
    
    public function onAfterDispatch()
    {
        $app = Factory::getApplication();
        
        // Only in site and if we have an article
        if ($app->isClient('site') && $app->input->get('option') === 'com_content' && $app->input->get('view') === 'article') {
            $doc = $app->getDocument();
            $doc->addStyleDeclaration('
                .ai-summary-loading {
                    padding: 15px;
                    margin: 10px 0;
                    background: #f8f9fa;
                    border: 1px solid #dee2e6;
                    border-radius: 4px;
                    text-align: center;
                }
                .ai-summary {
                    margin: 15px 0;
                    padding: 15px;
                }
            ');
        }
    }
}
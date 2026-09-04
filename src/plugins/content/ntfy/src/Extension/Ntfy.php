<?php

namespace Alikonweb\Plugin\Content\Ntfy\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Model;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\Exception\MailDisabledException;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryAwareTrait;
use Joomla\CMS\Version;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

final class Ntfy extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use UserFactoryAwareTrait;

    /**
     * Mappatura degli eventi a cui il plugin risponde
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentAfterSave' => 'onAfterContentSave',
        ];
    }

    /**
     * Gestore dell'evento onContentAfterSave
     */
    public function onAfterContentSave(Model\AfterSaveEvent $event): void
    {
        // Estrazione argomenti in modo nativo per gli Event di Joomla
        $context = $event['context'];
        $article = $event['item'];
        $isNew   = $event['isNew'];

        // Esegui solo per gli articoli di com_content
        if ($context !== 'com_content.article') {
            return;
        }

        // Procedi solo se l'articolo è nello stato "Pubblicato" (state = 1) ed è NUOVO
        if ((int) $article->state !== 1 || !$isNew) {
            return;
        }

        $server   = rtrim($this->params->get('ntfy_server', 'https://ntfy.sh'), '/');
        $topic    = trim($this->params->get('ntfy_topic', ''));
        $token    = trim($this->params->get('ntfy_token', ''));
        $priority = $this->params->get('ntfy_priority', '3');

        if (empty($topic)) {
            return;
        }

        // Generazione URL dell'articolo nel frontend
        $articleUrl = Uri::root() . RouteHelper::getArticleRoute($article->slug, $article->catid, $article->language);

        $url = $server . '/' . $topic;
        $headers = [
            'Title'    => 'Nuovo Articolo: ' . $article->title,
            'Priority' => (string) $priority,
            'Tags'     => 'newspaper,joomla',
            'Click'    => $articleUrl,
        ];

        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $body = !empty($article->introtext) 
            ? strip_tags($article->introtext) 
            : 'Un nuovo articolo è stato pubblicato!';

        if (mb_strlen($body) > 250) {
            $body = mb_substr($body, 0, 247) . '...';
        }

         // Prepare connection
        $options = new Registry();
        $options->set('userAgent', (new Version())->getUserAgent('Joomla', true, false));

        $http = (new HttpFactory())->getHttp($options);
        // Invio notifica via HTTP POST
        try {
            $http->post($url, $body, $headers, 20);
        } catch (\RuntimeException $e) {
            $this->getApplication()->getLogger()->error('Errore invio ntfy: ' . $e->getMessage());
            $this->getApplication()->enqueueMessage($e->getMessage(), 'error');
        }
    }
}

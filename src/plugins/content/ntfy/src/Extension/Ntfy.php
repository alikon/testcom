<?php

namespace Alikonweb\Plugin\Content\Ntfy\Extension;

use Joomla\CMS\Event\Model;
use Joomla\CMS\Plugin\CMSPlugin;
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

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentAfterSave'    => 'onAfterContentSave',
            'onContentChangeState'  => 'onContentChangeState',
        ];
    }

    /**
     * Notifies for articles saved directly in published state for the first time.
     */
    public function onAfterContentSave(Model\AfterSaveEvent $event): void
    {
        if ($event->getContext() !== 'com_content.article') {
            return;
        }

        $article = $event->getItem();

        if (!$event->getIsNew() || (int) $article->state !== 1) {
            return;
        }

        $this->sendNtfyNotification($article);
    }

    /**
     * Notifies for articles transitioning to published state (e.g. draft → published).
     */
    public function onContentChangeState(Model\AfterChangeStateEvent $event): void
    {
        if ($event->getContext() !== 'com_content.article' || $event->getValue() !== 1) {
            return;
        }

        $pks = $event->getPks();

        if (empty($pks)) {
            return;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'introtext', 'catid', 'language', 'alias']))
            ->from($db->quoteName('#__content'))
            ->whereIn($db->quoteName('id'), $pks);

        foreach ($db->setQuery($query)->loadObjectList() as $article) {
            $article->slug = $article->alias ? ($article->id . ':' . $article->alias) : $article->id;
            $this->sendNtfyNotification($article);
        }
    }

    private function sendNtfyNotification(object $article): void
    {
        $server   = rtrim($this->params->get('ntfy_server', 'https://ntfy.sh'), '/');
        $topic    = trim($this->params->get('ntfy_topic', ''));
        $token    = trim($this->params->get('ntfy_token', ''));
        $priority = $this->params->get('ntfy_priority', '3');

        if (empty($topic)) {
            return;
        }

        $articleUrl = Uri::root() . RouteHelper::getArticleRoute($article->slug, $article->catid, $article->language);

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

        $options = new Registry();
        $options->set('userAgent', (new Version())->getUserAgent('Joomla', true, false));

        $http = (new HttpFactory())->getHttp($options);

        try {
            $response = $http->post($server . '/' . $topic, $body, $headers, 20);
            if ($response->code < 200 || $response->code >= 300) {
                $message = 'Errore invio ntfy: HTTP ' . $response->code;
                $this->getApplication()->getLogger()->error($message);
                $this->getApplication()->enqueueMessage($message, 'error');
            }
        } catch (\RuntimeException $e) {
            $this->getApplication()->getLogger()->error('Errore invio ntfy: ' . $e->getMessage());
            $this->getApplication()->enqueueMessage($e->getMessage(), 'error');
        }
    }
}

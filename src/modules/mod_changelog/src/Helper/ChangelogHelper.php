<?php
namespace Alikonweb\Module\Changelog\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Http\HttpFactory;

class ChangelogHelper 
{
    public static function getChangelogData($url) 
    {
        try {
            $options = new \JRegistry;
            // Set a timeout so your site doesn't hang if GitHub is slow
            $transport = HttpFactory::getHttp($options, 'curl');
            $response  = $transport->get($url);

            if ($response->code !== 200) {
                return null;
            }

            $xml = simplexml_load_string($response->body);
            
            if (!$xml) {
                return null;
            }

            $logs = [];
            foreach ($xml->changelog as $entry) {
                $logs[] = $entry;
            }
            
            // Reverse to show the latest version first
            return array_reverse($logs);

        } catch (\Exception $e) {
            return null;
        }
    }
}
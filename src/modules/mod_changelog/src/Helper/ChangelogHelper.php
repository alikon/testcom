<?php

/**
 * @package     Joomla.Module
 * @subpackage  Module.changelog
 *
 * @copyright   Copyright (C) 2026 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Alikonweb\Module\Changelog\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Http\HttpFactory;
use Joomla\Registry\Registry;

class ChangelogHelper
{
    public static function getChangelogData($url)
    {
        try {
            $options =  new Registry();
            // Set a timeout so your site doesn't hang if GitHub is slow
            $options->set('timeout', 10);
            $transport = HttpFactory::getHttp($options);
            $response  = $transport->get($url);

            if ($response->code !== 200) {
                return null;
            }

            $xml = simplexml_load_string($response->body);

            if (!$xml) {
                return null;
            }

            $logs       = [];
            $changelogs = [];

            foreach ($xml->changelog as $changelog) {
                $item          = new \stdClass();
                $item->element = (string)$changelog->element;
                $item->type    = (string)$changelog->type;
                $item->version = (string)$changelog->version;

                // Process each section
                $sections = ['security', 'fix', 'language', 'addition', 'change', 'remove', 'note'];

                foreach ($sections as $section) {
                    if (isset($changelog->{$section}, $changelog->{$section}->item)) {
                        $item->{$section} = new \stdClass();

                        // Check if there are multiple items
                        if (\count($changelog->{$section}->item) > 1) {
                            // Multiple items - convert to array
                            $items = [];
                            foreach ($changelog->{$section}->item as $subItem) {
                                $items[] = trim((string)$subItem);
                            }
                            $item->{$section}->item = $items;
                        } else {
                            // Single item - convert to array with one element for consistency
                            $item->{$section}->item = [trim((string)$changelog->{$section}->item)];
                        }
                    }
                }

                $changelogs[] = $item;
            }

            // Reverse to show the latest version first
            return array_reverse($changelogs);

        } catch (\Exception $e) {
            // Log the exception so operational issues can be diagnosed
            \Joomla\CMS\Log\Log::add(
                'Error fetching changelog: ' . $e->getMessage(),
                \Joomla\CMS\Log\Log::ERROR,
                'mod_changelog'
            );

            return null;
        }
    }
}

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

use Joomla\CMS\Log\Log;
use Joomla\CMS\Version;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;

/**
 * Helper class for the mod_changelog module.
 *
 * @since  1.0.0
 */
class ChangelogHelper
{
    /**
     * Fetches and parses changelog data from a remote XML URL.
     *
     * @param   string  $url  The URL of the changelog XML file.
     *
     * @return  array|null  Array of changelog entries or null on failure.
     *
     * @since   1.0.0
     */
    public static function getChangelogData(string $url): ?array
    {
        try {
            $options = new Registry();
            $options->set('timeout', 10);
            $options->set('userAgent', (new Version())->getUserAgent('Joomla', true, false));

            $body = (new HttpFactory())->getHttp($options)->get($url)->getBody();

            if (stripos(trim($body), '<html') === 0 || stripos(trim($body), '<!DOCTYPE') === 0) {
                Log::add('Changelog URL returned HTML instead of XML. Use a raw URL.', Log::WARNING, 'mod_changelog');

                return null;
            }

            $xml = @simplexml_load_string($body);

            if (!$xml) {
                return null;
            }

            $changelogs = [];

            foreach ($xml->changelog as $changelog) {
                $item          = new \stdClass();
                $item->element = (string) $changelog->element;
                $item->type    = (string) $changelog->type;
                $item->version = (string) $changelog->version;

                foreach (['security', 'fix', 'language', 'addition', 'change', 'remove', 'note'] as $section) {
                    if (!isset($changelog->{$section}, $changelog->{$section}->item)) {
                        continue;
                    }

                    $item->{$section}       = new \stdClass();
                    $item->{$section}->item = array_map(
                        static fn(\SimpleXMLElement $i): string => trim((string) $i),
                        iterator_to_array($changelog->{$section}->item, false)
                    );
                }

                $changelogs[] = $item;
            }

            usort($changelogs, static fn(\stdClass $a, \stdClass $b): int => version_compare($b->version, $a->version));

            return $changelogs;

        } catch (\Exception $e) {
            Log::add('Error fetching changelog: ' . $e->getMessage(), Log::ERROR, 'mod_changelog');

            return null;
        }
    }
}

<?php

/**
 * @package     Joomla.Site
 * @subpackage  mod_whosonline
 *
 * @copyright   (C) 2006 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\Jstats\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Helper for mod_whosonline
 *
 * @since  1.5
 */
class GithubHelper
{
    /**
     * Show online count
     *
     * @return  array  The number of Users and Guests online.
     *
     * @since   1.5
     **/
    public static function getData()
    {
        $db = Factory::getDbo();

        // Calculate number of guests and users
        $result         = [];


        $query = $db->getQuery(true)
            ->select('DATE(execution) as date,
			 openi as value')
            ->from($db->quoteName('#__github_issues'))
            ->order('execution');
        $db->setQuery($query);

        try {
            $datas = (array) $db->loadObjectList();
        } catch (\RuntimeException $e) {
            $datas = [];
        }

        foreach ($datas as $data) {
            //$result['openi'][] = $data->openi*$ratio;

        }

        return $result;
    }
}

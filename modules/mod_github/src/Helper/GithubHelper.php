<?php

/**
 * @package     Joomla.Site
 * @subpackage  mod_github
 *
 * @copyright   (C) 2006 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\Github\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Registry\Registry;

/**
 * Helper for mod_github
 *
 * @since  1.5
 */

class GithubHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * Show online count
     *
     * @return  array  The number of Users and Guests online.
     *
     * @since   1.5
     **/
    public function getGithubData(Registry $params, SiteApplication $app): array
    {
        $db     = $this->getDatabase();
        $result = [];

        $query = $db->getQuery(true)
            ->select('DATE(execution) as date,
			 openi as value, openp as value2')
            ->from($db->quoteName('#__github_issues'))
            ->order('execution');
        $db->setQuery($query);

        try {
            $datas = (array) $db->loadObjectList();
        } catch (\RuntimeException $e) {
            $datas = [];
        }

        //foreach ($datas as $data) {
            //$result['openi'][] = $data->openi*$ratio;

        //}

        return $datas;
    }
}

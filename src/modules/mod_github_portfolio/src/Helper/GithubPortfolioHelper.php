<?php

/**
 * @package     Joomla.Module
 * @subpackage  Module.portfolio
 *
 * @copyright   Copyright (C) 2026 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\GithubPortfolio\Site\Helper;

\defined('_JEXEC') or die;

class GithubPortfolioHelper
{
    public static function getUsername($params)
    {
        return $params->get('github_username', 'alikon');
    }

    public static function getMaxItems($params)
    {
        return (int) $params->get('max_items', 6);
    }
}

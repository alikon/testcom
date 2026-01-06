<?php

namespace Joomla\Module\GithubPortfolio\Site\Helper;

defined('_JEXEC') or die;

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

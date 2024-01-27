<?php

/**
 * @package     Joomla.Site
 * @subpackage  mod_codeapi
 *
 * @copyright   Copyright (C) 2024 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\Codeapi\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\HTML\HTMLHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher class for mod_codeapi
 *
 * @since  4.4.0
 */
class Dispatcher extends AbstractModuleDispatcher
{
    /**
     * Returns the layout data.
     *
     * @return  array
     *
     * @since   4.4.0
     */
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();

        if (($data['params'])->get('prepare_content', 1)) {
            ($data['module'])->content = HTMLHelper::_('content.prepare', ($data['module'])->content, '', 'mod_codeapi.content');
        }

        return $data;
    }
}

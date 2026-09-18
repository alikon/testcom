<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  mod_backward
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\Backward\Administrator\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Version;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher class for mod_backward
 *
 * @since  1.0.0
 */
class Dispatcher extends AbstractModuleDispatcher
{
    /**
     * Returns the layout data.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    protected function getLayoutData()
    {
        $data               = parent::getLayoutData();
        $data['compat']     = PluginHelper::isEnabled('behaviour', 'compat') ? (string) Version::MAJOR_VERSION : '';
        $data['compatNext'] = PluginHelper::isEnabled('behaviour', 'compat6') ? (string) (Version::MAJOR_VERSION + 1) : '';

        return $data;
    }
}

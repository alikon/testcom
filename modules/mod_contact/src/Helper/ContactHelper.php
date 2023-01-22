<?php

/**
 * @package     Joomla.Site
 * @subpackage  mod_contact
 *
 * @copyright   (C) 2023 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\Contact\Site\Helper;

use Joomla\CMS\Factory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper for mod_contact
 *
 * @since  __DEPLOY_VERSION__
 */
abstract class ContactHelper
{
    /**
     * Get a list of contacts from the Contacts model
     *
     * @param   \Joomla\Registry\Registry  &$params  object holding the models parameters
     *
     * @return  mixed
     */

     public static function getList(&$params)
     {
        $app = Factory::getApplication();

        // Get an instance of the generic contact model
        $model = $app->bootComponent('com_contact')
            ->getMVCFactory()->createModel('Contacts', 'Administrator', ['ignore_request' => true]);

        // Set module parameters in model
        $model->setState('filter.category_id', $params->get('catid'));
        $model->setState('filter.published', '1');
        $model->setState('list.limit', $params->get('count', 0));

        $items = $model->getItems();

        return $items;
     }
}

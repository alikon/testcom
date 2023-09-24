<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.aimetadesc
 *
 * @copyright   Copyright (C) 2021 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Toolbar\Toolbar;


/**
 * Add a button to post a webservice
 *
 * @since  __DEPLOY_VERSION__
 */
class PlgContentAimetadesc extends CMSPlugin
{
    /**
     * Application object
     *
     * @var    CMSApplication
     * @since  __DEPLOY_VERSION__
     */
    protected $app;

    /**
     * Render the button.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onBeforeRender(): void
    {
        // Run in backend
        if ($this->app->isClient('administrator') === true)
        { 
            // Append button on Article
            if ($this->app->input->getCmd('option') === 'com_content' && $this->app->input->getCmd('view') === 'article')
            {
                $apiKey  = $this->params->get('apikey', '');

                // Get an instance of the Toolbar
                $toolbar = Toolbar::getInstance('toolbar');
                $toolbar->appendButton('Link', 'flash', 'AI-MetaDesc', '#');
                
                $wa = $this->app->getDocument()->getWebAssetManager();

                // Pass some data to javascript
                $this->app->getDocument()->addScriptOptions(
                    'ai-metadesc',
                     [
                        'apiKey' => $apiKey,
                    ]
                );
                $wa->registerAndUseScript('plg_content_aimetadesc', 'plg_content_aimetadesc/aimetadesc.js', [], ['defer' => true], []);
            }
        }
    }
}

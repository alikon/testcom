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

                $wa->addInlineScript("
                    document.addEventListener('DOMContentLoaded', () => {(function() {
                        const options = window.Joomla.getOptions('ai-metadesc');
                        let apiKey    = options.apikey;
                        const text    = document.getElementById('jform_articletext').value
                        const strWithoutHTmlTags = text.replace(/(<([^>]+)>)/gi, '');

                        function cambia(){
                            function aiResponse(aiObj) {
                                output = aiObj.choices[0].text;
                                document.getElementById('jform_metadesc').value =  output.trim();
                                hideLoader('loader')
                            }

                            function hideLoader(id){
                                var loader = document.getElementById(id);
                                loader.style.display = 'none';
                            }

                            document.getElementById('toolbar-flash')
                                .insertAdjacentHTML('afterend', '<span id=\'loader\' class=\'spinner-border spinner-border-sm\' role=\'status\' aria-hidden=\'true\'></span>');

                            let myHeaders = new Headers();
                            myHeaders.append('Content-Type', 'application/json');
                            myHeaders.append('Authorization', 'Bearer ' + apiKey);

                            let raw = JSON.stringify({
                                'prompt': strWithoutHTmlTags,
                                'model': 'text-davinci-003',
                                'max_tokens': 160,
                                'temperature': 0.5
                            });

                            let requestOptions = {
                                method: 'POST',
                                headers: myHeaders,
                                body: raw,
                                redirect: 'follow'
                            };

                            fetch('https://api.openai.com/v1/completions', requestOptions)
                                .then(response => response.json())
                                .then(aiResponse)
                                .catch(error => console.log('error', error));
                        }

                        var element = document.getElementById('toolbar-flash');
                        element.addEventListener('click', cambia)
                        })();
                    });
                ");
            }
        }
    }
}
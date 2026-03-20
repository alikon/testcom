<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;

class JFormFieldVersion extends FormField
{
    protected $type = 'Version';

    public function getInput()
    {
        // 1. Get the path to your plugin's XML
        // Note: Ensure this path matches your folder name (safemode)
        $path = JPATH_SITE . '/plugins/task/deltrash/deltrash.xml';

        $version = '0.0.0';
        if (file_exists($path)) {
            $xml     = simplexml_load_file($path);
            $version = (string)$xml->version;
        }

        // 2. Return a larger, styled "button-style" badge
        // padding: 8px 16px makes it chunky
        // font-size: 1rem makes it standard text size (bigger than a tiny badge)
        // border-radius: 4px gives it a subtle button curve
        $style = 'display: inline-block; 
                  padding: 8px 16px; 
                  font-size: 1rem; 
                  font-weight: bold; 
                  line-height: 1; 
                  color: #fff; 
                  text-align: center; 
                  white-space: nowrap; 
                  vertical-align: baseline; 
                  border-radius: 4px; 
                  background-color: #0dcaf0;';

        return '<span style="' . $style . '">' . $version . '</span>';
    }
}

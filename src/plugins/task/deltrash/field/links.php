<?php
/**
 * @package     Joomla.Plugins
 * @subpackage  Task.DelTrash
 *
 * @copyright   Copyright (C) 2021 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

class JFormFieldLinks extends FormField
{
    protected $type = 'Links';

    public function getInput()
    {
        $buttons = [
            ['link' => 'https://www.alikonweb.it/extensions/task-plugin-delete-trashed', 'class' => 'btn-primary', 'icon' => 'fas fa-book', 'label' => 'PLG_TASK_DELTRASH_LBL_MANUAL'],
            ['link' => 'https://www.x.com/Alikon', 'class' => 'btn-primary', 'icon' => 'fab fa-x-twitter', 'label' => 'PLG_TASK_DELTRASH_LBL_FOLLOW'],
            ['link' => 'https://github.com/alikon/testcom/issues', 'class' => 'btn-danger', 'icon' => 'fas fa-bug', 'label' => 'PLG_TASK_DELTRASH_LBL_REPORT'],
            ['link' => 'https://github.com/sponsors/alikon', 'class' => 'btn-success', 'icon' => 'fab fa-github', 'label' => 'PLG_TASK_DELTRASH_LBL_SPONSOR'],
        ];

        // This CSS reset ensures that if your template adds a "duplicate" icon via
        // a ::before or ::after selector on target="_blank", it is hidden here.
        $html = '<style>.magic-links-container a[target="_blank"]::before, .magic-links-container a[target="_blank"]::after {content: none !important;}</style>';

        $html .= '<div class="magic-links-container" style="display: flex; gap: 6px; flex-wrap: nowrap; width: 100%;">';

        foreach ($buttons as $btn) {
            $safeLink = htmlspecialchars($btn['link'], ENT_QUOTES, 'UTF-8');

            $html .= '<a href="' . $safeLink . '" target="_blank" rel="noopener noreferrer" 
                        class="btn ' . $btn['class'] . '" 
                        style="display: flex; align-items: center; justify-content: center; flex-direction: row; 
                               flex: 1; padding: 20px 5px; text-decoration: none; white-space: nowrap;">';

            // 1. Primary Icon (Manual/Bug/etc.) stays on the LEFT
            $html .= '  <i class="' . $btn['icon'] . '" style="margin-right: 8px;" aria-hidden="true"></i>';

            // 2. Button Label (Center)
            $html .= '  <span style="font-size: 0.8rem; font-weight: 600;">' . Text::_($btn['label']) . '</span>';

            // 3. External Link Icon on the RIGHT
            $html .= '  <i class="fas fa-external-link-alt" style="margin-left: 8px; font-size: 0.7rem; opacity: 0.8;" aria-hidden="true"></i>';

            $html .= '</a>';
        }

        $html .= '</div>';

        return $html;
    }
}

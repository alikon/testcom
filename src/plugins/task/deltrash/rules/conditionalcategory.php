<?php

/**
 * @package     Joomla.Plugins
 * @subpackage  Task.DelTrash
 *
 * @copyright   Copyright (C) 2021 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;

/**
 * Modern Joomla Rule class naming convention for addrulepath:
 * JFormRule + [YourRuleNameInCamelCase]
 */
class JFormRuleConditionalcategory extends FormRule
{
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null): bool
    {
        // Get all form data
        $data = $input->toArray();

        // Navigate to the 'params' group where our switcher is
        $params = $data['params'] ?? [];

        // Check if categories switcher is '1' (Yes)
        $categoriesEnabled = isset($params['categories']) ? (int) $params['categories'] : 0;

        // If categories are NOT enabled, the field is valid regardless of value
        if ($categoriesEnabled !== 1) {
            return true;
        }

        // If categories ARE enabled, check if $value is empty
        // For 'multiple="true"', $value is usually an array
        // If categories ARE enabled, check if $value is actually useful
        if (empty($value)) {
            return false;
        }

        // If it's an array (multiple select), filter out any empty strings/nulls
        if (\is_array($value)) {
            $filtered = array_filter($value, function ($v) {
                return $v !== '' && $v !== null;
            });

            if (\count($filtered) === 0) {
                return false;
            }
        }
        return true;
    }
}

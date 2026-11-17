<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.Export
 *
 * @copyright   Copyright (C) 2026 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\TextField;
use Joomla\CMS\Language\Text;

/**
 * Export URL form field.
 *
 * Extends the text field to require a URL unless the download format is JSON or XML.
 *
 * @since  1.0.0
 */
class JFormFieldExporturl extends TextField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $type = 'Exporturl';

    /**
     * Validates the field value.
     *
     * @param   mixed                          $value  The value to validate.
     * @param   string|null                    $group  The field group.
     * @param   \Joomla\Registry\Registry|null $input  The form data.
     * @param   \Joomla\CMS\Form\Form|null     $form   The form.
     *
     * @return  \Exception|boolean  True on success, Exception on failure.
     *
     * @since   1.0.0
     */
    public function validate($value, $group = null, ?\Joomla\Registry\Registry $input = null, ?\Joomla\CMS\Form\Form $form = null)
    {
        $format = $input ? $input->get('params.download_format', 'none') : 'none';

        if ($format !== 'json' && $format !== 'xml' && trim((string) $value) === '') {
            return new \RuntimeException(Text::_('PLG_CONTENT_EXPORT_URL_LABEL') . ': ' . Text::_('JGLOBAL_FIELD_REQUIRED'));
        }

        return parent::validate($value, $group, $input, $form);
    }
}

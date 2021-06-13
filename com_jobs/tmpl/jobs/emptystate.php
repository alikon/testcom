<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_jobs
 *
 * @copyright   (C) 2021 Alikon. <https://www.alikonweb.it>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;

$displayData = [
	'textPrefix' => 'COM_JOBS',
	'helpURL'    => 'https://docs.joomla.org/Special:MyLanguage/Help4.x:Content_Security_Policy_Reports',
	'icon'       => 'icon-play',
];

echo LayoutHelper::render('joomla.content.emptystate', $displayData);
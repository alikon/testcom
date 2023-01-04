<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_whosonline
 *
 * @copyright   (C) 2006 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

//namespace Joomla\Module\Openaidalle\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class ModOpenaidalleHelper
{
	public static function getDataAjax()
	{
		$tipo="data:image/png;base64,";
		$immagine ="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";
		$data = $tipo . $immagine;
		$source = fopen($data, 'r');
		$destination = fopen('testimg02.png', 'w');

		stream_copy_to_stream($source, $destination);

		fclose($source);
		fclose($destination);
	}
}
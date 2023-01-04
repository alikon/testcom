<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_openaidalle
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
		$app = Factory::getApplication();
		$img = json_decode(file_get_contents("php://input"), true);
		$dir = '../images/' . $app->input->getString('dir'). "/testimg03.png";

		$file = ["file" => $dir];
		$tipo = "data:image/png;base64,";
		$data = $img['imagedata'];
		$source = fopen($data, 'r');
		$destination = fopen($dir, 'w');

		stream_copy_to_stream($source, $destination);

		fclose($source);
		fclose($destination);
		 // Output a JSON object
		echo json_encode($file);
		$app->close();
	}
}
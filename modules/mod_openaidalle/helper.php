<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_openaidalle
 *
 * @copyright   Copyright (C) 2021 Alikon. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

//namespace Joomla\Module\Openaidalle\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Date\Date;

class ModOpenaidalleHelper
{
	public static function getDataAjax()
	{
		$app = Factory::getApplication();
		$date = new Date('now', new \DateTimeZone('UTC'));
		$date =  $date->format('Y-m-d_His_T');
		$img  = json_decode(file_get_contents("php://input"), true);
		$dir  = '../images/' . str_replace('../images', '', $app->input->getString('dir'));
		$path = $dir . "/DallE_" . $date . ".png";

		$file = ["file" => $path];
		$data = $img['imagedata'];
		$source = fopen($data, 'r');
		$destination = fopen($path, 'w');

		stream_copy_to_stream($source, $destination);

		fclose($source);
		fclose($destination);
		 // Output a JSON object
		echo json_encode($file);
		$app->close();
	}
}
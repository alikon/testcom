<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_whosonline
 *
 * @copyright   (C) 2006 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Module\Github\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Helper for mod_whosonline
 *
 * @since  1.5
 */
class GithubHelper
{
	/**
	 * Show online count
	 *
	 * @return  array  The number of Users and Guests online.
	 *
	 * @since   1.5
	 **/
	public static function getData()
	{
		$db = Factory::getDbo();

		// Calculate number of guests and users
		$result	     = [];
	
		$minio = '(SELECT MIN(openi) FROM ' . $db->quoteName('#__github_issues') . ')';
		$minic = '(SELECT MIN(closedi) FROM ' . $db->quoteName('#__github_issues') . ')';
		$minpo = '(SELECT MIN(openp) FROM ' . $db->quoteName('#__github_issues') . ')';
		$minpc = '(SELECT MIN(closedp) FROM ' . $db->quoteName('#__github_issues') . ')';
		$query = $db->getQuery(true)
			->select('DATE(execution) as date,
			 (openi - ' . $minio . ') as minio, openi,
			 (closedi - ' . $minic .') as minic, closedi, 
			 (openp - ' . $minpo . ') as minpo, openp,
			 (closedp - ' . $minpc . ') as minpc, closedp')
			->from($db->quoteName('#__github_issues'))
			->order('execution');
		$db->setQuery($query);

		try
		{
			$datas = (array) $db->loadObjectList();
		}
		catch (\RuntimeException $e)
		{
			$datas = [];
		}
		$ratio = 1;
		if (\count($datas))
		{
			
			foreach ($datas as $data)
			{
				$result['openi'][] = $data->openi*$ratio;
				$result['closedi'][] = $data->closedi*$ratio;
				$result['openp'][] = $data->openp*$ratio;
				$result['closedp'][] = $data->closedp*$ratio;
				$result['issueopen'][] ='{date: "' . $data->date . '", value: ' . $data->minio . ' , realvalue:' . $data->openi . '}'; 
				$result['issueclosed'][] ='{date: "' . $data->date . '", value: ' . $data->minic . ' , realvalue:' . $data->closedi . '}';
				$result['pullopen'][] ='{date: "' . $data->date . '", value: ' . $data->minpo . ' , realvalue:' . $data->openp . '}';
				$result['pullclosed'][] ='{date: "' . $data->date . '", value: ' . $data->minpc . ' , realvalue:' . $data->closedp . '}';
			}
		}

		return $result;
	}

}

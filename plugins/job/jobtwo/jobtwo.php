<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Job.jobone
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\Component\Jobs\Administrator\Jobs\JobsPlugin;
/**
 * Joomla! Job One plugin
 *
 * An example for a job plugin
 *
 * @since  __DEPLOY_VERSION__
 */
class PlgJobJobtwo extends JobsPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  __DEPLOY_VERSION__
	 */
	protected $autoloadLanguage = true;

	/**
	 * Application object.
	 *
	 * @var    ApplicationCms
	 * @since  __DEPLOY_VERSION__
	 */
	protected $app;

	/**
	 * Database object.
	 *
	 * @var    DatabaseDriver
	 * @since  __DEPLOY_VERSION__
	 */
	protected $db;

	/**
	 * Status for the process
	 *
	 * @var    array
	 * @since  __DEPLOY_VERSION__
	 */
	protected $snapshot;

	/**
	 * The log check and rotation code event.
	 *
	 * @param   boolean  $options  The plugin options
	 *
	 * @return  array  status of the execution
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onExecuteScheduledTask($options = false) : array
	{
		$this->snapshot['startTime'] = microtime(true);
		$eid = PluginHelper::getPlugin($this->_type, $this->_name);
		// Pseudo Lock
		if (!$this->acquireLock($this->_name, $this->_type, $this->params, $options, $eid))
		{
			return $this->snapshot;
		}

		// Execute the job ONE task
		try
		{
			$this->joboneTask();
		}
		catch (\Exception $e)
		{
			$this->snapshot['status'] = self::JOB_KO_RUN;
		}

		// Update job execution data
		$this->releaseLock($this->_name, $this->_type);

		return $this->snapshot;
	}

	/**
	 * The log check and rotation code event.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function joboneTask()
	{
		// Sleep for 1 seconds

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => 'http://localhost/jugnight/api/index.php/v1/jobs/start/1',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_POSTFIELDS => '{
				"alias": "my-3-article",
				"articletext": "My text",
				"catid": 2,
				"language": "*",
				"metadesc": "",
				"metakey": "",
				"state": 1,
				"featured": 1,
				"title": "Here\'s 2 article",
				"images": {
						"image_intro": "https://leganerd.com/wp-content/uploads/2020/03/google-classroom-cover-999x500.jpg",
						"float_intro": "none",
						"image_intro_alt": "Intro alt",
						"image_intro_caption": "Intro capt",
						"image_fulltext": "https://www.fastweb.it/var/storage_feeds/CMS/articoli/108/10840b1e803a03d644e95dd60efa9f64/480x270.jpg",
						"float_fulltext": "left",
						"image_fulltext_alt": "Full alt",
						"image_fulltext_caption": "Full capt"
				}
			}',
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer c2hhMjU2Ojk0MToyOTRjYTU5MWQ5NTI1OTdlMmE5OWI4MzEyM2JiY2IwM2ZjZjk4ODFhYTZjMDdiYjMxZWU2Y2Q5NjlmMWU2MGYx',
				'Content-Type: text/plain'
			),
		));

		$response = curl_exec($curl);

		curl_close($curl);
		echo $response;

		// Simulate error
		// throw new RuntimeException('Test the failure: ');
	}

}

<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Job.exportdb
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\Archive\Archive;
use Joomla\Database\Exception\UnsupportedAdapterException;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Filesystem\File;
use Joomla\Registry\Registry;
use Joomla\Component\Jobs\Administrator\Jobs\JobsPlugin;

/**
 * Joomla! Export DB job plugin
 *
 * Export the DB to a file.
 *
 * @since  __DEPLOY_VERSION__
 */
class PlgJobExportdb extends JobsPlugin
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
	 * @var    JApplicationCms
	 * @since  __DEPLOY_VERSION__
	 */
	protected $app;

	/**
	 * Database object.
	 *
	 * @var    JDatabaseDriver
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
	 * @return  array
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onExecuteScheduledTask($options = false) : array
	{
		ini_set("memory_limit", '256M');
		$this->snapshot['startTime'] = microtime(true);

		// Pseudo Lock
		if (!$this->acquireLock($this->_name, $this->_type, $this->params, $options))
		{
			return $this->snapshot;
		}

		// Execute the job EXPORTDB task
		try
		{
			$this->exportdbTask();
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
	public function exportdbTask()
	{
		// Make sure the database supports exports before we get going
		try
		{
			$exporter = $this->db->getExporter()->withStructure();
		}
		catch (UnsupportedAdapterException $e)
		{
			return;
		}

		$tables = $this->db->getTableList();
		$prefix = $this->db->getPrefix();

		$directory = $this->app->get('tmp_path') . '/' . $this->params->get('directory', '');
		$zipFile = $directory . '/' . OutputFilter::stringURLSafe($this->app->get('sitename')) . '_DB_' . date("Y-m-d\TH-i-s") . '.zip';
		$zipArchive = (new Archive)->getAdapter('zip');

		foreach ($tables as $table)
		{
			if (strpos($table, $prefix) === 0)
			{
				$data = (string) $exporter->from($table)->withData(true);
				$zipFilesArray[] = ['name' => $table . '.xml', 'data' => $data];
				$zipArchive->create($zipFile, $zipFilesArray);
			}
		}
	}
}

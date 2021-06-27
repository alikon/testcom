<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Job.logrotation
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;
use Joomla\Component\Jobs\Administrator\Jobs\JobsPlugin;
/**
 * Joomla! Job One plugin
 *
 * An example for a job plugin
 *
 * @since  __DEPLOY_VERSION__
 */
class PlgJobLogrotation extends JobsPlugin
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

		// Pseudo Lock
		if (!$this->acquireLock($this->_name, $this->_type, $this->params, $options))
		{
			return $this->snapshot;
		}

		// Execute the job log rotation
		try
		{
			$this->logRotationTask();
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
	public function logRotationTask()
	{
		// Get the log path
		$logPath = Path::clean($this->app->get('log_path'));

		// Invalid path, stop processing further
		if (!is_dir($logPath))
		{
			return;
		}

		$logsToKeep = (int) $this->params->get('logstokeep', 1);
		$logFiles   = $this->getLogFiles($logPath);

		// Sort log files by version number in reserve order
		krsort($logFiles, SORT_NUMERIC);

		foreach ($logFiles as $version => $files)
		{
			if ($version >= $logsToKeep)
			{
				// Delete files which has version greater than or equals $logsToKeep
				foreach ($files as $file)
				{
					File::delete($logPath . '/' . $file);
				}
			}
			else
			{
				// For files which has version smaller than $logsToKeep, rotate (increase version number)
				foreach ($files as $file)
				{
					$this->rotate($logPath, $file, $version);
				}
			}
		}
	}

	/**
	 * Get log files from log folder
	 *
	 * @param   string  $path  The folder to get log files
	 *
	 * @return  array   The log files in the given path grouped by version number (not rotated files has number 0)
	 *
	 * @since   3.9.0
	 */
	private function getLogFiles($path)
	{
		$logFiles = array();
		$files    = Folder::files($path, '\.php$');

		foreach ($files as $file)
		{
			$parts    = explode('.', $file);

			/*
			 * Rotated log file has this filename format [VERSION].[FILENAME].php. So if $parts has at least 3 elements
			 * and the first element is a number, we know that it's a rotated file and can get it's current version
			 */
			if (count($parts) >= 3 && is_numeric($parts[0]))
			{
				$version = (int) $parts[0];
			}
			else
			{
				$version = 0;
			}

			if (!isset($logFiles[$version]))
			{
				$logFiles[$version] = array();
			}

			$logFiles[$version][] = $file;
		}

		return $logFiles;
	}

	/**
	 * Method to rotate (increase version) of a log file
	 *
	 * @param   string  $path            Path to file to rotate
	 * @param   string  $filename        Name of file to rotate
	 * @param   int     $currentVersion  The current version number
	 *
	 * @return  void
	 *
	 * @since   3.9.0
	 */
	private function rotate($path, $filename, $currentVersion)
	{
		if ($currentVersion === 0)
		{
			$rotatedFile = $path . '/1.' . $filename;
		}
		else
		{
			/*
			 * Rotated log file has this filename format [VERSION].[FILENAME].php. To rotate it, we just need to explode
			 * the filename into an array, increase value of first element (keep version) and implode it back to get the
			 * rotated file name
			 */
			$parts    = explode('.', $filename);
			$parts[0] = $currentVersion + 1;

			$rotatedFile = $path . '/' . implode('.', $parts);
		}

		File::move($path . '/' . $filename, $rotatedFile);
	}
}

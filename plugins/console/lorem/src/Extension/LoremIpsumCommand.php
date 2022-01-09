<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2020 Alikon. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Console\Lorem\Extension;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Factory;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Registry\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\User\User;
use Symfony\Component\Console\Command\Command;
use Joomla\CMS\Plugin\PluginHelper;

/**
 * Console command for list jobs queue
 *
 * @since  __DEPLOY_VERSION__
 */
class LoremIpsumCommand extends AbstractCommand
{
	/**
	 * The default command name
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $defaultName = 'lorem:ipsum';

	/**
	 * The elapsed time
	 *
	 * @var    string
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	private $time;

	/**
	 * Database connector
	 *
	 * @var    DatabaseInterface
	 * @since  __DEPLOY_VERSION__
	 */
	private $db;

	/**
	 * Instantiate the command.
	 *
	 * @param   DatabaseInterface  $db  Database connector
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(DatabaseInterface $db)
	{
		$this->db = $db;
		parent::__construct();
	}

	/**
	 * Internal function to execute the command.
	 *
	 * @param   InputInterface   $input   The input to inject into the command.
	 * @param   OutputInterface  $output  The output to inject into the command.
	 *
	 * @return  integer  The command exit code
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{
		$symfonyStyle = new SymfonyStyle($input, $output);

		$symfonyStyle->title('Lorem Ipsum Generator');

		// Initialize the time value.
		$this->time = microtime(true);

		
		switch ($this->loremIpsum())
		{
			case Command::SUCCESS:
				$symfonyStyle->success('LoremIpsum finished in ' . round(microtime(true) - $this->time, 3));
				return Command::SUCCESS;
				break;
			
			case Command::FAILURE:
				$symfonyStyle->error('LoremIpsum finished in ' . round(microtime(true) - $this->time, 3));
				return Command::FAILURE;
				break;
			
			default:
				$symfonyStyle->caution('LoremIpsum finished in ' . round(microtime(true) - $this->time, 3));
				return Command::INVALID;
				break;
		}
		
		

	}

	/**
	 * Create a new LoremIpsum article.
	 *
	 * @return  integer
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function loremIpsum(): int
	{
		$options = new Registry;
		$options->set('Content-Type', 'application/json');
		$apiurl='https://loripsum.net/generate.php?p=3&l=medium&d=1&a=1&co=1&ul=1&ol=1&dl=1&bq=1&h=1';

		try
		{
			$response = HttpFactory::getHttp($options)->get($apiurl, [], 3);

			if (preg_match("#<h1>([\w\W\s]*)</h1>#", $response->body, $matches1)) {
				$title = substr($response->body, strlen($matches1[0]));
			}

			$article['introtext'] = $title;

			if (preg_match("#<blockquote([\w\W\s]*)</blockquote>#", $title, $matches2)) {
				$article['introtext'] = substr($title, strlen($matches1[0]),strlen($matches2[0]));
				$article['fulltext'] = substr($title, strlen($matches2[0]));
			}
		
			$plugin = PluginHelper::getPlugin('console', 'lorem');
			$pluginParams = new Registry($plugin->params);
			// Set values.
			$article['title']        = $matches1[1];
			$article['catid']        = $pluginParams->get('catid', 0);
			$article['state']        = 1;
			$article['id']           = 0;
			$article['alias']        = ApplicationHelper::stringURLSafe($article['title']);
			$article['language']     = '*';
			$article['associations'] = [];
			$article['metakey']      = '';
			$article['metadesc']     = '';
			$article['xreference']   = '';
			$article['created_by']   = $pluginParams->get('authorid', 0);

			// @todo
			define('JPATH_COMPONENT', JPATH_ADMINISTRATOR . '/components/com_content');

			$content = Factory::getApplication()->bootComponent('com_content')->getMVCFactory();
			/** @var Joomla\Component\Content\Administrator\Model\ArticleModel $model */
			$model = $content->createModel('Article', 'Administrator',['ignore_request' => true]);
			$result = $model->save($article);
		}
		catch (\Exception $e)
		{
			return Command::FAILURE;
		}

		if (!$result)
		{
			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}

	/**
	 * Configure the command.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function configure(): void
	{
		$this->setDescription('Lorem ipsum generator');
		$this->setHelp(
			<<<EOF
The <info>%command.name%</info> command Create a new Lorem ipsum article.

<info>php %command.full_name%</info>
EOF
		);
	}
}
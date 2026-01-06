<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.safemode
 *
 * @copyright   (C) 2024 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\Safemode\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Language\Text;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\System\Safemode\Helper\SafemodeHelper;

/**
 * SafeMode System Plugin
 *
 * @since  __DEPLOY_VERSION__
 */
class SafemodePlugin extends CMSPlugin implements SubscriberInterface
{
    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  __DEPLOY_VERSION__
     */
    protected $autoloadLanguage = true;

    /**
     * SafeMode helper instance
     *
     * @var    SafemodeHelper
     * @since  __DEPLOY_VERSION__
     */
    private $helper;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   __DEPLOY_VERSION__
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise' => 'onAfterInitialise',
        ];
    }

    /**
     * Constructor
     *
     * @param   \Joomla\Event\DispatcherInterface  $dispatcher  The event dispatcher
     * @param   array                              $config      An optional associative array of configuration settings
     *
     * @since   __DEPLOY_VERSION__
     */
    public function __construct($dispatcher, array $config)
    {
        parent::__construct($dispatcher, $config);
        $this->helper = new SafemodeHelper();
    }

    /**
     * After initialise event handler
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    public function onAfterInitialise(Event $event): void
    {
        // Only run in admin
        if (!$this->getApplication()->isClient('administrator')) {
            return;
        }

        // Check if user has core.admin permission
        $user = $this->getApplication()->getIdentity();
        if (!$user || !$user->authorise('core.admin')) {
            return;
        }

        $input = $this->getApplication()->getInput();

        // Determine SafeMode state from params and URL
        $enabledUi = (bool) $this->params->get('enabled_ui', 0);
        $dryRun = (bool) $this->params->get('dry_run', 0) || $input->getBool('dryrun', false);
        $safeMode = $enabledUi || $input->getBool('safemodeon', false);

        // Get current state
        $isActive = $this->helper->isSafeModeActive();

        // Handle state transitions
        if (!$safeMode && $isActive) {
            // SafeMode should be OFF but it's ON - restore plugins
            $this->helper->restorePlugins($dryRun);
            // Clear existing SafeMode banner messages when turning OFF
            $this->clearSafeModeMessages();
        } elseif ($safeMode && !$isActive) {
            // SafeMode should be ON but it's OFF - disable plugins
            $this->helper->disablePlugins($dryRun);
        }

        // Show admin banner if SafeMode is active (check state again after potential changes)
        $currentlyActive = $this->helper->isSafeModeActive();
        if ($currentlyActive) {
            $this->showAdminBanner();
        }
    }

    /**
     * Show admin banner
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    private function showAdminBanner(): void
    {
        $this->getApplication()->enqueueMessage(
            'SafeMode is currently active. Non-core plugins are disabled.',
            'warning'
        );
    }

    /**
     * Clear SafeMode banner messages
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    private function clearSafeModeMessages(): void
    {
        $this->getApplication()->getMessageQueue(true);
        $this->getApplication()->enqueueMessage(Text::_(('PLG_SYSTEM_SAFEMODE_OFF'), 'warning'));
    }
}

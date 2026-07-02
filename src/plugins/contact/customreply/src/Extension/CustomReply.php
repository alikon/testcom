<?php

/**
 * @package    Joomla.Plugin
 * @subpackage Contact.customreply
 *
 * @author     Alikon <alikon@alikonweb.it>
 *
 * @copyright  (C) 2025, Alikonweb <https://www.alikonweb.it>. All rights reserved.
 * @license    GNU General Public License version 3 or later; see LICENSE
 * @link       https://www.alikonweb.it
 */

namespace Alikonweb\Plugin\Contact\CustomReply\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Contact\SubmitContactEvent;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\Exception\MailDisabledException;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryAwareTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Custom Reply System Plugin
 *
 * Allows users to reply to contact form submissions with custom messages.
 *
 * @since  1.0.0
 */
final class CustomReply extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use UserFactoryAwareTrait;

    /**
     * Application object
     *
     * @var    \Joomla\CMS\Application\CMSApplication
     * @since  1.0.0
     */
    protected $app;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onSubmitContact' => 'onSubmitContact',
        ];
    }

    /**
     * Handles the onSubmitContact event
     *
     * Processes contact form submissions, sends the contact email to the recipient,
     * and optionally sends an auto-response email to the sender if custom reply is enabled.
     *
     * @param   SubmitContactEvent  $event  The contact submission event
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onSubmitContact(SubmitContactEvent $event): void
    {
        if ($this->app->isClient('administrator')) {
            return;
        }

        // Load plugin language
        $this->loadLanguage();
        $params = ComponentHelper::getParams('com_contact');

        if (!$params->get('custom_reply')) {
            return;
        }
        $contact = $event->getContact();
        $data    = $event->getData();

        // Send primary contact email
        $contactEmailSent = $this->_sendEmail($data, $contact, $params->get('show_email_copy', 0));

        // Send autoresponse if email is valid
        $autoresponseSent = false;
        if (!empty($data['contact_email']) && filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $autoresponseSent = $this->sendAutoresponse($data);
        }

        // Show success message only if at least the primary email was sent
        if ($contactEmailSent) {
            $this->app->enqueueMessage(
                Text::_('PLG_CONTACT_CUSTOMREPLY_EMAIL_SENT'),
                'info'
            );

            // Log autoresponse failure separately if it occurred
            if (!$autoresponseSent && !empty($data['contact_email'])) {
                Log::add(
                    'Contact email sent successfully but autoresponse failed for: ' . $data['contact_email'],
                    Log::WARNING,
                    'plg_contact_customreply'
                );
            }
        }

        if ($this->app->isClient('site')) {
            $returnMenuId = (int) $this->params->get('redirect_url', 0);
            $url          = Uri::base();

            if ($returnMenuId > 0) {
                $item = $this->app->getMenu()->getItem($returnMenuId);

                if ($item) {
                    $lang = '';

                    if ($item->language !== '*' && Multilanguage::isEnabled()) {
                        $lang = '&lang=' . $item->language;
                    }

                    $url = Uri::base() . 'index.php?Itemid=' . $item->id . $lang;
                }
            }

            $this->app->redirect($url);
        }
    }

    /**
     * Sends an auto-response email to the contact form sender
     *
     * Sends a customized auto-response email using the mail template system
     * to acknowledge receipt of the contact form submission.
     *
     * @param   array  $data  The contact form data including email, name, subject, and message
     *
     * @return  bool  True on success, false on failure
     *
     * @since   1.0.0
     */
    private function sendAutoresponse($data): bool
    {
        $db = $this->getDatabase();

        // Send email using MailTemplate
        try {
            $siteName  = $this->app->get('sitename');

            $mailer = new MailTemplate('plg_contact_customreply.autoresponder', $this->app->getLanguage()->getTag());
            $mailer->addRecipient($data['contact_email']);
            $mailer->addTemplateData([
                'SITENAME' => $siteName,
                'NAME'     => $data['contact_name'],
                'SUBJECT'  => $data['contact_subject'],
                'MESSAGE'  => $data['contact_message'],
            ]);
            $mailer->send();
        } catch (\Exception $e) {
            Log::add('Failed to send auto-response email: ' . $e->getMessage(), Log::ERROR, 'plg_contact_customreply');
            return false;
        }

        return true;
    }

    /**
     * Sends the contact form email to the contact recipient
     *
     * Sends the contact form submission to the designated contact email address,
     * and optionally sends a copy to the sender if requested.
     *
     * @param   array      $data               The contact form data to send
     * @param   \stdClass  $contact            The contact information including email recipient
     * @param   boolean    $emailCopyToSender  True to send a copy of the email to the sender
     *
     * @return  boolean  True on success sending the email, false on failure
     *
     * @since   1.0.0
     */
    private function _sendEmail($data, $contact, $emailCopyToSender)
    {
        $app = $this->app;
        // Load core and installer language files
        $language = $this->getApplication()->getLanguage();

        $language->load('com_contact', JPATH_SITE, 'en-GB', false, true);
        $language->load('com_contact', JPATH_SITE, null, true);

        if ($contact->email_to == '' && $contact->user_id != 0) {
            $contact_user      = $this->getUserFactory()->loadUserById($contact->user_id);
            $contact->email_to = $contact_user->email;
        }

        $templateData = [
            'sitename'     => $app->get('sitename'),
            'name'         => $data['contact_name'],
            'contactname'  => $contact->name,
            'email'        => PunycodeHelper::emailToPunycode($data['contact_email']),
            'subject'      => $data['contact_subject'],
            'body'         => stripslashes($data['contact_message']),
            'url'          => Uri::base(),
            'customfields' => '',
        ];

        // Load the custom fields
        if (!empty($data['com_fields']) && $fields = FieldsHelper::getFields('com_contact.mail', $contact, true, $data['com_fields'])) {
            $output = FieldsHelper::render(
                'com_contact.mail',
                'fields.render',
                [
                    'context' => 'com_contact.mail',
                    'item'    => $contact,
                    'fields'  => $fields,
                ]
            );

            if ($output) {
                $templateData['customfields'] = $output;
            }
        }

        try {
            $mailer = new MailTemplate('com_contact.mail', $app->getLanguage()->getTag());
            $mailer->addRecipient($contact->email_to);
            $mailer->setReplyTo($templateData['email'], $templateData['name']);
            $mailer->addTemplateData($templateData);
            $mailer->addUnsafeTags(['name', 'email', 'body']);
            $sent = $mailer->send();

            // If we are supposed to copy the sender, do so.
            if ($emailCopyToSender && !empty($data['contact_email_copy'])) {
                $mailer = new MailTemplate('com_contact.mail.copy', $app->getLanguage()->getTag());
                $mailer->addRecipient($templateData['email']);
                $mailer->setReplyTo($templateData['email'], $templateData['name']);
                $mailer->addTemplateData($templateData);
                $mailer->addUnsafeTags(['name', 'email', 'body']);
                $sent = $mailer->send();
            }
        } catch (MailDisabledException | phpMailerException $exception) {
            try {
                Log::add(Text::_($exception->getMessage()), Log::WARNING, 'jerror');

                $sent = false;
            } catch (\RuntimeException $exception) {
                $this->app->enqueueMessage(Text::_($exception->getMessage()), 'warning');

                $sent = false;
            }
        }

        return $sent;
    }
}

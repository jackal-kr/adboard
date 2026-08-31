<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
namespace Joomla\Component\Adboard\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Captcha\Captcha;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Component\Adboard\Administrator\Helper\ImageHelper;
use Joomla\Component\Adboard\Administrator\Helper\TextHelper;
use Joomla\Component\Adboard\Site\Helper\CategoryHelper;

/**
 * Handles public ad submission.
 *
 * Security layers (in order):
 *   1. CSRF token
 *   2. Honeypot field — bots fill it, humans leave it empty
 *   3. CAPTCHA (when configured globally in Joomla)
 *   4. IP-based rate limiting
 *   5. Server-side field validation (model)
 *   6. Image upload security (ImageHelper)
 */
class FormController extends BaseController
{
    private string $formUrl = 'index.php?option=com_adboard&view=form';

    public function submit(): void
    {
        // 1. CSRF
        if (!Session::checkToken('post')) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_($this->formUrl));
            return;
        }

        // 2. Honeypot
        if (trim($this->input->getString('website', '')) !== '') {
            $this->app->enqueueMessage(Text::_('COM_ADBOARD_SUBMIT_SUCCESS'), 'message');
            $this->setRedirect($this->redirectUrl());
            return;
        }

        // 3. CAPTCHA
        $captchaPlugin = $this->app->get('captcha', '0');
        if ($captchaPlugin && $captchaPlugin !== '0') {
            try {
                $verified = Captcha::getInstance($captchaPlugin)
                    ->checkAnswer($this->input->getString('captcha', ''));
            } catch (\Throwable $e) {
                $verified = false;
            }
            if (!$verified) {
                $this->app->enqueueMessage(Text::_('COM_ADBOARD_CAPTCHA_FAILED'), 'warning');
                $this->setRedirect(Route::_($this->formUrl));
                return;
            }
        }

        /** @var \Joomla\Component\Adboard\Site\Model\FormModel $model */
        $model = $this->getModel('Form', 'Site');

        // 4. Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($model->isRateLimited($ip)) {
            $this->app->enqueueMessage(Text::_('COM_ADBOARD_SUBMIT_RATE_LIMITED'), 'warning');
            $this->setRedirect(Route::_($this->formUrl));
            return;
        }

        // 5. Validate text fields
        $data = [
            'title'        => trim($this->input->getString('title',       '')),
            'category'     => trim($this->input->getString('category',    '')),
            'description'  => trim($this->input->getString('description', '')),
            'contact'      => trim($this->input->getString('contact',     '')),
            'expires_days' => $this->input->getInt('expires_days', 0),
            'ip_address'   => $ip,
        ];

        $errors = $model->validate($data);
        if ($errors) {
            foreach ($errors as $error) {
                $this->app->enqueueMessage($error, 'warning');
            }
            $this->setRedirect(Route::_($this->formUrl));
            return;
        }

        // 6. Image uploads
        $params    = ComponentHelper::getParams('com_adboard');
        $maxImages = (int) $params->get('max_images',     5);
        $maxSizeMb = (int) $params->get('max_image_size', 5);

        $hadRejects     = false;
        $data['images'] = ImageHelper::saveUploads('images', $maxImages, $maxSizeMb, $hadRejects);

        if ($hadRejects) {
            $this->app->enqueueMessage(Text::_('COM_ADBOARD_UPLOAD_TYPE_ERROR'), 'warning');
        }

        // Persist
        if ($model->save($data)) {
            // Only send notification if enabled in Options → Generic → Email
            if ((int) $params->get('email_notify', 1) === 1) {
                $this->sendAdminNotification($data);
            }
            $this->app->enqueueMessage(Text::_('COM_ADBOARD_SUBMIT_SUCCESS'), 'message');
        } else {
            $this->app->enqueueMessage(Text::_('COM_ADBOARD_SUBMIT_ERROR'), 'error');
        }

        $this->setRedirect($this->redirectUrl());
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Send a plain-text notification email to the site administrator.
     *
     * Uses the global mail settings from Joomla's Global Configuration
     * (System → Global Configuration → Server → Mail).
     * Failures are caught and silenced so a mail server issue never
     * prevents the ad from being saved.
     */
    private function sendAdminNotification(array $data): void
    {
        try {
            $siteEmail = $this->app->get('mailfrom', '');
            $siteName  = $this->app->get('fromname', $this->app->get('sitename', ''));

            if ($siteEmail === '') {
                return; // mail not configured — skip silently
            }

            $categoryTitle = CategoryHelper::getTitle((string) ($data['category'] ?? ''));

            // Sanitize user-supplied fields before embedding in the email body.
            // $data here is pre-model (Joomla-filtered but not yet through
            // TextHelper::sanitize), so we apply it explicitly.
            $safeTitle       = TextHelper::sanitize($data['title']       ?? '', 255);
            $safeDescription = TextHelper::sanitize($data['description'] ?? '');
            $safeContact     = TextHelper::sanitize($data['contact']     ?? '', 255);

            $subject = Text::sprintf('COM_ADBOARD_MAIL_SUBJECT', $this->app->get('sitename', ''), $safeTitle);

            $body = Text::sprintf('COM_ADBOARD_MAIL_INTRO', $this->app->get('sitename', ''))
                . "\n\n"
                . Text::_('COM_ADBOARD_FIELD_TITLE')       . ': ' . $safeTitle       . "\n"
                . Text::_('COM_ADBOARD_FIELD_CATEGORY')    . ': ' . $categoryTitle   . "\n"
                . "\n"
                . Text::_('COM_ADBOARD_FIELD_DESCRIPTION') . ":\n"
                . (trim($safeDescription) !== '' ? $safeDescription : '—')
                . "\n\n"
                . Text::_('COM_ADBOARD_FIELD_CONTACT')     . ":\n"
                . (trim($safeContact) !== '' ? $safeContact : '—')
                . "\n\n"
                . Text::sprintf('COM_ADBOARD_MAIL_FOOTER', $this->app->get('sitename', ''));

            $mailer = Factory::getMailer();
            $mailer->setSender([$siteEmail, $siteName]);
            $mailer->addRecipient($siteEmail);
            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $mailer->isHtml(false);
            $mailer->Send();

        } catch (\Throwable $e) {
            // Non-fatal — submission succeeds even when the mailer fails
        }
    }

    /**
     * Build the post-submit redirect URL from the Options menu item setting.
     */
    private function redirectUrl(): string
    {
        $params = ComponentHelper::getParams('com_adboard');
        $itemId = (int) $params->get('redirect_itemid', 0);

        if ($itemId > 0) {
            return Route::_('index.php?Itemid=' . $itemId);
        }

        return Route::_('index.php?option=com_adboard&view=ads');
    }
}

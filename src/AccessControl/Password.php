<?php
namespace Bolt\AccessControl;

use Bolt\Application;
use Bolt\Storage\Entity;
use Bolt\Storage\Repository;
use Bolt\Translation\Translator as Trans;
use Doctrine\DBAL\DBALException;
use Hautelook\Phpass\PasswordHash;
use Symfony\Component\HttpFoundation\Request;
use UAParser;

/**
 * Password handling.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Password
{
    /** @var \Bolt\Application $app */
    protected $app;

    public function __construct(Application $app)
    {
        /*
         * $this->app['config']
         * $this->app['logger.system']
         * $this->app['logger.flash']
         * $this->app['mailer']
         * $this->app['randomgenerator']
         * $this->app['resources']
         * $this->app['render']
         */
        $this->app = $app;
    }

    /**
     * Set a random password for user.
     *
     * @param string $username User specified by ID, username or email address.
     *
     * @return string|boolean New password or FALSE when no match for username.
     */
    public function setRandomPassword($username)
    {
        $password = false;

        if ($userEntity = $this->app['storage']->getRepository('Bolt\Storage\Entity\Users')->getUser($username)) {
            $password = $this->app['randomgenerator']->generateString(12);

            $hashStrength = max($this->app['config']->get('general/hash_strength'), 8);
            $hasher = new PasswordHash($hashStrength, true);
            $hashedpassword = $hasher->HashPassword($password);

            $userEntity->setPassword($hashedpassword);
            $userEntity->setShadowpassword('');
            $userEntity->setShadowtoken('');
            $userEntity->setShadowvalidity(null);

            $this->app['storage']->getRepository('Bolt\Storage\Entity\Users')->save($userEntity);

            $this->app['logger.system']->info(
                "Password for user '{$userEntity->getUsername()}' was reset via Nut.",
                ['event' => 'authentication']
            );
        }

        return $password;
    }

    /**
     * Handle a password reset confirmation
     *
     * @param string $token
     *
     * @return void
     */
    public function resetPasswordConfirm($token)
    {
        // Append the remote caller's IP to the token
        $token .= '-' . str_replace('.', '-', $this->remoteIP);

        if ($userEntity = $this->app['storage']->getRepository('Bolt\Storage\Entity\Users')->getUserShadowAuth($token)) {
            // Update entries
            $userEntity->setPassword($userEntity->getShadowpassword());
            $userEntity->setShadowpassword('');
            $userEntity->setShadowtoken('');
            $userEntity->setShadowvalidity(null);
            $this->app['storage']->getRepository('Bolt\Storage\Entity\Users')->save($userEntity);

            $this->app['logger.flash']->success(Trans::__('Password reset successful! You can now log on with the password that was sent to you via email.'));
        } else {
            // That was not a valid token, or too late, or not from the correct IP.
            $this->app['logger.system']->error('Somebody tried to reset a password with an invalid token.', ['event' => 'authentication']);
            $this->app['logger.flash']->error(Trans::__('Password reset not successful! Either the token was incorrect, or you were too late, or you tried to reset the password from a different IP-address.'));
        }
    }

    /**
     * Sends email with password request. Accepts email or username
     *
     * @param string $username
     *
     * @return boolean
     */
    public function resetPasswordRequest($username)
    {
        $userEntity = $this->app['storage']->getRepository('Bolt\Storage\Entity\Users')->getUser($username);

        if (!$userEntity) {
            // For safety, this is the message we display, regardless of whether user exists.
            $this->app['logger.flash']->info(Trans::__("A password reset link has been sent to '%user%'.", ['%user%' => $username]));

            return false;
        }

        $shadowpassword = $this->app['randomgenerator']->generateString(12);
        $shadowtoken = $this->app['randomgenerator']->generateString(32);
        $hasher = new PasswordHash($this->hashStrength, true);
        $shadowhashed = $hasher->HashPassword($shadowpassword);
        $validity = new \DateTime();
        $delay = new \DateInterval(PT2H);

        // Set the shadow password and related stuff in the database.
        $userEntity->setShadowpassword($shadowhashed);
        $userEntity->setShadowtoken($shadowtoken . '-' . str_replace('.', '-', $this->remoteIP));
        $userEntity->setShadowvalidity($validity->add($delay));
        $this->app['storage']->getRepository('Bolt\Storage\Entity\Users')->save($userEntity);

        // Sent the password reset notification
        $this->resetPasswordNotification($userEntity, $shadowpassword, $shadowtoken);

        return true;
    }

    /**
     * Send the password reset link notification to the user.
     *
     * @param Entity\Users $userEntity
     * @param string       $shadowpassword
     * @param string       $shadowtoken
     */
    private function resetPasswordNotification(Entity\Users $userEntity, $shadowpassword, $shadowtoken)
    {
        $shadowlink = sprintf(
            '%s%sresetpassword?token=%s',
            $this->app['resources']->getUrl('hosturl'),
            $this->app['resources']->getUrl('bolt'),
            urlencode($shadowtoken)
        );

        // Compile the email with the shadow password and reset link.
        $mailhtml = $this->app['render']->render(
            'mail/passwordreset.twig',
            [
                'user'           => $userEntity,
                'shadowpassword' => $shadowpassword,
                'shadowtoken'    => $shadowtoken,
                'shadowvalidity' => date('Y-m-d H:i:s', strtotime('+2 hours')),
                'shadowlink'     => $shadowlink
            ]
        );

        $subject = sprintf('[ Bolt / %s ] Password reset.', $this->app['config']->get('general/sitename'));

        $message = $this->app['mailer']
            ->createMessage('message')
            ->setSubject($subject)
            ->setFrom([$this->app['config']->get('general/mailoptions/senderMail', $userEntity->getEmail()) => $this->app['config']->get('general/mailoptions/senderName', $this->app['config']->get('general/sitename'))])
            ->setTo([$userEntity['email'] => $userEntity['displayname']])
            ->setBody(strip_tags($mailhtml))
            ->addPart($mailhtml, 'text/html');

        $recipients = $this->app['mailer']->send($message);

        if ($recipients) {
            $this->app['logger.system']->info("Password request sent to '" . $userEntity->getDisplayname() . "'.", ['event' => 'authentication']);
        } else {
            $this->app['logger.system']->error("Failed to send password request sent to '" . $userEntity['displayname'] . "'.", ['event' => 'authentication']);
            $this->app['logger.flash']->error(Trans::__("Failed to send password request. Please check the email settings."));
        }
    }
}

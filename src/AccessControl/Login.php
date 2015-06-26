<?php
namespace Bolt\AccessControl;

use Bolt\Logger\FlashLogger;
use Bolt\Logger\FlashLoggerInterface;
use Bolt\Storage\Entity;
use Bolt\Storage\Repository\AuthtokenRepository;
use Bolt\Storage\Repository\UsersRepository;
use Bolt\Translation\Translator as Trans;
use Hautelook\Phpass\PasswordHash;
use Psr\Log\LoggerInterface;
use RandomLib\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Login authentication handling.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Login extends AccessChecker
{
    /**
     * Attempt to login a user with the given password. Accepts username or
     * email.
     *
     * @param string $userName
     * @param string $password
     * @param string $authCookie
     *
     * @return boolean
     */
    public function login($userName = null, $password = null, $authCookie = null)
    {
        // Remove expired tokens
        $this->repositoryAuthtoken->deleteExpiredTokens();

        if ($userName !== null && $password !== null) {
            return $this->loginCheckPassword($userName, $password);
        } elseif ($authCookie !== null) {
            return $this->loginCheckAuthtoken($authCookie);
        }

        $this->flashLogger->error(Trans::__('Invalid login parameters.'));

        return false;
    }

    /**
     * Check a user login request for username/password combinations.
     *
     * @param string $userName
     * @param string $password
     *
     * @return boolean
     */
    protected function loginCheckPassword($userName, $password)
    {
        if (!$userEntity = $this->getUserEntity($userName)) {
            return false;
        }

        $hasher = new PasswordHash($this->hashStrength, true);
        if (!$hasher->CheckPassword($password, $userEntity->getPassword())) {
            $this->loginFailed($userEntity);

            return false;
        }

        return $this->loginFinish($userEntity);
    }

    /**
     * Attempt to login a user via the bolt_authtoken cookie.
     *
     * @param string $authCookie
     *
     * @return boolean
     */
    protected function loginCheckAuthtoken($authCookie)
    {
        if (!$userTokenEntity = $this->repositoryAuthtoken->getToken($authCookie, $this->remoteIP, $this->userAgent)) {
            $this->flashLogger->error(Trans::__('Invalid login parameters.'));

            return false;
        }

        $checksalt = $this->getAuthToken($userTokenEntity->getUsername(), $userTokenEntity->getSalt());
        if ($checksalt === $userTokenEntity->getToken()) {
            if (!$userEntity = $this->getUserEntity($userTokenEntity->getUsername())) {
                return false;
            }

            $this->repositoryAuthtoken->save($userEntity);
            $this->flashLogger->success(Trans::__('Session resumed.'));

            return $this->loginFinish($userEntity);
        }

        return false;
    }

    /**
     * Get the user record entity if it exists.
     *
     * @param string $userName
     *
     * @return Entity\Users|null
     */
    protected function getUserEntity($userName)
    {
        if (!$userEntity = $this->repositoryUsers->getUser($userName)) {
            $this->flashLogger->error(Trans::__('Your account is disabled. Sorry about that.'));

            return;
        }

        if (!$userEntity->getEnabled()) {
            $this->flashLogger->error(Trans::__('Your account is disabled. Sorry about that.'));

            return;
        }

        return $userEntity;
    }

    /**
     * Finish user login process(es).
     *
     * @param Entity\Users $userEntity
     *
     * @return boolean
     */
    protected function loginFinish(Entity\Users $userEntity)
    {
        if (!$this->updateUserLogin($userEntity)) {
            return false;
        }

        $userEntity->setPassword('**dontchange**');
        $tokenEntity = $this->updateAuthToken($userEntity);
        $token = new Token($userEntity, $tokenEntity);

        $this->session->set('authentication', $token);
        $this->session->save();

        return true;
    }

    /**
     * Add errormessages to logs and update the user
     *
     * @param Entity\Users $userEntity
     */
    protected function loginFailed(Entity\Users $userEntity)
    {
        $this->flashLogger->error(Trans::__('Username or password not correct. Please check your input.'));
        $this->systemLogger->info("Failed login attempt for '" . $userEntity->getDisplayname() . "'.", ['event' => 'authentication']);

        // Update the failed login attempts, and perhaps throttle the logins.
        $userEntity->setFailedlogins($userEntity->getFailedlogins() + 1);
        $userEntity->setThrottleduntil($this->throttleUntil($userEntity->getFailedlogins() + 1));
        $this->repositoryUsers->save($userEntity);
    }

    /**
     * Update the user record with latest login information.
     *
     * @param Entity\Users $userEntity
     *
     * @return boolean
     */
    protected function updateUserLogin(Entity\Users $userEntity)
    {
        $userEntity->setLastseen(new \DateTime());
        $userEntity->setLastip($this->remoteIP);
        $userEntity->setFailedlogins(0);
        $userEntity->setThrottleduntil($this->throttleUntil(0));

        // Don't try to save the password on login
        unset($userEntity->password);
        if ($this->repositoryUsers->save($userEntity)) {
            $this->flashLogger->success(Trans::__("You've been logged on successfully."));

            return true;
        }

        return false;
    }

    /**
     * Set the Authtoken cookie and DB-entry. If it's already present, update it.
     *
     * @param Entity\Users $userEntity
     *
     * @return Entity\Token
     */
    protected function updateAuthToken($userEntity)
    {
        $salt = $this->randomGenerator->generateString(32);

        if (!$tokenEntity = $this->repositoryAuthtoken->getUserToken($userEntity->getUsername(), $this->remoteIP, $this->userAgent)) {
            $tokenEntity = new Entity\Authtoken();
        }

        $username = $userEntity->getUsername();
        $token = $this->getAuthToken($username, $salt);
        $validityPeriod = $this->cookieOptions['lifetime'];
        $validityDate = new \DateTime();
        $validityInterval = new \DateInterval("PT{$validityPeriod}S");

        $tokenEntity->setUsername($userEntity->getUsername());
        $tokenEntity->setToken($token);
        $tokenEntity->setSalt($salt);
        $tokenEntity->setValidity($validityDate->add($validityInterval));
        $tokenEntity->setIp($this->remoteIP);
        $tokenEntity->setLastseen(new \DateTime());
        $tokenEntity->setUseragent($this->userAgent);

        $this->repositoryAuthtoken->save($tokenEntity);

        return $tokenEntity;
    }

    /**
     * Calculate the amount of time until we should throttle login attempts for
     * a user.
     *
     * The amount is increased exponentially with each attempt: 1, 4, 9, 16, 25,
     * 36… seconds.
     *
     * Note: I just realized this is conceptually wrong: we should throttle
     * based on remote_addr, not username. So, this isn't used, yet.
     *
     * @param integer $attempts
     *
     * @return \DateTime
     */
    private function throttleUntil($attempts)
    {
        if ($attempts < 5) {
            return null;
        } else {
            $wait = pow(($attempts - 4), 2);

            $dt = new \DateTime();
            $di = new \DateInterval("PT{$wait}S");

            return $dt->add($di);
        }
    }
}

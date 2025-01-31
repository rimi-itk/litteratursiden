<?php

namespace Drupal\lit_user\Controller;

use Drupal\user_registrationpassword\Controller\UserRegistrationPassword as UserRegistrationPasswordDefault;

class UserRegistrationPassword extends UserRegistrationPasswordDefault
{

    /**
     * {@inheritdoc}
     */
    public function confirmAccount ($uid , $timestamp , $hash)
    {

        $route_name = '<front>';
        $route_options = [];
        $url_options = [];
        $current_user = $this->currentUser();

        // When processing the one-time login link, we have to make sure that a user
        // isn't already logged in.
        if ($current_user->isAuthenticated()) {
            // The existing user is already logged in.
            if ($current_user->id() == $uid) {
                drupal_set_message(t('You are currently authenticated as user %user.' ,
                    ['%user' => $current_user->getAccountName()]));

                // Redirect to user page.
                $route_name = 'entity.user.canonical';
                $route_options = ['user' => $current_user->id()];
            } // A different user is already logged in on the computer.
            else {
                $reset_link_account = $this->userStorage->load($uid);
                if (!empty($reset_link_account)) {
                    drupal_set_message($this->t('Another user (%other_user) is already logged into the site on this computer, but you tried to use a one-time link for user %resetting_user. Please <a href=":logout">log out</a> and try using the link again.' ,
                        [
                            '%other_user' => $current_user->getUsername() ,
                            '%resetting_user' => $reset_link_account->getUsername() ,
                            ':logout' => $this->url('user.logout')
                        ]) , 'warning');
                } else {
                    // Invalid one-time link specifies an unknown user.
                    $route_name = user_registrationpassword_set_message('linkerror' , true);
                }
            }
        } else {
            // Time out, in seconds, until login URL expires. 24 hours = 86400 seconds.
            $timeout = $this->config('user_registrationpassword.settings')->get('registration_ftll_timeout');
            $current = REQUEST_TIME;
            $timestamp_created = $timestamp - $timeout;

            // Some redundant checks for extra security ?
            $users = $this->entityQuery
                ->condition('uid' , $uid)
                ->condition('status' , 0)
                ->condition('access' , 0)
                ->execute();

            // Timestamp can not be larger then current.
            /** @var \Drupal\user\UserInterface $account */
            if ($timestamp_created <= $current && !empty($users) && $account = $this->userStorage->load(reset($users))) {
                // Check if we have to enforce expiration for activation links.
                if ($this->config('user_registrationpassword.settings')->get('registration_ftll_expire') && !$account->getLastLoginTime() && $current - $timestamp > $timeout) {
                    $route_name = user_registrationpassword_set_message('linkerror' , true);
                }
                // Else try to activate the account.
                // Password = user's password - timestamp = current request - login = username.
                // user_pass_rehash($password, $timestamp, $login, $uid)
                elseif ($account->id() && $timestamp >= $account->getCreatedTime() && !$account->getLastLoginTime() && $hash == user_pass_rehash($account ,
                        $timestamp)
                ) {
                    // Format the date, so the logs are a bit more readable.
                    $date = $this->dateFormatter->format($timestamp);
                    $this->getLogger('user')->notice('User %name used one-time login link at time %timestamp.' ,
                        ['%name' => $account->getAccountName() , '%timestamp' => $date]);
                    // Activate the user and update the access and login time to $current.
                    $account
                        ->activate()
                        ->setLastAccessTime($current)
                        ->setLastLoginTime($current)
                        ->save();

                    // user_login_finalize() also updates the login timestamp of the
                    // user, which invalidates further use of the one-time login link.
                    user_login_finalize($account);

                    // Display default welcome message.
                    drupal_set_message(t('You have just used your one-time login link. Your account is now active and you are authenticated.'));

                    // Redirect to user.
                    $route_name = 'entity.user.edit_form';
                    $route_options = ['user' => $account->id()];
                    $url_options = ['query' => ['first_time_logging' => 1]];
                }
                // Something else is wrong, redirect to the password
                // reset form to request a new activation e-mail.
                else {
                    $route_name = user_registrationpassword_set_message('linkerror' , true);
                }
            } else {
                // Deny access, no more clues.
                // Everything will be in the watchdog's
                // URL for the administrator to check.
                $route_name = user_registrationpassword_set_message('linkerror' , true);
            }
        }

        return $this->redirect($route_name , $route_options , $url_options);
    }
}

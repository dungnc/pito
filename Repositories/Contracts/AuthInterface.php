<?php

namespace App\Repositories\Contracts;

interface AuthInterface
{
    /**
     * Sign in
     */
    public function login($username, $password);

    /**
     * Sign out
     */
    public function logout($user);

    /**
     * Sign up default
     */
    public function register_default($data, $auto = false);

    /**
     * Sign up
     */
    public function register_social($data);

    /**
     * Verify Send code to email .
     */
    public function sendApiEmailVerificationNotification($user);

    public function sendEmailResetingPassword($user);
    public function resetingPassword($token, $user, $password);
}

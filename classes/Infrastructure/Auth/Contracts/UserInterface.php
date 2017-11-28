<?php

namespace OpenCFP\Infrastructure\Auth\Contracts;

/**
 * This interface is intended to be used as a representation of a user.
 *
 * It should serve as a bridge to allow switching between Sentry and Sentinel
 */
interface UserInterface
{
    /**
     * Retrieves the user's Id
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Retrieves the users login (email)
     *
     * @return string
     */
    public function getLogin(): string;

    /**
     * @param $permissions
     *
     * @return mixed
     */
    public function hasAccess($permissions);

    /**
     * Checks if the given matches the current password.
     *
     * @param string $password
     *
     * @return bool
     */
    public function checkPassword(string $password): bool;

    /**
     * Checks if the provided user reset password code is
     * valid without actually resetting the password.
     *
     * @param string $resetCode
     *
     * @return bool
     */
    public function checkResetPasswordCode(string $resetCode): bool;

    /**
     * Get a reset password code for the given user.
     *
     * @return string
     */
    public function getResetPasswordCode(): string;

    /**
     * Attempts to reset a user's password by matching
     * the reset code generated with the user's.
     *
     * @param string $resetCode
     * @param string $newPassword
     *
     * @return bool
     */
    public function attemptResetPassword($resetCode, $newPassword): bool;
}

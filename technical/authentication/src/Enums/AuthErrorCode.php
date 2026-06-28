<?php

namespace Technical\Authentication\Enums;

/**
 * Machine-readable error codes returned by the authentication endpoints, so
 * the front-end can branch on a stable identifier rather than a localized
 * message string.
 */
enum AuthErrorCode: string
{
    case InvalidCredentials = 'invalid_credentials';
    case EmailUnverified = 'email_unverified';
    case UserNotFound = 'user_not_found';
}

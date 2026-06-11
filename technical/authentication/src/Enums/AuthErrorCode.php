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
    case TwoFactorRequired = 'two_factor_required';
    case InvalidTwoFactorCode = 'invalid_two_factor_code';
    case InvalidPendingToken = 'invalid_pending_token';
    case UserNotFound = 'user_not_found';
}

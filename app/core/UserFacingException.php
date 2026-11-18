<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * An error whose message was written for the person using the site ("That photo is too large…")
 * and is safe to show them.
 *
 * Every other exception (database errors, file-system errors, bugs) can contain table names,
 * file paths or SQL, so it is logged and replaced with a generic message. See report_exception().
 */
final class UserFacingException extends RuntimeException
{
}

<?php
namespace App\utils;

use RuntimeException;

/** Internal failure that must be returned as HTTP 500 with a generic message. */
final class SystemException extends RuntimeException {}

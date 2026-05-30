<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a project leader reassignment fails business validation
 * (inactive candidate, not a project team member, etc.).
 *
 * Caller is expected to map this to HTTP 422.
 */
class InvalidProjectLeaderException extends RuntimeException {}

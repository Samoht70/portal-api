<?php

namespace Dailyapps\PortalShared\Exceptions;

use RuntimeException;

/**
 * Thrown when application code attempts to mutate a read-only replica table
 * outside of the sanctioned ingestion path.
 */
class ReplicaIsReadOnlyException extends RuntimeException
{
}

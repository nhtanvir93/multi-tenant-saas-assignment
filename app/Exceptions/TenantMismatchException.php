<?php

declare(strict_types=1);

namespace App\Exceptions;

use LogicException;

/** A write tried to put a row into another tenant, or to move a row between tenants. */
final class TenantMismatchException extends LogicException {}

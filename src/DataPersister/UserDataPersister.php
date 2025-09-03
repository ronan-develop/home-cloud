<?php

namespace App\DataPersister;

/**
 * Legacy shim left for backward compatibility during migration to API Platform v4.
 * The project now uses `App\State\Processor\UserProcessor`.
 * If this class is still referenced, replace usages by the new Processor.
 */
final class UserDataPersister
{
    public function __construct()
    {
        throw new \LogicException('UserDataPersister is deprecated: use App\\State\\Processor\\UserProcessor instead.');
    }
}

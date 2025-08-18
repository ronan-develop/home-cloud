<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\InfoApiOutput;

class InfoApiProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): InfoApiOutput
    {
        $dto = new InfoApiOutput();

        return $dto;
    }
}

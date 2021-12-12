<?php
declare(strict_types=1);

namespace LightWeightMultiplex;

interface MultiplexCommandInterface
{
    public function getCommand(): array;
    public function passEnvs(): array;
    public function enableSupervisor(): bool;
    public function getMultiplexProcess(): MultiplexProcessInterface;
}

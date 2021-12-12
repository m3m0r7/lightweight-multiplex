<?php
declare(strict_types=1);

namespace LightWeightMultiplex;

interface MultiplexProcessInterface
{
    public static function factory(MultiplexCommandInterface $command);
    public function getPID(): int;

    /**
     * @return resource|null
     */
    public function getOutStream();

    /**
     * @return resource|null
     */
    public function getErrorStream();
    public function runAt(): \DateTimeImmutable;
    public function isRunning(): bool;
    public function isTerminated(): bool;
    public function terminate(): void;
    public function create(): void;

    /**
     * @return resource|null
     */
    public function getResource();
}

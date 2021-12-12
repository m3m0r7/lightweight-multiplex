<?php
declare(strict_types=1);

namespace LightWeightMultiplex;

class MultiplexObserver
{
    protected const BUFFER = 8192;

    /**
     * @var array{MultiplexCommandInterface, callable}[]
     */
    protected array $listeners = [];

    public function listen(MultiplexCommandInterface $command, callable $watcher): static
    {
        $this->listeners[] = [$command, $watcher];
        return $this;
    }

    public function run(): void
    {
        $listenerWithProcesses = $this->createProcesses();

        while (true) {
            [$outStreams, $errorStreams, $terminatedProcessIndexes] = array_reduce(
                array_keys($listenerWithProcesses),
                static fn (array $carry, int $index) => ($commandWithProcess = $listenerWithProcesses[$index][1])->isTerminated()
                    ? [
                        $carry[0],
                        $carry[1],
                        [...$carry[2], $index],
                    ]
                    : [
                        [...$carry[0], $commandWithProcess[1]->getOutStream()],
                        [...$carry[1], $commandWithProcess[1]->getErrorStream()],
                        $carry[2],
                    ],
                [[], [], []]
            );

            // Process terminated process
            if (!empty($terminatedProcessIndexes)) {
                foreach ($terminatedProcessIndexes as $terminatedProcessIndex) {
                    /**
                     * @var MultiplexCommandInterface $command
                     */
                    [$command] = $listenerWithProcesses[$terminatedProcessIndex];
                    unset($listenerWithProcesses[$terminatedProcessIndex]);

                    if ($command->enableSupervisor()) {
                        $listenerWithProcesses[$terminatedProcessIndex] = $process = $command->getMultiplexProcess();
                        $process->create();
                    }
                }
                $listenerWithProcesses = array_values($listenerWithProcesses);
            }

            $originalOutStreams = $outStreams;
            $originalErrorStreams = $errorStreams;

            $inStreams = null;

            stream_select(
                $outStreams,
                $inStreams,
                $errorStreams,
                0,
                200000,
            );

            $outStreamIndexes = $this->getUpdatedProcessIndexes($originalOutStreams, $outStreams);
            $errorStreamIndexes = $this->getUpdatedProcessIndexes($originalErrorStreams, $errorStreams);

            $this->process(
                WatchEventType::STDOUT,
                array_intersect_key($listenerWithProcesses, $outStreamIndexes)
            );
            $this->process(
                WatchEventType::STDERR,
                array_intersect_key($listenerWithProcesses, $errorStreamIndexes)
            );
        }
    }

    public function getUpdatedProcessIndexes(array $originalProcesses, array $updatedProcesses): array
    {
        return array_reduce(
            array_keys($originalProcesses),
            fn (array $carry, int $index) => in_array(
                $originalProcesses[$index],
                $updatedProcesses,
                true,
            ) ? [...$carry, $index] : $carry,
            []
        );
    }

    protected function process(WatchEventType $watchEventType, array $updatedListenerWithProcesses): void
    {
        /**
         * @var array{MultiplexCommandInterface, callable}
         * @var MultiplexProcess $process
         */
        foreach ($updatedListenerWithProcesses as [$listener, $process]) {
            [, $watcher] = $listener;
            if ($process->isTerminated()) {
                throw new MultiplexException("The {$watchEventType->name} stream is terminated");
            }
            $payload = fread(
                match ($watchEventType) {
                    WatchEventType::STDOUT => $process->getOutStream(),
                    WatchEventType::STDERR => $process->getErrorStream(),
                    default => throw new MultiplexException("Unknown watch event type: {$watchEventType->value}"),
                },
                static::BUFFER
            );
            $watcher($watchEventType, $payload);
        }
    }

    /**
     * @return array{array{MultiplexCommandInterface, callable}, MultiplexProcessInterface}[]
     */
    protected function createProcesses(): array
    {
        $listenerWithProcesses = [];
        foreach ($this->listeners as $listener) {
            [$command] = $listener;

            $process = $command
                ->getMultiplexProcess();

            $process->create();

            $listenerWithProcesses[] = [$listener, $process];
        }

        return $listenerWithProcesses;
    }
}

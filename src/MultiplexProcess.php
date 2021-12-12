<?php
declare(strict_types=1);

namespace LightWeightMultiplex;

class MultiplexProcess implements MultiplexProcessInterface
{
    protected const STDOUT = 1;
    protected const STDERR = 2;

    protected MultiplexCommandInterface $command;
    protected int $pid;
    protected \DateTimeImmutable $runAt;
    protected bool $isTerminated = false;

    /**
     * @var resource|null
     */
    protected $resource;

    /**
     * @var resource|null
     */
    protected $outStream;

    /**
     * @var resource
     */
    protected $errorStream;

    protected function __construct(MultiplexCommandInterface $command)
    {
        $this->command = $command;
    }

    public function __destruct()
    {
        $this->terminate();
    }

    public static function factory(MultiplexCommandInterface $command)
    {
        return new static($command);
    }

    public function getPID(): int
    {
        return $this->pid;
    }

    public function getOutStream()
    {
        return $this->outStream;
    }

    public function getErrorStream()
    {
        return $this->errorStream;
    }

    public function runAt(): \DateTimeImmutable
    {
        return $this->runAt;
    }

    public function isRunning(): bool
    {
        if ($this->isTerminated) {
            return false;
        }
        return proc_get_status($this->resource)['running'];
    }

    public function hasUnreadBytesInOut(): bool
    {
        return stream_get_meta_data($this->outStream)['unread_bytes'];
    }

    public function hasUnreadBytesInError(): bool
    {
        return stream_get_meta_data($this->errorStream)['unread_bytes'];
    }

    public function isTerminated(): bool
    {
        return $this->isTerminated ||
            stream_get_meta_data($this->outStream)['eof'] ||
            stream_get_meta_data($this->errorStream)['eof'] ||
            proc_get_status($this->resource)['stopped'];
    }

    public function terminate(): void
    {
        if ($this->isTerminated) {
            return;
        }

        if (is_resource($this->outStream)) {
            fclose($this->outStream);
        }

        if (is_resource($this->errorStream)) {
            fclose($this->errorStream);
        }

        if (is_resource($this->resource)) {
            proc_close($this->resource);
        }

        $this->outStream = null;
        $this->errorStream = null;
        $this->resource = null;

        $this->isTerminated = true;
    }

    public function create(): void
    {
        $this->resource = proc_open(
            $this->command->getCommand(),
            [
                static::STDOUT => ['pipe', 'w'],
                static::STDERR => ['pipe', 'w'],
            ],
            $pipes,
            getcwd(),
            $this->command->passEnvs(),
        );
        $this->runAt = new \DateTimeImmutable('now');
        $this->pid = proc_get_status($this->resource)['pid'];

        $this->outStream = $pipes[static::STDOUT];
        $this->errorStream = $pipes[static::STDERR];
    }

    public function getResource()
    {
        return $this->resource;
    }
}
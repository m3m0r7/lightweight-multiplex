<?php
declare(strict_types=1);

namespace LightWeightMultiplex;

enum WatchEventType: int
{
    case STDOUT = 1;
    case STDERR = 2;
}

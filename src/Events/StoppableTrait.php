<?php

namespace Onion\Framework\Server\Events;

trait StoppableTrait
{
    private bool $terminated = false;

    public function stopPropagation(): void
    {
        $this->terminated = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->terminated;
    }
}

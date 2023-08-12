<?php
declare(strict_types=1);

namespace Onion\Framework\Server\Contexts;
use Onion\Framework\Server\Interfaces\ContextInterface;

class AggregateContext implements ContextInterface
{
    public function __construct(private array $contexts)
    {
    }

	/**
	 * @return array
	 */
	public function getContextArray(): array
    {
        return array_merge(
            array_map(
                fn(ContextInterface $c) => $c->getContextArray(),
                $this->contexts,
            )
        );
	}

	/**
	 * @return array
	 */
	public function getContextOptions(): array
    {
        return [];
	}
}

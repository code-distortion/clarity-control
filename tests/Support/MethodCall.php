<?php

namespace CodeDistortion\ClarityControl\Tests\Support;

/**
 * Class to represent a method call, and its parameters.
 */
class MethodCall
{
    /** @var string The method to call. */
    private string $method;

    /** @var mixed[] The arguments to pass. */
    private array $args;

    /**
     * @param string  $method The method to call.
     * @param mixed[] $args   The arguments to pass.
     * @return void
     */
    public function __construct(string $method, array $args)
    {
        $this->method = $method;
        $this->args = $args;
    }

    /**
     * Retrieve the method.
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Retrieve the arguments.
     *
     * @return mixed[]
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * Retrieve the arguments, with arrays flattened out.
     *
     * @param callable|null $filterCallback The callback to filter the arguments by.
     * @return mixed[]
     */
    public function getArgsFlat(callable $filterCallback = null): array
    {
        $flatArgs = [];
        foreach ($this->args as $arg) {
            $arg = is_array($arg)
                ? $arg
                : [$arg];
            $flatArgs = array_merge($flatArgs, $arg);
        }

        return !is_null($filterCallback)
            ? array_filter($flatArgs, $filterCallback)
            : $flatArgs;
    }
}

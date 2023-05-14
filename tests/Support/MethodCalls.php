<?php

namespace CodeDistortion\ClarityControl\Tests\Support;

use CodeDistortion\Staticall\Staticall;

/**
 * Class to contain a list of method calls.
 *
 * @codingStandardsIgnoreStart
 *
 * @method static self add(string $method, ?array $args = []) Add a method to call.
 *
 * @codingStandardsIgnoreEnd
 */
class MethodCalls
{
    use Staticall;



    /** @var MethodCall[] The method calls to make. */
    private array $methodCalls = [];



    /**
     * Static constructor method.
     *
     * @return self
     */
    public static function new(): self
    {
        return new self();
    }

    /**
     * Add a method to call.
     *
     * @param string       $method The method to call.
     * @param mixed[]|null $args   The arguments to pass.
     * @return $this
     */
    protected function callAdd(string $method, ?array $args = []): self
    {
        if (is_array(($args))) {
            $this->methodCalls[] = new MethodCall($method, $args);
        }
        return $this;
    }

    /**
     * Retrieve the method calls, with an optional method name filter.
     *
     * @param string|string[]|null $method The method type to filter by.
     * @return MethodCall[]
     */
    public function getCalls(string|array $method = null): array
    {
        if (is_null($method)) {
            return $this->methodCalls;
        }

        $methods = is_array($method)
            ? $method
            : [$method];

        $methodCalls = [];
        foreach ($this->methodCalls as $methodCall) {
            if (in_array($methodCall->getMethod(), $methods)) {
                $methodCalls[] = $methodCall;
            }
        }
        return $methodCalls;
    }

    /**
     * Check if any methods have been specified.
     *
     * @return boolean
     */
    public function hasCalls(): bool
    {
        return count($this->methodCalls) > 0;
    }

    /**
     * Check if a particular method call exists.
     *
     * @param string $method The method type to filter by.
     * @return boolean
     */
    public function hasCall(string $method): bool
    {
        foreach ($this->methodCalls as $methodCall) {
            if ($methodCall->getMethod() == $method) {
                return true;
            }
        }
        return false;
    }

    /**
     * Pick all the parameters of a particular method call.
     *
     * @param string        $method         The method call type to pick arguments from.
     * @param callable|null $filterCallback The callback to filter the arguments by.
     * @return mixed[]
     */
    public function getAllCallArgsFlat(string $method, callable $filterCallback = null): array
    {
        return collect($this->methodCalls)
            ->filter(fn(MethodCall $m) => $m->getMethod() == $method)
            ->map(fn(MethodCall $m) => $m->getArgsFlat($filterCallback))
            ->flatten(1)
            ->toArray();
    }
}

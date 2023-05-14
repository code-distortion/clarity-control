<?php

namespace CodeDistortion\ClarityControl\Support;

use CodeDistortion\ClarityContext\Support\Framework\Framework;
use CodeDistortion\ClarityControl\CatchType;
use CodeDistortion\ClarityControl\Settings;
use Throwable;

/**
 * Class that allows for values to be retrieved from a CatchType, without having to add extra methods to CatchType.
 */
class Inspector extends CatchType
{
    /** @var CatchType The catch-type to inspect. */
    private CatchType $catchType;

    /** @var CatchType The fallback catch-type to inherit details from if they haven't been set. */
    private CatchType $fallbackCatchType;



    /**
     * Constructor.
     *
     * @param CatchType      $catchType         The catch-type to inspect.
     * @param CatchType|null $fallbackCatchType The fall-back catch-type to inherit values from.
     */
    public function __construct(CatchType $catchType, ?CatchType $fallbackCatchType = null)
    {
        $this->catchType = $catchType;
        $this->fallbackCatchType = $fallbackCatchType
            ?? $catchType;
    }



    /**
     * Check if an exception matches this catch-type.
     *
     * @param Throwable $e The exception that occurred.
     * @return boolean
     */
    public function checkForMatch(Throwable $e): bool
    {
        if (!$this->exceptionTypeMatches($e)) {
            return false;
        }

        $a = $this->exceptionMessageMatches($e);
        $b = $this->exceptionMessageMatchesRegex($e);
        if (($a === false || $b === false) && $a !== true && $b !== true) {
            return false;
        }
        return true;
    }

    /**
     * Check if an exception's type (class) should be picked up by this catch-type.
     *
     * @param Throwable $e The exception that occurred.
     * @return boolean
     */
    private function exceptionTypeMatches(Throwable $e): bool
    {
        $classes = $this->catchType->exceptionClasses;

        if (!count($classes)) {
            return true; // it doesn't need to match any
        }

        foreach ($classes as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if an exception message should be picked up by this catch-type based on the allowed strings.
     *
     * @param Throwable $e The exception that occurred.
     * @return boolean|null
     */
    private function exceptionMessageMatches(Throwable $e): ?bool
    {
        $matchStrings = $this->catchType->matchStrings
            ?: $this->fallbackCatchType->matchStrings;

        if (!count($matchStrings)) {
            return null; // it doesn't need to match any
        }

        return in_array($e->getMessage(), $matchStrings, true);
    }

    /**
     * Check if an exception message should be picked up by this catch-type based on the allowed regexes.
     *
     * @param Throwable $e The exception that occurred.
     * @return boolean|null
     */
    private function exceptionMessageMatchesRegex(Throwable $e): ?bool
    {
        $regexes = $this->catchType->matchRegexes
            ?: $this->fallbackCatchType->matchRegexes;

        if (!count($regexes)) {
            return null; // it doesn't need to match any
        }

        foreach ($regexes as $regex) {
            if (preg_match($regex, $e->getMessage())) {
                return true;
            }
        }
        return false;
    }



    /**
     * Retrieve the exception classes to catch.
     *
     * @return string[]
     */
    public function getExceptionClasses(): array
    {
        return $this->catchType->exceptionClasses;
    }

    /**
     * Get the callbacks that have been set.
     *
     * @return callable[]
     */
    public function resolveCallbacks(): array
    {
        return $this->catchType->callbacks
            ?: $this->fallbackCatchType->callbacks;
    }

    /**
     * Get the known issues that have been set.
     *
     * @return string[]
     */
    public function resolveKnown(): array
    {
        return $this->catchType->known
            ?: $this->fallbackCatchType->known;
    }

    /**
     * Find out if there are any known issues.
     *
     * @return boolean
     */
    public function hasKnown(): bool
    {
        return count($this->resolveKnown()) > 0;
    }

    /**
     * Get the channels that have been set.
     *
     * @return string[]
     */
    public function resolveChannels(): array
    {
        return $this->catchType->channels
            ?: $this->fallbackCatchType->channels
            ?: Framework::config()->pickBestChannels($this->hasKnown());
    }

    /**
     * Get the log reporting level that has been set.
     *
     * @return string|null
     */
    public function resolveLevel(): ?string
    {
        return $this->catchType->level
            ?? $this->fallbackCatchType->level
            ?? Framework::config()->pickBestLevel($this->hasKnown())
            ?? Settings::REPORTING_LEVEL_ERROR;
    }

    /**
     * Check whether this catch-type intends the exception to be reported or not.
     *
     * @return boolean
     */
    public function shouldReport(): bool
    {
        return $this->catchType->report
            ?? $this->fallbackCatchType->report
            ?? Framework::config()->getReport()
            ?? true; // default true
    }

    /**
     * Check whether this catch-type intends the exception to be re-thrown or not.
     *
     * @return boolean|callable|null
     */
    public function pickRethrow(): bool|callable|null
    {
        return $this->catchType->rethrow
            ?? $this->fallbackCatchType->rethrow
            ?? false; // default false
    }

    /**
     * Get the default value (used when an exception has occurred).
     *
     * @return mixed
     */
    public function resolveDefault(): mixed
    {
        return $this->catchType->defaultIsSet
            ? $this->catchType->default
            : $this->fallbackCatchType->default;
    }

    /**
     * Retrieve the finally callable.
     *
     * @return callable|null
     */
    public function getFinally(): ?callable
    {
        return $this->catchType->finally;
    }





    /**
     * Retrieve the exception classes to match (used while running tests).
     *
     * @internal
     *
     * @return string[]
     */
    public function getRawExceptionClasses(): array
    {
        return $this->catchType->exceptionClasses;
    }

    /**
     * Retrieve the exception message strings to match (used while running tests).
     *
     * @internal
     *
     * @return string[]
     */
    public function getRawMatchStrings(): array
    {
        return $this->catchType->matchStrings;
    }

    /**
     * Retrieve the exception message strings to match using regexes (used while running tests).
     *
     * @internal
     *
     * @return string[]
     */
    public function getRawMatchRegexes(): array
    {
        return $this->catchType->matchRegexes;
    }

    /**
     * Retrieve the exception callbacks (used while running tests).
     *
     * @internal
     *
     * @return callable[]
     */
    public function getRawCallbacks(): array
    {
        return $this->catchType->callbacks;
    }

    /**
     * Retrieve the known issues (used while running tests).
     *
     * @internal
     *
     * @return string[]
     */
    public function getRawKnown(): array
    {
        return $this->catchType->known;
    }

    /**
     * Retrieve the channels (used while running tests).
     *
     * @internal
     *
     * @return string[]
     */
    public function getRawChannels(): array
    {
        return $this->catchType->channels;
    }

    /**
     * Retrieve the report level (used while running tests).
     *
     * @internal
     *
     * @return string|null
     */
    public function getRawLevel(): ?string
    {
        return $this->catchType->level;
    }

    /**
     * Retrieve whether to report exceptions or not (used while running tests).
     *
     * @internal
     *
     * @return boolean|null
     */
    public function getRawReport(): ?bool
    {
        return $this->catchType->report;
    }

    /**
     * Retrieve whether to rethrow exceptions or not, or the callable that decides (used while running tests).
     *
     * @internal
     *
     * @return boolean|callable|null
     */
    public function getRawRethrow(): bool|callable|null
    {
        return $this->catchType->rethrow;
    }

    /**
     * Retrieve the default value (used while running tests).
     *
     * @internal
     *
     * @return mixed
     */
    public function getRawDefault(): mixed
    {
        return $this->catchType->default;
    }
}

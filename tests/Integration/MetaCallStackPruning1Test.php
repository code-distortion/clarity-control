<?php

namespace CodeDistortion\ClarityControl\Tests\Integration;

use CodeDistortion\ClarityContext\Clarity;
use CodeDistortion\ClarityContext\Context;
use CodeDistortion\ClarityContext\Support\CallStack\MetaData\CallMeta;
use CodeDistortion\ClarityContext\Support\CallStack\MetaData\ContextMeta;
use CodeDistortion\ClarityContext\Support\CallStack\MetaData\ExceptionCaughtMeta;
use CodeDistortion\ClarityContext\Support\CallStack\MetaData\ExceptionThrownMeta;
use CodeDistortion\ClarityContext\Support\CallStack\MetaData\LastApplicationFrameMeta;
use CodeDistortion\ClarityControl\Control;
use CodeDistortion\ClarityControl\Tests\LaravelTestCase;
use Exception;

/**
 * Test the MetaCallStack class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class MetaCallStackPruning1Test extends LaravelTestCase
{
    /**
     * Test that meta-data is purged when making calls, returning, and branching.
     *
     * Each closure will alternately throw an exception, catch, re-throw.
     *
     * e.g. ----- oneA() -- oneB()
     *        \-- twoA() -- twoB()
     *
     * @test
     * @dataProvider metaPurgingDataProvider
     *
     * @param boolean                 $oneATrigger Should closure "one-a" trigger an exception?.
     * @param boolean                 $oneARethrow Should closure "one-a" re-throw exceptions?.
     * @param boolean                 $oneBTrigger Should closure "one-b" trigger an exception?.
     * @param boolean                 $oneBRethrow Should closure "one-b" re-throw exceptions?.
     * @param boolean                 $twoATrigger Should closure "two-a" trigger an exception?.
     * @param boolean                 $twoARethrow Should closure "two-a" re-throw exceptions?.
     * @param boolean                 $twoBTrigger Should closure "two-b" trigger an exception?.
     * @param boolean                 $twoBRethrow Should closure "two-b" re-throw exceptions?.
     * @param array<integer, mixed[]> $expected    The expected meta-data.
     * @return void
     */
    public static function test_meta_purging(
        bool $oneATrigger,
        bool $oneARethrow,
        bool $oneBTrigger,
        bool $oneBRethrow,
        bool $twoATrigger,
        bool $twoARethrow,
        bool $twoBTrigger,
        bool $twoBRethrow,
        array $expected
    ): void {

        $allCondensedMetaData = [];
        $callback = function (Context $context) use (&$allCondensedMetaData) {

            $condensedMetaData = [];
            foreach ($context->getCallStack()->getMeta() as $meta) {

                if ($meta instanceof LastApplicationFrameMeta) {
                    $condensedMetaData[] = 'last-application-frame';
                } elseif ($meta instanceof ExceptionThrownMeta) {
                    $condensedMetaData[] = 'exception-thrown';
                } elseif ($meta instanceof ExceptionCaughtMeta) {
                    $condensedMetaData[] = 'exception-caught';
                } elseif ($meta instanceof ContextMeta) {
                    $condensedMetaData[] = $meta->getContext();
                } elseif ($meta instanceof CallMeta) {
                    $condensedMetaData[] = [
                        'known' => $meta->getKnown()[0] ?? null,
                        'caughtHere' => $meta->wasCaughtHere(),
                    ];
                } else {
                    throw new Exception('Unexpected Meta class: ' . get_class($meta));
                }
            }

            $allCondensedMetaData[] = $condensedMetaData;
        };



        $oneB = function () use ($oneBTrigger, $oneBRethrow, $callback) {

            Clarity::context('context one-b');

            Control::prepare(self::maybeThrow($oneBTrigger))
                ->known('known one-b')
                ->callback($callback)
                ->rethrow($oneBRethrow)
                ->execute();
        };

        $oneA = function () use ($oneATrigger, $oneARethrow, $oneB, $callback) {

            Clarity::context('context one-a');

            Control::prepare(self::maybeThrow($oneATrigger, $oneB))
                ->known('known one-a')
                ->callback($callback)
                ->rethrow($oneARethrow)
                ->execute();
        };



        $twoB = function () use ($twoBTrigger, $twoBRethrow, $callback) {

            Clarity::context('context two-b');

            Control::prepare(self::maybeThrow($twoBTrigger))
                ->known('known two-b')
                ->callback($callback)
                ->rethrow($twoBRethrow)
                ->execute();
        };

        $twoA = function () use ($twoATrigger, $twoARethrow, $twoB, $callback) {

            Clarity::context('context two-a');

            Control::prepare(self::maybeThrow($twoATrigger, $twoB))
                ->known('known two-a')
                ->callback($callback)
                ->rethrow($twoARethrow)
                ->execute();
        };



        // start making the calls
        Clarity::context('context one');

        Control::prepare($oneA)
            ->known('known one')
            ->callback($callback)
            ->execute();

        Clarity::context('context two');

        Control::prepare($twoA)
            ->known('known two')
            ->callback($callback)
            ->execute();



        self::assertSame($expected, $allCondensedMetaData);
    }

    /**
     * DataProvider for test_meta_purging().
     *
     * @return array<integer, mixed>
     */
    public static function metaPurgingDataProvider()
    {
        $return = [];

        foreach ([false, true] as $oneATrigger) {
            foreach ([false, true] as $oneARethrow) {
                foreach ([false, true] as $oneBTrigger) {
                    foreach ([false, true] as $oneBRethrow) {
                        foreach ([false, true] as $twoATrigger) {
                            foreach ([false, true] as $twoARethrow) {
                                foreach ([false, true] as $twoBTrigger) {
                                    foreach ([false, true] as $twoBRethrow) {

                                        $expected = self::resolveExpected(
                                            $oneATrigger,
                                            $oneARethrow,
                                            $oneBTrigger,
                                            $oneBRethrow,
                                            $twoATrigger,
                                            $twoARethrow,
                                            $twoBTrigger,
                                            $twoBRethrow,
                                        );

                                        if (!is_null($expected)) {
                                            $return[] = [
                                                'oneATrigger' => $oneATrigger,
                                                'oneARethrow' => $oneARethrow,
                                                'oneBTrigger' => $oneBTrigger,
                                                'oneBRethrow' => $oneBRethrow,
                                                'twoATrigger' => $twoATrigger,
                                                'twoARethrow' => $twoARethrow,
                                                'twoBTrigger' => $twoBTrigger,
                                                'twoBRethrow' => $twoBRethrow,
                                                'expected' => $expected,
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Determine what the outcome should be for the given trigger and rethrow settings.
     *
     * @param boolean $oneATrigger Should closure "one-a" trigger an exception?.
     * @param boolean $oneARethrow Should closure "one-a" re-throw exceptions?.
     * @param boolean $oneBTrigger Should closure "one-b" trigger an exception?.
     * @param boolean $oneBRethrow Should closure "one-b" re-throw exceptions?.
     * @param boolean $twoATrigger Should closure "two-a" trigger an exception?.
     * @param boolean $twoARethrow Should closure "two-a" re-throw exceptions?.
     * @param boolean $twoBTrigger Should closure "two-b" trigger an exception?.
     * @param boolean $twoBRethrow Should closure "two-b" re-throw exceptions?.
     * @return array<integer, mixed>|null
     */
    private static function resolveExpected(
        bool $oneATrigger,
        bool $oneARethrow,
        bool $oneBTrigger,
        bool $oneBRethrow,
        bool $twoATrigger,
        bool $twoARethrow,
        bool $twoBTrigger,
        bool $twoBRethrow,
    ): ?array {

        // "one-b" can't trigger, as "one-a" will trigger first
        if (($oneBTrigger) && ($oneATrigger)) {
            return null;
        }
        // "one-b" can't rethrow, as no exception will have been triggered
        if (($oneBRethrow) && (!$oneBTrigger)) {
            return null;
        }
        // "one-a" can't rethrow, as no exception will have been triggered
        if ($oneARethrow) {
            if ((!$oneATrigger) && (!$oneBTrigger || !$oneBRethrow)) {
                return null;
            }
        }

        // path "two" shouldn't be used, because an exception was already triggered via path "one"
        if (($twoATrigger || $twoBTrigger) && ($oneATrigger || $oneBTrigger)) {
            return null;
        }

        // "two-b" can't trigger, as "two-a" will trigger first
        if (($twoBTrigger) && ($twoATrigger)) {
            return null;
        }
        // "two-b" can't rethrow, as no exception will have been triggered
        if (($twoBRethrow) && (!$twoBTrigger)) {
            return null;
        }
        // "two-a" can't rethrow, as no exception will have been triggered
        if ($twoARethrow) {
            if ((!$twoATrigger) && (!$twoBTrigger || !$twoBRethrow)) {
                return null;
            }
        }



        // go down path "one"
        $snapshot = self::makeSnapshot(
            'one',
            $oneATrigger,
            $oneARethrow,
            $oneBTrigger,
            $oneBRethrow
        );

        // go down path "two"
        if (count($snapshot) <= 1) {
            $frameToAdd = self::makeFrame('one', false, false, false);
            $newSnapshot = self::makeSnapshot(
                'two',
                $twoATrigger,
                $twoARethrow,
                $twoBTrigger,
                $twoBRethrow,
                $frameToAdd
            );
            $snapshot = array_merge($snapshot, $newSnapshot);
        }

        return self::formatSnapshots(
            self::runSnapshotThroughSteps($snapshot)
        );
    }

    /**
     * Build one snapshot worth of frames.
     *
     * @param string       $name       The name that representing the call-level.
     * @param boolean      $aTrigger   Should closure "a" trigger an exception?.
     * @param boolean      $aRethrow   Should closure "a" re-throw exceptions?.
     * @param boolean      $bTrigger   Should closure "b" trigger an exception?.
     * @param boolean      $bRethrow   Should closure "b" re-throw exceptions?.
     * @param mixed[]|null $frameToAdd A frame to arbitrarily add to the beginning, provided some other frame was used.
     * @return array<integer, array<integer, mixed>>
     */
    private static function makeSnapshot(
        string $name,
        bool $aTrigger,
        bool $aRethrow,
        bool $bTrigger,
        bool $bRethrow,
        ?array $frameToAdd = null,
    ): array {

        $frameShouldBeIncluded = false;
        $exceptionIsActive = true;
        $snapshot = [];

        if ($bTrigger) {

            $snapshot[] = self::makeFrame(
                "$name-b",
                $exceptionIsActive,
                true,
                $bTrigger,
            );
            $frameShouldBeIncluded = true;

            if (!$bRethrow) {
                $exceptionIsActive = false;
            }
        }

        if ($aTrigger || $frameShouldBeIncluded) {

            $snapshot[] = self::makeFrame(
                "$name-a",
                $exceptionIsActive,
                true,
                $aTrigger,
            );
            $frameShouldBeIncluded = true;

            if (!$aRethrow) {
                $exceptionIsActive = false;
            }
        }

        if ($frameShouldBeIncluded) {
            $snapshot[] = self::makeFrame(
                $name,
                $exceptionIsActive,
                true,
                false,
            );
        }

        if (count($snapshot) && $frameToAdd) {
            $snapshot[] = $frameToAdd;
        }

        return $snapshot;
    }

    /**
     * Make one frame's worth of meta-object details.
     *
     * @param string  $name             The name that representing the call-level.
     * @param boolean $canCatch         Whether this frame will catch the exception at some point.
     * @param boolean $includeExecution Whether to include the execution meta-data or not.
     * @param boolean $willTrigger      Whether the exception will be triggered by this closure or not.
     * @return array<integer, mixed>
     */
    private static function makeFrame(
        string $name,
        bool $canCatch,
        bool $includeExecution,
        bool $willTrigger
    ): array {

        $return = [];

        $return[] = "context $name";

        if ($includeExecution) {
            $return[] = [
                "known" => "known $name",
                "caughtHere" => null,
                "canCatch" => $canCatch, // will be removed later
            ];
        }

        if ($willTrigger) {
            $return[] = 'last-application-frame';
            $return[] = 'exception-thrown';
        }

        return $return;
    }

    /**
     * Run a callstack snapshot through the steps, where each "frame" catches the exception.
     *
     * @param array<integer, array<integer, mixed>> $snapshot The snapshot to loop through.
     * @return array<integer, array<integer, array<integer, mixed>>>
     */
    private static function runSnapshotThroughSteps(array $snapshot): array
    {
        $snapshots = [];

        // make this many snapshots
        for ($count = 0; $count < count($snapshot); $count++) {

            // loop through each frame
            $newSnapshot = $snapshot;
            $foundFrameThatCaught = false;
            for ($index = 0; $index < count($newSnapshot); $index++) {

                // look for "call" entry
                if (!array_key_exists(1, $newSnapshot[$index])) {
                    continue;
                }

                if (!is_array($newSnapshot[$index][1])) {
                    continue;
                }

                /** @var array<string, mixed> $callMeta */
                $callMeta = $newSnapshot[$index][1];

                // remove the "known" setting if deeper frame has already caught it
                if ($foundFrameThatCaught) {
                    $callMeta['known'] = null;
                }

                // update the "caughtHere" setting for this frame
                $caughtHere = ($index == $count) && $callMeta['canCatch'];

                $foundFrameThatCaught = $foundFrameThatCaught || $caughtHere;

                $callMeta['caughtHere'] = $caughtHere;
                unset($callMeta['canCatch']);

                $newSnapshot[$index][1] = $callMeta;

                // add in the "exception-caught" meta-data after the "call" one
                if ($caughtHere) {
                    array_splice($newSnapshot[$index], 2, 0, ['exception-caught']);
                }
            }

            // keep the new snapshot if the exception was caught *somewhere*
            if ($foundFrameThatCaught) {
                $snapshots[] = $newSnapshot;
            }
        }

        return $snapshots;
    }

    /**
     * Format the snapshot to be in the format that's used when running the test.
     *
     * @param array<integer, array<integer, array<integer, mixed>>> $snapshots The snapshots to process.
     * @return array<integer, array<integer, mixed>>
     */
    private static function formatSnapshots(array $snapshots): array
    {
        foreach ($snapshots as $index => $snapshot) {

            // put the frames in the order that they'll appear
            $snapshot = array_reverse($snapshot);

            // flatten them, so the meta-details for each frame are in a single list
            $flattenedSnapshot = [];
            /** @var array<integer, mixed> $frame */
            foreach ($snapshot as $frame) {
                $flattenedSnapshot = array_merge($flattenedSnapshot, $frame);
            }

            $snapshots[$index] = $flattenedSnapshot;
        }

        return $snapshots;
    }



    /**
     * Build a closure that throws a new exception.
     *
     * @param boolean       $shouldThrow Whether an exception should be thrown or not.
     * @param callable|null $alternative The callable to use when not throwing an exception.
     * @return callable
     * @throws Exception Doesn't throw this, but phpcs expects this to be here.
     */
    private static function maybeThrow(bool $shouldThrow, ?callable $alternative = null): callable
    {
        $alternative ??= fn() => 'a';

        return $shouldThrow
            ? fn() => throw new Exception()
            : $alternative;
    }
}

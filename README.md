# Clarity Control - Handle Your Exceptions

[![Latest Version on Packagist](https://img.shields.io/packagist/v/code-distortion/clarity-control.svg?style=flat-square)](https://packagist.org/packages/code-distortion/clarity-control)
![PHP Version](https://img.shields.io/badge/PHP-8.0%20to%208.3-blue?style=flat-square)
![Laravel](https://img.shields.io/badge/laravel-8%20to%2010-blue?style=flat-square)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/code-distortion/clarity-control/run-tests.yml?branch=master&style=flat-square)](https://github.com/code-distortion/clarity-control/actions)
[![Buy The World a Tree](https://img.shields.io/badge/treeware-%F0%9F%8C%B3-lightgreen?style=flat-square)](https://plant.treeware.earth/code-distortion/clarity-control)
[![Contributor Covenant](https://img.shields.io/badge/contributor%20covenant-v2.1%20adopted-ff69b4.svg?style=flat-square)](.github/CODE_OF_CONDUCT.md)

***code-distortion/clarity-control*** is a Laravel package that lets you catch and log exceptions with a fluent interface.

``` php
// run $callable - will catch + report() any exceptions
Control::run($callable);

// do the same, but with extra optional configuration
Control::prepare($callable)
    ->channel('slack')
    ->level(Settings::REPORTING_LEVEL_WARNING)
    ->debug() … ->emergency()
    ->default('some-value')
    ->catch(DivisionByZeroError::class)
    ->match('Undefined variable $a')
    ->matchRegex('/^Undefined variable /')
    ->known('https://company.atlassian.net/browse/ISSUE-1234')
    ->report() or ->dontReport()
    ->rethrow() or ->dontRethrow() or ->rethrow($callable)
    ->callback($callable)
    ->finally($callable)
    ->execute();
```



<br />



## Clarity Suite

Clarity Control is a part of the ***Clarity Suite***, designed to let you manage exceptions more easily:
- [Clarity Context](https://github.com/code-distortion/clarity-context) - Understand Your Exceptions
- [Clarity Logger](https://github.com/code-distortion/clarity-logger) - Useful Exception Logs
- **Clarity Control** - Handle Your Exceptions



<br />



## Table of Contents

- [Installation](#installation)
  - [Config File](#config-file)
- [Catching Exceptions](#catching-exceptions)
- [Configuring The Way Exceptions Are Caught](#configuring-the-way-exceptions-are-caught)
  - [Note About Logging](#note-about-logging)
- [Log Channel](#log-channel)
- [Log Level](#log-level)
- [Default Return Value](#default-return-value)
- [Catching Selectively](#catching-selectively)
- [Recording "Known" Issues](#recording-known-issues)
- [Disabling Logging](#disabling-logging)
- [Re-throwing Exceptions](#re-throwing-exceptions)
- [Suppressing Exceptions](#suppressing-exceptions)
- [Callbacks](#callbacks)
  - [Using The Context Object in Callbacks](#using-the-context-object-in-callbacks)
  - [Suppressing Exceptions On The Fly](#suppressing-exceptions-on-the-fly)
  - [Global Callbacks](#global-callbacks)
- [Advanced Catching](#advanced-catching)
- [Retrieving Exceptions](#retrieving-exceptions)



## Installation

Install the package via composer:

``` bash
composer require code-distortion/clarity-control
```



### Config File

Use the following command if you would like to publish the `config/code_distortion.clarity_control.php` config file:

``` bash
php artisan vendor:publish --provider="CodeDistortion\ClarityControl\ServiceProvider" --tag="config"
```



## Catching Exceptions

Clarity Control deals with flow control. Which is to say, it provides ways to catch and manage exceptions.

Whilst you can still use [try / catch statements](https://www.php.net/manual/en/language.exceptions.php) of course, you can write them this way instead:

``` php
use CodeDistortion\ClarityControl\Control;

Control::run($callable);

// is equivalent to:
try {
    $callable(); // … do something
} catch (Exception $e) {
    report($e);
}
```

Control will run the *callable* passed to `Control::run(…)`. If an exception occurs, it will be caught and reported using Laravel's `report()` helper.

The code following it will continue to run afterwards.

> ***Tip:*** [Laravel's *dependency injection*](https://laravel.com/docs/10.x/container#when-to-use-the-container) system is used to run your callable. Just type-hint your parameters and they'll be resolved for you.





## Configuring The Way Exceptions Are Caught

Another way to write this is to call `Control::prepare($callable)`, and then `->execute()` afterwards.

``` php
use CodeDistortion\ClarityControl\Control;

Control::prepare($callable)->execute();
// is equivalent to:
Control::run($callable);
```

These are the same, except that when using `Control::prepare($callable)` and `->execute()`, there's an opportunity to set some configuration values in-between…

Here is the list of the methods you can use to configure the way Control catches exceptions (they're explained in more detail below):

``` php
use CodeDistortion\ClarityControl\Control;
use CodeDistortion\ClarityControl\Settings;

Control::prepare($callable)
    ->channel('slack')
    ->level(Settings::REPORTING_LEVEL_WARNING)
    ->debug() … ->emergency()
    ->default('default')
    ->catch(DivisionByZeroError::class)
    ->match('Undefined variable $a')
    ->matchRegex('/^Undefined variable /')
    ->known('https://company.atlassian.net/browse/ISSUE-1234')
    ->report() or ->dontReport()
    ->rethrow() or ->dontRethrow() or ->rethrow($callable)
    ->callback($callable)
    ->finally($callable)
    ->execute();
```



### Note About Logging

This package uses [Clarity Context](https://github.com/code-distortion/clarity-context#logging-exceptions), and some settings require a logger that's also aware of *Clarity Context* (such as [Clarity Logger](https://github.com/code-distortion/clarity-logger)) to work. Specifically, the *channel*, *log level*, and *"known" issue* settings won't appear anywhere otherwise.

These details are added to the `Context` object that *Clarity Context* produces when an exception occurs. And it's up to the logger to use its values.

See [Clarity Context](https://github.com/code-distortion/clarity-context#logging-exceptions) for more information about this `Context` object.

See [Clarity Logger](https://github.com/code-distortion/clarity-logger) for a logger that understands the `Context` object.



## Log Channel

You can specify which Laravel log-channel you'd like to log to. The possible values come from your projects' `config/logging.php` file.

``` php
Control::prepare($callable)->channel('slack')->execute();
```

You can specify more than one if you'd like.

``` php
Control::prepare($callable)->channel(['stack', 'slack'])->execute();
```

> ***Note:*** This setting requires a logging tool that's aware of Clarity. See the [Note About Logging](#note-about-logging) for more information.

> See [Laravel's documentation about logging](https://laravel.com/docs/10.x/logging#available-channel-drivers) for more information about Log Channels.



## Log Level

You can specify the reporting level you'd like to use when logging.

``` php
use CodeDistortion\ClarityControl\Settings;

Control::prepare($callable)->debug()->execute();
Control::prepare($callable)->info()->execute();
Control::prepare($callable)->notice()->execute();
Control::prepare($callable)->warning()->execute();
Control::prepare($callable)->error()->execute();
Control::prepare($callable)->critical()->execute();
Control::prepare($callable)->alert()->execute();
Control::prepare($callable)->emergency()->execute();
// or
Control::prepare($callable)->level(Settings::REPORTING_LEVEL_WARNING)->execute(); // etc
```

> ***Note:*** This setting requires a logging tool that's aware of Clarity. See the [Note About Logging](#note-about-logging) for more information.

> See [Laravel's documentation about logging](https://laravel.com/docs/10.x/logging#writing-log-messages) for more information about Log Levels.



## Default Return Value

You can specify the default value to return when an exception occurs by passing a second parameter to `Control::run()` or `Control::prepare()`.

``` php
$result = Control::run($callable, $default);
// or
$result = Control::prepare($callable, $default)->execute();
```

You can also call `->default()` after calling `Control::prepare()`

``` php
$result = Control::prepare($callable)->default($default)->execute();
```

> ***Tip:*** If the default value is *callable*, Control will run it (when needed) to resolve the value.



## Catching Selectively

You can choose to only catch certain types of exceptions. Other exceptions will ignored.

``` php
use DivisionByZeroError;

Control::prepare($callable)
    ->catch(DivisionByZeroError::class) // only catch this type of exception
    ->execute();
```

``` php
Control::prepare($callable)
    ->match('Undefined variable $a')       // exact string match of $e->getMessage()
    ->matchRegex('/^Undefined variable /') // regex string match of $e->getMessage()
    ->execute();
```

You can specify multiple exception classes, match-strings or regexes.

When you specify `match()` and `matchRegex()`, only one of them needs to match the exception message.



## Recording "Known" Issues

If you use an issue management system like Jira, you can make a note of the issue/task the exception relates to:

``` php
Control::prepare($callable)
    ->known('https://company.atlassian.net/browse/ISSUE-1234')
    ->execute();
```

This gives you an opportunity to label exceptions while the fix is being worked on.

> ***Note:*** This setting requires a logging tool that's aware of Clarity. See the [Note About Logging](#note-about-logging) for more information.



## Disabling Logging

You can disable the reporting of exceptions once caught. This will stop `report()` from being triggered.

``` php
Control::prepare($callable)->dontReport()->execute();
// or
Control::prepare($callable)->report(false)->execute();
```



## Re-throwing Exceptions

If you'd like caught exceptions to be detected and reported, but *re-thrown* again afterwards, you can tell Control to rethrow them:

``` php
Control::prepare($callable)->rethrow()->execute();
```

If you'd like to rethrow a different exception, you can pass a closure to make the decision. It must return a Throwable / Exception, or true / false.

``` php
$closure = fn(Throwable $e) => new MyException('Something happened', 0, $e);
Control::prepare($callable)->rethrow($closure)->execute();
```



## Suppressing Exceptions

If you'd like to stop exceptions from being reported *and* rethrown once caught, you can suppress them altogether.

``` php
Control::prepare($callable)->suppress()->execute();
// is equivalent to:
Control::prepare($callable)->dontReport()->dontRethrow()->execute();
```



## Callbacks

You can add a custom callback to be run when an exception is caught. This can be used to either *do something* when an exception occurs, or to decide if the [the exception should be "suppressed"](#suppressing-exceptions).

You can add multiple callbacks if you like.

``` php
use CodeDistortion\ClarityControl\Context;
use Illuminate\Http\Request;
use Throwable;

$callback = fn(Throwable $e, Context $context, Request $request) => …; // do something

Control::prepare($callable)->callback($callback)->execute();
```

> ***Tip:*** [Laravel's *dependency injection*](https://laravel.com/docs/10.x/container#when-to-use-the-container) is used to run your callback. Just type-hint your parameters, like in the example above.
>
> Extra parameters you can use are:
> - The exception: when the parameter is named `$e` or `$exception`
> - The `Context` object: when type-hinted with `CodeDistortion\ClarityContext\Context`



### Using The Context Object in Callbacks

When you type-hint a callback parameter with `CodeDistortion\ClarityContext\Context`, you'll receive the `Context` object populated with details about the exception.

This is the *same* Context object from the [Clarity Context](https://github.com/code-distortion/clarity-context) package, and is designed to be used in `app/Exceptions/Handler.php` when reporting an exception. See Clarity Context's [documentation for more information](https://github.com/code-distortion/clarity-context#logging-exceptions).

As well as reading values from the Context object, you can update some of its values inside your callback. This lets you alter what happens on-the-fly.

``` php
use CodeDistortion\ClarityControl\Context;
use CodeDistortion\ClarityControl\Settings;

$callback = function (Context $context) {
    // the exception that occurred
    $context->getException();
    // manage the log channels
    $context->getChannels();
    $context->setChannels(['slack']);
    // manage the log reporting level
    $context->getLevel();
    $context->setLevel(Settings::REPORTING_LEVEL_WARNING);
    $context->debug() … $context->emergency();
    // manage the default return value
    $context->getDefault();
    $context->setDefault('default');
    // manage the request's trace identifiers
    $context->getTraceIdentifiers();
    $context->setTraceIdentifiers([$traceId]);
    // manage the known issues
    $context->hasKnown();
    $context->getKnown();
    $context->setKnown(['https://company.atlassian.net/browse/ISSUE-1234']);
    // manage the report setting
    $context->getReport();
    $context->setReport(true/false);
    $context->dontReport();
    // manage the rethrow setting
    $context->getRethrow();
    $context->setRethrow(true/false);
    $context->setRethrow($exception); // a new exception to rethrow
    $context->setRethrow($callable);  // a closure that decides which exception to rethrow
    $context->dontRethrow();
    // turn both report and rethrow off
    $context->suppress();
};
```



### Suppressing Exceptions On The Fly

You can suppress an exception by calling `$context->suppress()` inside your callback.

This will also happen if your callback sets `$context->setReport(false)` *and* `$context->setRethrow(false)`.

> ***Tip:*** Callbacks are run in the order they were specified. Subsequent callbacks won't be called when the exception is suppressed.

``` php
use CodeDistortion\ClarityControl\Context;
use Illuminate\Http\Request;

$callback = function (Context $context, Request $request) {

    // suppress the exception when the user-agent is 'test-agent'
    if ($request->userAgent == 'test-agent') {
        $context->suppress()
        // or
        $context->setReport(false)->setRethrow(false);
    }
};

Control::prepare($callable)->callback($callback)->execute();
```



### Global Callbacks

You can tell Control to run a "global" callback whenever it catches an exception. You can add as many as you need.

These callbacks are run *before* the regular (non-global) callbacks.

``` php
Control::globalCallback($callable);
```

A good place to set one up would be in a service provider. See Laravel's documentation for [more information about service providers](https://laravel.com/docs/10.x/providers#main-content).

``` php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use CodeDistortion\ClarityControl\Control;

class MyServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $callback = function () { … }; // do something
        Control::globalCallback($callback); // <<<
    }
}
```



## Finally

You can specify a callable to run after the execution of the main `$callable` by passing a third parameter to `Control::run()` or `Control::prepare()`.

``` php
$finally = fn() => …; // do something

Control::run($callable, 'default', $finally);
// or
Control::prepare($callable, 'default', $finally)->execute();
```

You can also call `->finally()` after calling `Control::prepare()`

``` php
Control::prepare($callable)->finally($finally)->execute();
```

> ***Tip:*** [Laravel's *dependency injection*](https://laravel.com/docs/10.x/container#when-to-use-the-container) system is used to run your callable. Just type-hint your parameters and they'll be resolved for you.



## Advanced Catching

You can choose to do different things when different exceptions are caught.

To do this, configure a `CodeDistortion\ClarityControl\CatchType` object, and pass *that* to `$clarity->catch()` instead of 
passing the exception class *string*.

`CatchType` objects can be customised with the same settings as the `Control` object. They're all optional, and can be called in any order.

``` php
use CodeDistortion\ClarityControl\CatchType;
use CodeDistortion\ClarityControl\Settings;

$catchType1 = CatchType::channel('slack')
    ->level(Settings::REPORTING_LEVEL_WARNING)
    ->debug() … ->emergency()
    ->default('default')
    ->catch(DivisionByZeroError::class)
    ->match('Undefined variable $a')
    ->matchRegex('/^Undefined variable /')
    ->known('https://company.atlassian.net/browse/ISSUE-1234')
    ->report() or ->dontReport()
    ->rethrow() or ->dontRethrow() or ->rethrow($callable)
    ->suppress()
    ->callback($callable)
    ->finally($callable);
$catchType2 = …;

Control::prepare($callable)
    ->catch($catchType1)
    ->catch($catchType2)
    ->execute();
```

CatchTypes are checked in the order they were specified. The first one that matches the exception is used.



## Retrieving Exceptions

If you'd like to obtain the exception, you can call `getException()` and pass a variable by reference. When an exception occurs, it will contain the exception afterwards. Otherwise it's set to *null*.

The exception will be set, even if the exception was [suppressed](#suppressing-exceptions).

``` php
Control::prepare($callable)->getException($e)->execute();

dump($e); // will contain the exception, or null
```



<br />



## Testing This Package

- Clone this package: `git clone https://github.com/code-distortion/clarity-control.git .`
- Run `composer install` to install dependencies
- Run the tests: `composer test`



## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.



### SemVer

This library uses [SemVer 2.0.0](https://semver.org/) versioning. This means that changes to `X` indicate a breaking change: `0.0.X`, `0.X.y`, `X.y.z`. When this library changes to version 1.0.0, 2.0.0 and so forth, it doesn't indicate that it's necessarily a notable release, it simply indicates that the changes were breaking.



## Treeware

This package is [Treeware](https://treeware.earth). If you use it in production, then we ask that you [**buy the world a tree**](https://plant.treeware.earth/code-distortion/clarity-control) to thank us for our work. By contributing to the Treeware forest you’ll be creating employment for local families and restoring wildlife habitats.



## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.



### Code of Conduct

Please see [CODE_OF_CONDUCT](.github/CODE_OF_CONDUCT.md) for details.



### Security

If you discover any security related issues, please email tim@code-distortion.net instead of using the issue tracker.



## Credits

- [Tim Chandler](https://github.com/code-distortion)



## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

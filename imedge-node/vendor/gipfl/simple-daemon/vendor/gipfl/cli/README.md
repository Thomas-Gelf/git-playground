gipfl\\Cli
==========

CLI-related library. Currently provides Process- and Screen-related helper
classes.

Usage
-----

Just some usage examples. Please check our source code, as all useful public
methods should carry a nice documentation.

### Change the your process title
 
This is what you'll see in your process list:

```php
use gipfl\Cli\Process;

Process::setTitle('mydaemon: doing something');
```

### Restart the process, replacing itself

The new process will run in the same process space, after being started with
the very same binary, given the same parameters and the the same environment:

```php
Process::restart();
```

### Do more with your CLI screen

```php
use gipfl\Cli\Screen;

$screen = Screen::instance();
echo $screen->center($screen->underline('Hello world'));
```

### Use some color

```php
echo Screen::instance()->colorize('OK', 'green') . ": everything is fine!\n"'
```

Changes
-------

### v0.7.0
* dependencies: support react/promise v2 and v3

### v0.3.0

* Process: fix restart when started from a shell
* Introduce Spinner

### v0.2.0

* Process::restart() and related helper methods
* Screen helpers

### v0.1.0

* First release

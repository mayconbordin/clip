clip
====

A simple command line interface for PHP.


## Usage

Start the library:

```php
require '../src/clip.php';
$clip = new Clip();
```

Create your commands:

```php
$clip->register('createdb -d <database> [-h <host>]', function($args) {
    if (Clip::bool("Create database %s", $args->database)) {
        Clip::success("done");
    } else {
        Clip::error("skipped");
    }
});
```

And start the application:

```php
$clip->run($argv);
```

## Syntax

We implement part of the [docopt](http://docopt.org/) language definition. Support for pipes and ellipsis, for the mutually exclusive and repeating elements, is missing for now.

Examples:

```bash
createdb -d <database> [-h <host>] [--debug]
db <command> -d <database> [--host=<host>]
```

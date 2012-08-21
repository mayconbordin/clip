clip
====

A simple command line interface for PHP.


### Usage ###

Start the library:

```php
require '../src/clip.php';
$clip = new Clip();
```

And create your commands:

```php
$clip->register('createdb -d DATABASE [-h HOST]', function($args) {
    if (Clip::bool("Create database %s", $args->get("d"))) {
        Clip::green("done");
    } else {
        Clip::red("skipped");
    }
});
```

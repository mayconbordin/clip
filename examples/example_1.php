<?php

require '../src/clip.php';

$clip = new Clip();

$clip->register('remote <command> [<name>] [--exclude=PATTERNS] [-f FILE] [--no-prompt] [-s] [<anothername>] drop', function($args) {
    print_r($args);
});

$clip->register('createdb -d DATABASE [-h HOST]', function($args) {
    if (Clip::bool("Create database %s", $args->d)) {
        Clip::green("done");
    } else {
        Clip::red("skipped");
    }
});

$clip->register('db <command> -d DATABASE', function($args) {
    print_r($args);
});

$clip->run($argv, $argc);


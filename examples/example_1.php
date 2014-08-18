<?php

require '../src/clip.php';

$clip = new Clip();

$clip->register('remote <command> [<name>] [--exclude=<patterns>] [-f <file>] [--no-prompt] [-s] [<anothername>] drop', function($args) {
    print_r($args);
});

$clip->register('createdb -d <database> [-h <host>]', function($args) {
    if (Clip::bool("Create database %s", $args->database)) {
        Clip::success("done");
    } else {
        Clip::error("skipped");
    }
    
    print_r($args);
});

$clip->register('db <command> -d <database>', function($args) {
    print_r($args);
});

$clip->run($argv, $argc);


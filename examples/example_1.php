<?php

require '../src/clip.php';

$clip = new Clip();

$clip->register('remote <command> [<name>] [--exclude=PATTERNS] [-f FILE] [--no-prompt] [-s] [<anothername>] drop', function($args) {
    print_r($args);
});

$clip->register('createdb -d DATABASE [-h HOST]', function($args) {
    Clip::println("hell %s", "yeah");
    Clip::dark_gray("hell %s", "yeah");
    
    if (Clip::bool("Want to do this, dude?")) {
        Clip::green("done");
    } else {
        Clip::red("skipped");
    }
});

$clip->register('db dropall -d DATABASE', function($args) {
    echo "Drop database " . $args->get("d") . "\n";
});

$clip->run($argv, $argc);


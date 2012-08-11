<?php

function isEqualOrDash($c) {
    return ($c == '-') || ($c == '=');
}

echo preg_match('/[\s\-]/', '-');
echo "\n";

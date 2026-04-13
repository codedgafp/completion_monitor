<?php

use core_cache\store;

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'completion_percentage' => [
        'mode' => store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
    ],
];

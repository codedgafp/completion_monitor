<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_completion_monitor_send_message' => [
        'classname'    => 'block_completion_monitor\external\send_message',
        'methodname'   => 'execute',
        'description'  => 'Envoie un message personnel Moodle à une liste d\'utilisateurs sélectionnés.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'report/progress:view',
    ],
];
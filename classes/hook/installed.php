<?php

namespace block_completion_monitor\hook;

/**
 * Allows plugins or features to perform actions after installing
 *
 * @package   block_completion_monitor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\tags('install')]
#[\core\attribute\label('Allows plugins or features to perform actions after installing')]
final class installed {}

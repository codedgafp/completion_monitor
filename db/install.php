<?php

use block_completion_monitor\hook\installed;

/**
 * Install script for block_completion_monitor.
 *
 * @package   block_completion_monitor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_block_completion_monitor_install() {
    \core\di::get(\core\hook\manager::class)
        ->dispatch(new installed());
    return true;
}

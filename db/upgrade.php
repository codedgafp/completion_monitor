<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Database upgrades for the completion_monitor block.
 *
 * @package   block_completion_monitor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

/**
 * Upgrade the block_completion_monitor database.
 *
 * @param int $oldversion The version number of the plugin that was installed.
 * @return boolean
 */
function xmldb_block_completion_monitor_upgrade($oldversion) {
    apply_completion_monitor_upgrade_scripts($oldversion);
    return true;
}

/**
 * Retrieves scripts from files in ./updates/<version>.php and applies those
 * that have not yet been run. Each script is savepointed individually so that
 * a failure mid-upgrade does not replay already-applied scripts on the next run.
 *
 * @param int $oldversion
 */
function apply_completion_monitor_upgrade_scripts($oldversion) {
    $scripts = glob(__DIR__ . '/updates/*.php');
    sort($scripts);

    foreach ($scripts as $script) {
        $version = (int) basename($script, '.php');
        if ($oldversion < $version) {
            include($script);
            upgrade_block_savepoint(true, $version, 'completion_monitor');
        }
    }
}

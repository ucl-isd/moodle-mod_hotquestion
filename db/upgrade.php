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
 * Upgrade code for install
 *
 * @package   mod_hotquestion
 * @copyright 2012 Zhang Anzhen
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade this hotquestion instance - this function could be skipped but it will be needed later.
 * @param int $oldversion The old version of the hotquestion module
 * @return bool
 */
function xmldb_hotquestion_upgrade($oldversion=0) {
    global $CFG, $DB;
    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.
    $result = true;

    // 1.9.0 Upgrade line.
    if ($result && $oldversion < 2007040100) {

        // Define field course to be added to hotquestion.
        $table = new XMLDBTable('hotquestion');
        $field = new XMLDBField('course');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'id');
        // Launch add field course.
        $result = $result && $table->add_field($field);

        // Define field intro to be added to hotquestion.
        $field = new xmldb_field('intro');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null, null, 'name');

        // Launch add field intro.
        $result = $result && $table->add_field($field);

        // Define field introformat to be added to hotquestion.
        $field = new xmldb_field('introformat');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'intro');
        // Launch add field introformat.
        $result = $result && $table->add_field($field);
    }

    if ($result && $oldversion < 2007040101) {

        // Define field timecreated to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('timecreated');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'introformat');

        // Launch add field timecreated.
        $result = $result && $table->add_field($field);

        $field = new xmldb_field('timemodified');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'timecreated');
        // Launch add field timemodified.
        $result = $result && $table->add_field($table, $field);

        // Define index course (not unique) to be added to hotquestion.
        $result = $result && $table->add_index('course', XMLDB_INDEX_NOTUNIQUE, array('course'));
    }

    if ($result && $oldversion < 2007040200) {
        // Add some actions to get them properly displayed in the logs.
        $rec = new stdClass;
        $rec->module = 'hotquestion';
        $rec->action = 'add';
        $rec->mtable = 'hotquestion';
        $rec->filed  = 'name';
        // Insert the add action in log_display.
        $result = $DB->insert_record('log_display', $rec);
        // Now the update action.
        $rec->action = 'update';
        $result = $DB->insert_record('log_display', $rec);
        // Now the view action.
        $rec->action = 'view';
        $result = $DB->insert_record('log_display', $rec);
    }

    // 3.1.0 Upgrade start here.
    if ($oldversion < 2016100300) {

        // Define field submitdirections to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('submitdirections', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL,
            null, 'Submit your question here', 'introformat');

        // Conditionally launch add field submitdirections.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field timeopen to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('timeopen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field timeopen.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field timeclose to be added to hotquestion.
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('timeclose', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeopen');

        // Conditionally launch add field timeclose.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hotquestion savepoint reached.
        upgrade_mod_savepoint(true, 2016100300, 'hotquestion');
    }
    return $result;
}

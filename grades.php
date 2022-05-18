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
 * Prints a particular instance of hotquestion
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @copyright 2016 onwards AL Rachels (drachels@drachels.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later$this->hotquestion->instance->questionlabel
 */

use \mod_hotquestion\event\course_module_viewed;

require_once("../../config.php");
require_once("lib.php");
require_once("locallib.php");

$id = required_param('id', PARAM_INT); // Course_module ID.
$group = optional_param('group', 0, PARAM_INT);  // Choose the current group.

if (! $cm = get_coursemodule_from_id('hotquestion', $id)) {
    throw new moodle_exception(get_string('incorrectmodule', 'hotquestion'));
}

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);


// Construct hotquestion instance.
$hq = new mod_hotquestion($id);

// Confirm login.
require_login($hq->course, true, $hq->cm);

$context = context_module::instance($hq->cm->id);
$baseurl = new moodle_url('/mod/hotquestion/grades.php', ['id' => $hq->cm->id]);
if ($group > 0) {
    $baseurl->param('group', $group);
}
$PAGE->set_url($baseurl);
$PAGE->set_title($hq->instance->name);
$PAGE->set_heading($hq->course->shortname);
$PAGE->set_context($context);
$PAGE->set_cm($hq->cm);
$PAGE->add_body_class('hotquestion');

if ($entriesmanager = has_capability('mod/hotquestion:manageentries', $context)) {
    $userid = 0;
} else {
    $userid = $USER->id;
}
// 20220515 Commented out next line so that an individual can see only their grade.
//$userid = 0;

// Process submited actions.

// Get local renderer.
$output = $PAGE->get_renderer('mod_hotquestion');
$output->init($hq);
$gradestable = new mod_hotquestion\output\viewgrades($hq, $group, $userid);

if ($gradestable->is_downloading()) {
    $gradestable->download();
}

// Start print page.
$hotquestionname = format_string($hq->instance->name, true, array('context' => $context));
echo $output->header();
echo $output->heading($hotquestionname);

$gradestable->display();
echo $output->footer();

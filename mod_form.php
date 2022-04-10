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
 * The main hotquestion configuration form.
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Standard base class for mod_hotquestion configuration form.
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_hotquestion_mod_form extends moodleform_mod {

    /**
     * Define the Hot Question mod_form used when editing a Hot Question activity.
     */
    public function definition() {

        global $COURSE, $CFG;
        $mform =& $this->_form;
        $hotquestionconfig = get_config('mod_hotquestion');

        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('hotquestionname', 'hotquestion'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Adding the standard "intro" fields based on Moodle version.
        if ($CFG->branch < 29) {
            $this->add_intro_editor(true, get_string('description'));
        } else {
            $this->standard_intro_elements(get_string('description', 'hotquestion'));
        }

        // Adding the rest of hotquestion settings, spreading them into this fieldset
        // or adding more fieldsets ('header' elements), if needed for better logic.

        // Add Entries header to form.
        $mform->addElement('header', 'entrieshdr', get_string('entries', 'hotquestion'));

        // Add submit instruction text field here.
        $mform->addElement('text', 'submitdirections', get_string('inputquestion', 'hotquestion'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('submitdirections', PARAM_TEXT);
        } else {
            $mform->setType('submitdirections', PARAM_CLEANHTML);
        }
        $mform->setDefault('submitdirections', $hotquestionconfig->submitinstructions);
        $mform->addHelpButton('submitdirections', 'inputquestion', 'hotquestion');
        $mform->addRule('submitdirections', null, 'required', null, 'client');
        $mform->addRule('submitdirections', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Add Questions label text field here.
        $mform->addElement('text', 'questionlabel', get_string('questionlabel', 'hotquestion'), array('size' => '20'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('questionlabel', PARAM_TEXT);
        } else {
            $mform->setType('questionlabel', PARAM_CLEANHTML);
        }
        $mform->setDefault('questionlabel', $hotquestionconfig->questionlabel);
        $mform->addHelpButton('questionlabel', 'inputquestionlabel', 'hotquestion');
        $mform->addRule('questionlabel', null, 'required', null, 'client');
        $mform->addRule('questionlabel', get_string('maximumchars', '', 20), 'maxlength', 20, 'client');

        // Add visibility setting for the teacher Priority column.
        $mform->addElement('selectyesno', 'teacherpriorityvisibility', get_string('teacherpriorityvisibility', 'hotquestion'));
        $mform->addHelpButton('teacherpriorityvisibility', 'teacherpriorityvisibility', 'hotquestion');
        $mform->setDefault('teacherpriorityvisibility', '1');

        // Add Priority label text field here.
        $mform->addElement('text',
                           'teacherprioritylabel',
                           get_string('teacherprioritylabel', 'hotquestion'),
                           array('size' => '20')
                           );
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('teacherprioritylabel', PARAM_TEXT);
        } else {
            $mform->setType('teacherprioritylabel', PARAM_CLEANHTML);
        }
        $mform->setDefault('teacherprioritylabel', $hotquestionconfig->teacherprioritylabel);
        $mform->addHelpButton('teacherprioritylabel', 'inputteacherprioritylabel', 'hotquestion');
        $mform->addRule('teacherprioritylabel', null, 'required', null, 'client');
        $mform->addRule('teacherprioritylabel', get_string('maximumchars', '', 20), 'maxlength', 20, 'client');

        // Add visibility setting for the Heat column.
        $mform->addElement('selectyesno', 'heatvisibility', get_string('heatvisibility', 'hotquestion'));
        $mform->addHelpButton('heatvisibility', 'heatvisibility', 'hotquestion');
        $mform->setDefault('heatvisibility', '1');

        // Add Heat label text field here.
        $mform->addElement('text', 'heatlabel', get_string('heatlabel', 'hotquestion'), array('size' => '20'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('heatlabel', PARAM_TEXT);
        } else {
            $mform->setType('heatlabel', PARAM_CLEANHTML);
        }
        $mform->setDefault('heatlabel', $hotquestionconfig->heatlabel);
        $mform->addHelpButton('heatlabel', 'inputheatlabel', 'hotquestion');
        $mform->addRule('heatlabel', null, 'required', null, 'client');
        $mform->addRule('heatlabel', get_string('maximumchars', '', 20), 'maxlength', 20, 'client');

        // Add Heat limit select field here.
        // Add a dropdown slector for heatlimit. 05/25/2020.
        $tlimit = array();
        for ($i = 0; $i <= 10; $i++) {
            $tlimit[] = $i;
        }
        $mform->addElement('select', 'heatlimit', get_string('heatlimit', 'hotquestion'), $tlimit);
        $mform->addHelpButton('heatlimit', 'heatlimit', 'hotquestion');
        $mform->setDefault('heatlimit', $hotquestionconfig->heatlimit);

        // Adding 'anonymouspost' field.
        $mform->addElement('selectyesno', 'anonymouspost', get_string('allowanonymouspost', 'hotquestion'));
        $mform->addHelpButton('anonymouspost', 'allowanonymouspost', 'hotquestion');
        $mform->setDefault('anonymouspost', '1');

        // Adding 'authorhide' field.
        $mform->addElement('selectyesno', 'authorhide', get_string('allowauthorinfohide', 'hotquestion'));
        $mform->addHelpButton('authorhide', 'allowauthorinfohide', 'hotquestion');
        $mform->setDefault('authorhide', '0');

        // Add 'requireapproval' field.
        $mform->addElement('selectyesno', 'approval', get_string('requireapproval', 'hotquestion'));
        $mform->addHelpButton('approval', 'requireapproval', 'hotquestion');
        $mform->setDefault('approval', '0');

        // Add Approval label text field here.
        $mform->addElement('text', 'approvallabel', get_string('approvallabel', 'hotquestion'), array('size' => '20'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('approvallabel', PARAM_TEXT);
        } else {
            $mform->setType('approvallabel', PARAM_CLEANHTML);
        }
        $mform->setDefault('approvallabel', $hotquestionconfig->approvallabel);
        $mform->addHelpButton('approvallabel', 'inputapprovallabel', 'hotquestion');
        $mform->addRule('approvallabel', null, 'required', null, 'client');
        $mform->addRule('approvallabel', get_string('maximumchars', '', 20), 'maxlength', 20, 'client');

        // Add Remove label text field here.
        $mform->addElement('text', 'removelabel', get_string('removelabel', 'hotquestion'), array('size' => '20'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('removelabel', PARAM_TEXT);
        } else {
            $mform->setType('removelabel', PARAM_CLEANHTML);
        }
        $mform->setDefault('removelabel', $hotquestionconfig->removelabel);
        $mform->addHelpButton('removelabel', 'inputapprovallabel', 'hotquestion');
        $mform->addRule('removelabel', null, 'required', null, 'client');
        $mform->addRule('removelabel', get_string('maximumchars', '', 20), 'maxlength', 20, 'client');

        // 20220410 Allow comments.
        if ($hotquestionconfig->allowcomments) {
            $mform->addElement('selectyesno', 'comments', get_string('allowcomments', 'hotquestion'));
            $mform->addHelpButton('comments', 'allowcomments', 'hotquestion');
            $mform->setDefault('comments', 0);
        }

        // Availability.
        $mform->addElement('header', 'availabilityhdr', get_string('availability'));

        $mform->addElement('date_time_selector', 'timeopen',
                           get_string('hotquestionopentime', 'hotquestion'),
                           array('optional' => true, 'step' => 1));
        $mform->addElement('date_time_selector', 'timeclose',
                           get_string('hotquestionclosetime', 'hotquestion'),
                           array('optional' => true, 'step' => 1));

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();
        // Next line was missing. Added Sep 30, 2016.
        $this->apply_admin_defaults();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();

    }
}

/**
 * Standard base class for hotquestion_form for typing and submitting a question.
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hotquestion_form extends moodleform {

    /**
     * Define the Hot Question input form called from view.php.
     */
    public function definition() {
        global $CFG, $DB;

        list($allowanonymous, $cm) = $this->_customdata;

        $temp = $DB->get_record('hotquestion', array('id' => $cm->instance));

        $mform =& $this->_form;

        // 20210218 Changed using a text editor instead of textarea.
        // $mform->addElement('editor', 'text_editor', $temp->submitdirections, 'wrap="virtual" rows="5"');
        // Changed to format text which allows filters such as Gerico, etc. to work.
        $mform->addElement('editor'
                           , 'text_editor'
                           , format_text($temp->submitdirections
                           , $format = FORMAT_MOODLE
                           , $options = null
                           , $courseiddonotuse = null)
                           , 'wrap="virtual" rows="5"');
        $mform->setType('text_editor', PARAM_RAW);

        $mform->addElement('hidden', 'id', $cm->id, 'id="hotquestion_courseid"');
        $mform->setType('id', PARAM_INT);

        $submitgroup = array();
        $submitgroup[] =& $mform->createElement('submit', 'submitbutton', get_string('postbutton', 'hotquestion'));
        if ($allowanonymous) {
            $submitgroup[] =& $mform->createElement('checkbox', 'anonymous', '', get_string('displayasanonymous', 'hotquestion'));
            $mform->setType('anonymous', PARAM_BOOL);
        }
        $mform->addGroup($submitgroup);

    }
}

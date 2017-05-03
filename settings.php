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
 * Administration settings definitions for the quiz module.
 *
 * @package    mod_hotquestion
 * @copyright  2016 onwards AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */


defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/hotquestion/lib.php');
    require_once($CFG->dirroot.'/mod/hotquestion/locallib.php');

    // Recent activity setting.
    $name = new lang_string('showrecentactivity', 'mod_hotquestion');
    $description = new lang_string('showrecentactivityconfig', 'mod_hotquestion');
    $setting = new admin_setting_configcheckbox('mod_hotquestion/showrecentactivity',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    // Default submit instructions setting.
    $settings->add(new admin_setting_configtext(
        'mod_hotquestion/submitinstructions',
        new lang_string('inputquestion', 'hotquestion'),
        new lang_string('inputquestion_descr', 'hotquestion'),
        'Submit your question here:', PARAM_TEXT, 25)
    );
}

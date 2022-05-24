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

declare(strict_types=1);

namespace mod_hotquestion\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for the hotquestion activity.
 *
 * Class for defining mod_hotquestion's custom completion rules and fetching the completion statuses
 * of the custom completion rules for a given hotquestion instance and a user.
 *
 * @package mod_hotquestion
 * @copyright AL Rachels <drachels@drachels.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $userid = $this->userid;
        $hotquestionid = $this->cm->instance;


        if (!$hotquestion = $DB->get_record('hotquestion', ['id' => $hotquestionid])) {
            //throw new \moodle_exception('Unable to find hotquestion with id ' . $hotquestionid);
            throw new moodle_exception(get_string('incorrectmodule', 'hotquestion'));

        }

        $questioncountparams = ['userid' => $userid, 'hotquestionid' => $hotquestionid];
        $questionvoteparams = ['userid' => $userid, 'hotquestionid' => $hotquestionid];
/*
        $questioncountsql = "SELECT COUNT(*)
                           FROM {hotquestion_questions} hqq
                           LEFT JOIN {hotquestion_votes} hqv ON hqq.id = hqv.question
                           LEFT JOIN {hotquestion_grades} hqg ON hqq.id = hqg.hotquestion
                          WHERE hqq.userid = :userid
                            AND hqv.question = :hotquestionid";




    // Check if the user has used up all heat.
    if ($hotquestion->completionvote) {
        $sql = "SELECT COUNT(v.id)
                  FROM {hotquestion_votes} v
                  JOIN {hotquestion_questions} q ON q.id = v.question
                 WHERE q.hotquestion = :hotquestion AND v.voter = :userid ";
        $params = array('hotquestion' => $hotquestion->id, 'userid' => $userid);
        $value = $hotquestion->completionvote <= $DB->count_records_sql($sql, $params);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
*/

        $questionvotesql = "SELECT COUNT(hv.id)
                           FROM {hotquestion_votes} hv
                           JOIN {hotquestion_questions} hq ON hq.id = hv.question
                          WHERE hq.hotquestion = :hotquestionid
                            AND hv.voter = :userid";

        $questiongradesql = "SELECT *
                           FROM {hotquestion_grades} hqg
                           JOIN {hotquestion_questions} hqq ON hqg.hotquestion = hqq.id 
                          WHERE hqg.userid = :userid
                            AND hqq.id = :hotquestionid";

        if ($rule == 'completionpost') {
            $status = $hotquestion->completionpost <=
                $DB->count_records('hotquestion_questions', ['hotquestion' => $hotquestionid, 'userid' => $userid]);

        } else if ($rule == 'completionvote') {
            $status = $hotquestion->completionvote <=
                //$DB->count_records('hotquestion_votes', ['question' => $hotquestionid, 'voter' => $userid]);


                //$DB->get_field_sql($questioncountsql . ' AND hqv.voter <> $userid', $questioncountparams);
                $DB->get_field_sql($questionvotesql , $questionvoteparams);


        //} else if ($rule == 'completionpass') {
        //    $status = $hotquestion->completionpass <= 
        //        $DB->get_record('hotquestion_grades', ['hotquestion' => $hotquestionid, 'userid' => !$userid]);

                //$DB->get_field_sql($questioncountsql . ' AND hqg.userid = $userid AND hqg.rawrating >= hqqcompletionpass', $questioncountparams);
        }

        return $status ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completionpost',
            'completionvote',
            //'completionpass',
        ];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $completionpost = $this->cm->customdata['customcompletionrules']['completionpost'] ?? 0;
        $completionvote = $this->cm->customdata['customcompletionrules']['completionvote'] ?? 0;
        //$completionpass = $this->cm->customdata['customcompletionrules']['completionpass'] ?? 0;
        return [
            'completionpost' => get_string('completiondetail:post', 'hotquestion', $completionpost),
            'completionvote' => get_string('completiondetail:vote', 'hotquestion', $completionvote),
            //'completionpass' => get_string('completiondetail:pass', 'hotquestion', $completionpass),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionpost',
            'completionvote',
            'completionusegrade',
            //'completionpass',
        ];
    }
}

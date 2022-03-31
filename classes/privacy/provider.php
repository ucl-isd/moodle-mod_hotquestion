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
 * Data provider.
 *
 * @package    mod_hotquestion
 * @copyright  2016 AL Rachels
 * @author     AL Rachels < drachels@drachels.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hotquestion\privacy;

use context;
use context_helper;
use context_user;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\{writer, transform, helper, contextlist, approved_contextlist, approved_userlist, userlist};

defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine

/**
 * Data provider class for the Hot Question activity module.
 *
 * @package    mod_hotquestion
 * @copyright  2018 AL Rachels
 * @author     AL Rachels <drachels@drachels.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider,
                          \core_privacy\local\request\plugin\provider,
                          \core_privacy\local\request\core_userlist_provider {
    /**
     * Get a description of the data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection of metadata items.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
            'hotquestion_questions',
            [
                'id' => 'privacy:metadata:hotquestion_questions:id',
                'hotquestion' => 'privacy:metadata:hotquestion_questions:hotquestion',
                'content' => 'privacy:metadata:hotquestion_questions:content',
                'userid' => 'privacy:metadata:hotquestion_questions:userid',
                'time' => 'privacy:metadata:hotquestion_questions:time',
                'anonymous' => 'privacy:metadata:hotquestion_questions:anonymous',
                'approved' => 'privacy:metadata:hotquestion_questions:approved',
                'tpriority' => 'privacy:metadata:hotquestion_questions:tpriority',
            ],
            'privacy:metadata:hotquestion_questions'
        );
        $collection->add_database_table(
            'hotquestion_votes',
            [
                'id' => 'privacy:metadata:hotquestion_votes:id',
                'question' => 'privacy:metadata:hotquestion_votes:question',
                'voter' => 'privacy:metadata:hotquestion_votes:voter',
            ],
            'privacy:metadata:hotquestion_votes'
        );
        return $collection;
    }

    /** @var int */
    private static $modid;

    /**
     * Get the module id for the 'hotquestion' module.
     * @return false|mixed
     * @throws \dml_exception
     */
    private static function get_modid() {
        global $DB;
        if (self::$modid === null) {
            self::$modid = $DB->get_field('modules', 'id', ['name' => 'hotquestion']);
        }
        return self::$modid;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new contextlist();
        $modid = self::get_modid();
        if (!$modid) {
            return $contextlist; // Hot Question module not installed.
        }

        $params = [
            'modid' => $modid,
            'contextlevel' => CONTEXT_MODULE,
            'userid1' => $userid,
            'userid2' => $userid
        ];
        $sql = "
            SELECT c.id
              FROM {context} c
              JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
              JOIN {modules} m ON m.id = cm.module and m.name = :modid
              JOIN {hotquestion} h ON h.id = cm.instance
              JOIN {hotquestion_questions} hq ON hq.hotquestion = h.id
         LEFT JOIN {hotquestion_votes} hv ON hv.question = hq.id
             WHERE (hq.userid = :userid1) OR (hv.voter = :userid2)";

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!is_a($context, \context_module::class)) {
            return;
        }
        $modid = self::get_modid();
        if (!$modid) {
            return; // HotQuestion module not installed.
        }
        $params = [
            'modid' => $modid,
            'contextlevel' => CONTEXT_MODULE,
            'contextid'    => $context->id,
        ];

        // User-created entry in hotquestion_question.
        $sql = "
            SELECT hqq.userid
              FROM {hotquestion_questions} hqq
              JOIN {hotquestion} hq ON hq.id = hqq.hotquestion
              JOIN {course_modules} cm ON cm.instance = hq.id AND cm.module = :modid
              JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
             WHERE ctx.id = :contextid
        ";
        $userlist->add_from_sql('userid', $sql, $params);
    }
    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $user = $contextlist->get_user();
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        // Export the votes.
        $sql = "SELECT cm.id AS cmid,
                       hq.hotquestion AS hotquestion,
                       hv.question AS question,
                       hv.voter AS voter
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid
             LEFT JOIN {hotquestion_questions} hq ON hq.hotquestion = cm.instance
             LEFT JOIN {hotquestion_votes} hv ON hv.question = hq.id
                 WHERE (c.id {$contextsql} AND hv.voter = :userid2)
              ORDER BY cm.id";

        $params = [
            'userid1' => $user->id,
            'userid2' => $user->id,
        ] + $contextparams;
        $recordset = $DB->get_recordset_sql($sql, $params);

        static::_recordset_loop_and_export($recordset, 'hotquestion', [], function($carry, $record) {
            $carry[] = (object) [
                'question' => $record->question,
                'voter' => $record->voter,
            ];
            return $carry;
        }, function($hotquestionid, $data) use ($hotquestionidstocmids) {
            $context = context_module::instance($hotquestionidstocmids[$hotquestionid]);
            writer::with_context($context)->export_related_data([], 'votes', (object) ['votes' => $data]);
        });

        // Export the questions.
        $sql = "SELECT cm.id AS cmid,
                       hq.hotquestion as hotquestion,
                       hq.content as content,
                       hq.time as time,
                       hq.anonymous as anonymous,
                       hq.approved as approved,
                       hq.tpriority as tpriority
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid
            INNER JOIN {hotquestion_questions} hq ON hq.hotquestion = cm.instance
                 WHERE c.id {$contextsql}
                       AND hq.userid = :userid
              ORDER BY cm.id";

        $params = ['userid' => $user->id] + $contextparams;
        $recordset = $DB->get_recordset_sql($sql, $params);

        static::_recordset_loop_and_export($recordset, 'hotquestion', [], function($carry, $record) {
            $carry[] = (object) [
                'hotquestion' => $record->hotquestion,
                'content' => $record->content,
                'time' => transform::datetime($record->time),
                'anonymous' => transform::yesno($record->anonymous),
                'approved' => transform::yesno($record->approved),
                'tpriority' => $record->tpriority
            ];
            return $carry;
        }, function($hotquestionid, $data) use ($hotquestionidstocmids) {
            $context = context_module::instance($hotquestionidstocmids[$hotquestionid]);
            writer::with_context($context)->export_related_data([], 'questions', (object) ['questions' => $data]);
        });
    }

    /**
     * Export the supplied personal data for a single hotquestion activity, along with any generic data or area files.
     *
     * @param array $hotquestiondata the personal data to export for the hotquestion.
     * @param \context_module $context the context of the hotquestion.
     * @param array $subcontext the subcontext personal data to export for the hotquestion.
     * @param \stdClass $user the user record
     */
    public static function export_hotquestion_data_for_user(array $hotquestiondata, \context_module $context,
                                                            array $subcontext, \stdClass $user) {

        // Fetch the generic module data for the hotquestion.
        $contextdata = helper::get_context_data($context, $user);

        // Merge with hotquestion data and write it.
        $contextdata = (object)array_merge((array)$contextdata, $hotquestiondata);
        writer::with_context($context)->export_data($subcontext, $contextdata);

        // Write generic module intro files.
        helper::export_context_files($context, $user);
    }

    /**
     * Delete all data for all users in the specified context.
     * Called when retention period for the context has expired.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!$context) {
            return;
        }
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        if (!$cm = get_coursemodule_from_id('hotquestion', $context->instanceid)) {
            return;
        }
        $itemids = $DB->get_fieldset_select('hotquestion_questions', 'id', 'hotquestion = ?', [$cm->instance]);
        if ($itemids) {
            $DB->delete_records('hotquestion_questions', ['hotquestionid' => $cm->instance]);
            $DB->delete_records('hotquestion_votes', ['hotquestionid' => $cm->instance]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            if (!$cm = get_coursemodule_from_id('hotquestion', $context->instanceid)) {
                continue;
            }
            $itemids = $DB->get_fieldset_select('hotquestion_questions', 'id', 'hotquestion = ?', [$cm->instance]);
            if ($itemids) {
                list($isql, $params) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
                $params['userid'] = $userid;
                $DB->delete_records_select('hotquestion_votes', "id $isql AND userid = :userid", $params);
                $params = ['instanceid' => $cm->instance, 'userid' => $userid];
                $DB->delete_records_select('hotquestion_questions', 'hotquestion = :instanceid AND userid = :userid', $params);



                //$DB->delete_records('hotquestion_questions', ['hotquestion' => $instanceid, 'userid' => $userid]);
                //$DB->delete_records('hotquestion_votes', ['voter' => $userid]);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!is_a($context, \context_module::class)) {
            return;
        }
        $modid = self::get_modid();
        if (!$modid) {
            return; // HotQuestion module not installed.
        }
        if (!$cm = get_coursemodule_from_id('hotquestion', $context->instanceid)) {
            return;
        }

        // Prepare SQL to gather all completed IDs.
        $itemids = $DB->get_fieldset_select('hotquestion_questions', 'id', 'hotquestion = ?', [$cm->instance]);
        list($itsql, $itparams) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $userids = $userlist->get_userids();
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete user-created personal hotquestion items.
        $DB->delete_records_select(
            'hotquestion_questions',
            "userid $insql AND hotquestion = :hotquestionid",
            array_merge($inparams, ['hotquestionid' => $cm->instance])
        );

        // Delete comments made by a teacher about a particular item for a student.
        $DB->delete_records_select(
            'hotquestion_votes',
            "userid $insql AND itemid $itsql",
            array_merge($inparams, $itparams)
        );
    }

}

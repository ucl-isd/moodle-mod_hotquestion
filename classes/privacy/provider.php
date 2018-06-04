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
defined('MOODLE_INTERNAL') || die();

use context;
use context_helper;
use context_user;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\writer;

/**
 * Data provider class for the Hot Question activity module.
 *
 * @package    mod_hotquestion
 * @copyright  2018 AL Rachels
 * @author     AL Rachels <drachels@drachels.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    // This trait must be included.
    use \core_privacy\local\legacy_polyfill;

    /**
     * Return metadata.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection of metadata items.
     */
    public static function _get_metadata(collection $collection) {

        $collection->add_database_table('hotquestion_questions', [
            'id' => 'privacy:metadata:hotquestion_questions:id',
            'hotquestion' => 'privacy:metadata:hotquestion_questions:hotquestion',
            'content' => 'privacy:metadata:hotquestion_questions:content',
            'userid' => 'privacy:metadata:hotquestion_questions:userid',
            'time' => 'privacy:metadata:hotquestion_questions:time',
            'anonymous' => 'privacy:metadata:hotquestion_questions:anonymous',
            'approved' => 'privacy:metadata:hotquestion_questions:approved',
            'tpriority' => 'privacy:metadata:hotquestion_questions:tpriority',
        ], 'privacy:metadata:hotquestion_questions');

        $collection->add_database_table('hotquestion_votes', [
            'id' => 'privacy:metadata:hotquestion_votes:id',
            'question' => 'privacy:metadata:hotquestion_votes:question',
            'voter' => 'privacy:metadata:hotquestion_votes:voter',
        ], 'privacy:metadata:hotquestion_votes');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function _get_contexts_for_userid($userid) {
        $contextlist = new \core_privacy\local\request\contextlist();

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module and m.name = :modulename
                  JOIN {hotquestion} h ON h.id = cm.instance
                  JOIN {hotquestion_questions} hq ON hq.hotquestion = h.id
             LEFT JOIN {hotquestion_votes} hv ON hv.question = hq.id
                 WHERE (hq.userid = :userid1) OR (hv.voter = :userid2)";

        $params = [
            'modulename' => 'hotquestion',
            'contextlevel' => CONTEXT_MODULE,
            'userid1' => $userid,
            'userid2' => $userid
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function _export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        $userid = $user->id;
        $cmids = array_reduce($contextlist->get_contexts(), function($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[] = $context->instanceid;
            }
            return $carry;
        }, []);
        if (empty($cmids)) {
            return;
        }

        // If the context export was requested, then let's at least describe the hotquestion.
        foreach ($cmids as $cmid) {
            $context = context_module::instance($cmid);
            $contextdata = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);
            writer::with_context($context)->export_data([], $contextdata);
        }
        // Find the hotquestion IDs.
        $hotquestionidstocmids = static::_get_hotquestion_ids_to_cmids_from_cmids($cmids);

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
     * @param \stdClass $user the user record
     */
    public static function _export_hotquestion_data_for_user(array $hotquestiondata, \context_module $context,
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
    public static function _delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        if ($cm = get_coursemodule_from_id('hotquestion', $context->instanceid)) {
            $DB->delete_records('hotquestion_questions', ['hotquestionid' => $cm->instance]);
            $DB->delete_records('hotquestion_votes', ['hotquestionid' => $cm->instance]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function _delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);
            $DB->delete_records('hotquestion_questions', ['hotquestion' => $instanceid, 'userid' => $userid]);
            $DB->delete_records('hotquestion_votes', ['voter' => $userid]);
        }
    }

    /**
     * Return a dict of hotquestion IDs mapped to their course module ID.
     *
     * @param array $cmids The course module IDs.
     * @return array In the form of [$hotquestionid => $cmid].
     */
    protected static function _get_hotquestion_ids_to_cmids_from_cmids(array $cmids) {
        global $DB;
        list($insql, $inparams) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $sql = "
            SELECT h.id, cm.id AS cmid
              FROM {hotquestion} h
              JOIN {modules} m
                ON m.name = :hotquestion
              JOIN {course_modules} cm
                ON cm.instance = h.id
               AND cm.module = m.id
             WHERE cm.id $insql";
        $params = array_merge($inparams, ['hotquestion' => 'hotquestion']);
        return $DB->get_records_sql_menu($sql, $params);
    }
    /**
     * Loop and export from a recordset.
     *
     * @param moodle_recordset $recordset The recordset.
     * @param string $splitkey The record key to determine when to export.
     * @param mixed $initial The initial data to reduce from.
     * @param callable $reducer The function to return the dataset, receives current dataset, and the current record.
     * @param callable $export The function to export the dataset, receives the last value from $splitkey and the dataset.
     * @return void
     */
    protected static function _recordset_loop_and_export(\moodle_recordset $recordset, $splitkey, $initial,
            callable $reducer, callable $export) {

        $data = $initial;
        $lastid = null;

        foreach ($recordset as $record) {
            if ($lastid && $record->{$splitkey} != $lastid) {
                $export($lastid, $data);
                $data = $initial;
            }
            $data = $reducer($data, $record);
            $lastid = $record->{$splitkey};
        }
        $recordset->close();

        if (!empty($lastid)) {
            $export($lastid, $data);
        }
    }
}
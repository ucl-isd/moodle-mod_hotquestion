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
 * Internal library of functions for module hotquestion.
 *
 * All the hotquestion specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \mod_hotquestion\event\remove_vote;
use \mod_hotquestion\event\update_vote;
use \mod_hotquestion\event\add_question;
use \mod_hotquestion\event\add_round;
use \mod_hotquestion\event\remove_question;
use \mod_hotquestion\event\remove_round;
use \mod_hotquestion\event\download_questions;

defined('MOODLE_INTERNAL') || die();
define('HOTQUESTION_EVENT_TYPE_OPEN', 'open');
define('HOTQUESTION_EVENT_TYPE_CLOSE', 'close');
/**
 * Standard base class for mod_hotquestion.
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_hotquestion {

    /** @var int callback arg - the instance of the current hotquestion activity */
    public $instance;
    /** @var int callback arg - the instance of the current hotquestion module */
    public $cm;
    /** @var int callback arg - the instance of the current hotquestion course */
    public $course;

    /** @var int callback arg - the id of current round of questions */
    protected $currentround;
    /** @var int callback arg - the id of previous round of questions */
    protected $prevround;
    /** @var int callback arg - the id of next round of questions */
    protected $nextround;

    /**
     * Constructor for the base hotquestion class.
     *
     * Note: For $coursemodule you can supply a stdclass if you like, but it
     * will be more efficient to supply a cm_info object.
     *
     * @param mixed $cmid
     * @param mixed $roundid
     */
    public function __construct($cmid, $roundid = -1) {
        global $DB;
        $this->cm        = get_coursemodule_from_id('hotquestion', $cmid, 0, false, MUST_EXIST);
        $this->course    = $DB->get_record('course', array('id' => $this->cm->course), '*', MUST_EXIST);
        $this->instance  = $DB->get_record('hotquestion', array('id' => $this->cm->instance), '*', MUST_EXIST);
        $this->set_currentround($roundid);
    }

    /**
     * Return whether the user has voted on specified question.
     *
     * Called from function vote_on($question).
     * @param int $question question id
     * @param int $user user id. -1 means current user
     * @return boolean
     */
    public function has_voted($question, $user = -1) {
        global $USER, $DB;
        if ($user == -1) {
            $user = $USER->id;
        }
        return $DB->record_exists('hotquestion_votes', array('question' => $question, 'voter' => $user));
    }

    /**
     * Add a new question to current round.
     *
     * @param object $fromform From ask form.
     */
    public function add_new_question($fromform) {
        global $USER, $CFG, $DB;
        $data = new StdClass();
        $data->hotquestion = $this->instance->id;
        $data->content = trim($fromform->question);
        $data->userid = $USER->id;
        $data->time = time();
        $data->tpriority = 0;
        // Check if approval is required for this HotQuestion activity.
        if (!($this->instance->approval)) {
            // If approval is NOT required, then approve the question so everyone can see it.
            $data->approved = 1;
        } else {
            // If approval is required, then mark as not approved so only teachers can see it.
            $data->approved = 0;
        }
        $context = context_module::instance($this->cm->id);
        if (isset($fromform->anonymous) && $fromform->anonymous && $this->instance->anonymouspost) {
            $data->anonymous = $fromform->anonymous;
            // Assume this user is guest.
            $data->userid = $CFG->siteguest;
        }
        if (!empty($data->content)) {
            $DB->insert_record('hotquestion_questions', $data);
            if ($CFG->version > 2014051200) { // If newer than Moodle 2.7+ use new event logging.
                $params = array(
                    'objectid' => $this->cm->id,
                    'context' => $context,
                );
                $event = add_question::create($params);
                $event->trigger();
            } else {
                add_to_log($this->course->id, "hotquestion", "add question"
                    , "view.php?id={$this->cm->id}", $data->content, $this->cm->id);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Vote on question.
     *
     * Called from view.php.
     * @param int $question The question id.
     */
    public function vote_on($question) {
        global $CFG, $DB, $USER;
        $votes = new StdClass();
        $context = context_module::instance($this->cm->id);
        $question = $DB->get_record('hotquestion_questions', array('id' => $question));
        if ($question && $this->can_vote_on($question)) {

            // Trigger and log a vote event.
            if ($CFG->version > 2014051200) { // If newer than Moodle 2.7+ use new event logging.
                $params = array(
                    'objectid' => $this->cm->id,
                    'context' => $context,
                );
                $event = update_vote::create($params);
                $event->trigger();
            } else {
                add_to_log($this->course->id, 'hotquestion', 'update vote'
                    , "view.php?id={$this->cm->id}", $question->id, $this->cm->id);
            }

            if (!$this->has_voted($question->id)) {
                $votes->question = $question->id;
                $votes->voter = $USER->id;
                $DB->insert_record('hotquestion_votes', $votes);
            } else {
                $DB->delete_records('hotquestion_votes', array('question' => $question->id, 'voter' => $USER->id));
            }
        }
    }

    /**
     * Remove vote on question.
     *
     * Called from view.php.
     * @param int $question The question id.
     */
    public function remove_vote($question) {
        global $CFG, $DB, $USER;
        $votes = new StdClass();
        $context = context_module::instance($this->cm->id);
        $question = $DB->get_record('hotquestion_questions', array('id' => $question));
        if ($question && $this->can_vote_on($question)) {

            // Trigger and log a remove_vote event.
            if ($CFG->version > 2014051200) { // If newer than Moodle 2.7+ use new event logging.
                $params = array(
                    'objectid' => $this->cm->id,
                    'context' => $context,
                );
                $event = remove_vote::create($params);
                $event->trigger();
            } else {
                add_to_log($this->course->id, 'hotquestion', 'remove vote'
                    , "view.php?id={$this->cm->id}", $question->id, $this->cm->id);
            }

            if (!$this->has_voted($question->id)) {
                $votes->question = $question->id;
                $votes->voter = $USER->id;
                $DB->insert_record('hotquestion_votes', $votes);
            } else {
                $DB->delete_records('hotquestion_votes', array('question' => $question->id, 'voter' => $USER->id));
            }
        }
    }


    /**
     * Whether can vote on the question.
     *
     * @param int $question
     * @param stdClass $user null means current user
     */
    public function can_vote_on($question, $user = null) {
        global $USER, $DB;

        if (is_int($question)) {
            $question = $DB->get_record('hotquestion_questions', array('id' => $question));
        }
        if (empty($user)) {
            $user = $USER;
        }

        // Is this question in last round?
        $rounds = $DB->get_records('hotquestion_rounds', array('hotquestion' => $this->instance->id), 'id DESC', '*', 0, 1);
        $lastround = reset($rounds);
        $inlastround = $question->time >= $lastround->starttime;

        return $question->userid != $user->id && $inlastround;
    }

    /**
     * Calculates number of remain votes (heat) available to this user in the current round.
     *
     * Added 20200529.
     * @param int $hq
     * @param int $user
     */
    public function heat_tally($hq, $user = null) {
        global $USER, $CFG, $DB;

        $params = array($hq->currentround->id, $hq->currentround->hotquestion, $USER->id);

        $sql = "SELECT hqq.id AS questionid,
                       COUNT(hqv.voter) AS heat,
                       hqq.hotquestion AS hotquestionid,
                       hqr.id AS round,
                       hqq.content AS content,
                       hqq.userid AS userid,
                       hqv.voter AS voter,
                       hqq.time AS time,
                       hqq.anonymous AS anonymous,
                       hqq.tpriority AS tpriority,
                       hqq.approved AS approved
                  FROM {hotquestion_rounds} hqr
             LEFT JOIN {hotquestion_questions} hqq ON hqr.hotquestion=hqq.hotquestion
             LEFT JOIN {hotquestion_votes} hqv ON hqv.question=hqq.id
                  JOIN {user} u ON u.id = hqq.userid
                 WHERE hqr.id = ?
                   AND hqr.hotquestion = ?
                   AND hqq.time > hqr.starttime
                   AND hqr.endtime = 0
                   AND hqv.voter = ?
              GROUP BY hqq.id, hqr.id, hqv.voter
              ORDER BY hqq.hotquestion ASC, tpriority DESC, heat DESC";

        $tally = count($DB->get_records_sql($sql, $params));

        $results = ($hq->instance->heatlimit - $tally);
        return $results;
    }

    /**
     * Open a new round and close the old one.
     */
    public function add_new_round() {
        global $USER, $CFG, $DB;

        // Close the latest round.
        $rounds = $DB->get_records('hotquestion_rounds', array('hotquestion' => $this->instance->id), 'id DESC', '*', 0, 1);
        $old = array_pop($rounds);
        $old->endtime = time();
        $DB->update_record('hotquestion_rounds', $old);

        // Open a new round.
        $new = new StdClass();
        $new->hotquestion = $this->instance->id;
        $new->starttime = time();
        $new->endtime = 0;
        $context = context_module::instance($this->cm->id);
        $rid = $DB->insert_record('hotquestion_rounds', $new);

        if ($CFG->version > 2014051200) { // If newer than Moodle 2.7+ use new event logging.
            $params = array(
                'objectid' => $this->cm->id,
                'context' => $context,
            );
            $event = add_round::create($params);
            $event->trigger();
        } else {
            add_to_log($this->course->id, 'hotquestion', 'add round',
                "view.php?id={$this->cm->id}&round=$rid", $rid, $this->cm->id);
        }
    }

    /**
     * Set current round to show.
     * @param int $roundid
     */
    public function set_currentround($roundid = -1) {
        global $DB;

        $rounds = $DB->get_records('hotquestion_rounds', array('hotquestion' => $this->instance->id), 'id ASC');
        if (empty($rounds)) {
            // Create the first round.
            $round = new StdClass();
            $round->starttime = time();
            $round->endtime = 0;
            $round->hotquestion = $this->instance->id;
            $round->id = $DB->insert_record('hotquestion_rounds', $round);
            $rounds[] = $round;
        }

        if ($roundid != -1 && array_key_exists($roundid, $rounds)) {
            $this->currentround = $rounds[$roundid];

            $ids = array_keys($rounds);
            // Search previous round.
            $currentkey = array_search($roundid, $ids);
            if (array_key_exists($currentkey - 1, $ids)) {
                $this->prevround = $rounds[$ids[$currentkey - 1]];
            } else {
                $this->prevround = null;
            }
            // Search next round.
            if (array_key_exists($currentkey + 1, $ids)) {
                $this->nextround = $rounds[$ids[$currentkey + 1]];
            } else {
                $this->nextround = null;
            }
        } else {
            // Use the last round.
            $this->currentround = array_pop($rounds);
            $this->prevround = array_pop($rounds);
            $this->nextround = null;
        }
        return $roundid;
    }

    /**
     * Return current round.
     *
     * @return object
     */
    public function get_currentround() {
        return $this->currentround;
    }

    /**
     * Return previous round.
     *
     * @return object
     */
    public function get_prevround() {
        return $this->prevround;
    }

    /**
     * Return next round.
     *
     * @return object
     */
    public function get_nextround() {
        return $this->nextround;
    }

    /**
     * Return questions according to $currentround.
     *
     * Sort order is priority descending, votecount descending,
     * and time descending from most recent to oldest.
     * @return all questions with vote count in current round.
     */
    public function get_questions() {
        global $DB;
        if ($this->currentround->endtime == 0) {
            $this->currentround->endtime = 0xFFFFFFFF;  // Hack.
        }
        $params = array($this->instance->id, $this->currentround->starttime, $this->currentround->endtime);
        return $DB->get_records_sql('SELECT q.id, q.hotquestion, q.content, q.userid, q.time,
            q.anonymous, q.approved, q.tpriority, count(v.voter) as votecount
            FROM {hotquestion_questions} q
            LEFT JOIN {hotquestion_votes} v
            ON v.question = q.id
            WHERE q.hotquestion = ?
            AND q.time >= ?
            AND q.time <= ?
            GROUP BY q.id
            ORDER BY tpriority DESC, votecount DESC, q.time DESC', $params);
    }

    /**
     * Remove selected question and any votes that it might have.
     *
     * @return object
     */
    public function remove_question() {
        global $CFG, $DB;

        $data = new StdClass();
        $data->hotquestion = $this->instance->id;
        $context = context_module::instance($this->cm->id);
        // Trigger remove_question event.
        if ($CFG->version > 2014051200) { // If newer than Moodle 2.7+ use new event logging.
            $params = array(
                'objectid' => $this->cm->id,
                'context' => $context,
            );
            $event = remove_question::create($params);
            $event->trigger();
        } else {
            add_to_log($this->course->id, 'hotquestion', 'remove question',
                "view.php?id={$this->cm->id}&round=$rid", $rid, $this->cm->id);
        }

        if (null !== (required_param('q', PARAM_INT))) {
            $questionid = required_param('q', PARAM_INT);
            $dbquestion = $DB->get_record('hotquestion_questions', array('id' => $questionid));
            $DB->delete_records('hotquestion_questions', array('id' => $dbquestion->id));
            // Get an array of all votes on the question that was just deleted, then delete them.
            $dbvote = $DB->get_records('hotquestion_votes', array('question' => $questionid));
            $DB->delete_records('hotquestion_votes', array('question' => $dbquestion->id));
        }
        return $this->currentround;
    }

    /**
     * If the currently being viewed round is empty, delete it.
     * Otherwise, remove any questions in the round currently being viewed,
     * remove any votes for each question being removed,
     * then remove the currently being viewed round.
     * @return nothing
     */
    public function remove_round() {
        global $CFG, $DB;

        $data = new StdClass();
        $data->hotquestion = $this->instance->id;
        $context = context_module::instance($this->cm->id);
        // Trigger remove_question event.
        if ($CFG->version > 2014051200) { // If newer than Moodle 2.7+ use new event logging.
            $params = array(
                'objectid' => $this->cm->id,
                'context' => $context,
            );
            $event = remove_round::create($params);
            $event->trigger();
        } else {
            add_to_log($this->course->id, 'hotquestion', 'remove round',
                "view.php?id={$this->cm->id}&round=$rid", $rid, $this->cm->id);
        }

        $roundid = required_param('round', PARAM_INT);
        if ($this->currentround->endtime == 0) {
            $this->currentround->endtime = 0xFFFFFFFF;  // Hack.
        }
        $params = array($this->instance->id, $this->currentround->starttime, $this->currentround->endtime);
        $questions = $DB->get_records_sql('SELECT q.id, q.hotquestion, q.content, q.userid, q.time,
            q.anonymous, q.approved, q.tpriority, count(v.voter) as votecount
            FROM {hotquestion_questions} q
            LEFT JOIN {hotquestion_votes} v
            ON v.question = q.id
            WHERE q.hotquestion = ?
            AND q.time >= ?
            AND q.time <= ?
            GROUP BY q.id
            ORDER BY votecount DESC, q.time DESC', $params);

        if ($questions) {
            foreach ($questions as $q) {
                $questionid = $q->id; // Get id of first question on the page to delete.
                $dbquestion = $DB->get_record('hotquestion_questions', array('id' => $questionid));
                $DB->delete_records('hotquestion_questions', array('id' => $dbquestion->id));
                // Get an array of all votes on the question that was just deleted, then delete them.
                $dbvote = $DB->get_records('hotquestion_votes', array('question' => $questionid));
                $DB->delete_records('hotquestion_votes', array('question' => $dbquestion->id));
            }
            // Now that all questions and votes are gone, remove the round.
            $dbround = $DB->get_record('hotquestion_rounds', array('id' => $roundid));
            $DB->delete_records('hotquestion_rounds', array('id' => $dbround->id));
        } else {
            // This round is empty so delete without having to remove questions and votes.
            $dbround = $DB->get_record('hotquestion_rounds', array('id' => $roundid));
            $DB->delete_records('hotquestion_rounds', array('id' => $dbround->id));
        }
        // Now we need to see if we need a new round or have one we can still use.
        $rounds = $DB->get_records('hotquestion_rounds', array('hotquestion' => $this->instance->id), 'id DESC');

        foreach ($rounds as $rnd) {
            if ($rnd->endtime == 0) {
                // Deleted a closed round so just return.
                return;
            } else {
                // Deleted our open round so create a new round.
                $round = new StdClass();
                $round->starttime = time();
                $round->endtime = 0;
                $round->hotquestion = $this->instance->id;
                $round->id = $DB->insert_record('hotquestion_rounds', $round);
                $rounds[] = $round;
                return;
            }
        }
        return $this->currentround;
    }

    /**
     * Download questions.
     * @param array $chq
     * @param string $filename - The filename to use.
     * @param string $delimiter - The character to use as a delimiter.
     * @return nothing
     */
    public function download_questions($chq, $filename = "export.csv", $delimiter=";") {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir.'/csvlib.class.php');

        $context = context_module::instance($this->cm->id);
        // Trigger download_questions event.
        if ($CFG->version > 2014051200) { // If newer than Moodle 2.7+ use new event logging.
            $params = array(
                'objectid' => $this->cm->id,
                'context' => $context,
            );
            $event = download_questions::create($params);
            $event->trigger();
        } else {
            add_to_log($this->course->id, 'hotquestion', 'download questions',
                "view.php?id={$this->cm->id}&round=$rid", $rid, $this->cm->id);
        }

        // Construct sql query and filename based on admin or teacher/manager.
        // Add filename details based on course and HQ activity name.
        $csv = new csv_export_writer();
        $strhotquestion = get_string('hotquestion', 'hotquestion');

        $fields = array();

        if (is_siteadmin($USER->id)) {
            // Add fields with HQ default labels since admin will list ALL site questions.
            $fields = array(get_string('firstname'),
                            get_string('lastname'),
                            get_string('userid', 'hotquestion'),
                            get_string('hotquestion', 'hotquestion').' ID',
                            get_string('question', 'hotquestion').' ID',
                            get_string('time', 'hotquestion'),
                            get_string('anonymous', 'hotquestion'),
                            get_string('teacherpriority', 'hotquestion'),
                            get_string('heat', 'hotquestion'),
                            get_string('approvedyes', 'hotquestion'),
                            get_string('content', 'hotquestion')
                            );
            // For admin we want every hotquestion activity.
            $whichhqs = ('AND hq.hotquestion > 0');
            $csv->filename = clean_filename(get_string('exportfilenamep1', 'hotquestion'));

            // 20200524 Add info to our data array and denote this is ALL site questions.
            $activityinfo = array(null, null, null, null, null,
                                  null, null, null, null, null,
                                  get_string('exportfilenamep1', 'hotquestion').
                                  get_string('exportfilenamep2', 'hotquestion').
                                  gmdate("Ymd_Hi").get_string('for', 'hotquestion').
                                  $CFG->wwwroot);
            $csv->add_data($activityinfo);
        } else {
            // Add fields with the column labels for ONLY the current HQ activity.
            $fields = array(get_string('firstname'),
                            get_string('lastname'),
                            get_string('userid', 'hotquestion'),
                            get_string('hotquestion', 'hotquestion').' ID',
                            get_string('question', 'hotquestion').' ID',
                            get_string('time', 'hotquestion'),
                            get_string('anonymous', 'hotquestion'),
                            $this->instance->teacherprioritylabel,
                            $this->instance->heatlabel,
                            $this->instance->approvallabel,
                            $this->instance->questionlabel,
                            );

            $whichhqs = ('AND hq.hotquestion = ');
            $whichhqs .= (':thisinstid');

            $csv->filename = clean_filename(($this->course->shortname).'_');
            $csv->filename .= clean_filename(($this->instance->name));
            // 20200513 Add the course shortname and the HQ activity name to our data array.
            $activityinfo = array(get_string('course').': '
                                  .$this->course->shortname,
                                  get_string('activity').': '
                                  .$this->instance->name);
            $csv->add_data($activityinfo);
        }

        $csv->filename .= clean_filename(get_string('exportfilenamep2', 'hotquestion').gmdate("Ymd_Hi").'GMT.csv');

        // Add the column headings to our data array.
        $csv->add_data($fields);
        // Now add this instance id that's needed in the sql for teachers and managers downloads.
        $fields = array($fields, 'thisinstid' => $this->instance->id);

        if ($CFG->dbtype == 'pgsql') {
            $sql = "SELECT hq.id AS question,
                      CASE
                           WHEN u.firstname = 'Guest user'
                           THEN u.lastname || 'Anonymous'
                           ELSE u.firstname
                       END AS firstname,
                           u.lastname AS lastname,
                           hq.hotquestion AS hotquestion,
                           hq.content AS content,
                           hq.userid AS userid,
                           to_char(to_timestamp(hq.time), 'YYYY-MM-DD HH24:MI:SS') AS time,
                           hq.anonymous AS anonymous,
                           hq.tpriority AS tpriority,
                           COUNT(hv.voter) AS heat,
                           hq.approved AS approved
                     FROM {hotquestion_questions} hq
                LEFT JOIN {hotquestion_votes} hv ON hv.question=hq.id
                     JOIN {user} u ON u.id = hq.userid
                    WHERE hq.userid > 0 ";
        } else {
            $sql = "SELECT hq.id AS question,
                      CASE
                           WHEN u.firstname = 'Guest user'
                           THEN CONCAT(u.lastname, 'Anonymous')
                           ELSE u.firstname
                       END AS 'firstname',
                           u.lastname AS 'lastname',
                           hq.hotquestion AS hotquestion,
                           hq.content AS content,
                           hq.userid AS userid,
                           FROM_UNIXTIME(hq.time) AS TIME,
                           hq.anonymous AS anonymous,
                           hq.tpriority AS tpriority,
                           COUNT(hv.voter) AS heat,
                           hq.approved AS approved
                     FROM {hotquestion_questions} hq
                LEFT JOIN {hotquestion_votes} hv ON hv.question=hq.id
                     JOIN {user} u ON u.id = hq.userid
                    WHERE hq.userid > 0 ";
        }

        $sql .= ($whichhqs);
        $sql .= " GROUP BY u.lastname, u.firstname, hq.hotquestion, hq.id
                  ORDER BY hq.hotquestion ASC, hq.id ASC, tpriority DESC, heat";

        // Add the list of users and HotQuestions to our data array.
        if ($hqs = $DB->get_records_sql($sql, $fields)) {
            foreach ($hqs as $q) {
                $output = array($q->firstname, $q->lastname, $q->userid, $q->hotquestion, $q->question,
                    $q->time, $q->anonymous, $q->tpriority, $q->heat, $q->approved, $q->content);
                $csv->add_data($output);
            }
        }
        // Download the completed array.
        $csv->download_file();
        exit;
    }

    /**
     * Toggle approval go/stop of current question in current round.
     *
     * @param var $question
     * @return nothing
     */
    public function approve_question($question) {
        global $CFG, $DB, $USER;
        $context = context_module::instance($this->cm->id);
        $question = $DB->get_record('hotquestion_questions', array('id' => $question));

        if ($question->approved) {
            // If currently approved, toggle to disapproved.
            $question->approved = '0';
            $DB->update_record('hotquestion_questions', $question);
        } else {
            // If currently disapproved, toggle to approved.
            $question->approved = '1';
            $DB->update_record('hotquestion_questions', $question);
        }
        return;
    }

    /**
     * Set teacher priority of current question in current round.
     *
     * @param int $u the priority up or down flag.
     * @param int $question the question id
     */
    public function tpriority_change($u, $question) {
        global $CFG, $DB, $USER;

        $context = context_module::instance($this->cm->id);
        $question = $DB->get_record('hotquestion_questions', array('id' => $question));

        if ($u) {
            // If priority flag is 1, increase priority by 1.
            $question->tpriority = ++$question->tpriority;
            $DB->update_record('hotquestion_questions', $question);
        } else {
            // If priority flag is 0, decrease priority by 1.
            $question->tpriority = --$question->tpriority;
            $DB->update_record('hotquestion_questions', $question);
        }
    }
}

/**
 * Count questions in current rounds.
 * Counts all the hotquestion entries (optionally in a given group)
 * and is called from index.php.
 * @param var $hotquestion
 * @param int $groupid
 * @return nothing
 */
function hotquestion_count_entries($hotquestion, $groupid = 0) {
    global $DB, $CFG, $USER;

    $cm = hotquestion_get_coursemodule($hotquestion->id);
    $context = context_module::instance($cm->id);
    // Get the groupmode which should be 0, 1, or 2.
    $groupmode = ($hotquestion->groupmode);

    // If user is in a group, how many users and questions in each Hot Question activity current round?
    if ($groupid && ($groupmode > '0')) {

        // Extract each group id from $groupid and process based on whether viewer is a member of the group.
        // Show user and question counts only if a member of the current group.
        foreach ($groupid as $gid) {
            $sql = "SELECT COUNT(DISTINCT hq.userid) AS ucount,
                     COUNT(DISTINCT hq.content) AS qcount
                      FROM {hotquestion_questions} hq
                      JOIN {groups_members} g ON g.userid = hq.userid
                      JOIN {user} u ON u.id = g.userid
                 LEFT JOIN {hotquestion_rounds} hr ON hr.hotquestion=hq.hotquestion
                     WHERE hq.hotquestion = :hqid
                           AND g.groupid = :gidid
                           AND hr.endtime=0
                           AND hq.time>=hr.starttime
                           AND hq.userid>0";
            $params = array();
            $params = ['hqid' => $hotquestion->id] + ['gidid' => $gid->id];
            $hotquestions = $DB->get_records_sql($sql, $params);
        }

    } else if (!$groupid && ($groupmode > '0')) {

        // Check all the entries from the whole course.
        // If not currently a group member, but group mode is set for separate groups or visible groups,
        // see if this user has posted anyway, posted before mode was changed or posted before removal from a group.
        $sql = "SELECT COUNT(DISTINCT hq.userid) AS ucount, COUNT(DISTINCT hq.content) AS qcount FROM {hotquestion_questions} hq
                  JOIN {user} u ON u.id = hq.userid
             LEFT JOIN {hotquestion_rounds} hr ON hr.hotquestion=hq.hotquestion
                 WHERE hq.hotquestion = :hqid
                       AND hr.endtime = 0
                       AND hq.time >= hr.starttime
                       AND hq.userid = :userid";

        $params = array();
        $params = ['hqid' => $hotquestion->id] + ['userid' => $USER->id];
        $hotquestions = $DB->get_records_sql($sql, $params);

    } else {

        // Check all the users and entries from the whole course.
        $sql = "SELECT COUNT(DISTINCT hq.userid) AS ucount, COUNT(DISTINCT hq.content) AS qcount FROM {hotquestion_questions} hq
                  JOIN {user} u ON u.id = hq.userid
             LEFT JOIN {hotquestion_rounds} hr ON hr.hotquestion=hq.hotquestion
                 WHERE hq.hotquestion = :hqid
                       AND hr.endtime = 0
                       AND hq.time >= hr.starttime
                       AND hq.userid > 0";
        $params = array();
        $params = ['hqid' => $hotquestion->id];
        $hotquestions = $DB->get_records_sql($sql, $params);
    }

    if (!$hotquestions) {
        return 0;
    }
    $canadd = get_users_by_capability($context, 'mod/hotquestion:ask', 'u.id');
    $entriesmanager = get_users_by_capability($context, 'mod/hotquestion:manageentries', 'u.id');
    // If not enrolled or not an admin, teacher, or manager, then return nothing.
    if ($canadd || $entriesmanager) {
        return ($hotquestions);
    } else {
        return 0;
    }
}

    /**
     * Returns availability status.
     * Added 10/2/16.
     * @param var $hotquestion
     */
function hq_available($hotquestion) {
    $timeopen = $hotquestion->timeopen;
    $timeclose = $hotquestion->timeclose;
    return (($timeopen == 0 || time() >= $timeopen) && ($timeclose == 0 || time() < $timeclose));
}

/**
 * Returns the hotquestion instance course_module id
 *
 * Called from function hotquestion_count_entries().
 * @param var $hotquestionid
 * @return object
 */
function hotquestion_get_coursemodule($hotquestionid) {
    global $DB;
    $sql = "SELECT cm.id
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.instance = :hqid
               AND m.name = 'hotquestion'";
    $params = array();
    $params = ['hqid' => $hotquestionid];
    return $DB->get_record_sql($sql, $params);
}

/**
 * Update the calendar entries for this hotquestion activity.
 *
 * @param stdClass $hotquestion the row from the database table hotquestion.
 * @param int $cmid The coursemodule id
 * @return bool
 */
function hotquestion_update_calendar(stdClass $hotquestion, $cmid) {
    global $DB, $CFG;

    require_once($CFG->dirroot.'/calendar/lib.php');

    // Hotquestion start calendar events.
    $event = new stdClass();
    $event->eventtype = HOTQUESTION_EVENT_TYPE_OPEN;
    // The HOTQUESTION_EVENT_TYPE_OPEN event should only be an action event if no close time is specified.
    if ($CFG->branch > 32) {
        $event->type = empty($hotquestion->timeclose) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
    }
    if ($event->id = $DB->get_field('event', 'id',
        array('modulename' => 'hotquestion', 'instance' => $hotquestion->id, 'eventtype' => $event->eventtype))) {
        if ((!empty($hotquestion->timeopen)) && ($hotquestion->timeopen > 0)) {
            // Calendar event exists so update it.
            $event->name = get_string('calendarstart', 'hotquestion', $hotquestion->name);
            $event->description = format_module_intro('hotquestion', $hotquestion, $cmid);
            $event->timestart = $hotquestion->timeopen;
            $event->timesort = $hotquestion->timeopen;
            $event->visible = instance_is_visible('hotquestion', $hotquestion);
            $event->timeduration = 0;

            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            // Calendar event is on longer needed.
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->delete();
        }
    } else {
        // Event doesn't exist so create one.
        if ((!empty($hotquestion->timeopen)) && ($hotquestion->timeopen > 0)) {
            $event->name = get_string('calendarstart', 'hotquestion', $hotquestion->name);
            $event->description = format_module_intro('hotquestion', $hotquestion, $cmid);
            $event->courseid = $hotquestion->course;
            $event->groupid = 0;
            $event->userid = 0;
            $event->modulename = 'hotquestion';
            $event->instance = $hotquestion->id;
            $event->timestart = $hotquestion->timeopen;
            $event->timesort = $hotquestion->timeopen;
            $event->visible = instance_is_visible('hotquestion', $hotquestion);
            $event->timeduration = 0;

            calendar_event::create($event, false);
        }
    }

    // Hotquestion end calendar events.
    $event = new stdClass();
    if ($CFG->branch > 32) {
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
    }
    $event->eventtype = HOTQUESTION_EVENT_TYPE_CLOSE;
    if ($event->id = $DB->get_field('event', 'id',
        array('modulename' => 'hotquestion', 'instance' => $hotquestion->id, 'eventtype' => $event->eventtype))) {
        if ((!empty($hotquestion->timeclose)) && ($hotquestion->timeclose > 0)) {
            // Calendar event exists so update it.
            $event->name = get_string('calendarend', 'hotquestion', $hotquestion->name);
            $event->description = format_module_intro('hotquestion', $hotquestion, $cmid);
            $event->timestart = $hotquestion->timeclose;
            $event->timesort = $hotquestion->timeclose;
            $event->visible = instance_is_visible('hotquestion', $hotquestion);
            $event->timeduration = 0;

            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            // Calendar event is on longer needed.
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->delete();
        }
    } else {
        // Event doesn't exist so create one.
        if ((!empty($hotquestion->timeclose)) && ($hotquestion->timeclose > 0)) {
            $event->name = get_string('calendarend', 'hotquestion', $hotquestion->name);
            $event->description = format_module_intro('hotquestion', $hotquestion, $cmid);
            $event->courseid = $hotquestion->course;
            $event->groupid = 0;
            $event->userid = 0;
            $event->modulename = 'hotquestion';
            $event->instance = $hotquestion->id;
            $event->timestart = $hotquestion->timeclose;
            $event->timesort = $hotquestion->timeclose;
            $event->visible = instance_is_visible('hotquestion', $hotquestion);
            $event->timeduration = 0;

            calendar_event::create($event, false);
        }
    }

    return true;
}

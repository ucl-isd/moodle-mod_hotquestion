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
    /** @var int callback arg - the total round count in this hot question */
    protected $roundcount;
    /** @var int callback arg - the round being looked at in this hot question */
    protected $currentroundx;

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

        // $data->content = trim($fromform->question);
        // print_object($fromform->question2);
        // 20210218 Switched code to use text editor instead of text area.
        $data->content = ($fromform->text_editor['text']);
        $data->format = ($fromform->text_editor['format']);

        $data->userid = $USER->id;
        $data->time = time();
        $data->tpriority = 0;
        // Check if approval is required for this HotQuestion activity.
        if (!($this->instance->approval)) {
            // If approval is NOT required, then auto approve the question so everyone can see it.
            $data->approved = 1;
        } else {
            // If approval is required, then mark as not approved so only teachers can see it.
            $data->approved = 0;
        }
        $context = context_module::instance($this->cm->id);
        // If marked anonymous and anonymous is allowed then change from actual userid to guest.
        if (isset($fromform->anonymous) && $fromform->anonymous && $this->instance->anonymouspost) {
            $data->anonymous = $fromform->anonymous;
            // Assume this user is guest.
            $data->userid = $CFG->siteguest;
        }
        if (!empty($data->content)) {
            // If there is some actual content, then create a new record.
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
     * Calculates number of remaining votes (heat) available to this user in the current round.
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
               GROUP BY hqq.id, hqr.id, hqv.voter, hqq.hotquestion, hqq.content,
                        hqq.userid, hqv.voter, hqq.time, hqq.anonymous, hqq.tpriority,
                        hqq.approved
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

        // Get all the rounds for this Hot Question.
        $rounds = $DB->get_records('hotquestion_rounds', array('hotquestion' => $this->instance->id), 'id ASC');

        // 20210214 Get total number of rounds for the current Hot Question activity.
        $this->roundcount = (count($rounds));

        // If there are no rounds, it is a new Hot Question activity and we need to create the first round for it.
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
            // 20210215 $currentkey contains the virtual number - 1 of the current round being looked at.
            // Correct the virtual number by adding 1.
            $this->currentroundx = $currentkey + 1;

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
     * Return next round.
     *
     * @return object
     */
    public function get_roundcount() {
        return $this->roundcount;
    }

    /**
     * Return next round.
     *
     * @return object
     */
    public function get_currentroundx() {
        return $this->currentroundx;
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
        // 20210306 Added format to the selection.
        return $DB->get_records_sql('SELECT q.id, q.hotquestion, q.content, q.format, q.userid, q.time,
            q.anonymous, q.approved, q.tpriority, count(v.voter) as votecount
            FROM {hotquestion_questions} q
            LEFT JOIN {hotquestion_votes} v
            ON v.question = q.id
            WHERE q.hotquestion = ?
            AND q.time >= ?
            AND q.time <= ?
            GROUP BY q.id, q.hotquestion, q.content, q.userid, q.time,
                     q.anonymous, q.approved, q.tpriority
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
            GROUP BY q.id, q.hotquestion, q.content, q.userid, q.time, q.anonymous,
                     q.approved, q.tpriority
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
        $sql .= " GROUP BY u.lastname, u.firstname, hq.hotquestion, hq.id, hq.content,
                            hq.userid, hq.time, hq.anonymous, hq.tpriority, hq.approved
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






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
 * This file contains a renderer for the hotquestion module.
 *
 * @package   mod_hotquestion
 * @copyright 2012 Zhang Anzhen
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * A custmom renderer class that extends the plugin_renderer_base and is used by the hotquestion module.
 *
 * @package   mod_hotquestion
 * @copyright 2012 Zhang Anzhen
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_hotquestion_renderer extends plugin_renderer_base {

    /**
     * Rendering hotquestion files.
     * @var init $hotquestion
     */
    private $hotquestion;

    /**
     * Initialise internal objects.
     *
     * @param object $hotquestion
     */
    public function init($hotquestion) {
        $this->hotquestion = $hotquestion;
    }

    /**
     * Return introduction
     */
    public function introduction() {
        $output = '';
        if (trim($this->hotquestion->instance->intro)) {
            $output .= $this->box_start('generalbox boxaligncenter', 'intro');
            $output .= format_module_intro('hotquestion', $this->hotquestion->instance, $this->hotquestion->cm->id);
            $output .= $this->box_end();
        }
        return $output;
    }

    /**
     * Return the toolbar
     *
     * @param bool $shownew whether show "New round" button
     * return alist of links
     */
    public function toolbar($shownew = true) {
        $output = '';
        $toolbuttons = array();
        $roundp = new stdClass();
        $round = '';
        $roundn = '';
        $roundp = '';

        // Print export to .csv file toolbutton.
        if ($shownew) {
            $options = array();
            $options['id'] = $this->hotquestion->cm->id;
            $options['action'] = 'download';
            $url = new moodle_url('/mod/hotquestion/view.php', $options);
            $toolbuttons[] = html_writer::link($url, $this->pix_icon('a/download_all'
                , get_string('csvexport', 'hotquestion'))
                , array('class' => 'toolbutton'));
        }

        // Print prev/next round toolbuttons.
        if ($this->hotquestion->get_prevround() != null) {
            $roundp = $this->hotquestion->get_prevround()->id;
            $roundn = '';

            $url = new moodle_url('/mod/hotquestion/view.php', array('id' => $this->hotquestion->cm->id, 'round' => $roundp));
            $toolbuttons[] = html_writer::link($url, $this->pix_icon('t/collapsed_rtl'
                , get_string('previousround', 'hotquestion')), array('class' => 'toolbutton'));
        } else {
            $toolbuttons[] = html_writer::tag('span', $this->pix_icon('t/collapsed_empty_rtl', '')
                , array('class' => 'dis_toolbutton'));
        }
        if ($this->hotquestion->get_nextround() != null) {
            $roundn = $this->hotquestion->get_nextround()->id;
            $roundp = '';

            $url = new moodle_url('/mod/hotquestion/view.php', array('id' => $this->hotquestion->cm->id, 'round' => $roundn));
            $toolbuttons[] = html_writer::link($url, $this->pix_icon('t/collapsed'
                , get_string('nextround', 'hotquestion')), array('class' => 'toolbutton'));
        } else {
            $toolbuttons[] = html_writer::tag('span', $this->pix_icon('t/collapsed_empty', ''), array('class' => 'dis_toolbutton'));
        }

        // Print new round toolbutton.
        if ($shownew) {
            $options = array();
            $options['id'] = $this->hotquestion->cm->id;
            $options['action'] = 'newround';
            $url = new moodle_url('/mod/hotquestion/view.php', $options);
            $toolbuttons[] = html_writer::link($url, $this->pix_icon('t/add'
                , get_string('newround', 'hotquestion')), array('class' => 'toolbutton'));
        }

        // Print remove round toolbutton.
        if ($shownew) {
            $options = array();
            $options['id'] = $this->hotquestion->cm->id;
            $options['action'] = 'removeround';
            $options['round'] = $this->hotquestion->get_currentround()->id;
            $url = new moodle_url('/mod/hotquestion/view.php', $options);
            $toolbuttons[] = html_writer::link($url, $this->pix_icon('t/less'
            , get_string('removeround', 'hotquestion')), array('class' => 'toolbutton'));
        }

        // Print refresh toolbutton.
        $url = new moodle_url('/mod/hotquestion/view.php', array('id' => $this->hotquestion->cm->id));
        $toolbuttons[] = html_writer::link($url, $this->pix_icon('t/reload', get_string('reload')), array('class' => 'toolbutton'));

        // Return all available toolbuttons.
        $output .= html_writer::alist($toolbuttons, array('id' => 'toolbar'));
        return $output;
    }

    /**
     * Return all questions in current list.
     *
     * Return question list, which includes the content, the author, the time
     * and the heat. If $can_vote is true, will display an icon to vote with.
     *
     * @param bool $allowvote whether current user has vote cap
     * return table of questionlist
     */
    public function questions($allowvote = true) {
        global $DB, $CFG, $USER;
        $output = '';
        $formatoptions = new stdClass();
        $a = new stdClass();
        // Search questions in current round.
        $questions = $this->hotquestion->get_questions();

        // Added for Remove capability.
        $id = required_param('id', PARAM_INT);
        $hq = new mod_hotquestion($id);
        $context = context_module::instance($hq->cm->id);

        if ($questions) {
            $table = new html_table();
            $table->cellpadding = 10;
            $table->class = 'generaltable';
            $table->width = '100%';
            $table->align = array ('left', 'center', 'center');
            // Modified table heading for show/not show Remove capability.
            if (has_capability('mod/hotquestion:manageentries', $context)) {
                $table->head = array(get_string('question', 'hotquestion')
                    , get_string('heat', 'hotquestion'), get_string('questionremove', 'hotquestion'));
            } else {
                $table->head = array(get_string('question', 'hotquestion'), get_string('heat', 'hotquestion'));
            }

            foreach ($questions as $question) {
                $line = array();
                $formatoptions->para = false;
                $content = format_text($question->content, FORMAT_MOODLE, $formatoptions);
                $user = $DB->get_record('user', array('id' => $question->userid));

                // Process the question part of the row entry.
                if ($question->anonymous) {
                    $a->user = get_string('anonymous', 'hotquestion');
                } else {
                    $a->user = '<a href="'.$CFG->wwwroot.'/user/view.php?id='
                    .$user->id.'&amp;course='.$this->hotquestion->course->id.'">'.fullname($user).'</a>';
                }
                // Process the time part of the row entry.
                $a->time = userdate($question->time).'&nbsp('.get_string('ago', 'hotquestion'
                    , format_time(time() - $question->time)).')';
                $info = '<div class="author">'.get_string('authorinfo', 'hotquestion', $a).'</div>';
                $line[] = $content.$info;
                $heat = $question->votecount;
                $remove = '';

                // Print the vote cron case.
                if ($allowvote && $this->hotquestion->can_vote_on($question)) {
                    if (!$this->hotquestion->has_voted($question->id)) {
                        $heat .= '&nbsp;<a href="view.php?id='
                              .$this->hotquestion->cm->id
                              .'&action=vote&q='.$question->id
                              .'" class="hotquestion_vote" id="question_'
                              .$question->id.'"><img src="'.$this->image_url('s/yes')
                              .'" title="'.get_string('vote', 'hotquestion')
                              .'" alt="'.get_string('vote', 'hotquestion').'"/></a>';
                    }
                }
                $line[] = $heat;

                // Print the remove case option for teacher and manager.
                if (has_capability('mod/hotquestion:manageentries', $context)) {
                    // Do something.
                    $remove .= '&nbsp;<a href="view.php?id='
                            .$this->hotquestion->cm->id.'&action=remove&q='
                            .$question->id.'" class="hotquestion_vote" id="question_'
                            .$question->id.'"><img src="'.$this->image_url('t/delete').'" title="'
                            .get_string('questionremove', 'hotquestion') .'" alt="'
                            .get_string('questionremove', 'hotquestion') .'"/></a>';
                    $line[] = $remove;

                }
                $table->data[] = $line;
            }
            $output .= html_writer::table($table);
        } else {
            $output .= $this->box(get_string('noquestions', 'hotquestion'), 'center', '70%');
        }
        return $output;
    }
    /**
     * Returns HTML for a hotquestion inaccessible message.
     * Added 10/2/16
     * @param string $message
     * @return <type>
     */
    public function hotquestion_inaccessible($message) {
        global $CFG;
        $output  = $this->output->box_start('generalbox boxaligncenter');
        $output .= $this->output->box_start('center');
        $output .= (get_string('notavailable', 'hotquestion'));
        $output .= $message;
        $output .= $this->output->box('<a href="'.$CFG->wwwroot.'/course/view.php?id='
                . $this->page->course->id .'">'
                . get_string('returnto', 'hotquestion', format_string($this->page->course->fullname, true))
                .'</a>', 'hotquestionbutton standardbutton');
        $output .= $this->output->box_end();
        $output .= $this->output->box_end();
        return $output;
    }
}


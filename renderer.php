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
 * @copyright 2019 onwards AL Rachels drachels@drachels.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * A custmom renderer class that extends the plugin_renderer_base and is used by the hotquestion module.
 *
 * @package   mod_hotquestion
 * @copyright 2019 onwards AL Rachels drachels@drachels.com
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
        global $DB, $CFG, $USER;

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
        // Added new round confirm 6/24/19.
        if ($shownew) {
            $options = array();
            $options['id'] = $this->hotquestion->cm->id;
            $options['action'] = 'newround';
            if ($CFG->branch > 32) {
                $rtemp = $this->image_url('t/add');
            } else {
                $rtemp = $this->pix_url('t/add');
            }
            $url = '&nbsp;<a onclick="return confirm(\''.get_string('newroundconfirm', 'hotquestion').'\')" href="view.php?id='
                .$this->hotquestion->cm->id.'&action=newround&round='
                .$this->hotquestion->get_currentround()->id
                .'"><img src="'.$rtemp.'" title="'
                .get_string('newround', 'hotquestion') .'" alt="'
                .get_string('newround', 'hotquestion') .'"/></a>';
            $toolbuttons[] = $url;
        }

        // Print remove round toolbutton.
        // Added remove round confirm 2/10/19.
        if ($shownew) {
            $options = array();
            $options['id'] = $this->hotquestion->cm->id;
            $options['action'] = 'removeround';
            $options['round'] = $this->hotquestion->get_currentround()->id;

            if ($CFG->branch > 32) {
                $rtemp = $this->image_url('t/delete');
            } else {
                $rtemp = $this->pix_url('t/delete');
            }
            $url = '&nbsp;<a onclick="return confirm(\''.get_string('deleteroundconfirm', 'hotquestion').'\')" href="view.php?id='
                .$this->hotquestion->cm->id.'&action=removeround&round='
                .$this->hotquestion->get_currentround()->id
                .'"><img src="'.$rtemp.'" title="'
                .get_string('removeround', 'hotquestion') .'" alt="'
                .get_string('removeround', 'hotquestion') .'"/></a>';

            $toolbuttons[] = $url;
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
        $heatvisibility = new stdClass();
        // Search questions in current round.
        $questions = $this->hotquestion->get_questions();
        // Set column visibility flags for Priority and Heat.
        $teacherpriorityvisibility = $this->hotquestion->instance->teacherpriorityvisibility;

        // 20200609 Auto hide heat column if vote limit is zero.
        if ($this->hotquestion->instance->heatlimit == 0) {
            $heatvisibility = '0';
        } else {
            $heatvisibility = $this->hotquestion->instance->heatvisibility;
        }

        // Added for Remove capability.
        $id = required_param('id', PARAM_INT);
        $hq = new mod_hotquestion($id);
        $context = context_module::instance($hq->cm->id);

        if (! $cm = get_coursemodule_from_id('hotquestion', $id)) {
            print_error("Course Module ID was incorrect");
        }
        if ($questions) {
            $table = new html_table();
            $table->cellpadding = 10;
            $table->class = 'generaltable';
            $table->width = '100%';
            $table->align = array ('left', 'center', 'center', 'center', 'center');

            // 20200611 If teacher changes heat setting to lower than the number of heat
            // votes already applied by a user, those users will see an error message.
            if ($this->hotquestion->heat_tally($hq, $USER->id) < 0) {
                $temp = get_string('heaterror', 'hotquestion').$this->hotquestion->heat_tally($hq, $USER->id);
            } else {
                $temp = $this->hotquestion->heat_tally($hq, $USER->id);
            }

            // Admin, manager and teachers headings for questions, priority, heat, remove and approved headings.
            if (has_capability('mod/hotquestion:manageentries', $context)) {
                // 20200512 Changed from fixed string to new questionlabel column setting.
                $table->head = array($this->hotquestion->instance->questionlabel);
                // Check teacher priority column visibilty settings.
                if ($teacherpriorityvisibility) {
                    // Priority column is visible, so show the label.
                    // 20200512 Changed from fixed string to new prioritylable column setting.
                    $table->head[] .= $this->hotquestion->instance->teacherprioritylabel;
                } else {
                    // Priority column is not visible, so replace label with a space.
                    $table->head[] .= ' ';
                }
                // Check heat column visibilty settings for teachers.
                if ($heatvisibility) {
                    // 20200512 Changed from fixed string to new heatlabel column setting.
                    // 20200526 Show heatlimit setting and how many heat/votes remain for current user.
                    $table->head[] .= $this->hotquestion->instance->heatlabel
                                   .' '.$this->hotquestion->instance->heatlimit
                                   .'/'.$temp;

                } else {
                    // Heat column is not visible, so replace label with a space.
                    $table->head[] .= ' ';
                }
                    // 20200512 Changed from fixed string to new removelabel column setting.
                    $table->head[] .= $this->hotquestion->instance->removelabel;
                    // 20200512 Changed from fixed string to new approvallabel column setting.
                    $table->head[] .= $this->hotquestion->instance->approvallabel;
            } else {
                // Students only see headings for questions, priority, and heat columns.
                // 20200512 Changed from fixed string to new questionlabel column setting.
                $table->head = array($this->hotquestion->instance->questionlabel);
                // Check teacher priority column visibilty settings.
                if ($teacherpriorityvisibility) {
                    // 20200512 Changed from fixed string to new prioritylabel column setting.
                    // Priority column is visible, so show the label.
                    $table->head[] .= $this->hotquestion->instance->teacherprioritylabel;
                } else {
                    // Priority column is not visible, so replace label with a space.
                    $table->head[] .= ' ';
                }
                // Check heat column visibilty settings for students.
                if ($heatvisibility) {
                    // 20200512 Changed from fixed string to new heatlabel column setting.
                    // Heat column is visible, so show the label.
                    $table->head[] .= $this->hotquestion->instance->heatlabel
                                   .' '.$this->hotquestion->instance->heatlimit
                                   .'/'.$temp;
                } else {
                    // Heat column is not visible, so replace label with a space.
                    $table->head[] .= ' ';
                }
            }

            // Check to see if groups are being used here.
            $groupmode = groups_get_activity_groupmode($cm);
            $currentgroup = groups_get_activity_group($cm, true);
            if ($currentgroup) {
                $groups = $currentgroup;
            } else {
                $groups = '';
            }

            // 20200528 Added variable for remaining votes to use as a test for showing vote icon for current user.
            $remaining = ($this->hotquestion->heat_tally($hq, $USER->id));

            foreach ($questions as $question) {
                $line = array();
                $formatoptions->para = false;
                $content = format_text($question->content, FORMAT_MOODLE, $formatoptions);
                $user = $DB->get_record('user', array('id' => $question->userid));
                // If groups is set to all participants or matches current group, show the question.
                if ((! $groups) || (groups_is_member($groups, $user->id))) {
                    // Process the question part of the row entry.
                    // If not a teacher and question is not approved, skip over it and do not show it.
                    if ($question->approved || (has_capability('mod/hotquestion:manageentries', $context))) {
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
                        // Get current priority value to show.
                        $tpriority = $question->tpriority;
                        // Get current heat total to show.
                        $heat = $question->votecount;
                        $remove = '';
                        $approve = '';
                        // Set code for thumbs up/thumbs down pictures based on Moodle version.
                        if ($CFG->branch > 32) {
                            $ttemp1 = $this->image_url('s/yes');
                            $ttemp2 = $this->image_url('s/no');
                            $ttemp3 = $this->image_url('t/delete');
                        } else {
                            $ttemp1 = $this->pix_url('s/yes');
                            $ttemp2 = $this->pix_url('s/no');
                            $ttemp3 = $this->pix_url('t/delete');
                        }
                        // Process priority code here.
                        // Had to add width/height to priority and heat due to now using svg in Moodle 3.6.
                        if (has_capability('mod/hotquestion:manageentries', $context)) {
                            // Process priority column.
                            $tpriority .= '&nbsp;<a href="view.php?id='
                                       .$this->hotquestion->cm->id.'&action=tpriority&u=1&q='
                                       .$question->id.'" class="hotquestion_vote" id="question_'
                                       .$question->id.'"><img src="'.$ttemp1.'" title="'
                                       .get_string('teacherpriority', 'hotquestion').'" alt="'
                                       .get_string('teacherpriority', 'hotquestion')
                                       .'" style="width:16px;height:16px;"/></a><br> &nbsp;';
                            $tpriority .= '&nbsp; &nbsp;<a href="view.php?id='
                                       .$this->hotquestion->cm->id.'&action=tpriority&u=0&q='
                                       .$question->id.'" class="hotquestion_vote" id="question_'
                                       .$question->id.'"><img src="'.$ttemp2.'" title="'
                                       .get_string('teacherpriority', 'hotquestion') .'" alt="'
                                       .get_string('teacherpriority', 'hotquestion') .'" style="width:16px;height:16px;"/></a>';
                        }

                        // Check teacher priority column visibilty settings.
                        if ($teacherpriorityvisibility) {
                            // The priority column is visible, so show the data.
                            $line[] = $tpriority;
                        } else {
                            // The priority column is not visible, so replace the data with a space.
                            $line[] = ' ';
                        }

                        // Print the vote cron case. 20200528 Added check for votes remaining.
                        if ($allowvote && $this->hotquestion->can_vote_on($question) && ($remaining >= 0)) {
                            if (!$this->hotquestion->has_voted($question->id) && ($remaining >= 1)) {
                                $heat .= '&nbsp;<a href="view.php?id='
                                      .$this->hotquestion->cm->id
                                      .'&action=vote&q='.$question->id
                                      .'" class="hotquestion_vote" id="question_'
                                      .$question->id.'"><img src="'.$ttemp1
                                      .'" title="'.get_string('vote', 'hotquestion')
                                      .'" alt="'.get_string('vote', 'hotquestion').'" style="width:16px;height:16px;"/></a>';
                            } else if ($this->hotquestion->has_voted($question->id)) {
                                // 20200608 Added remove vote capability.
                                $heat .= '&nbsp;<a href="view.php?id='
                                      .$this->hotquestion->cm->id
                                      .'&action=removevote&q='.$question->id
                                      .'" class="hotquestion_remove_vote" id="question_'
                                      .$question->id.'"> <img src="'.$ttemp3
                                      .'" title="'.get_string('removevote', 'hotquestion')
                                      .'" alt="'.get_string('removevote', 'hotquestion').'" "/></a>';
                            }

                        }

                        // Check heat column visibilty settings.
                        if ($heatvisibility) {
                            // The heat column is visible, so show the data.
                            $line[] = $heat;
                        } else {
                            // The heat column is not visible, so replace the data with a space.
                            $line[] = ' ';
                        }

                        // Set code for remove picture based on Moodle version.
                        if ($CFG->branch > 32) {
                            $rtemp = $this->image_url('t/delete');
                        } else {
                            $rtemp = $this->pix_url('t/delete');
                        }
                        // Print the remove and approve case option for teacher and manager.
                        if (has_capability('mod/hotquestion:manageentries', $context)) {
                            // Process remove column.
                            // Added delete confirm 2/8/19.
                            $remove .= '&nbsp;<a onclick="return confirm(\''
                                    .get_string('deleteentryconfirm', 'hotquestion')
                                    .'\')" href="view.php?id='
                                    .$this->hotquestion->cm->id.'&action=remove&q='
                                    .$question->id.'" class="hotquestion_vote" id="question_'
                                    .$question->id.'"><img src="'.$rtemp.'" title="'
                                    .get_string('questionremove', 'hotquestion') .'" alt="'
                                    .get_string('questionremove', 'hotquestion') .'"/></a>';
                            $line[] = $remove;

                            // Process approval column.
                            // Set code for approve toggle picture based on Moodle version.
                            if ($CFG->branch > 32) {
                                $a1temp = $this->image_url('t/go');
                                $a2temp = $this->image_url('t/stop');
                            } else {
                                $a1temp = $this->pix_url('t/go');
                                $a2temp = $this->pix_url('t/stop');
                            }
                            // Show approval column.
                            if ($question->approved) {
                                $approve .= '&nbsp;<a href="view.php?id='
                                         .$this->hotquestion->cm->id.'&action=approve&q='
                                         .$question->id.'" class="hotquestion_vote" id="question_'
                                         .$question->approved.'"><img src="'.$a1temp.'" title="'
                                         .get_string('approvedyes', 'hotquestion') .'" alt="'
                                         .get_string('approvedyes', 'hotquestion') .'"/></a>';
                            } else {
                                $approve .= '&nbsp;<a href="view.php?id='
                                         .$this->hotquestion->cm->id.'&action=approve&q='
                                         .$question->id.'" class="hotquestion_vote" id="question_'
                                         .$question->approved.'"><img src="'.$a2temp.'" title="'
                                         .get_string('approvedno', 'hotquestion') .'" alt="'
                                         .get_string('approvedno', 'hotquestion') .'"/></a>';
                            }
                            $line[] = $approve;
                        }
                        $table->data[] = $line;
                    } else {
                        $line[] = get_string('notapproved', 'hotquestion');
                        $table->data[] = $line;
                    }
                }
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

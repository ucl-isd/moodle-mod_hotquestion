# Moodle Hot Question module
The first moodle activity for physical classroom. The idea comes from
Purdue University's hotseat.

Inside or outside classroom, students post questions into a Hot Question
activity from notebook, netbook, android, iPhone, iPod or any other device
which can access the moodle site. Students can also vote on others' 
questions, so that the hottest questions will be popped up to the top of
the list. Teachers can make oral comments on selected questions in classroom.

June 2017 Added 10 Behat tests.
Changed the code for Export to CSV to make it simpler.
Changed the order of the fields exported in Export to CSV. MS Excel will not open files
in which the first record is "ID" so it was moved from column 1 to column 3.

May 2017, fixed code style and documentation errors and warnings.
Moved remove_question event from view.php to function remove_question in locallib.php
Added new setting capability 10/6/2016 to display description on a course page.
Added new setting capability 10/6/2016 for availability dates for open and close.
Added new setting capability 10/6/2016 to allow creator to change wording of submit
directions text.
Included new icon 10/6/2016 for optional use.

Enhanced remove question capability 7/16/2016 to show a message that you
have successfully removed a question. Reworded the message for opening a
new round.

New capability added 7/15/2016 that places an entry with link in the 
Recent Activity block when anyone posts an entry in a Hot Question acitivity.
Added a Recent activity setting that lets you limit students to seeing
only their posts. Admin, teachers and managers still see all posts.

New capability added 4/01/2016 that allows admin, teachers, and managers to delete
any round in the current Hot Question activity if they deem all the questions in the
round are no longer needed, or if the round is empty. Can also be used to reset
the activity. If you need to keep a copy of the questions in a round, you must download
them before the round is removed.

New capability added 3/29/2016 that allows admin, teachers, and managers to download
a csv file of the questions in the current Hot Question activity. An admin can
also download questions and their download will include ALL questions from the Moodle
site. 

New capability added 3/29/2016 that allows admin, teachers, and managers to delete
any question in the current Hot Question activity if they deem the question
as inappropriate or not applicable to the subject. 

NOTE: If you plan on disciplinary action for any inappropriate question,
you should download the evidence BEFORE you remove questions or remove rounds, 
as once removed, questions and rounds are not recoverable.

Details: https://github.com/hit-moodle/moodle-mod_hotquestion/wiki

- Moodle tracker component: https://github.com/drachels/moodle-mod_hotquestion/issues
- Documentation: https://github.com/drachels/moodle-mod_hotquestion/wiki
- Source Code: https://github.com/drachels/moodle-mod_hotquestion
- License: http://www.gnu.org/licenses/gpl.txt

## Install from git
- Navigate to Moodle root folder
- **git clone git://github.com/drachels/moodle-mod_hotquestion.git mod/hotquestion**
- **cd hotquestion**
- **git checkout MOODLE_XY_STABLE** (where XY is the moodle version, e.g: MOODLE_30_STABLE, MOODLE_28_STABLE...)
- Click the 'Notifications' link on the frontpage administration block or **php admin/cli/upgrade.php** if you have access to a command line interpreter.

## Install from a compressed file
- Extract the compressed file data
- Rename the main folder to hotquestion
- Copy to the Moodle mod/ folder
- Click the 'Notifications' link on the frontpage administration block or **php admin/cli/upgrade.php** if you have access to a command line interpreter.

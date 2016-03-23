# Moodle Hot Question module
The first moodle activity for physical classroom. The idea comes from
Purdue University's hotseat.

Inside or outside classroom, students post questions into Hot Question
activity from notebook, netbook, android, iPhone, iPod and other gears
which can access the moodle site. Students can also vote on others' 
questions, so that the hottest questions will be popped up. Teachers 
can make orally comments on selected questions in classroom.

Details: https://github.com/hit-moodle/moodle-mod_hotquestion/wiki

- Moodle tracker component: https://github.com/drachels/moodle-mod_hotquestion/issues
- Documentation: https://github.com/drachels/moodle-mod_hotquestion/wiki
- Source Code: https://github.com/drachels/moodle-mod_hotquestion
- License: http://www.gnu.org/licenses/gpl.txt

## Install from git
- Navigate to Moodle root folder
- **git clone git://github.com/drachels/moodle-mod_hotquestion.git mod/hotquestion**
- **cd mootyper**
- **git checkout MOODLE_XY_STABLE** (where XY is the moodle version, e.g: MOODLE_30_STABLE, MOODLE_28_STABLE...)
- Click the 'Notifications' link on the frontpage administration block or **php admin/cli/upgrade.php** if you have access to a command line interpreter.

## Install from a compressed file
- Extract the compressed file data
- Rename the main folder to hotquestion
- Copy to the Moodle mod/ folder
- Click the 'Notifications' link on the frontpage administration block or **php admin/cli/upgrade.php** if you have access to a command line interpreter.

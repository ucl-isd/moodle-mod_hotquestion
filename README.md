[![Build Status](https://travis-ci.org/drachels/moodle-mod_hotquestion.svg?branch=master)](https://travis-ci.org/drachels/moodle-mod_hotquestion)

# Moodle Hot Question module
The first moodle activity for physical classroom. The idea comes from
Purdue University's hotseat.

Inside or outside classroom, students post questions into a Hot Question
activity from notebook, netbook, android, iPhone, iPod or any other device
which can access the moodle site. Students can also vote on others' 
questions, so that the hottest questions will be popped up to the top of
the list. Teachers can make oral comments on selected questions in classroom.

See the download page for more suggestions and way to use this plugin.

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
- **git checkout MOODLE_XY_STABLE** (where XY is the moodle version, e.g: MOODLE_311_STABLE, MOODLE_400_STABLE...)
- Click the 'Notifications' link on the frontpage administration block or **php admin/cli/upgrade.php** if you have access to a command line interpreter.

## Install from a compressed file
- Extract the compressed file data
- Rename the main folder to hotquestion
- Copy to the Moodle mod/ folder
- Click the 'Notifications' link on the frontpage administration block or **php admin/cli/upgrade.php** if you have access to a command line interpreter.

## Install directly in newer versions of Moodle
- Download the zip file from Moodle downloads
- In your Moodle navigate to Site administration > Plugins > Install plugins
- Drag and drop the downloaded zip file into the file area
- Click the 'Install plugin from the ZIP file' button.
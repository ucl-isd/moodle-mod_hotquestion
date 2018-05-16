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
 * Handle submitting question and voting action of hotquestion
 * using Ajax of YUI.
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @copyright 2016 onwards AL Rachels drachels@drachels.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

M.modHotquestion = {};

M.modHotquestion.Y = {};

M.modHotquestion.questionbox = {};
M.modHotquestion.submitbutton = {};

M.modHotquestion.init = function(Y) {
    M.modHotquestion.Y = Y;

    // Init question box.
    M.modHotquestion.questionbox = Y.one('#id_question');
    M.modHotquestion.questionbox.on('valueChange', M.modHotquestion.questionchanged);

    // Init submit button.
    M.modHotquestion.submitbutton = Y.one('#id_submitbutton');
    if (M.modHotquestion.getquestion() == '') {
        M.modHotquestion.submitbutton.set('disabled', 'disabled');
    }
    Y.on("submit", M.modHotquestion.submit, '#mform1');

    // Bind toolbar buttons.
    Y.on('click', M.modHotquestion.refresh, '.hotquestion_vote');
    Y.on('click', M.modHotquestion.refresh, '.toolbutton');

    // Bind io events.
    Y.on('io:success', M.modHotquestion.iocomplete);
    Y.on('io:failure', M.modHotquestion.iofailure);
};

M.modHotquestion.iocomplete = function(transactionid, response, arguments) {
    var Y = M.modHotquestion.Y;

    // Update questions.
    var contentdiv = Y.one('#questions_list');
    contentdiv.set("innerHTML", response.responseText);

    // Clean up form if this is a submit IO.
    if (arguments.caller == 'submit') {
        M.modHotquestion.questionbox.set('value', '');
        M.modHotquestion.questionbox.removeAttribute('disabled');
        M.modHotquestion.submitbutton.set('disabled', 'disabled');
    }

    // Rebind buttons.
    Y.on('click', M.modHotquestion.refresh, '.hotquestion_vote');
    Y.on('click', M.modHotquestion.refresh, '.toolbutton');
};

M.modHotquestion.iofailure = function(transactionid, response, arguments) {
    M.modHotquestion.submitbutton.removeAttribute('disabled');
    M.modHotquestion.questionbox.removeAttribute('disabled');
    alert(M.str.hotquestion.connectionerror);
};

M.modHotquestion.refresh = function(e) {
    e.preventDefault();

    var data = e.currentTarget.get('href').split('?', 2)[1];
    data += '&ajax=1';
    var cfg = {
        method: "GET",
        data: data,
        arguments: {
            caller: 'refresh',
        }
    };

    var request = M.modHotquestion.Y.io('view.php', cfg);
};

M.modHotquestion.getquestion = function() {
    var question = M.modHotquestion.questionbox.get('value');
    return trim(question);
};

M.modHotquestion.questionchanged = function(e) {
    var question = M.modHotquestion.getquestion();
    var submitbutton = M.modHotquestion.submitbutton;
    if (question == '') {
        submitbutton.set('disabled', 'disabled');
    } else {
        submitbutton.removeAttribute('disabled');
    }
};

M.modHotquestion.submit = function(e) {
    e.preventDefault();

    var question = M.modHotquestion.getquestion();
    if (question == '') {
        return; // Ignore empty question.
    }

    // To avoid multiple clicks and editing.
    M.modHotquestion.submitbutton.set('disabled', 'disabled');
    M.modHotquestion.questionbox.set('disabled', 'disabled');

    // Get all input components.
    var inputs = M.modHotquestion.Y.all('#mform1 input');

    // Construct post data.
    var data = '';
    inputs.each(function(node, index, nodelist) {
        if (node.get('type') != 'checkbox') {
            data += node.get('name') + '=' + node.get('value') + '&';
        } else {
            data += node.get('name') + '=' + node.get('checked') + '&';
        }
    });
    data += 'question=' + question + '&';
    data += 'ajax=1';

    var cfg = {
        method: "POST",
        data: data,
        arguments: {
            caller: 'submit',
        }
    };
    var request = M.modHotquestion.Y.io('view.php', cfg);
};
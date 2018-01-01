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

M.mod_hotquestion = {};

M.mod_hotquestion.Y = {};

M.mod_hotquestion.questionbox = {};
M.mod_hotquestion.submitbutton = {};

M.mod_hotquestion.init = function(Y) {
    M.mod_hotquestion.Y = Y;

    // Init question box.
    M.mod_hotquestion.questionbox = Y.one('#id_question');
    M.mod_hotquestion.questionbox.on('valueChange', M.mod_hotquestion.questionchanged);

    // Init submit button.
    M.mod_hotquestion.submitbutton = Y.one('#id_submitbutton');
    if (M.mod_hotquestion.getquestion() == '') {
        M.mod_hotquestion.submitbutton.set('disabled', 'disabled');
    }
    Y.on("submit", M.mod_hotquestion.submit, '#mform1');

    // Bind toolbar buttons.
    Y.on('click', M.mod_hotquestion.refresh, '.hotquestion_vote');
    Y.on('click', M.mod_hotquestion.refresh, '.toolbutton');

    // Bind io events.
    Y.on('io:success', M.mod_hotquestion.iocomplete);
    Y.on('io:failure', M.mod_hotquestion.iofailure);
};

M.mod_hotquestion.iocomplete = function(transactionid, response, arguments) {
    var Y = M.mod_hotquestion.Y;

    // Update questions.
    var contentdiv = Y.one('#questions_list');
    contentdiv.set("innerHTML", response.responseText);

    // Clean up form if this is a submit IO.
    if (arguments.caller == 'submit') {
        M.mod_hotquestion.questionbox.set('value', '');
        M.mod_hotquestion.questionbox.removeAttribute('disabled');
        M.mod_hotquestion.submitbutton.set('disabled', 'disabled');
    }

    // Rebind buttons.
    Y.on('click', M.mod_hotquestion.refresh, '.hotquestion_vote');
    Y.on('click', M.mod_hotquestion.refresh, '.toolbutton');
};

M.mod_hotquestion.iofailure = function(transactionid, response, arguments) {
    M.mod_hotquestion.submitbutton.removeAttribute('disabled');
    M.mod_hotquestion.questionbox.removeAttribute('disabled');
    alert(M.str.hotquestion.connectionerror);
};

M.mod_hotquestion.refresh = function(e) {
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

    var request = M.mod_hotquestion.Y.io('view.php', cfg);
};

M.mod_hotquestion.getquestion = function() {
    var question = M.mod_hotquestion.questionbox.get('value');
    return YAHOO.lang.trim(question);
};

M.mod_hotquestion.questionchanged = function(e) {
    var question = M.mod_hotquestion.getquestion();
    var submitbutton = M.mod_hotquestion.submitbutton;
    if (question == '') {
        submitbutton.set('disabled', 'disabled');
    } else {
        submitbutton.removeAttribute('disabled');
    }
};

M.mod_hotquestion.submit = function(e) {
    e.preventDefault();

    var question = M.mod_hotquestion.getquestion();
    if (question == '') {
        return; // Ignore empty question.
    }

    // To avoid multiple clicks and editing.
    M.mod_hotquestion.submitbutton.set('disabled', 'disabled');
    M.mod_hotquestion.questionbox.set('disabled', 'disabled');

    // Get all input components.
    var inputs = M.mod_hotquestion.Y.all('#mform1 input');

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
    var request = M.mod_hotquestion.Y.io('view.php', cfg);
};
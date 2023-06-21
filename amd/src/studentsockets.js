"use strict";

import jQuery from 'jquery';
import Templates from 'core/templates';
import Notification from 'core/notification';
import Ajax from 'core/ajax';
import Encryptor from 'mod_jqshow/encryptor';

let REGION = {
    MESSAGEBOX: '#message-box',
    USERLIST: '[data-region="active-users"]',
    COUNTUSERS: '#countusers',
    ROOT: '[data-region="student-canvas"]'
};

let SERVICES = {
    SESSIONFIINISHED: 'mod_jqshow_sessionfinished',
    USERQUESTIONRESPONSE: 'mod_jqshow_getuserquestionresponse'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    PARTICIPANT: 'mod_jqshow/session/manual/waitingroom/participant',
    SESSIONFIINISHED: 'mod_jqshow/session/manual/closeconnection',
    QUESTION: 'mod_jqshow/questions/encasement'
};

let portUrl = '8080';


/**
 * @constructor
 * @param {String} region
 * @param {String} port
 */
function Sockets(region, port) {
    this.root = jQuery(region);
    portUrl = port;
    this.initSockets();
    this.disableDevTools();
    this.initListeners();
}

Sockets.prototype.disableDevTools = function() {
    document.addEventListener('contextmenu', (e) => e.preventDefault());
    document.onkeydown = (e) => {
        return !(event.keyCode === 123 ||
            this.ctrlShiftKey(e, 'I') ||
            this.ctrlShiftKey(e, 'J') ||
            this.ctrlShiftKey(e, 'C') ||
            (e.ctrlKey && e.keyCode === 'U'.charCodeAt(0)));
    };
};

Sockets.prototype.ctrlShiftKey = function(e, keyCode) {
    return e.ctrlKey && e.shiftKey && e.keyCode === keyCode.charCodeAt(0);
};

/** @type {jQuery} The jQuery node for the page region. */
Sockets.prototype.root = null;

let userid = null;
let usersocketid = null;
let username = null;
let userimage = null;
let messageBox = null;
let countusers = null;
let cmid = null;
let sid = null;
let jqshowid = null;
let currentCuestionJqid = null;

Sockets.prototype.initSockets = function() {
    userid = this.root[0].dataset.userid;
    username = this.root[0].dataset.username;
    userimage = this.root[0].dataset.userimage;
    jqshowid = this.root[0].dataset.jqshowid;
    cmid = this.root[0].dataset.cmid;
    sid = this.root[0].dataset.sid;
    messageBox = this.root.find(REGION.MESSAGEBOX);
    countusers = this.root.find(REGION.COUNTUSERS);

    Sockets.prototype.webSocket = new WebSocket(
        'wss://' + M.cfg.wwwroot.replace(/^https?:\/\//, '') + ':' + portUrl + '/jqshow'
    );

    Sockets.prototype.webSocket.onopen = function() {

    };

    Sockets.prototype.webSocket.onmessage = function(ev) {
        let msgDecrypt = Encryptor.decrypt(ev.data);
        let response = JSON.parse(msgDecrypt); // PHP sends Json data.
        let resAction = response.action; // Message type.
        switch (resAction) {
            case 'connect':
                // The server has returned the connected status, it is time to identify yourself.
                if (response.usersocketid !== undefined) {
                    usersocketid = response.usersocketid;
                    let msg = {
                        'userid': userid,
                        'name': username,
                        'pic': userimage, // TODO encrypt.
                        'cmid': cmid,
                        'sid': sid,
                        'usersocketid': usersocketid,
                        'action': 'newuser',
                    };
                    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
                }
                break;
            case 'newuser': {
                let identifier = jQuery(REGION.USERLIST);
                let data = response.students;
                identifier.html('');
                jQuery.each(data, function (i, student) {
                    let templateContext = {
                        'usersocketid': student.usersocketid,
                        'userimage': student.picture,
                        'userfullname': student.name,
                    };
                    Templates.render(TEMPLATES.PARTICIPANT, templateContext).then(function(html) {
                        identifier.append(html);
                    }).fail(Notification.exception);
                });
                countusers.html(response.count);
                break;
            }
            case 'countusers':
                countusers.html(response.count);
                break;
            case 'question':
                Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                    let identifier = jQuery(REGION.ROOT);
                    identifier.append(html);
                    currentCuestionJqid = response.context.jqid;
                    let request = {
                        methodname: SERVICES.USERQUESTIONRESPONSE,
                        args: {
                            jqid: response.context.jqid,
                            cmid: cmid,
                            sid: sid,
                            uid: 0
                        }
                    };
                    Ajax.call([request])[0].done(function(answer) {
                        const questionData = {
                            ...response.context.value,
                            ...answer
                        }; // TODO check, as if the question contains feedbacks, they are not displayed.
                        Templates.render(TEMPLATES.QUESTION, questionData).then(function(html, js) {
                            identifier.html(html);
                            Templates.runTemplateJS(js);
                            jQuery(REGION.LOADING).remove();
                        }).fail(Notification.exception);
                    });
                });
                break;
            case 'pauseQuestion':
                dispatchEvent(new Event('pauseQuestion_' + response.jqid));
                break;
            case 'playQuestion':
                dispatchEvent(new Event('playQuestion_' + response.jqid));
                break;
            case 'endSession':
                Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                    let identifier = jQuery(REGION.ROOT);
                    identifier.append(html);
                    currentCuestionJqid = response.context.jqid;
                    Templates.render(TEMPLATES.QUESTION, response.context.value).then(function(html, js) {
                        identifier.html(html);
                        Templates.runTemplateJS(js);
                        jQuery(REGION.LOADING).remove();
                    }).fail(Notification.exception);
                });
                break;
            case 'teacherQuestionEnd':
                dispatchEvent(new Event('teacherQuestionEnd_' + response.jqid));
                break;
            case 'userdisconnected':
                jQuery('[data-userid="' + response.usersocketid + '"]').remove();
                countusers.html(response.count);
                break;
            default:
                break;
        }
        messageBox[0].scrollTop = messageBox[0].scrollHeight;
    };

    Sockets.prototype.webSocket.onerror = function(ev) {
        messageBox.append('<div class="system_error">Error Occurred - ' + ev.data + '</div>');
    };

    Sockets.prototype.webSocket.onclose = function() {
        let request = {
            methodname: SERVICES.SESSIONFIINISHED,
            args: {
                jqshowid: jqshowid,
                cmid: cmid
            }
        };
        Ajax.call([request])[0].done(function(response) {
            Templates.render(TEMPLATES.SESSIONFIINISHED, response).then(function(html, js) {
                jQuery(REGION.ROOT).html(html);
                Templates.runTemplateJS(js);
            }).fail(Notification.exception);
        });
    };
};

Sockets.prototype.initListeners = function() {
    addEventListener('studentQuestionEnd', () => {
        // TODO get the result and score of the user's response and send it to the socket for the teacher to receive.
        // TODO there is no way to get the note yet, it can be stored in dataSotarge with id->currentsid so that it can pick it up.
        let msg = {
            'userid': userid,
            'sid': sid,
            'usersocketid': usersocketid,
            'jqid': currentCuestionJqid,
            'answer': '', // TODO get pulsed response.
            'points': '', // TODO obtain total score.
            'oft': true, // IMPORTANT: Only for teacher.
            'action': 'studentQuestionEnd',
        };
        Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
    }, false);
};

Sockets.prototype.sendMessageSocket = function(msg) {
    this.webSocket.send(msg);
};

export const studentInitSockets = (region, port) => {
    return new Sockets(region, port);
};

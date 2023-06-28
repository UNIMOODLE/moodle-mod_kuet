"use strict";

import jQuery from 'jquery';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Ajax from 'core/ajax';
import Encryptor from 'mod_jqshow/encryptor';
import Database from 'mod_jqshow/database';

let REGION = {
    MESSAGEBOX: '#message-box',
    USERLIST: '[data-region="active-users"]',
    COUNTUSERS: '#countusers',
    TEACHERCANVASCONTENT: '[data-region="teacher-canvas-content"]', // This root.
    TEACHERCANVAS: '[data-region="teacher-canvas"]',
    TEACHERPANEL: '[data-region="teacher-panel"]',
    SESSIONCONTROLER: '[data-region="session-controller"]',
    SESSIONRESUME: '[data-region="session-resume"]',
    LISTRESULTS: '[data-region="list-results"]',
};

let ACTION = {
    BACKSESSION: '[data-action="back-session"]',
    INITSESSION: '[data-action="init-session"]',
    ENDSESSION: '[data-action="end-session"]'
};

let SERVICES = {
    ACTIVESESSION: 'mod_jqshow_activesession',
    GETALLQUESTIONS: 'mod_jqshow_session_getallquestions',
    NEXTQUESTION: 'mod_jqshow_nextquestion',
    FIRSTQUESTION: 'mod_jqshow_firstquestion',
    FINISHSESSION: 'mod_jqshow_finishsession',
    DELETERESPONSES: 'mod_jqshow_deleteresponses',
    JUMPTOQUESTION: 'mod_jqshow_jumptoquestion',
    GETSESSIONRESUME: 'mod_jqshow_getsessionresume',
    GETLISTRESULTS: 'mod_jqshow_getlistresults'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    PARTICIPANT: 'mod_jqshow/session/manual/waitingroom/participant',
    QUESTION: 'mod_jqshow/questions/encasement',
    SESSIONRESUME: 'mod_jqshow/session/sessionresume',
    LISTRESULTS: 'mod_jqshow/session/listresults'
};

let portUrl = '8080'; // It is rewritten in the constructor.

/**
 * @constructor
 * @param {String} region
 * @param {String} port
 */
function Sockets(region, port) {
    this.root = jQuery(region);
    portUrl = port;
    this.measuringSpeed(); // TODO extend to the whole mod.
    this.disableDevTools(); // TODO extend to the whole mod.
    this.initSockets();
    this.cleanMessages();
    this.initListeners();
}

Sockets.prototype.cleanMessages = function() {
    setInterval(function() {
        messageBox.find(':first-child').remove();
    }, 10000);
};

Sockets.prototype.disableDevTools = function(){
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

Sockets.prototype.measuringSpeed = function() {
    let connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (connection) {
        let typeConnection = connection.effectiveType;
        let speedMbps = connection.downlink;
        // eslint-disable-next-line no-console
        console.log("Type of Connection: " + typeConnection, "Estimated speed: " + speedMbps + " Mbps");
        if (speedMbps < 1) {
            let reason = {
                effectiveType: connection.effectiveType,
                downlink: connection.downlink
            };
            getString('lowspeed', 'mod_jqshow', reason).done((s) => {
                messageBox.append(
                    '<div class="alert alert-danger" role="alert">' + s + '</div>'
                );
            });
        }
    } else {
        // eslint-disable-next-line no-console
        console.log("Connection speed detection is not supported in this browser.");
    }
};

Sockets.prototype.backSession = function() {
    const stringkeys = [
        {key: 'backtopanelfromsession', component: 'mod_jqshow'},
        {key: 'backtopanelfromsession_desc', component: 'mod_jqshow'},
        {key: 'confirm', component: 'mod_jqshow'}
    ];
    getStrings(stringkeys).then((langStrings) => {
        return ModalFactory.create({
            title: langStrings[0],
            body: langStrings[1],
            type: ModalFactory.types.SAVE_CANCEL
        }).then(modal => {
            modal.setSaveButtonText(langStrings[2]);
            modal.getRoot().on(ModalEvents.save, () => {
                let request = {
                    methodname: SERVICES.ACTIVESESSION,
                    args: {
                        cmid: cmid,
                        sessionid: sid
                    }
                };
                Ajax.call([request])[0].done(function() {
                    window.location.replace(M.cfg.wwwroot + '/mod/jqshow/view.php?id=' + cmid);
                }).fail(Notification.exception);
            });
            modal.getRoot().on(ModalEvents.hidden, () => {
                modal.destroy();
            });
            return modal;
        });
    }).done(function(modal) {
        modal.show();
        // eslint-disable-next-line no-restricted-globals
    }).fail(Notification.exception);
};

/* ****************** */

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
let db = null;
let questionsJqids = [];
let waitingRoom = true;
let currentQuestionJqid = null;
let nextQuestionJqid = null;

Sockets.prototype.initSockets = function() {
    userid = this.root[0].dataset.userid;
    username = this.root[0].dataset.username;
    userimage = this.root[0].dataset.userimage;
    cmid = this.root[0].dataset.cmid;
    sid = this.root[0].dataset.sid;
    messageBox = this.root.find(REGION.MESSAGEBOX);
    countusers = this.root.find(REGION.COUNTUSERS);
    let that = this;
    this.root.find(ACTION.BACKSESSION).on('click', this.backSession);

    db = Database.initDb(sid, userid);
    Sockets.prototype.webSocket = new WebSocket(
        'wss://' + M.cfg.wwwroot.replace(/^https?:\/\//, '') + ':' + portUrl + '/jqshow'
    );

    Sockets.prototype.webSocket.onopen = function() { // Waitingroom.
        /* The first and second questions are obtained.
        When the teacher clicks on init session, the teacher will send the first question over the socket to all
        students, and will get the 3rd question. When a student answers a question, a service will be called to save the answer
        and progress, and the teacher will be informed via the socket. When the teacher clicks on next question,
        the 2nd question will be sent by socket to all students, and the 4th question will be obtained.
        The questions will be stored in the teacher's data storage, you can even store the user's answers so that you
        don't have to call a service at the end for the ranking.
        */

        let request = {
            methodname: SERVICES.FIRSTQUESTION,
            args: {
                cmid: cmid,
                sessionid: sid
            }
        };
        Ajax.call([request])[0].done(function(firstquestion) {
            let data = {
                jqid: firstquestion.jqid,
                value: firstquestion
            };
            db.add('questions', data);
            questionsJqids.push(firstquestion.jqid);
            currentQuestionJqid = firstquestion.jqid;
            that.setCurrentQuestion(firstquestion.jqid);
            that.getNextQuestion(firstquestion.jqid);
            that.root.find(ACTION.INITSESSION).removeClass('disabled');
            that.root.find(ACTION.INITSESSION).on('click', that.initSession);
            that.root.find(ACTION.ENDSESSION).on('click', that.endSession);
        }).fail(Notification.exception);
    };

    Sockets.prototype.webSocket.onmessage = function(ev) {
        // TODO Refactor.
        let msgDecrypt = Encryptor.decrypt(ev.data);
        let response = JSON.parse(msgDecrypt); // PHP sends Json data.
        switch (response.action) {
            case 'connect':
                // The server has returned the connected status, it is time to identify yourself.
                if (response.usersocketid !== undefined) {
                    usersocketid = response.usersocketid;
                    let msg = {
                        'userid': userid,
                        'name': username,
                        'pic': userimage, // TODO encrypt.
                        'isteacher': true,
                        'cmid': cmid,
                        'sid': sid,
                        'usersocketid': usersocketid,
                        'action': 'newuser'
                    };
                    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
                }
                break;
            case 'newuser': {
                let identifier = jQuery(REGION.USERLIST);
                let data = response.students;
                identifier.html('');
                if (waitingRoom === false) {
                    Sockets.prototype.normalizeUser(response.usersocketid);
                }
                jQuery.each(data, function(i, student) {
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
            case 'studentQuestionEnd':
                messageBox.append('<div>' + response.message + '</div>');
                break;
            case 'userdisconnected':
                jQuery('[data-userid="' + response.usersocketid + '"]').remove();
                countusers.html(response.count);
                messageBox.append('<div>' + response.message + '</div>');
                break;
            case 'alreadyteacher':
                messageBox.append(
                    '<div class="alert alert-danger" role="alert">' + response.message + '</div>'
                );
                break;
            case 'pauseQuestion':
                dispatchEvent(new Event('pauseQuestion_' + response.jqid));
                break;
            case 'playQuestion':
                dispatchEvent(new Event('playQuestion_' + response.jqid));
                break;
            case 'showAnswers':
                dispatchEvent(new Event('showAnswers_' + response.jqid));
                break;
            case 'hideAnswers':
                dispatchEvent(new Event('hideAnswers_' + response.jqid));
                break;
            case 'showStatistics':
                dispatchEvent(new Event('showStatistics_' + response.jqid));
                break;
            case 'hideStatistics':
                dispatchEvent(new Event('hideStatistics_' + response.jqid));
                break;
            case 'showFeedback':
                dispatchEvent(new Event('showFeedback_' + response.jqid));
                break;
            case 'hideFeedback':
                dispatchEvent(new Event('hideFeedback_' + response.jqid));
                break;
            default:
                break;
        }
        messageBox[0].scrollTop = messageBox[0].scrollHeight;
    };

    Sockets.prototype.webSocket.onerror = function() {
        getString('system_error', 'mod_jqshow').done((s) => {
            messageBox.append(
                '<div class="alert alert-danger" role="alert">' + s + '</div>'
            );
        });
    };

    Sockets.prototype.webSocket.onclose = function(ev) {
        let reason = {
            reason: ev.reason,
            code: ev.code
        };
        getString('connection_closed', 'mod_jqshow', reason).done((s) => {
            jQuery(REGION.TEACHERCANVASCONTENT).prepend(
                '<div class="alert alert-danger" role="alert">' + s + '</div>'
            );
        });
    };
};

Sockets.prototype.initListeners = function() {
    let that = this;
    addEventListener('nextQuestion', () => {
        that.nextQuestion();
    }, false);
    addEventListener('pauseQuestionSelf', () => {
        that.pauseQuestion();
    }, false);
    addEventListener('playQuestionSelf', () => {
        that.playQuestion();
    }, false);
    addEventListener('resendSelf', () => {
        that.resendSelf();
    }, false);
    addEventListener('jumpTo', (e) => {
        that.jumpTo(e.detail.jumpTo);
    }, false);
    addEventListener('teacherQuestionEndSelf', () => {
        that.questionEnd();
    }, false);
    addEventListener('showAnswersSelf', () => {
        that.showAnswers();
    }, false);
    addEventListener('hideAnswersSelf', () => {
        that.hideAnswers();
    }, false);
    addEventListener('showStatisticsSelf', () => {
        that.showStatistics();
    }, false);
    addEventListener('hideStatisticsSelf', () => {
        that.hideStatistics();
    }, false);
    addEventListener('showFeedbackSelf', () => {
        that.showFeedback();
    }, false);
    addEventListener('hideFeedbackSelf', () => {
        that.hideFeedback();
    }, false);
    addEventListener('endSession', () => {
        that.endSession();
    }, {once: true});
};

Sockets.prototype.setCurrentQuestion = function(currentQuestion) {
    let data = {
        state: 'currentQuestion',
        value: currentQuestion
    };
    db.update('statequestions', data);
};

Sockets.prototype.setNextQuestion = function(nextQuestion) {
    let data = {
        state: 'nextQuestion',
        value: nextQuestion
    };
    db.update('statequestions', data);
};

Sockets.prototype.setEndSession = function(endData) {
    db.delete('statequestions', 'nextQuestion');
    db.delete('statequestions', 'currentQuestion');
    let data = {
        state: 'endSession',
        value: endData
    };
    db.update('statequestions', data);
};

Sockets.prototype.getNextQuestion = function(jqid) {
    let that = this;
    let request = {
        methodname: SERVICES.NEXTQUESTION,
        args: {
            cmid: cmid,
            sessionid: sid,
            jqid: jqid,
            manual: true
        }
    };
    nextQuestionJqid = null;
    Ajax.call([request])[0].done(function(nextquestion) {
        if (nextquestion.endsession !== true) {
            let data = {
                jqid: nextquestion.jqid,
                value: nextquestion
            };
            db.add('questions', data);
            nextQuestionJqid = nextquestion.jqid;
            that.setNextQuestion(nextquestion.jqid);
        } else {
            // End session.
            nextQuestionJqid = null;
            that.setEndSession(nextquestion);
        }
    }).fail(Notification.exception);
};

Sockets.prototype.initSession = function() {
    db.delete('statequestions', 'endSession');
    const stringkeys = [
        {key: 'init_session', component: 'mod_jqshow'},
        {key: 'init_session_desc', component: 'mod_jqshow'},
        {key: 'confirm', component: 'mod_jqshow'},
        {key: 'sessionstarted', component: 'mod_jqshow'},
        {key: 'sessionstarted_info', component: 'mod_jqshow'}
    ];
    getStrings(stringkeys).then((langStrings) => {
        return ModalFactory.create({
            title: langStrings[0],
            body: langStrings[1],
            type: ModalFactory.types.SAVE_CANCEL
        }).then(modal => {
            modal.setSaveButtonText(langStrings[2]);
            modal.getRoot().on(ModalEvents.save, () => {
                let firstQuestion = db.get('questions', currentQuestionJqid);
                firstQuestion.onsuccess = function() {
                    let msg = {
                        'action': 'question',
                        'sid': sid,
                        'context': firstQuestion.result
                    };
                    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
                    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                        Sockets.prototype.initPanel();
                        let identifier = jQuery(REGION.TEACHERCANVAS);
                        identifier.append(html);
                        currentQuestionJqid = firstQuestion.result.jqid;
                        firstQuestion.result.value.isteacher = true;
                        Templates.render(TEMPLATES.QUESTION, firstQuestion.result.value).then(function(html, js) {
                            identifier.html(html);
                            jQuery(REGION.TEACHERCANVASCONTENT).find('.content-title h2').html(langStrings[3]);
                            jQuery(REGION.TEACHERCANVASCONTENT).find('.content-title small').html(langStrings[4]);
                            jQuery(ACTION.BACKSESSION).remove();
                            jQuery(ACTION.INITSESSION).remove();
                            jQuery(ACTION.ENDSESSION).removeClass('hidden').removeClass('disabled');
                            waitingRoom = false;
                            Templates.runTemplateJS(js);
                            jQuery(REGION.LOADING).remove();
                        }).fail(Notification.exception);
                    });
                };
            });
            modal.getRoot().on(ModalEvents.hidden, () => {
                modal.destroy();
            });
            return modal;
        });
    }).done(function(modal) {
        modal.show();
        // eslint-disable-next-line no-restricted-globals
    }).fail(Notification.exception);
};

Sockets.prototype.initPanel = function() {
    let requestResume = {
        methodname: SERVICES.GETSESSIONRESUME,
        args: {
            sid: sid,
            cmid: cmid,
        }
    };
    Ajax.call([requestResume])[0].done(function(responseResume) {
        Templates.render(TEMPLATES.SESSIONRESUME, responseResume).then(function(html) {
            jQuery(REGION.SESSIONRESUME).append(html);
        });
    });
    setInterval(function() {
        let requestResults = {
            methodname: SERVICES.GETLISTRESULTS,
            args: {
                sid: sid,
                cmid: cmid,
            }
        };
        Ajax.call([requestResults])[0].done(function(responseResults) {
            Templates.render(TEMPLATES.LISTRESULTS, responseResults).then(function(html) {
                jQuery(REGION.LISTRESULTS).html(html);
            });
        });
    }, 20000);
    jQuery(REGION.TEACHERPANEL).removeClass('d-none');
};

Sockets.prototype.endSession = function() {
    const stringkeys = [
        {key: 'end_session', component: 'mod_jqshow'},
        {key: 'end_session_manual_desc', component: 'mod_jqshow'},
        {key: 'confirm', component: 'mod_jqshow'},
        {key: 'end_session_error', component: 'mod_jqshow'}
    ];
    getStrings(stringkeys).then((langStrings) => {
        return ModalFactory.create({
            title: langStrings[0],
            body: langStrings[1],
            type: ModalFactory.types.SAVE_CANCEL
        }).then(modal => {
            modal.setSaveButtonText(langStrings[2]);
            modal.getRoot().on(ModalEvents.save, () => {
                let request = {
                    methodname: SERVICES.FINISHSESSION,
                    args: {
                        cmid: cmid,
                        sessionid: sid
                    }
                };
                Ajax.call([request])[0].done(function(response) {
                    if (response.finished === true) {
                        let deleteDb = db.deleteDatabase();
                        deleteDb.onerror = function(event) {
                            // eslint-disable-next-line no-console
                            console.error("Error deleting database.", event);
                        };
                        deleteDb.onsuccess = function() {
                            window.location.replace(M.cfg.wwwroot + '/mod/jqshow/view.php?id=' + cmid);
                        };
                    } else {
                        Notification.alert('Error', langStrings[3], langStrings[2]);
                    }
                }).fail(Notification.exception);
            });
            modal.getRoot().on(ModalEvents.hidden, () => {
                modal.destroy();
            });
            return modal;
        });
    }).done(function(modal) {
        modal.show();
        // eslint-disable-next-line no-restricted-globals
    }).fail(Notification.exception);
};

Sockets.prototype.nextQuestion = function() {
    let that = this;
    if (nextQuestionJqid === null) {
        this.getNextQuestion(currentQuestionJqid);
    }
    let nextQuestion = db.get('statequestions', 'nextQuestion');
    nextQuestion.onsuccess = function() {
        if (nextQuestion.result !== undefined) {
            let nextQuestionData = db.get('questions', nextQuestion.result.value);
            nextQuestionData.onsuccess = function() {
                let msg = {
                    'action': 'question',
                    'sid': sid,
                    'context': nextQuestionData.result
                };
                Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
                Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                    let identifier = jQuery(REGION.TEACHERCANVAS);
                    identifier.append(html);
                    currentQuestionJqid = nextQuestionData.result.jqid;
                    // The following question is marked as current.
                    that.setCurrentQuestion(nextQuestionData.result.jqid);
                    // And get the next question to store it.
                    that.getNextQuestion(nextQuestionData.result.jqid);
                    nextQuestionData.result.value.isteacher = true;
                    Templates.render(TEMPLATES.QUESTION, nextQuestionData.result.value).then(function(html, js) {
                        identifier.html(html);
                        Templates.runTemplateJS(js);
                        jQuery(REGION.LOADING).remove();
                    }).fail(Notification.exception);
                });
            };
        } else {
            let endSession = db.get('statequestions', 'endSession');
            endSession.onsuccess = function() {
                let msg = {
                    'action': 'endSession',
                    'sid': sid,
                    'context': endSession.result
                };
                Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
                Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                    let identifier = jQuery(REGION.TEACHERCANVAS);
                    identifier.append(html);
                    currentQuestionJqid = null;
                    that.setEndSession(endSession.result);
                    endSession.result.value.isteacher = true;
                    Templates.render(TEMPLATES.QUESTION, endSession.result.value).then(function(html, js) {
                        identifier.html(html);
                        Templates.runTemplateJS(js);
                        jQuery(REGION.LOADING).remove();
                    }).fail(Notification.exception);
                });
            };
        }
    };
};

Sockets.prototype.pauseQuestion = function() {
    let msg = {
        'action': 'pauseQuestion',
        'sid': sid,
        'jqid': currentQuestionJqid
    };
    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
};

Sockets.prototype.playQuestion = function() {
    let msg = {
        'action': 'playQuestion',
        'sid': sid,
        'jqid': currentQuestionJqid
    };
    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
};

Sockets.prototype.resendSelf = function() {
    let request = {
        methodname: SERVICES.DELETERESPONSES,
        args: {
            cmid: cmid,
            sessionid: sid,
            jqid: currentQuestionJqid
        }
    };
    Ajax.call([request])[0].done(function(response) {
        if (response.deleted === true) {
            let currentQuestionData = db.get('questions', currentQuestionJqid);
            currentQuestionData.onsuccess = function() {
                let msg = {
                    'action': 'question',
                    'sid': sid,
                    'context': currentQuestionData.result
                };
                Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
                Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                    let identifier = jQuery(REGION.TEACHERCANVAS);
                    identifier.append(html);
                    currentQuestionData.result.value.isteacher = true;
                    Templates.render(TEMPLATES.QUESTION, currentQuestionData.result.value).then(function(html, js) {
                        identifier.html(html);
                        Templates.runTemplateJS(js);
                        jQuery(REGION.LOADING).remove();
                    }).fail(Notification.exception);
                });
            };
        }
    }).fail(Notification.exception);
};

Sockets.prototype.jumpTo = function(questionNumber) {
    let that = this;
    let request = {
        methodname: SERVICES.JUMPTOQUESTION,
        args: {
            cmid: cmid,
            sessionid: sid,
            position: questionNumber,
            manual: true
        }
    };
    nextQuestionJqid = null;
    Ajax.call([request])[0].done(function(question) {
        let data = {
            jqid: question.jqid,
            value: question
        };
        db.add('questions', data);
        currentQuestionJqid = question.jqid;
        that.setCurrentQuestion(question.jqid);
        that.getNextQuestion(question.jqid);
        let msg = {
            'action': 'question',
            'sid': sid,
            'context': data
        };
        Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
        Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
            let identifier = jQuery(REGION.TEACHERCANVAS);
            identifier.append(html);
            question.isteacher = true;
            Templates.render(TEMPLATES.QUESTION, question).then(function(html, js) {
                identifier.html(html);
                Templates.runTemplateJS(js);
                jQuery(REGION.LOADING).remove();
            }).fail(Notification.exception);
        });
    }).fail(Notification.exception);
};

Sockets.prototype.questionEnd = function() {
    let msg = {
        'action': 'teacherQuestionEnd',
        'sid': sid,
        'jqid': currentQuestionJqid
    };
    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
};

Sockets.prototype.showAnswers = function() {
    let msg = {
        'action': 'showAnswers',
        'sid': sid,
        'jqid': currentQuestionJqid
    };
    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
};

Sockets.prototype.hideAnswers = function() {
    let msg = {
        'action': 'hideAnswers',
        'sid': sid,
        'jqid': currentQuestionJqid
    };
    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
};

Sockets.prototype.showStatistics = function() {
    let msg = {
        'action': 'showStatistics',
        'sid': sid,
        'jqid': currentQuestionJqid
    };
    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
};

Sockets.prototype.hideStatistics = function() {
    let msg = {
        'action': 'hideStatistics',
        'sid': sid,
        'jqid': currentQuestionJqid
    };
    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
};

Sockets.prototype.showFeedback = function() {
    let msg = {
        'action': 'showFeedback',
        'sid': sid,
        'jqid': currentQuestionJqid
    };
    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
};

Sockets.prototype.hideFeedback = function() {
    let msg = {
        'action': 'hideFeedback',
        'sid': sid,
        'jqid': currentQuestionJqid
    };
    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
};

Sockets.prototype.normalizeUser = function(usersocketid) {
    let currentQuestion = db.get('statequestions', 'currentQuestion');
    currentQuestion.onsuccess = function() {
        let currentQuestiondata = db.get('questions', currentQuestion.result.value);
        currentQuestiondata.onsuccess = function() {
            let msg = {
                'action': 'normalizeUser',
                'sid': sid,
                'ofs': true, // Only for student.
                'usersocketid': usersocketid,
                'context': currentQuestiondata.result
            };
            Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
        };
    };
};

Sockets.prototype.sendMessageSocket = function(msg) {
    this.webSocket.send(msg);
};

export const teacherInitSockets = (region, port) => {
    return new Sockets(region, port);
};

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

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos

/**
 *
 * @module    mod_jqshow/teachersockets
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
import mEvent from 'core/event';

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
    RACERESULTS: '[data-region="race-results"]',
    SWITCHS: '.showhide-action',
    ESPECIALS: '.special-action',
    VOTEIMPROVISE: '.special-action.vote-question',
    CLOUDTAGS: '.cloud',
    IMPROVISE: '[data-region="teacher-improvise"]',
    IMPROVISESTATEMENT: '.improvise-form #statement',
    IMPROVISEREPLY: '.improvise-form #reply_improvise',
    TAGSCONTENT: '[data-region="tags-content"]',
};

let ACTION = {
    BACKSESSION: '[data-action="back-session"]',
    INITSESSION: '[data-action="init-session"]',
    ENDSESSION: '[data-action="end-session"]',
    CLOSEIMPROVISE: '[data-action="close-improvise"]',
    SUBMITIMPROVISE: '[data-action="submit-improvise"]',
    NEXT: '[data-action="next"]',
    VOTE: '[data-action="vote"]',
    JUMP: '[data-action="jump"]',
    FINISHQUESTION: '[data-action="finishquestion"]',
};

let SERVICES = {
    ACTIVESESSION: 'mod_jqshow_activesession',
    NEXTQUESTION: 'mod_jqshow_nextquestion',
    FIRSTQUESTION: 'mod_jqshow_firstquestion',
    FINISHSESSION: 'mod_jqshow_finishsession',
    DELETERESPONSES: 'mod_jqshow_deleteresponses',
    JUMPTOQUESTION: 'mod_jqshow_jumptoquestion',
    GETSESSIONRESUME: 'mod_jqshow_getsessionresume',
    GETLISTRESULTS: 'mod_jqshow_getlistresults',
    GETGROUPLISTRESULTS: 'mod_jqshow_getgrouplistresults',
    GETQUESTIONSTATISTICS: 'mod_jqshow_getquestionstatistics',
    GETSESSIONCONFIG: 'mod_jqshow_getsession',
    GETPROVISIONALRANKING: 'mod_jqshow_getprovisionalranking',
    GETFINALRANKING: 'mod_jqshow_getfinalranking',
    GETRACERESULTS: 'mod_jqshow_getraceresults'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    PARTICIPANT: 'mod_jqshow/session/manual/waitingroom/participant',
    GROUPPARTICIPANT: 'mod_jqshow/session/manual/waitingroom/groupparticipant',
    QUESTION: 'mod_jqshow/questions/encasement',
    SESSIONRESUME: 'mod_jqshow/session/sessionresume',
    LISTRESULTS: 'mod_jqshow/session/listresults',
    PROVISIONALRANKING: 'mod_jqshow/ranking/provisional',
    RACERESULTS: 'mod_jqshow/session/raceresults'
};

let socketUrl = '';
let portUrl = '8080'; // It is rewritten in the constructor.
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
let sessionMode = null;
let showRankingBetweenQuestions = false;
let showRankingBetweenQuestionsSwitch = false;
let showRankingFinal = false;
let groupMode = false;
let currentQuestionDataForRace = {};
let cloudTags = [];
let voting = false;

/**
 * @constructor
 * @param {String} region
 * @param {String} socketurl
 * @param {String} port
 * @param {String} sessionmode
 * @param {Boolean} groupmode
 */
function Sockets(region, socketurl, port, sessionmode, groupmode) {
    this.root = jQuery(region);
    socketUrl = socketurl;
    portUrl = port;
    userid = this.root[0].dataset.userid;
    username = this.root[0].dataset.username;
    userimage = this.root[0].dataset.userimage;
    cmid = this.root[0].dataset.cmid;
    sid = this.root[0].dataset.sid;
    messageBox = this.root.find(REGION.MESSAGEBOX);
    countusers = this.root.find(REGION.COUNTUSERS);
    groupMode = groupmode;
    sessionMode = sessionmode;
    switch (sessionMode) {
        case 'inactive_manual':
        default:
            showRankingBetweenQuestions = false;
            showRankingBetweenQuestionsSwitch = false;
            showRankingFinal = false;
            break;
        case 'podium_manual':
        case 'race_manual': {
            let request = {
                methodname: SERVICES.GETSESSIONCONFIG,
                args: {
                    sid: sid,
                    cmid: cmid
                }
            };
            Ajax.call([request])[0].done(function(response) {
                if (response.session.showgraderanking === 1) {
                    showRankingBetweenQuestions = true;
                }
                if (response.session.showfinalgrade === 1) {
                    showRankingFinal = true;
                }
            }).fail(Notification.exception);
            break;
        }
    }
    this.measuringSpeed();
    this.disableDevTools();
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
        console.error("Connection speed detection is not supported in this browser.");
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

Sockets.prototype.initSockets = function() {
    let that = this;
    this.root.find(ACTION.BACKSESSION).on('click', this.backSession);
    this.root.find(ACTION.CLOSEIMPROVISE).on('click', this.closeImprovise);
    this.root.find(ACTION.SUBMITIMPROVISE).on('click', this.submitImprovise);

    db = Database.initDb(sid, userid);
    let normalizeSocketUrl = Sockets.prototype.normalizeSocketUrl(socketUrl, portUrl);
    Sockets.prototype.webSocket = new WebSocket(normalizeSocketUrl);

    Sockets.prototype.webSocket.onopen = function() { // Waitingroom.
        /* The first and second questions are obtained.
        When the teacher clicks on init session, the teacher will send the first question over the socket to all
        students, and will get the 3rd question. When a student answers a question, a service will be called to save the answer
        and progress, and the teacher will be informed via the socket. When the teacher clicks on next question,
        the 2nd question will be sent by socket to all students, and the 3th question will be obtained.
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
                        'pic': userimage,
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
            case 'newgroup': {
                let participantshtml = jQuery(REGION.USERLIST);
                let grouplist = response.groups;
                participantshtml.html('');
                jQuery.each(grouplist, function (i, group) {
                    let templateContext = {
                        'usersocketid': group.usersocketid,
                        'groupimage': group.picture,
                        'groupid': group.groupid,
                        'name': group.name,
                        'numgroupusers': group.numgroupusers,
                    };
                    Templates.render(TEMPLATES.GROUPPARTICIPANT, templateContext).then(function(html) {
                        participantshtml.append(html);
                    }).fail(Notification.exception);
                });
                countusers.html(response.count);
                break;
            }
            case 'countusers':
                countusers.html(response.count);
                break;
            case 'studentQuestionEnd':
                /*messageBox.append('<div>' + response.message + '</div>');*/
                if (sessionMode === 'race_manual') {
                    Sockets.prototype.raceResults();
                }
                break;
            case 'userdisconnected':
                if (groupMode != '1') {
                    jQuery('[data-userid="' + response.usersocketid + '"]').remove();
                    countusers.html(response.count);
                }
                messageBox.append('<div>' + response.message + '</div>');
                break;
            case 'groupdisconnected':
                jQuery('.participants').find('[data-groupid="' + response.groupid + '"]').remove();
                countusers.html(response.count);
                messageBox.append('<div>' + response.message + '</div>');
                break;
            case 'groupmemberdisconnected':
                jQuery('.participants').find('[data-groupid="' + response.groupid + '"]')
                    .find('.numgroupusers').html('(' + response.count + ')');
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
            case 'ImproviseStudentTag':
                Sockets.prototype.manageCloudTags(response.improvisereply);
                Sockets.prototype.printNewTag();
                break;
            case 'StudentVotedTag':
                if (voting === true) {
                    Sockets.prototype.manageTagsVotes(response.votedtag);
                    Sockets.prototype.printNewTag();
                }
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
        that.root.find(ACTION.INITSESSION).addClass('disabled');
        that.root.find(ACTION.INITSESSION).off('click');
        that.root.find(ACTION.ENDSESSION).off('click');
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
        that.root.find(ACTION.INITSESSION).addClass('disabled');
        that.root.find(ACTION.INITSESSION).off('click');
        that.root.find(ACTION.ENDSESSION).off('click');
    };
};

Sockets.prototype.normalizeSocketUrl = function(socketUrl, port) {
    let jsUrl = new URL(socketUrl);
    if (jsUrl.protocol === 'https:') {
        jsUrl.port = port;
        jsUrl.protocol = 'wss:';
        if (jsUrl.pathname === '/') {
            jsUrl.pathname = jsUrl.pathname + 'jqshow';
        } else {
            jsUrl.pathname = jsUrl.pathname + '/jqshow';
        }
        return jsUrl.toString();
    }
    return '';
};

Sockets.prototype.initListeners = function() {
    let that = this;
    addEventListener('nextQuestion', () => {
        switch (sessionMode) {
            case 'inactive_programmed':
            case 'inactive_manual':
            default:
                that.nextQuestion();
                break;
            case 'podium_manual':
            case 'podium_programmed':
            case 'race_manual':
            case 'race_programmed':
                that.manageNext();
                break;
        }
        // TODO send tag cloud to a service to persist it in DB for retrieval in reports..
        cloudTags = [];
        voting = false;
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
        if (sessionMode === 'race_manual') {
            currentQuestionDataForRace.isteacher = true;
            Templates.render(TEMPLATES.QUESTION, currentQuestionDataForRace).then(function(html, js) {
                let identifier = jQuery(REGION.TEACHERCANVAS);
                identifier.html(html);
                Templates.runTemplateJS(js);
                jQuery(REGION.SWITCHS).removeClass('disabled');
                jQuery(REGION.ESPECIALS).removeClass('disabled');
                setTimeout(() => {
                    that.questionEnd();
                    jQuery(REGION.SWITCHS).removeClass('disabled');
                    jQuery(REGION.IMPROVISE).removeClass('disabled');
                    jQuery(ACTION.FINISHQUESTION).addClass('d-none');
                    jQuery(ACTION.NEXT).removeClass('d-none');
                    jQuery(ACTION.VOTE).addClass('disabled');
                    jQuery(ACTION.JUMP).addClass('disabled');
                    mEvent.notifyFilterContentUpdated(document.querySelector(REGION.TEACHERCANVAS));
                }, 300);
            }).fail(Notification.exception);
        } else {
            that.questionEnd();
        }
        cloudTags = [];
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
    addEventListener('improvise', () => {
        that.improvise();
    }, false);
    addEventListener('initVote', () => {
        that.vote();
    }, false);
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
    let data = {
        state: 'endSession',
        value: endData
    };
    db.update('statequestions', data);
    showRankingBetweenQuestionsSwitch = false;
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
                        currentQuestionDataForRace = firstQuestion.result.value;
                        if (sessionMode === 'podium_manual' || sessionMode === 'inactive_manual') {
                            Templates.render(TEMPLATES.QUESTION, firstQuestion.result.value).then(function(html, js) {
                                identifier.html(html);
                                Templates.runTemplateJS(js);
                                jQuery(REGION.LOADING).remove();
                                mEvent.notifyFilterContentUpdated(document.querySelector(REGION.TEACHERCANVAS));
                            }).fail(Notification.exception);
                        }
                        if (sessionMode === 'race_manual') {
                            Sockets.prototype.raceResults();
                        }
                        jQuery(REGION.TEACHERCANVASCONTENT).find('.content-title h2').html(langStrings[3]);
                        jQuery(REGION.TEACHERCANVASCONTENT).find('.content-title small').html(langStrings[4]);
                        jQuery(ACTION.BACKSESSION).remove();
                        jQuery(ACTION.INITSESSION).remove();
                        jQuery(ACTION.ENDSESSION).removeClass('hidden').removeClass('disabled');
                        waitingRoom = false;
                        if (showRankingBetweenQuestions) {
                            showRankingBetweenQuestionsSwitch = true;
                        }
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
    let methodname = SERVICES.GETLISTRESULTS;
    if (groupMode === true) {
        methodname = SERVICES.GETGROUPLISTRESULTS;
    }
    setInterval(function() {
        let requestResults = {
            methodname: methodname,
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
                currentQuestionDataForRace = nextQuestionData.result.value;
                Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
                let removeEvents = new CustomEvent('removeEvents');
                dispatchEvent(removeEvents);
                Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                    let identifier = jQuery(REGION.TEACHERCANVAS);
                    identifier.append(html);
                    currentQuestionJqid = nextQuestionData.result.jqid;
                    // The following question is marked as current.
                    that.setCurrentQuestion(nextQuestionData.result.jqid);
                    // And get the next question to store it.
                    that.getNextQuestion(nextQuestionData.result.jqid);
                    if (sessionMode === 'podium_manual' || sessionMode === 'inactive_manual') {
                        nextQuestionData.result.value.isteacher = true;
                        // Render questin for teacher.
                        Templates.render(TEMPLATES.QUESTION, nextQuestionData.result.value).then(function(html, js) {
                            identifier.html(html);
                            Templates.runTemplateJS(js);
                            mEvent.notifyFilterContentUpdated(document.querySelector(REGION.TEACHERCANVAS));
                            jQuery(REGION.LOADING).remove();
                        }).fail(Notification.exception);
                    }
                    if (sessionMode === 'race_manual') {
                        Sockets.prototype.raceResults();
                    }
                });
            };
        } else {
            let endSession = db.get('statequestions', 'endSession');
            if (showRankingFinal === false) {
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
                        db.delete('statequestions', 'currentQuestion');
                        that.setEndSession(endSession.result);
                        endSession.result.value.isteacher = true;
                        Templates.render(TEMPLATES.QUESTION, endSession.result.value).then(function(html, js) {
                            identifier.html(html);
                            Templates.runTemplateJS(js);
                            jQuery(REGION.LOADING).remove();
                        }).fail(Notification.exception);
                    });
                };
            } else {
                endSession.onsuccess = function() {
                    let request = {
                        methodname: SERVICES.GETFINALRANKING,
                        args: {
                            sid: sid,
                            cmid: cmid
                        }
                    };
                    Ajax.call([request])[0].done(function(finalRanking) {
                        endSession.result.value = finalRanking;
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
                            db.delete('statequestions', 'currentQuestion');
                            that.setEndSession(endSession.result);
                            endSession.result.value.isteacher = true;
                            Templates.render(TEMPLATES.QUESTION, endSession.result.value).then(function(html, js) {
                                identifier.html(html);
                                Templates.runTemplateJS(js);
                                jQuery(REGION.LOADING).remove();
                            }).fail(Notification.exception);
                        });
                    }).fail(Notification.exception);
                };
            }
        }
    };
};

Sockets.prototype.raceResults = function() {
    let identifier = jQuery(REGION.TEACHERCANVAS);
    let request = {
        methodname: SERVICES.GETRACERESULTS,
        args: {
            sid: sid,
            cmid: cmid
        }
    };
    Ajax.call([request])[0].done(function(response) {
        response.raceresults = true;
        response.isteacher = true;
        response.programmedmode = false;
        response.sessionprogress = currentQuestionDataForRace.sessionprogress;
        // eslint-disable-next-line camelcase
        response.question_index_string = currentQuestionDataForRace.question_index_string;
        response.numquestions = currentQuestionDataForRace.numquestions;
        Templates.render(TEMPLATES.QUESTION, response).then((html, js) => {
            let scrollUsersTop = 0;
            let scrollQuestionsLeft = 0;
            if (document.querySelector('#content-users') !== null) {
                scrollUsersTop = document.querySelector('#content-users').scrollTop;
                scrollQuestionsLeft = document.querySelector('#questions-list').scrollLeft;
            }
            identifier.html(html);
            Templates.runTemplateJS(js);
            let newScrollUsers = document.querySelector('#content-users');
            let newScrollQuestions = document.querySelector('#questions-list');
            let scrollUsers = document.querySelector('#content-users');
            let questions = document.querySelectorAll('.content-responses');
            scrollUsers.addEventListener('scroll', function() {
                questions.forEach((question) => {
                    question.scrollTop = scrollUsers.scrollTop;
                });
            }, {passive: true});
            newScrollUsers.scrollTop = scrollUsersTop;
            newScrollQuestions.scrollLeft = scrollQuestionsLeft;
        }).fail(Notification.exception);
    }).fail(Notification.exception);
};

Sockets.prototype.manageNext = function() {
    let that = this;
    if (showRankingBetweenQuestions === false) {
        that.nextQuestion();
    } else {
        if (showRankingBetweenQuestionsSwitch === false) {
            that.nextQuestion();
            showRankingBetweenQuestionsSwitch = true;
        } else {
            let request = {
                methodname: SERVICES.GETPROVISIONALRANKING,
                args: {
                    sid: sid,
                    cmid: cmid,
                    jqid: currentQuestionJqid
                }
            };
            Ajax.call([request])[0].done(function(provisionalRanking) {
                let msg = {
                    'action': 'ranking',
                    'sid': sid,
                    'context': provisionalRanking
                };
                Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
                showRankingBetweenQuestionsSwitch = false;
                Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                    let identifier = jQuery(REGION.TEACHERCANVAS);
                    identifier.append(html);
                    provisionalRanking.isteacher = true;
                    Templates.render(TEMPLATES.PROVISIONALRANKING, provisionalRanking).then(function(html, js) {
                        identifier.html(html);
                        Templates.runTemplateJS(js);
                        jQuery(REGION.LOADING).remove();
                    }).fail(Notification.exception);
                });
            }).fail(Notification.exception);
        }
    }
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
    let removeEvents = new CustomEvent('removeEvents');
    dispatchEvent(removeEvents);
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
                showRankingBetweenQuestionsSwitch = true;
                currentQuestionDataForRace = currentQuestionData.result.value;
                if (sessionMode === 'race_manual') {
                    Sockets.prototype.raceResults();
                } else {
                    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                        let identifier = jQuery(REGION.TEACHERCANVAS);
                        identifier.append(html);
                        currentQuestionData.result.value.isteacher = true;
                        Templates.render(TEMPLATES.QUESTION, currentQuestionData.result.value).then(function(html, js) {
                            identifier.html(html);
                            Templates.runTemplateJS(js);
                            mEvent.notifyFilterContentUpdated(document.querySelector(REGION.TEACHERCANVAS));
                            jQuery(REGION.LOADING).remove();
                        }).fail(Notification.exception);
                    });
                }
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
    let removeEvents = new CustomEvent('removeEvents');
    dispatchEvent(removeEvents);
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
        showRankingBetweenQuestionsSwitch = true;
        Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
            let identifier = jQuery(REGION.TEACHERCANVAS);
            identifier.append(html);
            question.isteacher = true;
            currentQuestionDataForRace = question;
            if (sessionMode === 'race_manual') {
                Sockets.prototype.raceResults();
            } else {
                Templates.render(TEMPLATES.QUESTION, question).then(function(html, js) {
                    identifier.html(html);
                    Templates.runTemplateJS(js);
                    mEvent.notifyFilterContentUpdated(document.querySelector(REGION.TEACHERCANVAS));
                    jQuery(REGION.LOADING).remove();
                }).fail(Notification.exception);
            }
        });
    }).fail(Notification.exception);
};

Sockets.prototype.questionEnd = function() {
    let request = {
        methodname: SERVICES.GETQUESTIONSTATISTICS,
        args: {
            sid: sid,
            jqid: currentQuestionJqid
        }
    };
    Ajax.call([request])[0].done(function(response) {
        let msg = {
            'action': 'teacherQuestionEnd',
            'sid': sid,
            'jqid': currentQuestionJqid,
            'statistics': response.statistics
        };
        Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
        dispatchEvent(new CustomEvent('teacherQuestionEnd_' + currentQuestionJqid, {
            "detail": {"statistics": response.statistics}
        }));
    }).fail(Notification.exception);
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

Sockets.prototype.closeImprovise = function() {
    let msg = {
        'action': 'closeImprovise',
        'sid': sid
    };
    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
    jQuery(REGION.IMPROVISE).addClass('d-none');
    jQuery(REGION.TEACHERCANVAS).addClass('d-flex').removeClass('d-none');
};

Sockets.prototype.improvise = function() {
    let msg = {
        'action': 'improvising',
        'sid': sid
    };
    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
    jQuery(REGION.IMPROVISE).removeClass('d-none');
    jQuery(REGION.TEACHERCANVAS).removeClass('d-flex').addClass('d-none');
};

Sockets.prototype.submitImprovise = function() {
    let improviseStatement = jQuery(REGION.IMPROVISESTATEMENT).val();
    let improviseReply = jQuery(REGION.IMPROVISEREPLY).val();
    if (improviseStatement === '') {
        jQuery(REGION.IMPROVISESTATEMENT).focus(() => {
            jQuery(REGION.IMPROVISESTATEMENT).css({'border-color': 'red'});
        });
    } else {
        jQuery(REGION.IMPROVISESTATEMENT).val('');
        jQuery(REGION.IMPROVISEREPLY).val('');
        let msg = {
            'action': 'improvised',
            'sid': sid,
            'improvisestatement': improviseStatement,
            'improvisereply': improviseReply,
            'cmid': cmid
        };
        Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
        Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
            jQuery(REGION.IMPROVISE).addClass('d-none');
            jQuery(REGION.TEACHERCANVAS).addClass('d-flex').removeClass('d-none');
            let identifier = jQuery(REGION.TEACHERCANVAS);
            identifier.append(html);
            if (improviseReply !== '') {
                Sockets.prototype.manageCloudTags(improviseReply);
            }
            let cloudtagsData = {
                'sessionid': sid,
                'cmid': cmid,
                'improvised': true,
                'sessionprogress': 0,
                'cloudtags': true,
                'isteacher': true,
                'questiontext': improviseStatement,
                'programmedmode': false,
                'preview': false,
                'tags': cloudTags
            };
            Templates.render(TEMPLATES.QUESTION, cloudtagsData).then(function(html, js) {
                identifier.html(html);
                jQuery('[data-action="delete-tag"]').on('click', Sockets.prototype.deleteTag.bind(this));
                Templates.runTemplateJS(js);
                jQuery(REGION.LOADING).remove();
                jQuery(REGION.VOTEIMPROVISE).removeClass('disabled');
            }).fail(Notification.exception);
        });
    }
};

Sockets.prototype.manageCloudTags = function(newtag) {
    if (newtag !== '') {
        let exist = false;
        if (cloudTags.length) {
            cloudTags = cloudTags.map((tag) => {
                if (tag.name.toLowerCase() === newtag.toLowerCase()) {
                    tag.count = tag.count + 1;
                    tag.votenum = 0;
                    exist = true;
                }
                return tag;
            });
        }
        if (!exist) {
            cloudTags.push({name: newtag, count: 1, votenum: 0});
        }
        const maxCount = Math.max(...cloudTags.map(item => item.count));
        cloudTags.forEach((item) => {
            item.size = Math.ceil((item.count / maxCount) * 10);
        });
    }
};

Sockets.prototype.manageTagsVotes = function(voteTag) {
    if (cloudTags.length) {
        cloudTags = cloudTags.map((tag) => {
            if (tag.name.toLowerCase() === voteTag.toLowerCase()) {
                tag.votenum = tag.votenum + 1;
            }
            return tag;
        });
        const maxCount = Math.max(...cloudTags.map(item => item.votenum));
        cloudTags.forEach((item) => {
            item.size = Math.ceil((item.votenum / maxCount) * 10);
        });
    }
};

Sockets.prototype.printNewTag = function() {
    let htmlTags = '';
    cloudTags.forEach(tag => {
        htmlTags +=
            '<li><span class="tag" data-count="' + tag.count + '" data-vote="' + tag.votenum + '"  data-size="' + tag.size + '">' +
                '<div class="delete-tag" data-name="' + tag.name + '" data-action="delete-tag">' +
                    'x' +
                '</div>' +
            tag.name +
            '</span></li>';
    });
    jQuery(REGION.TAGSCONTENT).html(htmlTags);
    jQuery('[data-action="delete-tag"]').on('click', Sockets.prototype.deleteTag.bind(this));
    let msg = {
        'action': 'printNewTag',
        'sid': sid,
        'tags': cloudTags
    };
    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
};

Sockets.prototype.deleteTag = function(e) {
    let nameToDelete = jQuery(e.currentTarget).attr('data-name');
    if (cloudTags.length) {
        const indexToDelete = cloudTags.findIndex(item => item.name === nameToDelete);
        if (indexToDelete !== -1) {
            cloudTags.splice(indexToDelete, 1);
        }
    }
    Sockets.prototype.printNewTag();
};

Sockets.prototype.vote = function() {
    voting = true;
    jQuery(REGION.VOTEIMPROVISE).addClass('disabled');
    jQuery(REGION.CLOUDTAGS).attr('data-show-votes', true);
    jQuery(REGION.CLOUDTAGS).attr('data-show-value', false);
    let msg = {
        'action': 'initVote',
        'sid': sid
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

export const teacherInitSockets = (region, socketurl, port, sessionmode, groupmode) => {
    return new Sockets(region, socketurl, port, sessionmode, groupmode);
};

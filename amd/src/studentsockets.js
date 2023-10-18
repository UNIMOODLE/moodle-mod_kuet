"use strict";
import jQuery from 'jquery';
import Templates from 'core/templates';
import Notification from 'core/notification';
import Ajax from 'core/ajax';
import Encryptor from 'mod_jqshow/encryptor';
import mEvent from 'core/event';

let REGION = {
    MESSAGEBOX: '#message-box',
    USERLIST: '[data-region="active-users"]',
    COUNTUSERS: '#countusers',
    ROOT: '[data-region="student-canvas"]',
    IMPROVISE: '[data-region="student-improvise"]',
    IMPROVISEREPLY: '.improvise-student-form #reply_student_improvise',
    TAGSCONTENT: '[data-region="tags-content"]',
    VOTETAG: '[data-action="vote-tag"]',
};

let ACTIONS = {
    REPLYIMPROVISE: '.improvise-response-content [data-action="submit-improvise"]'
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
    GROUPPARTICIPANT: 'mod_jqshow/session/manual/waitingroom/groupparticipant',
    SESSIONFIINISHED: 'mod_jqshow/session/manual/closeconnection',
    QUESTION: 'mod_jqshow/questions/encasement',
    PROVISIONALRANKING: 'mod_jqshow/ranking/provisional',
    IMPROVISESTUDENTRESPONSE: 'mod_jqshow/session/manual/improvise/studentresponse',
};

let portUrl = '8080';
let socketUrl = '';

/**
 * @constructor
 * @param {String} region
 * @param {String} socketurl
 * @param {String} port
 */
function Sockets(region, socketurl, port) {
    this.root = jQuery(region);
    socketUrl = socketurl;
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
let groupid = 0;
let groupmode = '';
let groupimage = null;
let groupname = null;
// Flags for improvised flow.
let userHasImprovised = false;
let userHasVoted = false;
let initVote = false;
let notImprovised = false;

Sockets.prototype.initSockets = function() {
    userid = this.root[0].dataset.userid;
    username = this.root[0].dataset.username;
    userimage = this.root[0].dataset.userimage;
    jqshowid = this.root[0].dataset.jqshowid;
    cmid = this.root[0].dataset.cmid;
    sid = this.root[0].dataset.sid;
    messageBox = this.root.find(REGION.MESSAGEBOX);
    countusers = this.root.find(REGION.COUNTUSERS);
    groupmode = this.root[0].dataset.groupmode;
    if (groupmode == '1') {
        groupimage = this.root[0].dataset.groupimage;
        groupid = parseInt(this.root[0].dataset.groupid);
        groupname = this.root[0].dataset.groupname;
    }

    let normalizeSocketUrl = Sockets.prototype.normalizeSocketUrl(socketUrl, portUrl);
    Sockets.prototype.webSocket = new WebSocket(normalizeSocketUrl);

    Sockets.prototype.webSocket.onopen = function() {

    };

    Sockets.prototype.webSocket.onmessage = function(ev) {
        let msgDecrypt = Encryptor.decrypt(ev.data);
        let response = JSON.parse(msgDecrypt); // PHP sends Json data.
        let resAction = response.action; // Message type.
        switch (resAction) {
            case 'alreadyAnswered':
                if (groupmode == '1') {
                    dispatchEvent(new CustomEvent('alreadyAnswered_' + response.jqid, {detail: { userid : response.userid }}));
                }
                break;
            case 'connect':
                // The server has returned the connected status, it is time to identify yourself.
                if (response.usersocketid !== undefined) {
                    usersocketid = response.usersocketid;
                    let msg = {
                        'userid': userid,
                        'name': username,
                        'pic': userimage,
                        'cmid': cmid,
                        'sid': sid,
                        'usersocketid': usersocketid,
                        'action': 'newuser',
                    };
                    if (groupmode == '1') {
                        msg.action = 'newgroup';
                        msg.name = groupname;
                        msg.pic = groupimage;
                        msg.groupid = groupid;
                    }
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
            case 'newgroup': {
                let participantshtml = jQuery(REGION.USERLIST);
                let grouplist = response.groups;
                participantshtml.html('');
                jQuery.each(grouplist, function(i, group) {
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
                    let removeEvents = new CustomEvent('removeEvents');
                    dispatchEvent(removeEvents);
                    Ajax.call([request])[0].done(function(answer) {
                        const questionData = {
                            ...response.context.value,
                            ...answer
                        };
                        Templates.render(TEMPLATES.QUESTION, questionData).then(function(html, js) {
                            identifier.html(html);
                            Templates.runTemplateJS(js);
                            mEvent.notifyFilterContentUpdated(document.querySelector(REGION.ROOT));
                            jQuery(REGION.LOADING).remove();
                        }).fail(Notification.exception);
                    });
                });
                break;
            case 'ranking':
                Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                    let identifier = jQuery(REGION.ROOT);
                    identifier.append(html);
                    Templates.render(TEMPLATES.PROVISIONALRANKING, response.context).then(function(html, js) {
                        identifier.html(html);
                        Templates.runTemplateJS(js);
                        jQuery(REGION.LOADING).remove();
                    }).fail(Notification.exception);
                });
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
            case 'improvising':
                jQuery(REGION.IMPROVISE).removeClass('d-none');
                jQuery(REGION.ROOT).addClass('d-none');
                break;
            case 'closeImprovise':
                jQuery(REGION.IMPROVISE).addClass('d-none');
                jQuery(REGION.ROOT).removeClass('d-none');
                break;
            case 'improvised': // Teacher sen question improvised.
                userHasImprovised = false;
                userHasVoted = false;
                initVote = false;
                notImprovised = false;
                Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                    jQuery(REGION.IMPROVISE).addClass('d-none');
                    jQuery(REGION.ROOT).removeClass('d-none');
                    let identifier = jQuery(REGION.ROOT);
                    identifier.append(html);
                    Templates.render(TEMPLATES.IMPROVISESTUDENTRESPONSE, response).then(function(html) {
                        identifier.html(html);
                        jQuery(ACTIONS.REPLYIMPROVISE).on('click', Sockets.prototype.replyImprovise);
                        jQuery(REGION.LOADING).remove();
                    }).fail(Notification.exception);
                });
                break;
            case 'printNewTag':
                // eslint-disable-next-line no-case-declarations
                let printTags = false;
                if (initVote === true) {
                    if (userHasVoted === true && notImprovised === false) {
                        printTags = true;
                    }
                    if (notImprovised === true) {
                        printTags = true;
                        notImprovised = false;
                    }
                } else {
                    printTags = true;
                }
                if (printTags === true) {
                    // eslint-disable-next-line no-case-declarations
                    let htmlTags = '';
                    response.tags.forEach(tag => {
                        // eslint-disable-next-line max-len
                        htmlTags += '<li><span class="tag" data-count="' + tag.count + '" data-vote="' + tag.votenum + '" data-size="' + tag.size + '">' +
                            '<div class="vote-tag d-none" data-name="' + tag.name + '" data-action="vote-tag">' +
                            // eslint-disable-next-line max-len
                            '<svg id="vote-layer" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 115.29">' +
                            '<defs><style>.cls-1{fill:#212121;}.cls-2{fill:#33a867;fill-rule:evenodd;}</style></defs>' +
                            // eslint-disable-next-line max-len
                            '<path d="M45.22,30.83a5.78,5.78,0,0,1,2.9-3,6.83,6.83,0,0,1,4.66-.26,12.88,12.88,0,0,1,4.45,2.27,22,22,0,0,1,8.14,15.82,45.93,45.93,0,0,1-.1,7c-.13,1.64-.34,3.37-.63,5.16H81.57a18.21,18.21,0,0,1,7.64,1.94,12.43,12.43,0,0,1,4.69,4.12,10.55,10.55,0,0,1,1.71,6.31A14.93,14.93,0,0,1,94.08,76a18.42,18.42,0,0,1,.46,7,8.22,8.22,0,0,1-2.33,4.75A21.3,21.3,0,0,1,91,95.65a16.62,16.62,0,0,1-3.77,5.9,24,24,0,0,1-.75,4.21,14.4,14.4,0,0,1-2.1,4.52h0c-2.88,4.06-5.18,4-8.82,3.81-.51,0-1,0-1.89,0h-33a16,16,0,0,1-7.42-1.49,17.68,17.68,0,0,1-5.83-5.08L27,106.07V67.62l1.68-.45C32.9,66,36.24,62.39,38.85,58a64.87,64.87,0,0,0,5.83-13.95v-10a6.17,6.17,0,0,1,.54-3.25ZM3.38,63.6H19.73A3.39,3.39,0,0,1,23.11,67v44.93a3.39,3.39,0,0,1-3.38,3.38H3.38A3.39,3.39,0,0,1,0,111.91V67A3.39,3.39,0,0,1,3.38,63.6ZM50,31.87a1.82,1.82,0,0,0-.83,1.32V44.4l-.1.64a69.3,69.3,0,0,1-6.38,15.3C39.87,65,36.22,69.09,31.47,71v34.32a12.18,12.18,0,0,0,3.82,3.25,11.76,11.76,0,0,0,5.42,1h33c.59,0,1.35,0,2.07.06,2.16.08,3.52.14,5-1.92h0a9.93,9.93,0,0,0,1.43-3.14,20.11,20.11,0,0,0,.67-4.15l.69-1.48a12.6,12.6,0,0,0,3.27-4.83,17.75,17.75,0,0,0,.87-7.28l-.08-1.33,1.13-.7a3.44,3.44,0,0,0,1.38-2.51,14.87,14.87,0,0,0-.57-6l.18-1.63A11.34,11.34,0,0,0,91.13,70a6.15,6.15,0,0,0-1-3.68,8.06,8.06,0,0,0-3-2.61,13.58,13.58,0,0,0-5.63-1.43H59.26l.5-2.66c.48-2.58.83-5,1-7.37a42.55,42.55,0,0,0,.11-6.33,17.42,17.42,0,0,0-6.39-12.53,8.43,8.43,0,0,0-2.86-1.5,2.56,2.56,0,0,0-1.65,0Z"/>' +
                            '</svg>' +
                            '</div>' +
                            tag.name + '</span></li>';
                    });
                    jQuery(REGION.TAGSCONTENT).html(htmlTags);
                    if (initVote === true && userHasVoted === false) {
                        jQuery(REGION.VOTETAG).removeClass('d-none').on('click', Sockets.prototype.voteTags);
                    }
                }
                break;
            case 'initVote': // Teacher init vote.
                initVote = true;
                if (userHasImprovised === false) {
                    Sockets.prototype.replyImprovise();
                }
                jQuery(REGION.VOTETAG).removeClass('d-none').on('click', Sockets.prototype.voteTags);
                break;
            case 'endSession':
                jQuery(REGION.IMPROVISE).addClass('d-none');
                jQuery(REGION.ROOT).removeClass('d-none');
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
                dispatchEvent(new CustomEvent('teacherQuestionEnd_' + response.jqid, {
                    "detail": {"statistics": response.statistics}
                }));
                break;
            case 'userdisconnected':
                if (groupmode != '1') {
                    jQuery('[data-userid="' + response.usersocketid + '"]').remove();
                    countusers.html(response.count);
                }
                break;
            case 'groupdisconnected':
                jQuery('[data-groupid="' + response.groupid + '"]').remove();
                countusers.html(response.count);
                messageBox.append('<div>' + response.message + '</div>');
                break;
            case 'groupmemberdisconnected':
                jQuery('.participants').find('[data-groupid="' + response.groupid + '"]')
                    .find('.numgroupusers').html('(' + response.count + ')');
                messageBox.append('<div>' + response.message + '</div>');
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
        jQuery(REGION.IMPROVISE).addClass('d-none');
        jQuery(REGION.ROOT).removeClass('d-none');
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
    addEventListener('studentQuestionEnd', () => {
        let msg = {
            'userid': userid,
            'sid': sid,
            'usersocketid': usersocketid,
            'jqid': currentCuestionJqid,
            'oft': true, // IMPORTANT: Only for teacher.
            'action': 'studentQuestionEnd',
        };
        Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
        if (groupmode == '1') {
            let msg2 = {
                'userid': userid,
                'sid': sid,
                'usersocketid': usersocketid,
                'jqid': currentCuestionJqid,
                'ofg': true,
                'action': 'alreadyAnswered',
            };
            setTimeout(function() {
                Sockets.prototype.sendMessageSocket(JSON.stringify(msg2));
            }, 300);
        }
    }, false);
};

Sockets.prototype.replyImprovise = function() {
    let improviseReply = '';
    if (initVote === false) {
        improviseReply = jQuery(REGION.IMPROVISEREPLY).val();
        if (improviseReply === '') {
            jQuery(REGION.IMPROVISEREPLY).focus(() => {
                jQuery(REGION.IMPROVISEREPLY).css({'border-color': 'red'});
            });
        }
    }
    if ((initVote === false && improviseReply !== '') || initVote === true) {
        Templates.render(TEMPLATES.LOADING, {visible: true}).done(function() {
            jQuery(REGION.IMPROVISE).addClass('d-none');
            jQuery(REGION.ROOT).removeClass('d-none');
            jQuery(REGION.IMPROVISEREPLY).val('');
            let cloudtagsData = {
                'sessionid': sid,
                'cmid': cmid,
                'improvised': true,
                'sessionprogress': 0,
                'cloudtags': true,
                'isteacher': false,
                'questiontext': jQuery('.improvise-statement').text(),
                'programmedmode': false,
                'preview': false,
                'tags': [{name: improviseReply, count: 1, size: 9}]
            };
            Templates.render(TEMPLATES.QUESTION, cloudtagsData).then(function(html) {
                jQuery(REGION.ROOT).html(html);
                jQuery(REGION.LOADING).remove();
                let msg = {
                    'action': 'ImproviseStudentTag',
                    'sid': sid,
                    'oft': true, // IMPORTANT: Only for teacher.
                    'improvisereply': improviseReply,
                    'sessionid': sid,
                    'cmid': cmid,
                    'userid': userid,
                };
                Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
                if (userHasImprovised === false) {
                    jQuery(REGION.VOTETAG).removeClass('d-none').on('click', Sockets.prototype.voteTags);
                    notImprovised = true;
                }
                userHasImprovised = true;
            }).fail(Notification.exception);
        });
    }
};

Sockets.prototype.voteTags = function(e) {
    jQuery(REGION.VOTETAG).addClass('d-none');
    jQuery(REGION.TAGSCONTENT).attr('data-show-votes', true);
    jQuery(REGION.TAGSCONTENT).attr('data-show-value', false);
    let votedTag = e.target.getAttribute('data-name');
    if (votedTag) {
        userHasVoted = true;
        let msg = {
            'action': 'StudentVotedTag',
            'sid': sid,
            'oft': true, // IMPORTANT: Only for teacher.
            'votedtag': votedTag,
            'sessionid': sid,
            'cmid': cmid,
            'userid': userid,
        };
        Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
    }
};

Sockets.prototype.sendMessageSocket = function(msg) {
    this.webSocket.send(msg);
};

export const studentInitSockets = (region, socketurl, port) => {
    return new Sockets(region, socketurl, port);
};

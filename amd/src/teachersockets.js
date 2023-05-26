"use strict";

import jQuery from 'jquery';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Ajax from 'core/ajax';

let REGION = {
    MESSAGEBOX: '#message-box',
    USERLIST: '[data-region="active-users"]',
    COUNTUSERS: '#countusers'
};

let ACTION = {
    BACKSESSION: '[data-action="back-session"]',
    INITSESSION: '[data-action="init-session"]',
};

let SERVICES = {
    ACTIVESESSION: 'mod_jqshow_activesession'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    PARTICIPANT: 'mod_jqshow/session/manual/waitingroom/participant'
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
    this.measuringSpeed();
    this.disableDevTools(); // TODO extend to the whole mod.
    this.initSockets();
    this.cleanMessages();
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

/* ****************** */

/** @type {jQuery} The jQuery node for the page region. */
Sockets.prototype.root = null;

let userid = null;
let usersocketid = null;
let username = null;
let messageBox = null;
let countusers = null;
let cmid = null;
let sid = null;
const password = 'elktkktagqes';
const abc = 'abcdefghijklmnopqrstuvwxyz0123456789=ABCDEFGHIJKLMNOPQRSTUVWXYZ/+-*';

Sockets.prototype.initSockets = function() {
    userid = this.root[0].dataset.userid;
    username = this.root[0].dataset.username;
    cmid = this.root[0].dataset.cmid;
    sid = this.root[0].dataset.sid;
    messageBox = this.root.find(REGION.MESSAGEBOX);
    countusers = this.root.find(REGION.COUNTUSERS);

    this.root.find(ACTION.BACKSESSION).on('click', this.backSession);

    Sockets.prototype.webSocket = new WebSocket(
        'wss://' + M.cfg.wwwroot.replace(/^https?:\/\//, '') + ':' + portUrl + '/jqshow'
    );

    Sockets.prototype.webSocket.onopen = function() {
        /* TODO call service to get all the quiz questions,
            and generate an iterator to call .next() each time the socket/professor says so. */
    };

    Sockets.prototype.webSocket.onmessage = function(ev) {
        let msgDecrypt = Sockets.prototype.decrypt(password, ev.data);
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
            messageBox.append(
                '<div class="alert alert-warning" role="alert">' + s + '</div>'
            );
            setTimeout(() => {
                // ... window.location.replace(M.cfg.wwwroot + '/mod/jqshow/view.php?id=' + cmid);
            }, 5000);
        });
    };
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

Sockets.prototype.sendMessageSocket = function(msg) {
    this.webSocket.send(msg);
};

Sockets.prototype.decrypt = function(password, text) {
    const arr = text.split('');
    const arrPass = password.split('');
    let lastPassLetter = 0;
    let decrypted = '';
    for (let i = 0; i < arr.length; i++) {
        const letter = arr[i];
        const passwordLetter = arrPass[lastPassLetter];
        const temp = this.getInvertedLetterFromAlphabetForLetter(passwordLetter, letter);
        if (temp) {
            decrypted += temp;
        } else {
            return null;
        }
        if (lastPassLetter === (arrPass.length - 1)) {
            lastPassLetter = 0;
        } else {
            lastPassLetter++;
        }
    }
    return atob(decrypted);
};

Sockets.prototype.getInvertedLetterFromAlphabetForLetter = function(letter, letterToChange) {
    const posLetter = abc.indexOf(letter);
    if (posLetter == -1) {
        // eslint-disable-next-line no-console
        console.log('Password letter ' + letter + ' not allowed.');
        return null;
    }

    const part1 = abc.substring(posLetter, abc.length);
    const part2 = abc.substring(0, posLetter);
    const newABC = '' + part1 + '' + part2;
    const posLetterToChange = newABC.indexOf( letterToChange );

    if (posLetterToChange == -1) {
        // eslint-disable-next-line no-console
        console.log('Password letter ' + letter + ' not allowed.');
        return null;
    }

    return abc.split('')[posLetterToChange];
};

Sockets.prototype.encrypt = function(password, text) {
    const base64 = btoa(text);
    const arr = base64.split('');
    const arrPass = password.split('');
    let lastPassLetter = 0;
    let encrypted = '';
    for (let i = 0; i < arr.length; i++) {
        const letter = arr[i];
        const passwordLetter = arrPass[lastPassLetter];
        const temp = this.getLetterFromAlphabetForLetter( passwordLetter, letter );
        if (temp) {
            encrypted += temp;
        } else {
            return null;
        }
        if (lastPassLetter === (arrPass.length - 1)) {
            lastPassLetter = 0;
        } else {
            lastPassLetter++;
        }
    }
    return encrypted;
};

Sockets.prototype.getLetterFromAlphabetForLetter = function(letter, letterToChange) {
    const posLetter = abc.indexOf(letter);
    if (posLetter == -1) {
        // eslint-disable-next-line no-console
        console.log('Password letter ' + letter + ' not allowed.');
        return null;
    }
    const posLetterToChange = abc.indexOf(letterToChange);
    if (posLetterToChange == -1) {
        // eslint-disable-next-line no-console
        console.log('Password letter ' + letter + ' not allowed.');
        return null;
    }
    const part1 = abc.substring(posLetter, abc.length);
    const part2 = abc.substring(0, posLetter);
    const newABC = '' + part1 + '' + part2;
    return newABC.split('')[posLetterToChange];
};

export const teacherInitSockets = (region, port) => {
    return new Sockets(region, port);
};

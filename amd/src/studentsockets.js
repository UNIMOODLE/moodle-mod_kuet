"use strict";

import jQuery from 'jquery';
import Templates from 'core/templates';
import Notification from 'core/notification';
import Ajax from 'core/ajax';

let REGION = {
    MESSAGEBOX: '#message-box',
    USERLIST: '[data-region="active-users"]',
    COUNTUSERS: '#countusers',
    ROOT: '[data-region="student-canvas"]'
};

let SERVICES = {
    SESSIONFIINISHED: 'mod_jqshow_sessionfinished',
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    PARTICIPANT: 'mod_jqshow/session/manual/waitingroom/participant',
    SESSIONFIINISHED: 'mod_jqshow/session/manual/closeconnection',
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
const password = 'elktkktagqes';
const abc = 'abcdefghijklmnopqrstuvwxyz0123456789=ABCDEFGHIJKLMNOPQRSTUVWXYZ/+-*';

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
    const posLetterToChange = newABC.indexOf(letterToChange);

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
        const temp = this.getLetterFromAlphabetForLetter(passwordLetter, letter);
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

export const studentInitSockets = (region, port) => {
    return new Sockets(region, port);
};

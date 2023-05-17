"use strict";
/* eslint-disable no-unused-vars */ // TODO remove.

import jQuery from 'jquery';

let REGION = {
    MESSAGEBOX: '#message-box',
    USERLIST: '#userlist',
    COUNTUSERS: '#countusers'
};

let ACTION = {

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
}

/** @type {jQuery} The jQuery node for the page region. */
Sockets.prototype.root = null;

let userid = null;
let usersocketid = null;
let username = null;
let messageBox = null;
let userlist = null;
let countusers = null;
let cmid = null;
let sid = null;

Sockets.prototype.initSockets = function() {
    userid = this.root[0].dataset.userid;
    username = this.root[0].dataset.username;
    cmid = this.root[0].dataset.cmid;
    sid = this.root[0].dataset.sid;
    messageBox = this.root.find(REGION.MESSAGEBOX);
    userlist = this.root.find(REGION.USERLIST);
    countusers = this.root.find(REGION.COUNTUSERS);

    Sockets.prototype.webSocket = new WebSocket(
        'wss://' + M.cfg.wwwroot.replace(/^https?:\/\//, '') + ':' + portUrl + '/jqshow'
    );

    Sockets.prototype.webSocket.onopen = function(event) {
        messageBox.append(
            '<div class="system_msg" style="color:#bbbbbb">' +
            'Bienvenido a Jam Quiz Show ' + username + '!</div>'
        );
    };

    Sockets.prototype.webSocket.onmessage = function(ev) {
        let response = JSON.parse(ev.data); // PHP sends Json data.
        let resAction = response.action; // Message type.
        switch (resAction) {
            case 'connect':
                // The server has returned the connected status, it is time to identify yourself.
                if (response.usersocketid !== undefined) {
                    usersocketid = response.usersocketid;
                    let msg = {
                        'id': userid,
                        'name': username,
                        'cmid': cmid,
                        'sid': sid,
                        'usersocketid': usersocketid,
                        'action': 'newuser',
                    };
                    Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
                }
                break;
            case 'newuser':
                // TODO Append new user to waitroom.
                break;
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
        messageBox.append('<div class="system_msg">Connection Closed</div>');
    };
};

Sockets.prototype.sendMessageSocket = function(msg) {
    this.webSocket.send(msg);
};

export const studentInitSockets = (region, port) => {
    return new Sockets(region, port);
};

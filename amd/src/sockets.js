// TODO translate into @import.
define(['jquery'], function($) {
    "use strict";

    let REGION = {
        MESSAGEBOX: '#message-box',
        USERLIST: '#userlist',
        COUNTUSERS: '#countusers'
    };

    let ACTION = {
        TEACHERSEND: '#teacher_send',
        STUDENTSEND: '#student_send',
        TEACHERMESSAGES: '#teacher_messages',
        STUDENTMESSAGES: '#student_messages',
    };

    let portUrl = '8080';

    /**
     * @constructor
     * @param {String} region
     * @param {String} port
     */
    function Sockets(region, port) {
        this.root = $(region);
        portUrl = port;
        this.initSockets();
    }

    /** @type {jQuery} The jQuery node for the page region. */
    Sockets.prototype.root = null;

    let userid = null;
    let username = null;
    let messageBox = null;
    let userlist = null;
    let countusers = null;
    let teacherMessages = null;
    let studentMessages = null;
    let isteacher = null;
    let cmid = null;
    let sid = null;

    Sockets.prototype.initSockets = function() {
        userid = this.root[0].dataset.userid;
        username = this.root[0].dataset.username;
        isteacher = this.root[0].dataset.isteacher;
        cmid = this.root[0].dataset.cmid;
        sid = this.root[0].dataset.sid;
        messageBox = this.root.find(REGION.MESSAGEBOX);
        userlist = this.root.find(REGION.USERLIST);
        countusers = this.root.find(REGION.COUNTUSERS);
        teacherMessages = this.root.find(ACTION.TEACHERMESSAGES);
        studentMessages = this.root.find(ACTION.STUDENTMESSAGES);
        this.root.find(ACTION.TEACHERSEND).on('click', this.teacherSend.bind(this));
        this.root.find(ACTION.STUDENTSEND).on('click', this.studentSend.bind(this));

        Sockets.prototype.webSocket = new WebSocket(
            'wss://' + M.cfg.wwwroot.replace(/^https?:\/\//, '') + ':' + portUrl + '/jqshow'
        );

        Sockets.prototype.webSocket.onopen = function() {
            let msg = {
                'id': userid,
                'name': username,
                'isteacher': isteacher,
                'cmid': cmid,
                'sid': sid,
                'action': 'newuser',
            };
            Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
            messageBox.append(
                '<div class="system_msg" style="color:#bbbbbb">' +
                'Bienvenido a Jam Quiz Show ' + username + '!</div>'
            );
        };

        Sockets.prototype.webSocket.onmessage = function(ev) {
            let response = JSON.parse(ev.data); // PHP sends Json data.
            let resAction = response.action; // Message type.
            switch (resAction) {
                case 'newuser':
                    if (response.name !== undefined) {
                        userlist.append(
                            '<li data-userid="' + response.userid + '">' +
                            response.name + '</li>'
                        );
                        teacherMessages.append('<div>' + response.message + '</div>');
                    }
                    countusers.html(response.count);
                    break;
                case 'countusers':
                    countusers.html(response.count);
                    break;
                case 'teacherSend':
                    studentMessages.append('<div>' + response.message + '</div>');
                    break;
                case 'studentSend':
                    teacherMessages.append('<div>' + response.message + '</div>');
                    break;
                case 'userdisconnected':
                    $('[data-userid="' + response.id + '"]').remove();
                    teacherMessages.append('<div>' + response.message + '</div>');
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

    Sockets.prototype.teacherSend = function() {
        let msg = {
            'id': userid,
            'name': username,
            'action': 'teacherSend',
        };
        Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
    };

    Sockets.prototype.studentSend = function() {
        let msg = {
            'id': userid,
            'name': username,
            'action': 'studentSend',
        };
        Sockets.prototype.sendMessageSocket(JSON.stringify(msg));
    };

    Sockets.prototype.sendMessageSocket = function(msg) {
        this.webSocket.send(msg);
    };

    return {
        /**
         * @param {String} region
         * @param {String} port
         * @return {Sockets}
         */
        initSockets: function(region, port) {
            return new Sockets(region, port);
        },
    };
});

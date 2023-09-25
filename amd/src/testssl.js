define(['jquery', 'core/str', 'core/notification'], function($, str, notification) {
    "use strict";

    let socketUrl = '';
    let portUrl = '8080';

    /**
     * @constructor
     * @param {String} region
     * @param {String} socketurl
     * @param {String} port
     */
    function TestSockets(region, socketurl, port) {
        this.root = $(region);
        socketUrl = socketurl;
        portUrl = port;
        this.initTestSockets();
    }

    /** @type {jQuery} The jQuery node for the page region. */
    TestSockets.prototype.root = null;

    let messageBox = null;

    TestSockets.prototype.initTestSockets = function() {
        messageBox = this.root.find('#testresult');

        TestSockets.prototype.webSocket = new WebSocket(
            'wss://' + socketUrl.replace(/^https?:\/\//, '') + ':' + portUrl + '/testjqshow'
        );

        TestSockets.prototype.webSocket.onopen = function() {
            str.get_strings([
                {key: 'validcertificates', component: 'mod_jqshow'}
            ]).done(function(strings) {
                messageBox.append('<div class="alert alert-success" role="alert">' + strings[0] + '</div>');
            }).fail(notification.exception);
        };

        TestSockets.prototype.webSocket.onerror = function(event) {
            // eslint-disable-next-line no-console
            console.error(event);
            str.get_strings([
                {key: 'invalidcertificates', component: 'mod_jqshow'}
            ]).done(function(strings) {
                messageBox.append('<div class="alert alert-danger" role="alert">' + strings[0] + '</div>');
            }).fail(notification.exception);
        };

        TestSockets.prototype.webSocket.onclose = function() {
            str.get_strings([
                {key: 'socketclosed', component: 'mod_jqshow'}
            ]).done(function(strings) {
                messageBox.append('<div class="alert alert-warning" role="alert">' + strings[0] + '</div>');
            }).fail(notification.exception);
        };
    };

    TestSockets.prototype.sendMessageSocket = function(msg) {
        this.webSocket.send(msg);
    };

    return {
        /**
         * @param {String} region
         * @param {String} socketurl
         * @param {String} port
         * @return {TestSockets}
         */
        initTestSockets: function(region, socketurl, port) {
            return new TestSockets(region, socketurl, port);
        },
    };
});

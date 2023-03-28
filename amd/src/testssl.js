define(['jquery', 'core/str', 'core/notification'], function($, str, notification) {
    "use strict";

    let portUrl = '8080';

    /**
     * @constructor
     * @param {String} region
     * @param {String} port
     */
    function TestSockets(region, port) {
        this.root = $(region);
        portUrl = port;
        this.portUrl = port;
        // eslint-disable-next-line no-console
        console.log('port', portUrl, region);
        this.initTestSockets();
    }

    // eslint-disable-next-line no-console
    console.log('port fuera', this.portUrl);

    /** @type {jQuery} The jQuery node for the page region. */
    TestSockets.prototype.root = null;
    TestSockets.prototype.portUrl = null;
    TestSockets.prototype.webSocket = new WebSocket(
        'wss://' + M.cfg.wwwroot.replace(/^https?:\/\//, '') + ':' + this.portUrl + '/jqshow'
    );

    let messageBox = null;

    TestSockets.prototype.initTestSockets = function() {
        messageBox = this.root.find('#testresult');
    };

    TestSockets.prototype.webSocket.onopen = function() {
        str.get_strings([
            {key: 'validcertificates', component: 'mod_jqshow'}
        ]).done(function(strings) {
            messageBox.append('<div class="alert alert-success" role="alert">' + strings[0] + '</div>');
            let msg = {
                'action': 'shutdownTest',
            };
            TestSockets.prototype.sendMessageSocket(JSON.stringify(msg));
        }).fail(notification.exception);
    };

    TestSockets.prototype.webSocket.onerror = function() {
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

    TestSockets.prototype.sendMessageSocket = function(msg) {
        this.webSocket.send(msg);
    };

    return {
        /**
         * @param {String} region
         * @param {String} port
         * @return {TestSockets}
         */
        initTestSockets: function(region, port) {
            return new TestSockets(region, port);
        },
    };

});

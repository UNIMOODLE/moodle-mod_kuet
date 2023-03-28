define(['jquery'], function($) {
    "use strict";

    /**
     * @constructor
     * @param {String} region
     */
    function TestSockets(region) {
        this.root = $(region);
        this.initTestSockets();
    }

    /** @type {jQuery} The jQuery node for the page region. */
    TestSockets.prototype.root = null;
    TestSockets.prototype.webSocket = new WebSocket(
        'wss://' + M.cfg.wwwroot.replace(/^https?:\/\//, '') + ':8080/jqshow'
    );

    let messageBox = null;

    TestSockets.prototype.initTestSockets = function() {
        messageBox = this.root.find('#testresult');
    };

    TestSockets.prototype.webSocket.onopen = function() {
        messageBox.append('<h4 style="color:green">Valid SSL Certificates</h4>');
        let msg = {
            'action': 'shutdownTest',
        };
        TestSockets.prototype.sendMessageSocket(JSON.stringify(msg));
    };

    TestSockets.prototype.webSocket.onerror = function() {
        messageBox.append('<h4 style="color:red">Invalid certificates</h4>');
    };

    TestSockets.prototype.webSocket.onclose = function() {
        messageBox.append('<h5 style="color:orange">Socket closed</h5>');
    };

    TestSockets.prototype.sendMessageSocket = function(msg) {
        this.webSocket.send(msg);
    };

    return {
        /**
         * @param {String} region
         * @return {TestSockets}
         */
        initTestSockets: function(region) {
            return new TestSockets(region);
        },
    };

});

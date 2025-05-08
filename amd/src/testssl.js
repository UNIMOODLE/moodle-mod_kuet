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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos..

/**
 *
 * @module    mod_kuet/testssl
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str', 'core/notification', 'mod_kuet/encryptor'], function($, str, notification, Encryptor) {
    "use strict";

    let socketUrl = '';
    let portUrl = '8080';
    let secureProtocol = false;
    let concatenateOnClose = false;

    /** @type {jQuery} The jQuery node for the page region. */
    TestSockets.prototype.root = null;

    let protocolCell = null;
    let protocolExtraInfoCell = null;

    let sslCell = null;
    let sslExtraInfoCell = null;

    let connectionCell = null;
    let connectionExtraInfoCell = null;

    let sendmessageCell = null;
    let sendmessageExtraInfoCell = null;

    let receivemessageCell = null;
    let receivemessageExtraInfoCell = null;

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
        protocolCell = this.root.find('#protocol-test-result');
        protocolExtraInfoCell = this.root.find('#protocol-test-extra-info');
        sslCell = this.root.find('#ssl-test-result');
        sslExtraInfoCell = this.root.find('#ssl-test-extra-info');
        connectionCell = this.root.find('#open-connection-test-result');
        connectionExtraInfoCell = this.root.find('#open-connection-test-extra-info');
        sendmessageCell = this.root.find('#send-message-test-result');
        sendmessageExtraInfoCell = this.root.find('#send-message-test-extra-info');
        receivemessageCell = this.root.find('#receive-message-test-result');
        receivemessageExtraInfoCell = this.root.find('#receive-message-test-extra-info');

        this.initTestSockets();
    }

    TestSockets.prototype.setValid = function(element, clean = true) {
        if (clean === true) {
            TestSockets.prototype.cleanAllChilds(element);
        }
        element.append('<i class="icon fa fa-check text-success fa-fw " aria-hidden="true"></i>');
    };

    TestSockets.prototype.setWarning = function(element, clean = true) {
        if (clean === true) {
            TestSockets.prototype.cleanAllChilds(element);
        }
        element.append('<i class="icon fa fa-warning text-warning fa-fw " aria-hidden="true"></i>');
    };

    TestSockets.prototype.setError = function(element, clean = true) {
        if (clean === true) {
            TestSockets.prototype.cleanAllChilds(element);
        }
        element.append('<i class="icon fa fa-times text-danger fa-fw " aria-hidden="true"></i>');
    };

    TestSockets.prototype.setExtraInfo = function(element, msg, clean = true) {
        if (clean === true) {
            TestSockets.prototype.cleanAllChilds(element);
        }
        element.append('<span>' + msg + '</span>');
    };

    TestSockets.prototype.cleanAllChilds = function(element) {
        if (element && element.length === 1) {
            let el = element[0];
            el.childNodes.forEach(child => {
                el.removeChild(child);
            });
        }
    };

    TestSockets.prototype.normalizeSocketUrl = function(socketUrl, port) {
        let jsUrl = new URL(socketUrl);

        if (jsUrl.pathname === '/') {
            jsUrl.pathname = jsUrl.pathname + 'testkuet';
        } else {
            jsUrl.pathname = jsUrl.pathname + '/testkuet';
        }

        jsUrl.port = port;

        if (jsUrl.protocol === 'https:') {
            jsUrl.protocol = 'wss:';
            secureProtocol = true;
            TestSockets.prototype.setValid(protocolCell);
            return jsUrl.toString();
        } else if (jsUrl.protocol === 'http:') {
            jsUrl.protocol = 'ws:';
            TestSockets.prototype.setWarning(protocolCell);
            str.get_strings([
                {key: 'httpsrecommended', component: 'mod_kuet'}
            ]).done(function(strings) {
                TestSockets.prototype.setExtraInfo(protocolExtraInfoCell, strings[0]);
            }).fail(notification.exception);
            return jsUrl.toString();
        }

        TestSockets.prototype.setError(protocolCell);
        str.get_strings([
            {key: 'protocolnotvalid', component: 'mod_kuet'}
        ]).done(function(strings) {
            TestSockets.prototype.setExtraInfo(protocolExtraInfoCell, strings[0]);
        }).fail(notification.exception);
        return '';
    };

    TestSockets.prototype.initTestSockets = function() {
        let normalizeSocketUrl = TestSockets.prototype.normalizeSocketUrl(socketUrl, portUrl);
        TestSockets.prototype.webSocket = new WebSocket(normalizeSocketUrl);

        if (TestSockets.prototype.webSocket !== null) {
            TestSockets.prototype.webSocket.onopen = function() {
                TestSockets.prototype.setValid(connectionCell);
                if (secureProtocol === true) {
                    TestSockets.prototype.setValid(sslCell);
                    str.get_strings([
                        {key: 'validcertificates', component: 'mod_kuet'},
                        {key: 'validopenconnection', component: 'mod_kuet'}
                    ]).done(function(strings) {
                        TestSockets.prototype.setExtraInfo(sslExtraInfoCell, strings[0]);
                        TestSockets.prototype.setExtraInfo(connectionExtraInfoCell, strings[1]);
                    }).fail(notification.exception);
                } else {
                    TestSockets.prototype.setWarning(sslCell);
                    str.get_strings([
                        {key: 'userconnectionsonrisk', component: 'mod_kuet'},
                        {key: 'validopenconnection', component: 'mod_kuet'}
                    ]).done(function(strings) {
                        TestSockets.prototype.setExtraInfo(sslExtraInfoCell, strings[0]);
                        TestSockets.prototype.setExtraInfo(connectionExtraInfoCell, strings[1]);
                    }).fail(notification.exception);
                }
                TestSockets.prototype.sendMessageSocket('ping');
            };

            TestSockets.prototype.webSocket.onerror = function(event) {
                // eslint-disable-next-line no-console
                console.log(event);
                TestSockets.prototype.setError(sslCell);
                if (secureProtocol === true) {
                    str.get_strings([
                        {key: 'invalidcertificates', component: 'mod_kuet'}
                    ]).done(function(strings) {
                        TestSockets.prototype.setExtraInfo(sslExtraInfoCell, strings[0] + ' ' + event.reason);
                        TestSockets.prototype.setExtraInfo(connectionExtraInfoCell, strings[0]);
                    }).fail(notification.exception);
                } else {
                    str.get_strings([
                        {key: 'notusecertificates', component: 'mod_kuet'},
                        {key: 'invalidconnection', component: 'mod_kuet'},
                        {key: 'notmakeaconnection', component: 'mod_kuet'},
                    ]).done(function(strings) {
                        TestSockets.prototype.setExtraInfo(sslExtraInfoCell, strings[0]);
                        let messageError = '';
                        if (event && event.target && event.target.readyState === 3) {
                            messageError += ' ' + strings[2];
                            concatenateOnClose = true;
                        }
                        TestSockets.prototype.setExtraInfo(connectionExtraInfoCell, strings[1] + messageError);
                    }).fail(notification.exception);
                    // Update the rest of tests.
                    TestSockets.prototype.setError(sendmessageCell);
                    TestSockets.prototype.setError(receivemessageCell);
                    str.get_strings([
                        {key: 'generictesterror', component: 'mod_kuet'}
                    ]).done(function(strings) {
                        TestSockets.prototype.setExtraInfo(sendmessageExtraInfoCell, strings[0]);
                        TestSockets.prototype.setExtraInfo(receivemessageExtraInfoCell, strings[0]);
                    }).fail(notification.exception);
                }
            };

            TestSockets.prototype.webSocket.onclose = function(event) {
                TestSockets.prototype.setError(connectionCell);
                str.get_strings([
                    {key: 'connectionclosed', component: 'mod_kuet'}
                ]).done(function(strings) {
                    TestSockets.prototype.setExtraInfo(
                        connectionExtraInfoCell,
                        strings[0] + ' ' + event.reason, concatenateOnClose
                    );
                    if (concatenateOnClose === true) {
                        concatenateOnClose = false;
                    }
                }).fail(notification.exception);
            };

            TestSockets.prototype.webSocket.onmessage = function(event) {
                if (event.data) {
                    TestSockets.prototype.setValid(receivemessageCell);
                    let msgDecrypt = Encryptor.decrypt(event.data);
                    let response = JSON.parse(msgDecrypt); // PHP sends Json data.
                    let resAction = response.action; // Message type.
                    let user = response.usersocketid;

                    if (resAction === 'connect' && user !== undefined) {
                        str.get_strings([
                            {key: 'validreceivedtest', component: 'mod_kuet'}
                        ]).done(function(strings) {
                            TestSockets.prototype.setExtraInfo(receivemessageExtraInfoCell, strings[0]);
                        }).fail(notification.exception);
                    } else {
                        TestSockets.prototype.setWarning(receivemessageCell);
                        str.get_strings([
                            {key: 'invalidreceivedtest', component: 'mod_kuet'}
                        ]).done(function(strings) {
                            let err = ' Expected: Action => connect; user => value, and we received: ' + ' Action: '
                            + resAction + ' & user: ' + user;
                            TestSockets.prototype.setExtraInfo(receivemessageExtraInfoCell, strings[0] + err);
                        }).fail(notification.exception);
                    }
                } else {
                    TestSockets.prototype.setError(receivemessageCell);
                    str.get_strings([
                        {key: 'nodatareceivedtest', component: 'mod_kuet'}
                    ]).done(function(strings) {
                        TestSockets.prototype.setExtraInfo(receivemessageExtraInfoCell, strings[0]);
                    }).fail(notification.exception);
                }
            };
        }
    };

    TestSockets.prototype.sendMessageSocket = function(msg) {
        try {
            this.webSocket.send(msg);
            TestSockets.prototype.setValid(sendmessageCell);
            str.get_strings([
                {key: 'validsendedtest', component: 'mod_kuet'}
            ]).done(function(strings) {
                TestSockets.prototype.setExtraInfo(sendmessageExtraInfoCell, strings[0]);
            }).fail(notification.exception);
        } catch(error) {
            TestSockets.prototype.setError(sendmessageCell);
            str.get_strings([
                {key: 'invalidsendedtest', component: 'mod_kuet'}
            ]).done(function(strings) {
                TestSockets.prototype.setExtraInfo(sendmessageExtraInfoCell, strings[0]);
            }).fail(notification.exception);
        }
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

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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 *
 * @module    mod_jqshow/testssl
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
                {key: 'connectionclosed', component: 'mod_jqshow'}
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

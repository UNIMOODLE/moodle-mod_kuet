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
 * @module    mod_kuet/check_activesession
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
"use strict";

import Ajax from 'core/ajax';
import Notification from 'core/notification';

let SERVICES = {
    GETACTIVESESSION: 'mod_kuet_getactivesession'
};

let cmId;
let jqshowId;

/**
 * @constructor
 * @param {int} cmid
 * @param {int} jqshowid
 */
function CheckActiveSession(cmid, jqshowid) {
    cmId = cmid;
    jqshowId = jqshowid;
    setInterval(this.checkActive, 5000);
}

CheckActiveSession.prototype.checkActive = function() {
    let request = {
        methodname: SERVICES.GETACTIVESESSION,
        args: {
            cmid: cmId,
            jqshowid: jqshowId
        }
    };
    Ajax.call([request])[0].done(function(response) {
        if (response.active !== 0) {
            let sessionUrl = new URL(M.cfg.wwwroot + '/mod/kuet/session.php');
            sessionUrl.searchParams.set('cmid', cmId);
            sessionUrl.searchParams.set('sid', response.active);
            window.location.href = sessionUrl.href;
        }
    }).fail(Notification.exception);
};

export const checkActiveSession = (cmid, jqshowid) => {
    return new CheckActiveSession(cmid, jqshowid);
};

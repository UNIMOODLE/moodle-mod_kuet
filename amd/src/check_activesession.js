"use strict";

import Ajax from 'core/ajax';
import Notification from 'core/notification';

let SERVICES = {
    GETACTIVESESSION: 'mod_jqshow_getactivesession'
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
            let sessionUrl = new URL(M.cfg.wwwroot + '/mod/jqshow/session.php');
            sessionUrl.searchParams.set('cmid', cmId);
            sessionUrl.searchParams.set('sid', response.active);
            window.location.href = sessionUrl.href;
        }
    }).fail(Notification.exception);
};

export const checkActiveSession = (cmid, jqshowid) => {
    return new CheckActiveSession(cmid, jqshowid);
};

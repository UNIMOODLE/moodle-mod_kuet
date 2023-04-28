"use strict";
/* eslint-disable no-unused-vars */ // TODO remove.

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';

let REGION = {
    LISTRESULTS: '[data-region="list-results"]'
};

let SERVICES = {
    GETLISTRESULTS: 'mod_jqshow_getlistresults'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    LISTRESULTS: 'mod_jqshow/session/listresults'
};

let cmId;
let sId;

/**
 * @constructor
 * @param {String} selector
 */
function ProgrammedMode(selector) {
    this.node = jQuery(selector);
    this.initProgrammedMode();
}

/** @type {jQuery} The jQuery node for the page region. */
ProgrammedMode.prototype.node = null;

ProgrammedMode.prototype.initProgrammedMode = function() {
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    setInterval(this.reloadList, 20000);
};

ProgrammedMode.prototype.reloadList = function() {
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        let identifier = jQuery(REGION.LISTRESULTS);
        identifier.append(html);
        let request = {
            methodname: SERVICES.GETLISTRESULTS,
            args: {
                sid: sId,
                cmid: cmId
            }
        };
        Ajax.call([request])[0].done(function(response) {
            // eslint-disable-next-line promise/always-return
            Templates.render(TEMPLATES.LISTRESULTS, response).then((html) => {
                identifier.html(html);
            }).fail(Notification.exception);
        }).fail(Notification.exception);
    });
};

export const initProgrammedMode = (selector) => {
    return new ProgrammedMode(selector);
};

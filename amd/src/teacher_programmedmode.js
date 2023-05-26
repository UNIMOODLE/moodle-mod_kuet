"use strict";

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {get_strings as getStrings} from 'core/str';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';

let REGION = {
    LISTRESULTS: '[data-region="list-results"]'
};

let ACTION = {
    ENDSESSION: '[data-action="end_session"]'
};

let SERVICES = {
    GETLISTRESULTS: 'mod_jqshow_getlistresults',
    FINISHSESSION: 'mod_jqshow_finishsession'
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
    jQuery(ACTION.ENDSESSION).on('click', this.finishSession);
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

ProgrammedMode.prototype.finishSession = function(e) {
    e.preventDefault();
    e.stopPropagation();
    const stringkeys = [
        {key: 'end_session', component: 'mod_jqshow'},
        {key: 'end_session_desc', component: 'mod_jqshow'},
        {key: 'confirm', component: 'mod_jqshow'}
    ];
    getStrings(stringkeys).then((langStrings) => {
        const title = langStrings[0];
        const confirmMessage = langStrings[1];
        const buttonText = langStrings[2];
        return ModalFactory.create({
            title: title,
            body: confirmMessage,
            type: ModalFactory.types.SAVE_CANCEL
        }).then(modal => {
            modal.setSaveButtonText(buttonText);
            modal.getRoot().on(ModalEvents.save, () => {
                let request = {
                    methodname: SERVICES.FINISHSESSION,
                    args: {
                        cmid: cmId,
                        sessionid: sId
                    }
                };
                Ajax.call([request])[0].done(function(response) {
                    if (response.finished === true) {
                        window.location.replace(M.cfg.wwwroot + '/mod/jqshow/view.php?id=' + cmId);
                    }
                }).fail(Notification.exception);
            });
            modal.getRoot().on(ModalEvents.hidden, () => {
                modal.destroy();
            });
            return modal;
        });
    }).done(function(modal) {
        modal.show();
        // eslint-disable-next-line no-restricted-globals
    }).fail(Notification.exception);
};

export const initProgrammedMode = (selector) => {
    return new ProgrammedMode(selector);
};

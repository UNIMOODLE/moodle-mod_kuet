"use strict";

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {get_strings as getStrings} from 'core/str';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';

let REGION = {
    LISTRESULTS: '[data-region="list-results"]',
    RACERESULTS: '[data-region="race-results"]'
};

let ACTION = {
    ENDSESSION: '[data-action="end_session"]'
};

let SERVICES = {
    GETLISTRESULTS: 'mod_jqshow_getlistresults',
    GETGROUPLISTRESULTS: 'mod_jqshow_getgrouplistresults',
    GETRACERESULTS: 'mod_jqshow_getraceresults',
    FINISHSESSION: 'mod_jqshow_finishsession'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    LISTRESULTS: 'mod_jqshow/session/listresults',
    RACERESULTS: 'mod_jqshow/session/raceresults'
};

let cmId;
let sId;
let groupMode = false;

/**
 * @constructor
 * @param {String} selector
 * @param {boolean} racemode
 */
function ProgrammedMode(selector, racemode) {
    this.node = jQuery(selector);
    this.initProgrammedMode();
    jQuery(ACTION.ENDSESSION).on('click', this.finishSession);
    if (racemode) {
        this.raceMode();
        setInterval(this.reloadRace, 5000);
    }
}

/** @type {jQuery} The jQuery node for the page region. */
ProgrammedMode.prototype.node = null;

ProgrammedMode.prototype.initProgrammedMode = function() {
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    groupMode = Boolean(this.node.attr('data-groupmode'));
    setInterval(this.reloadList, 20000);
};

ProgrammedMode.prototype.reloadList = function() {
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        let identifier = jQuery(REGION.LISTRESULTS);
        identifier.append(html);
        let methodname = SERVICES.GETLISTRESULTS;
        if (groupMode === true) {
            methodname = SERVICES.GETGROUPLISTRESULTS;
        }
        let request = {
            methodname: methodname,
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

ProgrammedMode.prototype.raceMode = function() {
    let scrollUsers = document.querySelector('#content-users');
    let questions = document.querySelectorAll('.content-responses');
    scrollUsers.addEventListener('scroll', function() {
        questions.forEach((question) => {
            question.scrollTop = scrollUsers.scrollTop;
        });
    }, {passive: true});
};

ProgrammedMode.prototype.reloadRace = function() {
    let identifier = jQuery(REGION.RACERESULTS);
    let request = {
        methodname: SERVICES.GETRACERESULTS,
        args: {
            sid: sId,
            cmid: cmId
        }
    };
    Ajax.call([request])[0].done(function(response) {
        response.programmedmode = true;
        // eslint-disable-next-line promise/always-return
        Templates.render(TEMPLATES.RACERESULTS, response).then((html) => {
            let scrollUsersTop = document.querySelector('#content-users').scrollTop;
            let scrollQuestionsLeft = document.querySelector('#questions-list').scrollLeft;
            identifier.html(html);
            let newScrollUsers = document.querySelector('#content-users');
            let newScrollQuestions = document.querySelector('#questions-list');
            ProgrammedMode.prototype.raceMode();
            newScrollUsers.scrollTop = scrollUsersTop;
            newScrollQuestions.scrollLeft = scrollQuestionsLeft;
        }).fail(Notification.exception);
    }).fail(Notification.exception);
};

export const initProgrammedMode = (selector, racemode = false) => {
    return new ProgrammedMode(selector, racemode);
};

"use strict";
/* eslint-disable no-unused-vars */

import jQuery from 'jquery';
import {get_strings as getStrings} from 'core/str';
import Notification from 'core/notification';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';

let REGION = {
    CONTROLPANEL: '[data-region="teacher_control_panel"]', // This root.
    JUMPTOINPUT: '[data-input="jumpto"]',
};

let ACTION = {
    NEXT: '[data-action="next"]',
    PAUSE: '[data-action="pause"]',
    PLAY: '[data-action="play"]',
    RESEND: '[data-action="resend"]',
    JUMP: '[data-action="jump"]',
    FINISHQUESTION: '[data-action="finishquestion"]',
    SHOWHIDE_RESPONSES: '[data-action="showhide_responses"]',
    SHOWHIDE_STATISTICS: '[data-action="showhide_statistics"]',
    SHOWHIDE_FEEDBACK: '[data-action="showhide_feedback"]',
    IMPROVISE: '[data-action="improvise"]',
    VOTE: '[data-action="vote"]',
    ENDSESSION: '[data-action="endsession"]'
};

TeacherControlPanel.prototype.root = null;
TeacherControlPanel.prototype.jqid = null;

/**
 * @constructor
 * @param {String} region
 * @param {int} jqid
 */
function TeacherControlPanel(region, jqid) {
    this.root = jQuery(region);
    this.jqid = jqid;
    this.initControlPanel();
    finishquestionEvent = new Event('teacherQuestionEnd_' + this.jqid);
}

const nextEvent = new Event('nextQuestion');
const pauseEvent = new Event('pauseQuestionSelf');
const playEvent = new Event('playQuestionSelf');
const resendEvent = new Event('resendSelf');
const finishquestionEventSelf = new Event('teacherQuestionEndSelf');
const endSession = new Event('endSession');
let finishquestionEvent = null;

TeacherControlPanel.prototype.initControlPanel = function() {
    this.root.find(ACTION.NEXT).on('click', this.next);
    this.root.find(ACTION.PAUSE).on('click', this.pause);
    this.root.find(ACTION.PLAY).on('click', this.play);
    this.root.find(ACTION.RESEND).on('click', this.resend);
    this.root.find(ACTION.JUMP).on('click', this.jump);
    this.root.find(REGION.JUMPTOINPUT).on('keyup', (e) => {
        if (e.key === 'Enter' || e.keyCode === 13) {
            TeacherControlPanel.prototype.jump();
        }
    });
    this.root.find(ACTION.FINISHQUESTION).on('click', this.finishquestion);
    this.root.find(ACTION.ENDSESSION).on('click', this.endsession);
};

// TODO prevent default and stop propagations.

TeacherControlPanel.prototype.next = function() {
    dispatchEvent(nextEvent);
};

TeacherControlPanel.prototype.pause = function(e) {
    dispatchEvent(pauseEvent);
    jQuery(ACTION.PAUSE).addClass('d-none');
    jQuery(ACTION.PLAY).removeClass('d-none');
};

TeacherControlPanel.prototype.play = function(e) {
    dispatchEvent(playEvent);
    jQuery(ACTION.PAUSE).removeClass('d-none');
    jQuery(ACTION.PLAY).addClass('d-none');
};

TeacherControlPanel.prototype.resend = function() {
    dispatchEvent(resendEvent);
};

TeacherControlPanel.prototype.jump = function() {
    let numquestion = jQuery(REGION.JUMPTOINPUT).data('numquestions');
    let value = jQuery(REGION.JUMPTOINPUT).val();
    if (value > numquestion || value < 1) {
        const stringkeys = [
            {key: 'jump', component: 'mod_jqshow'},
            {key: 'jumpto_error', component: 'mod_jqshow', param: numquestion},
            {key: 'confirm', component: 'mod_jqshow'}
        ];
        getStrings(stringkeys).then((langStrings) => {
            return ModalFactory.create({
                title: langStrings[0],
                body: langStrings[1],
                type: ModalFactory.types.CANCEL
            }).then(modal => {
                modal.getRoot().on(ModalEvents.hidden, () => {
                    jQuery(REGION.JUMPTOINPUT).val('');
                    modal.destroy();
                });
                return modal;
            });
        }).done(function(modal) {
            modal.show();
        }).fail(Notification.exception);
    } else {
        let jumpEvent = new CustomEvent('jumpTo', {
            "detail": {"jumpTo": value}
        });
        dispatchEvent(jumpEvent);
    }
};

TeacherControlPanel.prototype.finishquestion = function() {
    dispatchEvent(finishquestionEventSelf);
    dispatchEvent(finishquestionEvent);
};

TeacherControlPanel.prototype.endsession = function() {
    dispatchEvent(endSession);
};

export const teacherControlPanel = (region, jqid) => {
    return new TeacherControlPanel(region, jqid);
};

"use strict";
/* eslint-disable no-unused-vars */

import jQuery from 'jquery';

let REGION = {
    CONTROLPANEL: '[data-region="teacher_control_panel"]', // This root.
};

let ACTION = {
    NEXT: '[data-action="next"]',
    PAUSE: '[data-action="pause"]',
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
const pauseEvent = new Event('pauseTime');
const resendEvent = new Event('resend');
const jumpEvent = new Event('jumpTo');
const finishquestionEventSelf = new Event('teacherQuestionEndSelf');
const endSession = new Event('endSession');
let finishquestionEvent = null;

TeacherControlPanel.prototype.initControlPanel = function() {
    this.root.find(ACTION.NEXT).on('click', this.next);
    this.root.find(ACTION.PAUSE).on('click', this.pause);
    this.root.find(ACTION.RESEND).on('click', this.resend);
    this.root.find(ACTION.JUMP).on('click', this.jump);
    this.root.find(ACTION.FINISHQUESTION).on('click', this.finishquestion);
    this.root.find(ACTION.ENDSESSION).on('click', this.endsession);
};

TeacherControlPanel.prototype.next = function() {
    dispatchEvent(nextEvent);
};

TeacherControlPanel.prototype.pause = function() {
    dispatchEvent(pauseEvent);
};

TeacherControlPanel.prototype.resend = function() {
    dispatchEvent(resendEvent);
};

TeacherControlPanel.prototype.jump = function() {
    dispatchEvent(jumpEvent);
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

"use strict";
/* eslint-disable no-unused-vars */

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import {get_strings as getStrings} from 'core/str';
import Notification from 'core/notification';

let ACTION = {
    SEND_RESPONSE: '[data-action="send-response"]',
};

let REGION = {
    ROOT: '[data-region="question-content"]',
    MATCH: '[data-region="shortanswer"]',
    LOADING: '[data-region="overlay-icon-container"]',
    NEXT: '[data-action="next-question"]',
    TIMER: '[data-region="question-timer"]',
    SECONDS: '[data-region="seconds"]',
    CONTENTFEEDBACKS: '[data-region="containt-feedbacks"]',
    FEEDBACK: '[data-region="statement-feedback"]',
    FEEDBACKANSWER: '[data-region="answer-feedback"]',
    FEEDBACKBACGROUND: '[data-region="feedback-background"]',
    INPUTANSWER: '#userShortAnswer',
    ANSWERHELP: '#userShortAnswerHelp',
};

let SERVICES = {
    REPLY: 'mod_jqshow_shortanswer'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading'
};

let cmId;
let sId;
let questionid;
let jqshowId;
let jqid;
let questionEnd = false;
let correctAnswers = null;
let showQuestionFeedback = false;
let manualMode = false;
let strings = [];

/** @type {jQuery} The jQuery node for the page region. */
ShortAnswer.prototype.node = null;
ShortAnswer.prototype.endTimer = new Event('endTimer');
ShortAnswer.prototype.studentQuestionEnd = new Event('studentQuestionEnd');

/**
 * @constructor
 * @param {String} selector
 * @param {Boolean} showquestionfeedback
 * @param {Boolean} manualmode
 * @param {String} jsonresponse
 */
function ShortAnswer(selector, showquestionfeedback = false, manualmode = false, jsonresponse = '') {
    this.node = jQuery(selector);
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    questionid = this.node.attr('data-questionid');
    jqshowId = this.node.attr('data-jqshowid');
    jqid = this.node.attr('data-jqid');
    showQuestionFeedback = showquestionfeedback;
    manualMode = manualmode;
    questionEnd = false;
    if (jsonresponse !== '') {
        this.answered(jsonresponse);
        if (manualMode === false || jQuery('.modal-body').length) {
            questionEnd = true;
            if (showQuestionFeedback === true) {
                this.showFeedback();
            }
            this.showAnswers();
        }
    }
    const stringkeys = [
        {key: 'notallowedspecialchars', component: 'mod_jqshow'},
        {key: 'notallowedpasting', component: 'mod_jqshow'}
    ];
    getStrings(stringkeys).then((langStrings) => {
        strings = langStrings;
    }).done(function(modal) {
        ShortAnswer.prototype.initShortAnswer();
    }).fail(Notification.exception);
}

ShortAnswer.prototype.initShortAnswer = function() {
    jQuery(REGION.INPUTANSWER).on('keydown', ShortAnswer.prototype.checkCharacter.bind(this));
};

ShortAnswer.prototype.checkCharacter = function(e) {
    let key = (document.all) ? e.keyCode : e.which;
    let ctrl = e.ctrlKey ? e.ctrlKey : ((key === 17));
    if (key === 86 && ctrl) { // Pasting text.
        jQuery(REGION.ANSWERHELP).text(strings[1]);
        return false;
    }
    let finalKey = String.fromCharCode(key);
    // eslint-disable-next-line no-console
    console.log(key, finalKey);
    switch (key) {
        case 8: // Backspace.
        case 9: // Tab.
        case 192: // The Ñ.
        case 16: // Shift.
        case 17: // Ctrl.
        case 18: // Alt.
        case 32: // Space.
        case 35: // End.
        case 37: // Left.
        case 38: // Up.
        case 39: // Right.
        case 40: // Down.
        case 45: // Insert.
        case 46: // Delete.
        case 91: // Left windows.
        case 92: // Right windows.
        case 96:
        case 97:
        case 98:
        case 99:
        case 100:
        case 101:
        case 102:
        case 103:
        case 104:
        case 105:
        case 106:
        case 107:
        case 109:
        case 110:
        case 111: // Num Pad.
        case 186: // Semi-colon.
        case 187: // Equal sign.
        case 188: // Comma.
        case 189: // Dash.
        case 190: // Point.
        case 191: // Forward slash.
            jQuery(REGION.ANSWERHELP).text('');
            return true;
        case 33: // Page up.
        case 34: // Page down.
        case 219: // Interrogation.
        case 221: // Exclamation.
        case 226: // Interrogation.
            jQuery(REGION.ANSWERHELP).text(strings[0]);
            return false;
    }
    let filter = 'abcdefghijklmnñopqrstuvwxyzABCDEFGHIJKLMNÑOPQRSTUVWXYZ1234567890()&%$@ºª€=[];:\',._-Çç';
    // eslint-disable-next-line no-console
    console.log(finalKey, filter.indexOf(finalKey) > 0);
    if (filter.indexOf(finalKey) > 0) {
        jQuery(REGION.ANSWERHELP).text('');
        return true;
    } else {
        jQuery(REGION.ANSWERHELP).text(strings[0]);
        return false;
    }
};

ShortAnswer.prototype.answered = function(jsonresponse) {
    questionEnd = true;
    jQuery(ACTION.SEND_RESPONSE).addClass('d-none');
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(REGION.INPUTANSWER).css('z-index', 3).attr('disabled', 'disabled');
};

ShortAnswer.prototype.showFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'block', 'z-index': 3});
    }
};

ShortAnswer.prototype.showAnswers = function() {
    if (questionEnd === true) {
        // TODO obtain the possible answers, and paint them in a list.
    }
};

export const initShortAnswer = (selector, showquestionfeedback, manualmode, jsonresponse) => {
    return new ShortAnswer(selector, showquestionfeedback, manualmode, jsonresponse);
};

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
    FEEDBACKICONS: '.feedback-icons',
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
    jQuery(ACTION.SEND_RESPONSE).on('click', ShortAnswer.prototype.reply);
    ShortAnswer.prototype.initEvents();
};

ShortAnswer.prototype.initEvents = function() {
    addEventListener('timeFinish', () => {
        ShortAnswer.prototype.reply();
    }, {once: true});
    if (manualMode !== false) {
        addEventListener('teacherQuestionEnd_' + jqid, (e) => {
            // eslint-disable-next-line no-console
            console.log('teacherQuestionEnd_' + jqid);
            if (questionEnd !== true) {
                ShortAnswer.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('[data-answerid="' + statistic.answerid + '"] .numberofreplies').html(statistic.numberofreplies);
            });
        }, {once: true});
        addEventListener('pauseQuestion_' + jqid, () => {
            ShortAnswer.prototype.pauseQuestion();
        }, false);
        addEventListener('playQuestion_' + jqid, () => {
            ShortAnswer.prototype.playQuestion();
        }, false);
        addEventListener('showAnswers_' + jqid, () => {
            ShortAnswer.prototype.showAnswers();
        }, false);
        addEventListener('hideAnswers_' + jqid, () => {
            ShortAnswer.prototype.hideAnswers();
        }, false);
        addEventListener('showStatistics_' + jqid, () => {
            ShortAnswer.prototype.showStatistics();
        }, false);
        addEventListener('hideStatistics_' + jqid, () => {
            ShortAnswer.prototype.hideStatistics();
        }, false);
        addEventListener('showFeedback_' + jqid, () => {
            ShortAnswer.prototype.showFeedback();
        }, false);
        addEventListener('hideFeedback_' + jqid, () => {
            ShortAnswer.prototype.hideFeedback();
        }, false);
    }

    window.onbeforeunload = function() {
        if (jQuery(REGION.SECONDS).length > 0 && questionEnd === false) {
            ShortAnswer.prototype.reply();
            return 'Because the question is overdue and an attempt has been made to reload the page,' +
                ' the question has remained unanswered.';
        }
    };
};

ShortAnswer.prototype.reply = function() {
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        jQuery(REGION.ROOT).append(html);
        dispatchEvent(ShortAnswer.prototype.endTimer);
        let timeLeft = parseInt(jQuery(REGION.SECONDS).text());
        let responseText = jQuery(REGION.INPUTANSWER).val();
        let request = {
            methodname: SERVICES.REPLY,
            args: {
                responsetext: responseText,
                sessionid: sId,
                jqshowid: jqshowId,
                cmid: cmId,
                questionid: questionid,
                jqid: jqid,
                timeleft: timeLeft || 0,
                preview: false
            }
        };
        Ajax.call([request])[0].done(function(response) {
            if (response.reply_status === true) {
                questionEnd = true;
                ShortAnswer.prototype.answered(response);
                dispatchEvent(ShortAnswer.prototype.studentQuestionEnd);
                if (jQuery('.modal-body').length) { // Preview.
                    ShortAnswer.prototype.showAnswers();
                    if (showQuestionFeedback === true) {
                        ShortAnswer.prototype.showFeedback();
                    }
                } else {
                    if (manualMode === false) {
                        ShortAnswer.prototype.showAnswers();
                        if (showQuestionFeedback === true) {
                            ShortAnswer.prototype.showFeedback();
                        }
                    }
                }
            } else {
                alert('error');
            }
            jQuery(REGION.LOADING).remove();
        }).fail(Notification.exception);
    });
};

ShortAnswer.prototype.answered = function(response) {
    questionEnd = true;
    jQuery(ACTION.SEND_RESPONSE).addClass('d-none');
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(REGION.INPUTANSWER).css('z-index', 3).attr('disabled', 'disabled');
    jQuery(REGION.NEXT).removeClass('d-none');
    jQuery(REGION.ANSWERHELP).text(response.possibleanswers);
    if (response.result === 0) {
        jQuery(REGION.FEEDBACKICONS + ' .correct').remove();
        jQuery(REGION.FEEDBACKICONS + ' .partially').remove();
    }
    if (response.result === 1) {
        jQuery(REGION.FEEDBACKICONS + ' .incorrect').remove();
        jQuery(REGION.FEEDBACKICONS + ' .partially').remove();
    }
    if (response.result === 2) {
        jQuery(REGION.FEEDBACKICONS + ' .incorrect').remove();
        jQuery(REGION.FEEDBACKICONS + ' .correct').remove();
    }
};

ShortAnswer.prototype.showFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'block', 'z-index': 3});
    }
};

ShortAnswer.prototype.showAnswers = function() {
    if (questionEnd === true) {
        // TODO obtain the possible answers, and paint them in a list.
        jQuery(REGION.ANSWERHELP).removeClass('d-none').css({'z-index': 3});
        jQuery(REGION.FEEDBACKICONS).removeClass('d-none').css({'z-index': 3});
    }
};

ShortAnswer.prototype.hideAnswers = function() {
    if (questionEnd === true) {
        jQuery(REGION.ANSWERHELP).addClass('d-none');
        jQuery(REGION.FEEDBACKICONS).addClass('d-none');
    }
};

export const initShortAnswer = (selector, showquestionfeedback, manualmode, jsonresponse) => {
    return new ShortAnswer(selector, showquestionfeedback, manualmode, jsonresponse);
};

"use strict";


import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import mEvent from 'core/event';

let ACTION = {
    SEND_RESPONSE: '[data-action="send-response"]',
};

let REGION = {
    ROOT: '[data-region="question-content"]',
    LOADING: '[data-region="overlay-icon-container"]',
    NEXT: '[data-action="next-question"]',
    TIMER: '[data-region="question-timer"]',
    SECONDS: '[data-region="seconds"]',
    CONTENTFEEDBACKS: '[data-region="containt-feedbacks"]',
    FEEDBACK: '[data-region="statement-feedback"]',
    FEEDBACKANSWER: '[data-region="answer-feedback"]',
    FEEDBACKBACGROUND: '[data-region="feedback-background"]',
    INPUTANSWER: '#userNumerical',
    ANSWERHELP: '#userNumericalHelp',
    FEEDBACKICONS: '.feedback-icons',
    UNITSCONTENT: '#unitsContent'
};

let SERVICES = {
    REPLY: 'mod_jqshow_numerical'
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
let showQuestionFeedback = false;
let manualMode = false;
let hasUnits = false;

/** @type {jQuery} The jQuery node for the page region. */
Numerical.prototype.node = null;
Numerical.prototype.endTimer = new Event('endTimer');
Numerical.prototype.studentQuestionEnd = new Event('studentQuestionEnd');

/**
 * @constructor
 * @param {String} selector
 * @param {Boolean} showquestionfeedback
 * @param {Boolean} manualmode
 * @param {String} jsonresponse
 */
function Numerical(selector, showquestionfeedback = false, manualmode = false, jsonresponse = '') {
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
        this.answered(JSON.parse(jsonresponse));
        if (manualMode === false || jQuery('.modal-body').length) {
            questionEnd = true;
            if (showQuestionFeedback === true) {
                this.showFeedback();
            }
            this.showAnswers();
        }
    }
    Numerical.prototype.initNumerical();
}

Numerical.prototype.initNumerical = function() {
    jQuery(ACTION.SEND_RESPONSE).off('click');
    jQuery(ACTION.SEND_RESPONSE).on('click', Numerical.prototype.reply);
    if (jQuery(REGION.UNITSCONTENT).length) {
        hasUnits = true;
    }
    Numerical.prototype.initEvents();
};

Numerical.prototype.initEvents = function() {
    addEventListener('timeFinish', Numerical.prototype.reply, {once: true});
    if (manualMode !== false) {
        addEventListener('teacherQuestionEnd_' + jqid, (e) => {
            if (questionEnd !== true) {
                Numerical.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('[data-answerid="' + statistic.answerid + '"] .numberofreplies').html(statistic.numberofreplies);
            });
        }, {once: true});
        addEventListener('pauseQuestion_' + jqid, () => {
            Numerical.prototype.pauseQuestion();
        }, false);
        addEventListener('playQuestion_' + jqid, () => {
            Numerical.prototype.playQuestion();
        }, false);
        addEventListener('showAnswers_' + jqid, () => {
            Numerical.prototype.showAnswers();
        }, false);
        addEventListener('hideAnswers_' + jqid, () => {
            Numerical.prototype.hideAnswers();
        }, false);
        addEventListener('showStatistics_' + jqid, () => {
            Numerical.prototype.showStatistics();
        }, false);
        addEventListener('hideStatistics_' + jqid, () => {
            Numerical.prototype.hideStatistics();
        }, false);
        addEventListener('showFeedback_' + jqid, () => {
            Numerical.prototype.showFeedback();
        }, false);
        addEventListener('hideFeedback_' + jqid, () => {
            Numerical.prototype.hideFeedback();
        }, false);
    }

    window.onbeforeunload = function() {
        if (jQuery(REGION.SECONDS).length > 0 && questionEnd === false) {
            Numerical.prototype.reply();
            return 'Because the question is overdue and an attempt has been made to reload the page,' +
                ' the question has remained unanswered.';
        }
    };
};

Numerical.prototype.removeEvents = function() {
    removeEventListener('timeFinish', Numerical.prototype.reply, {once: true});
};

Numerical.prototype.reply = function() {
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        jQuery(REGION.ROOT).append(html);
        dispatchEvent(Numerical.prototype.endTimer);
        Numerical.prototype.removeEvents();
        let timeLeft = parseInt(jQuery(REGION.SECONDS).text());
        let responseNum = jQuery(REGION.INPUTANSWER).val();
        let unit = '0';
        let multiplier = '';
        if (hasUnits) {
            let contentUnit = jQuery(REGION.UNITSCONTENT);
            if (contentUnit.is('div')) {
                unit = contentUnit.find('input[type="radio"]:checked').attr('name');
                multiplier = contentUnit.find('input[type="radio"]:checked').data('multiplier');
            } else if (contentUnit.is('select')) {
                unit = contentUnit.find(':selected').val();
                multiplier = contentUnit.find(':selected').data('multiplier');
            }
        }
        let request = {
            methodname: SERVICES.REPLY,
            args: {
                responsenum: responseNum === undefined || responseNum === null ? '' : responseNum,
                unit: unit === undefined ? '0' : unit,
                multiplier: multiplier === undefined ? '0' : multiplier,
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
                Numerical.prototype.answered(response);
                dispatchEvent(Numerical.prototype.studentQuestionEnd);
                if (jQuery('.modal-body').length) { // Preview.
                    Numerical.prototype.showAnswers();
                    if (showQuestionFeedback === true) {
                        Numerical.prototype.showFeedback();
                    }
                } else {
                    if (manualMode === false) {
                        Numerical.prototype.showAnswers();
                        if (showQuestionFeedback === true) {
                            Numerical.prototype.showFeedback();
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

Numerical.prototype.answered = function(response) {
    questionEnd = true;
    if (response.hasfeedbacks) {
        jQuery(REGION.FEEDBACK).html(response.statment_feedback);
        jQuery(REGION.FEEDBACKANSWER).html(response.answer_feedback);
    }
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
    mEvent.notifyFilterContentUpdated(document.querySelector(REGION.CONTENTFEEDBACKS));
};

Numerical.prototype.pauseQuestion = function() {
    dispatchEvent(new Event('pauseQuestion'));
    jQuery(REGION.TIMER).css('z-index', 3);
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(ACTION.REPLY).css('pointer-events', 'none');
};

Numerical.prototype.playQuestion = function() {
    if (questionEnd !== true) {
        dispatchEvent(new Event('playQuestion'));
        jQuery(REGION.TIMER).css('z-index', 1);
        jQuery(REGION.FEEDBACKBACGROUND).css('display', 'none');
        jQuery(ACTION.REPLY).css('pointer-events', 'auto');
    }
};

Numerical.prototype.showFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'block', 'z-index': 3});
    }
};

Numerical.prototype.hideFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'none', 'z-index': 0});
    }
};

Numerical.prototype.showAnswers = function() {
    if (questionEnd === true) {
        jQuery(REGION.ANSWERHELP).removeClass('d-none').css({'z-index': 3});
        jQuery(REGION.FEEDBACKICONS).removeClass('d-none').css({'z-index': 3});
    }
};

Numerical.prototype.hideAnswers = function() {
    if (questionEnd === true) {
        jQuery(REGION.ANSWERHELP).addClass('d-none');
        jQuery(REGION.FEEDBACKICONS).addClass('d-none');
    }
};

export const initNumerical = (selector, showquestionfeedback, manualmode, jsonresponse) => {
    return new Numerical(selector, showquestionfeedback, manualmode, jsonresponse);
};

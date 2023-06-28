"use strict";

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Templates from 'core/templates';

let ACTION = {
    REPLY: '[data-action="multichoice-answer"]'
};

let REGION = {
    MULTICHOICE: '[data-region="multichoice"]',
    LOADING: '[data-region="overlay-icon-container"]',
    CONTENTFEEDBACKS: '[data-region="containt-feedbacks"]',
    FEEDBACK: '[data-region="statement-feedback"]',
    FEEDBACKANSWER: '[data-region="answer-feedback"]',
    FEEDBACKBACGROUND: '[data-region="feedback-background"]',
    STATEMENTTEXT: '[data-region="statement-text"]',
    TIMER: '[data-region="question-timer"]',
    SECONDS: '[data-region="seconds"]',
    NEXT: '[data-action="next-question"]'
};

let SERVICES = {
    REPLY: 'mod_jqshow_multichoice'
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

/**
 * @constructor
 * @param {String} selector
 * @param {String} jsonresponse
 */
function MultiChoice(selector, jsonresponse = '') {
    this.node = jQuery(selector);
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    questionid = this.node.attr('data-questionid');
    jqshowId = this.node.attr('data-jqshowid');
    jqid = this.node.attr('data-jqid');
    questionEnd = false;
    if (jsonresponse !== '') {
        this.answered(JSON.parse(jsonresponse));
    }
    this.initMultichoice();
}

/** @type {jQuery} The jQuery node for the page region. */
MultiChoice.prototype.node = null;
MultiChoice.prototype.endTimer = new Event('endTimer');
MultiChoice.prototype.studentQuestionEnd = new Event('studentQuestionEnd');

MultiChoice.prototype.initMultichoice = function() {
    this.node.find(ACTION.REPLY).on('click', this.reply.bind(this));
    let that = this;
    addEventListener('timeFinish', () => {
        this.reply();
    }, {once: true});
    addEventListener('teacherQuestionEnd_' + jqid, () => {
        if (questionEnd !== true) {
            this.reply();
        }
    }, {once: true});
    addEventListener('pauseQuestion_' + jqid, () => {
        this.pauseQuestion();
    }, false);
    addEventListener('playQuestion_' + jqid, () => {
        this.playQuestion();
    }, false);
    addEventListener('showAnswers_' + jqid, () => {
        this.showAnswers();
    }, false);
    addEventListener('hideAnswers_' + jqid, () => {
        this.hideAnswers();
    }, false);
    addEventListener('showStatistics_' + jqid, () => {
        this.showStatistics();
    }, false);
    addEventListener('hideStatistics_' + jqid, () => {
        this.hideStatistics();
    }, false);
    addEventListener('showFeedback_' + jqid, () => {
        this.showFeedback();
    }, false);
    addEventListener('hideFeedback_' + jqid, () => {
        this.hideFeedback();
    }, false);
    // TODO test well, and add/replace alternative methods.
    window.addEventListener('beforeunload' + jqid, () => { // TODO delete this listener, not work.
        if (jQuery(REGION.SECONDS).length > 0 && questionEnd === false) {
            that.reply();
            return 'Because the question is overdue and an attempt has been made to reload the page,' +
                ' the question has remained unanswered.';
        }
    });
    window.onbeforeunload = function() {
        if (jQuery(REGION.SECONDS).length > 0 && questionEnd === false) {
            that.reply();
            return 'Because the question is overdue and an attempt has been made to reload the page,' +
                ' the question has remained unanswered.';
        }
    };
};

MultiChoice.prototype.reply = function(e) {
    let answerId = 0;
    let that = this;
    if (e !== undefined) {
        e.preventDefault();
        e.stopPropagation();
        answerId = jQuery(e.currentTarget).attr('data-answerid');
    }
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        that.node.append(html);
        dispatchEvent(that.endTimer);
        let timeLeft = parseInt(jQuery(REGION.SECONDS).text());
        let request = {
            methodname: SERVICES.REPLY,
            args: {
                answerid: answerId,
                sessionid: sId,
                jqshowid: jqshowId,
                cmid: cmId,
                questionid: questionid,
                timeleft: timeLeft || 0,
                preview: false
            }
        };
        Ajax.call([request])[0].done(function(response) {
            if (response.reply_status === true) {
                if (e !== undefined) {
                    jQuery(e.currentTarget).css({'z-index': 3, 'pointer-events': 'none'});
                }
                that.answered(response);
                questionEnd = true;
                dispatchEvent(that.studentQuestionEnd);
            } else {
                alert('error');
            }
            jQuery(REGION.LOADING).remove();
        }).fail(Notification.exception);
    });
};

MultiChoice.prototype.answered = function(response) {
    questionEnd = true;
    if (response.hasfeedbacks) {
        jQuery(REGION.FEEDBACK).html(response.statment_feedback);
        jQuery(REGION.FEEDBACKANSWER).html(response.answer_feedback);
    }
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(REGION.STATEMENTTEXT).css({'z-index': 3, 'padding': '15px'});
    jQuery(REGION.TIMER).css('z-index', 3);
    jQuery(REGION.NEXT).removeClass('d-none');
    if (response.answerid) {
        jQuery('[data-answerid="' + response.answerid + '"]').css({'z-index': 3, 'pointer-events': 'none'});
    }
    if (response.correct_answers) {
        correctAnswers = response.correct_answers;
        jQuery(REGION.FEEDBACKBACGROUND).css('height', '100%');
    }
};

MultiChoice.prototype.pauseQuestion = function() {
    dispatchEvent(new Event('pauseQuestion'));
    jQuery(REGION.TIMER).css('z-index', 3);
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(ACTION.REPLY).css('pointer-events', 'none');
};

MultiChoice.prototype.playQuestion = function() {
    dispatchEvent(new Event('playQuestion'));
    jQuery(REGION.TIMER).css('z-index', 1);
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'none');
    jQuery(ACTION.REPLY).css('pointer-events', 'auto');
};

MultiChoice.prototype.showAnswers = function() {
    if (correctAnswers !== null && questionEnd === true) {
        jQuery('.feedback-icon').css('display', 'flex');
        let correctAnswersSplit = correctAnswers.split(',');
        correctAnswersSplit.forEach((answ) => {
            jQuery('[data-answerid="' + answ + '"] .incorrect').css('display', 'none');
        });
    }
};

MultiChoice.prototype.hideAnswers = function() {
    if (questionEnd === true) {
        jQuery('.feedback-icon').css('display', 'none');
    }
};

MultiChoice.prototype.showStatistics = function() {

};

MultiChoice.prototype.hideStatistics = function() {

};

MultiChoice.prototype.showFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'block', 'z-index': 3});
    }
};

MultiChoice.prototype.hideFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'none', 'z-index': 0});
    }
};

export const initMultiChoice = (selector, jsonresponse) => {
    return new MultiChoice(selector, jsonresponse);
};

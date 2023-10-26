"use strict";

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import mEvent from 'core/event';

let ACTION = {
    SEND_RESPONSE: '[data-action="next-question"]',
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
    FEEDBACKBACGROUND: '[data-region="feedback-background"]'
};

let SERVICES = {
    REPLY: 'mod_jqshow_description'
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

/** @type {jQuery} The jQuery node for the page region. */
Description.prototype.node = null;
Description.prototype.endTimer = new Event('endTimer');
Description.prototype.studentQuestionEnd = new Event('studentQuestionEnd');

/**
 * @constructor
 * @param {String} selector
 * @param {Boolean} showquestionfeedback
 * @param {Boolean} manualmode
 * @param {String} jsonresponse
 */
function Description(selector, showquestionfeedback = false, manualmode = false, jsonresponse = '') {
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
        this.answered(JSON.parse(atob(jsonresponse)));
        if (manualMode === false || jQuery('.modal-body').length) {
            questionEnd = true;
            if (showQuestionFeedback === true) {
                this.showFeedback();
            }
            this.showAnswers();
        }
    }
    Description.prototype.initDescription();
}

Description.prototype.initDescription = function() {
    jQuery(ACTION.SEND_RESPONSE).on('click', Description.prototype.reply);
    Description.prototype.initEvents();
};

Description.prototype.initEvents = function() {
    addEventListener('timeFinish', Description.prototype.reply, {once: true});
    if (manualMode !== false) {
        addEventListener('alreadyAnswered_' + jqid, (ev) => {
            let userid =  jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                Description.prototype.reply();
            }
        }, {once: true});
        addEventListener('teacherQuestionEnd_' + jqid, (e) => {
            if (questionEnd !== true) {
                Description.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('[data-answerid="' + statistic.answerid + '"] .numberofreplies').html(statistic.numberofreplies);
            });
        }, {once: true});
        addEventListener('pauseQuestion_' + jqid, () => {
            Description.prototype.pauseQuestion();
        }, false);
        addEventListener('playQuestion_' + jqid, () => {
            Description.prototype.playQuestion();
        }, false);
        addEventListener('showAnswers_' + jqid, () => {
            Description.prototype.showAnswers();
        }, false);
        addEventListener('hideAnswers_' + jqid, () => {
            Description.prototype.hideAnswers();
        }, false);
        addEventListener('showStatistics_' + jqid, () => {
            Description.prototype.showStatistics();
        }, false);
        addEventListener('hideStatistics_' + jqid, () => {
            Description.prototype.hideStatistics();
        }, false);
        addEventListener('showFeedback_' + jqid, () => {
            Description.prototype.showFeedback();
        }, false);
        addEventListener('hideFeedback_' + jqid, () => {
            Description.prototype.hideFeedback();
        }, false);
        addEventListener('removeEvents', () => {
            Description.prototype.removeEvents();
        }, {once: true});
    }

    window.onbeforeunload = function() {
        if (jQuery(REGION.SECONDS).length > 0 && questionEnd === false) {
            Description.prototype.reply();
            return 'Because the question is overdue and an attempt has been made to reload the page,' +
                ' the question has remained unanswered.';
        }
    };
};

Description.prototype.removeEvents = function() {
    removeEventListener('timeFinish', Description.prototype.reply, {once: true});
    if (manualMode !== false) {
        removeEventListener('alreadyAnswered_' + jqid, (ev) => {
            let userid =  jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                Description.prototype.reply();
            }
        }, {once: true});
        removeEventListener('teacherQuestionEnd_' + jqid, (e) => {
            if (questionEnd !== true) {
                Description.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('[data-answerid="' + statistic.answerid + '"] .numberofreplies').html(statistic.numberofreplies);
            });
        }, {once: true});
        removeEventListener('pauseQuestion_' + jqid, () => {
            Description.prototype.pauseQuestion();
        }, false);
        removeEventListener('playQuestion_' + jqid, () => {
            Description.prototype.playQuestion();
        }, false);
        removeEventListener('showAnswers_' + jqid, () => {
            Description.prototype.showAnswers();
        }, false);
        removeEventListener('hideAnswers_' + jqid, () => {
            Description.prototype.hideAnswers();
        }, false);
        removeEventListener('showStatistics_' + jqid, () => {
            Description.prototype.showStatistics();
        }, false);
        removeEventListener('hideStatistics_' + jqid, () => {
            Description.prototype.hideStatistics();
        }, false);
        removeEventListener('showFeedback_' + jqid, () => {
            Description.prototype.showFeedback();
        }, false);
        removeEventListener('hideFeedback_' + jqid, () => {
            Description.prototype.hideFeedback();
        }, false);
        removeEventListener('removeEvents', () => {
            Description.prototype.removeEvents();
        }, {once: true});
    }
};

Description.prototype.reply = function() {
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        jQuery(REGION.ROOT).append(html);
        dispatchEvent(Description.prototype.endTimer);
        Description.prototype.removeEvents();
        let timeLeft = parseInt(jQuery(REGION.SECONDS).text());
        let request = {
            methodname: SERVICES.REPLY,
            args: {
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
                Description.prototype.answered(response);
                dispatchEvent(Description.prototype.studentQuestionEnd);
                if (jQuery('.modal-body').length) { // Preview.
                    Description.prototype.showAnswers();
                    if (showQuestionFeedback === true) {
                        Description.prototype.showFeedback();
                    }
                } else {
                    if (manualMode === false) {
                        Description.prototype.showAnswers();
                        if (showQuestionFeedback === true) {
                            Description.prototype.showFeedback();
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

Description.prototype.answered = function(response) {
    questionEnd = true;
    if (response.hasfeedbacks) {
        jQuery(REGION.FEEDBACK).html(response.statment_feedback);
        jQuery(REGION.FEEDBACKANSWER).html(response.answer_feedback);
    }
    jQuery(ACTION.SEND_RESPONSE).addClass('d-none');
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(REGION.NEXT).removeClass('d-none');
    let contentFeedbacks = document.querySelector(REGION.CONTENTFEEDBACKS);
    if (contentFeedbacks !== null) {
        mEvent.notifyFilterContentUpdated(document.querySelector(REGION.CONTENTFEEDBACKS));
    }
};

Description.prototype.pauseQuestion = function() {
    dispatchEvent(new Event('pauseQuestion'));
    jQuery(REGION.TIMER).css('z-index', 3);
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(ACTION.REPLY).css('pointer-events', 'none');
};

Description.prototype.playQuestion = function() {
    if (questionEnd !== true) {
        dispatchEvent(new Event('playQuestion'));
        jQuery(REGION.TIMER).css('z-index', 1);
        jQuery(REGION.FEEDBACKBACGROUND).css('display', 'none');
        jQuery(ACTION.REPLY).css('pointer-events', 'auto');
    }
};

Description.prototype.showFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'block', 'z-index': 3});
    }
};

Description.prototype.hideFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'none', 'z-index': 0});
    }
};

Description.prototype.showAnswers = function() {
    if (questionEnd === true) {
        jQuery(REGION.ANSWERHELP).removeClass('d-none').css({'z-index': 3});
    }
};

Description.prototype.hideAnswers = function() {
    if (questionEnd === true) {
        jQuery(REGION.ANSWERHELP).addClass('d-none');
    }
};

export const initDescription = (selector, showquestionfeedback, manualmode, jsonresponse) => {
    return new Description(selector, showquestionfeedback, manualmode, jsonresponse);
};

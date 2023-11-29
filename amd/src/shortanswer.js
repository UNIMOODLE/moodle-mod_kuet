// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos

/**
 *
 * @module    mod_kuet/shortanswer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
    INPUTANSWER: '#userShortAnswer',
    ANSWERHELP: '#userShortAnswerHelp',
    FEEDBACKICONS: '.feedback-icons',
};

let SERVICES = {
    REPLY: 'mod_kuet_shortanswer'
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
        this.answered(JSON.parse(atob(jsonresponse)));
        if (manualMode === false || jQuery('.modal-body').length) {
            questionEnd = true;
            if (showQuestionFeedback === true) {
                this.showFeedback();
            }
            this.showAnswers();
        }
    }
    ShortAnswer.prototype.initShortAnswer();
}

ShortAnswer.prototype.initShortAnswer = function() {
    jQuery(ACTION.SEND_RESPONSE).on('click', ShortAnswer.prototype.reply);
    ShortAnswer.prototype.initEvents();
};

ShortAnswer.prototype.initEvents = function() {
    addEventListener('timeFinish', ShortAnswer.prototype.reply, {once: true});
    if (manualMode !== false) {
        addEventListener('alreadyAnswered_' + jqid, (ev) => {
            let userid =  jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                ShortAnswer.prototype.reply();
            }
        }, {once: true});
        addEventListener('teacherQuestionEnd_' + jqid, (e) => {
            if (questionEnd !== true) {
                ShortAnswer.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('#statistics .correct').attr('aria-valuenow', statistic.correct)
                    .width(statistic.correct + '%').html(statistic.correct + '%');
                jQuery('#statistics .failure').attr('aria-valuenow', statistic.failure)
                    .width(statistic.failure + '%').html(statistic.failure + '%');
                jQuery('#statistics .invalid').attr('aria-valuenow', statistic.invalid)
                    .width(statistic.invalid + '%').html(statistic.invalid + '%');
                jQuery('#statistics .noresponse').attr('aria-valuenow', statistic.noresponse)
                    .width(statistic.noresponse + '%').html(statistic.noresponse + '%');
                jQuery('#statistics .partially').attr('aria-valuenow', statistic.partially)
                    .width(statistic.partially + '%').html(statistic.partially + '%');
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
        addEventListener('removeEvents', () => {
            ShortAnswer.prototype.removeEvents();
        }, {once: true});
    }

    window.onbeforeunload = function() {
        if (jQuery(REGION.SECONDS).length > 0 && questionEnd === false) {
            ShortAnswer.prototype.reply();
            return 'Because the question is overdue and an attempt has been made to reload the page,' +
                ' the question has remained unanswered.';
        }
    };
};

ShortAnswer.prototype.showStatistics = function() {
    if (questionEnd === true) {
        jQuery('#statistics').css({'display': 'block', 'z-index': 3});
    }
};
ShortAnswer.prototype.hideStatistics = function() {
    if (questionEnd === true) {
        jQuery('#statistics').css({'display': 'none', 'z-index': 0});
    }
};

ShortAnswer.prototype.removeEvents = function() {
    removeEventListener('timeFinish', ShortAnswer.prototype.reply, {once: true});
    if (manualMode !== false) {
        removeEventListener('alreadyAnswered_' + jqid, (ev) => {
            let userid =  jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                ShortAnswer.prototype.reply();
            }
        }, {once: true});
        removeEventListener('teacherQuestionEnd_' + jqid, (e) => {
            if (questionEnd !== true) {
                ShortAnswer.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('#statistics .correct').attr('aria-valuenow', statistic.correct)
                    .width(statistic.correct + '%').html(statistic.correct + '%');
                jQuery('#statistics .failure').attr('aria-valuenow', statistic.failure)
                    .width(statistic.failure + '%').html(statistic.failure + '%');
                jQuery('#statistics .invalid').attr('aria-valuenow', statistic.invalid)
                    .width(statistic.invalid + '%').html(statistic.invalid + '%');
                jQuery('#statistics .noresponse').attr('aria-valuenow', statistic.noresponse)
                    .width(statistic.noresponse + '%').html(statistic.noresponse + '%');
                jQuery('#statistics .partially').attr('aria-valuenow', statistic.partially)
                    .width(statistic.partially + '%').html(statistic.partially + '%');
            });
        }, {once: true});
        removeEventListener('pauseQuestion_' + jqid, () => {
            ShortAnswer.prototype.pauseQuestion();
        }, false);
        removeEventListener('playQuestion_' + jqid, () => {
            ShortAnswer.prototype.playQuestion();
        }, false);
        removeEventListener('showAnswers_' + jqid, () => {
            ShortAnswer.prototype.showAnswers();
        }, false);
        removeEventListener('hideAnswers_' + jqid, () => {
            ShortAnswer.prototype.hideAnswers();
        }, false);
        removeEventListener('showStatistics_' + jqid, () => {
            ShortAnswer.prototype.showStatistics();
        }, false);
        removeEventListener('hideStatistics_' + jqid, () => {
            ShortAnswer.prototype.hideStatistics();
        }, false);
        removeEventListener('showFeedback_' + jqid, () => {
            ShortAnswer.prototype.showFeedback();
        }, false);
        removeEventListener('hideFeedback_' + jqid, () => {
            ShortAnswer.prototype.hideFeedback();
        }, false);
        removeEventListener('removeEvents', () => {
            ShortAnswer.prototype.removeEvents();
        }, {once: true});
    }
};

ShortAnswer.prototype.reply = function() {
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        jQuery(REGION.ROOT).append(html);
        dispatchEvent(ShortAnswer.prototype.endTimer);
        removeEventListener('timeFinish', ShortAnswer.prototype.reply, {once: true});
        ShortAnswer.prototype.removeEvents();
        let timeLeft = parseInt(jQuery(REGION.SECONDS).text());
        let responseText = jQuery(REGION.INPUTANSWER).val();
        let request = {
            methodname: SERVICES.REPLY,
            args: {
                responsetext: responseText === undefined || responseText === null ? '' : responseText,
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
    let contentFeedbacks = document.querySelector(REGION.CONTENTFEEDBACKS);
    if (contentFeedbacks !== null) {
        mEvent.notifyFilterContentUpdated(document.querySelector(REGION.CONTENTFEEDBACKS));
    }
};

ShortAnswer.prototype.pauseQuestion = function() {
    dispatchEvent(new Event('pauseQuestion'));
    jQuery(REGION.TIMER).css('z-index', 3);
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(ACTION.REPLY).css('pointer-events', 'none');
};

ShortAnswer.prototype.playQuestion = function() {
    if (questionEnd !== true) {
        dispatchEvent(new Event('playQuestion'));
        jQuery(REGION.TIMER).css('z-index', 1);
        jQuery(REGION.FEEDBACKBACGROUND).css('display', 'none');
        jQuery(ACTION.REPLY).css('pointer-events', 'auto');
    }
};

ShortAnswer.prototype.showFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'block', 'z-index': 3});
    }
};

ShortAnswer.prototype.hideFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'none', 'z-index': 0});
    }
};

ShortAnswer.prototype.showAnswers = function() {
    if (questionEnd === true) {
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

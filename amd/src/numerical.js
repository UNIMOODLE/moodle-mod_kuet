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
 * @module    mod_kuet/numerical
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
    INPUTANSWER: '#userNumerical',
    ANSWERHELP: '#userNumericalHelp',
    FEEDBACKICONS: '.feedback-icons',
    UNITSCONTENT: '#unitsContent'
};

let SERVICES = {
    REPLY: 'mod_kuet_numerical'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading'
};

let cmId;
let sId;
let questionid;
let kuetId;
let kid;
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
    kuetId = this.node.attr('data-kuetid');
    kid = this.node.attr('data-kid');
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
        addEventListener('alreadyAnswered_' + kid, (ev) => {
            let userid = jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                Numerical.prototype.reply();
            }
        }, {once: true});
        addEventListener('teacherQuestionEnd_' + kid, (e) => {
            if (questionEnd !== true) {
                Numerical.prototype.reply();
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
        addEventListener('pauseQuestion_' + kid, () => {
            Numerical.prototype.pauseQuestion();
        }, false);
        addEventListener('playQuestion_' + kid, () => {
            Numerical.prototype.playQuestion();
        }, false);
        addEventListener('showAnswers_' + kid, () => {
            Numerical.prototype.showAnswers();
        }, false);
        addEventListener('hideAnswers_' + kid, () => {
            Numerical.prototype.hideAnswers();
        }, false);
        addEventListener('showStatistics_' + kid, () => {
            Numerical.prototype.showStatistics();
        }, false);
        addEventListener('hideStatistics_' + kid, () => {
            Numerical.prototype.hideStatistics();
        }, false);
        addEventListener('showFeedback_' + kid, () => {
            Numerical.prototype.showFeedback();
        }, false);
        addEventListener('hideFeedback_' + kid, () => {
            Numerical.prototype.hideFeedback();
        }, false);
        addEventListener('removeEvents', () => {
            Numerical.prototype.removeEvents();
        }, {once: true});
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
    if (manualMode !== false) {
        removeEventListener('alreadyAnswered_' + kid, (ev) => {
            let userid = jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                Numerical.prototype.reply();
            }
        }, {once: true});
        removeEventListener('teacherQuestionEnd_' + kid, (e) => {
            if (questionEnd !== true) {
                Numerical.prototype.reply();
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
        removeEventListener('pauseQuestion_' + kid, () => {
            Numerical.prototype.pauseQuestion();
        }, false);
        removeEventListener('playQuestion_' + kid, () => {
            Numerical.prototype.playQuestion();
        }, false);
        removeEventListener('showAnswers_' + kid, () => {
            Numerical.prototype.showAnswers();
        }, false);
        removeEventListener('hideAnswers_' + kid, () => {
            Numerical.prototype.hideAnswers();
        }, false);
        removeEventListener('showStatistics_' + kid, () => {
            Numerical.prototype.showStatistics();
        }, false);
        removeEventListener('hideStatistics_' + kid, () => {
            Numerical.prototype.hideStatistics();
        }, false);
        removeEventListener('showFeedback_' + kid, () => {
            Numerical.prototype.showFeedback();
        }, false);
        removeEventListener('hideFeedback_' + kid, () => {
            Numerical.prototype.hideFeedback();
        }, false);
        removeEventListener('removeEvents', () => {
            Numerical.prototype.removeEvents();
        }, {once: true});
    }
};

Numerical.prototype.reply = function() {
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        jQuery(REGION.ROOT).append(html);
        dispatchEvent(Numerical.prototype.endTimer);
        removeEventListener('timeFinish', Numerical.prototype.reply, {once: true});
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
                kuetid: kuetId,
                cmid: cmId,
                questionid: questionid,
                kid: kid,
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
    let contentFeedbacks = document.querySelector(REGION.CONTENTFEEDBACKS);
    if (contentFeedbacks !== null) {
        mEvent.notifyFilterContentUpdated(document.querySelector(REGION.CONTENTFEEDBACKS));
    }
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

Numerical.prototype.showStatistics = function() {
    if (questionEnd === true) {
        jQuery('#statistics').css({'display': 'block', 'z-index': 3});
    }
};
Numerical.prototype.hideStatistics = function() {
    if (questionEnd === true) {
        jQuery('#statistics').css({'display': 'none', 'z-index': 0});
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

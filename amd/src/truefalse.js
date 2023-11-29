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
 * @module    mod_kuet/truefalse
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Templates from 'core/templates';
import mEvent from 'core/event';

let ACTION = {
    REPLY: '[data-action="truefalse-answer"]',
};

let REGION = {
    ROOT: '[data-region="question-content"]',
    TRUEFALSE: '[data-region="truefalse"]',
    LOADING: '[data-region="overlay-icon-container"]',
    CONTENTFEEDBACKS: '[data-region="containt-feedbacks"]',
    FEEDBACK: '[data-region="statement-feedback"]',
    FEEDBACKANSWER: '[data-region="answer-feedback"]',
    FEEDBACKBACGROUND: '[data-region="feedback-background"]',
    STATEMENTTEXT: '[data-region="statement-text"]',
    TIMER: '[data-region="question-timer"]',
    SECONDS: '[data-region="seconds"]',
    NEXT: '[data-action="next-question"]',
    ANSWERCHECKED: '.answer-checked'
};

let SERVICES = {
    REPLY: 'mod_kuet_truefalse'
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

/**
 * @constructor
 * @param {String} selector
 * @param {Boolean} showquestionfeedback
 * @param {Boolean} manualmode
 * @param {String} jsonresponse
 */
function TrueFalse(selector, showquestionfeedback = false, manualmode = false, jsonresponse = '') {
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
            this.showAnswers();
            questionEnd = true;
            if (showQuestionFeedback === true) {
                this.showFeedback();
            }
        }
    }
    this.initTrueFalse();
}

/** @type {jQuery} The jQuery node for the page region. */
TrueFalse.prototype.node = null;
TrueFalse.prototype.endTimer = new Event('endTimer');
TrueFalse.prototype.studentQuestionEnd = new Event('studentQuestionEnd');

TrueFalse.prototype.initTrueFalse = function() {
    this.node.find(ACTION.REPLY).on('click', this.reply.bind(this));
    TrueFalse.prototype.initEvents();
};

TrueFalse.prototype.initEvents = function() {
    addEventListener('timeFinish', TrueFalse.prototype.reply, {once: true});
    if (manualMode !== false) {
        addEventListener('alreadyAnswered_' + jqid, (ev) => {
            let userid =  jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                TrueFalse.prototype.reply();
            }
        }, {once: true});
        addEventListener('teacherQuestionEnd_' + jqid, (e) => {
            if (questionEnd !== true) {
                TrueFalse.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('[data-answerid="' + statistic.answerid + '"] .numberofreplies').html(statistic.numberofreplies);
            });
        }, {once: true});
        addEventListener('pauseQuestion_' + jqid, () => {
            TrueFalse.prototype.pauseQuestion();
        }, false);
        addEventListener('playQuestion_' + jqid, () => {
            TrueFalse.prototype.playQuestion();
        }, false);
        addEventListener('showAnswers_' + jqid, () => {
            TrueFalse.prototype.showAnswers();
        }, false);
        addEventListener('hideAnswers_' + jqid, () => {
            TrueFalse.prototype.hideAnswers();
        }, false);
        addEventListener('showStatistics_' + jqid, () => {
            TrueFalse.prototype.showStatistics();
        }, false);
        addEventListener('hideStatistics_' + jqid, () => {
            TrueFalse.prototype.hideStatistics();
        }, false);
        addEventListener('showFeedback_' + jqid, () => {
            TrueFalse.prototype.showFeedback();
        }, false);
        addEventListener('hideFeedback_' + jqid, () => {
            TrueFalse.prototype.hideFeedback();
        }, false);
        addEventListener('removeEvents', () => {
            TrueFalse.prototype.removeEvents();
        }, {once: true});
    }

    window.onbeforeunload = function() {
        if (jQuery(REGION.SECONDS).length > 0 && questionEnd === false) {
            TrueFalse.prototype.reply();
            return 'Because the question is overdue and an attempt has been made to reload the page,' +
                ' the question has remained unanswered.';
        }
    };
};

TrueFalse.prototype.removeEvents = function() {
    removeEventListener('timeFinish', TrueFalse.prototype.reply, {once: true});
    if (manualMode !== false) {
        removeEventListener('alreadyAnswered_' + jqid, (ev) => {
            let userid =  jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                TrueFalse.prototype.reply();
            }
        }, {once: true});
        removeEventListener('teacherQuestionEnd_' + jqid, (e) => {
            if (questionEnd !== true) {
                TrueFalse.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('[data-answerid="' + statistic.answerid + '"] .numberofreplies').html(statistic.numberofreplies);
            });
        }, {once: true});
        removeEventListener('pauseQuestion_' + jqid, () => {
            TrueFalse.prototype.pauseQuestion();
        }, false);
        removeEventListener('playQuestion_' + jqid, () => {
            TrueFalse.prototype.playQuestion();
        }, false);
        removeEventListener('showAnswers_' + jqid, () => {
            TrueFalse.prototype.showAnswers();
        }, false);
        removeEventListener('hideAnswers_' + jqid, () => {
            TrueFalse.prototype.hideAnswers();
        }, false);
        removeEventListener('showStatistics_' + jqid, () => {
            TrueFalse.prototype.showStatistics();
        }, false);
        removeEventListener('hideStatistics_' + jqid, () => {
            TrueFalse.prototype.hideStatistics();
        }, false);
        removeEventListener('showFeedback_' + jqid, () => {
            TrueFalse.prototype.showFeedback();
        }, false);
        removeEventListener('hideFeedback_' + jqid, () => {
            TrueFalse.prototype.hideFeedback();
        }, false);
        removeEventListener('removeEvents', () => {
            TrueFalse.prototype.removeEvents();
        }, {once: true});
    }
};

TrueFalse.prototype.reply = function(e) {
    let answerIds = 0;
    if (e !== undefined && e !== null) {
        e.preventDefault();
        e.stopPropagation();
        // Avoid 'timefinished' event.
        if (e.type === 'click') {
            answerIds = parseInt(jQuery(e.currentTarget).attr('data-answerid'));
        }
        if (answerIds === null) {
            answerIds = 0;
        }
    }
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        jQuery(REGION.ROOT).append(html);
        dispatchEvent(TrueFalse.prototype.endTimer);
        removeEventListener('timeFinish', TrueFalse.prototype.reply, {once: true});
        TrueFalse.prototype.removeEvents();
        let timeLeft = parseInt(jQuery(REGION.SECONDS).text());
        let request = {
            methodname: SERVICES.REPLY,
            args: {
                answerid: answerIds,
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
                if (e !== undefined) {
                    jQuery(e.currentTarget).css({'z-index': 3, 'pointer-events': 'none'});
                }
                TrueFalse.prototype.answered(response);
                questionEnd = true;
                dispatchEvent(TrueFalse.prototype.studentQuestionEnd);
                if (jQuery('.modal-body').length) { // Preview.
                    TrueFalse.prototype.showAnswers();
                    if (showQuestionFeedback === true) {
                        TrueFalse.prototype.showFeedback();
                    }
                } else {
                    if (manualMode === false) {
                        TrueFalse.prototype.showAnswers();
                        if (showQuestionFeedback === true) {
                            TrueFalse.prototype.showFeedback();
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

TrueFalse.prototype.answered = function(response) {
    questionEnd = true;
    if (response.hasfeedbacks) {
        jQuery(REGION.FEEDBACK).html(response.statment_feedback);
        jQuery(REGION.FEEDBACKANSWER).html(response.answer_feedback);
    }
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(REGION.STATEMENTTEXT).css({'z-index': 3, 'padding': '15px'});
    jQuery(REGION.TIMER).css('z-index', 3);
    jQuery(REGION.NEXT).removeClass('d-none');
    if (response.answerids && response.answerids !== '') {
        let responses = response.answerids.split(',');
        responses.forEach((rId) => {
            jQuery('[data-answerid="' + rId + '"]').css({'z-index': 3, 'pointer-events': 'none'});
        });
    }
    if (response.correct_answers) {
        correctAnswers = response.correct_answers;
        jQuery(REGION.FEEDBACKBACGROUND).css('height', '100%');
    }
    if (response.statistics) {
        response.statistics.forEach((statistic) => {
            jQuery('[data-answerid="' + statistic.answerids + '"] .numberofreplies').html(statistic.numberofreplies);
        });
    }
    let contentFeedbacks = document.querySelector(REGION.CONTENTFEEDBACKS);
    if (contentFeedbacks !== null) {
        mEvent.notifyFilterContentUpdated(document.querySelector(REGION.CONTENTFEEDBACKS));
    }
};

TrueFalse.prototype.pauseQuestion = function() {
    dispatchEvent(new Event('pauseQuestion'));
    jQuery(REGION.TIMER).css('z-index', 3);
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(ACTION.REPLY).css('pointer-events', 'none');
};

TrueFalse.prototype.playQuestion = function() {
    if (questionEnd !== true) {
        dispatchEvent(new Event('playQuestion'));
        jQuery(REGION.TIMER).css('z-index', 1);
        jQuery(REGION.FEEDBACKBACGROUND).css('display', 'none');
        jQuery(ACTION.REPLY).css('pointer-events', 'auto');
    }
};

TrueFalse.prototype.showAnswers = function() {
    if (correctAnswers !== null && questionEnd === true) {
        jQuery('.feedback-icon').css('display', 'flex');
        jQuery('[data-answerid="' + correctAnswers + '"] .incorrect').css('display', 'none');
    }
};

TrueFalse.prototype.hideAnswers = function() {
    if (questionEnd === true) {
        jQuery('.feedback-icon').css('display', 'none');
    }
};

TrueFalse.prototype.showStatistics = function() {
    if (questionEnd === true) {
        jQuery('.statistics-icon').css('display', 'flex');
    }
};

TrueFalse.prototype.hideStatistics = function() {
    if (questionEnd === true) {
        jQuery('.statistics-icon').css('display', 'none');
    }
};

TrueFalse.prototype.showFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'block', 'z-index': 3});
    }
};

TrueFalse.prototype.hideFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'none', 'z-index': 0});
    }
};

export const initTrueFalse = (selector, showquestionfeedback, manualmode, jsonresponse) => {
    return new TrueFalse(selector, showquestionfeedback, manualmode, jsonresponse);
};

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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos..

/**
 *
 * @module    mod_kuet/multichoice
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
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
    REPLY: '[data-action="multichoice-answer"]',
    MULTIANSWER: '[data-action="multichoice-multianswer"]',
    SENDMULTIANSWER: '[data-action="send-multianswer"]'
};

let REGION = {
    ROOT: '[data-region="question-content"]',
    MULTICHOICE: '[data-region="multichoice"]',
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
    REPLY: 'mod_kuet_multichoice'
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
function MultiChoice(selector, showquestionfeedback = false, manualmode = false, jsonresponse = '') {
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
            this.showAnswers();
            if (showQuestionFeedback === true) {
                this.showFeedback();
            }
        }
    }
    this.initMultichoice();
}

/** @type {jQuery} The jQuery node for the page region. */
MultiChoice.prototype.node = null;
MultiChoice.prototype.endTimer = new Event('endTimer');
MultiChoice.prototype.studentQuestionEnd = new Event('studentQuestionEnd');

MultiChoice.prototype.initMultichoice = function() {
    this.node.find(ACTION.REPLY).on('click', this.reply.bind(this));
    this.node.find(ACTION.MULTIANSWER).on('click', this.markAnswer.bind(this));
    this.node.find(ACTION.SENDMULTIANSWER).on('click', this.reply.bind(this));
    MultiChoice.prototype.initEvents();
};

MultiChoice.prototype.initEvents = function() {
    addEventListener('timeFinish', MultiChoice.prototype.reply, {once: true});
    if (manualMode !== false) {
        addEventListener('alreadyAnswered_' + kid, (ev) => {
            let userid = jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                MultiChoice.prototype.reply();
            }
        }, {once: true});
        addEventListener('teacherQuestionEnd_' + kid, (e) => {
            if (questionEnd !== true) {
                MultiChoice.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('[data-answerid="' + statistic.answerid + '"] .numberofreplies').html(statistic.numberofreplies);
            });
        }, {once: true});
        addEventListener('pauseQuestion_' + kid, () => {
            MultiChoice.prototype.pauseQuestion();
        }, false);
        addEventListener('playQuestion_' + kid, () => {
            MultiChoice.prototype.playQuestion();
        }, false);
        addEventListener('showAnswers_' + kid, () => {
            MultiChoice.prototype.showAnswers();
        }, false);
        addEventListener('hideAnswers_' + kid, () => {
            MultiChoice.prototype.hideAnswers();
        }, false);
        addEventListener('showStatistics_' + kid, () => {
            MultiChoice.prototype.showStatistics();
        }, false);
        addEventListener('hideStatistics_' + kid, () => {
            MultiChoice.prototype.hideStatistics();
        }, false);
        addEventListener('showFeedback_' + kid, () => {
            MultiChoice.prototype.showFeedback();
        }, false);
        addEventListener('hideFeedback_' + kid, () => {
            MultiChoice.prototype.hideFeedback();
        }, false);
        addEventListener('removeEvents', () => {
            MultiChoice.prototype.removeEvents();
        }, {once: true});
    }

    window.onbeforeunload = function() {
        if (jQuery(REGION.SECONDS).length > 0 && questionEnd === false) {
            MultiChoice.prototype.reply();
            return 'Because the question is overdue and an attempt has been made to reload the page,' +
                ' the question has remained unanswered.';
        }
    };
};

MultiChoice.prototype.removeEvents = function() {
    removeEventListener('timeFinish', () => MultiChoice.prototype.reply, {once: true});
    if (manualMode !== false) {
        removeEventListener('alreadyAnswered_' + kid, (ev) => {
            let userid = jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                MultiChoice.prototype.reply();
            }
        }, {once: true});
        removeEventListener('teacherQuestionEnd_' + kid, (e) => {
            if (questionEnd !== true) {
                MultiChoice.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('[data-answerid="' + statistic.answerid + '"] .numberofreplies').html(statistic.numberofreplies);
            });
        }, {once: true});
        removeEventListener('pauseQuestion_' + kid, () => {
            MultiChoice.prototype.pauseQuestion();
        }, false);
        removeEventListener('playQuestion_' + kid, () => {
            MultiChoice.prototype.playQuestion();
        }, false);
        removeEventListener('showAnswers_' + kid, () => {
            MultiChoice.prototype.showAnswers();
        }, false);
        removeEventListener('hideAnswers_' + kid, () => {
            MultiChoice.prototype.hideAnswers();
        }, false);
        removeEventListener('showStatistics_' + kid, () => {
            MultiChoice.prototype.showStatistics();
        }, false);
        removeEventListener('hideStatistics_' + kid, () => {
            MultiChoice.prototype.hideStatistics();
        }, false);
        removeEventListener('showFeedback_' + kid, () => {
            MultiChoice.prototype.showFeedback();
        }, false);
        removeEventListener('hideFeedback_' + kid, () => {
            MultiChoice.prototype.hideFeedback();
        }, false);
        removeEventListener('removeEvents', () => {
            MultiChoice.prototype.removeEvents();
        }, {once: true});
    }
};

MultiChoice.prototype.reply = function(e) {
    if (event.type === 'timeFinish' && questionEnd === true) {
        e.preventDefault();
        e.stopPropagation();
        return;
    }
    let answerIds = '0';
    let multiAnswer = e === undefined || jQuery(e.currentTarget).attr('data-action') === 'send-multianswer';
    if (!multiAnswer) {
        e.preventDefault();
        e.stopPropagation();
        answerIds = jQuery(e.currentTarget).attr('data-answerid');
    } else { // MultiAnswer or empty.
        let responses = [];
        jQuery(REGION.ANSWERCHECKED).each(function(index, response) {
            responses.push(jQuery(response).attr('data-answerid'));
        });
        answerIds = responses.toString();
    }
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        jQuery(REGION.ROOT).append(html);
        dispatchEvent(MultiChoice.prototype.endTimer);
        removeEventListener('timeFinish', () => MultiChoice.prototype.reply, {once: true});
        MultiChoice.prototype.removeEvents();
        let timeLeft = parseInt(jQuery(REGION.SECONDS).text());
        let request = {
            methodname: SERVICES.REPLY,
            args: {
                answerids: answerIds === undefined ? '0' : answerIds,
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
                if (!multiAnswer) {
                    jQuery(e.currentTarget).css({'z-index': 3, 'pointer-events': 'none'});
                } else {
                    let responses = answerIds.split(',');
                    responses.forEach((rId) => {
                        jQuery('[data-answerid="' + rId + '"]')
                            .css({'z-index': 3, 'pointer-events': 'none'})
                            .removeClass('answer-checked');
                    });
                    jQuery(ACTION.SENDMULTIANSWER).addClass('d-none');
                }
                questionEnd = true;
                MultiChoice.prototype.answered(response);
                dispatchEvent(MultiChoice.prototype.studentQuestionEnd);
                if (jQuery('.modal-body').length) { // Preview.
                    MultiChoice.prototype.showAnswers();
                    if (showQuestionFeedback === true) {
                        MultiChoice.prototype.showFeedback();
                    }
                } else {
                    if (manualMode === false) {
                        MultiChoice.prototype.showAnswers();
                        if (showQuestionFeedback === true) {
                            MultiChoice.prototype.showFeedback();
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

MultiChoice.prototype.markAnswer = function(e) {
    if (jQuery(e.currentTarget).hasClass('answer-checked')) {
        jQuery(e.currentTarget).removeClass('answer-checked');
    } else {
        jQuery(e.currentTarget).addClass('answer-checked');
    }
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
    if (response.answerids && response.answerids !== '') {
        let responses = response.answerids.split(',');
        responses.forEach((rId) => {
            jQuery('[data-answerid="' + rId + '"]').css({'z-index': 3, 'pointer-events': 'none'});
        });
        jQuery(ACTION.SENDMULTIANSWER).addClass('d-none');
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

MultiChoice.prototype.pauseQuestion = function() {
    dispatchEvent(new Event('pauseQuestion'));
    jQuery(REGION.TIMER).css('z-index', 3);
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(ACTION.REPLY).css('pointer-events', 'none');
};

MultiChoice.prototype.playQuestion = function() {
    if (questionEnd !== true) {
        dispatchEvent(new Event('playQuestion'));
        jQuery(REGION.TIMER).css('z-index', 1);
        jQuery(REGION.FEEDBACKBACGROUND).css('display', 'none');
        jQuery(ACTION.REPLY).css('pointer-events', 'auto');
    }
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
    if (questionEnd === true) {
        jQuery('.statistics-icon').css('display', 'flex');
    }
};

MultiChoice.prototype.hideStatistics = function() {
    if (questionEnd === true) {
        jQuery('.statistics-icon').css('display', 'none');
    }
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

export const initMultiChoice = (selector, showquestionfeedback, manualmode, jsonresponse) => {
    return new MultiChoice(selector, showquestionfeedback, manualmode, jsonresponse);
};

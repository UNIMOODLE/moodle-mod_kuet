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
 * @module    mod_kuet/description
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
    REPLY: 'mod_kuet_description'
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
    Description.prototype.initDescription();
}

Description.prototype.initDescription = function() {
    jQuery(ACTION.SEND_RESPONSE).on('click', Description.prototype.reply);
    Description.prototype.initEvents();
};

Description.prototype.initEvents = function() {
    addEventListener('timeFinish', Description.prototype.reply, {once: true});
    if (manualMode !== false) {
        addEventListener('alreadyAnswered_' + kid, (ev) => {
            let userid =  jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                Description.prototype.reply();
            }
        }, {once: true});
        addEventListener('teacherQuestionEnd_' + kid, (e) => {
            if (questionEnd !== true) {
                Description.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('[data-answerid="' + statistic.answerid + '"] .numberofreplies').html(statistic.numberofreplies);
            });
        }, {once: true});
        addEventListener('pauseQuestion_' + kid, () => {
            Description.prototype.pauseQuestion();
        }, false);
        addEventListener('playQuestion_' + kid, () => {
            Description.prototype.playQuestion();
        }, false);
        addEventListener('showAnswers_' + kid, () => {
            Description.prototype.showAnswers();
        }, false);
        addEventListener('hideAnswers_' + kid, () => {
            Description.prototype.hideAnswers();
        }, false);
        addEventListener('showStatistics_' + kid, () => {
            Description.prototype.showStatistics();
        }, false);
        addEventListener('hideStatistics_' + kid, () => {
            Description.prototype.hideStatistics();
        }, false);
        addEventListener('showFeedback_' + kid, () => {
            Description.prototype.showFeedback();
        }, false);
        addEventListener('hideFeedback_' + kid, () => {
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
        removeEventListener('alreadyAnswered_' + kid, (ev) => {
            let userid =  jQuery('[data-region="student-canvas"]').data('userid');
            if (userid != ev.detail.userid) {
                jQuery('[data-region="group-message"]').css({'z-index': 3, 'padding': '15px'});
                jQuery('[data-region="group-message"]').show();
            }
            if (questionEnd !== true) {
                Description.prototype.reply();
            }
        }, {once: true});
        removeEventListener('teacherQuestionEnd_' + kid, (e) => {
            if (questionEnd !== true) {
                Description.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('[data-answerid="' + statistic.answerid + '"] .numberofreplies').html(statistic.numberofreplies);
            });
        }, {once: true});
        removeEventListener('pauseQuestion_' + kid, () => {
            Description.prototype.pauseQuestion();
        }, false);
        removeEventListener('playQuestion_' + kid, () => {
            Description.prototype.playQuestion();
        }, false);
        removeEventListener('showAnswers_' + kid, () => {
            Description.prototype.showAnswers();
        }, false);
        removeEventListener('hideAnswers_' + kid, () => {
            Description.prototype.hideAnswers();
        }, false);
        removeEventListener('showStatistics_' + kid, () => {
            Description.prototype.showStatistics();
        }, false);
        removeEventListener('hideStatistics_' + kid, () => {
            Description.prototype.hideStatistics();
        }, false);
        removeEventListener('showFeedback_' + kid, () => {
            Description.prototype.showFeedback();
        }, false);
        removeEventListener('hideFeedback_' + kid, () => {
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
        removeEventListener('timeFinish', Description.prototype.reply, {once: true});
        Description.prototype.removeEvents();
        let timeLeft = parseInt(jQuery(REGION.SECONDS).text());
        let request = {
            methodname: SERVICES.REPLY,
            args: {
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

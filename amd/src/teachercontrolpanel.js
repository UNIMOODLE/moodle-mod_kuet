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
 * @module    mod_kuet/teachercontrolpanel
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";
/* eslint-disable no-unused-vars */

import jQuery from 'jquery';
import {get_strings as getStrings} from 'core/str';
import Notification from 'core/notification';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';

let REGION = {
    CONTROLPANEL: '[data-region="teacher_control_panel"]', // This root.
    JUMPTOINPUT: '[data-input="jumpto"]',
    SWITCHS: '.showhide-action',
    ESPECIALS: '.special-action',
    IMPROVISE: '.special-action.improvise-question',
};

let ACTION = {
    NEXT: '[data-action="next"]',
    PAUSE: '[data-action="pause"]',
    PLAY: '[data-action="play"]',
    RESEND: '[data-action="resend"]',
    JUMP: '[data-action="jump"]',
    FINISHQUESTION: '[data-action="finishquestion"]',
    SHOWHIDE_RESPONSES: '[data-action="showhide_responses"]',
    SHOWHIDE_STATISTICS: '[data-action="showhide_statistics"]',
    SHOWHIDE_FEEDBACK: '[data-action="showhide_feedback"]',
    IMPROVISE: '[data-action="improvise"]',
    VOTE: '[data-action="vote"]',
    ENDSESSION: '[data-action="endsession"]',
    COLLAPSE: '.teacher-control-panel-collapse',
};

TeacherControlPanel.prototype.root = null;
TeacherControlPanel.prototype.kid = null;

/**
 * @constructor
 * @param {String} region
 * @param {int} kid
 */
function TeacherControlPanel(region, kid) {
    this.root = jQuery(region);
    this.kid = kid;
    this.initControlPanel();
    finishquestionEvent = new Event('teacherQuestionEnd_' + this.kid);
}

const nextEvent = new Event('nextQuestion');
const pauseEvent = new Event('pauseQuestionSelf');
const playEvent = new Event('playQuestionSelf');
const resendEvent = new Event('resendSelf');
const finishquestionEventSelf = new Event('teacherQuestionEndSelf');
const endSession = new Event('endSession');
const showAnswers = new Event('showAnswersSelf');
const hideAnswers = new Event('hideAnswersSelf');
const showStatistics = new Event('showStatisticsSelf');
const hideStatistics = new Event('hideStatisticsSelf');
const showFeedback = new Event('showFeedbackSelf');
const hideFeedback = new Event('hideFeedbackSelf');
const improvise = new Event('improvise');
const vote = new Event('initVote');
let finishquestionEvent = null;
let questionEnd = false;

TeacherControlPanel.prototype.initControlPanel = function() {
    this.root.find(ACTION.NEXT).on('click', this.next);
    this.root.find(ACTION.PAUSE).on('click', this.pause);
    this.root.find(ACTION.PLAY).on('click', this.play);
    this.root.find(ACTION.RESEND).on('click', this.resend);
    this.root.find(ACTION.JUMP).on('click', this.jump);
    this.root.find(REGION.JUMPTOINPUT).on('keyup', (e) => {
        if (e.key === 'Enter' || e.keyCode === 13) {
            TeacherControlPanel.prototype.jump();
        }
    });
    this.root.find(ACTION.FINISHQUESTION).on('click', this.finishquestion);
    this.root.find(ACTION.ENDSESSION).on('click', this.endsession);
    this.root.find(ACTION.SHOWHIDE_RESPONSES).on('click', function(e) {
        if (jQuery(ACTION.SHOWHIDE_RESPONSES).is(':checked')) {
            TeacherControlPanel.prototype.showAnswers();
        } else {
            TeacherControlPanel.prototype.hideAnswers();
        }
    });
    this.root.find(ACTION.SHOWHIDE_STATISTICS).on('click', function() {
        if (jQuery(ACTION.SHOWHIDE_STATISTICS).is(':checked')) {
            TeacherControlPanel.prototype.showStatistics();
        } else {
            TeacherControlPanel.prototype.hideStatistics();
        }
    });
    this.root.find(ACTION.SHOWHIDE_FEEDBACK).on('click', function() {
        if (jQuery(ACTION.SHOWHIDE_FEEDBACK).is(':checked')) {
            TeacherControlPanel.prototype.showFeedback();
        } else {
            TeacherControlPanel.prototype.hideFeedback();
        }
    });
    this.root.find(ACTION.IMPROVISE).on('click', function() {
        TeacherControlPanel.prototype.improvise();
    });
    this.root.find(ACTION.VOTE).on('click', function() {
        TeacherControlPanel.prototype.vote();
    });
    jQuery(ACTION.COLLAPSE).on('click', function() {
        setTimeout(function() {
            if (jQuery(ACTION.COLLAPSE + '.collapsed').length !== 0) {
                jQuery('.content-raceresults').animate({
                    width: '100%'
                }, 400).css({
                    width: '100%'
                });
            } else {
                jQuery('.content-raceresults').animate({
                    width: jQuery('.question-body').width() - 260 + 'px'
                }, 400).css({
                    width: 'calc(100% - 260px)'
                });
            }
        }, 100);
    });
};

TeacherControlPanel.prototype.next = function() {
    dispatchEvent(nextEvent);
};

TeacherControlPanel.prototype.pause = function() {
    dispatchEvent(pauseEvent);
    jQuery(ACTION.PAUSE).addClass('d-none');
    jQuery(ACTION.PLAY).removeClass('d-none');
};

TeacherControlPanel.prototype.play = function(e) {
    dispatchEvent(playEvent);
    jQuery(ACTION.PAUSE).removeClass('d-none');
    jQuery(ACTION.PLAY).addClass('d-none');
};

TeacherControlPanel.prototype.resend = function() {
    dispatchEvent(resendEvent);
};

TeacherControlPanel.prototype.jump = function() {
    let numquestion = jQuery(REGION.JUMPTOINPUT).data('numquestions');
    let value = jQuery(REGION.JUMPTOINPUT).val();
    if (value > numquestion || value < 1) {
        const stringkeys = [
            {key: 'jump', component: 'mod_kuet'},
            {key: 'jumpto_error', component: 'mod_kuet', param: numquestion},
            {key: 'confirm', component: 'mod_kuet'}
        ];
        getStrings(stringkeys).then((langStrings) => {
            return ModalFactory.create({
                title: langStrings[0],
                body: langStrings[1],
                type: ModalFactory.types.CANCEL
            }).then(modal => {
                modal.getRoot().on(ModalEvents.hidden, () => {
                    jQuery(REGION.JUMPTOINPUT).val('');
                    modal.destroy();
                });
                return modal;
            });
        }).done(function(modal) {
            modal.show();
        }).fail(Notification.exception);
    } else {
        let jumpEvent = new CustomEvent('jumpTo', {
            "detail": {"jumpTo": value}
        });
        dispatchEvent(jumpEvent);
    }
};

TeacherControlPanel.prototype.finishquestion = function() {
    dispatchEvent(finishquestionEventSelf);
    questionEnd = true;
    jQuery(REGION.SWITCHS).removeClass('disabled');
    jQuery(REGION.IMPROVISE).removeClass('disabled');
    jQuery(ACTION.FINISHQUESTION).addClass('d-none');
    jQuery(ACTION.NEXT).removeClass('d-none');
};

TeacherControlPanel.prototype.showAnswers = function() {
    dispatchEvent(showAnswers);
};

TeacherControlPanel.prototype.hideAnswers = function() {
    dispatchEvent(hideAnswers);
};

TeacherControlPanel.prototype.showStatistics = function() {
    dispatchEvent(showStatistics);
};

TeacherControlPanel.prototype.hideStatistics = function() {
    dispatchEvent(hideStatistics);
};

TeacherControlPanel.prototype.showFeedback = function() {
    dispatchEvent(showFeedback);
};

TeacherControlPanel.prototype.hideFeedback = function() {
    dispatchEvent(hideFeedback);
};

TeacherControlPanel.prototype.improvise = function() {
    dispatchEvent(improvise);
};

TeacherControlPanel.prototype.vote = function() {
    dispatchEvent(vote);
};

TeacherControlPanel.prototype.endsession = function() {
    dispatchEvent(endSession);
};

export const teacherControlPanel = (region, kid) => {
    return new TeacherControlPanel(region, kid);
};

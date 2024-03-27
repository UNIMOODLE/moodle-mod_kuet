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
 * @module    mod_kuet/question
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
import ModalFactory from 'core/modal_factory';

let ACTION = {
    EXPAND: '[data-action="question-fullscreen"]',
    COMPRESS: '[data-action="question-exit-fullscreen"]',
    NEXTQUESTION: '[data-action="next-question"]'
};

let REGION = {
    PAGE_HEADER: '#page-header',
    BODY: 'body.path-mod-kuet',
    NAV: 'nav.navbar',
    SESSIONCONTENT: '[data-region="session-content"]',
    QUESTIONCONTENT: '[data-region="question-content"]',
    RANKINGCONTENT: '[data-region="ranking-content"]',
};

let SERVICES = {
    NEXTQUESTION: 'mod_kuet_nextquestion',
    GETSESSIONCONFIG: 'mod_kuet_getsession',
    GETPROVISIONALRANKING: 'mod_kuet_getprovisionalranking',
    GETFINALRANKING: 'mod_kuet_getfinalranking',
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    QUESTION: 'mod_kuet/questions/encasement',
    PROVISIONALRANKING: 'mod_kuet/ranking/provisional'
};

let cmId;
let sId;
let kId;
let isRanking = false;

/**
 * @constructor
 * @param {String} selector
 */
function Question(selector) {
    this.node = jQuery(selector);
    if (this.node.attr('data-region') === 'ranking-content') {
        isRanking = true;
    }
    this.initQuestion();
}

/** @type {jQuery} The jQuery node for the page region. */
Question.prototype.node = null;

Question.prototype.initQuestion = function() {
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    kId = this.node.attr('data-kid');
    this.node.find(ACTION.EXPAND).on('click', this.fullScreen);
    this.node.find(ACTION.COMPRESS).on('click', this.exitFullScreen);
    if (jQuery(REGION.BODY).hasClass('fullscreen')) {
        jQuery(ACTION.EXPAND).css('display', 'none');
        jQuery(ACTION.COMPRESS).css('display', 'block');
    } else {
        jQuery(ACTION.EXPAND).css('display', 'block');
        jQuery(ACTION.COMPRESS).css('display', 'none');
    }
    jQuery(ACTION.NEXTQUESTION).on('click', this.nextQuestion);
    jQuery(document).keyup(function(e) {
        if (e.key === 'Escape' || e.key === 27 || e.key === 'F11' || e.keyCode === 122) {
            if (jQuery(REGION.BODY).hasClass('fullscreen')) {
                Question.prototype.exitFullScreen();
            } else {
                Question.prototype.fullScreen();
            }
        }
    });
    if (jQuery('.modal-body').length) {
        jQuery(ACTION.EXPAND).css('display', 'none');
        jQuery(ACTION.COMPRESS).css('display', 'none');
    }
    addEventListener('questionEnd', () => {
        jQuery(ACTION.NEXTQUESTION).removeClass('d-none');
    }, false);
};

Question.prototype.fullScreen = function() {
    jQuery(ACTION.EXPAND).css('display', 'none');
    jQuery(ACTION.COMPRESS).css('display', 'block');
    jQuery(REGION.BODY).addClass('fullscreen');
    jQuery(window).scrollTop(0);
    jQuery('html, body').animate({scrollTop: 0}, 500);
};

Question.prototype.exitFullScreen = function() {
    jQuery(ACTION.EXPAND).css('display', 'block');
    jQuery(ACTION.COMPRESS).css('display', 'none');
    jQuery(REGION.BODY).removeClass('fullscreen');
};

Question.prototype.nextQuestion = function(e) { // Only for programed modes, not used by sockets.
    e.preventDefault();
    e.stopPropagation();
    let identifier = jQuery(REGION.SESSIONCONTENT);
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        identifier.append(html);
        let requestConfig = {
            methodname: SERVICES.GETSESSIONCONFIG,
            args: {
                sid: sId,
                cmid: cmId
            }
        };
        Ajax.call([requestConfig])[0].done(function(sessionConfig) {
            if (sessionConfig.session.showgraderanking === 1 && isRanking === false) { // Provisional Ranking.
                let requestProvisional = {
                    methodname: SERVICES.GETPROVISIONALRANKING,
                    args: {
                        sid: sId,
                        cmid: cmId,
                        kid: kId
                    }
                };
                Ajax.call([requestProvisional])[0].done(function(provisionalRanking) {
                    provisionalRanking.programmedmode = true;
                    Templates.render(TEMPLATES.PROVISIONALRANKING, provisionalRanking).then(function(html, js) {
                        identifier.html(html);
                        Templates.runTemplateJS(js);
                        jQuery(REGION.LOADING).remove();
                    }).fail(Notification.exception);
                }).fail(Notification.exception);
            } else {
                let requestNext = {
                    methodname: SERVICES.NEXTQUESTION,
                    args: {
                        cmid: cmId,
                        sessionid: sId,
                        kid: kId,
                        manual: false
                    }
                };
                Ajax.call([requestNext])[0].done(function(nextQuestion) {
                    isRanking = false;
                    let templateQuestions = TEMPLATES.QUESTION;
                    if (nextQuestion.endsession === true && sessionConfig.session.showfinalgrade === 1) { // Final Ranking.
                        let requestFinal = {
                            methodname: SERVICES.GETFINALRANKING,
                            args: {
                                sid: sId,
                                cmid: cmId
                            }
                        };
                        Ajax.call([requestFinal])[0].done(function(finalRanking) {
                            finalRanking.programmedmode = true;
                            Templates.render(TEMPLATES.QUESTION, finalRanking).then(function(html, js) {
                                identifier.html(html);
                                Templates.runTemplateJS(js);
                                jQuery(REGION.LOADING).remove();
                            }).fail(Notification.exception);
                        }).fail(Notification.exception);
                    } else { // Normal Question.
                        Templates.render(templateQuestions, nextQuestion).then(function(html, js) {
                            identifier.html(html);
                            Templates.runTemplateJS(js);
                            mEvent.notifyFilterContentUpdated(document.querySelector(REGION.SESSIONCONTENT));
                            jQuery(REGION.LOADING).remove();
                        }).fail(Notification.exception);
                    }
                }).fail(async (e) =>  {
                    if (e.message && e.link) {
                        const modal = await ModalFactory.create({
                            title: 'KUET',
                            body: Templates.render('mod_kuet/error_modal', {message: e.message, link: e.link})
                        });
                        modal.getRoot().css('z-index', '3000');
                        modal.show();
                    }
                });
            }
        }).fail(Notification.exception);
    });
};

export const initQuestion = (selector) => {
    return new Question(selector);
};

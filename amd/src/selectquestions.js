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
 * @module    mod_kuet/selectquestions
 * @copyright  2023 Proyecto UNIMOODLE {@link https://unimoodle.github.io}
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";

import jQuery from 'jquery';
import {get_strings as getStrings} from 'core/str';
import Ajax from 'core/ajax';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Templates from 'core/templates';

let ACTION = {
    ADDQUESTIONS: '[data-action="add_questions"]',
    ADDQUESTION: '[data-action="add_question"]',
    SELECTALL: '#selectall',
    SELECTVISIBLES: '#selectvisibles',
};

let REGION = {
    PANEL: '[data-region="questions-panel"]',
    NUMBERSELECT: '#number_select',
    SELECTQUESTION: '.select_question',
    CONTENTQUESTIONS: '#content_questions',
    PAGENAVIGATION: '#page_navigation',
    CURRENTPAGE: '#current_page',
    SESSIONQUESTIONS: '[data-region="session-questions"]',
    LOADING: '[data-region="overlay-icon-container"]'
};

let SERVICES = {
    ADDQUESTIONS: 'mod_kuet_addquestions',
    SESSIONQUESTIONS: 'mod_kuet_sessionquestions'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    QUESTIONSSELECTED: 'mod_kuet/createsession/sessionquestions'
};

let cmId;
let sId;
let kuetId;
let showPerPage = 20;

/**
 * @constructor
 * @param {String} selector The selector for the page region containing the page.
 */
function SelectQuestions(selector) {
    this.node = jQuery(selector);
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    kuetId = this.node.attr('data-kuetid');
    this.initPanel();
}

/** @type {jQuery} The jQuery node for the page region. */
SelectQuestions.prototype.node = null;

SelectQuestions.prototype.initPanel = function() {
    this.node.find(ACTION.SELECTALL).on('change', this.selectAll.bind(this));
    this.node.find(ACTION.SELECTVISIBLES).on('change', this.selectVisibles.bind(this));
    this.node.find(ACTION.ADDQUESTIONS).on('click', this.addQuestions.bind(this));
    this.node.find(ACTION.ADDQUESTION).on('click', this.addQuestion.bind(this));
    this.node.find(REGION.SELECTQUESTION).on('change', this.selectQuestion.bind(this));
    this.countChecks();
    this.pagination();
};

SelectQuestions.prototype.countChecks = function() {
    let checksNumber = jQuery(REGION.SELECTQUESTION).length;
    let checkedsNumber = jQuery(REGION.SELECTQUESTION + ':checked').length;
    jQuery(REGION.NUMBERSELECT).html(checkedsNumber);
    if (checkedsNumber < checksNumber) {
        jQuery(ACTION.SELECTALL).prop('checked', false);
    }
    let checksNumberVisibles = jQuery('[style="display: flex;"] ' + REGION.SELECTQUESTION).length;
    let checkedsNumberVisibles = jQuery('[style="display: flex;"] ' + REGION.SELECTQUESTION + ':checked').length;
    if (checkedsNumberVisibles < checksNumberVisibles) {
        jQuery(ACTION.SELECTVISIBLES).prop('checked', false);
    }
};

SelectQuestions.prototype.selectAll = function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.node.find(REGION.SELECTQUESTION).prop('checked', jQuery(e.currentTarget).is(':checked'));
    this.countChecks();
};

SelectQuestions.prototype.selectVisibles = function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.node.find('[style="display: flex;"] ' + REGION.SELECTQUESTION).prop('checked', jQuery(e.currentTarget).is(':checked'));
    this.countChecks();
};

SelectQuestions.prototype.addQuestions = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let that = this;
    let questionschekced = jQuery(REGION.SELECTQUESTION + ':checked');
    if (questionschekced.length < 1) {
        const stringkeys = [
            {key: 'selectone', component: 'mod_kuet'},
            {key: 'selectone_desc', component: 'mod_kuet'}
        ];
        getStrings(stringkeys).then((langStrings) => {
            const title = langStrings[0];
            const message = langStrings[1];
            return ModalFactory.create({
                title: title,
                body: message,
                type: ModalFactory.types.CANCEL
            }).then(modal => {
                modal.getRoot().on(ModalEvents.hidden, () => {
                    modal.destroy();
                });
                return modal;
            });
        }).done(function(modal) {
            modal.show();
            // eslint-disable-next-line no-restricted-globals
        }).fail(Notification.exception);
    } else {
        let questions = [];
        questionschekced.each(function callback(index, question) {
            let questiondata = {
                questionid: jQuery(question).attr('data-questionnid'),
                sessionid: sId,
                kuetid: kuetId,
                qtype: jQuery(question).attr('data-type')
            };
            questions.push(questiondata);
        });
        const stringkeys = [
            {key: 'addquestions', component: 'mod_kuet'},
            {key: 'addquestions_desc', component: 'mod_kuet', param: questionschekced.length},
            {key: 'confirm', component: 'mod_kuet'}
        ];
        getStrings(stringkeys).then((langStrings) => {
            const title = langStrings[0];
            const message = langStrings[1];
            const buttonText = langStrings[2];
            return ModalFactory.create({
                title: title,
                body: message,
                type: ModalFactory.types.SAVE_CANCEL
            }).then(modal => {
                modal.setSaveButtonText(buttonText);
                modal.getRoot().on(ModalEvents.save, () => {
                    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                        let identifier = jQuery(REGION.PANEL);
                        identifier.append(html);
                    });
                    let request = {
                        methodname: SERVICES.ADDQUESTIONS,
                        args: {
                            questions: questions
                        }
                    };
                    Ajax.call([request])[0].done(function(response) {
                        if (response) {
                            jQuery(REGION.CONTENTQUESTIONS).find(REGION.SELECTQUESTION).prop('checked', false);
                            jQuery(ACTION.SELECTALL).prop('checked', false);
                            jQuery(ACTION.SELECTVISIBLES).prop('checked', false);
                            let request = {
                                methodname: SERVICES.SESSIONQUESTIONS,
                                args: {
                                    kuetid: kuetId,
                                    cmid: cmId,
                                    sid: sId
                                }
                            };
                            Ajax.call([request])[0].done(function(response) {
                                Templates.render(TEMPLATES.QUESTIONSSELECTED, response).then(function(html, js) {
                                    jQuery(REGION.SESSIONQUESTIONS).html(html);
                                    Templates.runTemplateJS(js);
                                    that.countChecks();
                                    jQuery(REGION.LOADING).remove();
                                }).fail(Notification.exception);
                            });
                        }
                    })
                    .fail( async (e) => {
                        const modal = await ModalFactory.create({
                            title: 'KUET',
                            body: Templates.render('mod_kuet/error_modal', {message: e.message, link: e.link})
                        });
                        modal.getRoot().css('z-index', '3000');
                        modal.show();
                    });
                });
                modal.getRoot().on(ModalEvents.hidden, () => {
                    modal.destroy();
                });
                return modal;
            });
        }).done(function(modal) {
            modal.show();
            // eslint-disable-next-line no-restricted-globals
        }).fail(Notification.exception);
    }
};

SelectQuestions.prototype.addQuestion = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let that = this;
    let questions = [];
    let questiondata = {
        questionid: jQuery(e.currentTarget).attr('data-questionnid'),
        sessionid: sId,
        kuetid: kuetId,
        qtype: jQuery(e.currentTarget).attr('data-type')
    };
    questions.push(questiondata);
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        let identifier = jQuery(REGION.PANEL);
        identifier.append(html);
    });
    let request = {
        methodname: SERVICES.ADDQUESTIONS,
        args: {
            questions: questions
        }
    };
    Ajax.call([request])[0].done(function(response) {
        if (response) {
            jQuery(REGION.CONTENTQUESTIONS).find(REGION.SELECTQUESTION).prop('checked', false);
            jQuery(ACTION.SELECTALL).prop('checked', false);
            jQuery(ACTION.SELECTVISIBLES).prop('checked', false);
            let request = {
                methodname: SERVICES.SESSIONQUESTIONS,
                args: {
                    kuetid: kuetId,
                    cmid: cmId,
                    sid: sId
                }
            };
            Ajax.call([request])[0].done(function(response) {
                Templates.render(TEMPLATES.QUESTIONSSELECTED, response).then(function(html, js) {
                    jQuery(REGION.SESSIONQUESTIONS).html(html);
                    Templates.runTemplateJS(js);
                    that.countChecks();
                    jQuery(REGION.LOADING).remove();
                }).fail(Notification.exception);
            });
        } else {
            // 3IP modal or notification error.
            alert('error');
            jQuery(REGION.LOADING).remove();
        }
    });
};

SelectQuestions.prototype.selectQuestion = function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.countChecks();
};

SelectQuestions.prototype.pagination = function() {
    let numberOfItems = jQuery(REGION.CONTENTQUESTIONS).children().length;
    let numberOfPages = Math.ceil(numberOfItems / showPerPage);
    jQuery(REGION.CURRENTPAGE).val(0);
    let navigationHtml = '';
    let currentLink = 0;
    if (numberOfItems === 0) {
        jQuery(ACTION.SELECTALL).remove();
        jQuery('label[for="selectall"]').remove();
    }
    if (numberOfPages > 1) {
        while (numberOfPages > currentLink) {
            navigationHtml +=
                '<div class="page-item" data-goto="' + currentLink + '">' +
                '<span class="page-link">' + (currentLink + 1) + '</span>' +
                '</div>';
            currentLink++;
        }
    } else {
        jQuery(ACTION.SELECTVISIBLES).remove();
        jQuery('label[for="selectvisibles"]').remove();
    }
    jQuery(REGION.PAGENAVIGATION).html(navigationHtml);
    this.node.find('.page-item').on('click', this.goToPage);
    jQuery(REGION.PAGENAVIGATION + ' .page-item:first').addClass('active');
    jQuery(REGION.CONTENTQUESTIONS).children().css('display', 'none');
    jQuery(REGION.CONTENTQUESTIONS).children().slice(0, showPerPage).css('display', 'flex');
};

SelectQuestions.prototype.goToPage = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let gotTo = jQuery(e.currentTarget).attr('data-goto');
    let startFrom = gotTo * showPerPage;
    let endOn = startFrom + showPerPage;
    jQuery(REGION.CONTENTQUESTIONS).children().css('display', 'none').slice(startFrom, endOn).css('display', 'flex');
    jQuery('.page-item[data-goto=' + gotTo + ']').addClass('active').siblings('.active').removeClass('active');
    jQuery(ACTION.SELECTVISIBLES).prop('checked', false);
    jQuery(REGION.CURRENTPAGE).val(gotTo);
};

export const initSelectQuestions = (selector) => {
    return new SelectQuestions(selector);
};

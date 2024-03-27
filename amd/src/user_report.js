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
 * @module    mod_kuet/user_report
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

"use strict";

import jQuery from 'jquery';
import Templates from 'core/templates';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import ModalKuet from 'mod_kuet/modal';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

let REGION = {
    ROOT: '[data-region="question-list"]', // This root.
    LOADING: '[data-region="overlay-icon-container"]',
};

let ACTION = {
    SEEANSWER: '[data-action="seeanswer"]',
};

let SERVICES = {
    GETQUESTION: 'mod_kuet_getquestion',
    USERQUESTIONRESPONSE: 'mod_kuet_getuserquestionresponse',
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    QUESTION: 'mod_kuet/questions/encasement'
};

UserReport.prototype.root = null;

/**
 * @constructor
 * @param {String} region
 */
function UserReport(region) {
    this.root = jQuery(region);
    this.root.find(ACTION.SEEANSWER).on('click', this.seeAnswer);
}

UserReport.prototype.seeAnswer = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let userId = jQuery(e.currentTarget).attr('data-userid');
    let sessionId = jQuery(e.currentTarget).attr('data-sessionid');
    let questionnId = jQuery(e.currentTarget).attr('data-questionnid');
    let cmId = jQuery(e.currentTarget).attr('data-cmid');
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        let identifier = jQuery(REGION.ROOT);
        identifier.append(html);
        let request = {
            methodname: SERVICES.GETQUESTION,
            args: {
                cmid: cmId,
                sid: sessionId,
                kid: questionnId
            }
        };
        Ajax.call([request])[0].done(function(question) {
            let requestAnswer = {
                methodname: SERVICES.USERQUESTIONRESPONSE,
                args: {
                    kid: question.kid,
                    cmid: cmId,
                    sid: sessionId,
                    uid: userId,
                    preview: true
                }
            };
            Ajax.call([requestAnswer])[0].done(function(answer) {
                const questionData = {
                    ...question,
                    ...answer
                };
                getString('viewquestion_user', 'mod_kuet').done((title) => {
                    Templates.render(TEMPLATES.QUESTION, questionData).then(function(html, js) {
                        ModalFactory.create({
                            classes: 'modal_kuet',
                            body: html,
                            title: title,
                            footer: '',
                            type: ModalKuet.TYPE
                        }).then(modal => {
                            modal.getRoot().on(ModalEvents.hidden, function() {
                                modal.destroy();
                            });
                            jQuery(REGION.LOADING).remove();
                            modal.show();
                            Templates.runTemplateJS(js);
                        }).fail(Notification.exception);
                    }).fail(Notification.exception);
                }).fail(Notification.exception);
            });
        });
    });
};

export const userReport = (region) => {
    return new UserReport(region);
};

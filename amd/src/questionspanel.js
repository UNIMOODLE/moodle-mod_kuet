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
 * @module    mod_kuet/questionspanel
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
import Templates from 'core/templates';
import Notification from 'core/notification';

let ACTION = {
    SELECTCATEGORY: '#id_movetocategory',
};

let SERVICES = {
    SELECTCATEGORY: 'mod_kuet_selectquestionscategory',
};

let REGION = {
    PANEL: '[data-region="questions-panel"]',
    NUMBERSELECT: '#number_select',
    SELECTQUESTION: '.select_question',
    CONTENTQUESTIONS: '[data-region="content-question"]',
    LOADING: '[data-region="overlay-icon-container"]'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    QUESTIONSFORSELECT: 'mod_kuet/createsession/contentquestions'
};

let cmId;
// eslint-disable-next-line no-unused-vars
let sId;

/**
 * @constructor
 * @param {String} selector The selector for the page region containing the page.
 */
function QuestionsPanel(selector) {
    this.node = jQuery(selector);
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    this.initPanel();
}

/** @type {jQuery} The jQuery node for the page region. */
QuestionsPanel.prototype.node = null;

QuestionsPanel.prototype.initPanel = function() {
    this.node.find(ACTION.SELECTCATEGORY).on('change', this.selectCategory.bind(this));
};

QuestionsPanel.prototype.selectCategory = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let categoryKey = jQuery(e.currentTarget).val();
    let identifier = jQuery(REGION.CONTENTQUESTIONS);
    if (jQuery(REGION.SELECTQUESTION + ':checked').length > 0) {
        const stringkeys = [
            {key: 'changecategory', component: 'mod_kuet'},
            {key: 'changecategory_desc', component: 'mod_kuet'},
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
                        methodname: SERVICES.SELECTCATEGORY,
                        args: {
                            categorykey: categoryKey,
                            cmid: cmId
                        }
                    };
                    Ajax.call([request])[0].done(function(response) {
                        let templateQuestions = TEMPLATES.QUESTIONSFORSELECT;
                        Templates.render(templateQuestions, response).then(function(html, js) {
                            identifier.html(html);
                            Templates.runTemplateJS(js);
                            jQuery(REGION.LOADING).remove();
                        }).fail(Notification.exception);
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
    } else {
        Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
            let identifier = jQuery(REGION.PANEL);
            identifier.append(html);
        });
        let request = {
            methodname: SERVICES.SELECTCATEGORY,
            args: {
                categorykey: categoryKey,
                cmid: cmId
            }
        };
        Ajax.call([request])[0].done(function(response) {
            let templateQuestions = TEMPLATES.QUESTIONSFORSELECT;
            Templates.render(templateQuestions, response).then(function(html, js) {
                identifier.html(html);
                Templates.runTemplateJS(js);
                jQuery(REGION.LOADING).remove();
            }).fail(Notification.exception);
        });
    }
};

export const initQuestionsPanel = (selector) => {
    return new QuestionsPanel(selector);
};

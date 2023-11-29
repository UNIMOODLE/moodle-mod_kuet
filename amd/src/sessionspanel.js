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
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 *
 * @module    mod_kuet/sessionspanel
 * @copyright  2023 Proyecto UNIMOODLE
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
    COPYSESSION: '[data-action="copy_session"]',
    DELETESESSION: '[data-action="delete_session"]',
    INITSESSION: '[data-action="init_session"]'
};

let SERVICES = {
    SESSIONSPANEL: 'mod_kuet_sessionspanel',
    COPYSESSION: 'mod_kuet_copysession',
    DELETESESSION: 'mod_kuet_deletesession',
    STARTSESSION: 'mod_kuet_startsession'
};

let REGION = {
    SESSIONSPANEL: '[data-region="sessions-panel-reload"]',
    PANEL: '[data-region="sessions-panel"]'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    PANEL: 'mod_kuet/sessions_panel'
};

let cmId;
let courseId;

/**
 * @constructor
 * @param {String} selector The selector for the page region containing the page.
 */
function SessionsPanel(selector) {
    this.node = jQuery(selector);
    courseId = this.node.attr('data-courseid');
    cmId = this.node.attr('data-cmid');
    this.initPanel();
}

/** @type {jQuery} The jQuery node for the page region. */
SessionsPanel.prototype.node = null;

SessionsPanel.prototype.initPanel = function() {
    this.node.find(ACTION.COPYSESSION).on('click', this.copySession);
    this.node.find(ACTION.DELETESESSION).on('click', this.deleteSession);
    this.node.find(ACTION.INITSESSION).on('click', this.initSession);
};

SessionsPanel.prototype.copySession = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let sessionId = jQuery(e.currentTarget).attr('data-sessionid');
    const stringkeys = [
        {key: 'copysession', component: 'mod_kuet'},
        {key: 'copysession_desc', component: 'mod_kuet'},
        {key: 'confirm', component: 'mod_kuet'},
        {key: 'copysessionerror', component: 'mod_kuet'},
    ];
    getStrings(stringkeys).then((langStrings) => {
        const title = langStrings[0];
        const confirmMessage = langStrings[1];
        const buttonText = langStrings[2];
        const copysessionerror = langStrings[3];
        return ModalFactory.create({
            title: title,
            body: confirmMessage,
            type: ModalFactory.types.SAVE_CANCEL
        }).then(modal => {
            modal.setSaveButtonText(buttonText);
            modal.getRoot().on(ModalEvents.save, () => {
                Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                    let identifier = jQuery(REGION.PANEL);
                    identifier.append(html);
                    let request = {
                        methodname: SERVICES.COPYSESSION,
                        args: {
                            courseid: courseId,
                            cmid: cmId,
                            sessionid: sessionId
                        }
                    };
                    Ajax.call([request])[0].done(function(copied) {
                        if (copied) {
                            let requestPanel = {
                                methodname: SERVICES.SESSIONSPANEL,
                                args: {
                                    cmid: cmId
                                }
                            };
                            // eslint-disable-next-line max-nested-callbacks
                            Ajax.call([requestPanel])[0].done(function(response) {
                                // eslint-disable-next-line max-nested-callbacks,promise/always-return
                                Templates.render(TEMPLATES.PANEL, response).then((html, js) => {
                                    identifier.html(html);
                                    Templates.runTemplateJS(js);
                                }).fail(Notification.exception);
                            }).fail(Notification.exception);
                        } else {
                            Notification.exception({message: copysessionerror});
                        }
                    });
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
};

SessionsPanel.prototype.deleteSession = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let sessionId = jQuery(e.currentTarget).attr('data-sessionid');
    const stringkeys = [
        {key: 'deletesession', component: 'mod_kuet'},
        {key: 'deletesession_desc', component: 'mod_kuet'},
        {key: 'confirm', component: 'mod_kuet'},
        {key: 'deletesessionerror', component: 'mod_kuet'},
    ];
    getStrings(stringkeys).then((langStrings) => {
        const title = langStrings[0];
        const confirmMessage = langStrings[1];
        const buttonText = langStrings[2];
        const copysessionerror = langStrings[3];
        return ModalFactory.create({
            title: title,
            body: confirmMessage,
            type: ModalFactory.types.SAVE_CANCEL
        }).then(modal => {
            modal.setSaveButtonText(buttonText);
            modal.getRoot().on(ModalEvents.save, () => {
                Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
                    let identifier = jQuery(REGION.PANEL);
                    identifier.append(html);
                    let request = {
                        methodname: SERVICES.DELETESESSION,
                        args: {
                            courseid: courseId,
                            cmid: cmId,
                            sessionid: sessionId
                        }
                    };
                    Ajax.call([request])[0].done(function(deleted) {
                        if (deleted) {
                            let requestPanel = {
                                methodname: SERVICES.SESSIONSPANEL,
                                args: {
                                    cmid: cmId
                                }
                            };
                            // eslint-disable-next-line max-nested-callbacks
                            Ajax.call([requestPanel])[0].done(function(response) {
                                let templatePanel = TEMPLATES.PANEL;
                                // eslint-disable-next-line max-nested-callbacks
                                Templates.render(templatePanel, response).done(function(html, js) {
                                    identifier.html(html);
                                    Templates.runTemplateJS(js);
                                });
                            }).fail(Notification.exception);
                        } else {
                            Notification.exception({message: copysessionerror});
                        }
                    });
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

};

SessionsPanel.prototype.initSession = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let sessionId = jQuery(e.currentTarget).attr('data-sessionid');
    const stringkeys = [
        {key: 'init_session', component: 'mod_kuet'},
        {key: 'init_session_desc', component: 'mod_kuet'},
        {key: 'confirm', component: 'mod_kuet'}
    ];
    getStrings(stringkeys).then((langStrings) => {
        return ModalFactory.create({
            title: langStrings[0],
            body: langStrings[1],
            type: ModalFactory.types.SAVE_CANCEL
        }).then(modal => {
            modal.setSaveButtonText(langStrings[2]);
            modal.getRoot().on(ModalEvents.save, () => {
                let request = {
                    methodname: SERVICES.STARTSESSION,
                    args: {
                        cmid: cmId,
                        sessionid: sessionId
                    }
                };
                Ajax.call([request])[0].done(function(response) {
                    if (response.started === true) {
                        window.location.replace(M.cfg.wwwroot + '/mod/kuet/session.php?cmid=' + cmId + '&sid=' + sessionId);
                    } else {
                        const stringkeyserror = [
                            {key: 'error_initsession', component: 'mod_kuet'},
                            {key: 'error_initsession_desc', component: 'mod_kuet'},
                            {key: 'confirm', component: 'mod_kuet'}
                        ];
                        getStrings(stringkeyserror).then((langStringsError) => {
                            return ModalFactory.create({
                                title: langStringsError[0],
                                body: langStringsError[1],
                                type: ModalFactory.types.ALERT,
                                buttons: {
                                    cancel: langStringsError[2],
                                },
                                removeOnClose: true,
                                // eslint-disable-next-line max-nested-callbacks
                            }).then(modalError => {
                                modalError.getRoot().on(ModalEvents.save, () => {
                                    location.reload();
                                });
                                modalError.getRoot().on(ModalEvents.hidden, () => {
                                    modalError.destroy();
                                    location.reload();
                                });
                                return modalError;
                            });
                        }).done(function(modalError) {
                            modalError.show();
                            // eslint-disable-next-line no-restricted-globals
                        }).fail(Notification.exception);
                    }
                }).fail(Notification.exception);
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
};

export const initSessionsPanel = (selector) => {
    return new SessionsPanel(selector);
};

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
    SESSIONSPANEL: 'mod_jqshow_sessionspanel',
    COPYSESSION: 'mod_jqshow_copysession',
    DELETESESSION: 'mod_jqshow_deletesession'
};

let REGION = {
    SESSIONSPANEL: '[data-region="sessions-panel-reload"]',
    PANEL: '[data-region="sessions-panel"]'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    PANEL: 'mod_jqshow/sessions_panel'
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
        {key: 'copysession', component: 'mod_jqshow'},
        {key: 'copysession_desc', component: 'mod_jqshow'},
        {key: 'confirm', component: 'mod_jqshow'},
        {key: 'copysessionerror', component: 'mod_jqshow'},
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
        {key: 'deletesession', component: 'mod_jqshow'},
        {key: 'deletesession_desc', component: 'mod_jqshow'},
        {key: 'confirm', component: 'mod_jqshow'},
        {key: 'deletesessionrror', component: 'mod_jqshow'},
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
        {key: 'init_session', component: 'mod_jqshow'},
        {key: 'init_session_desc', component: 'mod_jqshow'},
        {key: 'confirm', component: 'mod_jqshow'}
    ];
    getStrings(stringkeys).then((langStrings) => {
        const title = langStrings[0];
        const confirmMessage = langStrings[1];
        const buttonText = langStrings[2];
        return ModalFactory.create({
            title: title,
            body: confirmMessage,
            type: ModalFactory.types.SAVE_CANCEL
        }).then(modal => {
            modal.setSaveButtonText(buttonText);
            modal.getRoot().on(ModalEvents.save, () => {
                window.location.replace(M.cfg.wwwroot + '/mod/jqshow/session.php?cmid=' + cmId + '&sid=' + sessionId);
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

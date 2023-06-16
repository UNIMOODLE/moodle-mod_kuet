"use strict";
/* eslint-disable no-unused-vars */

import CustomEvents from 'core/custom_interaction_events';
import Modal from 'core/modal';
import ModalRegistry from 'core/modal_registry';

let SELECTORS = {
    CANCEL_BUTTON: '[data-action="close"]',
};

const ModalJqshow = class extends Modal {
    static TYPE = 'mod_jqshow/modal';
    static TEMPLATE = 'mod_jqshow/modal';

    registerEventListeners() {
        // Call the parent registration.
        super.registerEventListeners();

        // Register to close on save/cancel.
        this.registerCloseOnSave();
        this.registerCloseOnCancel();
        this.getModal().on(CustomEvents.events.activate, SELECTORS.CANCEL_BUTTON, function() {
            this.destroy();
        }.bind(this));
    }
};

ModalRegistry.register(ModalJqshow.TYPE, ModalJqshow, ModalJqshow.TEMPLATE);

export default ModalJqshow;

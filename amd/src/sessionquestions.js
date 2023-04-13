"use strict";
/* eslint-disable no-unused-vars */ // TODO remove.

import jQuery from 'jquery';
import {get_strings as getStrings} from 'core/str';
import Ajax from 'core/ajax';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import Notification from 'core/notification';

let ACTION = {
    COPYQUESTION: '[data-action="copy_question"]',
    DELETEQUESTION: '[data-action="delete_question"]',
};

let SERVICES = {
    COPYQUESTION: 'mod_jqshow_copyquestion',
    DELETEQUESTION: 'mod_jqshow_deletequestion'
};

let REGION = {
    PANEL: '[data-region="questions-panel"]',
    LOADING: '[data-region="overlay-icon-container"]',
    SESSIONQUESTIONS: '[data-region="session-questions"]'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    SUCCESS: 'core/notification_success',
    ERROR: 'core/notification_error',
    QUESTIONSSELECTED: 'mod_jqshow/createsession/sessionquestions'
};

let cmId;
let sId;
let jqshowId;

/**
 * @constructor
 * @param {String} selector The selector for the page region containing the page.
 */
function SessionQuestions(selector) {
    this.node = jQuery(selector);
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    jqshowId = this.node.attr('data-jqshowid');
    this.initPanel();
}

/** @type {jQuery} The jQuery node for the page region. */
SessionQuestions.prototype.node = null;

SessionQuestions.prototype.initPanel = function() {
    this.node.find(ACTION.COPYQUESTION).on('click', this.copyQuestion);
    this.node.find(ACTION.DELETEQUESTION).on('click', this.deleteQuestion);
};

SessionQuestions.prototype.copyQuestion = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let questionId = jQuery(e.currentTarget).attr('data-questionnid');
    alert('copyQuestion ' + questionId);
};

SessionQuestions.prototype.deleteQuestion = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let questionId = jQuery(e.currentTarget).attr('data-questionnid');
    alert('deleteQuestion ' + questionId);
};

export const initSessionQuestions = (selector) => {
    return new SessionQuestions(selector);
};

"use strict";
/* eslint-disable no-unused-vars */ // TODO remove.

import jQuery from 'jquery';

let ACTION = {
    COPYQUESTION: '[data-action="copy_question"]',
    DELETEQUESTION: '[data-action="delete_question"]',
    SELECTCATEGORY: '#id_movetocategory',
    ADDQUESTIONS: '[data-action="add_questions"]',
    ADDQUESTION: '[data-action="add_question"]',
    SELECTTALL: '#selectall',
};

let REGION = {
    PANEL: '[data-region="questions-panel"]',
    NUMBERSELECT: '#number_select',
    SELECTQUESTION: '.select_question',
    CONTENTQUESTIONS: '[data-region="content-question"]'
};

let cmId;
let sId;

/**
 * @constructor
 * @param {String} selector The selector for the page region containing the page.
 */
function SelectQuestions(selector) {
    this.node = jQuery(selector);
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    this.initPanel();
}

/** @type {jQuery} The jQuery node for the page region. */
SelectQuestions.prototype.node = null;

SelectQuestions.prototype.initPanel = function() {
    this.node.find(ACTION.SELECTTALL).on('change', this.selectAll.bind(this));
    this.node.find(ACTION.ADDQUESTIONS).on('click', this.addQuestions);
    this.node.find(ACTION.ADDQUESTION).on('click', this.addQuestion);
    this.node.find(REGION.SELECTQUESTION).on('change', this.selectQuestion.bind(this));
};

SelectQuestions.prototype.selectAll = function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.node.find(REGION.SELECTQUESTION).prop('checked', jQuery(e.currentTarget).is(':checked'));
    this.countCheckeds();
};

SelectQuestions.prototype.addQuestions = function(e) {
    e.preventDefault();
    e.stopPropagation();
    alert('addQuestions ');
};

SelectQuestions.prototype.addQuestion = function(e) {
    e.preventDefault();
    e.stopPropagation();
    let questionId = jQuery(e.currentTarget).attr('data-questionnid');
    alert('addQuestion ' + questionId);
};

SelectQuestions.prototype.selectQuestion = function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.countCheckeds();
};

SelectQuestions.prototype.countCheckeds = function(e) {
    // TODO change for datastorage, para poder contabilizar todos los marcados tras la paginación.
    jQuery(REGION.NUMBERSELECT).html(jQuery(REGION.SELECTQUESTION + ':checked').length);
};

export const initSelectQuestions = (selector) => {
    return new SelectQuestions(selector);
};

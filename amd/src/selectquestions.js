"use strict";
/* eslint-disable no-unused-vars */ // TODO remove.

import jQuery from 'jquery';

let ACTION = {
    ADDQUESTIONS: '[data-action="add_questions"]',
    ADDQUESTION: '[data-action="add_question"]',
    SELECTALL: '#selectall',
    SELECTVISIBLES: '#selectvisibles',
};

let REGION = {
    NUMBERSELECT: '#number_select',
    SELECTQUESTION: '.select_question',
    CONTENTQUESTIONS: '#content_questions',
    PAGENAVIGATION: '#page_navigation',
    CURRENTPAGE: '#current_page'
};

let cmId;
let sId;
let showPerPage = 20;

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
    this.node.find(ACTION.SELECTALL).on('change', this.selectAll.bind(this));
    this.node.find(ACTION.SELECTVISIBLES).on('change', this.selectVisibles.bind(this));
    this.node.find(ACTION.ADDQUESTIONS).on('click', this.addQuestions);
    this.node.find(ACTION.ADDQUESTION).on('click', this.addQuestion);
    this.node.find(REGION.SELECTQUESTION).on('change', this.selectQuestion.bind(this));
    this.countChecks();
    this.pagination();
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
    this.countChecks();
};

SelectQuestions.prototype.countChecks = function(e) {
    // TODO change for datastorage, para poder contabilizar todos los marcados tras la paginación.
    jQuery(REGION.NUMBERSELECT).html(jQuery(REGION.SELECTQUESTION + ':checked').length);
};

SelectQuestions.prototype.pagination = function() {
    let numberOfItems = jQuery(REGION.CONTENTQUESTIONS).children().length;
    let numberOfPages = Math.ceil(numberOfItems / showPerPage);
    jQuery(REGION.CURRENTPAGE).val(0);
    let navigationHtml = '';
    let currentLink = 0;
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

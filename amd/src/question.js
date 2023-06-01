"use strict";

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';

let ACTION = {
    EXPAND: '[data-action="question-fullscreen"]',
    COMPRESS: '[data-action="question-exit-fullscreen"]',
    NEXTQUESTION: '[data-action="next-question"]'
};

let REGION = {
    PAGE_HEADER: '#page-header',
    BODY: 'body.path-mod-jqshow',
    NAV: 'nav.navbar',
    SESSIONCONTENT: '[data-region="session-content"]'
};

let SERVICES = {
    NEXTQUESTION: 'mod_jqshow_nextquestion'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    QUESTION: 'mod_jqshow/questions/encasement'
};

let cmId;
let sId;

/**
 * @constructor
 * @param {String} selector
 */
function Question(selector) {
    this.node = jQuery(selector);
    this.initQuestion();
}

/** @type {jQuery} The jQuery node for the page region. */
Question.prototype.node = null;

Question.prototype.initQuestion = function() {
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    this.node.find(ACTION.EXPAND).on('click', this.fullScreen);
    this.node.find(ACTION.COMPRESS).on('click', this.exitFullScreen);
    jQuery(ACTION.NEXTQUESTION).on('click', this.nextQuestion);
    let that = this;
    jQuery(document).keyup(function(e) {
        if (e.key === 'Escape') {
            that.exitFullScreen();
        }
    });
    addEventListener('questionEnd', () => {
        // TODO this button is only for programmed mode, in manual mode the teacher controls.
        jQuery(ACTION.NEXTQUESTION).removeClass('d-none');
    }, false);
};

Question.prototype.fullScreen = function(e) {
    e.preventDefault();
    e.stopPropagation();
    jQuery(ACTION.EXPAND).css('display', 'none');
    jQuery(ACTION.COMPRESS).css('display', 'block');
    jQuery(REGION.BODY).addClass('fullscreen');
    jQuery(window).scrollTop(0);
    jQuery('html, body').animate({scrollTop: 0}, 500);
    let element = document.getElementById("page-mod-jqshow-view");
    if (element === undefined || element === null) {
        element = document.getElementById("page-mod-jqshow-preview");
    }
    if (element.requestFullscreen) {
        element.requestFullscreen();
    } else if (element.webkitRequestFullscreen) { /* Safari */
        element.webkitRequestFullscreen();
    } else if (element.msRequestFullscreen) { /* IE11 */
        element.msRequestFullscreen();
    }
};

Question.prototype.exitFullScreen = function() {
    jQuery(ACTION.EXPAND).css('display', 'block');
    jQuery(ACTION.COMPRESS).css('display', 'none');
    jQuery(REGION.BODY).removeClass('fullscreen');
    if (document.fullscreen) {
        document.exitFullscreen();
    }
};

Question.prototype.nextQuestion = function(e) {
    e.preventDefault();
    e.stopPropagation();
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        let identifier = jQuery(REGION.SESSIONCONTENT);
        identifier.append(html);
        let request = {
            methodname: SERVICES.NEXTQUESTION,
            args: {
                cmid: cmId,
                sessionid: sId,
                jqid: jQuery(e.currentTarget).attr('data-jqid')
            }
        };
        Ajax.call([request])[0].done(function(response) {
            let templateQuestions = TEMPLATES.QUESTION;
            Templates.render(templateQuestions, response).then(function(html, js) {
                identifier.html(html);
                Templates.runTemplateJS(js);
                jQuery(REGION.LOADING).remove();
            }).fail(Notification.exception);
        });
    });
};

export const initQuestion = (selector) => {
    return new Question(selector);
};

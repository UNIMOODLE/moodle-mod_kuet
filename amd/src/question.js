"use strict";

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import mEvent from 'core/event';

let ACTION = {
    EXPAND: '[data-action="question-fullscreen"]',
    COMPRESS: '[data-action="question-exit-fullscreen"]',
    NEXTQUESTION: '[data-action="next-question"]'
};

let REGION = {
    PAGE_HEADER: '#page-header',
    BODY: 'body.path-mod-jqshow',
    NAV: 'nav.navbar',
    SESSIONCONTENT: '[data-region="session-content"]',
    QUESTIONCONTENT: '[data-region="question-content"]',
    RANKINGCONTENT: '[data-region="ranking-content"]',
};

let SERVICES = {
    NEXTQUESTION: 'mod_jqshow_nextquestion',
    GETSESSIONCONFIG: 'mod_jqshow_getsession',
    GETPROVISIONALRANKING: 'mod_jqshow_getprovisionalranking',
    GETFINALRANKING: 'mod_jqshow_getfinalranking',
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading',
    QUESTION: 'mod_jqshow/questions/encasement',
    PROVISIONALRANKING: 'mod_jqshow/ranking/provisional'
};

let cmId;
let sId;
let jqId;
let isRanking = false;

/**
 * @constructor
 * @param {String} selector
 */
function Question(selector) {
    if (selector === REGION.RANKINGCONTENT) {
        isRanking = true;
    }
    this.node = jQuery(selector);
    this.initQuestion();
}

/** @type {jQuery} The jQuery node for the page region. */
Question.prototype.node = null;

Question.prototype.initQuestion = function() {
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    jqId = this.node.attr('data-jqid');
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

Question.prototype.nextQuestion = function(e) { // Only for programed modes, not used by sockets.
    e.preventDefault();
    e.stopPropagation();
    let identifier = jQuery(REGION.SESSIONCONTENT);
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        identifier.append(html);
        let requestNext = {
            methodname: SERVICES.NEXTQUESTION,
            args: {
                cmid: cmId,
                sessionid: sId,
                jqid: jqId,
                manual: false,
                isranking: isRanking
            }
        };
        Ajax.call([requestNext])[0].done(function(nextQuestion) {
            let requestConfig = {
                methodname: SERVICES.GETSESSIONCONFIG,
                args: {
                    sid: sId,
                    cmid: cmId
                }
            };
            Ajax.call([requestConfig])[0].done(function(sessionConfig) {
                if (nextQuestion.endsession === true && sessionConfig.session.showfinalgrade === 1) { // Final Ranking.
                    let requestFinal = {
                        methodname: SERVICES.GETFINALRANKING,
                        args: {
                            sid: sId,
                            cmid: cmId
                        }
                    };
                    Ajax.call([requestFinal])[0].done(function(finalRanking) {
                        finalRanking.programmedmode = true;
                        Templates.render(TEMPLATES.QUESTION, finalRanking).then(function(html, js) {
                            identifier.html(html);
                            Templates.runTemplateJS(js);
                            jQuery(REGION.LOADING).remove();
                        }).fail(Notification.exception);
                    }).fail(Notification.exception);
                } else if (sessionConfig.session.showgraderanking === 1 && isRanking === false) { // Provisional Ranking.
                    let requestProvisional = {
                        methodname: SERVICES.GETPROVISIONALRANKING,
                        args: {
                            sid: sId,
                            cmid: cmId,
                            jqid: jqId
                        }
                    };
                    Ajax.call([requestProvisional])[0].done(function(provisionalRanking) {
                        provisionalRanking.programmedmode = true;
                        Templates.render(TEMPLATES.PROVISIONALRANKING, provisionalRanking).then(function(html, js) {
                            identifier.html(html);
                            Templates.runTemplateJS(js);
                            jQuery(REGION.LOADING).remove();
                        }).fail(Notification.exception);
                    }).fail(Notification.exception);
                } else { // Normal Question.
                    isRanking = false;
                    let templateQuestions = TEMPLATES.QUESTION;
                    Templates.render(templateQuestions, nextQuestion).then(function(html, js) {
                        identifier.html(html);
                        Templates.runTemplateJS(js);
                        mEvent.notifyFilterContentUpdated(document.querySelector(REGION.SESSIONCONTENT));
                        jQuery(REGION.LOADING).remove();
                    }).fail(Notification.exception);
                }
            }).fail(Notification.exception);
        }).fail(Notification.exception);
    });
};

export const initQuestion = (selector) => {
    return new Question(selector);
};

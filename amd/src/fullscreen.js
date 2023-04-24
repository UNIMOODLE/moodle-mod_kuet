"use strict";

import jQuery from 'jquery';

let ACTION = {
    EXPAND: '[data-action="question-fullscreen"]',
    COMPRESS: '[data-action="question-exit-fullscreen"]'
};

let REGION = {
    PAGE_HEADER: '#page-header',
    BODY: 'body.path-mod-jqshow',
    NAV: 'nav.navbar'
};

/**
 * @constructor
 * @param {String} selector
 */
function FullScreen(selector) {
    this.node = jQuery(selector);
    this.initFullScreen();
}

/** @type {jQuery} The jQuery node for the page region. */
FullScreen.prototype.node = null;

FullScreen.prototype.initFullScreen = function() {
    this.node.find(ACTION.EXPAND).on('click', this.fullScreen);
    this.node.find(ACTION.COMPRESS).on('click', this.exitFullScreen);
    this.node.find(ACTION.COMPRESS).on('click', this.exitFullScreen);
    let that = this;
    jQuery(document).keyup(function(e) {
        if (e.key === 'Escape') {
            that.exitFullScreen();
        }
    });
};

FullScreen.prototype.fullScreen = function(e) {
    e.preventDefault();
    e.stopPropagation();
    jQuery(ACTION.EXPAND).css('display', 'none');
    jQuery(ACTION.COMPRESS).css('display', 'block');
    jQuery(REGION.BODY).addClass('fullscreen');
    jQuery(window).scrollTop(0);
    jQuery('html, body').animate({scrollTop: 0}, 500);
    let element = document.getElementById("page-mod-jqshow-preview"); // TODO review.
    if (element.requestFullscreen) {
        element.requestFullscreen();
    } else if (element.webkitRequestFullscreen) { /* Safari */
        element.webkitRequestFullscreen();
    } else if (element.msRequestFullscreen) { /* IE11 */
        element.msRequestFullscreen();
    }
};

FullScreen.prototype.exitFullScreen = function() {
    jQuery(ACTION.EXPAND).css('display', 'block');
    jQuery(ACTION.COMPRESS).css('display', 'none');
    jQuery(REGION.BODY).removeClass('fullscreen');
    if (document.fullscreen) {
        document.exitFullscreen();
    }
};

export const initFullScreen = (selector) => {
    return new FullScreen(selector);
};

"use strict";

import jQuery from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import mEvent from 'core/event';
/*import DragDrop from 'core/local/reactive/dragdrop';*/
import dragDrop from 'core/dragdrop';

let ACTION = {
    SEND_RESPONSE: '[data-action="send-response"]',
};

let REGION = {
    ROOT: '[data-region="question-content"]',
    LOADING: '[data-region="overlay-icon-container"]',
    NEXT: '[data-action="next-question"]',
    TIMER: '[data-region="question-timer"]',
    SECONDS: '[data-region="seconds"]',
    CONTENTFEEDBACKS: '[data-region="containt-feedbacks"]',
    FEEDBACK: '[data-region="statement-feedback"]',
    FEEDBACKANSWER: '[data-region="answer-feedback"]',
    FEEDBACKBACGROUND: '[data-region="feedback-background"]',
    AREA: '#drag_and_drop_area',
    DRAGHOME: '.draghome',
    DRAGELEMENT: '.draghome.user-select-none',
    DROPELEMENT: '.drop.active',
    INPUTPLACE: '.placeinput',
    STATEMENTTEXT: '[data-region="statement-text"]',
    STATEMENTTEXT_CONTENT: '[data-region="statement-text"] .statement-text',
    ICONSANSWERS: '.icon.text-success, .icon.text-danger',
    ANSWERSCONTENT: '.answercontainer',
    ANSWERSCONTENTGROUP: '.answercontainer [class*="draggrouphomes"]',
    MODALBODY: '.modal-body'
};

let SERVICES = {
    REPLY: 'mod_jqshow_ddwtos'
};

let TEMPLATES = {
    LOADING: 'core/overlay_loading'
};

let cmId;
let sId;
let questionid;
let jqshowId;
let jqid;
let questionEnd = false;
let showQuestionFeedback = false;
let manualMode = false;

/** @type {jQuery} The jQuery node for the page region. */
Ddwtos.prototype.node = null;
Ddwtos.prototype.endTimer = new Event('endTimer');
Ddwtos.prototype.studentQuestionEnd = new Event('studentQuestionEnd');
Ddwtos.prototype.questionAnswer = {};

/**
 * @constructor
 * @param {String} selector
 * @param {Boolean} showquestionfeedback
 * @param {Boolean} manualmode
 * @param {String} jsonresponse
 */
function Ddwtos(selector, showquestionfeedback = false, manualmode = false, jsonresponse = '') {
    this.node = jQuery(selector);
    sId = this.node.attr('data-sid');
    cmId = this.node.attr('data-cmid');
    questionid = this.node.attr('data-questionid');
    jqshowId = this.node.attr('data-jqshowid');
    jqid = this.node.attr('data-jqid');
    showQuestionFeedback = showquestionfeedback;
    manualMode = manualmode;
    questionEnd = false;
    if (jsonresponse !== '') {
        this.answered(JSON.parse(jsonresponse), false);
        if (manualMode === false || jQuery(REGION.MODALBODY).length) {
            questionEnd = true;
            if (showQuestionFeedback === true) {
                this.showFeedback();
            }
            this.showAnswers();
        }
    }
    if (jQuery(REGION.MODALBODY).length) {
        setTimeout(() => {
            Ddwtos.prototype.initDdwtos();
        }, 500); // For modal preview, is executed before the DOM is rendered.
    } else {
        Ddwtos.prototype.initDdwtos();
    }
    questionManager.init('drag_and_drop_area');
}

Ddwtos.prototype.initDdwtos = function() {
    jQuery(ACTION.SEND_RESPONSE).on('click', Ddwtos.prototype.reply);
    if (jQuery(REGION.STATEMENTTEXT_CONTENT).hasClass('randomanswers')) {
        let groups = document.querySelectorAll(REGION.ANSWERSCONTENTGROUP);
        groups.forEach(function(element){
            let draghomes = Array.prototype.slice.call(element.getElementsByClassName('draghome'));
            draghomes.forEach(function(drag){
                element.removeChild(drag);
            });
            Ddwtos.prototype.shuffleArray(draghomes);
            draghomes.forEach(function(drag){
                element.appendChild(drag);
            });
        });
    }
    Ddwtos.prototype.initEvents();
    Ddwtos.prototype.resizeAllDragsAndDrops();
    Ddwtos.prototype.cloneDrags();
    Ddwtos.prototype.positionDrags();
    if (questionEnd === false) {
        Ddwtos.prototype.initDragAndDrop();
    }
};

Ddwtos.prototype.shuffleArray = function(array) {
    for (let i = array.length - 1; i > 0; i--) {
        let j = Math.floor(Math.random() * (i + 1));
        let temp = array[i];
        array[i] = array[j];
        array[j] = temp;
    }
    return array;
};

Ddwtos.prototype.initEvents = function() {
    addEventListener('timeFinish', Ddwtos.prototype.reply, {once: true});
    if (manualMode !== false) {
        addEventListener('teacherQuestionEnd_' + jqid, (e) => {
            if (questionEnd !== true) {
                Ddwtos.prototype.reply();
            }
            e.detail.statistics.forEach((statistic) => {
                jQuery('[data-answerid="' + statistic.answerid + '"] .numberofreplies').html(statistic.numberofreplies);
            });
        }, {once: true});
        addEventListener('pauseQuestion_' + jqid, () => {
            Ddwtos.prototype.pauseQuestion();
        }, false);
        addEventListener('playQuestion_' + jqid, () => {
            Ddwtos.prototype.playQuestion();
        }, false);
        addEventListener('showAnswers_' + jqid, () => {
            Ddwtos.prototype.showAnswers();
        }, false);
        addEventListener('hideAnswers_' + jqid, () => {
            Ddwtos.prototype.hideAnswers();
        }, false);
        addEventListener('showStatistics_' + jqid, () => {
            Ddwtos.prototype.showStatistics();
        }, false);
        addEventListener('hideStatistics_' + jqid, () => {
            Ddwtos.prototype.hideStatistics();
        }, false);
        addEventListener('showFeedback_' + jqid, () => {
            Ddwtos.prototype.showFeedback();
        }, false);
        addEventListener('hideFeedback_' + jqid, () => {
            Ddwtos.prototype.hideFeedback();
        }, false);
    }

    window.onbeforeunload = function() {
        if (jQuery(REGION.SECONDS).length > 0 && questionEnd === false) {
            Ddwtos.prototype.reply();
            return 'Because the question is overdue and an attempt has been made to reload the page,' +
                ' the question has remained unanswered.';
        }
    };
};

Ddwtos.prototype.removeEvents = function() {
    removeEventListener('timeFinish', Ddwtos.prototype.reply, {once: true});
};

Ddwtos.prototype.reply = function() {
    Templates.render(TEMPLATES.LOADING, {visible: true}).done(function(html) {
        jQuery(REGION.ROOT).append(html);
        dispatchEvent(Ddwtos.prototype.endTimer);
        Ddwtos.prototype.removeEvents();
        let inputPlaces = document.querySelectorAll(REGION.INPUTPLACE);
        let response = {};
        let i = 1;
        inputPlaces.forEach(function(input) {
            response['p' + i] = input.value;
            i++;
        });
        let timeLeft = parseInt(jQuery(REGION.SECONDS).text());
        let request = {
            methodname: SERVICES.REPLY,
            args: {
                sessionid: sId,
                jqshowid: jqshowId,
                cmid: cmId,
                questionid: questionid,
                jqid: jqid,
                timeleft: timeLeft || 0,
                preview: false,
                response: JSON.stringify(response)
            }
        };
        Ajax.call([request])[0].done(function(response) {
            if (response.reply_status === true) {
                questionEnd = true;
                Ddwtos.prototype.answered(response, true);
                dispatchEvent(Ddwtos.prototype.studentQuestionEnd);
                if (jQuery('.modal-body').length) { // Preview.
                    Ddwtos.prototype.showAnswers();
                    if (showQuestionFeedback === true) {
                        Ddwtos.prototype.showFeedback();
                    }
                } else {
                    if (manualMode === false) {
                        Ddwtos.prototype.showAnswers();
                        if (showQuestionFeedback === true) {
                            Ddwtos.prototype.showFeedback();
                        }
                    }
                }
            } else {
                alert('error');
            }
            jQuery(REGION.LOADING).remove();
        }).fail(Notification.exception);
    });
};

/* DRAG&DROP */
Ddwtos.prototype.initDragAndDrop = function() {
    let dragItems = document.querySelectorAll(REGION.DRAGELEMENT);
    dragItems.forEach(function(dragElement) {
        dragElement.setAttribute('draggable', 'false');
        jQuery(dragElement).unbind('mousedown touchstart');
        jQuery(dragElement).on('mousedown touchstart', Ddwtos.prototype.handleDragStart.bind(this));
    });
};

Ddwtos.prototype.handleDragStart = function(e) {
    let drag = jQuery(e.target).closest('.draghome');
    let info = dragDrop.prepare(e);
    if (!info.start || drag.hasClass('beingdragged')) {
        return;
    }
    drag.addClass('beingdragged');
    let currentPlace = Ddwtos.prototype.getClassnameNumericSuffix(drag, 'inplace');
    if (currentPlace !== null) {
        Ddwtos.prototype.setInputValue(currentPlace, 0);
        drag.removeClass('inplace' + currentPlace);
        let hiddenDrop = Ddwtos.prototype.getDrop(drag, currentPlace);
        if (hiddenDrop.length) {
            hiddenDrop.addClass('active');
            drag.offset(hiddenDrop.offset());
        }
    } else {
        let hiddenDrag = Ddwtos.prototype.getDragClone(drag);
        if (hiddenDrag.length) {
            if (drag.hasClass('infinite')) {
                let noOfDrags = Ddwtos.prototype.noOfDropsInGroup(Ddwtos.prototype.getGroup(drag));
                let cloneDrags = Ddwtos.prototype.getInfiniteDragClones(drag, false);
                if (cloneDrags.length < noOfDrags) {
                    let cloneDrag = drag.clone();
                    cloneDrag.removeClass('beingdragged');
                    hiddenDrag.after(cloneDrag);
                    questionManager.addEventHandlersToDrag(cloneDrag);
                    drag.offset(cloneDrag.offset());
                } else {
                    hiddenDrag.addClass('active');
                    drag.offset(hiddenDrag.offset());
                }
            } else {
                hiddenDrag.addClass('active');
                drag.offset(hiddenDrag.offset());
            }
        }
    }
    dragDrop.start(e, drag, function(x, y, drag) {
        Ddwtos.prototype.dragMove(x, y, drag);
    }, function(x, y, drag) {
        Ddwtos.prototype.dragEnd(x, y, drag);
    });
};


/* EVENTS */
Ddwtos.prototype.answered = function(response, fromService = false) {
    questionEnd = true;
    if (response.hasfeedbacks) {
        jQuery(REGION.FEEDBACK).html(response.statment_feedback);
        jQuery(REGION.FEEDBACKANSWER).html(response.answer_feedback);
    }
    jQuery(ACTION.SEND_RESPONSE).addClass('d-none');
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(REGION.STATEMENTTEXT).css({'z-index': 3, 'padding': '15px'});
    jQuery(REGION.TIMER).css('z-index', 3);
    if (manualMode === false) {
        jQuery(REGION.NEXT).removeClass('d-none');
    }
    if (fromService === true) {
        jQuery(REGION.STATEMENTTEXT_CONTENT).html(decodeURIComponent(escape(atob(response.question_text_feedback))));
        Ddwtos.prototype.resizeAllDragsAndDrops();
        Ddwtos.prototype.cloneDrags();
        Ddwtos.prototype.positionDrags();
    }
    let dragItems = document.querySelectorAll(REGION.DRAGHOME);
    dragItems.forEach(function(dragElement) {
        dragElement.setAttribute('draggable', 'false');
        jQuery(dragElement).unbind('mousedown touchstart');
        jQuery(dragElement).off('mousedown touchstart');
        jQuery(dragElement).css({'pointer-events': 'none'});
    });
    mEvent.notifyFilterContentUpdated(document.querySelector(REGION.CONTENTFEEDBACKS));
};

Ddwtos.prototype.pauseQuestion = function() {
    dispatchEvent(new Event('pauseQuestion'));
    jQuery(REGION.TIMER).css('z-index', 3);
    jQuery(REGION.FEEDBACKBACGROUND).css('display', 'block');
    jQuery(ACTION.REPLY).css('pointer-events', 'none');
};

Ddwtos.prototype.playQuestion = function() {
    if (questionEnd !== true) {
        dispatchEvent(new Event('playQuestion'));
        jQuery(REGION.TIMER).css('z-index', 1);
        jQuery(REGION.FEEDBACKBACGROUND).css('display', 'none');
        jQuery(ACTION.REPLY).css('pointer-events', 'auto');
    }
};

Ddwtos.prototype.showFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'block', 'z-index': 3});
    }
};

Ddwtos.prototype.hideFeedback = function() {
    if (questionEnd === true) {
        jQuery(REGION.CONTENTFEEDBACKS).css({'display': 'none', 'z-index': 0});
    }
};

Ddwtos.prototype.showAnswers = function() {
    if (questionEnd === true) {
        jQuery(REGION.ICONSANSWERS).css({'display': 'inline-block'});
    }
};

Ddwtos.prototype.hideAnswers = function() {
    if (questionEnd === true) {
        jQuery(REGION.ICONSANSWERS).css({'display': 'none'});
    }
};

/* CORE */
Ddwtos.prototype.resizeAllDragsAndDrops = function() {
    let thisQ = this;
    jQuery(REGION.AREA).find('.answercontainer > div').each(function(i, node) {
        thisQ.resizeAllDragsAndDropsInGroup(
            thisQ.getClassnameNumericSuffix(jQuery(node), 'draggrouphomes'));
    });
};

Ddwtos.prototype.cloneDrags = function() {
    jQuery(REGION.AREA).find('span.draghome').each(function(index, draghome) {
        let drag = jQuery(draghome);
        let placeHolder = drag.clone();
        placeHolder.removeClass();
        placeHolder.addClass('draghome choice' +
            Ddwtos.prototype.getChoice(drag) + ' group' +
            Ddwtos.prototype.getGroup(drag) + ' dragplaceholder');
        drag.before(placeHolder);
    });
};

Ddwtos.prototype.positionDrags = function() {
    let root = jQuery(REGION.AREA);
    root.find('span.draghome').not('.dragplaceholder').each(function(i, dragNode) {
        let drag = jQuery(dragNode),
            currentPlace = Ddwtos.prototype.getClassnameNumericSuffix(drag, 'inplace');
        drag.addClass('unplaced')
            .removeClass('placed');
        drag.removeAttr('tabindex');
        if (currentPlace !== null) {
            drag.removeClass('inplace' + currentPlace);
        }
    });
    root.find('input.placeinput').each(function(i, inputNode) {
        let input = jQuery(inputNode),
            choice = input.val(),
            place = Ddwtos.prototype.getPlace(input);
        let drop = root.find('.drop.place' + place),
            dropPosition = drop.offset();
        drop.data('prev-top', dropPosition.top).data('prev-left', dropPosition.left);

        if (choice === '0') {
            return;
        }
        let unplacedDrag = Ddwtos.prototype.getUnplacedChoice(Ddwtos.prototype.getGroup(input), choice);
        let hiddenDrag = Ddwtos.prototype.getDragClone(unplacedDrag);
        if (hiddenDrag.length) {
            if (unplacedDrag.hasClass('infinite')) {
                let noOfDrags = Ddwtos.prototype.noOfDropsInGroup(Ddwtos.prototype.getGroup(unplacedDrag));
                let cloneDrags = Ddwtos.prototype.getInfiniteDragClones(unplacedDrag, false);
                if (cloneDrags.length < noOfDrags) {
                    let cloneDrag = unplacedDrag.clone();
                    hiddenDrag.after(cloneDrag);
                    questionManager.addEventHandlersToDrag(cloneDrag);
                } else {
                    hiddenDrag.addClass('active');
                }
            } else {
                hiddenDrag.addClass('active');
            }
        }
        // Send the drag to drop.
        Ddwtos.prototype.sendDragToDrop(Ddwtos.prototype.getUnplacedChoice(Ddwtos.prototype.getGroup(input), choice), drop);
    });

    // Save the question answer.
    Ddwtos.prototype.questionAnswer = Ddwtos.prototype.getQuestionAnsweredValues();
};

Ddwtos.prototype.handleDragMoved = function(e, drag, target) {
    drag.removeClass('beingdragged');
    drag.css('top', '').css('left', '');
    target.after(drag);
    target.removeClass('active');
    if (typeof drag.data('unplaced') !== 'undefined' && drag.data('unplaced') === true) {
        drag.removeClass('placed').addClass('unplaced');
        drag.removeAttr('tabindex');
        drag.removeData('unplaced');
        if (drag.hasClass('infinite') && Ddwtos.prototype.getInfiniteDragClones(drag, true).length > 1) {
            Ddwtos.prototype.getInfiniteDragClones(drag, true).first().remove();
        }
    }
    if (typeof drag.data('isfocus') !== 'undefined' && drag.data('isfocus') === true) {
        drag.focus();
        drag.removeData('isfocus');
    }
    if (typeof target.data('isfocus') !== 'undefined' && target.data('isfocus') === true) {
        target.removeData('isfocus');
    }
    if (Ddwtos.prototype.isQuestionInteracted()) {
        Ddwtos.prototype.questionAnswer = Ddwtos.prototype.getQuestionAnsweredValues();
    }
};

Ddwtos.prototype.isPointInDrop = function(pageX, pageY, drop) {
    let position = drop.offset();
    return pageX >= position.left && pageX < position.left + drop.width()
        && pageY >= position.top && pageY < position.top + drop.height();
};

Ddwtos.prototype.sendDragToDrop = function(drag, drop) {
    let oldDrag = Ddwtos.prototype.getCurrentDragInPlace(Ddwtos.prototype.getPlace(drop));
    if (oldDrag.length !== 0) {
        let currentPlace = Ddwtos.prototype.getClassnameNumericSuffix(oldDrag, 'inplace');
        let hiddenDrop = Ddwtos.prototype.getDrop(oldDrag, currentPlace);
        hiddenDrop.addClass('active');
        oldDrag.addClass('beingdragged');
        oldDrag.offset(hiddenDrop.offset());
        Ddwtos.prototype.sendDragHome(oldDrag);
    }

    if (drag.length === 0) {
        Ddwtos.prototype.setInputValue(Ddwtos.prototype.getPlace(drop), 0);
        if (drop.data('isfocus')) {
            drop.focus();
        }
    } else {
        // Prevent the drag item drop into two drop-zone.
        if (Ddwtos.prototype.getClassnameNumericSuffix(drag, 'inplace')) {
            return;
        }

        Ddwtos.prototype.setInputValue(Ddwtos.prototype.getPlace(drop), Ddwtos.prototype.getChoice(drag));
        drag.removeClass('unplaced')
            .addClass('placed inplace' + Ddwtos.prototype.getPlace(drop));
        drag.attr('tabindex', 0);
        Ddwtos.prototype.animateTo(drag, drop);
    }
};

Ddwtos.prototype.sendDragHome = function(drag) {
    let currentPlace = Ddwtos.prototype.getClassnameNumericSuffix(drag, 'inplace');
    if (currentPlace !== null) {
        drag.removeClass('inplace' + currentPlace);
    }
    drag.data('unplaced', true);

    Ddwtos.prototype.animateTo(
        drag,
        Ddwtos.prototype.getDragHome(Ddwtos.prototype.getGroup(drag),
            Ddwtos.prototype.getChoice(drag))
    );
};

Ddwtos.prototype.getPlace = function(node) {
    return Ddwtos.prototype.getClassnameNumericSuffix(node, 'place');
};

Ddwtos.prototype.getQuestionAnsweredValues = function() {
    let result = {};
    jQuery(REGION.AREA).find('input.placeinput').each((i, inputNode) => {
        result[inputNode.id] = inputNode.value;
    });

    return result;
};

Ddwtos.prototype.animateTo = function(drag, target) {
    if (target !== undefined) {
        let currentPos = drag.offset(),
            targetPos = target.offset(),
            thisQ = this;
        if (targetPos !== undefined) {
            drag.animate(
                {
                    left: parseInt(drag.css('left')) + targetPos.left - currentPos.left,
                    top: parseInt(drag.css('top')) + targetPos.top - currentPos.top
                },
                {
                    duration: 'fast',
                    done: function() {
                        jQuery('body').trigger('qtype_ddwtos-dragmoved', [drag, target, thisQ]);
                    }
                }
            );
        }
    }
};

Ddwtos.prototype.getDragHome = function(group, choice) {
    if (!jQuery(REGION.AREA).find('.draghome.dragplaceholder.group' + group + '.choice' + choice).is(':visible')) {
        return jQuery(REGION.AREA).find('.draggrouphomes' + group +
            ' span.draghome.infinite' +
            '.choice' + choice +
            '.group' + group);
    }
    return jQuery(REGION.AREA).find('.draghome.dragplaceholder.group' + group + '.choice' + choice);
};

Ddwtos.prototype.getCurrentDragInPlace = function(place) {
    return jQuery(REGION.AREA).find('span.draghome.inplace' + place);
};

Ddwtos.prototype.resizeAllDragsAndDropsInGroup = function(group) {
    let thisQ = this,
        dragHomes = jQuery(REGION.AREA).find('.draggrouphomes' + group + ' span.draghome'),
        maxWidth = 0,
        maxHeight = 0;
    dragHomes.each(function(i, drag) {
        maxWidth = Math.max(maxWidth, Math.ceil(drag.offsetWidth));
        maxHeight = Math.max(maxHeight, Math.ceil(0 + drag.offsetHeight));
    });
    maxWidth += 8;
    maxHeight += 2;
    dragHomes.each(function(i, drag) {
        thisQ.setElementSize(drag, maxWidth, maxHeight);
    });
    jQuery(REGION.AREA).find('span.drop.group' + group).each(function(i, drop) {
        thisQ.setElementSize(drop, maxWidth, maxHeight);
    });
};

Ddwtos.prototype.setElementSize = function(element, width, height) {
    jQuery(element).width(width).height(height).css('lineHeight', height + 'px');
};

Ddwtos.prototype.getClassnameNumericSuffix = function(node, prefix) {
    let classes = node.attr('class');
    if (classes !== '' && classes !== undefined) {
        let classesArr = classes.split(' ');
        for (let index = 0; index < classesArr.length; index++) {
            let patt1 = new RegExp('^' + prefix + '([0-9])+$');
            if (patt1.test(classesArr[index])) {
                let patt2 = new RegExp('([0-9])+$');
                let match = patt2.exec(classesArr[index]);
                return Number(match[0]);
            }
        }
    }
    return null;
};

Ddwtos.prototype.setInputValue = function(place, choice) {
    jQuery(REGION.AREA).find('input.placeinput.place' + place).val(choice);
};

Ddwtos.prototype.getDrop = function(drag, currentPlace) {
    return jQuery(REGION.AREA).find('.drop.group' + Ddwtos.prototype.getGroup(drag) + '.place' + currentPlace);
};

Ddwtos.prototype.getGroup = function(node) {
    return Ddwtos.prototype.getClassnameNumericSuffix(node, 'group');
};

Ddwtos.prototype.getDragClone = function(drag) {
    return jQuery(REGION.AREA).find('.draggrouphomes' +
        Ddwtos.prototype.getGroup(drag) +
        ' span.draghome' +
        '.choice' + Ddwtos.prototype.getChoice(drag) +
        '.group' + Ddwtos.prototype.getGroup(drag) +
        '.dragplaceholder');
};

Ddwtos.prototype.getChoice = function(drag) {
    return Ddwtos.prototype.getClassnameNumericSuffix(drag, 'choice');
};

Ddwtos.prototype.noOfDropsInGroup = function(group) {
    return jQuery(REGION.AREA).find('.drop.group' + group).length;
};

Ddwtos.prototype.getInfiniteDragClones = function(drag, inHome) {
    if (inHome) {
        return jQuery(REGION.AREA).find('.draggrouphomes' +
            Ddwtos.prototype.getGroup(drag) +
            ' span.draghome' +
            '.choice' + Ddwtos.prototype.getChoice(drag) +
            '.group' + Ddwtos.prototype.getGroup(drag) +
            '.infinite').not('.dragplaceholder');
    }
    return jQuery(REGION.AREA).find('span.draghome' +
        '.choice' + Ddwtos.prototype.getChoice(drag) +
        '.group' + Ddwtos.prototype.getGroup(drag) +
        '.infinite').not('.dragplaceholder');
};

Ddwtos.prototype.dragMove = function(pageX, pageY, drag) {
    jQuery(REGION.AREA).find('span.group' + Ddwtos.prototype.getGroup(drag)).not('.beingdragged').each(function(i, dropNode) {
        let drop = jQuery(dropNode);
        if (Ddwtos.prototype.isPointInDrop(pageX, pageY, drop)) {
            drop.addClass('valid-drag-over-drop');
        } else {
            drop.removeClass('valid-drag-over-drop');
        }
    });
};

Ddwtos.prototype.dragEnd = function(pageX, pageY, drag) {
    let root = jQuery(REGION.AREA);
    let placed = false;
    root.find('span.group' + Ddwtos.prototype.getGroup(drag)).not('.beingdragged').each(function(i, dropNode) {
        if (placed) {
            return false;
        }
        const dropZone = jQuery(dropNode);
        if (!Ddwtos.prototype.isPointInDrop(pageX, pageY, dropZone)) {
            // Not this drop zone.
            return true;
        }
        let drop = null;
        if (dropZone.hasClass('placed')) {
            // This is an placed drag item in a drop.
            dropZone.removeClass('valid-drag-over-drop');
            // Get the correct drop.
            drop = Ddwtos.prototype.getDrop(drag, Ddwtos.prototype.getClassnameNumericSuffix(dropZone, 'inplace'));
        } else {
            // Empty drop.
            drop = dropZone;
        }
        if (drop.hasClass('draghome')) {
            // Not this drop zone.
            return true;
        }
        // Now put this drag into the drop.
        drop.removeClass('valid-drag-over-drop');
        Ddwtos.prototype.sendDragToDrop(drag, drop);
        placed = true;
        return false; // Stop the each() here.
    });
    if (!placed) {
        Ddwtos.prototype.sendDragHome(drag);
    }
};

Ddwtos.prototype.isQuestionInteracted = function() {
    const oldAnswer = Ddwtos.prototype.questionAnswer;
    const newAnswer = Ddwtos.prototype.getQuestionAnsweredValues();
    let isInteracted = false;

    // First, check both answers have the same structure or not.
    if (JSON.stringify(newAnswer) !== JSON.stringify(oldAnswer)) {
        isInteracted = true;
        return isInteracted;
    }
    // Check the values.
    Object.keys(newAnswer).forEach(key => {
        if (newAnswer[key] !== oldAnswer[key]) {
            isInteracted = true;
        }
    });

    return isInteracted;
};

Ddwtos.prototype.getUnplacedChoice = function(group, choice) {
    return jQuery(REGION.AREA).find('.draghome.group' + group + '.choice' + choice + '.unplaced').slice(0, 1);
};

let questionManager = {
    eventHandlersInitialised: false,
    dragEventHandlersInitialised: {},
    isKeyboardNavigation: false,
    questions: {},

    /**
     * Initialise questions.
     *
     * @param {String} containerId id of the outer div for this question.
     */
    init: function(containerId) {
        questionManager.questions[containerId] = Ddwtos.prototype;
        if (!questionManager.eventHandlersInitialised) {
            questionManager.setupEventHandlers();
            questionManager.eventHandlersInitialised = true;
        }
        if (!questionManager.dragEventHandlersInitialised.hasOwnProperty(containerId)) {
            questionManager.dragEventHandlersInitialised[containerId] = true;
            // We do not use the body event here to prevent the other event on Mobile device, such as scroll event.
            let questionContainer = document.getElementById(containerId);
            if (questionContainer.classList.contains('ddwtos') &&
                !questionContainer.classList.contains('qtype_ddwtos-readonly')) {
            }
        }
    },

    setupEventHandlers: function() {
        jQuery('body').on('qtype_ddwtos-dragmoved', Ddwtos.prototype.handleDragMoved.bind(this));
    },

    /**
     * Binding the drag/touch event again for newly created element.
     *
     * @param {jQuery} element Element to bind the event
     */
    addEventHandlersToDrag: function(element) {
        // Unbind all the mousedown and touchstart events to prevent double binding.
        element.unbind('mousedown touchstart');
        element.on('mousedown touchstart', Ddwtos.prototype.handleDragStart);
    },

    /**
     * Given an event, work out which question it affects.
     *
     * @param {Event} e the event.
     * @returns {DragDropToTextQuestion|undefined} The question, or undefined.
     */
    getQuestionForEvent: function(e) {
        let containerId = jQuery(e.currentTarget).closest('.que.ddwtos').attr('id');
        return questionManager.questions[containerId];
    }
};

export const initDdwtos = (selector, showquestionfeedback, manualmode, jsonresponse) => {
    return new Ddwtos(selector, showquestionfeedback, manualmode, jsonresponse);
};

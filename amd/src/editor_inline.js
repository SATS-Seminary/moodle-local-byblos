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

/**
 * Sub-module for inline contenteditable editing of text/heading sections.
 *
 * Makes text and custom section bodies editable on click. Shows a floating
 * toolbar with bold/italic/underline/link. Auto-saves via debounced AJAX
 * on input and blur events.
 *
 * @module     local_byblos/editor_inline
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    'use strict';

    /** @type {jQuery|null} Floating toolbar element. */
    var $toolbar = null;
    /** @type {number} Debounce timer for auto-save. */
    var saveTimer = 0;
    /** @type {number} Debounce delay in milliseconds. */
    var SAVE_DELAY = 1500;

    /**
     * Call a Moodle external function via core/ajax.
     * @param {string} methodname
     * @param {Object} args
     * @returns {Promise}
     */
    function callExternal(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0];
    }

    /**
     * Create the floating formatting toolbar (once per page).
     * @returns {jQuery}
     */
    function createToolbar() {
        if ($toolbar) {
            return $toolbar;
        }

        $toolbar = $(
            '<div class="byblos-inline-toolbar" style="' +
            'display:none !important; position:absolute !important; z-index:1050 !important;' +
            'background:#333 !important; color:#fff !important; border-radius:4px !important;' +
            'padding:2px 6px !important; font-size:0.8rem !important; box-shadow:0 2px 8px rgba(0,0,0,0.2) !important;' +
            '">' +
            '<button type="button" class="byblos-itb-btn" data-cmd="bold" title="Bold" style="' +
            'background:none !important; border:none !important; color:#fff !important;' +
            'cursor:pointer !important; padding:4px 8px !important; font-weight:700 !important;">' +
            '<i class="fa fa-bold"></i></button>' +
            '<button type="button" class="byblos-itb-btn" data-cmd="italic" title="Italic" style="' +
            'background:none !important; border:none !important; color:#fff !important;' +
            'cursor:pointer !important; padding:4px 8px !important;">' +
            '<i class="fa fa-italic"></i></button>' +
            '<button type="button" class="byblos-itb-btn" data-cmd="underline" title="Underline" style="' +
            'background:none !important; border:none !important; color:#fff !important;' +
            'cursor:pointer !important; padding:4px 8px !important;">' +
            '<i class="fa fa-underline"></i></button>' +
            '<button type="button" class="byblos-itb-btn" data-cmd="createLink" title="Link" style="' +
            'background:none !important; border:none !important; color:#fff !important;' +
            'cursor:pointer !important; padding:4px 8px !important;">' +
            '<i class="fa fa-link"></i></button>' +
            '</div>'
        );

        $('body').append($toolbar);

        // Handle toolbar button clicks.
        $toolbar.on('mousedown', '.byblos-itb-btn', function(e) {
            e.preventDefault(); // Prevent stealing focus from contenteditable.
            var cmd = $(this).data('cmd');
            if (cmd === 'createLink') {
                var url = window.prompt('Enter URL:');
                if (url) {
                    document.execCommand('createLink', false, url);
                }
            } else {
                document.execCommand(cmd, false, null);
            }
        });

        return $toolbar;
    }

    /**
     * Position the toolbar above the current text selection.
     */
    function positionToolbar() {
        var sel = window.getSelection();
        if (!sel || sel.isCollapsed || sel.rangeCount === 0) {
            $toolbar.hide();
            return;
        }

        var range = sel.getRangeAt(0);
        var rect = range.getBoundingClientRect();
        if (rect.width === 0) {
            $toolbar.hide();
            return;
        }

        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

        $toolbar.css({
            display: 'block',
            top: (rect.top + scrollTop - $toolbar.outerHeight() - 6) + 'px',
            left: (rect.left + scrollLeft + (rect.width / 2) - ($toolbar.outerWidth() / 2)) + 'px'
        });
    }

    /**
     * Auto-save the content of a contenteditable section body.
     * @param {jQuery} $card The section card.
     * @param {HTMLElement} el The contenteditable element.
     */
    function debouncedSave($card, el) {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function() {
            doSave($card, el);
        }, SAVE_DELAY);
    }

    /**
     * Immediately save the contenteditable content.
     * @param {jQuery} $card The section card.
     * @param {HTMLElement} el The contenteditable element.
     */
    function doSave($card, el) {
        clearTimeout(saveTimer);

        var sectionId = parseInt($card.data('section-id'), 10);
        var stype = $card.data('sectiontype');
        var raw = $card.attr('data-configdata') || '{}';
        var cfg;
        try {
            cfg = JSON.parse(raw);
        } catch (ex) {
            cfg = {};
        }

        var newContent = el.innerHTML;

        // Update the appropriate field based on section type.
        var configdata;
        var content = '';
        if (stype === 'text') {
            cfg.body = newContent;
            configdata = JSON.stringify(cfg);
        } else if (stype === 'custom') {
            configdata = $card.attr('data-configdata') || '{}';
            content = newContent;
        } else {
            // For heading sections, update the heading field.
            cfg.heading = el.textContent;
            configdata = JSON.stringify(cfg);
        }

        // Update the data attributes.
        $card.attr('data-configdata', configdata);
        if (content) {
            $card.attr('data-content', content);
        }

        callExternal('local_byblos_update_section', {
            sectionid: sectionId,
            configdata: configdata,
            content: content
        }).catch(Notification.exception);
    }

    /**
     * Make a section card's body contenteditable.
     * @param {jQuery} $card
     */
    function makeEditable($card) {
        var stype = $card.data('sectiontype');

        // Only text and custom sections support inline editing.
        if (stype !== 'text' && stype !== 'custom') {
            return;
        }

        var selector;
        if (stype === 'text') {
            selector = '.section-body';
        } else if (stype === 'custom') {
            selector = '.eportfolio-section-custom';
        }

        if (!selector) {
            return;
        }

        var $body = $card.find('.byblos-section-preview ' + selector);
        if (!$body.length) {
            return;
        }

        var el = $body[0];
        if (el.getAttribute('contenteditable') === 'true') {
            return; // Already initialised.
        }

        el.setAttribute('contenteditable', 'true');
        el.style.outline = 'none';
        el.style.minHeight = '2em';
        el.style.cursor = 'text';

        var tb = createToolbar();

        // Show toolbar on selection change.
        $(el).on('mouseup keyup', function() {
            positionToolbar();
        });

        // Auto-save on input.
        $(el).on('input', function() {
            debouncedSave($card, el);
        });

        // Save on blur and hide toolbar.
        $(el).on('blur', function() {
            doSave($card, el);
            setTimeout(function() {
                // Only hide if focus did not move to the toolbar.
                if (!$toolbar.is(':hover') && !$.contains(tb[0], document.activeElement)) {
                    tb.hide();
                }
            }, 200);
        });

        // Focus styling.
        $(el).on('focus', function() {
            $card.css('border-color', '#0d6efd');
        });
        $(el).on('blur', function() {
            $card.css('border-color', '');
        });
    }

    return {
        /**
         * Initialise inline editing on all eligible sections.
         * @param {jQuery} $root The editor root element.
         */
        initAll: function($root) {
            $root.find('.byblos-section-card').each(function() {
                makeEditable($(this));
            });
        },

        /**
         * Initialise inline editing on a single section card.
         * @param {jQuery} $card
         */
        initSection: function($card) {
            makeEditable($card);
        }
    };
});

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
 * "Get announcement link" picker for the page view.
 *
 * Lazily loads the list of courses the teacher can post announcements in,
 * builds the turnstile URL on each course selection, and exposes a
 * copy-to-clipboard action.
 *
 * @module     local_byblos/announce_link
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {
    'use strict';

    /** @type {number} Byblos page id pulled from the trigger button. */
    var pageId = 0;
    /** @type {string} Moodle wwwroot prefix. */
    var wwwroot = '';
    /** @type {boolean} Whether the picker body has been populated this session. */
    var loaded = false;

    /**
     * Wrap Moodle Ajax.call() into a single-call promise so the chain reads
     * like a normal async function.
     *
     * @param {string} methodname WS function name.
     * @param {Object} args WS arguments.
     * @returns {Promise}
     */
    function callWs(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0];
    }

    /**
     * Build the turnstile URL for the current page + selected course.
     *
     * @param {number} courseId Selected course id.
     * @returns {string}
     */
    function buildUrl(courseId) {
        return wwwroot + '/local/byblos/go.php?course=' + courseId + '&page=' + pageId;
    }

    /**
     * Render the picker body once the course list has been fetched.
     *
     * @param {Array} courses [{id, fullname, shortname}, ...]
     */
    function renderBody(courses) {
        var $body = $('#byblos-announce-body');
        $body.empty();

        if (!courses || courses.length === 0) {
            $body.append(
                '<p class="text-muted mb-0" id="byblos-announce-empty"></p>'
            );
            Str.get_string('announcelink_no_courses', 'local_byblos').then(function(s) {
                $body.find('#byblos-announce-empty').text(s);
                return s;
            }).catch(Notification.exception);
            return;
        }

        var $picker = $('<div class="mb-3"></div>');
        Str.get_string('announcelink_pick_course', 'local_byblos').then(function(label) {
            $picker.prepend('<label class="form-label small d-block" for="byblos-announce-course">'
                + escHtml(label) + '</label>');
            return label;
        }).catch(Notification.exception);

        var optionsHtml = '';
        courses.forEach(function(c) {
            optionsHtml += '<option value="' + parseInt(c.id, 10) + '">'
                + escHtml(c.fullname)
                + (c.shortname ? ' (' + escHtml(c.shortname) + ')' : '')
                + '</option>';
        });
        $picker.append(
            '<select id="byblos-announce-course" class="form-control">' + optionsHtml + '</select>'
        );
        $body.append($picker);

        $body.append(
            '<label class="form-label small d-block" for="byblos-announce-url">URL</label>' +
            '<div class="input-group input-group-sm mb-2">' +
            '<input type="text" readonly id="byblos-announce-url" class="form-control">' +
            '<div class="input-group-append">' +
            '<button type="button" class="btn btn-primary" id="byblos-announce-copy">' +
            '<i class="fa fa-clipboard"></i> <span id="byblos-announce-copy-label"></span>' +
            '</button>' +
            '</div></div>' +
            '<p class="small text-muted mb-0" id="byblos-announce-hint"></p>'
        );

        Str.get_strings([
            {key: 'announcelink_copy', component: 'local_byblos'},
            {key: 'announcelink_hint', component: 'local_byblos'},
        ]).then(function(strs) {
            $('#byblos-announce-copy-label').text(strs[0]);
            $('#byblos-announce-hint').text(strs[1]);
            return strs;
        }).catch(Notification.exception);

        var firstId = parseInt(courses[0].id, 10);
        $('#byblos-announce-url').val(buildUrl(firstId));

        $('#byblos-announce-course').on('change', function() {
            var cid = parseInt($(this).val(), 10);
            $('#byblos-announce-url').val(buildUrl(cid));
        });
        $('#byblos-announce-copy').on('click', function() {
            var $input = $('#byblos-announce-url');
            $input[0].select();
            $input[0].setSelectionRange(0, 99999);
            navigator.clipboard.writeText($input.val()).then(function() {
                Str.get_string('announcelink_copied', 'local_byblos').then(function(s) {
                    $('#byblos-announce-copy-label').text(s);
                    return s;
                }).catch(Notification.exception);
                return null;
            }).catch(Notification.exception);
        });
    }

    /**
     * Minimal HTML escape for safely interpolating values into our markup.
     *
     * @param {string} s Raw input.
     * @returns {string}
     */
    function escHtml(s) {
        return (s || '').toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Open the picker popover and trigger the WS call on first open.
     */
    function openPicker() {
        var $pop = $('#byblos-announce-popover');
        $pop.toggleClass('d-none');
        if (loaded || $pop.hasClass('d-none')) {
            return;
        }
        loaded = true;
        $('#byblos-announce-body').html('<p class="text-muted small mb-0">Loading…</p>');
        callWs('local_byblos_list_postable_courses', {}).then(function(courses) {
            renderBody(courses);
            return courses;
        }).catch(function(err) {
            loaded = false;
            $('#byblos-announce-body').html(
                '<p class="text-danger small mb-0">Failed to load courses.</p>'
            );
            Notification.exception(err);
        });
    }

    return {
        /**
         * Boot the picker.
         *
         * @param {Object} args
         * @param {number} args.pageid Byblos page id.
         * @param {string} args.wwwroot Moodle wwwroot URL (no trailing slash).
         */
        init: function(args) {
            pageId = parseInt(args.pageid, 10) || 0;
            wwwroot = (args.wwwroot || '').replace(/\/+$/, '');

            var $btn = $('#byblos-announce-toggle');
            if (!$btn.length) {
                return;
            }
            $btn.on('click', function(ev) {
                ev.preventDefault();
                openPicker();
            });

            // Close the popover when the user clicks outside it or on the button again.
            $(document).on('click', function(ev) {
                var $pop = $('#byblos-announce-popover');
                if ($pop.hasClass('d-none')) {
                    return;
                }
                if ($(ev.target).closest('#byblos-announce-popover, #byblos-announce-toggle').length) {
                    return;
                }
                $pop.addClass('d-none');
            });
        }
    };
});

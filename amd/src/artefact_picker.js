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
 * Artefact picker modal for the Byblos portfolio editor.
 *
 * Presents a Bootstrap modal with a grid of the user's library artefacts
 * (images, files, text snippets, badges, completions, blog entries). The
 * caller passes an onPick callback that receives the selected artefact.
 *
 * Consumers should lazy-load this module inside a click handler:
 *   require(['local_byblos/artefact_picker'], function(Picker) {
 *       Picker.open({typefilter: 'image', onPick: function(a) { ... }});
 *   });
 *
 * @module     local_byblos/artefact_picker
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    'use strict';

    /** @type {Object|null} Cached modal DOM references — populated on first open(). */
    var dom = null;

    /** @type {Object} Current invocation options (onPick, hardTypefilter, etc.). */
    var current = {
        onPick: null,
        hardTypefilter: null,
        activeTypefilter: ''
    };

    /** @type {Array} Latest artefact result set from the server. */
    var lastResults = [];

    /** @type {string} Current client-side search query (lowercase). */
    var searchQuery = '';

    /** @type {Array<{value: string, label: string}>} Pill definitions. */
    var PILLS = [
        {value: '', label: 'All'},
        {value: 'image', label: 'Images'},
        {value: 'file', label: 'Files'},
        {value: 'text', label: 'Text'},
        {value: 'badge', label: 'Badges'},
        {value: 'course_completion', label: 'Completions'},
        {value: 'blog_entry', label: 'Blog'}
    ];

    /** @type {Object<string, string>} FontAwesome icons keyed by artefacttype. */
    var TYPE_ICONS = {
        file: 'fa-file-o',
        text: 'fa-file-text-o',
        badge: 'fa-certificate',
        course_completion: 'fa-graduation-cap',
        blog_entry: 'fa-rss',
        image: 'fa-image'
    };

    /**
     * Escape HTML entities in a string.
     * @param {string} str
     * @returns {string}
     */
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode((str === null || str === undefined) ? '' : String(str)));
        return div.innerHTML;
    }

    /**
     * Build the modal DOM once and cache it. Subsequent calls return the
     * same structure so state-free elements (pills wrapper, grid, etc.)
     * can be reused between invocations.
     * @returns {Object} Cached DOM references.
     */
    function buildDom() {
        if (dom) {
            return dom;
        }

        var $modal = $(
            '<div class="modal fade byblos-artefact-picker-modal" tabindex="-1" role="dialog" ' +
            'aria-hidden="true">' +
                '<div class="modal-dialog modal-lg" role="document">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<h5 class="modal-title byblos-artefact-picker-title">' +
                                'Insert from library</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" ' +
                                'aria-label="Close"></button>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<div class="byblos-artefact-picker-pills mb-2"></div>' +
                            '<div class="byblos-artefact-picker-search input-group input-group-sm mb-2">' +
                                '<input type="search" class="form-control byblos-artefact-picker-search-input" ' +
                                    'placeholder="Search by title...">' +
                            '</div>' +
                            '<div class="byblos-artefact-picker-status text-muted small mb-2 d-none"></div>' +
                            '<div class="byblos-artefact-picker-grid"></div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );

        $('body').append($modal);

        dom = {
            $modal: $modal,
            $title: $modal.find('.byblos-artefact-picker-title'),
            $pills: $modal.find('.byblos-artefact-picker-pills'),
            $searchRow: $modal.find('.byblos-artefact-picker-search'),
            $searchInput: $modal.find('.byblos-artefact-picker-search-input'),
            $status: $modal.find('.byblos-artefact-picker-status'),
            $grid: $modal.find('.byblos-artefact-picker-grid')
        };

        // Wire static handlers once.
        dom.$pills.on('click', '.byblos-artefact-picker-pill', function() {
            var val = $(this).data('value') || '';
            if (val === current.activeTypefilter) {
                return;
            }
            current.activeTypefilter = val;
            renderPills();
            fetchArtefacts();
        });

        dom.$searchInput.on('input', function() {
            searchQuery = ($(this).val() || '').toLowerCase().trim();
            renderGrid();
        });

        dom.$grid.on('click', '.byblos-artefact-picker-card', function() {
            var idx = parseInt($(this).data('idx'), 10);
            var artefact = lastResults[idx];
            if (!artefact) {
                return;
            }
            if (typeof current.onPick === 'function') {
                try {
                    current.onPick(artefact);
                } catch (ex) {
                    Notification.exception(ex);
                }
            }
            dom.$modal.modal('hide');
        });

        return dom;
    }

    /**
     * Render the type-filter pill row based on the active filter. When a
     * hard typefilter is set (caller-locked) the pill row is hidden.
     */
    function renderPills() {
        if (!dom) {
            return;
        }
        if (current.hardTypefilter) {
            dom.$pills.addClass('d-none').empty();
            return;
        }
        dom.$pills.removeClass('d-none');
        var html = '';
        PILLS.forEach(function(pill) {
            var active = pill.value === current.activeTypefilter;
            html += '<button type="button" ' +
                'class="byblos-artefact-picker-pill btn btn-sm ' +
                (active ? 'btn-primary' : 'btn-outline-secondary') + ' mr-1 mb-1" ' +
                'data-value="' + escHtml(pill.value) + '">' +
                escHtml(pill.label) +
                '</button>';
        });
        dom.$pills.html(html);
    }

    /**
     * Show a transient status line (loading / error) in the modal body.
     * Pass an empty string to clear.
     * @param {string} text
     * @param {boolean} [isError]
     */
    function setStatus(text, isError) {
        if (!dom) {
            return;
        }
        if (!text) {
            dom.$status.addClass('d-none').text('').removeClass('text-danger');
            return;
        }
        dom.$status.removeClass('d-none').text(text);
        dom.$status.toggleClass('text-danger', !!isError);
    }

    /**
     * Render the artefact grid from lastResults, applying the client-side
     * search filter. Shows an empty state when nothing matches.
     */
    function renderGrid() {
        if (!dom) {
            return;
        }

        var filtered = lastResults.filter(function(a) {
            if (!searchQuery) {
                return true;
            }
            return (a.title || '').toLowerCase().indexOf(searchQuery) !== -1;
        });

        if (!filtered.length) {
            var msg = lastResults.length
                ? 'No artefacts match your search.'
                : 'No artefacts yet. Upload files via the plugin or use ' +
                  '<em>Export to portfolio</em> from anywhere in Moodle to build your library.';
            dom.$grid.html(
                '<div class="byblos-artefact-picker-empty text-center text-muted p-4">' +
                    '<i class="fa fa-folder-open-o fa-3x mb-2"></i><br>' +
                    msg +
                '</div>'
            );
            return;
        }

        var html = '';
        filtered.forEach(function(a) {
            // Preserve original lastResults index for click handler.
            var origIdx = lastResults.indexOf(a);
            html += renderCardHtml(a, origIdx);
        });
        dom.$grid.html(html);
    }

    /**
     * Render a single artefact card as HTML.
     * @param {Object} a Artefact record.
     * @param {number} idx Index into lastResults.
     * @returns {string}
     */
    function renderCardHtml(a, idx) {
        var thumbBlock;
        if (a.thumburl) {
            thumbBlock = '<div class="byblos-artefact-picker-thumb">' +
                '<img src="' + escHtml(a.thumburl) + '" alt="' + escHtml(a.title || '') + '">' +
                '</div>';
        } else {
            var icon = TYPE_ICONS[a.artefacttype] || 'fa-file-o';
            thumbBlock = '<div class="byblos-artefact-picker-thumb byblos-artefact-picker-thumb-icon">' +
                '<i class="fa ' + icon + ' fa-3x"></i>' +
                '</div>';
        }

        var desc = a.description
            ? '<div class="byblos-artefact-picker-card-desc text-muted small">' +
                escHtml(a.description) + '</div>'
            : '';

        return '<div class="byblos-artefact-picker-card" data-idx="' + idx + '" ' +
                'role="button" tabindex="0" title="' + escHtml(a.title || '') + '">' +
                thumbBlock +
                '<div class="byblos-artefact-picker-card-title">' + escHtml(a.title || '') + '</div>' +
                desc +
            '</div>';
    }

    /**
     * Call the local_byblos_list_artefacts external function with the
     * effective typefilter, then render the grid.
     * @returns {Promise}
     */
    function fetchArtefacts() {
        if (!dom) {
            return Promise.resolve();
        }

        // Effective filter: hard-filter overrides pill selection.
        var effective = current.hardTypefilter || current.activeTypefilter || '';
        if (effective === 'any') {
            effective = '';
        }

        lastResults = [];
        dom.$grid.empty();
        setStatus('Loading...');

        return Ajax.call([{
            methodname: 'local_byblos_list_artefacts',
            args: {
                typefilter: effective,
                search: ''
            }
        }])[0].then(function(data) {
            lastResults = Array.isArray(data) ? data : [];
            setStatus('');
            renderGrid();
            return data;
        }).catch(function(err) {
            setStatus('Failed to load artefacts.', true);
            Notification.exception(err);
        });
    }

    /**
     * Open the artefact picker modal.
     *
     * @param {Object} options
     * @param {string} [options.typefilter='any'] Hard filter: 'image', 'file', or 'any'.
     *     When set to something other than 'any' the pill row is hidden.
     * @param {Function} options.onPick Callback invoked with the chosen artefact.
     * @param {string} [options.title] Optional modal title override.
     */
    function open(options) {
        options = options || {};
        buildDom();

        var typefilter = options.typefilter || 'any';
        current.onPick = typeof options.onPick === 'function' ? options.onPick : null;
        current.hardTypefilter = (typefilter && typefilter !== 'any') ? typefilter : null;
        current.activeTypefilter = current.hardTypefilter || '';

        // Reset search state each open.
        searchQuery = '';
        dom.$searchInput.val('');

        dom.$title.text(options.title || 'Insert from library');

        renderPills();
        dom.$modal.modal('show');
        fetchArtefacts();
    }

    return {
        open: open
    };
});

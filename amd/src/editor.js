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
 * Main AMD module for the Byblos section-based page editor.
 *
 * Bootstraps AJAX-driven section CRUD: add, update, delete, reorder.
 * Provides inline editing panels, preview toggle, and theme switching.
 *
 * @module     local_byblos/editor
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str',
        'local_byblos/editor_inline', 'local_byblos/upload', 'editor_tiny/loader'],
function($, Ajax, Notification, Str, InlineEditor, Upload, TinyLoader) {
    'use strict';

    /** @type {number} Page ID from PHP. */
    var pageId;
    /** @type {jQuery} Root editor element. */
    var $root;
    /** @type {number} Position for the next section insert. */
    var insertPosition = 0;

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * Escape HTML entities.
     * @param {string} str
     * @returns {string}
     */
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

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
     * Smoothly scroll a jQuery element into the viewport. Uses the native
     * scrollIntoView where supported; falls back to no-op otherwise.
     * @param {jQuery} $el
     */
    function scrollIntoViewSafely($el) {
        if (!$el || !$el.length || !$el[0].scrollIntoView) {
            return;
        }
        try {
            $el[0].scrollIntoView({behavior: 'smooth', block: 'start'});
        } catch (e) {
            $el[0].scrollIntoView();
        }
    }

    /**
     * Give focus to the first meaningful input/select/textarea inside a panel.
     * Skips hidden inputs and elements marked readonly/disabled.
     * @param {jQuery} $panel
     */
    function focusFirstField($panel) {
        if (!$panel || !$panel.length) {
            return;
        }
        var $field = $panel.find(
            'input:not([type=hidden]):not([readonly]):not([disabled]),' +
            ' select:not([disabled]),' +
            ' textarea:not([readonly]):not([disabled])'
        ).first();
        if ($field.length) {
            // Defer slightly so TinyMCE / collapse animations don't steal focus back.
            setTimeout(function() {
                $field.trigger('focus');
            }, 50);
        }
    }

    // ================================================================
    // Section Type Modal
    // ================================================================

    /**
     * Open the section type picker at a given insert position.
     * @param {number} position
     */
    function openTypePicker(position) {
        insertPosition = position + 1; // Insert AFTER the clicked position.
        $('#byblos-section-type-modal').modal('show');
    }

    /**
     * Handle section type selection from the picker modal.
     */
    function initTypePicker() {
        $(document).on('click', '.byblos-type-pick-card', function() {
            var stype = $(this).data('type');
            $('#byblos-section-type-modal').modal('hide');

            callExternal('local_byblos_add_section', {
                pageid: pageId,
                sectiontype: stype,
                sortorder: insertPosition
            }).then(function(result) {
                if (result && result.id) {
                    // Reload to get the fresh section in the stack.
                    window.location.reload();
                }
                return;
            }).catch(Notification.exception);
        });
    }

    // ================================================================
    // Section Actions (Edit, Delete, Move)
    // ================================================================

    /**
     * Bind action handlers for a section card.
     * @param {jQuery} $card
     */
    function bindSectionActions($card) {
        var sectionId = parseInt($card.data('section-id'), 10);

        // Edit button.
        $card.find('.byblos-tb-edit').on('click', function(e) {
            e.stopPropagation();
            toggleEditPanel($card);
        });

        // Delete button.
        $card.find('.byblos-tb-delete').on('click', function(e) {
            e.stopPropagation();
            Str.get_string('deletesectionconfirm', 'local_byblos').then(function(confirmstr) {
                if (!window.confirm(confirmstr)) {
                    return;
                }
                callExternal('local_byblos_delete_section', {
                    sectionid: sectionId
                }).then(function() {
                    // Remove the card and its preceding add-row.
                    var $prev = $card.prev('.byblos-add-row');
                    $card.fadeOut(200, function() {
                        $card.remove();
                        if ($prev.length) {
                            $prev.remove();
                        }
                        // If no sections left, show empty state.
                        if ($root.find('.byblos-section-card').length === 0) {
                            $('#byblos-section-stack').append(
                                '<div class="text-center text-muted py-5" id="byblos-empty-state">' +
                                '<i class="fa fa-puzzle-piece fa-3x mb-3" style="display:block !important;"></i>' +
                                '<p>No sections yet.</p></div>'
                            );
                        }
                    });
                    return;
                }).catch(Notification.exception);
                return;
            }).catch(Notification.exception);
        });

        // Move up.
        $card.find('.byblos-tb-up').on('click', function(e) {
            e.stopPropagation();
            var $prevCard = $card.prevAll('.byblos-section-card').first();
            if ($prevCard.length) {
                var $myAddRow = $card.prev('.byblos-add-row');
                var $prevAddRow = $prevCard.prev('.byblos-add-row');
                if ($prevAddRow.length) {
                    $myAddRow.insertBefore($prevAddRow);
                    $card.insertAfter($myAddRow);
                } else {
                    $card.insertBefore($prevCard);
                }
                saveOrder();
            }
        });

        // Move down.
        $card.find('.byblos-tb-down').on('click', function(e) {
            e.stopPropagation();
            var $nextCard = $card.nextAll('.byblos-section-card').first();
            if ($nextCard.length) {
                var $nextAddRow = $nextCard.next('.byblos-add-row');
                if ($nextAddRow.length) {
                    $card.insertAfter($nextAddRow);
                    var $myAddRow = $card.prev('.byblos-add-row');
                    if (!$myAddRow.length || !$myAddRow.hasClass('byblos-add-row')) {
                        // Ensure an add-row exists before us.
                    }
                } else {
                    $card.insertAfter($nextCard);
                }
                saveOrder();
            }
        });
    }

    // ================================================================
    // Save Ordering
    // ================================================================

    /**
     * Persist the current visual order of section cards to the server.
     */
    function saveOrder() {
        var items = [];
        $root.find('.byblos-section-card').each(function(idx) {
            items.push({
                sectionid: parseInt($(this).data('section-id'), 10),
                sortorder: idx
            });
        });

        callExternal('local_byblos_reorder_sections', {
            pageid: pageId,
            ordering: JSON.stringify(items)
        }).catch(Notification.exception);
    }

    // ================================================================
    // Inline Edit Panel
    // ================================================================

    /**
     * Build a single form field HTML.
     * @param {string} label
     * @param {string} id
     * @param {*} val
     * @param {string} type - text|textarea|color|checkbox
     * @returns {string}
     */
    function buildField(label, id, val, type) {
        type = type || 'text';
        if (type === 'textarea') {
            return '<div class="form-group"><label for="' + id + '">' + label + '</label>' +
                '<textarea id="' + id + '" class="form-control form-control-sm" rows="4">' +
                escHtml(val || '') + '</textarea></div>';
        }
        if (type === 'rich') {
            return '<div class="form-group"><label for="' + id + '">' + label + '</label>' +
                '<textarea id="' + id + '" class="form-control byblos-rich" rows="6">' +
                escHtml(val || '') + '</textarea></div>';
        }
        if (type === 'color') {
            return '<div class="form-group"><label for="' + id + '">' + label + '</label>' +
                '<input type="color" id="' + id + '" class="form-control form-control-sm" value="' +
                escHtml(val || '#000000') + '" style="height:34px !important; padding:2px !important;"></div>';
        }
        if (type === 'checkbox') {
            return '<div class="form-check mb-2"><input type="checkbox" class="form-check-input" id="' +
                id + '"' + (val ? ' checked' : '') + '><label class="form-check-label small" for="' +
                id + '">' + label + '</label></div>';
        }
        return '<div class="form-group"><label for="' + id + '">' + label + '</label>' +
            '<input type="' + type + '" id="' + id + '" class="form-control form-control-sm" value="' +
            escHtml(val || '') + '"></div>';
    }

    /**
     * Build markup for an image field backed by the upload widget.
     * Renders a placeholder div (mounted later) plus a hidden input that
     * collectEditForm reads by id — keeping the collection logic unchanged.
     *
     * @param {string} label
     * @param {string} id  Hidden input id (e.g. 'bse_image_url').
     * @param {string} val Current URL.
     * @returns {string}
     */
    function buildImageField(label, id, val) {
        return '<div class="form-group"><label>' + label + '</label>' +
            '<div class="byblos-image-field" data-field-id="' + id + '"></div>' +
            '<input type="hidden" id="' + id + '" value="' + escHtml(val || '') + '">' +
            '</div>';
    }

    /**
     * Attach TinyMCE to every textarea.byblos-rich inside $panel.
     * Uses the Moodle Tiny loader with a lightweight toolbar — enough for body copy.
     *
     * @param {jQuery} $panel
     */
    function initRichFields($panel) {
        var $targets = $panel.find('textarea.byblos-rich');
        if (!$targets.length) {
            return;
        }
        TinyLoader.getTinyMCE().then(function(tinyMCE) {
            $targets.each(function() {
                tinyMCE.init({
                    target: this,
                    menubar: false,
                    statusbar: false,
                    branding: false,
                    promotion: false,
                    height: 220,
                    plugins: 'lists link autolink',
                    toolbar: 'undo redo | bold italic underline | bullist numlist | link | removeformat'
                });
            });
            return tinyMCE;
        }).catch(Notification.exception);
    }

    /**
     * Persist current TinyMCE content back to the underlying textareas
     * and remove the editor instances — call before reading values or
     * tearing down the panel.
     */
    function teardownRichFields() {
        if (window.tinyMCE) {
            window.tinyMCE.triggerSave();
            window.tinyMCE.remove('textarea.byblos-rich');
        }
    }

    /**
     * Mount the upload widget on every .byblos-image-field placeholder in $panel,
     * wiring the onUpload callback to the sibling hidden input.
     *
     * @param {jQuery} $panel
     */
    function initImageFields($panel) {
        var sesskey = (window.M && window.M.cfg && window.M.cfg.sesskey) || '';
        $panel.find('.byblos-image-field').each(function() {
            var $placeholder = $(this);
            var fieldId = $placeholder.data('field-id');
            var $hidden = $panel.find('#' + fieldId);
            var currentUrl = $hidden.val() || '';
            Upload.createWidget(
                $placeholder[0],
                pageId,
                sesskey,
                currentUrl || null,
                function(url) {
                    $hidden.val(url || '');
                }
            );
        });
    }

    /**
     * Build the edit form HTML for a given section type.
     * @param {string} stype
     * @param {Object} cfg
     * @param {string} content
     * @returns {string}
     */
    function buildEditForm(stype, cfg, content) {
        var h = '';
        switch (stype) {
            case 'hero':
                h = buildField('Name', 'bse_name', cfg.name) +
                    buildField('Title', 'bse_title', cfg.title) +
                    buildField('Subtitle', 'bse_subtitle', cfg.subtitle) +
                    buildField('Background Colour', 'bse_bg_color', cfg.bg_color, 'color') +
                    buildImageField('Background Image', 'bse_bg_image', cfg.bg_image) +
                    buildImageField('Profile Photo', 'bse_photo_url', cfg.photo_url);
                break;
            case 'text':
                h = buildField('Heading', 'bse_heading', cfg.heading) +
                    buildField('Body', 'bse_body', cfg.body, 'rich');
                break;
            case 'text_image':
                h = buildField('Heading', 'bse_heading', cfg.heading) +
                    buildField('Body', 'bse_body', cfg.body, 'rich') +
                    buildImageField('Image', 'bse_image_url', cfg.image_url) +
                    buildField('Image Alt Text', 'bse_image_alt', cfg.image_alt) +
                    buildField('Reverse layout (image left)', 'bse_reversed', cfg.reversed, 'checkbox');
                break;
            case 'gallery':
                var cols = cfg.columns || 3;
                h = '<div class="form-group"><label for="bse_columns">Columns</label>' +
                    '<select id="bse_columns" class="custom-select custom-select-sm">' +
                    '<option value="2"' + (cols === 2 ? ' selected' : '') + '>2</option>' +
                    '<option value="3"' + (cols === 3 ? ' selected' : '') + '>3</option>' +
                    '<option value="4"' + (cols === 4 ? ' selected' : '') + '>4</option>' +
                    '</select></div>';
                h += '<label class="font-weight-bold" style="font-size:0.8rem !important;">Gallery Items</label>';
                h += '<div id="bse_gallery_items"></div>';
                h += '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 bse-gallery-add">' +
                    '<i class="fa fa-plus"></i> Add Item</button>';
                break;
            case 'skills':
                h = buildField('Heading', 'bse_heading', cfg.heading);
                var skillsText = (cfg.skills || []).map(function(s) {
                    return s.name + ':' + s.level;
                }).join('\n');
                h += buildField('Skills (one per line: Name:Level 0-100)', 'bse_skills', skillsText, 'textarea');
                break;
            case 'timeline':
                h = buildField('Heading', 'bse_heading', cfg.heading);
                var tlText = (cfg.items || []).map(function(i) {
                    return i.date + '|' + i.title + '|' + (i.description || '');
                }).join('\n');
                h += buildField('Items (one per line: Date|Title|Description)', 'bse_items', tlText, 'textarea');
                break;
            case 'badges':
                h = buildField('Heading', 'bse_heading', cfg.heading) +
                    buildField('Show badges', 'bse_show', cfg.show !== false, 'checkbox');
                break;
            case 'completions':
                h = buildField('Heading', 'bse_heading', cfg.heading) +
                    buildField('Show completions', 'bse_show', cfg.show !== false, 'checkbox');
                break;
            case 'social':
                var platforms = ['linkedin', 'github', 'twitter', 'facebook', 'instagram', 'youtube', 'globe'];
                var linkMap = {};
                (cfg.links || []).forEach(function(l) {
                    linkMap[l.platform] = l.url;
                });
                h = '<label class="font-weight-bold" style="font-size:0.8rem !important;">Social Links</label>';
                platforms.forEach(function(p) {
                    var iconName = (p === 'globe') ? 'globe' : p;
                    h += '<div class="input-group input-group-sm mb-1">' +
                        '<div class="input-group-prepend"><span class="input-group-text" style="width:100px !important;">' +
                        '<i class="fa fa-' + iconName + '"></i> ' + p + '</span></div>' +
                        '<input type="url" class="form-control bse-social-url" data-platform="' + p +
                        '" value="' + escHtml(linkMap[p] || '') + '" placeholder="https://..."></div>';
                });
                break;
            case 'cta':
                h = buildField('Heading', 'bse_heading', cfg.heading) +
                    buildField('Body text', 'bse_body', cfg.body) +
                    buildField('Button text', 'bse_button_text', cfg.button_text) +
                    buildField('Button URL', 'bse_button_url', cfg.button_url) +
                    buildField('Background Colour', 'bse_bg_color', cfg.bg_color, 'color');
                break;
            case 'divider':
                h = '<div class="form-group"><label for="bse_style">Style</label>' +
                    '<select id="bse_style" class="custom-select custom-select-sm">' +
                    '<option value="line"' + (cfg.style === 'line' ? ' selected' : '') + '>Line</option>' +
                    '<option value="space"' + (cfg.style === 'space' ? ' selected' : '') + '>Space only</option>' +
                    '</select></div>' +
                    buildField('Spacing', 'bse_spacing', cfg.spacing || '2rem');
                break;
            case 'custom':
                h = '<div class="alert alert-warning small mb-2">' +
                    '<i class="fa fa-exclamation-triangle"></i> Raw HTML rendered without sanitisation.</div>' +
                    buildField('HTML Content', 'bse_html', content, 'rich');
                break;
            case 'chart':
                h = buildField('Heading', 'bse_heading', cfg.heading);
                h += '<div class="form-group"><label for="bse_chart_type">Chart type</label>' +
                    '<select id="bse_chart_type" class="custom-select custom-select-sm">' +
                    '<option value="bar"' + ((cfg.type || 'bar') === 'bar' ? ' selected' : '') + '>Bar</option>' +
                    '<option value="line"' + (cfg.type === 'line' ? ' selected' : '') + '>Line</option>' +
                    '<option value="pie"' + (cfg.type === 'pie' ? ' selected' : '') + '>Pie</option>' +
                    '<option value="donut"' + (cfg.type === 'donut' ? ' selected' : '') + '>Donut</option>' +
                    '</select></div>';
                h += buildField('Base Colour', 'bse_chart_color', cfg.color || '#0d6efd', 'color');
                h += '<label class="font-weight-bold" style="font-size:0.8rem !important;">Data points</label>';
                h += '<div id="bse_chart_items"></div>';
                h += '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 bse-chart-add">' +
                    '<i class="fa fa-plus"></i> Add data point</button>';
                break;
            case 'cloud':
                h = buildField('Heading', 'bse_heading', cfg.heading);
                h += buildField('Base Colour', 'bse_cloud_color', cfg.color || '#0d6efd', 'color');
                h += '<label class="font-weight-bold" style="font-size:0.8rem !important;">Words</label>';
                h += '<div id="bse_cloud_items"></div>';
                h += '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 bse-cloud-add">' +
                    '<i class="fa fa-plus"></i> Add word</button>';
                break;
            case 'quote':
                h = buildField('Quote body', 'bse_body', cfg.body, 'rich') +
                    buildField('Attribution', 'bse_attribution', cfg.attribution) +
                    buildField('Source URL (optional)', 'bse_source', cfg.source, 'url');
                break;
            case 'stats':
                h = buildField('Heading', 'bse_heading', cfg.heading);
                h += '<label class="font-weight-bold" style="font-size:0.8rem !important;">Stat cards (2–4)</label>';
                h += '<div id="bse_stats_items"></div>';
                h += '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 bse-stats-add">' +
                    '<i class="fa fa-plus"></i> Add stat card</button>';
                break;
            case 'citations':
                h = buildField('Heading', 'bse_heading', cfg.heading || 'References');
                h += '<div class="form-group"><label for="bse_citations_style">Citation style</label>' +
                    '<select id="bse_citations_style" class="custom-select custom-select-sm">' +
                    '<option value="plain"' + ((cfg.style || 'plain') === 'plain' ? ' selected' : '') + '>Plain</option>' +
                    '<option value="apa"' + (cfg.style === 'apa' ? ' selected' : '') + '>APA</option>' +
                    '<option value="mla"' + (cfg.style === 'mla' ? ' selected' : '') + '>MLA</option>' +
                    '<option value="chicago"' + (cfg.style === 'chicago' ? ' selected' : '') + '>Chicago</option>' +
                    '</select></div>';
                h += '<label class="font-weight-bold" style="font-size:0.8rem !important;">References</label>';
                h += '<div id="bse_citations_items"></div>';
                h += '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 bse-citations-add">' +
                    '<i class="fa fa-plus"></i> Add reference</button>';
                break;
            case 'files':
                h = buildField('Heading', 'bse_heading', cfg.heading || 'Files');
                h += '<div class="form-group"><label for="bse_files_display">Display</label>' +
                    '<select id="bse_files_display" class="custom-select custom-select-sm">' +
                    '<option value="list"' + ((cfg.display || 'list') === 'list' ? ' selected' : '') + '>List</option>' +
                    '<option value="tile"' + (cfg.display === 'tile' ? ' selected' : '') + '>Tile</option>' +
                    '<option value="thumbs"' + (cfg.display === 'thumbs' ? ' selected' : '') + '>Thumbnails</option>' +
                    '</select></div>';
                h += '<label class="font-weight-bold" style="font-size:0.8rem !important;">Files</label>';
                h += '<div id="bse_files_items"></div>';
                h += '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 bse-files-add">' +
                    '<i class="fa fa-plus"></i> Add file</button>';
                break;
            case 'pagenav':
                h = buildField('Heading', 'bse_heading', cfg.heading || 'Related pages');
                h += '<div class="form-group"><label for="bse_pn_source">Source</label>' +
                    '<select id="bse_pn_source" class="custom-select custom-select-sm">' +
                    '<option value="collection"' + ((cfg.source || 'collection') === 'collection' ? ' selected' : '') +
                    '>Collection</option>' +
                    '<option value="manual"' + (cfg.source === 'manual' ? ' selected' : '') +
                    '>Manual list of pages</option>' +
                    '</select></div>';
                // Collection source block.
                h += '<div class="form-group bse-pn-collection-block">' +
                    '<label for="bse_pn_collection">Collection</label>' +
                    '<select id="bse_pn_collection" class="custom-select custom-select-sm">' +
                    '<option value="0">Loading collections...</option>' +
                    '</select></div>';
                // Manual source block.
                h += '<div class="bse-pn-manual-block">' +
                    '<label class="font-weight-bold" style="font-size:0.8rem !important;">Pages</label>' +
                    '<div id="bse_pn_items"></div>' +
                    '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 bse-pn-add">' +
                    '<i class="fa fa-plus"></i> Add page</button></div>';
                h += '<div class="form-group"><label for="bse_pn_display">Display</label>' +
                    '<select id="bse_pn_display" class="custom-select custom-select-sm">' +
                    '<option value="tabs"' + (cfg.display === 'tabs' ? ' selected' : '') + '>Tabs</option>' +
                    '<option value="pills"' + ((cfg.display || 'pills') === 'pills' ? ' selected' : '') +
                    '>Pills</option>' +
                    '<option value="cards"' + (cfg.display === 'cards' ? ' selected' : '') + '>Cards</option>' +
                    '<option value="nextprev"' + (cfg.display === 'nextprev' ? ' selected' : '') +
                    '>Previous / next</option>' +
                    '</select></div>';
                h += '<div class="form-check mb-2 bse-pn-showdescs-wrap">' +
                    '<input type="checkbox" class="form-check-input" id="bse_pn_showdescs"' +
                    (cfg.show_descriptions ? ' checked' : '') + '>' +
                    '<label class="form-check-label small" for="bse_pn_showdescs">' +
                    'Show page descriptions on cards</label></div>';
                break;
            case 'youtube':
                h = buildField('Heading (optional)', 'bse_heading', cfg.heading);
                h += buildField('YouTube URL', 'bse_yt_url', cfg.url, 'url');
                h += '<small class="form-text text-muted mb-2" style="margin-top:-0.5rem !important;">' +
                    'Accepts any youtube.com/watch, youtu.be, /embed, /shorts, or /live link.</small>';
                h += '<div class="form-group"><label for="bse_yt_align">Layout</label>' +
                    '<select id="bse_yt_align" class="custom-select custom-select-sm">' +
                    '<option value="full"' + ((cfg.alignment || 'full') === 'full' ? ' selected' : '') +
                        '>Full width</option>' +
                    '<option value="center"' + (cfg.alignment === 'center' ? ' selected' : '') +
                        '>Center (max 720px)</option>' +
                    '<option value="left"' + (cfg.alignment === 'left' ? ' selected' : '') +
                        '>Align left — text on right</option>' +
                    '<option value="right"' + (cfg.alignment === 'right' ? ' selected' : '') +
                        '>Align right — text on left</option>' +
                    '</select></div>';
                h += buildField('Caption (optional, shown below video)', 'bse_yt_desc', cfg.description);
                h += buildField('Start at (seconds, optional)', 'bse_yt_start', cfg.start || '', 'number');
                h += buildField(
                    'Body text (shown beside the video in left/right layouts, below it in full/center)',
                    'bse_yt_body',
                    cfg.body || '',
                    'rich'
                );
                break;
            default:
                h = '<p class="text-muted">No edit form for this section type.</p>';
        }
        return h;
    }

    /**
     * Collect form values from the edit panel.
     * @param {string} stype
     * @param {jQuery} $panel
     * @returns {{cfg: Object, content: string}}
     */
    function collectEditForm(stype, $panel) {
        var v = function(id) {
            return $panel.find('#' + id).val() || '';
        };
        var chk = function(id) {
            return $panel.find('#' + id).is(':checked');
        };

        var cfg = {};
        var content = '';

        switch (stype) {
            case 'hero':
                cfg = {
                    name: v('bse_name'),
                    title: v('bse_title'),
                    subtitle: v('bse_subtitle'),
                    bg_color: v('bse_bg_color'),
                    bg_image: v('bse_bg_image'),
                    photo_url: v('bse_photo_url')
                };
                break;
            case 'text':
                cfg = {heading: v('bse_heading'), body: v('bse_body')};
                break;
            case 'text_image':
                cfg = {
                    heading: v('bse_heading'),
                    body: v('bse_body'),
                    image_url: v('bse_image_url'),
                    image_alt: v('bse_image_alt'),
                    reversed: chk('bse_reversed')
                };
                break;
            case 'gallery':
                cfg = {
                    columns: parseInt(v('bse_columns'), 10) || 3,
                    items: collectGalleryItems($panel)
                };
                break;
            case 'skills':
                var skills = v('bse_skills').split('\n').filter(function(s) {
                    return s.trim();
                }).map(function(line) {
                    var parts = line.split(':');
                    return {name: parts[0].trim(), level: parseInt(parts[1]) || 0};
                });
                cfg = {heading: v('bse_heading'), skills: skills};
                break;
            case 'timeline':
                var tlItems = v('bse_items').split('\n').filter(function(s) {
                    return s.trim();
                }).map(function(line) {
                    var parts = line.split('|');
                    return {
                        date: (parts[0] || '').trim(),
                        title: (parts[1] || '').trim(),
                        description: (parts[2] || '').trim()
                    };
                });
                cfg = {heading: v('bse_heading'), items: tlItems};
                break;
            case 'badges':
                cfg = {heading: v('bse_heading'), show: chk('bse_show')};
                break;
            case 'completions':
                cfg = {heading: v('bse_heading'), show: chk('bse_show')};
                break;
            case 'social':
                var links = [];
                $panel.find('.bse-social-url').each(function() {
                    var url = $(this).val().trim();
                    if (url) {
                        links.push({platform: $(this).data('platform'), url: url});
                    }
                });
                cfg = {links: links};
                break;
            case 'cta':
                cfg = {
                    heading: v('bse_heading'),
                    body: v('bse_body'),
                    button_text: v('bse_button_text'),
                    button_url: v('bse_button_url'),
                    bg_color: v('bse_bg_color')
                };
                break;
            case 'divider':
                cfg = {style: v('bse_style'), spacing: v('bse_spacing')};
                break;
            case 'custom':
                cfg = {};
                content = v('bse_html');
                break;
            case 'chart':
                cfg = {
                    heading: v('bse_heading'),
                    type: v('bse_chart_type') || 'bar',
                    color: v('bse_chart_color') || '#0d6efd',
                    items: collectChartItems($panel)
                };
                break;
            case 'cloud':
                cfg = {
                    heading: v('bse_heading'),
                    color: v('bse_cloud_color') || '#0d6efd',
                    items: collectCloudItems($panel)
                };
                break;
            case 'quote':
                cfg = {
                    body: v('bse_body'),
                    attribution: v('bse_attribution'),
                    source: v('bse_source')
                };
                break;
            case 'stats':
                cfg = {
                    heading: v('bse_heading'),
                    items: collectStatsItems($panel)
                };
                break;
            case 'citations':
                cfg = {
                    heading: v('bse_heading'),
                    style: v('bse_citations_style') || 'plain',
                    items: collectCitationsItems($panel)
                };
                break;
            case 'files':
                cfg = {
                    heading: v('bse_heading'),
                    display: v('bse_files_display') || 'list',
                    items: collectFilesItems($panel)
                };
                break;
            case 'pagenav':
                cfg = {
                    heading: v('bse_heading'),
                    source: v('bse_pn_source') || 'collection',
                    collectionid: parseInt(v('bse_pn_collection'), 10) || 0,
                    pageids: collectPagenavItems($panel),
                    display: v('bse_pn_display') || 'pills',
                    show_descriptions: chk('bse_pn_showdescs')
                };
                break;
            case 'youtube':
                cfg = {
                    heading: v('bse_heading'),
                    url: v('bse_yt_url'),
                    description: v('bse_yt_desc'),
                    start: parseInt(v('bse_yt_start'), 10) || 0,
                    alignment: v('bse_yt_align') || 'full',
                    body: v('bse_yt_body') || ''
                };
                break;
        }
        return {cfg: cfg, content: content};
    }

    /**
     * Collect chart data-point rows from the edit panel.
     * @param {jQuery} $panel
     * @returns {Array<{label: string, value: number}>}
     */
    function collectChartItems($panel) {
        var items = [];
        $panel.find('.bse-chart-item').each(function() {
            var $item = $(this);
            var label = $item.find('.bse-ci-label').val() || '';
            var value = parseFloat($item.find('.bse-ci-value').val());
            if (label || !isNaN(value)) {
                items.push({label: label, value: isNaN(value) ? 0 : value});
            }
        });
        return items;
    }

    /**
     * Append a chart data-point row to the container.
     * @param {jQuery} $container
     * @param {Object} item
     */
    function addChartItemRow($container, item) {
        item = item || {};
        var $row = $(
            '<div class="bse-chart-item d-flex align-items-center mb-1" style="gap:0.4rem;">' +
            '<input type="text" class="form-control form-control-sm bse-ci-label" placeholder="Label" value="' +
            escHtml(item.label || '') + '">' +
            '<input type="number" step="any" class="form-control form-control-sm bse-ci-value" ' +
            'placeholder="Value" style="max-width:110px;" value="' +
            escHtml((item.value !== null && item.value !== undefined) ? String(item.value) : '') + '">' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 bse-ci-remove">' +
            '<i class="fa fa-times"></i></button></div>'
        );
        $row.find('.bse-ci-remove').on('click', function() {
            $row.remove();
        });
        $container.append($row);
    }

    /**
     * Collect word-cloud rows from the edit panel.
     * @param {jQuery} $panel
     * @returns {Array<{text: string, weight: number}>}
     */
    function collectCloudItems($panel) {
        var items = [];
        $panel.find('.bse-cloud-item').each(function() {
            var $item = $(this);
            var text = $item.find('.bse-cl-text').val() || '';
            var weight = parseInt($item.find('.bse-cl-weight').val(), 10);
            if (text) {
                if (isNaN(weight) || weight < 1) {
                    weight = 1;
                }
                if (weight > 10) {
                    weight = 10;
                }
                items.push({text: text, weight: weight});
            }
        });
        return items;
    }

    /**
     * Append a word-cloud row to the container.
     * @param {jQuery} $container
     * @param {Object} item
     */
    function addCloudItemRow($container, item) {
        item = item || {};
        var w = (item.weight !== null && item.weight !== undefined) ? item.weight : 5;
        var $row = $(
            '<div class="bse-cloud-item d-flex align-items-center mb-1" style="gap:0.4rem;">' +
            '<input type="text" class="form-control form-control-sm bse-cl-text" placeholder="Word" value="' +
            escHtml(item.text || '') + '">' +
            '<input type="number" min="1" max="10" class="form-control form-control-sm bse-cl-weight" ' +
            'placeholder="Weight" style="max-width:90px;" value="' + escHtml(String(w)) + '">' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 bse-cl-remove">' +
            '<i class="fa fa-times"></i></button></div>'
        );
        $row.find('.bse-cl-remove').on('click', function() {
            $row.remove();
        });
        $container.append($row);
    }

    /**
     * Collect stat-card rows from the edit panel.
     * @param {jQuery} $panel
     * @returns {Array<{number: string, label: string, description: string}>}
     */
    function collectStatsItems($panel) {
        var items = [];
        $panel.find('.bse-stats-item').each(function() {
            var $item = $(this);
            items.push({
                number: $item.find('.bse-st-number').val() || '',
                label: $item.find('.bse-st-label').val() || '',
                description: $item.find('.bse-st-desc').val() || ''
            });
        });
        return items.slice(0, 4);
    }

    /**
     * Append a stat-card row to the container.
     * @param {jQuery} $container
     * @param {Object} item
     */
    function addStatsItemRow($container, item) {
        item = item || {};
        var $row = $(
            '<div class="bse-stats-item card card-body p-2 mb-2">' +
            '<div class="d-flex justify-content-between mb-1">' +
            '<small class="text-muted">Stat card</small>' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 bse-st-remove">' +
            '<i class="fa fa-times"></i></button></div>' +
            '<input type="text" class="form-control form-control-sm mb-1 bse-st-number"' +
            ' placeholder="Number (e.g. 42, 3.2x, 87%)" value="' +
            escHtml(item.number || '') + '">' +
            '<input type="text" class="form-control form-control-sm mb-1 bse-st-label" placeholder="Label" value="' +
            escHtml(item.label || '') + '">' +
            '<input type="text" class="form-control form-control-sm bse-st-desc" placeholder="Description (optional)" value="' +
            escHtml(item.description || '') + '">' +
            '</div>'
        );
        $row.find('.bse-st-remove').on('click', function() {
            $row.remove();
        });
        $container.append($row);
    }

    /**
     * Collect citation rows from the edit panel.
     * @param {jQuery} $panel
     * @returns {Array<{text: string, url: string}>}
     */
    function collectCitationsItems($panel) {
        var items = [];
        $panel.find('.bse-citations-item').each(function() {
            var $item = $(this);
            var text = $item.find('.bse-cit-text').val() || '';
            if (text.trim()) {
                items.push({
                    text: text,
                    url: $item.find('.bse-cit-url').val() || ''
                });
            }
        });
        return items;
    }

    /**
     * Append a citation row to the container.
     * @param {jQuery} $container
     * @param {Object} item
     */
    function addCitationsItemRow($container, item) {
        item = item || {};
        var $row = $(
            '<div class="bse-citations-item card card-body p-2 mb-2">' +
            '<div class="d-flex justify-content-between mb-1">' +
            '<small class="text-muted">Reference</small>' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 bse-cit-remove">' +
            '<i class="fa fa-times"></i></button></div>' +
            '<textarea class="form-control form-control-sm mb-1 bse-cit-text" rows="2" placeholder="Citation text">' +
            escHtml(item.text || '') + '</textarea>' +
            '<input type="url" class="form-control form-control-sm bse-cit-url" placeholder="URL (optional)" value="' +
            escHtml(item.url || '') + '">' +
            '</div>'
        );
        $row.find('.bse-cit-remove').on('click', function() {
            $row.remove();
        });
        $container.append($row);
    }

    /**
     * Collect file rows from the edit panel.
     * @param {jQuery} $panel
     * @returns {Array<{url: string, title: string, description: string, type: string}>}
     */
    function collectFilesItems($panel) {
        var items = [];
        $panel.find('.bse-files-item').each(function() {
            var $item = $(this);
            var url = ($item.find('.bse-file-url').val() || '').trim();
            if (url) {
                items.push({
                    url: url,
                    title: $item.find('.bse-file-title').val() || '',
                    description: $item.find('.bse-file-desc').val() || '',
                    type: $item.find('.bse-file-type').val() || ''
                });
            }
        });
        return items;
    }

    /**
     * Append a file row to the container.
     * @param {jQuery} $container
     * @param {Object} item
     */
    function addFilesItemRow($container, item) {
        item = item || {};
        var $row = $(
            '<div class="bse-files-item card card-body p-2 mb-2">' +
            '<div class="d-flex justify-content-between mb-1">' +
            '<small class="text-muted">File</small>' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 bse-file-remove">' +
            '<i class="fa fa-times"></i></button></div>' +
            '<div class="input-group input-group-sm mb-1">' +
                '<input type="url" class="form-control form-control-sm bse-file-url" ' +
                'placeholder="URL (required)" value="' + escHtml(item.url || '') + '">' +
                '<div class="input-group-append">' +
                    '<button type="button" class="btn btn-outline-secondary btn-sm bse-file-library" ' +
                        'title="Select from library">' +
                        '<i class="fa fa-folder-open-o"></i> Library' +
                    '</button>' +
                '</div>' +
            '</div>' +
            '<input type="text" class="form-control form-control-sm mb-1 bse-file-title" ' +
            'placeholder="Title (optional — defaults to filename)" value="' + escHtml(item.title || '') + '">' +
            '<input type="text" class="form-control form-control-sm mb-1 bse-file-desc" ' +
            'placeholder="Description (optional)" value="' + escHtml(item.description || '') + '">' +
            '<input type="text" class="form-control form-control-sm bse-file-type" ' +
            'placeholder="Type hint (optional — e.g. pdf, image, video)" value="' + escHtml(item.type || '') + '">' +
            '</div>'
        );
        $row.find('.bse-file-remove').on('click', function() {
            $row.remove();
        });

        /**
         * Open the artefact picker for this file row. Populates URL, and
         * title/type inputs if they are empty.
         */
        $row.find('.bse-file-library').on('click', function() {
            require(['local_byblos/artefact_picker'], function(Picker) {
                Picker.open({
                    typefilter: 'any',
                    title: 'Select from library',
                    onPick: function(artefact) {
                        $row.find('.bse-file-url').val(artefact.url || '');
                        var $titleInput = $row.find('.bse-file-title');
                        if (!($titleInput.val() || '').trim()) {
                            $titleInput.val(artefact.title || '');
                        }
                        var $typeInput = $row.find('.bse-file-type');
                        if (!($typeInput.val() || '').trim() && artefact.artefacttype) {
                            $typeInput.val(artefact.artefacttype);
                        }
                    }
                });
            });
        });

        $container.append($row);
    }

    /**
     * Collect the ordered array of page ids from the pagenav manual list.
     * @param {jQuery} $panel
     * @returns {number[]}
     */
    function collectPagenavItems($panel) {
        var ids = [];
        $panel.find('.bse-pn-item').each(function() {
            var id = parseInt($(this).data('page-id'), 10);
            if (id && !isNaN(id)) {
                ids.push(id);
            }
        });
        return ids;
    }

    /**
     * Append a pagenav page row to the manual-list container.
     * @param {jQuery} $container
     * @param {{id: number, title: string}} page
     */
    function addPagenavItemRow($container, page) {
        if (!page || !page.id) {
            return;
        }
        var $row = $(
            '<div class="bse-pn-item d-flex align-items-center mb-1" style="gap:0.4rem;"' +
            ' data-page-id="' + parseInt(page.id, 10) + '">' +
            '<span class="flex-fill" style="overflow:hidden !important; text-overflow:ellipsis !important;' +
            ' white-space:nowrap !important;">' + escHtml(page.title || ('Page #' + page.id)) + '</span>' +
            '<button type="button" class="btn btn-link btn-sm bse-pn-up p-0" title="Move up">' +
            '<i class="fa fa-arrow-up"></i></button>' +
            '<button type="button" class="btn btn-link btn-sm bse-pn-down p-0" title="Move down">' +
            '<i class="fa fa-arrow-down"></i></button>' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 bse-pn-remove" title="Remove">' +
            '<i class="fa fa-times"></i></button></div>'
        );
        $row.find('.bse-pn-remove').on('click', function() {
            $row.remove();
        });
        $row.find('.bse-pn-up').on('click', function() {
            var $prev = $row.prev('.bse-pn-item');
            if ($prev.length) {
                $row.insertBefore($prev);
            }
        });
        $row.find('.bse-pn-down').on('click', function() {
            var $next = $row.next('.bse-pn-item');
            if ($next.length) {
                $row.insertAfter($next);
            }
        });
        $container.append($row);
    }

    /**
     * Open the inline page-picker modal for a pagenav section.
     *
     * Fetches the current user's pages (excluding the host page) via
     * `local_byblos_list_user_pages` and lets the user click one to append
     * it to the manual list. Skips pages already in the list.
     *
     * @param {jQuery} $panel    The pagenav edit panel.
     * @param {jQuery} $container The manual-list container.
     */
    function openPagenavPagePicker($panel, $container) {
        var existing = {};
        $container.find('.bse-pn-item').each(function() {
            existing[parseInt($(this).data('page-id'), 10)] = true;
        });

        // Build a lightweight inline modal.
        var $overlay = $(
            '<div class="bse-pn-picker-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.4);' +
            'z-index:2000;display:flex;align-items:center;justify-content:center;">' +
            '<div class="card" style="width:min(520px, 90vw);max-height:80vh;display:flex;flex-direction:column;">' +
            '<div class="card-header d-flex justify-content-between align-items-center">' +
            '<strong>Pick a page</strong>' +
            '<button type="button" class="btn btn-link btn-sm bse-pn-picker-close p-0">' +
            '<i class="fa fa-times"></i></button></div>' +
            '<div class="card-body bse-pn-picker-body" style="overflow:auto;padding:0.5rem;">' +
            '<p class="text-muted small mb-0 p-2">Loading your pages...</p>' +
            '</div></div></div>'
        );
        $('body').append($overlay);

        var close = function() {
            $overlay.remove();
        };
        $overlay.find('.bse-pn-picker-close').on('click', close);
        $overlay.on('click', function(ev) {
            if (ev.target === $overlay[0]) {
                close();
            }
        });

        callExternal('local_byblos_list_user_pages', {
            excludepageid: pageId
        }).then(function(rows) {
            var $body = $overlay.find('.bse-pn-picker-body');
            $body.empty();
            var pages = (rows || []).filter(function(p) {
                return !existing[parseInt(p.id, 10)];
            });
            if (pages.length === 0) {
                $body.html('<p class="text-muted small p-2 mb-0">No more pages to add.</p>');
                return pages;
            }
            var $list = $('<div class="list-group list-group-flush"></div>');
            pages.forEach(function(p) {
                var $item = $(
                    '<button type="button" class="list-group-item list-group-item-action" ' +
                    'data-page-id="' + parseInt(p.id, 10) + '">' +
                    '<div style="font-weight:600 !important;">' + escHtml(p.title || '') + '</div>' +
                    '<small class="text-muted">' + escHtml(p.status || '') + '</small></button>'
                );
                $item.on('click', function() {
                    addPagenavItemRow($container, {id: parseInt(p.id, 10), title: p.title || ''});
                    close();
                });
                $list.append($item);
            });
            $body.append($list);
            return pages;
        }).catch(function(err) {
            $overlay.find('.bse-pn-picker-body').html(
                '<p class="text-danger small p-2 mb-0">Failed to load pages.</p>'
            );
            Notification.exception(err);
        });
    }

    /**
     * Initialise the pagenav edit panel: async-load collections, wire up
     * source-toggle show/hide, populate the manual list, bind Add-page.
     *
     * @param {jQuery} $panel
     * @param {Object} cfg
     */
    function initPagenavPanel($panel, cfg) {
        cfg = cfg || {};

        var $sourceSel = $panel.find('#bse_pn_source');
        var $collBlock = $panel.find('.bse-pn-collection-block');
        var $manualBlock = $panel.find('.bse-pn-manual-block');
        var $displaySel = $panel.find('#bse_pn_display');
        var $showdescsWrap = $panel.find('.bse-pn-showdescs-wrap');
        var $collSel = $panel.find('#bse_pn_collection');
        var $itemsContainer = $panel.find('#bse_pn_items');

        /**
         * Show / hide the source-specific blocks based on the select.
         */
        function applySourceVisibility() {
            var src = $sourceSel.val();
            if (src === 'manual') {
                $collBlock.hide();
                $manualBlock.show();
            } else {
                $collBlock.show();
                $manualBlock.hide();
            }
        }

        /**
         * Show/hide the "show descriptions" checkbox based on display mode.
         */
        function applyDisplayVisibility() {
            var d = $displaySel.val();
            if (d === 'cards') {
                $showdescsWrap.show();
            } else {
                $showdescsWrap.hide();
            }
        }

        applySourceVisibility();
        applyDisplayVisibility();

        $sourceSel.on('change', applySourceVisibility);
        $displaySel.on('change', applyDisplayVisibility);

        // Async-load the user's collections.
        callExternal('local_byblos_list_user_collections', {
            withpageid: 0
        }).then(function(rows) {
            $collSel.empty();
            var list = rows || [];
            if (list.length === 0) {
                $collSel.append('<option value="0">You have no collections yet.</option>');
                return list;
            }
            var selectedId = parseInt(cfg.collectionid, 10) || 0;
            list.forEach(function(c) {
                var cid = parseInt(c.id, 10);
                var label = escHtml(c.title || '') + ' (' + (c.pagecount || 0) + ')';
                var isSel = (cid === selectedId) ? ' selected' : '';
                $collSel.append('<option value="' + cid + '"' + isSel + '>' + label + '</option>');
            });
            return list;
        }).catch(Notification.exception);

        // Populate manual-list rows. We need titles for existing ids — fetch via list_user_pages.
        var existingIds = Array.isArray(cfg.pageids) ? cfg.pageids.map(function(x) {
            return parseInt(x, 10);
        }).filter(function(x) {
            return x > 0;
        }) : [];

        if (existingIds.length) {
            callExternal('local_byblos_list_user_pages', {
                excludepageid: 0
            }).then(function(rows) {
                var byId = {};
                (rows || []).forEach(function(p) {
                    byId[parseInt(p.id, 10)] = p;
                });
                existingIds.forEach(function(id) {
                    var p = byId[id];
                    addPagenavItemRow($itemsContainer, {
                        id: id,
                        title: p ? p.title : ('Page #' + id)
                    });
                });
                return rows;
            }).catch(function(err) {
                // On failure still add rows with fallback titles.
                existingIds.forEach(function(id) {
                    addPagenavItemRow($itemsContainer, {id: id, title: 'Page #' + id});
                });
                Notification.exception(err);
            });
        }

        $panel.find('.bse-pn-add').on('click', function() {
            openPagenavPagePicker($panel, $itemsContainer);
        });
    }

    /**
     * Collect gallery items from the edit panel.
     * @param {jQuery} $panel
     * @returns {Array}
     */
    function collectGalleryItems($panel) {
        var items = [];
        $panel.find('.bse-gallery-item').each(function() {
            var $item = $(this);
            items.push({
                title: $item.find('.bse-gi-title').val() || '',
                image_url: $item.find('.bse-gi-image').val() || '',
                description: $item.find('.bse-gi-desc').val() || ''
            });
        });
        return items;
    }

    /**
     * Add a gallery item row to the editor panel.
     * @param {jQuery} $container
     * @param {Object} item
     */
    function addGalleryItemRow($container, item) {
        item = item || {};
        var $row = $(
            '<div class="bse-gallery-item card card-body p-2 mb-2">' +
            '<div class="d-flex justify-content-between mb-1">' +
            '<small class="text-muted">Gallery Item</small>' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 bse-gi-remove">' +
            '<i class="fa fa-times"></i></button></div>' +
            '<input type="text" class="form-control form-control-sm mb-1 bse-gi-title" placeholder="Title" value="' +
            escHtml(item.title || '') + '">' +
            '<div class="bse-gi-image-widget mb-1"></div>' +
            '<input type="hidden" class="bse-gi-image" value="' + escHtml(item.image_url || '') + '">' +
            '<input type="text" class="form-control form-control-sm bse-gi-desc" placeholder="Description" value="' +
            escHtml(item.description || '') + '">' +
            '</div>'
        );
        $row.find('.bse-gi-remove').on('click', function() {
            $row.remove();
        });
        $container.append($row);

        var sesskey = (window.M && window.M.cfg && window.M.cfg.sesskey) || '';
        var $hidden = $row.find('.bse-gi-image');
        Upload.createWidget(
            $row.find('.bse-gi-image-widget')[0],
            pageId,
            sesskey,
            $hidden.val() || null,
            function(url) {
                $hidden.val(url || '');
            }
        );
    }

    /**
     * Toggle the inline edit panel for a section card.
     * @param {jQuery} $card
     */
    function toggleEditPanel($card) {
        var $panel = $card.find('.byblos-edit-panel');

        // Toggle off.
        if ($panel.is(':visible')) {
            teardownRichFields();
            $panel.hide().empty();
            return;
        }

        var stype = $card.data('sectiontype');
        var raw = $card.attr('data-configdata') || '{}';
        var cfg;
        try {
            cfg = JSON.parse(raw);
        } catch (ex) {
            cfg = {};
        }
        var content = $card.attr('data-content') || '';

        var html = buildEditForm(stype, cfg, content);
        html += '<div class="mt-2">' +
            '<button class="btn btn-primary btn-sm bse-save-section">' +
            '<i class="fa fa-check"></i> Save</button> ' +
            '<button class="btn btn-secondary btn-sm bse-cancel-section">' +
            'Cancel</button></div>';

        $panel.html(html).show();

        // Bring the newly-opened panel into view and focus its first field so
        // the user knows the Edit button worked (the panel can otherwise open
        // below the fold on long pages or near the bottom of the stack).
        scrollIntoViewSafely($panel);
        focusFirstField($panel);

        // Mount upload widgets on any image fields in the panel.
        initImageFields($panel);

        // Attach TinyMCE to any rich-text textareas.
        initRichFields($panel);

        // Populate gallery items if gallery type.
        if (stype === 'gallery') {
            var $container = $panel.find('#bse_gallery_items');
            var items = cfg.items || [];
            items.forEach(function(item) {
                addGalleryItemRow($container, item);
            });
            $panel.find('.bse-gallery-add').on('click', function() {
                addGalleryItemRow($container, {});
            });
        }

        // Populate repeating-row widgets for the academic section types.
        if (stype === 'chart') {
            var $chartContainer = $panel.find('#bse_chart_items');
            (cfg.items || []).forEach(function(item) {
                addChartItemRow($chartContainer, item);
            });
            $panel.find('.bse-chart-add').on('click', function() {
                addChartItemRow($chartContainer, {});
            });
        }
        if (stype === 'cloud') {
            var $cloudContainer = $panel.find('#bse_cloud_items');
            (cfg.items || []).forEach(function(item) {
                addCloudItemRow($cloudContainer, item);
            });
            $panel.find('.bse-cloud-add').on('click', function() {
                addCloudItemRow($cloudContainer, {});
            });
        }
        if (stype === 'stats') {
            var $statsContainer = $panel.find('#bse_stats_items');
            (cfg.items || []).forEach(function(item) {
                addStatsItemRow($statsContainer, item);
            });
            $panel.find('.bse-stats-add').on('click', function() {
                if ($statsContainer.find('.bse-stats-item').length >= 4) {
                    return;
                }
                addStatsItemRow($statsContainer, {});
            });
        }
        if (stype === 'citations') {
            var $citContainer = $panel.find('#bse_citations_items');
            (cfg.items || []).forEach(function(item) {
                addCitationsItemRow($citContainer, item);
            });
            $panel.find('.bse-citations-add').on('click', function() {
                addCitationsItemRow($citContainer, {});
            });
        }
        if (stype === 'files') {
            var $filesContainer = $panel.find('#bse_files_items');
            (cfg.items || []).forEach(function(item) {
                addFilesItemRow($filesContainer, item);
            });
            $panel.find('.bse-files-add').on('click', function() {
                addFilesItemRow($filesContainer, {});
            });
        }
        if (stype === 'pagenav') {
            initPagenavPanel($panel, cfg);
        }

        // Save handler.
        $panel.find('.bse-save-section').on('click', function() {
            teardownRichFields();
            var result = collectEditForm(stype, $panel);
            var sectionId = parseInt($card.data('section-id'), 10);

            callExternal('local_byblos_update_section', {
                sectionid: sectionId,
                configdata: JSON.stringify(result.cfg),
                content: result.content || ''
            }).then(function(data) {
                if (data && data.rendered) {
                    $card.find('.byblos-section-preview').html(data.rendered);

                    // Initialise contenteditable on text sections.
                    if (stype === 'text' || stype === 'custom') {
                        InlineEditor.initSection($card);
                    }
                }
                $card.attr('data-configdata', JSON.stringify(result.cfg));
                $card.attr('data-content', result.content || '');
                $panel.hide().empty();
                return;
            }).catch(Notification.exception);
        });

        // Cancel handler.
        $panel.find('.bse-cancel-section').on('click', function() {
            teardownRichFields();
            $panel.hide().empty();
        });
    }

    // ================================================================
    // Preview Toggle
    // ================================================================

    /**
     * Wire up the Edit/Preview mode toggle button.
     */
    function initPreviewToggle() {
        $('#byblos-preview-toggle').on('click', function() {
            $root.toggleClass('byblos-preview-mode');
            var inPreview = $root.hasClass('byblos-preview-mode');
            $(this).html(
                inPreview
                    ? '<i class="fa fa-pencil"></i> Edit'
                    : '<i class="fa fa-eye"></i> Preview'
            );
        });
    }

    // ================================================================
    // Theme Picker
    // ================================================================

    /**
     * Wire up the theme picker cards to persist the selection.
     */
    function initThemePicker() {
        $(document).on('click', '.byblos-theme-card', function() {
            var themeKey = $(this).data('theme');
            $('#byblos-selected-theme').val(themeKey);
            $('.byblos-theme-card').removeClass('byblos-theme-active');
            $(this).addClass('byblos-theme-active');

            callExternal('local_byblos_save_page_settings', {
                pageid: pageId,
                layoutkey: 'single',
                themekey: themeKey
            }).then(function() {
                window.location.reload();
                return;
            }).catch(Notification.exception);
        });
    }

    // ================================================================
    // Add Section Buttons
    // ================================================================

    /**
     * Wire up the "Add section" buttons between existing sections.
     */
    function initAddSectionButtons() {
        $(document).on('click', '.byblos-add-section-btn', function() {
            var position = parseInt($(this).data('position'), 10) || 0;
            openTypePicker(position);
        });
    }

    // ================================================================
    // Advisory Assessment Checklist
    // ================================================================

    /** @type {?Array} Cached checklist data after first fetch. */
    var checklistData = null;
    /** @type {boolean} Whether the checklist panel has been populated. */
    var checklistLoaded = false;

    /**
     * HTML-escape helper for checklist rendering.
     * @param {string} s
     * @returns {string}
     */
    function checklistEsc(s) {
        return escHtml(String((s === null || s === undefined) ? '' : s));
    }

    /**
     * Format a unix timestamp as a short local date string for the "Due ..." hint.
     * Returns empty string for 0.
     * @param {number} ts
     * @returns {string}
     */
    function formatDue(ts) {
        if (!ts) {
            return '';
        }
        var d = new Date(ts * 1000);
        // Use locale date + short time, kept short to fit the sidebar.
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
    }

    /**
     * Build the HTML for a single assignment's checklist items.
     * @param {Object} a Assignment record { assignmentid, name, coursename, duedate, items }.
     * @returns {string} HTML.
     */
    function renderChecklistItems(a) {
        var html = '';
        html += '<div class="byblos-checklist-assignment">';
        html += '<h6 class="mb-1">' + checklistEsc(a.name) + '</h6>';
        var meta = [];
        if (a.coursename) {
            meta.push(checklistEsc(a.coursename));
        }
        if (a.duedate && a.duedate > 0) {
            meta.push('Due ' + checklistEsc(formatDue(a.duedate)));
        }
        if (meta.length) {
            html += '<div class="text-muted small mb-2">' + meta.join(' &middot; ') + '</div>';
        }
        html += '<p class="small text-muted mb-2"><em>Items below are guidance only &mdash; '
              + 'they are not enforced when you submit.</em></p>';
        html += '<ul class="list-unstyled mb-0">';
        (a.items || []).forEach(function(item, idx) {
            var cbid = 'byblos-ck-' + a.assignmentid + '-' + idx;
            html += '<li class="byblos-checklist-item">';
            html += '<input type="checkbox" id="' + cbid + '">';
            html += '<label for="' + cbid + '" class="mb-0">' + checklistEsc(item) + '</label>';
            html += '</li>';
        });
        html += '</ul>';
        html += '</div>';
        return html;
    }

    /**
     * Render the full checklist panel content into #byblos-checklist-content.
     * Handles 0 / 1 / 2+ assignments with the appropriate UI.
     */
    function renderChecklistPanel() {
        var $content = $('#byblos-checklist-content');
        if (!checklistData || checklistData.length === 0) {
            $content.html('<p class="text-muted mb-0">No active assignments with a checklist for this page.</p>');
            return;
        }
        if (checklistData.length === 1) {
            $content.html(renderChecklistItems(checklistData[0]));
            return;
        }
        // Multiple: show a dropdown + the currently selected assignment.
        var html = '';
        html += '<div class="form-group mb-3">';
        html += '<label for="byblos-checklist-select" class="mb-1">Show checklist for:</label>';
        html += '<select class="form-control form-control-sm" id="byblos-checklist-select">';
        checklistData.forEach(function(a, i) {
            html += '<option value="' + i + '">' + checklistEsc(a.name) + '</option>';
        });
        html += '</select>';
        html += '</div>';
        html += '<div id="byblos-checklist-selected">' + renderChecklistItems(checklistData[0]) + '</div>';
        $content.html(html);

        $content.off('change.byblosck').on('change.byblosck', '#byblos-checklist-select', function() {
            var idx = parseInt($(this).val(), 10) || 0;
            $('#byblos-checklist-selected').html(renderChecklistItems(checklistData[idx]));
        });
    }

    /**
     * Show the checklist panel and auto-expand its collapsible body.
     */
    function revealChecklistPanel() {
        var $panel = $('#byblos-checklist-panel');
        $panel.removeAttr('hidden');
        // Expand the Bootstrap collapse if it isn't already open.
        var $body = $('#byblos-checklist-body');
        if (!$body.hasClass('show')) {
            $body.addClass('show');
            $panel.find('.card-header').attr('aria-expanded', 'true');
        }
    }

    /**
     * Click-to-edit on the page title. Swaps the h2 for an input; Enter/blur
     * saves via local_byblos_save_page_settings, Escape cancels. Restores the
     * heading with the new text on success.
     */
    function initTitleEditor() {
        var $h2 = $('#byblos-page-title');
        if (!$h2.length) {
            return;
        }

        /**
         * Swap the heading for a text input and wire up save/cancel handlers.
         */
        function enterEdit() {
            if ($h2.find('input').length) {
                return; // Already editing.
            }
            var current = ($h2.text() || '').trim();
            var $input = $('<input type="text" class="form-control form-control-sm byblos-title-input" maxlength="255">');
            $input.val(current);
            $h2.empty().append($input);
            $input.trigger('focus').trigger('select');

            var cancelled = false;

            /**
             * Commit or discard the edit. No-ops on second call.
             * @param {boolean} save True to persist; false to revert.
             */
            function finish(save) {
                if (cancelled) {
                    return;
                }
                cancelled = true;
                var next = ($input.val() || '').trim();
                if (!save || next === '' || next === current) {
                    $h2.text(current);
                    return;
                }
                callExternal('local_byblos_save_page_settings', {
                    pageid: pageId,
                    title: next
                }).then(function() {
                    $h2.text(next);
                    document.title = next;
                    return;
                }).catch(function(err) {
                    $h2.text(current);
                    Notification.exception(err);
                });
            }

            $input.on('keydown', function(ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    finish(true);
                } else if (ev.key === 'Escape') {
                    ev.preventDefault();
                    finish(false);
                }
            });
            $input.on('blur', function() {
                finish(true);
            });
        }

        $h2.on('click', enterEdit);
        $h2.on('keydown', function(ev) {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                enterEdit();
            }
        });
    }

    /**
     * Wire up the "Checklist" toggle button in the editor header.
     * First click fetches checklists via web service and renders them;
     * subsequent clicks toggle the panel's visibility.
     */
    function initChecklist() {
        $(document).on('click', '#byblos-checklist-toggle', function(ev) {
            ev.preventDefault();

            if (!checklistLoaded) {
                callExternal('local_byblos_get_assignment_checklists', {
                    pageid: pageId
                }).then(function(rows) {
                    checklistData = rows || [];
                    checklistLoaded = true;
                    renderChecklistPanel();
                    revealChecklistPanel();
                    return;
                }).catch(Notification.exception);
                return;
            }

            // Subsequent clicks: toggle visibility.
            var $panel = $('#byblos-checklist-panel');
            if ($panel.attr('hidden')) {
                revealChecklistPanel();
            } else {
                $panel.attr('hidden', 'hidden');
            }
        });
    }

    // ================================================================
    // Collection control (header dropdown)
    // ================================================================

    /** @type {?Array} Last-fetched collection list for this page. */
    var collectionsCache = null;
    /** @type {?Array} Groups the current user belongs to, for new-group-collection picker. */
    var userGroupsCache = null;
    /** @type {boolean} Whether the dropdown has been populated this session. */
    var collectionsLoaded = false;

    /**
     * Update the dropdown button label with the current primary collection title
     * (or fall back to the "no collection" string).
     */
    function updateCollectionButtonLabel() {
        var $label = $('#byblos-collection-control .byblos-collection-current-label');
        if (!collectionsCache) {
            return;
        }
        var primary = collectionsCache.find(function(c) {
            return c.is_primary;
        });
        if (primary) {
            $label.text(primary.title || '');
        } else {
            $label.text('No collection');
        }
    }

    /**
     * Build a single row in the collections dropdown.
     * @param {Object} c Collection descriptor: {id, title, contains_page, is_primary}.
     * @returns {jQuery}
     */
    function buildCollectionRow(c) {
        var id = parseInt(c.id, 10);
        var contains = !!c.contains_page;
        var primary = !!c.is_primary;
        var groupLabel = '';
        if (c.is_group) {
            groupLabel = ' <span class="badge badge-info byblos-coll-group-badge" title="'
                + escHtml(c.group_name || '') + '">'
                + '<i class="fa fa-users"></i> ' + escHtml(c.group_name || 'Group')
                + '</span>';
        }
        var $row = $(
            '<div class="byblos-collection-row d-flex align-items-center mb-1" data-cid="' + id + '">' +
            '<div class="form-check mb-0 flex-fill">' +
            '<input type="checkbox" class="form-check-input byblos-coll-member" id="bcc_m_' + id + '"' +
            (contains ? ' checked' : '') + '>' +
            '<label class="form-check-label small" for="bcc_m_' + id + '">' +
            escHtml(c.title || '') + groupLabel + '</label></div>' +
            '<div class="form-check form-check-inline mb-0" title="Set as primary">' +
            '<input type="radio" class="form-check-input byblos-coll-primary" name="byblos-coll-primary" ' +
            'id="bcc_p_' + id + '" value="' + id + '"' + (primary ? ' checked' : '') +
            (contains ? '' : ' disabled') + '>' +
            '<label class="form-check-label small" for="bcc_p_' + id + '">Primary</label></div>' +
            '</div>'
        );
        return $row;
    }

    /**
     * Render the dropdown body from the current collectionsCache.
     */
    function renderCollectionDropdown() {
        var $menu = $('#byblos-collection-control .byblos-collection-dropdown');
        $menu.empty();
        if (!collectionsCache || collectionsCache.length === 0) {
            $menu.append('<p class="text-muted small mb-2">You have no collections yet.</p>');
        } else {
            collectionsCache.forEach(function(c) {
                $menu.append(buildCollectionRow(c));
            });
        }

        $menu.append('<hr class="my-2">');
        var groupOptions = '<option value="0">Personal (just me)</option>';
        (userGroupsCache || []).forEach(function(g) {
            var label = escHtml(g.name);
            if (g.coursecode) {
                label += ' — ' + escHtml(g.coursecode);
            }
            groupOptions += '<option value="' + parseInt(g.id, 10) + '">' + label + '</option>';
        });
        $menu.append(
            '<div class="form-group mb-0">' +
            '<label class="small mb-1" for="bcc_newtitle">Create new collection</label>' +
            '<input type="text" class="form-control form-control-sm mb-1" id="bcc_newtitle" ' +
            'placeholder="Collection title">' +
            '<select class="form-control form-control-sm mb-1" id="bcc_newgroup">' +
            groupOptions + '</select>' +
            '<button type="button" class="btn btn-primary btn-sm" id="bcc_create">' +
            'Create collection</button>' +
            '</div>'
        );

        updateCollectionButtonLabel();
    }

    /**
     * Apply a server response that includes `primary_collectionid` to the
     * local cache, so UI stays in sync without a full refetch.
     *
     * @param {?number} primaryId
     */
    function applyPrimaryFromServer(primaryId) {
        if (!collectionsCache) {
            return;
        }
        var pid = parseInt(primaryId, 10) || 0;
        collectionsCache.forEach(function(c) {
            c.is_primary = (parseInt(c.id, 10) === pid);
        });
        // Reflect in the DOM.
        $('#byblos-collection-control .byblos-coll-primary').each(function() {
            var cid = parseInt($(this).val(), 10);
            $(this).prop('checked', cid === pid);
        });
        updateCollectionButtonLabel();
    }

    /**
     * Wire event handlers for rows and the create-new inline form.
     */
    function bindCollectionDropdownHandlers() {
        var $menu = $('#byblos-collection-control .byblos-collection-dropdown');

        // Keep the dropdown open when clicking inside it (Bootstrap closes on
        // any link or form-control click by default with some themes).
        $menu.off('click.bccstop').on('click.bccstop', function(ev) {
            ev.stopPropagation();
        });

        // Checkbox — add or remove the page.
        $menu.off('change.bccmember').on('change.bccmember', '.byblos-coll-member', function() {
            var $row = $(this).closest('.byblos-collection-row');
            var cid = parseInt($row.data('cid'), 10);
            var checked = $(this).is(':checked');
            var $primary = $row.find('.byblos-coll-primary');

            if (checked) {
                callExternal('local_byblos_add_page_to_collection', {
                    pageid: pageId,
                    collectionid: cid,
                    setprimary: false
                }).then(function(res) {
                    $primary.prop('disabled', false);
                    var c = collectionsCache.find(function(x) {
                        return parseInt(x.id, 10) === cid;
                    });
                    if (c) {
                        c.contains_page = true;
                    }
                    applyPrimaryFromServer(res && res.primary_collectionid);
                    return res;
                }).catch(function(err) {
                    $(this).prop('checked', false);
                    Notification.exception(err);
                }.bind(this));
            } else {
                callExternal('local_byblos_remove_page_from_collection', {
                    pageid: pageId,
                    collectionid: cid
                }).then(function(res) {
                    $primary.prop('disabled', true).prop('checked', false);
                    var c = collectionsCache.find(function(x) {
                        return parseInt(x.id, 10) === cid;
                    });
                    if (c) {
                        c.contains_page = false;
                        c.is_primary = false;
                    }
                    applyPrimaryFromServer(res && res.primary_collectionid);
                    return res;
                }).catch(function(err) {
                    $(this).prop('checked', true);
                    Notification.exception(err);
                }.bind(this));
            }
        });

        // Primary radio.
        $menu.off('change.bccprimary').on('change.bccprimary', '.byblos-coll-primary', function() {
            var cid = parseInt($(this).val(), 10);
            callExternal('local_byblos_set_primary_collection', {
                pageid: pageId,
                collectionid: cid
            }).then(function(res) {
                applyPrimaryFromServer(res && res.primary_collectionid);
                return res;
            }).catch(Notification.exception);
        });

        // Create new collection.
        $menu.off('click.bcccreate').on('click.bcccreate', '#bcc_create', function() {
            var $input = $menu.find('#bcc_newtitle');
            var title = ($input.val() || '').trim();
            if (!title) {
                $input.trigger('focus');
                return;
            }
            var groupId = parseInt($menu.find('#bcc_newgroup').val(), 10) || 0;
            var groupName = '';
            if (groupId > 0) {
                (userGroupsCache || []).some(function(g) {
                    if (parseInt(g.id, 10) === groupId) {
                        groupName = g.name;
                        return true;
                    }
                    return false;
                });
            }
            callExternal('local_byblos_create_collection', {
                title: title,
                description: '',
                addpageid: pageId,
                groupid: groupId
            }).then(function(res) {
                var newId = parseInt(res && res.collectionid, 10);
                if (!newId) {
                    return res;
                }
                collectionsCache.unshift({
                    id: newId,
                    title: title,
                    description: '',
                    pagecount: 1,
                    contains_page: true,
                    is_primary: (parseInt(res.primary_collectionid, 10) === newId),
                    is_group: groupId > 0,
                    is_creator: true,
                    group_name: groupName
                });
                renderCollectionDropdown();
                bindCollectionDropdownHandlers();
                applyPrimaryFromServer(res.primary_collectionid);
                return res;
            }).catch(Notification.exception);
        });
    }

    /**
     * Initialise the collection control in the editor header. Lazily fetches
     * the user's collections with `contains_page` / `is_primary` flags the
     * first time the dropdown opens.
     */
    function initCollectionControl() {
        var $toggle = $('#byblos-collection-toggle');
        if (!$toggle.length) {
            return;
        }

        $toggle.on('click', function() {
            if (collectionsLoaded) {
                return;
            }
            collectionsLoaded = true;

            var $menu = $('#byblos-collection-control .byblos-collection-dropdown');
            $menu.html('<p class="text-muted small mb-0">Loading collections...</p>');

            $.when(
                callExternal('local_byblos_list_user_collections', {withpageid: pageId}),
                callExternal('local_byblos_list_user_groups', {})
            ).then(function(rows, groups) {
                collectionsCache = (rows || []).map(function(c) {
                    return {
                        id: parseInt(c.id, 10),
                        title: c.title || '',
                        description: c.description || '',
                        pagecount: parseInt(c.pagecount, 10) || 0,
                        contains_page: !!c.contains_page,
                        is_primary: !!c.is_primary,
                        is_group: !!c.is_group,
                        is_creator: !!c.is_creator,
                        group_name: c.group_name || ''
                    };
                });
                userGroupsCache = (groups || []).map(function(g) {
                    return {
                        id: parseInt(g.id, 10),
                        name: g.name || '',
                        courseid: parseInt(g.courseid, 10) || 0,
                        coursecode: g.coursecode || ''
                    };
                });
                renderCollectionDropdown();
                bindCollectionDropdownHandlers();
                return rows;
            }).catch(function(err) {
                collectionsLoaded = false;
                $menu.html('<p class="text-danger small mb-0">Failed to load collections.</p>');
                Notification.exception(err);
            });
        });
    }

    // ================================================================
    // Init
    // ================================================================

    return {
        /**
         * Initialise the section editor.
         *
         * @param {number} _pageId - Portfolio page ID.
         */
        init: function(_pageId) {
            pageId = _pageId;
            $root = $('#byblos-editor');

            if (!$root.length) {
                return;
            }

            // Bind actions on existing section cards.
            $root.find('.byblos-section-card').each(function() {
                bindSectionActions($(this));
            });

            // Init sub-systems.
            initTypePicker();
            initAddSectionButtons();
            initPreviewToggle();
            initThemePicker();
            initChecklist();
            initTitleEditor();
            initCollectionControl();

            // Init contenteditable on text sections.
            InlineEditor.initAll($root);
        }
    };
});

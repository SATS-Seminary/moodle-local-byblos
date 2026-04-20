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
 * Inline comment popover for the portfolio assessment viewer.
 *
 * Each element bearing data-anchor gets a floating comment-bubble icon
 * in its top-right corner. Clicking the icon reveals a popover holding
 * the comment thread and (unless readonly) a composer.
 *
 * @module     local_byblos/review
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    'use strict';

    var state = {
        submissionid: 0,
        role: 'none',
        userid: 0,
        readonly: true,
        library: {available: false, coursecode: ''},
        popoutClass: null,
        popoutInstance: null,
        lastFocusedField: null,
        peerpanel: {enabled: false},
    };

    /**
     * Lazy-load and return the unified-grader CommentLibraryPopout class.
     * Cached after first call so we don't re-require on every open.
     * @returns {Promise<Function>}
     */
    function loadPopoutClass() {
        if (state.popoutClass) {
            return Promise.resolve(state.popoutClass);
        }
        return new Promise(function(resolve, reject) {
            require(['local_unifiedgrader/components/comment_library_popout'], function(Mod) {
                // ES-module default export comes through as .default under requirejs interop.
                var Klass = (Mod && Mod.default) ? Mod.default : Mod;
                state.popoutClass = Klass;
                resolve(Klass);
            }, reject);
        });
    }

    /**
     * Get or create a shared popout instance. One instance is reused across anchors;
     * the "last focused field" is tracked globally so insertion targets the textarea
     * whose 📖 button was clicked most recently.
     * @returns {Promise<Object>} The popout instance.
     */
    function getPopout() {
        if (state.popoutInstance) {
            return Promise.resolve(state.popoutInstance);
        }
        return loadPopoutClass().then(function(Popout) {
            var instance = new Popout(
                state.library.coursecode || '',
                function() {
                    return state.lastFocusedField;
                }
            );
            state.popoutInstance = instance;
            return instance;
        });
    }

    /**
     * Wrap core/ajax for a single external function call.
     * @param {string} methodname
     * @param {Object} args
     * @returns {Promise}
     */
    function call(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0];
    }

    /**
     * Escape HTML entities for safe insertion.
     * @param {string} s
     * @returns {string}
     */
    function escapeHtml(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }

    /**
     * Format a unix epoch (seconds) as a locale date/time string.
     * @param {number} epochSeconds
     * @returns {string}
     */
    function formatDate(epochSeconds) {
        try {
            return new Date(epochSeconds * 1000).toLocaleString();
        } catch (e) {
            return '';
        }
    }

    /**
     * Update the count badge on an anchor's toggle button.
     * @param {jQuery} $anchor
     */
    function refreshCountBadge($anchor) {
        var count = $anchor.find('.byblos-comment-list .byblos-comment').length;
        var $toggle = $anchor.find('> .byblos-comment-toggle');
        $toggle.attr('data-count', count);
        $toggle.toggleClass('has-comments', count > 0);
    }

    /**
     * Build the editable card for a single comment.
     * @param {Object} c Comment record.
     * @returns {jQuery}
     */
    function buildCommentCard(c) {
        var mine = (parseInt(c.authorid, 10) === parseInt(state.userid, 10));
        var canModerate = mine || state.role === 'teacher';
        var roleBadgeClass = c.role === 'teacher' ? 'bg-primary'
            : c.role === 'peer' ? 'bg-info' : 'bg-secondary';

        var $card = $(
            '<div class="byblos-comment">' +
              '<div class="byblos-comment-header d-flex justify-content-between align-items-start">' +
                '<small class="text-muted">' +
                  '<span class="badge ' + roleBadgeClass + ' me-1 mr-1">' + escapeHtml(c.role) + '</span>' +
                  escapeHtml(formatDate(c.timecreated)) +
                '</small>' +
                (canModerate ?
                  '<div class="byblos-comment-actions">' +
                    (mine ? '<button type="button" class="btn btn-link btn-sm p-0 bse-edit-comment">' +
                      '<i class="fa fa-pencil"></i></button> ' : '') +
                    '<button type="button" class="btn btn-link btn-sm p-0 text-danger bse-delete-comment">' +
                      '<i class="fa fa-trash"></i></button>' +
                  '</div>' : '') +
              '</div>' +
              '<div class="byblos-comment-body mt-1"></div>' +
            '</div>'
        );
        $card.find('.byblos-comment-body').text(c.body);
        $card.data('comment', c);

        if (mine) {
            $card.find('.bse-edit-comment').on('click', function() {
                beginEdit($card, c);
            });
        }
        if (canModerate) {
            $card.find('.bse-delete-comment').on('click', function() {
                if (!window.confirm('Delete this comment?')) {
                    return;
                }
                var $anchor = $card.closest('[data-anchor]');
                call('local_byblos_delete_comment', {id: c.id}).then(function() {
                    $card.remove();
                    refreshCountBadge($anchor);
                    return;
                }).catch(Notification.exception);
            });
        }
        return $card;
    }

    /**
     * Swap a comment card into inline edit mode.
     * @param {jQuery} $card
     * @param {Object} c
     */
    function beginEdit($card, c) {
        var $body = $card.find('.byblos-comment-body');
        var $ta = $('<textarea class="form-control form-control-sm" rows="3">');
        $ta.val(c.body);
        $body.empty().append($ta);

        var $actions = $card.find('.byblos-comment-actions');
        $actions.empty()
            .append('<button type="button" class="btn btn-primary btn-sm bse-save-edit">Save</button> ')
            .append('<button type="button" class="btn btn-secondary btn-sm bse-cancel-edit">Cancel</button>');

        $actions.find('.bse-save-edit').on('click', function() {
            var newBody = ($ta.val() || '').trim();
            if (!newBody) {
                return;
            }
            call('local_byblos_update_comment', {id: c.id, body: newBody}).then(function() {
                c.body = newBody;
                $card.replaceWith(buildCommentCard(c));
                return;
            }).catch(Notification.exception);
        });
        $actions.find('.bse-cancel-edit').on('click', function() {
            $card.replaceWith(buildCommentCard(c));
        });
    }

    /**
     * Build and inject the comment UI (toggle icon + popover) for a single anchor.
     * The anchor element becomes position:relative so children can float.
     * @param {jQuery} $anchor Element with data-anchor attribute.
     */
    function decorateAnchor($anchor) {
        $anchor.addClass('byblos-anchored');

        var $toggle = $(
            '<button type="button" class="byblos-comment-toggle" ' +
              'title="Comments" aria-label="Comments" data-count="0">' +
              '<i class="fa fa-comment-o"></i>' +
            '</button>'
        );

        var libraryBtn = '';
        if (!state.readonly && state.role === 'teacher' && state.library.available) {
            libraryBtn =
                ' <button type="button" class="btn btn-outline-secondary btn-sm byblos-library-btn" ' +
                  'title="Comment library">' +
                  '<i class="fa fa-book"></i></button>';
        }

        var composerHtml = state.readonly ? '' :
            '<div class="byblos-comment-composer">' +
              '<textarea class="form-control form-control-sm mb-1 byblos-comment-input" ' +
                'rows="2" placeholder="Write a comment..."></textarea>' +
              '<div class="d-flex align-items-center">' +
                '<button type="button" class="btn btn-primary btn-sm byblos-add-comment-btn">' +
                  '<i class="fa fa-plus"></i> Add comment</button>' +
                libraryBtn +
              '</div>' +
            '</div>';

        var $popover = $(
            '<div class="byblos-comment-popover" hidden>' +
              '<div class="byblos-comment-popover-header d-flex justify-content-between align-items-center">' +
                '<strong class="small">Comments</strong>' +
                '<button type="button" class="btn btn-link btn-sm p-0 byblos-comment-close" ' +
                  'aria-label="Close"><i class="fa fa-times"></i></button>' +
              '</div>' +
              '<div class="byblos-comment-list"></div>' +
              composerHtml +
            '</div>'
        );

        $toggle.on('click', function(e) {
            e.stopPropagation();
            closeAllPopoversExcept($anchor);
            $popover.prop('hidden', !$popover.prop('hidden'));
        });
        $popover.find('.byblos-comment-close').on('click', function() {
            $popover.prop('hidden', true);
        });
        $popover.on('click', function(e) {
            e.stopPropagation();
        });

        if (!state.readonly && state.role === 'teacher' && state.library.available) {
            var $libBtn = $popover.find('.byblos-library-btn');
            var $input = $popover.find('.byblos-comment-input');

            // Track focus so the popout inserts into the correct textarea.
            $input.on('focus', function() {
                state.lastFocusedField = $input[0];
            });

            $libBtn.on('click', function(e) {
                e.stopPropagation();
                // Make sure this composer owns the insertion target, even if the
                // textarea was never explicitly focused.
                state.lastFocusedField = $input[0];
                $input.focus();
                getPopout().then(function(popout) {
                    popout.toggle($libBtn[0]);
                    return popout;
                }).catch(Notification.exception);
            });
        }

        if (!state.readonly) {
            $popover.find('.byblos-add-comment-btn').on('click', function() {
                var $ta = $popover.find('.byblos-comment-input');
                var body = ($ta.val() || '').trim();
                if (!body) {
                    return;
                }
                call('local_byblos_add_comment', {
                    submissionid: state.submissionid,
                    anchorkey: $anchor.attr('data-anchor'),
                    body: body,
                }).then(function(result) {
                    var rec = {
                        id: result.id,
                        submissionid: state.submissionid,
                        anchorkey: $anchor.attr('data-anchor'),
                        authorid: state.userid,
                        role: result.role,
                        body: body,
                        timecreated: Math.floor(Date.now() / 1000),
                        timemodified: Math.floor(Date.now() / 1000),
                    };
                    $popover.find('.byblos-comment-list').append(buildCommentCard(rec));
                    $ta.val('');
                    refreshCountBadge($anchor);
                    return;
                }).catch(Notification.exception);
            });
        }

        $anchor.prepend($popover).prepend($toggle);
    }

    /**
     * Close any open popovers outside of the given anchor.
     * @param {jQuery} [$keep] Anchor to leave alone.
     */
    function closeAllPopoversExcept($keep) {
        $('.byblos-comment-popover').each(function() {
            if (!$keep || !$.contains($keep[0], this)) {
                $(this).prop('hidden', true);
            }
        });
    }

    /**
     * Iterate every data-anchor target and attach the comment UI.
     */
    function decorateAllAnchors() {
        $('[data-anchor]').each(function() {
            decorateAnchor($(this));
        });
    }

    /**
     * Fetch all comments for the submission and distribute to their anchor popovers.
     * @returns {Promise}
     */
    function loadComments() {
        return call('local_byblos_list_comments', {submissionid: state.submissionid}).then(function(comments) {
            comments.forEach(function(c) {
                var $anchor = $('[data-anchor="' + c.anchorkey + '"]');
                if ($anchor.length) {
                    $anchor.find('.byblos-comment-list').append(buildCommentCard(c));
                }
            });
            $('[data-anchor]').each(function() {
                refreshCountBadge($(this));
            });
            return comments;
        });
    }

    /**
     * Wire up the "Submit review" panel for peer reviewers.
     * Reads the panel element from the DOM (rendered by review.php) and binds
     * star clicks + submit button to the peer-review external service.
     */
    function initPeerPanel() {
        if (!state.peerpanel || !state.peerpanel.enabled) {
            return;
        }
        var $panel = $('.byblos-peer-submit');
        if (!$panel.length) {
            return;
        }
        if ($panel.attr('data-status') === 'complete') {
            return;
        }

        var mode = $panel.attr('data-scoremode');
        var peerid = parseInt($panel.attr('data-peerid'), 10);

        // Star widget — click to set a value 1..5 into the hidden score input.
        if (mode === 'stars') {
            $panel.find('.byblos-peer-star').on('click', function() {
                var val = parseInt($(this).attr('data-value'), 10);
                $panel.find('#byblos-peer-score').val(val);
                $panel.find('.byblos-peer-star').each(function() {
                    var v = parseInt($(this).attr('data-value'), 10);
                    $(this).toggleClass('btn-warning', v <= val)
                        .toggleClass('btn-outline-warning', v > val);
                });
            });
        }

        // Rubric grid — click a level button to pick it for that criterion.
        if (mode === 'rubric') {
            $panel.find('.byblos-peer-rubric-level').on('click', function() {
                var $lvl = $(this);
                var critid = $lvl.attr('data-criterion');
                // Deselect siblings for the same criterion.
                $panel.find('.byblos-peer-rubric-level[data-criterion="' + critid + '"]')
                    .removeClass('btn-primary active')
                    .addClass('btn-outline-secondary');
                $lvl.removeClass('btn-outline-secondary')
                    .addClass('btn-primary active');

                // Rebuild the serialised selection map.
                var selection = {};
                $panel.find('.byblos-peer-rubric-level.active').each(function() {
                    var $l = $(this);
                    selection[$l.attr('data-criterion')] = parseInt($l.attr('data-level'), 10);
                });
                $panel.find('#byblos-peer-rubric').val(JSON.stringify(selection));
            });
        }

        $panel.find('.byblos-peer-submit-btn').on('click', function() {
            var $btn = $(this);
            var $feedback = $panel.find('.byblos-peer-feedback');
            $feedback.empty();

            var score = null;
            if (mode === 'numeric' || mode === 'stars') {
                var raw = $panel.find('#byblos-peer-score').val();
                if (raw !== '' && raw !== null && raw !== undefined) {
                    score = parseFloat(raw);
                    if (isNaN(score)) {
                        score = null;
                    }
                }
            }

            var rubricdata = null;
            if (mode === 'rubric') {
                rubricdata = $panel.find('#byblos-peer-rubric').val() || '';
                // Advisory score = sum of the selected levels' scores.
                var total = 0;
                $panel.find('.byblos-peer-rubric-level.active').each(function() {
                    total += parseFloat($(this).attr('data-score')) || 0;
                });
                score = total;
            }

            $btn.prop('disabled', true);
            call('local_byblos_submit_peer_review', {
                peerassignmentid: peerid,
                advisoryscore: score,
                rubricdata: rubricdata,
            }).then(function(res) {
                if (res && res.ok) {
                    $panel.attr('data-status', 'complete');
                    $feedback
                        .removeClass('text-danger')
                        .addClass('text-success')
                        .text('Review submitted.');
                    $panel.find('#byblos-peer-score, #byblos-peer-rubric, .byblos-peer-star').prop('disabled', true);
                    $btn.remove();
                }
                return res;
            }).catch(function(err) {
                $btn.prop('disabled', false);
                $feedback
                    .removeClass('text-success')
                    .addClass('text-danger')
                    .text('Failed to submit review.');
                Notification.exception(err);
            });
        });
    }

    return {
        /**
         * Boot the review viewer.
         * @param {Object} cfg Configuration.
         * @param {number} cfg.submissionid Submission ID.
         * @param {string} cfg.role Viewer role: self|teacher|peer.
         * @param {number} cfg.userid Current user ID.
         * @param {boolean} cfg.readonly True for students viewing feedback.
         * @param {Object} [cfg.library] Optional unified-grader library config.
         * @param {Object} [cfg.peerpanel] Optional peer-review submission panel config.
         */
        init: function(cfg) {
            state.submissionid = parseInt(cfg.submissionid, 10);
            state.role = cfg.role || 'none';
            state.userid = parseInt(cfg.userid, 10);
            state.readonly = !!cfg.readonly;
            state.library = cfg.library || {available: false, coursecode: ''};
            state.peerpanel = cfg.peerpanel || {enabled: false};

            decorateAllAnchors();
            loadComments().catch(Notification.exception);
            initPeerPanel();

            // Close popovers when clicking outside. Keep open if the click landed
            // on the unified-grader library popout (which lives on document.body,
            // outside our popover tree).
            $(document).on('click', function(e) {
                if (e.target && e.target.closest('.local-unifiedgrader-clib-popout')) {
                    return;
                }
                closeAllPopoversExcept(null);
            });
        },
    };
});

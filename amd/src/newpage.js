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
 * Template tile picker for the "Create new page" wizard.
 *
 * Handles selection state, thumbnail lazy-loading, and the preview modal.
 *
 * @module     local_byblos/newpage
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    'use strict';

    /**
     * Lazily swap each thumbnail iframe's data-src into src when it enters the viewport.
     * Falls back to an immediate swap when IntersectionObserver is unavailable.
     */
    function initThumbnails() {
        var iframes = document.querySelectorAll('.byblos-template-thumb-iframe[data-src]');

        /**
         * Set the iframe's real src and drop the data-src marker.
         * @param {HTMLIFrameElement} iframe
         */
        function swapToRealSrc(iframe) {
            var real = iframe.getAttribute('data-src');
            if (!real) {
                return;
            }
            iframe.src = real;
            iframe.removeAttribute('data-src');
        }

        if (typeof window.IntersectionObserver !== 'function') {
            iframes.forEach(swapToRealSrc);
            return;
        }
        var io = new window.IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    swapToRealSrc(entry.target);
                    io.unobserve(entry.target);
                }
            });
        }, {rootMargin: '150px'});
        iframes.forEach(function(f) {
            io.observe(f);
        });
    }

    /**
     * Wire up the preview modal: clicking the preview button on a tile sets
     * the modal iframe's src and shows the modal.
     */
    function initPreviewModal() {
        var $modal = $('#byblos-template-preview-modal');
        var $frame = $('#byblos-template-preview-frame');
        if (!$modal.length || !$frame.length) {
            return;
        }

        $(document).on('click', '.byblos-template-preview-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var url = this.getAttribute('data-preview-url');
            if (!url) {
                return;
            }
            $frame.attr('src', url);
            if (typeof $modal.modal === 'function') {
                $modal.modal('show');
            } else {
                // Fallback — no jQuery bootstrap plugin; add display toggle manually.
                $modal.addClass('show').css('display', 'block');
            }
        });

        // Reset the iframe src on close so the preview doesn't keep loading in the background.
        $modal.on('hidden.bs.modal hide.bs.modal', function() {
            $frame.attr('src', 'about:blank');
        });
        $modal.on('click', '[data-bs-dismiss="modal"], [data-dismiss="modal"]', function() {
            if (typeof $modal.modal !== 'function') {
                $modal.removeClass('show').css('display', 'none');
                $frame.attr('src', 'about:blank');
            }
        });
    }

    return {
        /**
         * Wire up template card selection, thumbnails, and preview modal.
         */
        init: function() {
            var cards = document.querySelectorAll('.byblos-template-card');
            var hiddenInput = document.getElementById('byblos-selected-template');
            var createBtn = document.getElementById('byblos-create-btn');
            var form = document.getElementById('byblos-newpage-form');

            if (!cards.length || !hiddenInput || !createBtn || !form) {
                return;
            }

            cards.forEach(function(card) {
                card.addEventListener('click', function(e) {
                    // Clicks on the preview button are handled separately and must not
                    // select the card underneath.
                    if (e.target && e.target.closest('.byblos-template-preview-btn')) {
                        return;
                    }
                    cards.forEach(function(c) {
                        c.classList.remove('byblos-template-card-selected', 'border-primary');
                    });
                    card.classList.add('byblos-template-card-selected', 'border-primary');
                    hiddenInput.value = card.getAttribute('data-template');
                    createBtn.disabled = false;
                });

                card.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        card.click();
                    }
                });
            });

            form.addEventListener('submit', function(e) {
                if (!hiddenInput.value) {
                    e.preventDefault();
                }
            });

            initThumbnails();
            initPreviewModal();
        }
    };
});

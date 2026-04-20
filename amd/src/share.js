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
 * Share page — swap the sharevalue picker to match the selected sharetype.
 *
 * Only the picker matching the current sharetype is named "sharevalue" and
 * enabled; the others are hidden and disabled so the form posts a single
 * consistent value. Public type submits no sharevalue.
 *
 * @module     local_byblos/share
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /**
     * Toggle visibility + `name` attribute so only the picker matching the
     * selected sharetype posts a sharevalue.
     *
     * @param {string} selected Current sharetype value.
     */
    function apply(selected) {
        document.querySelectorAll('.byblos-share-picker').forEach(function(group) {
            var matches = group.dataset.sharetype === selected;
            group.classList.toggle('d-none', !matches);
            var select = group.querySelector('select[data-name="sharevalue"]');
            if (!select) {
                return;
            }
            if (matches) {
                select.name = 'sharevalue';
                select.disabled = false;
            } else {
                select.removeAttribute('name');
                select.disabled = true;
            }
        });
    }

    return {
        init: function() {
            var typeSelect = document.getElementById('byblos-sharetype');
            if (!typeSelect) {
                return;
            }
            typeSelect.addEventListener('change', function() {
                apply(typeSelect.value);
            });
            apply(typeSelect.value);
        }
    };
});

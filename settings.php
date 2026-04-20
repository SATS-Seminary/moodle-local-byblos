<?php
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
 * Admin settings for local_byblos.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_byblos', get_string('pluginname', 'local_byblos'));

    // Enable/disable the plugin.
    $settings->add(new admin_setting_configcheckbox(
        'local_byblos/enabled',
        get_string('setting_enabled', 'local_byblos'),
        get_string('setting_enabled_desc', 'local_byblos'),
        1
    ));

    // Default page theme.
    $themes = [
        'clean'        => get_string('theme_clean', 'local_byblos'),
        'academic'     => get_string('theme_academic', 'local_byblos'),
        'modern-dark'  => get_string('theme_modern_dark', 'local_byblos'),
        'creative'     => get_string('theme_creative', 'local_byblos'),
        'corporate'    => get_string('theme_corporate', 'local_byblos'),
        'streaming'    => get_string('theme_streaming', 'local_byblos'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_byblos/defaulttheme',
        get_string('setting_defaulttheme', 'local_byblos'),
        get_string('setting_defaulttheme_desc', 'local_byblos'),
        'clean',
        $themes
    ));

    // Default page layout.
    $layouts = [
        'single'         => get_string('layout_single', 'local_byblos'),
        'two-equal'      => get_string('layout_two_equal', 'local_byblos'),
        'two-wide-left'  => get_string('layout_two_wide_left', 'local_byblos'),
        'two-wide-right' => get_string('layout_two_wide_right', 'local_byblos'),
        'three-equal'    => get_string('layout_three_equal', 'local_byblos'),
        'hero-two'       => get_string('layout_hero_two', 'local_byblos'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_byblos/defaultlayout',
        get_string('setting_defaultlayout', 'local_byblos'),
        get_string('setting_defaultlayout_desc', 'local_byblos'),
        'single',
        $layouts
    ));

    // Allow public (secret URL) sharing.
    $settings->add(new admin_setting_configcheckbox(
        'local_byblos/allowpublic',
        get_string('setting_allowpublic', 'local_byblos'),
        get_string('setting_allowpublic_desc', 'local_byblos'),
        0
    ));

    // Maximum artefacts per user.
    $settings->add(new admin_setting_configtext(
        'local_byblos/maxartefacts',
        get_string('setting_maxartefacts', 'local_byblos'),
        get_string('setting_maxartefacts_desc', 'local_byblos'),
        500,
        PARAM_INT
    ));

    // Maximum pages per user.
    $settings->add(new admin_setting_configtext(
        'local_byblos/maxpages',
        get_string('setting_maxpages', 'local_byblos'),
        get_string('setting_maxpages_desc', 'local_byblos'),
        50,
        PARAM_INT
    ));

    // Allow PDF export.
    $settings->add(new admin_setting_configcheckbox(
        'local_byblos/allowpdf',
        get_string('setting_allowpdf', 'local_byblos'),
        get_string('setting_allowpdf_desc', 'local_byblos'),
        1
    ));

    // Auto-import artefacts from course activities.
    $settings->add(new admin_setting_configcheckbox(
        'local_byblos/autoimport',
        get_string('setting_autoimport', 'local_byblos'),
        get_string('setting_autoimport_desc', 'local_byblos'),
        1
    ));

    // Completion: minimum portfolio pages.
    $settings->add(new admin_setting_configtext(
        'local_byblos/completion_pages',
        get_string('completion_pages', 'local_byblos'),
        get_string('completion_pages_desc', 'local_byblos'),
        0,
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}

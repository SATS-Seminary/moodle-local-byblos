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
 * Library of core hooks for local_byblos.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the global navigation tree to add "My Portfolio" to the user menu.
 *
 * @param global_navigation $nav The global navigation instance.
 * @return void
 */
function local_byblos_extend_navigation(global_navigation $nav): void {
    global $USER;

    if (!get_config('local_byblos', 'enabled')) {
        return;
    }

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();
    if (!has_capability('local/byblos:use', $context)) {
        return;
    }

    $node = $nav->add(
        get_string('nav_myportfolio', 'local_byblos'),
        new moodle_url('/local/byblos/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_byblos_portfolio',
        new pix_icon('i/portfolio', get_string('nav_myportfolio', 'local_byblos'))
    );
    $node->showinflatnavigation = true;
}

/**
 * Extends course navigation to add "Course Portfolios" link.
 *
 * @param navigation_node $nav   The course navigation node.
 * @param stdClass        $course The course object.
 * @param context_course  $context The course context.
 * @return void
 */
function local_byblos_extend_navigation_course(navigation_node $nav, stdClass $course, context_course $context): void {
    if (!get_config('local_byblos', 'enabled')) {
        return;
    }

    if (!has_capability('local/byblos:use', context_system::instance())) {
        return;
    }

    $nav->add(
        get_string('nav_course_portfolios', 'local_byblos'),
        new moodle_url('/local/byblos/courseportfolios.php', ['courseid' => $course->id]),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_byblos_course_portfolios',
        new pix_icon('i/portfolio', get_string('nav_course_portfolios', 'local_byblos'))
    );
}

/**
 * Serves files for the local_byblos plugin.
 *
 * Handles file areas: 'images', 'exports', 'artefact'.
 *
 * For the `images` filearea, files are stored under a user context with
 * itemid = pageid. Access is granted if:
 *  - The requesting user is the file owner, OR
 *  - The page status is 'published', OR
 *  - The requesting user has an active share record, OR
 *  - The requesting user has `local/byblos:manageall`.
 *
 * @param stdClass        $course        The course object (or 0 for system context).
 * @param stdClass|null   $cm            The course module object (unused).
 * @param context         $context       The context.
 * @param string          $filearea      The file area name.
 * @param array           $args          The remaining path components.
 * @param bool            $forcedownload Whether to force download.
 * @param array           $options       Additional options.
 * @return bool False if the file is not found or access denied.
 */
function local_byblos_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = [],
): bool {
    global $USER, $DB;

    if (!get_config('local_byblos', 'enabled')) {
        return false;
    }

    $allowedareas = ['images', 'exports', 'artefact'];
    if (!in_array($filearea, $allowedareas, true)) {
        return false;
    }

    // Require login for non-public file areas.
    require_login();

    if (!has_capability('local/byblos:use', context_system::instance())) {
        return false;
    }

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    // For the images filearea, enforce page-level access control.
    if ($filearea === 'images' && $context->contextlevel === CONTEXT_USER) {
        $pageid = $itemid;
        $page = \local_byblos\page::get($pageid);
        if (!$page) {
            return false;
        }

        $fileownerid = $context->instanceid;
        if ((int) $USER->id !== (int) $fileownerid) {
            $ismanager = has_capability('local/byblos:manageall', context_system::instance());
            $ispublished = (isset($page->status) && $page->status === 'published');

            if (!$ismanager && !$ispublished) {
                $hasshare = $DB->record_exists('local_byblos_share', [
                    'pageid' => $pageid,
                    'targetuserid' => $USER->id,
                ]);
                if (!$hasshare) {
                    return false;
                }
            }
        }
    }

    $fs = get_file_storage();
    $file = $fs->get_file(
        $context->id,
        'local_byblos',
        $filearea,
        $itemid,
        $filepath,
        $filename
    );

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}

/**
 * Add byblos-related nodes to the settings navigation.
 *
 * Used by Moodle core (`settings_navigation::load_local_plugin_settings()`) to let
 * local plugins decorate the settings tree. We add a "Manage peer reviewers"
 * link to the module-settings node of any assignment course module that has
 * the byblos submission plugin enabled with peer review turned on. Moodle's
 * secondary navigation then surfaces this as a tab on the assignment page.
 *
 * @param settings_navigation $settingsnav
 * @param \context|null       $context
 * @return void
 */
function local_byblos_extend_settings_navigation(settings_navigation $settingsnav, ?\context $context): void {
    global $PAGE, $DB, $USER;

    if (!$context || $context->contextlevel !== CONTEXT_MODULE) {
        return;
    }
    $cm = $PAGE->cm;
    if (!$cm || $cm->modname !== 'assign') {
        return;
    }

    $assignid = (int) $cm->instance;

    // Byblos enabled on this assignment?
    $enabled = $DB->get_record('assign_plugin_config', [
        'assignment' => $assignid,
        'plugin'     => 'byblos',
        'subtype'    => 'assignsubmission',
        'name'       => 'enabled',
    ]);
    if (!$enabled || $enabled->value !== '1') {
        return;
    }
    $peerenabled = $DB->get_record('assign_plugin_config', [
        'assignment' => $assignid,
        'plugin'     => 'byblos',
        'subtype'    => 'assignsubmission',
        'name'       => 'peerenabled',
    ]);
    if (!$peerenabled || $peerenabled->value !== '1') {
        return;
    }

    $modsettings = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$modsettings) {
        return;
    }

    // ------------------------------------------------------------------
    // Student-reviewer branch — show "My peer reviews" if the current user
    // has any peer_assignment rows on this assignment. Count includes both
    // pending and complete so reviewers can revisit comments they've left.
    // ------------------------------------------------------------------
    $reviewerrows = $DB->get_records('local_byblos_peer_assignment', [
        'assignmentid' => $assignid,
        'reviewerid'   => $USER->id,
    ]);
    if (!empty($reviewerrows)) {
        $pending = 0;
        foreach ($reviewerrows as $r) {
            if ($r->status === 'pending') {
                $pending++;
            }
        }
        $label = get_string('myreviews_nav', 'local_byblos');
        if ($pending > 0) {
            $label .= ' (' . $pending . ')';
        }
        $myurl = new moodle_url(
            '/local/byblos/view.php',
            ['tab' => 'reviews', 'assignmentid' => $assignid]
        );
        $mynode = $modsettings->add(
            $label,
            $myurl,
            navigation_node::TYPE_SETTING,
            null,
            'byblos_my_peerreviews',
            new pix_icon('i/feedback', '')
        );
        $mynode->showinflatnavigation = true;
    }

    // ------------------------------------------------------------------
    // Teacher branch — "Manage peer reviewers" for graders.
    // ------------------------------------------------------------------
    if (!has_capability('mod/assign:grade', $context)) {
        return;
    }

    $url = new moodle_url('/local/byblos/peerassign.php', ['assignmentid' => $assignid]);
    $node = $modsettings->add(
        get_string('manage_peer_reviewers', 'assignsubmission_byblos'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'byblos_peerreview',
        new pix_icon('i/users', '')
    );
    $node->showinflatnavigation = true;
}

/**
 * Adds a portfolio link to the user profile page.
 *
 * @param \core_user\output\myprofile\tree $tree         The profile tree.
 * @param stdClass                          $user         The user whose profile is being viewed.
 * @param bool                              $iscurrentuser Whether viewing own profile.
 * @param stdClass|null                     $course       The course context (or null).
 * @return void
 */
function local_byblos_myprofile_navigation(
    \core_user\output\myprofile\tree $tree,
    stdClass $user,
    bool $iscurrentuser,
    ?stdClass $course,
): void {
    if (!get_config('local_byblos', 'enabled')) {
        return;
    }

    $context = context_system::instance();

    // Show portfolio link for the current user, or for managers.
    if ($iscurrentuser && has_capability('local/byblos:use', $context)) {
        $url = new moodle_url('/local/byblos/index.php');
        $node = new \core_user\output\myprofile\node(
            'miscellaneous',
            'local_byblos',
            get_string('nav_profile_portfolio', 'local_byblos'),
            null,
            $url,
        );
        $tree->add_node($node);
    } else if (!$iscurrentuser && has_capability('local/byblos:viewshared', $context)) {
        // Teachers/managers can view this user's shared portfolio.
        $url = new moodle_url('/local/byblos/view.php', ['userid' => $user->id]);
        $node = new \core_user\output\myprofile\node(
            'miscellaneous',
            'local_byblos',
            get_string('nav_profile_portfolio', 'local_byblos'),
            null,
            $url,
        );
        $tree->add_node($node);
    }
}

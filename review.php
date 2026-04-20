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
 * Assessment review viewer — renders a submitted portfolio with inline comment overlay.
 *
 * Authorised viewers:
 *  - The student who owns the submission (read-only).
 *  - Any user with mod/assign:grade on the linked assignment (teacher role).
 *  - A user with a peer_assignment row for this reviewer/reviewee pair (peer role).
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\submission as byblos_submission;
use local_byblos\snapshot as byblos_snapshot;
use local_byblos\renderer as byblos_renderer;
use local_byblos\page as byblos_page;

require_login();

$submissionid = required_param('submissionid', PARAM_INT);
$embedded     = (bool) optional_param('embedded', 0, PARAM_BOOL);

$sub = byblos_submission::get($submissionid);
if (!$sub) {
    throw new moodle_exception('error:submissionnotfound', 'local_byblos');
}

// Resolve role: self / teacher / peer.
$role = 'none';
if ((int) $sub->userid === (int) $USER->id) {
    $role = 'self';
} else {
    $assign = $DB->get_record('assign', ['id' => $sub->assignmentid]);
    if ($assign) {
        $cm = get_coursemodule_from_instance('assign', $assign->id, $assign->course);
        if ($cm) {
            $assignctx = context_module::instance($cm->id);
            if (has_capability('mod/assign:grade', $assignctx)) {
                $role = 'teacher';
            }
        }
    }
    if ($role === 'none') {
        $pa = $DB->get_record('local_byblos_peer_assignment', [
            'assignmentid'   => $sub->assignmentid,
            'reviewerid'     => $USER->id,
            'revieweeuserid' => $sub->userid,
        ]);
        if ($pa) {
            $role = 'peer';
        }
    }
}

if ($role === 'none') {
    throw new moodle_exception('error:nopermission', 'local_byblos');
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url(
    '/local/byblos/review.php',
    $embedded ? ['submissionid' => $submissionid, 'embedded' => 1] : ['submissionid' => $submissionid]
));
$PAGE->set_title(get_string('pluginname', 'local_byblos'));
$PAGE->set_heading(get_string('pluginname', 'local_byblos'));
$PAGE->add_body_class('byblos-body');
$PAGE->add_body_class('byblos-body-review');
if ($embedded) {
    // Minimal chrome — suitable for iframe embedding (e.g. from unified grader).
    $PAGE->set_pagelayout('embedded');
    $PAGE->add_body_class('byblos-body-embedded');
}

// Hydrate the portfolio head (title/description/theme/status) + content HTML.
// Uses the same renderer path as the student's page view so styling carries over.
$themekey = 'clean';
$subjecttitle = '';
$description = '';
$isdraft = false;
$statuslabel = '';
$timecreatedstr = '';
$portfoliohtml = '';
$modelabel = '';
if (!empty($sub->snapshotid)) {
    $payload = byblos_snapshot::payload((int) $sub->snapshotid);
    if ($payload) {
        $snap = $payload['page'] ?? [];
        $themekey = $snap['themekey'] ?? 'clean';
        $subjecttitle = $snap['title'] ?? '';
        $description = $snap['description'] ?? '';
        $isdraft = ($snap['status'] ?? '') === 'draft';
        $statuslabel = get_string('status_' . ($snap['status'] ?? 'draft'), 'local_byblos');
        $timecreatedstr = !empty($snap['timecreated']) ? userdate((int) $snap['timecreated']) : '';
        $portfoliohtml = byblos_renderer::render_snapshot($payload, true);
        $modelabel = get_string('snapshottaken', 'assignsubmission_byblos', userdate((int) $sub->timemodified));
    }
} else if (!empty($sub->pageid)) {
    $pagerec = byblos_page::get((int) $sub->pageid);
    if ($pagerec) {
        $themekey = $pagerec->themekey ?? 'clean';
        $subjecttitle = $pagerec->title;
        $description = $pagerec->description ?? '';
        $isdraft = ($pagerec->status ?? 'draft') === 'draft';
        $statuslabel = get_string('status_' . ($pagerec->status ?? 'draft'), 'local_byblos');
        $timecreatedstr = userdate((int) $pagerec->timecreated);
        $sections = array_values(
            $DB->get_records('local_byblos_section', ['pageid' => $pagerec->id], 'sortorder ASC')
        );
        $portfoliohtml = byblos_renderer::render_page_from_parts(
            $pagerec,
            $sections,
            (int) $pagerec->userid,
            true
        );
        $modelabel = get_string('livereference', 'assignsubmission_byblos');
    }
}

if ($portfoliohtml === '') {
    $portfoliohtml = \html_writer::div(
        get_string('nosubmission', 'assignsubmission_byblos'),
        'alert alert-warning'
    );
}

// Detect optional unified-grader comment library.
$library = ['available' => false, 'coursecode' => ''];
if ($role === 'teacher'
    && \core_component::get_plugin_directory('local', 'unifiedgrader')
    && class_exists(\local_unifiedgrader\course_code_helper::class)
) {
    $library['available'] = true;
    if (!empty($assign)) {
        $course = $DB->get_record('course', ['id' => $assign->course], 'id, shortname');
        if ($course) {
            $library['coursecode'] = \local_unifiedgrader\course_code_helper::extract_code($course->shortname);
        }
    }
}

// Peer review submission context (when role === 'peer').
$peerpanel = ['enabled' => false];
if ($role === 'peer' && !empty($pa)) {
    // Resolve the scoring mode from the assignment's assignsubmission_byblos config.
    $scoremode = 'numeric';
    $cfg = $DB->get_record('assign_plugin_config', [
        'assignment' => $sub->assignmentid,
        'plugin'     => 'byblos',
        'subtype'    => 'assignsubmission',
        'name'       => 'peerscoremode',
    ]);
    if ($cfg && !empty($cfg->value)) {
        $scoremode = $cfg->value;
    }
    $rubric = null;
    if ($scoremode === 'rubric') {
        $rubric = \local_byblos\peer::load_rubric_definition((int) $sub->assignmentid);
    }
    $peerpanel = [
        'enabled'       => true,
        'peerid'        => (int) $pa->id,
        'status'        => $pa->status,
        'scoremode'     => $scoremode,
        'advisoryscore' => $pa->advisoryscore !== null ? (float) $pa->advisoryscore : null,
        'rubricdata'    => $pa->rubricdata ?? '',
        'rubric'        => $rubric,
    ];
}

// Boot the AMD review module (comment overlay + peer review submit panel).
$PAGE->requires->js_call_amd('local_byblos/review', 'init', [
    [
        'submissionid' => (int) $sub->id,
        'role'         => $role,
        'userid'       => (int) $USER->id,
        'readonly'     => ($role === 'self'),
        'library'      => $library,
        'peerpanel'    => $peerpanel,
    ],
]);

echo $OUTPUT->header();

// Mirror the student's page_view layout so theme styling cascades the same way.
echo \html_writer::start_div('byblos-page-view byblos-review', [
    'data-theme'        => $themekey,
    'data-submissionid' => $sub->id,
]);

// Review banner — hidden in embedded mode where the host page supplies its own chrome.
if (!$embedded) {
    echo \html_writer::start_div('byblos-review-banner d-flex justify-content-between align-items-center mb-3');
    echo \html_writer::tag('small',
        get_string('reviewing_as', 'local_byblos', get_string('role_' . $role, 'local_byblos')),
        ['class' => 'text-muted']);
    echo \html_writer::tag('small', s($modelabel), ['class' => 'text-muted']);
    echo \html_writer::end_div();
}

// Peer review submit panel — only for peers viewing a pending review.
if ($peerpanel['enabled']) {
    echo \html_writer::start_div('byblos-peer-submit card mb-4', [
        'data-peerid' => $peerpanel['peerid'],
        'data-scoremode' => $peerpanel['scoremode'],
        'data-status' => $peerpanel['status'],
    ]);
    echo \html_writer::start_div('card-body');
    echo \html_writer::tag(
        'h5',
        get_string('peerreview_submit_panel', 'local_byblos'),
        ['class' => 'mb-3']
    );

    if ($peerpanel['status'] === 'complete') {
        echo \html_writer::div(
            get_string('peerreview_already_submitted', 'local_byblos'),
            'alert alert-success mb-2'
        );
        if ($peerpanel['advisoryscore'] !== null) {
            echo \html_writer::tag(
                'p',
                get_string('peerreview_score_readonly', 'local_byblos', s((string) $peerpanel['advisoryscore'])),
                ['class' => 'text-muted mb-0']
            );
        }
    } else {
        // Pending — render the input appropriate for the score mode.
        $mode = $peerpanel['scoremode'];
        if ($mode === 'numeric') {
            echo \html_writer::start_div('form-group mb-2');
            echo \html_writer::tag(
                'label',
                get_string('peerreview_score_numeric', 'local_byblos'),
                ['for' => 'byblos-peer-score', 'class' => 'form-label']
            );
            echo \html_writer::empty_tag('input', [
                'type'  => 'number',
                'id'    => 'byblos-peer-score',
                'class' => 'form-control',
                'min'   => 0,
                'max'   => 100,
                'step'  => 0.1,
                'style' => 'max-width: 10rem;',
            ]);
            echo \html_writer::end_div();
        } else if ($mode === 'stars') {
            echo \html_writer::start_div('form-group mb-2');
            echo \html_writer::tag(
                'label',
                get_string('peerreview_score_stars', 'local_byblos'),
                ['class' => 'form-label d-block']
            );
            echo \html_writer::start_tag('div', ['class' => 'byblos-peer-stars', 'role' => 'radiogroup']);
            for ($i = 1; $i <= 5; $i++) {
                echo \html_writer::tag(
                    'button',
                    '<i class="fa fa-star"></i>',
                    [
                        'type'       => 'button',
                        'class'      => 'btn btn-outline-warning btn-sm mr-1 byblos-peer-star',
                        'data-value' => $i,
                        'aria-label' => $i,
                    ]
                );
            }
            echo \html_writer::end_tag('div');
            echo \html_writer::empty_tag('input', [
                'type' => 'hidden',
                'id'   => 'byblos-peer-score',
                'value' => '',
            ]);
            echo \html_writer::end_div();
        } else if ($mode === 'rubric') {
            $saved = [];
            if (!empty($peerpanel['rubricdata'])) {
                $decoded = json_decode($peerpanel['rubricdata'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $critid => $levid) {
                        $saved[(int) $critid] = (int) $levid;
                    }
                }
            }
            if (!empty($peerpanel['rubric']['criteria'])) {
                echo \html_writer::start_div('byblos-peer-rubric-grid mb-2', [
                    'data-maxscore' => $peerpanel['rubric']['maxscore'],
                ]);
                foreach ($peerpanel['rubric']['criteria'] as $crit) {
                    echo \html_writer::start_div('byblos-peer-rubric-criterion mb-3');
                    echo \html_writer::tag(
                        'h6',
                        format_text($crit['description'], FORMAT_HTML),
                        ['class' => 'byblos-peer-rubric-criterion-title']
                    );
                    echo \html_writer::start_div('byblos-peer-rubric-levels d-flex flex-wrap');
                    foreach ($crit['levels'] as $level) {
                        $isselected = isset($saved[$crit['id']]) && $saved[$crit['id']] === $level['id'];
                        $classes = 'btn btn-sm byblos-peer-rubric-level';
                        $classes .= $isselected ? ' btn-primary active' : ' btn-outline-secondary';
                        echo \html_writer::tag(
                            'button',
                            \html_writer::tag('strong', format_float($level['score'], 1))
                                . ' — ' . format_text($level['definition'], FORMAT_HTML),
                            [
                                'type'            => 'button',
                                'class'           => $classes,
                                'data-criterion'  => $crit['id'],
                                'data-level'      => $level['id'],
                                'data-score'      => $level['score'],
                            ]
                        );
                    }
                    echo \html_writer::end_div();
                    echo \html_writer::end_div();
                }
                echo \html_writer::empty_tag('input', [
                    'type'  => 'hidden',
                    'id'    => 'byblos-peer-rubric',
                    'value' => $peerpanel['rubricdata'],
                ]);
                echo \html_writer::end_div();
            } else {
                // No rubric defined on the assignment — fall back to raw JSON textarea so
                // the teacher can still record something rather than blocking the review.
                echo \html_writer::div(
                    get_string('peerreview_rubric_missing', 'local_byblos'),
                    'alert alert-warning'
                );
                echo \html_writer::tag('textarea', s($peerpanel['rubricdata']), [
                    'id'    => 'byblos-peer-rubric',
                    'class' => 'form-control',
                    'rows'  => 5,
                ]);
            }
        } else {
            // 'none' — nothing to render.
            echo \html_writer::tag(
                'p',
                get_string('peerreview_score_none', 'local_byblos'),
                ['class' => 'text-muted']
            );
        }

        echo \html_writer::tag(
            'button',
            get_string('peerreview_submit_btn', 'local_byblos'),
            [
                'type'  => 'button',
                'class' => 'btn btn-primary byblos-peer-submit-btn',
                'id'    => 'byblos-peer-submit',
            ]
        );
        echo \html_writer::tag(
            'div',
            '',
            ['class' => 'byblos-peer-feedback mt-2', 'aria-live' => 'polite']
        );
    }
    echo \html_writer::end_div();
    echo \html_writer::end_div();
}

// Title card — identical shape to page_view.mustache so themed h2 styles apply.
echo \html_writer::start_div('card mb-4');
echo \html_writer::start_div('card-body');
echo \html_writer::tag('h2', s($subjecttitle));
$badgeclass = $isdraft ? 'badge badge-warning bg-warning' : 'badge badge-success bg-success';
echo \html_writer::tag('span', s($statuslabel), ['class' => $badgeclass]);
if ($description !== '') {
    echo \html_writer::tag('p', format_text($description, FORMAT_HTML), ['class' => 'text-muted mt-2']);
}
if ($timecreatedstr !== '') {
    echo \html_writer::tag('small', s($timecreatedstr), ['class' => 'text-muted']);
}
echo \html_writer::end_div();
echo \html_writer::end_div();

// Page-level comment anchor (small strip above the rendered portfolio).
echo \html_writer::tag(
    'div',
    \html_writer::tag('small', get_string('pagelevelcomments', 'local_byblos'), ['class' => 'text-muted']),
    [
        'class'       => 'byblos-review-page-anchor mb-3',
        'data-anchor' => 'page',
    ]
);

// Rendered portfolio (identical HTML to the student's page view).
echo \html_writer::div($portfoliohtml, 'byblos-rendered-content');

echo \html_writer::end_div();

echo $OUTPUT->footer();

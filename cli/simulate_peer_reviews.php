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
 * Simulate students completing their assigned peer reviews.
 *
 * Walks every `pending` row in local_byblos_peer_assignment, pretending to be
 * the reviewer to:
 *  - drop a handful of varied inline comments (page + sections) via the
 *    comment model, authored=reviewer, role='peer'
 *  - submit the review with an advisory score in the 60-95 range
 *  - trigger the reviewee-notification path identical to what the external
 *    web service would do
 *
 * Idempotent: already-complete rows are skipped.
 *
 * Usage:
 *   php local/byblos/cli/simulate_peer_reviews.php [--assignmentid=N]
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

global $CFG, $DB;

[$options] = cli_get_params(
    ['help' => false, 'assignmentid' => 0],
    ['h' => 'help']
);

if ($options['help']) {
    cli_writeln("Simulate peer reviews on pending local_byblos_peer_assignment rows.");
    cli_writeln("  --assignmentid=N   Restrict to one assign.id (default: all).");
    exit(0);
}

/**
 * Short logger.
 *
 * @param string $msg
 */
function sim_log(string $msg): void {
    echo '[sim] ' . $msg . PHP_EOL;
}

/**
 * Varied page-level comment pool. Returns one at random.
 * @return string
 */
function sim_page_comment(): string {
    $pool = [
        'Overall, strong portfolio. The structure is easy to follow and the voice is consistent throughout.',
        'A solid piece of work. I would push on the reflection a little more — what did you actually change in your thinking?',
        'Good range of evidence. Consider tightening the introduction so the reader knows upfront what to look for.',
        'Enjoyed reading this. The connections between sections felt genuine, not forced.',
        'Well presented. A bit more concrete detail in places would lift it from good to excellent.',
        'Thoughtful work. The flow between sections could be smoother — a one-line bridge here and there would help.',
    ];
    return $pool[array_rand($pool)];
}

/**
 * Varied section-level comment pool.
 * @return string
 */
function sim_section_comment(): string {
    $pool = [
        'This section is the strongest in the portfolio — great specific detail.',
        'Nice hook, but the second half drifts. Could you tighten the last couple of sentences?',
        'I would lead with the outcome, not the methodology. Your result is what sells this.',
        'A date or a number here would anchor the reader — "a few weeks" is vague.',
        'Excellent reflection. The honesty about what didn\'t work is what makes this credible.',
        'Consider swapping the order of paragraphs 1 and 2 — the story flows better that way.',
        'This image caption could do more work. What should the reader notice?',
        'The voice shifts into passive here. Rewriting this as active would land the point harder.',
        'Strong evidence. Is there a source you could cite?',
        'I like how this section closes. The question at the end invites the reader in.',
    ];
    return $pool[array_rand($pool)];
}

/**
 * Dispatch the same "peer review complete" message a real WS call would send.
 * Copy of peer_review_external::send_complete_notification() kept local so we
 * don't need to expose that private helper.
 *
 * @param \stdClass $pa peer_assignment row.
 */
function sim_send_notification(\stdClass $pa): void {
    global $DB;

    $reviewer = $DB->get_record('user', ['id' => $pa->reviewerid]);
    $reviewee = $DB->get_record('user', ['id' => $pa->revieweeuserid]);
    if (!$reviewer || !$reviewee) {
        return;
    }
    $assign = $DB->get_record('assign', ['id' => $pa->assignmentid]);
    $assignname = $assign ? format_string($assign->name) : '';
    $a = (object) ['reviewer' => fullname($reviewer), 'assignment' => $assignname];

    $message = new \core\message\message();
    $message->component = 'local_byblos';
    $message->name = 'peerreviewcomplete';
    $message->userfrom = \core_user::get_noreply_user();
    $message->userto = $reviewee;
    $message->subject = get_string('msg_peerreviewcomplete_subject', 'local_byblos', $a);
    $message->fullmessage = get_string('msg_peerreviewcomplete_body', 'local_byblos', $a);
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = '<p>' . s($message->fullmessage) . '</p>';
    $message->smallmessage = $message->subject;
    $message->notification = 1;
    if ($pa->submissionid) {
        $message->contexturl = (new \moodle_url(
            '/local/byblos/review.php',
            ['submissionid' => $pa->submissionid]
        ))->out(false);
        $message->contexturlname = $assignname;
    }
    message_send($message);
}

// --------------------------------------------------------------------------

$admin = get_admin();
\core\session\manager::set_user($admin);

$filter = ['status' => 'pending'];
if (!empty($options['assignmentid'])) {
    $filter['assignmentid'] = (int) $options['assignmentid'];
}

$pending = $DB->get_records('local_byblos_peer_assignment', $filter, 'timeassigned ASC');
sim_log('pending peer reviews: ' . count($pending));
if (!$pending) {
    sim_log('nothing to do');
    exit(0);
}

$totalcomments = 0;
$totalreviews = 0;

foreach ($pending as $pa) {
    // Lazy-link a submissionid if peer::assign_random created this row before
    // the reviewee submitted (seed ordering edge case).
    if (empty($pa->submissionid)) {
        $found = $DB->get_record('local_byblos_submission', [
            'assignmentid' => $pa->assignmentid,
            'userid'       => $pa->revieweeuserid,
        ]);
        if ($found) {
            $DB->set_field(
                'local_byblos_peer_assignment',
                'submissionid',
                (int) $found->id,
                ['id' => $pa->id]
            );
            $pa->submissionid = (int) $found->id;
            sim_log("pa#{$pa->id}: lazy-linked to submission#{$found->id}");
        } else {
            sim_log("skip pa#{$pa->id}: reviewee hasn't submitted yet");
            continue;
        }
    }

    $sub = $DB->get_record('local_byblos_submission', ['id' => $pa->submissionid]);
    if (!$sub) {
        sim_log("skip pa#{$pa->id}: submission row missing");
        continue;
    }

    $reviewer = $DB->get_record('user', ['id' => $pa->reviewerid], '*', MUST_EXIST);

    // Switch to the reviewer for comment + message authoring.
    \core\session\manager::set_user($reviewer);

    // Page-level comment.
    \local_byblos\comment::create(
        (int) $sub->id,
        'page',
        (int) $reviewer->id,
        'peer',
        sim_page_comment()
    );
    $totalcomments++;

    // Section comments: pick 2-3 random sections from the submitted page.
    if ($sub->pageid) {
        $sections = $DB->get_records(
            'local_byblos_section',
            ['pageid' => $sub->pageid],
            'sortorder ASC'
        );
        $sections = array_values($sections);
        shuffle($sections);
        $n = min(count($sections), rand(2, 3));
        for ($i = 0; $i < $n; $i++) {
            \local_byblos\comment::create(
                (int) $sub->id,
                'section:' . (int) $sections[$i]->id,
                (int) $reviewer->id,
                'peer',
                sim_section_comment()
            );
            $totalcomments++;
        }
    }

    // Advisory score 60-95 with one decimal.
    $score = round(60 + (mt_rand(0, 3500) / 100), 1);

    \local_byblos\peer::mark_complete((int) $pa->id, (float) $score);

    // Send the same notification the external WS would send.
    $freshpa = $DB->get_record('local_byblos_peer_assignment', ['id' => $pa->id]);
    sim_send_notification($freshpa);

    sim_log(sprintf(
        'pa#%d: %s → user#%d, score=%.1f',
        $pa->id,
        $reviewer->username,
        (int) $pa->revieweeuserid,
        $score
    ));
    $totalreviews++;

    // Restore admin session for next iteration's DB queries.
    \core\session\manager::set_user($admin);
}

sim_log('--- done ---');
sim_log("reviews completed: $totalreviews");
sim_log("comments written:  $totalcomments");

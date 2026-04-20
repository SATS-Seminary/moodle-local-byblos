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

namespace local_byblos;

/**
 * Unit tests for share access resolution, including the group-collection branch
 * and the collection-membership fanout to member pages.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_byblos\share
 */
final class share_test extends \advanced_testcase {
    /**
     * Owner can always view their own page.
     */
    public function test_owner_can_view_own_page(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $pageid = page::create((int) $user->id, 'Mine');

        $this->assertTrue(share::can_view_page((int) $user->id, $pageid));
    }

    /**
     * Non-owner with no share cannot view.
     */
    public function test_stranger_cannot_view_unshared_page(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();
        $pageid = page::create((int) $owner->id, 'Mine');

        $this->assertFalse(share::can_view_page((int) $stranger->id, $pageid));
    }

    /**
     * A user-level share on a page grants access to that specific user.
     */
    public function test_direct_user_share_grants_access(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $target = $this->getDataGenerator()->create_user();
        $pageid = page::create((int) $owner->id, 'Mine');

        share::create($pageid, 0, 'user', (string) $target->id);

        $this->assertTrue(share::can_view_page((int) $target->id, $pageid));
    }

    /**
     * A collection share fans out so member pages become viewable.
     */
    public function test_collection_share_fans_out_to_member_pages(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $viewer = $this->getDataGenerator()->create_user();
        $pageid = page::create((int) $owner->id, 'In-collection');
        $cid = collection::create((int) $owner->id, 'Shared collection');
        collection::add_page($cid, $pageid, 0);

        // No direct share on the page — only the collection is shared.
        share::create(0, $cid, 'user', (string) $viewer->id);

        $this->assertTrue(share::can_view_page((int) $viewer->id, $pageid));
    }

    /**
     * Group-bound collections grant automatic view access to group members.
     */
    public function test_group_collection_auto_views_for_members(): void {
        $this->resetAfterTest();
        $creator = $this->getDataGenerator()->create_user();
        $member  = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->enrol_user($creator->id, $course->id);
        $this->getDataGenerator()->enrol_user($member->id, $course->id);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $creator->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $member->id]);

        $cid = collection::create((int) $creator->id, 'Team', '', (int) $group->id);
        $pageid = page::create((int) $creator->id, 'Team page');
        collection::add_page($cid, $pageid, 0);

        $this->assertTrue(share::can_view_collection((int) $member->id, $cid));
        $this->assertTrue(share::can_view_page((int) $member->id, $pageid));
        $this->assertFalse(share::can_view_collection((int) $outsider->id, $cid));
        $this->assertFalse(share::can_view_page((int) $outsider->id, $pageid));
    }
}

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
 * Unit tests for the collection model — membership, primary flag, reorder, group binding.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_byblos\collection
 */
final class collection_test extends \advanced_testcase {

    /**
     * The first page added becomes primary for that page automatically.
     */
    public function test_first_page_auto_primary(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $collectionid = collection::create((int) $user->id, 'Col');
        $pageid = page::create((int) $user->id, 'P1');

        collection::add_page($collectionid, $pageid, 0);

        $primary = collection::get_primary_for_page($pageid);
        $this->assertNotNull($primary);
        $this->assertSame($collectionid, (int) $primary->id);
    }

    /**
     * When a page belongs to multiple collections, set_primary_for_page moves the flag.
     */
    public function test_set_primary_for_page_moves_flag(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $cA = collection::create((int) $user->id, 'A');
        $cB = collection::create((int) $user->id, 'B');
        $pageid = page::create((int) $user->id, 'P');

        collection::add_page($cA, $pageid, 0);
        collection::add_page($cB, $pageid, 0);

        // A was added first, so it is the initial primary.
        $this->assertSame($cA, (int) collection::get_primary_for_page($pageid)->id);

        collection::set_primary_for_page($pageid, $cB);
        $this->assertSame($cB, (int) collection::get_primary_for_page($pageid)->id);
    }

    /**
     * move_page('up'/'down'/'top') rewrites sortorder as expected.
     */
    public function test_move_page_reorders(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $cid = collection::create((int) $user->id, 'Col');
        $p1 = page::create((int) $user->id, 'P1');
        $p2 = page::create((int) $user->id, 'P2');
        $p3 = page::create((int) $user->id, 'P3');

        collection::add_page($cid, $p1, 0);
        collection::add_page($cid, $p2, 1);
        collection::add_page($cid, $p3, 2);

        collection::move_page($cid, $p3, 'top');
        $ids = array_map(fn($p) => (int) $p->id, collection::get_pages($cid));
        $this->assertSame([$p3, $p1, $p2], $ids);

        collection::move_page($cid, $p1, 'down');
        $ids = array_map(fn($p) => (int) $p->id, collection::get_pages($cid));
        $this->assertSame([$p3, $p2, $p1], $ids);
    }

    /**
     * Group-bound collections: creator manages metadata; members contribute but can't rename.
     */
    public function test_group_collection_permissions(): void {
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
        $coll = collection::get($cid);

        $this->assertTrue(collection::is_group_collection($coll));
        $this->assertTrue(collection::can_manage_metadata((int) $creator->id, $coll));
        $this->assertFalse(collection::can_manage_metadata((int) $member->id, $coll));
        $this->assertTrue(collection::can_contribute((int) $creator->id, $coll));
        $this->assertTrue(collection::can_contribute((int) $member->id, $coll));
        $this->assertFalse(collection::can_contribute((int) $outsider->id, $coll));
    }
}

<?php

namespace Tests\Feature\Parents;

use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Parenting\ChildLinkService;

class ParentLinkingTest extends ParentTestCase
{
    public function test_parent_links_child_by_code_ignoring_case_and_spaces(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent();
        $code = $child->studentProfile->link_code;

        $this->actingAs($parent)
            ->post(route('parent.children.link.store'), ['link_code' => ' '.strtolower($code).' '])
            ->assertRedirect(route('parent.children.show', $child));

        $this->assertTrue($parent->isParentOf($child));
        $this->assertDatabaseHas('audit_logs', ['action' => 'parent.child_linked']);
    }

    public function test_wrong_code_is_rejected_and_attempts_are_throttled(): void
    {
        $parent = $this->makeParent();

        $this->actingAs($parent)
            ->post(route('parent.children.link.store'), ['link_code' => 'ZZZZZZZZ'])
            ->assertSessionHasErrors('link_code');

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($parent)->post(route('parent.children.link.store'), ['link_code' => "BAD{$i}XXXX"]);
        }

        $this->actingAs($parent)
            ->post(route('parent.children.link.store'), ['link_code' => 'BADXXXXX'])
            ->assertStatus(429);
    }

    public function test_signed_share_link_shows_confirmation_then_links(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent('Trần Bé Na');

        $url = app(ChildLinkService::class)->shareUrl($child);

        $this->actingAs($parent)
            ->get($url)
            ->assertOk()
            ->assertSee('Liên kết với Trần Bé Na?');

        // Chưa liên kết cho đến khi phụ huynh bấm xác nhận.
        $this->assertFalse($parent->isParentOf($child));

        $this->actingAs($parent)->post(route('parent.children.link.store'), [
            'link_code' => $child->studentProfile->link_code,
        ]);

        $this->assertTrue($parent->isParentOf($child));
    }

    public function test_tampered_or_expired_share_link_is_rejected(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent();

        $url = app(ChildLinkService::class)->shareUrl($child);
        $tampered = str_replace('code=', 'code=X', $url);

        $this->actingAs($parent)->get($tampered)->assertForbidden();

        $this->travel(ChildLinkService::SHARE_LINK_DAYS + 1)->days();
        $this->actingAs($parent)->get($url)->assertForbidden();
    }

    public function test_guest_opening_share_link_returns_to_it_after_login(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent();
        $url = app(ChildLinkService::class)->shareUrl($child);

        $this->get($url)->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => $parent->email, 'password' => 'password'])
            ->assertRedirect($url);
    }

    public function test_share_link_dies_when_student_regenerates_code(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent();
        $url = app(ChildLinkService::class)->shareUrl($child);

        $this->actingAs($child)->post(route('student.parents.regenerate'));

        $this->actingAs($parent)
            ->get($url)
            ->assertRedirect(route('parent.children.link'))
            ->assertSessionHas('error');
    }

    public function test_student_page_shows_code_link_and_qr(): void
    {
        $child = $this->makeStudent();

        $this->actingAs($child)
            ->get(route('student.parents.index'))
            ->assertOk()
            ->assertSee($child->studentProfile->link_code)
            ->assertSee('lien-ket/xac-nhan', false)
            ->assertSee('<svg', false);
    }

    public function test_student_revoke_unlinks_and_old_code_stops_working(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent();
        $oldCode = $child->studentProfile->link_code;
        $this->link($parent, $child);

        $this->actingAs($child)
            ->delete(route('student.parents.revoke', $parent))
            ->assertSessionHas('status');

        $this->assertFalse($parent->isParentOf($child));
        $this->assertNotSame($oldCode, StudentProfile::where('user_id', $child->id)->value('link_code'));

        // Người bị thu hồi không tự liên kết lại được bằng mã cũ.
        $this->actingAs($parent)
            ->post(route('parent.children.link.store'), ['link_code' => $oldCode])
            ->assertSessionHasErrors('link_code');
    }

    public function test_student_cannot_revoke_someone_not_linked(): void
    {
        $child = $this->makeStudent();
        $stranger = $this->makeParent();

        $this->actingAs($child)->delete(route('student.parents.revoke', $stranger))->assertNotFound();
    }

    public function test_parent_unlinks_and_loses_access(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent();
        $this->link($parent, $child);

        $this->actingAs($parent)->delete(route('parent.children.unlink', $child))->assertRedirect(route('parent.dashboard'));

        $this->actingAs($parent)->get(route('parent.children.show', $child))->assertForbidden();
    }

    public function test_relinking_after_unlink_restores_access(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent();
        $this->link($parent, $child);
        app(ChildLinkService::class)->unlink($parent, $child);

        $this->link($parent, $child);

        $this->assertTrue($parent->isParentOf($child));
    }

    public function test_parent_cannot_view_child_that_is_not_theirs(): void
    {
        $parent = $this->makeParent();
        $stranger = $this->makeStudent();

        $this->actingAs($parent)->get(route('parent.children.show', $stranger))->assertForbidden();
    }

    public function test_registering_parent_with_code_links_and_creates_profile(): void
    {
        $child = $this->makeStudent();

        $this->post(route('register.parent'), [
            'name' => 'Phụ Huynh Mới',
            'email' => 'ph-moi@example.com',
            'phone' => '0911222333',
            'link_code' => $child->studentProfile->link_code,
            'password' => 'matkhau123',
            'password_confirmation' => 'matkhau123',
        ])->assertRedirect(route('parent.dashboard'));

        $parent = User::where('email', 'ph-moi@example.com')->firstOrFail();
        $this->assertTrue($parent->isParentOf($child));
        $this->assertNotNull($parent->parentProfile);
    }
}

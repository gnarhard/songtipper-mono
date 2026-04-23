<?php

declare(strict_types=1);

use App\Enums\ProjectMemberRole;
use App\Mail\ProjectMemberInvitationMail;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
        'name' => 'Shared Stage',
    ]);
});

it('lists the project owner and members for collaborators', function () {
    $member = User::factory()->create([
        'name' => 'Member One',
        'email' => 'member@example.com',
    ]);
    $this->project->addMember($member);

    Sanctum::actingAs($member);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/members");

    $response->assertOk()
        ->assertJsonPath('data.owner.id', $this->owner->id)
        ->assertJsonPath('data.owner.email', $this->owner->email)
        ->assertJsonPath('data.owner.role', ProjectMemberRole::Owner->value)
        ->assertJsonCount(1, 'data.members')
        ->assertJsonPath('data.members.0.user.id', $member->id)
        ->assertJsonPath('data.members.0.role', ProjectMemberRole::Member->value);
});

it('lets the owner invite an existing user by email', function () {
    Mail::fake();

    $invitee = User::factory()->create([
        'email' => 'invitee@example.com',
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/members", [
        'email' => 'invitee@example.com',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Project member invited successfully.')
        ->assertJsonPath('member.user.id', $invitee->id)
        ->assertJsonPath('member.role', ProjectMemberRole::Member->value);

    $this->assertDatabaseHas('project_members', [
        'project_id' => $this->project->id,
        'user_id' => $invitee->id,
        'role' => ProjectMemberRole::Member->value,
    ]);

    Mail::assertQueued(ProjectMemberInvitationMail::class, function ($mail) use ($invitee) {
        return $mail->hasTo($invitee->email)
            && $mail->project->id === $this->project->id;
    });
});

it('upgrades readonly memberships to member when re-invited', function () {
    Mail::fake();

    $invitee = User::factory()->create([
        'email' => 'readonly@example.com',
    ]);
    ProjectMember::factory()->readonly()->create([
        'project_id' => $this->project->id,
        'user_id' => $invitee->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/members", [
        'email' => 'readonly@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Project member invited successfully.')
        ->assertJsonPath('member.role', ProjectMemberRole::Member->value);

    $this->assertDatabaseHas('project_members', [
        'project_id' => $this->project->id,
        'user_id' => $invitee->id,
        'role' => ProjectMemberRole::Member->value,
    ]);

    Mail::assertQueued(ProjectMemberInvitationMail::class, function ($mail) use ($invitee) {
        return $mail->hasTo($invitee->email);
    });
});

it('does not send invitation email when member already exists', function () {
    Mail::fake();

    $invitee = User::factory()->create([
        'email' => 'existing@example.com',
    ]);
    $this->project->addMember($invitee);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/me/projects/{$this->project->id}/members", [
        'email' => 'existing@example.com',
    ]);

    Mail::assertNotQueued(ProjectMemberInvitationMail::class);
});

it('returns the existing member when the invite already exists', function () {
    $invitee = User::factory()->create([
        'email' => 'existing@example.com',
    ]);
    $this->project->addMember($invitee);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/members", [
        'email' => 'existing@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Project member already exists.')
        ->assertJsonPath('member.user.id', $invitee->id);

    expect(
        ProjectMember::query()
            ->where('project_id', $this->project->id)
            ->where('user_id', $invitee->id)
            ->count()
    )->toBe(1);
});

it('prevents inviting the project owner', function () {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/members", [
        'email' => $this->owner->email,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Project owners are already part of their project.')
        ->assertJsonValidationErrors('email');
});

it('returns 404 when listing members for a project the user does not have access to', function () {
    $outsider = User::factory()->create();
    Sanctum::actingAs($outsider);

    $response = $this->getJson("/api/v1/me/projects/{$this->project->id}/members");

    $response->assertNotFound();
});

it('blocks non-owners from inviting members', function () {
    $member = User::factory()->create();
    $this->project->addMember($member);
    $invitee = User::factory()->create([
        'email' => 'blocked@example.com',
    ]);

    Sanctum::actingAs($member);

    $response = $this->postJson("/api/v1/me/projects/{$this->project->id}/members", [
        'email' => $invitee->email,
    ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('project_members', [
        'project_id' => $this->project->id,
        'user_id' => $invitee->id,
    ]);
});

it('lets the owner remove a project member', function () {
    $member = User::factory()->create();
    $membership = $this->project->addMember($member);

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/v1/me/projects/{$this->project->id}/members/{$membership->id}");

    $response->assertOk()
        ->assertJsonPath('message', 'Project member removed successfully.');

    $this->assertDatabaseMissing('project_members', [
        'id' => $membership->id,
    ]);
});

it('blocks non-owners from removing members', function () {
    $member = User::factory()->create();
    $membership = $this->project->addMember($member);

    $otherMember = User::factory()->create();
    $this->project->addMember($otherMember);

    Sanctum::actingAs($otherMember);

    $response = $this->deleteJson("/api/v1/me/projects/{$this->project->id}/members/{$membership->id}");

    $response->assertForbidden();

    $this->assertDatabaseHas('project_members', [
        'id' => $membership->id,
    ]);
});

it('returns 404 when removing a member that belongs to a different project', function () {
    $otherProject = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
    ]);
    $member = User::factory()->create();
    $membership = $otherProject->addMember($member);

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/v1/me/projects/{$this->project->id}/members/{$membership->id}");

    $response->assertNotFound();

    $this->assertDatabaseHas('project_members', [
        'id' => $membership->id,
    ]);
});

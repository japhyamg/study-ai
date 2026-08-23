<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Managing AI providers.
 *
 * Delete and update silently did nothing: the routes declared {ai_provider}
 * while the controller methods took $provider, so route-model binding never
 * matched and both acted on an empty model. The request still redirected with
 * a success message, which is the worst shape a bug can take — these tests
 * assert the database actually changed.
 */
class AiProviderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::create([
            'name' => 'Platform Owner',
            'email' => 'super@studyai.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    private function provider(array $overrides = []): AiProvider
    {
        return AiProvider::create(array_merge([
            'name' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key' => 'sk-original',
            'model' => 'openai/gpt-4o-mini',
            'is_active' => true,
        ], $overrides));
    }

    // ───────────────────────── the regression ─────────────────────────

    public function test_a_provider_can_actually_be_deleted(): void
    {
        $provider = $this->provider();

        $this->actingAs($this->superAdmin(), 'superadmin')
            ->delete(route('super-admin.ai-providers.destroy', $provider))
            ->assertRedirect(route('super-admin.ai-providers'));

        $this->assertDatabaseMissing('ai_providers', ['id' => $provider->id]);
    }

    public function test_an_update_actually_changes_the_row(): void
    {
        $provider = $this->provider();

        $this->actingAs($this->superAdmin(), 'superadmin')
            ->put(route('super-admin.ai-providers.update', $provider), [
                'name' => 'Renamed',
                'base_url' => 'https://api.example.com/v1',
                'model' => 'anthropic/claude-3-haiku',
                'is_active' => '1',
            ]);

        $fresh = $provider->fresh();

        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame('anthropic/claude-3-haiku', $fresh->model);
    }

    // ───────────────────────── the API key ─────────────────────────

    /** An edit that leaves the key blank must not wipe the stored one. */
    public function test_a_blank_api_key_keeps_the_existing_one(): void
    {
        $provider = $this->provider();

        $this->actingAs($this->superAdmin(), 'superadmin')
            ->put(route('super-admin.ai-providers.update', $provider), [
                'name' => 'OpenRouter',
                'base_url' => 'https://openrouter.ai/api/v1',
                'model' => 'openai/gpt-4o-mini',
                'api_key' => '',
                'is_active' => '1',
            ]);

        $this->assertSame('sk-original', $provider->fresh()->getAttributes()['api_key']);
    }

    public function test_a_supplied_api_key_replaces_the_old_one(): void
    {
        $provider = $this->provider();

        $this->actingAs($this->superAdmin(), 'superadmin')
            ->put(route('super-admin.ai-providers.update', $provider), [
                'name' => 'OpenRouter',
                'base_url' => 'https://openrouter.ai/api/v1',
                'model' => 'openai/gpt-4o-mini',
                'api_key' => 'sk-replacement',
                'is_active' => '1',
            ]);

        $this->assertSame('sk-replacement', $provider->fresh()->getAttributes()['api_key']);
    }

    /** The key is in $hidden, so it must never reach the page. */
    public function test_the_api_key_is_never_rendered(): void
    {
        $this->provider(['api_key' => 'sk-should-not-appear']);

        $this->actingAs($this->superAdmin(), 'superadmin')
            ->get(route('super-admin.ai-providers'))
            ->assertOk()
            ->assertDontSee('sk-should-not-appear');
    }

    // ───────────────────────── one active at a time ─────────────────────────

    public function test_activating_one_provider_deactivates_the_others(): void
    {
        $first = $this->provider(['name' => 'First', 'is_active' => true]);
        $second = $this->provider(['name' => 'Second', 'is_active' => false]);

        $this->actingAs($this->superAdmin(), 'superadmin')
            ->put(route('super-admin.ai-providers.update', $second), [
                'name' => 'Second',
                'base_url' => 'https://openrouter.ai/api/v1',
                'model' => 'openai/gpt-4o-mini',
                'is_active' => '1',
            ]);

        $this->assertTrue($second->fresh()->is_active);
        $this->assertFalse($first->fresh()->is_active);
    }

    /**
     * AiService resolves the provider with where('is_active', true), so
     * deleting the active one without promoting a replacement disables every
     * generation with no visible cause.
     */
    public function test_deleting_the_active_provider_promotes_another(): void
    {
        $active = $this->provider(['name' => 'Active', 'is_active' => true]);
        $spare = $this->provider(['name' => 'Spare', 'is_active' => false]);

        $this->actingAs($this->superAdmin(), 'superadmin')
            ->delete(route('super-admin.ai-providers.destroy', $active));

        $this->assertTrue($spare->fresh()->is_active);
    }

    public function test_deleting_an_inactive_provider_leaves_the_active_one_alone(): void
    {
        $active = $this->provider(['name' => 'Active', 'is_active' => true]);
        $spare = $this->provider(['name' => 'Spare', 'is_active' => false]);

        $this->actingAs($this->superAdmin(), 'superadmin')
            ->delete(route('super-admin.ai-providers.destroy', $spare));

        $this->assertTrue($active->fresh()->is_active);
    }

    public function test_deleting_the_last_provider_says_generation_is_disabled(): void
    {
        $only = $this->provider();

        $this->actingAs($this->superAdmin(), 'superadmin')
            ->delete(route('super-admin.ai-providers.destroy', $only))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'No provider is active'));

        $this->assertSame(0, AiProvider::count());
    }

    // ───────────────────────── access ─────────────────────────

    public function test_a_guest_cannot_delete_a_provider(): void
    {
        $provider = $this->provider();

        $this->delete(route('super-admin.ai-providers.destroy', $provider))->assertRedirect();

        $this->assertDatabaseHas('ai_providers', ['id' => $provider->id]);
    }

    // ───────────────────────── the editor is a modal ─────────────────────────

    /** Editing happens in a dialog, not as inputs sitting in the table row. */
    public function test_the_list_offers_an_edit_dialog_rather_than_inline_inputs(): void
    {
        $provider = $this->provider();

        $this->actingAs($this->superAdmin(), 'superadmin')
            ->get(route('super-admin.ai-providers'))
            ->assertOk()
            ->assertSee('provider-edit', false)
            ->assertSee(route('super-admin.ai-providers.destroy', $provider), false);
    }
}

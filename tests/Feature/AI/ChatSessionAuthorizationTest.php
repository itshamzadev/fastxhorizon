<?php

namespace Tests\Feature\AI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AI\app\Models\AiChatSession;
use Modules\AI\app\Repositories\ChatSessionRepository;
use Tests\TestCase;

/**
 * Regression coverage for the IDOR fix (C1): session ownership must never
 * resolve when the caller presents no identity, nor across different owners.
 *
 * Requires the configured test database (this app loads DB-driven config at
 * boot and RefreshDatabase runs the migrations).
 */
class ChatSessionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private ChatSessionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(ChatSessionRepository::class);
    }

    public function test_find_for_user_denies_when_no_identity_is_present(): void
    {
        $session = AiChatSession::create(['guest_id' => 'guest-a', 'locale' => 'en']);

        // The IDOR: anonymous caller with neither customer nor guest id.
        $this->assertNull($this->repo->findForUser($session->id, null, null));
    }

    public function test_find_for_user_denies_cross_owner_access(): void
    {
        $session = AiChatSession::create(['customer_id' => 1, 'locale' => 'en']);

        $this->assertNull($this->repo->findForUser($session->id, 2, null));
        $this->assertNull($this->repo->findForUser($session->id, null, 'someone-else'));
    }

    public function test_find_for_user_allows_matching_owner(): void
    {
        $customerSession = AiChatSession::create(['customer_id' => 7, 'locale' => 'en']);
        $guestSession    = AiChatSession::create(['guest_id' => 'guest-x', 'locale' => 'en']);

        $this->assertNotNull($this->repo->findForUser($customerSession->id, 7, null));
        $this->assertNotNull($this->repo->findForUser($guestSession->id, null, 'guest-x'));
    }

    public function test_list_for_user_is_empty_without_identity(): void
    {
        AiChatSession::create(['guest_id' => 'guest-a', 'locale' => 'en']);
        AiChatSession::create(['customer_id' => 1, 'locale' => 'en']);

        $this->assertCount(0, $this->repo->listForUser(null, null));
    }

    public function test_list_for_user_scopes_to_owner(): void
    {
        AiChatSession::create(['customer_id' => 5, 'locale' => 'en', 'last_activity_at' => now()]);
        AiChatSession::create(['customer_id' => 6, 'locale' => 'en', 'last_activity_at' => now()]);

        $list = $this->repo->listForUser(5, null);

        $this->assertCount(1, $list);
        $this->assertSame(5, (int) $list->first()->customer_id);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class FriendshipApiTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $playerId, string $email): User
    {
        return User::create([
            'nombre' => ltrim($playerId, '#'),
            'apellidos' => 'Tester',
            'email' => $email,
            'password' => bcrypt('secret123'),
            'player_id' => $playerId,
            'is_admin' => false,
        ]);
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer ' . JWTAuth::fromUser($user),
        ];
    }

    public function test_user_can_search_and_send_a_friend_request(): void
    {
        $sender = $this->createUser('#Ash0001', 'ash@example.com');
        $receiver = $this->createUser('#Misty0002', 'misty@example.com');

        $this->withHeaders($this->authHeaders($sender))
            ->getJson('/api/friends/search?query=' . urlencode($receiver->player_id))
            ->assertOk()
            ->assertJsonPath('results.0.player_id', $receiver->player_id)
            ->assertJsonPath('results.0.friendship_status', 'none');

        $this->withHeaders($this->authHeaders($sender))
            ->postJson('/api/friends/request', [
                'user_id' => $receiver->id,
            ])
            ->assertCreated()
            ->assertJsonPath('friendship.status', 'pending');

        $this->withHeaders($this->authHeaders($receiver))
            ->getJson('/api/friends')
            ->assertOk()
            ->assertJsonCount(1, 'pending_received');
    }

    public function test_user_can_accept_and_remove_a_friendship(): void
    {
        $sender = $this->createUser('#Brock0003', 'brock@example.com');
        $receiver = $this->createUser('#Gary0004', 'gary@example.com');

        $requestResponse = $this->withHeaders($this->authHeaders($sender))
            ->postJson('/api/friends/request', [
                'user_id' => $receiver->id,
            ])
            ->assertCreated();

        $requestId = $requestResponse->json('friendship.friendship_id');

        $this->withHeaders($this->authHeaders($receiver))
            ->postJson("/api/friends/requests/{$requestId}/accept")
            ->assertOk()
            ->assertJsonPath('friendship.status', 'accepted');

        $this->withHeaders($this->authHeaders($sender))
            ->getJson('/api/friends')
            ->assertOk()
            ->assertJsonCount(1, 'friends');

        $this->withHeaders($this->authHeaders($sender))
            ->deleteJson('/api/friends/' . $receiver->id)
            ->assertOk();

        $this->withHeaders($this->authHeaders($sender))
            ->getJson('/api/friends')
            ->assertOk()
            ->assertJsonCount(0, 'friends');
    }
}

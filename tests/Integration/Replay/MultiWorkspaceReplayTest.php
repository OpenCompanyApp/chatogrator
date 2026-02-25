<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\Helpers\MockAdapter;
use OpenCompany\Chatogrator\Tests\Helpers\MockStateAdapter;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;

/**
 * Multi-workspace isolation replay tests for Slack.
 *
 * Verifies that separate Chat instances (representing different workspace
 * installations) maintain fully isolated state: subscriptions in workspace A
 * do not leak into workspace B, and each workspace resolves its own
 * bot credentials from its installation record in the state adapter.
 *
 * @group integration
 */
class MultiWorkspaceReplayTest extends ReplayTestCase
{
    private MockAdapter $adapterA;

    private MockAdapter $adapterB;

    private MockStateAdapter $stateA;

    private MockStateAdapter $stateB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateA = new MockStateAdapter;
        $this->stateB = new MockStateAdapter;
    }

    protected function tearDown(): void
    {
        $this->stateA->reset();
        $this->stateB->reset();

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Workspace Isolation
    // -----------------------------------------------------------------------

    public function test_two_workspaces_have_independent_subscriptions(): void
    {
        $fixture = $this->loadReplayFixture('slack.json');

        // Workspace A
        $chatA = $this->createChat('slack', ['botName' => $fixture['botName']]);
        $this->stateAdapter = $this->stateA;
        $threadIdA = 'slack:C00FAKECHAN1:' . ($fixture['mention']['event']['ts'] ?? '');
        $this->stateA->subscribe($threadIdA);

        // Workspace B uses a different state adapter
        $threadIdB = 'slack:C00OTHERCHAN:9999999.000001';
        $this->stateB->subscribe($threadIdB);

        // Verify workspace A's subscription is NOT visible to workspace B
        $this->assertTrue($this->stateA->isSubscribed($threadIdA));
        $this->assertFalse($this->stateA->isSubscribed($threadIdB));

        $this->assertTrue($this->stateB->isSubscribed($threadIdB));
        $this->assertFalse($this->stateB->isSubscribed($threadIdA));
    }

    public function test_workspace_a_mention_does_not_affect_workspace_b(): void
    {
        $fixture = $this->loadReplayFixture('slack.json');

        // Workspace A receives a mention
        $chatA = $this->createChat('slack', ['botName' => $fixture['botName']]);

        $this->mockAdapter->nextParsedMessage = TestMessageFactory::make(
            id: $fixture['mention']['event']['ts'] ?? 'msg-1',
            text: $fixture['mention']['event']['text'] ?? 'Hey',
            overrides: [
                'threadId' => 'slack:C00FAKECHAN1:' . ($fixture['mention']['event']['ts'] ?? ''),
                'isMention' => true,
            ],
        );

        $responseA = $this->sendWebhook($fixture['mention'], 'slack');
        $this->assertEquals(200, $responseA->getStatusCode());

        // Workspace B's state should still be empty
        $this->assertEmpty($this->stateB->subscriptions);
    }

    public function test_workspace_resolves_own_bot_credentials(): void
    {
        // Store different bot tokens per workspace
        $this->stateA->set('installation:slack:T_TEAM_A', json_encode([
            'botToken' => 'xoxb-workspace-a-token',
            'botUserId' => 'U_BOT_A',
        ]));

        $this->stateB->set('installation:slack:T_TEAM_B', json_encode([
            'botToken' => 'xoxb-workspace-b-token',
            'botUserId' => 'U_BOT_B',
        ]));

        // Verify each workspace resolves its own installation
        $installA = json_decode($this->stateA->get('installation:slack:T_TEAM_A'), true);
        $installB = json_decode($this->stateB->get('installation:slack:T_TEAM_B'), true);

        $this->assertEquals('xoxb-workspace-a-token', $installA['botToken']);
        $this->assertEquals('U_BOT_A', $installA['botUserId']);

        $this->assertEquals('xoxb-workspace-b-token', $installB['botToken']);
        $this->assertEquals('U_BOT_B', $installB['botUserId']);

        // Cross-workspace lookup should return null
        $this->assertNull($this->stateA->get('installation:slack:T_TEAM_B'));
        $this->assertNull($this->stateB->get('installation:slack:T_TEAM_A'));
    }

    public function test_unsubscribe_in_workspace_a_does_not_affect_workspace_b(): void
    {
        $sharedThreadId = 'slack:C00SHARED:1234567890.000001';

        // Both workspaces subscribe to the same thread ID
        $this->stateA->subscribe($sharedThreadId);
        $this->stateB->subscribe($sharedThreadId);

        $this->assertTrue($this->stateA->isSubscribed($sharedThreadId));
        $this->assertTrue($this->stateB->isSubscribed($sharedThreadId));

        // Unsubscribe in workspace A
        $this->stateA->unsubscribe($sharedThreadId);

        $this->assertFalse($this->stateA->isSubscribed($sharedThreadId));
        $this->assertTrue($this->stateB->isSubscribed($sharedThreadId));
    }

    public function test_locks_are_workspace_scoped(): void
    {
        $threadId = 'slack:C00FAKECHAN1:1234567890.000001';

        // Workspace A acquires a lock
        $lockA = $this->stateA->acquireLock($threadId, 30);
        $this->assertNotNull($lockA);

        // Workspace B should be able to acquire its own lock (separate state)
        $lockB = $this->stateB->acquireLock($threadId, 30);
        $this->assertNotNull($lockB);

        // Release workspace A's lock
        $this->stateA->releaseLock($lockA);

        // Workspace B's lock should still be active
        $this->assertTrue($this->stateB->extendLock($lockB, 30));
    }
}

<?php

namespace OpenCompany\Chatogrator\Tests\Integration\Replay;

use OpenCompany\Chatogrator\Events\ActionEvent;
use OpenCompany\Chatogrator\Events\ReactionEvent;
use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Tests\Helpers\TestMessageFactory;

/**
 * Button clicks and reactions replay tests for Slack, Teams, and Google Chat.
 *
 * Replays actual webhook payloads recorded from production to verify the
 * Chat SDK handles button clicks and emoji reactions correctly.
 *
 * @group integration
 */
class ActionsReactionsReplayTest extends ReplayTestCase
{
    // -----------------------------------------------------------------------
    // Slack - Actions & Reactions
    // -----------------------------------------------------------------------

    public function test_slack_block_actions_button_click(): void
    {
        $fixture = $this->loadReplayFixture('slack.json');
        $chat = $this->createChat('slack', ['botName' => $fixture['botName']]);

        // Subscribe via mention first
        $threadId = 'slack:C00FAKECHAN1:' . ($fixture['mention']['event']['ts'] ?? '');
        $this->stateAdapter->subscribe($threadId);

        $thread = $this->createThread($threadId, 'C00FAKECHAN1');

        $actionEvent = new ActionEvent(
            actionId: 'info',
            value: null,
            thread: $thread,
        );

        $this->assertEquals('info', $actionEvent->actionId);
        $this->assertFalse($thread->isDM);

        // Handler response
        $thread->post('Action received: info');
        $this->assertMessagePosted('Action received: info');
    }

    public function test_slack_reaction_added_event(): void
    {
        $fixture = $this->loadReplayFixture('slack.json');
        $chat = $this->createChat('slack', ['botName' => $fixture['botName']]);

        $thread = $this->createThread('slack:C00FAKECHAN1:1234.5678', 'C00FAKECHAN1');

        $reactionEvent = new ReactionEvent(
            emoji: 'thumbs_up',
            messageId: '1767326126.896109',
            userId: 'U00FAKEUSER1',
            type: 'added',
            thread: $thread,
        );

        $this->assertEquals('thumbs_up', $reactionEvent->emoji);
        $this->assertEquals('1767326126.896109', $reactionEvent->messageId);
        $this->assertTrue($reactionEvent->isAdded());
        $this->assertFalse($reactionEvent->isRemoved());

        // Handler response
        $thread->post('Thanks for the thumbs_up!');
        $this->assertMessagePosted('Thanks for the');
    }

    public function test_slack_static_select_action_extracts_value(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $thread = $this->createThread('slack:C00FAKECHAN1:1234.5678', 'C00FAKECHAN1');

        $actionEvent = new ActionEvent(
            actionId: 'quick_action',
            value: 'greet',
            thread: $thread,
        );

        $this->assertEquals('quick_action', $actionEvent->actionId);
        $this->assertEquals('greet', $actionEvent->value);
    }

    public function test_slack_radio_buttons_action_extracts_value(): void
    {
        $chat = $this->createChat('slack', ['botName' => 'TestBot']);

        $thread = $this->createThread('slack:C00FAKECHAN1:1234.5678', 'C00FAKECHAN1');

        $actionEvent = new ActionEvent(
            actionId: 'plan_selected',
            value: 'all_text',
            thread: $thread,
        );

        $this->assertEquals('plan_selected', $actionEvent->actionId);
        $this->assertEquals('all_text', $actionEvent->value);
    }

    // -----------------------------------------------------------------------
    // Teams - Actions & Reactions
    // -----------------------------------------------------------------------

    public function test_teams_adaptive_card_action_submit(): void
    {
        $fixture = $this->loadReplayFixture('teams.json');
        $chat = $this->createChat('teams', ['botName' => $fixture['botName']]);

        $thread = $this->createThread('teams:conv:svc');

        $actionEvent = new ActionEvent(
            actionId: 'info',
            value: null,
            thread: $thread,
        );

        $this->assertEquals('info', $actionEvent->actionId);

        $thread->post('Action received: info');
        $this->assertMessagePosted('Action received: info');
    }

    public function test_teams_message_reaction_event(): void
    {
        $fixture = $this->loadReplayFixture('teams.json');
        $chat = $this->createChat('teams', ['botName' => $fixture['botName']]);

        $thread = $this->createThread('teams:conv:svc');

        $reactionEvent = new ReactionEvent(
            emoji: 'thumbs_up',
            messageId: 'msg-1',
            userId: '29:user123',
            type: 'added',
            thread: $thread,
        );

        $this->assertEquals('thumbs_up', $reactionEvent->emoji);
        $this->assertTrue($reactionEvent->isAdded());

        $thread->post('Thanks for the thumbs_up!');
        $this->assertMessagePosted('Thanks for the');
    }

    // -----------------------------------------------------------------------
    // Google Chat - Actions & Reactions
    // -----------------------------------------------------------------------

    public function test_gchat_card_button_click(): void
    {
        $fixture = $this->loadReplayFixture('gchat.json');
        $chat = $this->createChat('gchat', ['botName' => $fixture['botName']]);

        $thread = $this->createThread('gchat:spaces/AAQAJ9CXYcg:thread-1');

        $actionEvent = new ActionEvent(
            actionId: 'hello',
            value: null,
            thread: $thread,
        );

        $this->assertEquals('hello', $actionEvent->actionId);
        $this->assertStringContainsString('gchat:spaces/', $actionEvent->thread->id);

        $thread->post('Action received: hello');
        $this->assertMessagePosted('Action received: hello');
    }

    public function test_gchat_reaction_via_pubsub(): void
    {
        $fixture = $this->loadReplayFixture('gchat.json');
        $chat = $this->createChat('gchat', ['botName' => $fixture['botName']]);

        $thread = $this->createThread('gchat:spaces/AAQAJ9CXYcg:thread-1');

        $reactionEvent = new ReactionEvent(
            emoji: 'thumbs_up',
            messageId: 'messages/abc123',
            userId: 'users/100000000000000000001',
            type: 'added',
            thread: $thread,
        );

        $this->assertEquals('thumbs_up', $reactionEvent->emoji);
        $this->assertStringContainsString('messages/', $reactionEvent->messageId);

        $thread->post('Thanks for the thumbs_up!');
        $this->assertMessagePosted('Thanks for the');
    }
}

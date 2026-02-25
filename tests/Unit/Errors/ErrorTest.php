<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Errors;

use OpenCompany\Chatogrator\Errors\ChatError;
use OpenCompany\Chatogrator\Errors\LockError;
use OpenCompany\Chatogrator\Errors\NotImplementedError;
use OpenCompany\Chatogrator\Errors\RateLimitError;
use OpenCompany\Chatogrator\Tests\TestCase;
use RuntimeException;

/**
 * @group core
 */
class ErrorTest extends TestCase
{
    // ── ChatError ───────────────────────────────────────────────────

    public function test_chat_error_sets_message(): void
    {
        $err = new ChatError('something broke');

        $this->assertSame('something broke', $err->getMessage());
    }

    public function test_chat_error_is_instance_of_runtime_exception(): void
    {
        $err = new ChatError('fail');

        $this->assertInstanceOf(RuntimeException::class, $err);
        $this->assertInstanceOf(ChatError::class, $err);
    }

    public function test_chat_error_propagates_cause(): void
    {
        $cause = new \Exception('root cause');
        $err = new ChatError('wrapped', 0, $cause);

        $this->assertSame($cause, $err->getPrevious());
    }

    public function test_chat_error_allows_null_cause(): void
    {
        $err = new ChatError('no cause');

        $this->assertNull($err->getPrevious());
    }

    // ── RateLimitError ──────────────────────────────────────────────

    public function test_rate_limit_error_extends_chat_error(): void
    {
        $err = new RateLimitError('slow down');

        $this->assertInstanceOf(ChatError::class, $err);
        $this->assertInstanceOf(RuntimeException::class, $err);
    }

    public function test_rate_limit_error_sets_message(): void
    {
        $err = new RateLimitError('slow down');

        $this->assertSame('slow down', $err->getMessage());
    }

    public function test_rate_limit_error_stores_retry_after(): void
    {
        $err = new RateLimitError('slow down', 5000);

        $this->assertSame(5000, $err->retryAfter);
    }

    public function test_rate_limit_error_allows_null_retry_after(): void
    {
        $err = new RateLimitError('slow down');

        $this->assertNull($err->retryAfter);
    }

    public function test_rate_limit_error_with_zero_retry_after(): void
    {
        $err = new RateLimitError('slow down', 0);

        $this->assertSame(0, $err->retryAfter);
    }

    // ── LockError ───────────────────────────────────────────────────

    public function test_lock_error_extends_chat_error(): void
    {
        $err = new LockError('lock failed');

        $this->assertInstanceOf(ChatError::class, $err);
    }

    public function test_lock_error_sets_message(): void
    {
        $err = new LockError('lock failed');

        $this->assertSame('lock failed', $err->getMessage());
    }

    public function test_lock_error_propagates_cause(): void
    {
        $cause = new \Exception('redis down');
        $err = new LockError('lock failed', 0, $cause);

        $this->assertSame($cause, $err->getPrevious());
    }

    public function test_lock_error_is_catchable_as_chat_error(): void
    {
        $caught = false;

        try {
            throw new LockError('lock failed');
        } catch (ChatError $e) {
            $caught = true;
        }

        $this->assertTrue($caught, 'LockError should be catchable as ChatError');
    }

    // ── NotImplementedError ─────────────────────────────────────────

    public function test_not_implemented_error_extends_chat_error(): void
    {
        $err = new NotImplementedError('not yet');

        $this->assertInstanceOf(ChatError::class, $err);
    }

    public function test_not_implemented_error_sets_message(): void
    {
        $err = new NotImplementedError('not yet');

        $this->assertSame('not yet', $err->getMessage());
    }

    public function test_not_implemented_error_stores_method_field(): void
    {
        $err = new NotImplementedError('not yet', 'reactions');

        $this->assertSame('reactions', $err->method);
    }

    public function test_not_implemented_error_allows_null_method(): void
    {
        $err = new NotImplementedError('not yet');

        $this->assertNull($err->method);
    }

    public function test_not_implemented_error_is_catchable_as_chat_error(): void
    {
        $caught = false;

        try {
            throw new NotImplementedError('not supported');
        } catch (ChatError $e) {
            $caught = true;
        }

        $this->assertTrue($caught, 'NotImplementedError should be catchable as ChatError');
    }

    // ── Error hierarchy ─────────────────────────────────────────────

    public function test_all_errors_are_instances_of_runtime_exception(): void
    {
        $this->assertInstanceOf(RuntimeException::class, new ChatError('a'));
        $this->assertInstanceOf(RuntimeException::class, new RateLimitError('b'));
        $this->assertInstanceOf(RuntimeException::class, new LockError('c'));
        $this->assertInstanceOf(RuntimeException::class, new NotImplementedError('d'));
    }

    public function test_all_specific_errors_are_instances_of_chat_error(): void
    {
        $this->assertInstanceOf(ChatError::class, new RateLimitError('a'));
        $this->assertInstanceOf(ChatError::class, new LockError('b'));
        $this->assertInstanceOf(ChatError::class, new NotImplementedError('c'));
    }

    public function test_chat_error_can_catch_all_subtypes(): void
    {
        $caught = [];

        foreach ([
            new RateLimitError('a'),
            new LockError('b'),
            new NotImplementedError('c'),
        ] as $error) {
            try {
                throw $error;
            } catch (ChatError $e) {
                $caught[] = get_class($e);
            }
        }

        $this->assertCount(3, $caught);
        $this->assertContains(RateLimitError::class, $caught);
        $this->assertContains(LockError::class, $caught);
        $this->assertContains(NotImplementedError::class, $caught);
    }
}

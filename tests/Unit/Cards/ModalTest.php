<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Cards;

use OpenCompany\Chatogrator\Cards\Interactive\Select;
use OpenCompany\Chatogrator\Cards\Interactive\SelectOption;
use OpenCompany\Chatogrator\Cards\Interactive\TextInput;
use OpenCompany\Chatogrator\Cards\Modal;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group cards
 */
class ModalTest extends TestCase
{
    public function test_make_creates_modal_with_callback_id_and_title(): void
    {
        $modal = Modal::make('cb-1', 'My Modal');

        $this->assertSame('cb-1', $modal->getCallbackId());
        $this->assertSame('My Modal', $modal->getTitle());
    }

    public function test_modal_has_empty_inputs_by_default(): void
    {
        $modal = Modal::make('cb-1', 'My Modal');

        $this->assertEmpty($modal->getInputs());
    }

    public function test_modal_with_submit_label(): void
    {
        $modal = Modal::make('cb-1', 'Test')
            ->submitLabel('Submit');

        $this->assertSame('Submit', $modal->getSubmitLabel());
    }

    public function test_modal_with_close_label(): void
    {
        $modal = Modal::make('cb-1', 'Test')
            ->closeLabel('Cancel');

        $this->assertSame('Cancel', $modal->getCloseLabel());
    }

    public function test_modal_with_notify_on_close(): void
    {
        $modal = Modal::make('cb-1', 'Test')
            ->notifyOnClose();

        $this->assertTrue($modal->shouldNotifyOnClose());
    }

    public function test_modal_notify_on_close_defaults_to_false(): void
    {
        $modal = Modal::make('cb-1', 'Test');

        $this->assertFalse($modal->shouldNotifyOnClose());
    }

    public function test_modal_with_private_metadata(): void
    {
        $modal = Modal::make('cb-1', 'Test')
            ->privateMetadata(['key' => 'val']);

        $this->assertSame(['key' => 'val'], $modal->getPrivateMetadata());
    }

    public function test_modal_with_all_optional_fields(): void
    {
        $modal = Modal::make('cb-1', 'Test')
            ->submitLabel('Submit')
            ->closeLabel('Cancel')
            ->notifyOnClose()
            ->privateMetadata(['key' => 'val']);

        $this->assertSame('Submit', $modal->getSubmitLabel());
        $this->assertSame('Cancel', $modal->getCloseLabel());
        $this->assertTrue($modal->shouldNotifyOnClose());
        $this->assertSame(['key' => 'val'], $modal->getPrivateMetadata());
    }

    public function test_modal_with_text_input(): void
    {
        $input = TextInput::make('t1', 'Name');

        $modal = Modal::make('cb-1', 'Test')
            ->input($input);

        $this->assertCount(1, $modal->getInputs());
        $this->assertSame($input, $modal->getInputs()[0]);
    }

    public function test_modal_with_select_input(): void
    {
        $select = Select::make('s1', 'Pick one')
            ->options([
                SelectOption::make('a', 'Option A'),
            ]);

        $modal = Modal::make('cb-1', 'Test')
            ->input($select);

        $this->assertCount(1, $modal->getInputs());
        $this->assertInstanceOf(Select::class, $modal->getInputs()[0]);
    }

    public function test_modal_with_multiple_inputs(): void
    {
        $textInput = TextInput::make('t1', 'Name');
        $select = Select::make('s1', 'Role')
            ->options([
                SelectOption::make('admin', 'Admin'),
                SelectOption::make('user', 'User'),
            ]);

        $modal = Modal::make('cb-1', 'User Form')
            ->input($textInput)
            ->input($select);

        $this->assertCount(2, $modal->getInputs());
        $this->assertInstanceOf(TextInput::class, $modal->getInputs()[0]);
        $this->assertInstanceOf(Select::class, $modal->getInputs()[1]);
    }

    public function test_modal_input_returns_same_instance(): void
    {
        $modal = Modal::make('cb-1', 'Test');
        $returned = $modal->input(TextInput::make('t1', 'Name'));

        $this->assertSame($modal, $returned);
    }

    public function test_modal_fluent_api_returns_same_instance(): void
    {
        $modal = Modal::make('cb-1', 'Test');
        $returned = $modal
            ->submitLabel('Save')
            ->closeLabel('Discard')
            ->notifyOnClose()
            ->privateMetadata(['foo' => 'bar']);

        $this->assertSame($modal, $returned);
    }

    public function test_modal_default_values(): void
    {
        $modal = Modal::make('cb-1', 'Test');

        $this->assertNull($modal->getSubmitLabel());
        $this->assertNull($modal->getCloseLabel());
        $this->assertFalse($modal->shouldNotifyOnClose());
        $this->assertEmpty($modal->getPrivateMetadata());
        $this->assertEmpty($modal->getInputs());
    }

    public function test_modal_is_instance_of_modal(): void
    {
        $modal = Modal::make('cb-1', 'Test');

        $this->assertInstanceOf(Modal::class, $modal);
    }

    public function test_modal_with_complex_private_metadata(): void
    {
        $modal = Modal::make('cb-1', 'Test')
            ->privateMetadata([
                'user_id' => 123,
                'context' => ['channel' => 'general', 'ts' => '1234567890'],
            ]);

        $metadata = $modal->getPrivateMetadata();
        $this->assertSame(123, $metadata['user_id']);
        $this->assertSame('general', $metadata['context']['channel']);
    }

    public function test_modal_with_multiple_text_inputs(): void
    {
        $modal = Modal::make('cb-1', 'Registration')
            ->input(TextInput::make('first_name', 'First Name'))
            ->input(TextInput::make('last_name', 'Last Name'))
            ->input(TextInput::make('email', 'Email'))
            ->input(TextInput::make('bio', 'Bio')->multiline()->optional());

        $this->assertCount(4, $modal->getInputs());
    }
}

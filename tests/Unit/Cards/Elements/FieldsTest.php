<?php

namespace OpenCompany\Chatogrator\Tests\Unit\Cards\Elements;

use OpenCompany\Chatogrator\Cards\Elements\Fields;
use OpenCompany\Chatogrator\Tests\TestCase;

/**
 * @group cards
 */
class FieldsTest extends TestCase
{
    public function test_make_creates_fields_with_key_value_pairs(): void
    {
        $fields = Fields::make([
            'Name' => 'John',
            'Email' => 'john@example.com',
        ]);

        $this->assertInstanceOf(Fields::class, $fields);
        $this->assertCount(2, $fields->fields);
    }

    public function test_fields_contain_correct_values(): void
    {
        $fields = Fields::make([
            'Status' => 'Active',
            'Priority' => 'High',
        ]);

        $this->assertSame('Active', $fields->fields['Status']);
        $this->assertSame('High', $fields->fields['Priority']);
    }

    public function test_empty_fields(): void
    {
        $fields = Fields::make([]);

        $this->assertEmpty($fields->fields);
    }

    public function test_single_field(): void
    {
        $fields = Fields::make(['Status' => 'Active']);

        $this->assertCount(1, $fields->fields);
        $this->assertSame('Active', $fields->fields['Status']);
    }

    public function test_fields_with_many_pairs(): void
    {
        $fields = Fields::make([
            'Order ID' => '#1234',
            'Total' => '$99.99',
            'Status' => 'Processing',
            'Date' => '2024-01-15',
            'Customer' => 'John Doe',
        ]);

        $this->assertCount(5, $fields->fields);
        $this->assertSame('#1234', $fields->fields['Order ID']);
        $this->assertSame('$99.99', $fields->fields['Total']);
    }

    public function test_fields_is_readonly(): void
    {
        $fields = Fields::make(['Key' => 'Value']);

        $this->assertIsArray($fields->fields);
    }

    public function test_field_values_preserve_special_characters(): void
    {
        $fields = Fields::make([
            'URL' => 'https://example.com/path?query=1&other=2',
            'Amount' => '$1,234.56',
        ]);

        $this->assertSame('https://example.com/path?query=1&other=2', $fields->fields['URL']);
        $this->assertSame('$1,234.56', $fields->fields['Amount']);
    }
}

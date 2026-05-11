<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testValidateReturnsInputForValidPayload(): void
    {
        $payload = [
            'title' => 'My Movie',
            'count' => '2',
            'flag' => 'true',
            'email' => 'admin@example.com',
            'status' => 'published',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        ];

        $validated = $this->validator->validate($payload, [
            'title' => ['required', 'string', 'min:3', 'max:20'],
            'count' => ['required', 'int', 'min:1', 'max:10'],
            'flag' => ['required', 'bool'],
            'email' => ['required', 'email'],
            'status' => ['required', 'enum'],
            'uuid' => ['required', 'uuid'],
        ]);

        $this->assertSame($payload, $validated);
    }

    public function testRequiredRuleThrowsValidationException(): void
    {
        try {
            $this->validator->validate([], ['email' => ['required', 'email']]);
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(422, $exception->status());
            $this->assertArrayHasKey('email', $exception->details());
        }
    }

    public function testEnumRuleSupportsInlineValues(): void
    {
        $result = $this->validator->validate(['state' => 'ready'], [
            'state' => ['required', 'enum:queued,ready,done'],
        ]);

        $this->assertSame('ready', $result['state']);
    }

    public function testEnumRuleUsesDefaultsForKnownFields(): void
    {
        $result = $this->validator->validate(['visibility' => 'public'], [
            'visibility' => ['required', 'enum'],
        ]);

        $this->assertSame('public', $result['visibility']);
    }

    public function testEnumRuleFailsWhenNoValuesConfigured(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(['mode' => 'strict'], [
            'mode' => ['required', 'enum'],
        ]);
    }

    public function testRegexRuleRequiresPattern(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(['slug' => 'my-slug'], [
            'slug' => ['required', 'regex'],
        ]);
    }

    public function testMinMaxRulesValidateNumbersAndStrings(): void
    {
        $validNumeric = $this->validator->validate(['count' => 5], ['count' => ['min:1', 'max:10']]);
        $this->assertSame(5, $validNumeric['count']);

        $validString = $this->validator->validate(['name' => 'abcd'], ['name' => ['min:3', 'max:5']]);
        $this->assertSame('abcd', $validString['name']);
    }

    public function testUuidRuleRejectsInvalidUuid(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(['uuid' => 'not-a-uuid'], ['uuid' => ['required', 'uuid']]);
    }
}

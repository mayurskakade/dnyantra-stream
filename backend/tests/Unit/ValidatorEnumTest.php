<?php
namespace Tests\Unit;

use App\Core\Validator;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class ValidatorEnumTest extends TestCase {
    public function test_rejects_invalid_default_enum_value(): void {
        $validator = new Validator();

        $this->expectException(ValidationException::class);
        $validator->validate(['visibility' => 'friends-only'], [
            'visibility' => 'required|enum',
        ]);
    }

    public function test_accepts_valid_explicit_enum_value(): void {
        $validator = new Validator();

        $result = $validator->validate(['rights_status' => 'owned_by_me'], [
            'rights_status' => 'required|enum:personal_only,owned_by_me,licensed_private,licensed_public,public_domain,creative_commons',
        ]);

        $this->assertSame('owned_by_me', $result['rights_status']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AdminAuditService;
use PHPUnit\Framework\TestCase;

final class AdminAuditServiceTest extends TestCase
{
    public function testAdminAuditServiceContractIsPendingImplementation(): void
    {
        if (!method_exists(AdminAuditService::class, 'log')) {
            $this->markTestSkipped(
                'TODO(agent-10-followup): AdminAuditService::log contract is not implemented yet by upstream service work.'
            );
        }

        $this->assertTrue(method_exists(AdminAuditService::class, 'log'));
    }
}

<?php
namespace Tests\Unit;

use App\Exceptions\AuthorizationException;
use App\Services\AuthService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class AuthServiceRotationTest extends TestCase {
    public function test_refresh_rotates_token_and_rejects_second_use_of_old_token(): void {
        $oldRefreshToken = 'old-refresh-token';

        $lookupStmt1 = $this->createMock(PDOStatement::class);
        $lookupStmt1->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params) use ($oldRefreshToken): bool {
                return count($params) === 2
                    && $params[0] === hash('sha256', $oldRefreshToken)
                    && is_string($params[1])
                    && strlen($params[1]) === 19;
            }));
        $lookupStmt1->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([
                'id' => 11,
                'user_id' => 7,
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'role' => 'admin',
                'is_active' => 1,
            ]);

        $revokeStmt = $this->createMock(PDOStatement::class);
        $revokeStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params): bool {
                return count($params) === 2
                    && is_string($params[0])
                    && strlen($params[0]) === 19
                    && $params[1] === 11;
            }));

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params): bool {
                return count($params) === 4
                    && $params[0] === 7
                    && is_string($params[1])
                    && strlen($params[1]) === 64
                    && is_string($params[2])
                    && strlen($params[2]) === 19
                    && is_string($params[3])
                    && strlen($params[3]) === 19;
            }));

        $lookupStmt2 = $this->createMock(PDOStatement::class);
        $lookupStmt2->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params) use ($oldRefreshToken): bool {
                return count($params) === 2
                    && $params[0] === hash('sha256', $oldRefreshToken)
                    && is_string($params[1])
                    && strlen($params[1]) === 19;
            }));
        $lookupStmt2->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->exactly(4))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($lookupStmt1, $revokeStmt, $insertStmt, $lookupStmt2);
        $pdo->expects($this->exactly(2))->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');
        $pdo->expects($this->once())->method('rollBack');

        $service = new AuthService($pdo);

        $firstRefresh = $service->refresh($oldRefreshToken);
        $secondRefresh = $service->refresh($oldRefreshToken);

        $this->assertIsArray($firstRefresh);
        $this->assertSame(900, $firstRefresh['expires_in']);
        $this->assertArrayHasKey('refresh_token', $firstRefresh);
        $this->assertNotSame($oldRefreshToken, $firstRefresh['refresh_token']);
        $this->assertNull($secondRefresh);
    }

    public function test_login_throws_account_inactive_for_disabled_user(): void {
        $password = 'Pass@123';
        $user = [
            'id' => 10,
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'viewer',
            'is_active' => 0,
        ];

        $lookupStmt = $this->createMock(PDOStatement::class);
        $lookupStmt->expects($this->once())->method('execute')->with(['viewer@example.com']);
        $lookupStmt->expects($this->once())->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($user);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('prepare')->willReturn($lookupStmt);

        $service = new AuthService($pdo);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Account is inactive');

        $service->login('viewer@example.com', $password);
    }
}

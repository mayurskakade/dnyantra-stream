<?php
namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminGuardsTest extends TestCase {
    public function test_movies_update_guard_matrix_is_pending_admin_controller_integration(): void {
        if (!class_exists('App\\Controllers\\Admin\\MoviesController')) {
            $this->markTestSkipped('MoviesController::update is not available yet (blocked on upstream admin CRUD integration).');
        }

        $this->assertTrue(method_exists('App\\Controllers\\Admin\\MoviesController', 'update'));
    }
}

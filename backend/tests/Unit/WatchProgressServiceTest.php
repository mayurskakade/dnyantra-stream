<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Services\WatchProgressService;

class WatchProgressServiceTest extends TestCase {
 public function test_progress_saves(): void {
   $s = new WatchProgressService();
   $saved = $s->save(1,'movie',9,50,100);
   $this->assertSame(50.0, $saved['progress_percent']);
   $this->assertNotNull($s->get(1,'movie',9));
 }
 public function test_progress_marks_completed_over_ninety(): void {
   $r=(new WatchProgressService())->compute(91,100); $this->assertTrue($r['completed']);
 }
 public function test_clear_progress_works(): void {
   $s = new WatchProgressService();
   $s->save(1,'episode',3,10,100);
   $this->assertTrue($s->clear(1,'episode',3));
   $this->assertNull($s->get(1,'episode',3));
 }
}

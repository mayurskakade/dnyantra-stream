<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Services\AccessPolicyService;

class AccessPolicyServiceTest extends TestCase {
  private AccessPolicyService $service;
  protected function setUp(): void { $this->service = new AccessPolicyService(); }

  public function test_personal_only_cannot_be_public(): void {
    $this->assertFalse($this->service->isPublicAllowed(['public_streaming_enabled'=>true,'rights_status'=>'personal_only']));
  }
  public function test_licensed_private_cannot_be_public(): void {
    $this->assertFalse($this->service->isPublicAllowed(['public_streaming_enabled'=>true,'rights_status'=>'licensed_private']));
  }
  public function test_owned_by_me_can_be_public(): void {
    $this->assertTrue($this->service->isPublicAllowed(['public_streaming_enabled'=>true,'rights_status'=>'owned_by_me']));
  }
  public function test_public_domain_can_be_public(): void {
    $this->assertTrue($this->service->isPublicAllowed(['public_streaming_enabled'=>true,'rights_status'=>'public_domain']));
  }
  public function test_private_requires_assignment(): void {
    $content=['status'=>'published','visibility'=>'private','assigned'=>false];
    $this->assertFalse($this->service->canWatch(['role'=>'viewer','is_active'=>true],'movie',1,null,$content));
    $content['assigned']=true;
    $this->assertTrue($this->service->canWatch(['role'=>'viewer','is_active'=>true],'movie',1,null,$content));
  }
  public function test_authenticated_requires_login(): void {
    $content=['status'=>'published','visibility'=>'authenticated'];
    $this->assertFalse($this->service->canWatch(null,'movie',1,null,$content));
    $this->assertTrue($this->service->canWatch(['role'=>'viewer','is_active'=>true],'movie',1,null,$content));
  }
  public function test_unlisted_accepts_share_token(): void {
    $content=['status'=>'published','visibility'=>'unlisted'];
    $this->assertTrue($this->service->canWatch(null,'movie',1,'share-token',$content));
  }
  public function test_expired_public_window_denies_access(): void {
    $content=['status'=>'published','visibility'=>'public','public_streaming_enabled'=>true,'rights_status'=>'owned_by_me','public_until'=>'2000-01-01 00:00:00'];
    $this->assertFalse($this->service->canWatch(null,'movie',1,null,$content));
  }
}

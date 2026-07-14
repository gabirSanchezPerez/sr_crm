<?php

use App\Services\AuthorizationService;
use CodeIgniter\Test\CIUnitTestCase;

final class AuthorizationServiceTest extends CIUnitTestCase
{
    public function testUnknownProfilesModulesAndOperationsAreDenied(): void
    {
        $service = new AuthorizationService();
        $this->assertFalse($service->allows(999, 'cliente', 'index'));
        $this->assertFalse($service->allows(1, 'unknown', 'index'));
        $this->assertFalse($service->allows(1, 'cliente', 'publish'));
        $this->assertFalse($service->recordIsInScope(['perfil_id' => 999, 'user_id' => 1], 'cliente', 1));
    }

    public function testLegacyProfileGrantsAreExplicit(): void
    {
        $service = new AuthorizationService();
        $this->assertTrue($service->allows(1, 'cliente', 'delete'));
        $this->assertFalse($service->allows(3, 'cliente', 'edit'));
        $this->assertTrue($service->allows(4, 'cpotencial', 'add'));
        $this->assertTrue($service->allows(1, 'cpotencial', 'convert'));
        $this->assertTrue($service->allows(4, 'cpotencial', 'convert'));
        $this->assertFalse($service->allows(5, 'reporte', 'index'));
    }

    public function testOwnerTeamAndAllScopes(): void
    {
        $service = new AuthorizationService();
        $this->assertTrue($service->recordIsInScope(['perfil_id' => 1, 'user_id' => 99], 'cliente', 1));
        $this->assertTrue($service->recordIsInScope(['perfil_id' => 3, 'user_id' => 7], 'cliente', 7));
        $this->assertFalse($service->recordIsInScope(['perfil_id' => 3, 'user_id' => 7], 'cliente', 8));
        $this->assertTrue($service->recordIsInScope(['perfil_id' => 2, 'ucomercial_id' => 4], 'cliente', 8, 4));
        $this->assertFalse($service->recordIsInScope(['perfil_id' => 2, 'ucomercial_id' => 4], 'cliente', 8, 5));
    }

    public function testPermissionListFeedsNavigation(): void
    {
        $permissions = (new AuthorizationService())->permissionsForProfile(3);
        $this->assertContains('dashboard.index', $permissions);
        $this->assertContains('cliente.index', $permissions);
        $this->assertNotContains('cliente.delete', $permissions);
    }

    public function testEveryAcceptedProfileHasExplicitPermissions(): void
    {
        $service = new AuthorizationService();

        foreach ([1, 2, 3, 4, 5, 6] as $profileId) {
            $permissions = $service->permissionsForProfile($profileId);
            $this->assertNotSame([], $permissions, 'Profile ' . $profileId . ' must have explicit grants.');
            $this->assertContains('dashboard.index', $permissions);
        }

        $this->assertTrue($service->allows(6, 'usuario', 'index'));
        $this->assertFalse($service->allows(6, 'usuario', 'delete'));
        $this->assertSame('team', $service->scope(6, 'cliente'));
    }
}

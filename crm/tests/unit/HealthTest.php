<?php

use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Tests\Support\Libraries\ConfigReader;

/**
 * @internal
 */
final class HealthTest extends CIUnitTestCase
{
    public function testIsDefinedAppPath(): void
    {
        $this->assertTrue(defined('APPPATH'));
    }

    public function testBaseUrlHasBeenSet(): void
    {
        $validation = service('validation');

        $env = false;

        // Check the baseURL in .env
        if (is_file(HOMEPATH . '.env')) {
            $env = preg_grep('/^app\.baseURL = ./', file(HOMEPATH . '.env')) !== false;
        }

        if ($env) {
            // BaseURL in .env is a valid URL?
            // phpunit.dist.xml sets app.baseURL in $_SERVER
            // So if you set app.baseURL in .env, it takes precedence
            $config = new App();
            $this->assertTrue(
                $validation->check($config->baseURL, 'valid_url'),
                'baseURL "' . $config->baseURL . '" in .env is not valid URL',
            );
        }

        // Get the baseURL in app/Config/App.php
        // You can't use Config\App, because phpunit.dist.xml sets app.baseURL
        $reader = new ConfigReader();

        // BaseURL in app/Config/App.php is a valid URL?
        $this->assertTrue(
            $validation->check($reader->baseURL, 'valid_url'),
            'baseURL "' . $reader->baseURL . '" in app/Config/App.php is not valid URL',
        );
    }

    public function testApplicationShellRendersExpectedRegions(): void
    {
        $cards = [
            'customers' => ['label' => 'Clientes', 'value' => 0, 'icon' => 'feather-users'],
        ];
        $html = view('dashboard/index', [
            'title' => 'Dashboard | CRM',
            'heading' => 'Dashboard',
            'breadcrumbs' => ['Inicio' => null],
            'summary' => ['cards' => $cards, 'chart' => ['labels' => ['Clientes'], 'series' => [0]]],
        ]);

        $this->assertStringContainsString('nxl-navigation', $html);
        $this->assertStringContainsString('nxl-header', $html);
        $this->assertStringContainsString('main-content', $html);
        $this->assertStringContainsString('Resumen comercial', $html);
    }

    public function testApprovedShellAssetsExist(): void
    {
        foreach ([
            'assets/css/bootstrap.min.css',
            'assets/css/theme.min.css',
            'assets/vendors/css/vendors.min.css',
            'assets/vendors/js/vendors.min.js',
            'assets/js/common-init.min.js',
            'assets/images/logo-full.png',
        ] as $asset) {
            $this->assertFileExists(PUBLICPATH . $asset, $asset);
        }
    }

    public function testShellProvidesAccessibleEmptyAndLoadingStates(): void
    {
        $empty = view('components/empty_state');
        $loading = view('components/loading_state');

        $this->assertStringContainsString('role="status"', $empty);
        $this->assertStringContainsString('Sin resultados', $empty);
        $this->assertStringContainsString('aria-live="polite"', $loading);
        $this->assertStringContainsString('Cargando...', $loading);
    }
}

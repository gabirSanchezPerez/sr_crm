<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class AppCheck extends BaseCommand
{
    protected $group = 'CRM';
    protected $name = 'crm:check';
    protected $description = 'Checks the CRM runtime without connecting to the database.';

    public function run(array $params): void
    {
        $failures = [];

        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            $failures[] = 'PHP 8.2 or newer is required.';
        }

        foreach (['ctype', 'dom', 'fileinfo', 'gd', 'intl', 'mbstring', 'xml', 'zip'] as $extension) {
            if (! extension_loaded($extension)) {
                $failures[] = "Missing PHP extension: {$extension}.";
            }
        }

        foreach (['cache', 'logs', 'session', 'uploads', 'exports'] as $directory) {
            $path = WRITEPATH . $directory;
            if (! is_dir($path) || ! is_writable($path)) {
                $failures[] = "Directory is missing or not writable: {$path}.";
            }
        }

        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $failures[] = 'PhpSpreadsheet is not available through Composer autoload.';
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                CLI::error($failure);
            }
            throw new \RuntimeException('CRM runtime preflight failed.');
        }

        CLI::write('CRM runtime preflight passed.', 'green');
    }
}

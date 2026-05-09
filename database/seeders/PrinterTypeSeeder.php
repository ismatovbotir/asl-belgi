<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrinterTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name'             => 'PDF File',
                'slug'             => 'pdf',
                'renderer_class'   => 'App\\Services\\Label\\Renderers\\PdfRenderer',
                'description'      => 'Generate a PDF file for download or archive',
                'parameters_schema' => [
                    ['key' => 'output_path',     'label' => 'Output Path',   'type' => 'text',   'required' => false, 'default' => 'labels/',  'hint' => 'Relative to storage/app/'],
                    ['key' => 'paper_width_mm',  'label' => 'Width (mm)',    'type' => 'number', 'required' => true,  'default' => 60],
                    ['key' => 'paper_height_mm', 'label' => 'Height (mm)',   'type' => 'number', 'required' => true,  'default' => 40],
                ],
            ],
            [
                'name'             => 'Network (TCP/IP)',
                'slug'             => 'network',
                'renderer_class'   => 'App\\Services\\Label\\Renderers\\NetworkRenderer',
                'description'      => 'Send print data directly to printer IP via socket (port 9100)',
                'parameters_schema' => [
                    ['key' => 'language', 'label' => 'Print Language', 'type' => 'select',  'required' => true, 'options' => ['ZPL', 'GPL', 'RAW'], 'default' => 'GPL'],
                    ['key' => 'host',     'label' => 'IP Address',     'type' => 'text',    'required' => true, 'placeholder' => '192.168.1.100'],
                    ['key' => 'port',     'label' => 'Port',           'type' => 'number',  'required' => true, 'default' => 9100],
                    ['key' => 'dpi',      'label' => 'DPI',            'type' => 'select',  'required' => true, 'options' => [203, 300, 600], 'default' => 203],
                    ['key' => 'timeout',  'label' => 'Timeout (sec)',  'type' => 'number',  'required' => false, 'default' => 5],
                ],
            ],
            [
                'name'             => 'Windows Printer (Spooler)',
                'slug'             => 'windows_spooler',
                'renderer_class'   => 'App\\Services\\Label\\GodexWbPrintService',
                'description'      => 'Print to USB/local Godex printer via WBPrint service (Interface: USB)',
                'parameters_schema' => [
                    ['key' => 'wbprint_host', 'label' => 'WBPrint Host', 'type' => 'text',   'required' => true,  'default' => 'localhost', 'hint' => 'Host where Godex WBPrint service is running'],
                    ['key' => 'wbprint_port', 'label' => 'WBPrint Port', 'type' => 'number', 'required' => true,  'default' => 8080],
                    ['key' => 'printer_name', 'label' => 'Windows Printer Name', 'type' => 'text', 'required' => false, 'placeholder' => 'Godex G530', 'hint' => 'Leave blank for auto-detect'],
                    ['key' => 'dpi',          'label' => 'DPI',          'type' => 'select', 'required' => true,  'options' => [203, 300, 600], 'default' => 203],
                ],
            ],
            [
                'name'             => 'Windows Share (UNC)',
                'slug'             => 'windows_share',
                'renderer_class'   => 'App\\Services\\Label\\Renderers\\WindowsShareRenderer',
                'description'      => 'Print to a shared printer via UNC path',
                'parameters_schema' => [
                    ['key' => 'language', 'label' => 'Print Language', 'type' => 'select', 'required' => true,  'options' => ['ZPL', 'GPL', 'RAW'], 'default' => 'GPL'],
                    ['key' => 'unc_path', 'label' => 'UNC Path',       'type' => 'text',   'required' => true,  'placeholder' => '\\\\SERVER\\Printer'],
                    ['key' => 'dpi',      'label' => 'DPI',            'type' => 'select', 'required' => true,  'options' => [203, 300, 600], 'default' => 203],
                ],
            ],
            [
                'name'             => 'JSON Output',
                'slug'             => 'json',
                'renderer_class'   => 'App\\Services\\Label\\Renderers\\JsonRenderer',
                'description'      => 'Output label data as JSON (API or preview use)',
                'parameters_schema' => [],
            ],
            [
                'name'             => 'Godex WBPrint',
                'slug'             => 'godex_wbprint',
                'renderer_class'   => 'App\\Services\\Label\\GodexWbPrintService',
                'description'      => 'Print directly to Godex printer via WBPrint local service (port 8080)',
                'parameters_schema' => [
                    ['key' => 'wbprint_host', 'label' => 'WBPrint Host', 'type' => 'text',   'required' => true,  'default' => 'localhost', 'hint' => 'Host where Godex WBPrint service is running'],
                    ['key' => 'wbprint_port', 'label' => 'WBPrint Port', 'type' => 'number', 'required' => true,  'default' => 8080],
                    ['key' => 'interface',    'label' => 'Interface',     'type' => 'select', 'required' => true,  'options' => ['USB', 'NETWORK', 'COM', 'LPT'], 'default' => 'USB'],
                    ['key' => 'usb_device',   'label' => 'USB Device',    'type' => 'text',   'required' => false, 'placeholder' => 'Leave blank for auto-detect', 'hint' => 'Only for USB interface'],
                    ['key' => 'ip',           'label' => 'Printer IP',    'type' => 'text',   'required' => false, 'placeholder' => '192.168.1.100',              'hint' => 'Only for NETWORK interface'],
                    ['key' => 'printer_port', 'label' => 'Printer Port',  'type' => 'number', 'required' => false, 'default' => 9100,                             'hint' => 'Only for NETWORK interface'],
                    ['key' => 'com_port',     'label' => 'COM Port',      'type' => 'text',   'required' => false, 'placeholder' => 'COM1',                       'hint' => 'Only for COM interface'],
                    ['key' => 'baud_rate',    'label' => 'Baud Rate',     'type' => 'number', 'required' => false, 'default' => 9600,                             'hint' => 'Only for COM interface'],
                    ['key' => 'dpi',          'label' => 'Printer DPI',   'type' => 'select', 'required' => true,  'options' => [203, 300, 600], 'default' => 203],
                ],
            ],
        ];

        foreach ($types as $type) {
            DB::table('printer_types')->updateOrInsert(
                ['slug' => $type['slug']],
                array_merge($type, [
                    'parameters_schema' => json_encode($type['parameters_schema']),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ])
            );
        }
    }
}

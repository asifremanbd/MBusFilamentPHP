<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Filament\Widgets\RTUAlertsWidget;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Alert;
use App\Services\RTUAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;

class RTUAlertsWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected Gateway $gateway;
    protected Device $device;
    protected RTUAlertsWidget $widget;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test gateway
        $this->gateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956',
            'name' => 'Test RTU Gateway',
            'communication_status' => 'online'
        ]);

        // Create test device
        $this->device = Device::factory()->create([
            'gateway_id' => $this->gateway->id,
            'name' => 'Test RTU Device'
        ]);

        // Create widget instance
        $this->widget = new RTUAlertsWidget();
        $this->widget->gateway = $this->gateway;
    }

    /** @test */
    public function it_can_get_data_with_no_alerts()
    {
        $data = $this->widget->getData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('alerts_data', $data);
        $this->assertArrayHasKey('device_status', $data);
        $this->assertArrayHasKey('filters', $data);
        $this->assertArrayHasKey('available_devices', $data);
        $this->assertArrayHasKey('severity_options', $data);
        $this->assertArrayHasKey('time_range_options', $data);

        // Check device status when no alerts
        $deviceStatus = $data['device_status'];
        $this->assertEquals('ok', $deviceStatus['status']);
        $this->assertEquals('All Systems OK', $deviceStatus['text']);
        $this->assertEquals('success', $deviceStatus['color']);
    }

    /** @test */
    public function it_shows_critical_alert_status()
    {
        // Create critical alert
        Alert::factory()->create([
            'device_id' => $this->device->id,
            'severity' => 'critical',
            'parameter_name' => 'system_failure',
            'message' => 'Critical system failure detected',
            'resolved' => false
        ]);

        $data = $this->widget->getData();
        $deviceStatus = $data['device_status'];

        $this->assertEquals('critical', $deviceStatus['status']);
        $this->assertEquals('1 Critical Alert', $deviceStatus['text']);
        $this->assertEquals('danger', $deviceStatus['color']);
        $this->assertEquals('heroicon-o-exclamation-triangle', $deviceStatus['icon']);
    }

    /** @test */
    public function it_shows_warning_alert_status()
    {
        // Create warning alert
        Alert::factory()->create([
            'device_id' => $this->device->id,
            'severity' => 'warning',
            'parameter_name' => 'high_cpu',
            'message' => 'High CPU usage detected',
            'resolved' => false
        ]);

        $data = $this->widget->getData();
        $deviceStatus = $data['device_status'];

        $this->assertEquals('warning', $deviceStatus['status']);
        $this->assertEquals('1 Warning', $deviceStatus['text']);
        $this->assertEquals('warning', $deviceStatus['color']);
        $this->assertEquals('heroicon-o-exclamation-circle', $deviceStatus['icon']);
    }

    /** @test */
    public function it_prioritizes_critical_over_warning_alerts()
    {
        // Create both critical and warning alerts
        Alert::factory()->create([
            'device_id' => $this->device->id,
            'severity' => 'critical',
            'parameter_name' => 'system_failure',
            'message' => 'Critical system failure',
            'resolved' => false
        ]);

        Alert::factory()->create([
            'device_id' => $this->device->id,
            'severity' => 'warning',
            'parameter_name' => 'high_memory',
            'message' => 'High memory usage',
            'resolved' => false
        ]);

        $data = $this->widget->getData();
        $deviceStatus = $data['device_status'];

        $this->assertEquals('critical', $deviceStatus['status']);
        $this->assertEquals('1 Critical Alert', $deviceStatus['text']);
    }

    /** @test */
    public function it_shows_multiple_critical_alerts_count()
    {
        // Create multiple critical alerts
        Alert::factory()->count(3)->create([
            'device_id' => $this->device->id,
            'severity' => 'critical',
            'resolved' => false
        ]);

        $data = $this->widget->getData();
        $deviceStatus = $data['device_status'];

        $this->assertEquals('critical', $deviceStatus['status']);
        $this->assertEquals('3 Critical Alerts', $deviceStatus['text']);
    }

    /** @test */
    public function it_returns_available_devices()
    {
        // Create additional device
        $device2 = Device::factory()->create([
            'gateway_id' => $this->gateway->id,
            'name' => 'Second RTU Device'
        ]);

        $data = $this->widget->getData();
        $availableDevices = $data['available_devices'];

        $this->assertCount(2, $availableDevices);
        $this->assertEquals($this->device->id, $availableDevices[0]['id']);
        $this->assertEquals($this->device->name, $availableDevices[0]['name']);
        $this->assertEquals($device2->id, $availableDevices[1]['id']);
        $this->assertEquals($device2->name, $availableDevices[1]['name']);
    }

    /** @test */
    public function it_returns_severity_options()
    {
        $data = $this->widget->getData();
        $severityOptions = $data['severity_options'];

        $expectedOptions = [
            ['value' => 'critical', 'label' => 'Critical'],
            ['value' => 'warning', 'label' => 'Warning'],
            ['value' => 'info', 'label' => 'Info']
        ];

        $this->assertEquals($expectedOptions, $severityOptions);
    }

    /** @test */
    public function it_returns_time_range_options()
    {
        $data = $this->widget->getData();
        $timeRangeOptions = $data['time_range_options'];

        $expectedOptions = [
            ['value' => 'last_hour', 'label' => 'Last Hour'],
            ['value' => 'last_day', 'label' => 'Last Day'],
            ['value' => 'last_week', 'label' => 'Last Week'],
            ['value' => 'custom', 'label' => 'Custom Range']
        ];

        $this->assertEquals($expectedOptions, $timeRangeOptions);
    }

    /** @test */
    public function it_applies_filters_from_request()
    {
        // Create test alerts
        Alert::factory()->create([
            'device_id' => $this->device->id,
            'severity' => 'critical',
            'parameter_name' => 'system_failure',
            'resolved' => false
        ]);

        Alert::factory()->create([
            'device_id' => $this->device->id,
            'severity' => 'warning',
            'parameter_name' => 'high_cpu',
            'resolved' => false
        ]);

        // Mock request with filters
        RequestFacade::shouldReceive('get')
            ->with('severity', [])
            ->andReturn(['critical']);

        RequestFacade::shouldReceive('get')
            ->with('device_ids', [])
            ->andReturn([]);

        RequestFacade::shouldReceive('get')
            ->with('time_range', 'last_day')
            ->andReturn('last_day');

        RequestFacade::shouldReceive('get')
            ->with('start_date')
            ->andReturn(null);

        RequestFacade::shouldReceive('get')
            ->with('end_date')
            ->andReturn(null);

        $data = $this->widget->getData();

        // Should only show critical alerts due to filter
        $this->assertEquals(1, $data['alerts_data']['critical_count']);
        $this->assertEquals(0, $data['alerts_data']['warning_count']);
    }

    /** @test */
    public function it_gets_status_summary_correctly()
    {
        // Test critical alerts
        $summary = $this->widget->getStatusSummary(2, 0);
        $this->assertEquals('2 Critical Alerts', $summary);

        // Test single critical alert
        $summary = $this->widget->getStatusSummary(1, 0);
        $this->assertEquals('1 Critical Alert', $summary);

        // Test warning alerts
        $summary = $this->widget->getStatusSummary(0, 3);
        $this->assertEquals('3 Warnings', $summary);

        // Test single warning
        $summary = $this->widget->getStatusSummary(0, 1);
        $this->assertEquals('1 Warning', $summary);

        // Test no alerts
        $summary = $this->widget->getStatusSummary(0, 0);
        $this->assertEquals('All Systems OK', $summary);
    }

    /** @test */
    public function it_includes_gateway_in_data()
    {
        $data = $this->widget->getData();

        $this->assertArrayHasKey('gateway', $data);
        $this->assertEquals($this->gateway->id, $data['gateway']->id);
        $this->assertEquals($this->gateway->name, $data['gateway']->name);
    }

    /** @test */
    public function it_handles_empty_filters_gracefully()
    {
        // Mock empty request
        RequestFacade::shouldReceive('get')->andReturn([]);

        $data = $this->widget->getData();

        $this->assertIsArray($data['filters']);
        $this->assertEmpty($data['filters']['severity']);
        $this->assertEmpty($data['filters']['device_ids']);
    }

    /** @test */
    public function it_shows_correct_alert_counts_in_summary()
    {
        // Create mixed alerts
        Alert::factory()->count(2)->create([
            'device_id' => $this->device->id,
            'severity' => 'critical',
            'resolved' => false
        ]);

        Alert::factory()->count(3)->create([
            'device_id' => $this->device->id,
            'severity' => 'warning',
            'resolved' => false
        ]);

        Alert::factory()->count(1)->create([
            'device_id' => $this->device->id,
            'severity' => 'info',
            'resolved' => false
        ]);

        $data = $this->widget->getData();
        $alertsData = $data['alerts_data'];

        $this->assertEquals(2, $alertsData['critical_count']);
        $this->assertEquals(3, $alertsData['warning_count']);
        $this->assertEquals(1, $alertsData['info_count']);
        $this->assertTrue($alertsData['has_alerts']);
        $this->assertEquals('2 Critical Alerts', $alertsData['status_summary']);
    }

    /** @test */
    public function it_ignores_resolved_alerts()
    {
        // Create resolved alert
        Alert::factory()->create([
            'device_id' => $this->device->id,
            'severity' => 'critical',
            'resolved' => true
        ]);

        // Create unresolved alert
        Alert::factory()->create([
            'device_id' => $this->device->id,
            'severity' => 'warning',
            'resolved' => false
        ]);

        $data = $this->widget->getData();
        $alertsData = $data['alerts_data'];

        $this->assertEquals(0, $alertsData['critical_count']);
        $this->assertEquals(1, $alertsData['warning_count']);
    }
}
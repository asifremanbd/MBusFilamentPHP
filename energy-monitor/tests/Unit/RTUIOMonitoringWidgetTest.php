<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Gateway;
use App\Services\RTUDataService;
use App\Filament\Widgets\RTUIOMonitoringWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class RTUIOMonitoringWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected RTUIOMonitoringWidget $widget;
    protected Gateway $gateway;
    protected $mockRTUDataService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test gateway
        $this->gateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956',
            'di1_status' => true,
            'di2_status' => false,
            'do1_status' => true,
            'do2_status' => false,
            'analog_input_voltage' => 5.25,
            'last_system_update' => now()
        ]);

        // Mock RTUDataService
        $this->mockRTUDataService = Mockery::mock(RTUDataService::class);
        $this->app->instance(RTUDataService::class, $this->mockRTUDataService);

        // Create widget instance
        $this->widget = new RTUIOMonitoringWidget();
        $this->widget->mount($this->gateway);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_error_for_non_rtu_gateway()
    {
        // Create non-RTU gateway
        $nonRtuGateway = Gateway::factory()->create([
            'gateway_type' => 'generic'
        ]);

        $widget = new RTUIOMonitoringWidget();
        $widget->mount($nonRtuGateway);

        $data = $widget->getData();

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid or non-RTU gateway', $data['error']);
        $this->assertNull($data['digital_inputs']['di1']['status']);
        $this->assertNull($data['digital_outputs']['do1']['status']);
        $this->assertNull($data['analog_input']['voltage']);
    }

    /** @test */
    public function it_returns_error_for_null_gateway()
    {
        $widget = new RTUIOMonitoringWidget();
        $widget->mount(null);

        $data = $widget->getData();

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid or non-RTU gateway', $data['error']);
    }

    /** @test */
    public function it_gets_io_data_successfully()
    {
        $mockIOData = [
            'digital_inputs' => [
                'di1' => ['status' => true, 'label' => 'Digital Input 1'],
                'di2' => ['status' => false, 'label' => 'Digital Input 2']
            ],
            'digital_outputs' => [
                'do1' => ['status' => true, 'label' => 'Digital Output 1', 'controllable' => true],
                'do2' => ['status' => false, 'label' => 'Digital Output 2', 'controllable' => true]
            ],
            'analog_input' => [
                'voltage' => 7.35,
                'unit' => 'V',
                'range' => '0-10V',
                'precision' => 2
            ],
            'last_updated' => now()
        ];

        $this->mockRTUDataService
            ->shouldReceive('getIOStatus')
            ->once()
            ->with($this->gateway)
            ->andReturn($mockIOData);

        $data = $this->widget->getData();

        $this->assertArrayNotHasKey('error', $data);
        $this->assertEquals($this->gateway->id, $data['gateway_id']);
        
        // Test digital inputs
        $this->assertTrue($data['digital_inputs']['di1']['status']);
        $this->assertEquals('ON', $data['digital_inputs']['di1']['state_text']);
        $this->assertEquals('text-green-600 bg-green-50 border-green-200', $data['digital_inputs']['di1']['state_class']);
        
        $this->assertFalse($data['digital_inputs']['di2']['status']);
        $this->assertEquals('OFF', $data['digital_inputs']['di2']['state_text']);
        $this->assertEquals('text-gray-600 bg-gray-50 border-gray-200', $data['digital_inputs']['di2']['state_class']);
        
        // Test digital outputs
        $this->assertTrue($data['digital_outputs']['do1']['status']);
        $this->assertTrue($data['digital_outputs']['do1']['controllable']);
        $this->assertEquals('ON', $data['digital_outputs']['do1']['state_text']);
        
        $this->assertFalse($data['digital_outputs']['do2']['status']);
        $this->assertTrue($data['digital_outputs']['do2']['controllable']);
        $this->assertEquals('OFF', $data['digital_outputs']['do2']['state_text']);
        
        // Test analog input
        $this->assertEquals(7.35, $data['analog_input']['voltage']);
        $this->assertEquals('7.35 V', $data['analog_input']['formatted_value']);
        $this->assertEquals('text-blue-600 bg-blue-50 border-blue-200', $data['analog_input']['status_class']);
    }

    /** @test */
    public function it_handles_io_service_error()
    {
        $mockIOData = [
            'digital_inputs' => [
                'di1' => ['status' => null, 'label' => 'Digital Input 1'],
                'di2' => ['status' => null, 'label' => 'Digital Input 2']
            ],
            'digital_outputs' => [
                'do1' => ['status' => null, 'label' => 'Digital Output 1', 'controllable' => false],
                'do2' => ['status' => null, 'label' => 'Digital Output 2', 'controllable' => false]
            ],
            'analog_input' => [
                'voltage' => null,
                'unit' => 'V',
                'range' => '0-10V',
                'precision' => 2
            ],
            'last_updated' => $this->gateway->last_system_update,
            'error' => 'I/O data collection failed'
        ];

        $this->mockRTUDataService
            ->shouldReceive('getIOStatus')
            ->once()
            ->with($this->gateway)
            ->andReturn($mockIOData);

        $data = $this->widget->getData();

        $this->assertEquals('I/O data collection failed', $data['error']);
        $this->assertNull($data['digital_inputs']['di1']['status']);
        $this->assertEquals('Unknown', $data['digital_inputs']['di1']['state_text']);
        $this->assertFalse($data['digital_outputs']['do1']['controllable']);
        $this->assertEquals('Data Unavailable', $data['analog_input']['formatted_value']);
    }

    /** @test */
    public function it_formats_state_text_correctly()
    {
        $this->assertEquals('ON', $this->widget->getStateText(true));
        $this->assertEquals('OFF', $this->widget->getStateText(false));
        $this->assertEquals('Unknown', $this->widget->getStateText(null));
    }

    /** @test */
    public function it_returns_correct_state_classes()
    {
        $this->assertEquals('text-green-600 bg-green-50 border-green-200', $this->widget->getStateClass(true));
        $this->assertEquals('text-gray-600 bg-gray-50 border-gray-200', $this->widget->getStateClass(false));
        $this->assertEquals('text-gray-500 bg-gray-100 border-gray-300', $this->widget->getStateClass(null));
    }

    /** @test */
    public function it_formats_voltage_correctly()
    {
        $this->assertEquals('5.25 V', $this->widget->formatVoltage(5.25, 2, 'V'));
        $this->assertEquals('7.1 V', $this->widget->formatVoltage(7.123, 1, 'V'));
        $this->assertEquals('Data Unavailable', $this->widget->formatVoltage(null, 2, 'V'));
    }

    /** @test */
    public function it_returns_correct_voltage_status_classes()
    {
        // Normal range (0-10V)
        $this->assertEquals('text-blue-600 bg-blue-50 border-blue-200', $this->widget->getVoltageStatusClass(5.0));
        $this->assertEquals('text-blue-600 bg-blue-50 border-blue-200', $this->widget->getVoltageStatusClass(0.0));
        $this->assertEquals('text-blue-600 bg-blue-50 border-blue-200', $this->widget->getVoltageStatusClass(10.0));
        
        // Out of range
        $this->assertEquals('text-red-600 bg-red-50 border-red-200', $this->widget->getVoltageStatusClass(-1.0));
        $this->assertEquals('text-red-600 bg-red-50 border-red-200', $this->widget->getVoltageStatusClass(11.0));
        
        // Null value
        $this->assertEquals('text-gray-500 bg-gray-100 border-gray-300', $this->widget->getVoltageStatusClass(null));
    }

    /** @test */
    public function it_returns_correct_state_icons()
    {
        $this->assertEquals('heroicon-o-check-circle', $this->widget->getStateIcon(true));
        $this->assertEquals('heroicon-o-x-circle', $this->widget->getStateIcon(false));
        $this->assertEquals('heroicon-o-question-mark-circle', $this->widget->getStateIcon(null));
    }

    /** @test */
    public function it_checks_output_control_capability()
    {
        $mockIOData = [
            'digital_inputs' => [
                'di1' => ['status' => true, 'label' => 'Digital Input 1'],
                'di2' => ['status' => false, 'label' => 'Digital Input 2']
            ],
            'digital_outputs' => [
                'do1' => ['status' => true, 'label' => 'Digital Output 1', 'controllable' => true],
                'do2' => ['status' => false, 'label' => 'Digital Output 2', 'controllable' => false]
            ],
            'analog_input' => [
                'voltage' => 5.0,
                'unit' => 'V',
                'range' => '0-10V',
                'precision' => 2
            ],
            'last_updated' => now()
        ];

        $this->mockRTUDataService
            ->shouldReceive('getIOStatus')
            ->once()
            ->with($this->gateway)
            ->andReturn($mockIOData);

        $this->assertTrue($this->widget->canControlOutput('do1'));
        $this->assertFalse($this->widget->canControlOutput('do2'));
    }

    /** @test */
    public function it_returns_correct_toggle_button_classes()
    {
        // Controllable outputs
        $this->assertEquals('bg-green-500 hover:bg-green-600 text-white cursor-pointer', 
            $this->widget->getToggleButtonClass(true, true));
        $this->assertEquals('bg-gray-500 hover:bg-gray-600 text-white cursor-pointer', 
            $this->widget->getToggleButtonClass(false, true));
        
        // Non-controllable outputs
        $this->assertEquals('bg-gray-300 text-gray-500 cursor-not-allowed', 
            $this->widget->getToggleButtonClass(true, false));
        $this->assertEquals('bg-gray-300 text-gray-500 cursor-not-allowed', 
            $this->widget->getToggleButtonClass(false, false));
        
        // Unknown state
        $this->assertEquals('bg-gray-400 text-white cursor-not-allowed', 
            $this->widget->getToggleButtonClass(null, true));
        $this->assertEquals('bg-gray-300 text-gray-500 cursor-not-allowed', 
            $this->widget->getToggleButtonClass(null, false));
    }

    /** @test */
    public function it_returns_correct_toggle_button_text()
    {
        // Controllable outputs
        $this->assertEquals('Turn OFF', $this->widget->getToggleButtonText(true, true));
        $this->assertEquals('Turn ON', $this->widget->getToggleButtonText(false, true));
        
        // Non-controllable outputs
        $this->assertEquals('Disabled', $this->widget->getToggleButtonText(true, false));
        $this->assertEquals('Disabled', $this->widget->getToggleButtonText(false, false));
        
        // Unknown state
        $this->assertEquals('Unknown', $this->widget->getToggleButtonText(null, true));
        $this->assertEquals('Disabled', $this->widget->getToggleButtonText(null, false));
    }

    /** @test */
    public function it_handles_edge_case_voltage_values()
    {
        // Test boundary values
        $this->assertEquals('text-blue-600 bg-blue-50 border-blue-200', $this->widget->getVoltageStatusClass(0.0));
        $this->assertEquals('text-blue-600 bg-blue-50 border-blue-200', $this->widget->getVoltageStatusClass(10.0));
        
        // Test just outside boundaries
        $this->assertEquals('text-red-600 bg-red-50 border-red-200', $this->widget->getVoltageStatusClass(-0.01));
        $this->assertEquals('text-red-600 bg-red-50 border-red-200', $this->widget->getVoltageStatusClass(10.01));
        
        // Test extreme values
        $this->assertEquals('text-red-600 bg-red-50 border-red-200', $this->widget->getVoltageStatusClass(-999.0));
        $this->assertEquals('text-red-600 bg-red-50 border-red-200', $this->widget->getVoltageStatusClass(999.0));
    }

    /** @test */
    public function it_handles_precision_formatting_correctly()
    {
        // Test different precision levels
        $this->assertEquals('5.00 V', $this->widget->formatVoltage(5.0, 2, 'V'));
        $this->assertEquals('5.0 V', $this->widget->formatVoltage(5.0, 1, 'V'));
        $this->assertEquals('5 V', $this->widget->formatVoltage(5.0, 0, 'V'));
        
        // Test rounding
        $this->assertEquals('5.13 V', $this->widget->formatVoltage(5.125, 2, 'V'));
        $this->assertEquals('5.1 V', $this->widget->formatVoltage(5.125, 1, 'V'));
        $this->assertEquals('5 V', $this->widget->formatVoltage(5.125, 0, 'V'));
    }
}
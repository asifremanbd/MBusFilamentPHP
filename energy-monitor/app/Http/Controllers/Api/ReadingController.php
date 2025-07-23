<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Register;
use App\Models\Reading;
use App\Models\Alert;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ReadingController extends Controller
{
    protected AlertService $alertService;

    public function __construct(AlertService $alertService)
    {
        $this->alertService = $alertService;
    }

    /**
     * Store a reading from the Python poller
     */
    public function store(Request $request): JsonResponse
    {
        // Validate the incoming payload
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|integer|exists:devices,id',
            'parameter' => 'required|string|max:255',
            'value' => 'required|numeric',
            'timestamp' => 'required|date_format:Y-m-d\TH:i:s\Z'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get the device
            $device = Device::findOrFail($request->device_id);
            
            // Find the register for this device and parameter
            $register = Register::where('device_id', $request->device_id)
                ->where('parameter_name', $request->parameter)
                ->first();

            if (!$register) {
                return response()->json([
                    'success' => false,
                    'message' => "Register not found for device {$request->device_id} and parameter '{$request->parameter}'"
                ], 404);
            }

            // Create the reading
            $reading = Reading::create([
                'device_id' => $request->device_id,
                'register_id' => $register->id,
                'value' => $request->value,
                'timestamp' => Carbon::parse($request->timestamp)
            ]);

            // Process alerts using the AlertService
            $alerts = $this->alertService->processAlerts($register, $request->value, $request->timestamp);

            Log::info("Reading stored successfully", [
                'device_id' => $request->device_id,
                'parameter' => $request->parameter,
                'value' => $request->value,
                'timestamp' => $request->timestamp,
                'alerts_created' => count($alerts)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reading stored successfully',
                'data' => [
                    'reading_id' => $reading->id,
                    'device_id' => $reading->device_id,
                    'parameter' => $register->parameter_name,
                    'value' => $reading->value,
                    'timestamp' => $reading->timestamp->toISOString(),
                    'alerts_created' => count($alerts)
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error("Error storing reading", [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error while storing reading'
            ], 500);
        }
    }
} 
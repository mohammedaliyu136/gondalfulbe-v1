<?php

namespace Modules\MilkCollection\Http\Controllers\Api;

require_once base_path('Modules/MilkCollection/app/Models/MilkCollection.php');

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\Gondal\MilkCollectionWorkflowService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Vender;

class MilkCollectionApiController extends Controller
{
    /**
     * Handle bulk sync from offline mobile app.
     * Expects a JSON payload array of collections.
     */
    public function sync(Request $request)
    {
        $payload = $request->json()->all();

        if (!is_array($payload) || empty($payload)) {
            return response()->json(['error' => 'Invalid payload format'], 400);
        }

        $successful = [];
        $failed = [];

        DB::beginTransaction();
        try {
            foreach ($payload as $item) {
                $validator = Validator::make($item, [
                    'mcc_id' => 'required|string',
                    'farmer_id' => 'required|integer|exists:venders,id',
                    'quantity' => 'required|numeric|min:0.01',
                    'fat_percentage' => 'nullable|numeric',
                    'snf_percentage' => 'nullable|numeric',
                    'temperature' => 'nullable|numeric',
                    'collection_date' => 'required|date',
                    'recorded_by' => 'required|integer|exists:users,id',
                    'adulteration_test' => 'nullable|in:passed,failed',
                ]);

                if ($validator->fails()) {
                    $failed[] = [
                        'batch_id' => $item['batch_id'] ?? null,
                        'farmer_id' => $item['farmer_id'] ?? null,
                        'errors' => $validator->errors()
                    ];
                    continue; // Skip invalid rows
                }

                $farmer = Vender::query()->with('cooperative')->findOrFail($item['farmer_id']);
                $actor = User::query()->findOrFail($item['recorded_by']);
                $collection = app(MilkCollectionWorkflowService::class)->recordCollection([
                    'batch_id' => $item['batch_id'] ?? uniqid('SYNC-'),
                    'mcc_id' => $item['mcc_id'],
                    'quantity' => $item['quantity'],
                    'fat_percentage' => $item['fat_percentage'] ?? null,
                    'snf_percentage' => $item['snf_percentage'] ?? null,
                    'temperature' => $item['temperature'] ?? null,
                    'collection_date' => $item['collection_date'],
                    'recorded_by' => $item['recorded_by'],
                    'photo_path' => $item['photo_path'] ?? null,
                    'adulteration_test' => $item['adulteration_test'] ?? 'passed',
                    'captured_via' => 'milkcollection_api_sync',
                ], $farmer, $actor);

                $successful[] = $collection->id;
            }
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Sync completed',
                'synced_count' => count($successful),
                'failed_count' => count($failed),
                'failed_items' => $failed
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Database sync transaction failed: ' . $e->getMessage()
            ], 500);
        }
    }
}

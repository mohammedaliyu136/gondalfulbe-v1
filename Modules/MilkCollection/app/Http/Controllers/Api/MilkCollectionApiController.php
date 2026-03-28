<?php

namespace Modules\MilkCollection\Http\Controllers\Api;

require_once base_path('Modules/MilkCollection/app/Models/MilkCollection.php');

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\MilkCollection\Models\MilkCollection;
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
                    'farmer_id' => 'required|integer',
                    'quantity' => 'required|numeric|min:0.01',
                    'fat_percentage' => 'nullable|numeric',
                    'temperature' => 'nullable|numeric',
                    'collection_date' => 'required|date',
                    'recorded_by' => 'required|integer'
                ]);

                if ($validator->fails()) {
                    $failed[] = [
                        'batch_id' => $item['batch_id'] ?? null,
                        'farmer_id' => $item['farmer_id'] ?? null,
                        'errors' => $validator->errors()
                    ];
                    continue; // Skip invalid rows
                }

                // Create the collection model
                $collection = new MilkCollection();
                $collection->batch_id = $item['batch_id'] ?? uniqid('SYNC-');
                $collection->mcc_id = $item['mcc_id'];
                $collection->farmer_id = $item['farmer_id'];
                $collection->quantity = $item['quantity'];
                $collection->fat_percentage = $item['fat_percentage'] ?? null;
                $collection->temperature = $item['temperature'] ?? null;
                $collection->collection_date = $item['collection_date'];
                
                // Set recorded_by (could be API user id logically, but accepting from payload for now)
                $collection->recorded_by = $item['recorded_by'];
                
                if (isset($item['photo_path'])) {
                    $collection->photo_path = $item['photo_path'];
                }

                $collection->save(); // The `booted` saving event handles grading

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

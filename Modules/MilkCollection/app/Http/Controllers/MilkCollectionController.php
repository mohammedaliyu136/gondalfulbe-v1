<?php

namespace Modules\MilkCollection\Http\Controllers;

require_once base_path('Modules/MilkCollection/app/Models/MilkCollection.php');

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MilkCollectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (\Auth::user()->can('manage vender')) { // Using vender permission as generic access config
            $query = \Modules\MilkCollection\Models\MilkCollection::with(['farmer', 'recorder']);

            // Filters
            if (!empty($request->mcc_id)) {
                $query->where('mcc_id', $request->mcc_id);
            }
            if (!empty($request->farmer_id)) {
                $query->where('farmer_id', $request->farmer_id);
            }
            if (!empty($request->quality_grade)) {
                $query->where('quality_grade', $request->quality_grade);
            }
            if (!empty($request->start_date) && !empty($request->end_date)) {
                $query->whereBetween('collection_date', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
            }

            $collections = $query->orderBy('collection_date', 'desc')->get();

            // Daily Summary Widgets
            $totalLitres = $collections->sum('quantity');
            $uniqueFarmersCount = $collections->pluck('farmer_id')->unique()->count();
            
            // Average Quality Grade calculation (rough approx A=3, B=2, C=1)
            $grades = $collections->pluck('quality_grade')->map(function($g) {
                return $g == 'A' ? 3 : ($g == 'B' ? 2 : 1);
            });
            $avgGradeNum = $grades->count() > 0 ? round($grades->avg()) : 0;
            $avgQuality = $avgGradeNum == 3 ? 'A' : ($avgGradeNum == 2 ? 'B' : ($avgGradeNum == 1 ? 'C' : 'N/A'));

            $farmers = \App\Models\Vender::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $farmers->prepend('All Farmers', '');

            $mccs = \Modules\MilkCollection\Models\MilkCollectionCenter::where('created_by', \Auth::user()->creatorId())
                ->pluck('name', 'name')->toArray();

            return view('milkcollection::index', compact('collections', 'totalLitres', 'uniqueFarmersCount', 'avgQuality', 'farmers', 'mccs'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create()
    {
        if (\Auth::user()->can('manage vender')) {
            $farmers = \App\Models\Vender::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $farmers->prepend('Select Farmer', '');
            
            // Quick entry logic: Get last 10 farmers this user recorded
            $recentFarmersIds = \Modules\MilkCollection\Models\MilkCollection::where('recorded_by', \Auth::user()->id)
                                ->orderBy('id', 'desc')
                                ->take(10)
                                ->pluck('farmer_id')
                                ->unique()
                                ->toArray();
            $recentFarmers = \App\Models\Vender::whereIn('id', $recentFarmersIds)->get()->pluck('name', 'id');
            
            $mccs = \Modules\MilkCollection\Models\MilkCollectionCenter::where('created_by', \Auth::user()->creatorId())
                ->pluck('name', 'name')->toArray();

            return view('milkcollection::create', compact('farmers', 'recentFarmers', 'mccs'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('manage vender')) {
            $validator = \Validator::make(
                $request->all(), [
                    'mcc_id' => 'required',
                    'farmer_id' => 'required',
                    'quantity' => 'required|numeric|min:0.01',
                    'collection_date' => 'required|date',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $collection = new \Modules\MilkCollection\Models\MilkCollection();
            $collection->mcc_id = $request->mcc_id;
            $collection->farmer_id = $request->farmer_id;
            $collection->quantity = $request->quantity;
            $collection->fat_percentage = $request->fat_percentage;
            $collection->temperature = $request->temperature;
            $collection->collection_date = $request->collection_date;
            $collection->recorded_by = \Auth::user()->id;
            $collection->batch_id = 'WEB-' . date('YmdHis');

            // Grade is auto-assigned on Model boot/saving
            $collection->save();

            // Notify if Grade C
            if ($collection->quality_grade === 'C') {
                \Log::info("SMS NOTIFICATION: Farmer {$collection->farmer_id} - Milk Rejected. Reason: {$collection->rejection_reason}");
                // In production, dispatch actual SMS Notification.
            }

            return redirect()->route('milkcollection.index')->with('success', __('Milk collection recorded successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit($id)
    {
        // Not strictly required for MVP, but placeholder for full CRUD
    }
}

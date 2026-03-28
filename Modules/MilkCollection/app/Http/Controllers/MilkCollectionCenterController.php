<?php

namespace Modules\MilkCollection\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\MilkCollection\Models\MilkCollectionCenter;

class MilkCollectionCenterController extends Controller
{
    public function index()
    {
        if (\Auth::user()->can('manage mcc')) {
            $mccs = MilkCollectionCenter::where('created_by', \Auth::user()->creatorId())->get();
            return view('milkcollection::mcc.index', compact('mccs'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create()
    {
        if (\Auth::user()->can('create mcc')) {
            return view('milkcollection::mcc.create');
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create mcc')) {
            $validator = \Validator::make(
                $request->all(), [
                    'name' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $mcc = new MilkCollectionCenter();
            $mcc->name = $request->name;
            $mcc->location = $request->location;
            $mcc->contact_number = $request->contact_number;
            $mcc->created_by = \Auth::user()->creatorId();
            $mcc->save();

            return redirect()->route('mcc.index')->with('success', __('MCC created successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit(MilkCollectionCenter $mcc)
    {
        if (\Auth::user()->can('edit mcc')) {
            if ($mcc->created_by == \Auth::user()->creatorId()) {
                return view('milkcollection::mcc.edit', compact('mcc'));
            } else {
                return response()->json(['error' => __('Permission denied.')], 401);
            }
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function update(Request $request, MilkCollectionCenter $mcc)
    {
        if (\Auth::user()->can('edit mcc')) {
            if ($mcc->created_by == \Auth::user()->creatorId()) {
                $validator = \Validator::make(
                    $request->all(), [
                        'name' => 'required',
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();
                    return redirect()->back()->with('error', $messages->first());
                }

                $mcc->name = $request->name;
                $mcc->location = $request->location;
                $mcc->contact_number = $request->contact_number;
                $mcc->save();

                return redirect()->route('mcc.index')->with('success', __('MCC updated successfully.'));
            } else {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy(MilkCollectionCenter $mcc)
    {
        if (\Auth::user()->can('delete mcc')) {
            if ($mcc->created_by == \Auth::user()->creatorId()) {
                $mcc->delete();
                return redirect()->route('mcc.index')->with('success', __('MCC deleted successfully.'));
            } else {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}

<?php

namespace Modules\Cooperatives\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CooperativesController extends Controller
{
    public function index()
    {
        if(\Auth::user()->can('manage vender'))
        {
            $cooperatives = \Modules\Cooperatives\Models\Cooperative::withCount('farmers')->get();
            return view('cooperatives::index', compact('cooperatives'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create()
    {
        if(\Auth::user()->can('create vender'))
        {
            return view('cooperatives::create');
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function store(Request $request)
    {
        if(\Auth::user()->can('create vender'))
        {
            $request->validate([
                'name' => 'required|string|max:255|unique:cooperatives',
                'location' => 'nullable|string|max:255',
                'leader_name' => 'nullable|string|max:255',
                'leader_phone' => 'nullable|string|max:255',
                'formation_date' => 'nullable|date',
                'average_daily_supply' => 'nullable|numeric',
            ]);

            \Modules\Cooperatives\Models\Cooperative::create($request->all());

            return redirect()->route('cooperatives.index')->with('success', __('Cooperative successfully created.'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function show($id)
    {
        if(\Auth::user()->can('manage vender'))
        {
            $cooperative = \Modules\Cooperatives\Models\Cooperative::with('farmers')->findOrFail($id);
            return view('cooperatives::show', compact('cooperative'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function exportFarmers($id)
    {
        if(\Auth::user()->can('manage vender')) {
            $coop = \Modules\Cooperatives\Models\Cooperative::findOrFail($id);
            $name = 'cooperative_farmers_' . preg_replace('/[^A-Za-z0-9]/', '', $coop->name) . '_' . date('Y-m-d');
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\VenderExport($id), $name . '.xlsx');
        }
        return redirect()->back();
    }

    public function edit($id)
    {
        if(\Auth::user()->can('edit vender'))
        {
            $cooperative = \Modules\Cooperatives\Models\Cooperative::findOrFail($id);
            return view('cooperatives::edit', compact('cooperative'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function update(Request $request, $id)
    {
        if(\Auth::user()->can('edit vender'))
        {
            $cooperative = \Modules\Cooperatives\Models\Cooperative::findOrFail($id);
            
            $request->validate([
                'name' => 'required|string|max:255|unique:cooperatives,name,' . $id,
                'location' => 'nullable|string|max:255',
                'leader_name' => 'nullable|string|max:255',
                'leader_phone' => 'nullable|string|max:255',
                'formation_date' => 'nullable|date',
                'average_daily_supply' => 'nullable|numeric',
            ]);

            $cooperative->update($request->all());

            return redirect()->route('cooperatives.index')->with('success', __('Cooperative successfully updated.'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy($id)
    {
        if(\Auth::user()->can('delete vender'))
        {
            $cooperative = \Modules\Cooperatives\Models\Cooperative::findOrFail($id);
            if ($cooperative->farmers()->count() > 0) {
               return redirect()->route('cooperatives.index')->with('error', __('Cannot delete cooperative with assigned farmers.'));
            }
            $cooperative->delete();
            return redirect()->route('cooperatives.index')->with('success', __('Cooperative successfully deleted.'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function export()
    {
        $name = 'cooperatives_' . date('Y-m-d i:h:s');
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        $data = \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\CooperativeExport(), $name . '.xlsx');
        return $data;
    }

    public function fileImportExport()
    {
        return view('cooperatives::import');
    }

    public function fileImport(\Illuminate\Http\Request $request)
    {
        session_start();
        $html = '<h3 class="text-danger text-center">Below data is not inserted</h3></br>';
        $flag = 0;
        $html .= '<table class="table table-bordered"><tr>';
        try {
            $req = $request->data;
            $file_data = $_SESSION['file_data'];
            unset($_SESSION['file_data']);
        } catch (\Throwable $th) {
            $html = '<h3 class="text-danger text-center">Something went wrong, Please try again</h3></br>';
            return response()->json([
                'html' => true,
                'response' => $html,
            ]);
        }

        foreach ($file_data as $key => $row) {
            $coopName = isset($req['name']) && isset($row[$req['name']]) ? $row[$req['name']] : null;
            $coopLoc = isset($req['location']) && isset($row[$req['location']]) ? $row[$req['location']] : null;

            if ($coopName && $coopLoc) {
                $coopExists = \Modules\Cooperatives\Models\Cooperative::where('name', $coopName)
                                ->where('location', $coopLoc)->first();

                if(empty($coopExists)){
                    try {
                        $coop            = new \Modules\Cooperatives\Models\Cooperative();
                        $coop->name      = $coopName;
                        $coop->location  = $coopLoc;
                        $coop->leader_name = isset($req['leader_name']) ? $row[$req['leader_name']] : null;
                        $coop->leader_phone = isset($req['leader_phone']) ? $row[$req['leader_phone']] : null;
                        $coop->formation_date = isset($req['formation_date']) ? $row[$req['formation_date']] : null;
                        $coop->average_daily_supply = isset($req['average_daily_supply']) ? $row[$req['average_daily_supply']] : null;
                        $coop->save();
                    } catch (\Exception $e) {
                        $flag = 1;
                        $html .= '<tr>';
                        $html .= '<td>' . $coopName . '</td>';
                        $html .= '<td>' . $coopLoc . '</td>';
                        $html .= '</tr>';
                    }
                } else {
                    $flag = 1;
                    $html .= '<tr>';
                    $html .= '<td>' . $coopName . '</td>';
                    $html .= '<td>' . $coopLoc . ' (Duplicate)</td>';
                    $html .= '</tr>';
                }
            }
        }

        $html .= '</table><br />';
        if ($flag == 1) {
            return response()->json([
                'html' => true,
                'response' => $html,
            ]);
        } else {
            return response()->json([
                'html' => true,
                'response' => 'Data Imported Successfully',
            ]);
        }
    }
}

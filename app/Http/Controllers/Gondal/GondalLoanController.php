<?php

namespace App\Http\Controllers\Gondal;

use App\Http\Controllers\Controller;
use App\Models\Gondal\AgentProfile;
use App\Models\Gondal\GondalLoan;
use App\Models\Gondal\GondalLoanDisbursement;
use App\Models\Project;
use App\Models\Vender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GondalLoanController extends Controller
{
    public function index(Request $request)
    {
        $loans = GondalLoan::with(['farmer', 'agentProfile', 'project', 'approver'])
            ->orderByDesc('id')
            ->paginate();

        $farmers = Vender::where('is_active', 1)->get();
        $agents = AgentProfile::all();
        $projects = Project::all();

        return view('gondal.loans.index', compact('loans', 'farmers', 'agents', 'projects'));
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'farmer_id' => 'required|exists:venders,id',
            'agent_profile_id' => 'nullable|exists:gondal_agent_profiles,id',
            'project_id' => 'nullable|exists:projects,id',
            'type' => 'required|string',
            'principal_amount' => 'required|numeric|min:1',
            'interest_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $payload['reference'] = 'LN-' . strtoupper(Str::random(8));
        $payload['status'] = 'pending';
        $payload['created_by'] = $request->user()->id;

        GondalLoan::create($payload);

        return redirect()->back()->with('success', __('Loan application created successfully.'));
    }

    public function approve(Request $request, GondalLoan $loan)
    {
        if ($loan->status !== 'pending') {
            return redirect()->back()->with('error', __('Only pending applications can be approved.'));
        }

        $loan->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('success', __('Loan approved.'));
    }

    public function disburse(Request $request, GondalLoan $loan)
    {
        $request->validate(['disbursal_date' => 'required|date']);

        if ($loan->status !== 'approved') {
            return redirect()->back()->with('error', __('Only approved loans can be disbursed.'));
        }

        DB::transaction(function () use ($loan, $request) {
            $disbursement = GondalLoanDisbursement::create([
                'gondal_loan_id' => $loan->id,
                'disbursal_date' => $request->disbursal_date,
                'amount' => $loan->principal_amount,
                'status' => 'completed',
                'disbursed_by' => $request->user()->id,
                'notes' => 'Initial loan disbursement',
            ]);

            // Feed the engine: create GondalObligation
            $disbursement->obligation()->create([
                'reference' => 'OBL-' . $loan->reference,
                'farmer_id' => $loan->farmer_id,
                'agent_profile_id' => $loan->agent_profile_id,
                'project_id' => $loan->project_id,
                'principal_amount' => $loan->principal_amount,
                'outstanding_amount' => $loan->principal_amount,
                'recovered_amount' => 0,
                'priority' => 10,
                'status' => 'open',
                'created_by' => $request->user()->id,
            ]);

            $loan->update(['status' => 'disbursed']);

            app(\App\Services\Gondal\GondalNotificationService::class)->queueNotification(
                'loan_disbursed',
                'farmer',
                $loan->farmer_id,
                'sms',
                "Your loan of {$loan->principal_amount} has been successfully disbursed.",
                $loan->reference . '-DISB'
            );
        });

        return redirect()->back()->with('success', __('Loan disbursed and obligation created.'));
    }
}

<?php

namespace App\Http\Controllers;

use App\User;
use App\Check;
use App\Action;
use App\Company;
use App\History;
use Carbon\Carbon;
use App\Transmittal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class CheckController extends Controller
{
    // show all for dev
    public function index(Company $company)
    {
        return $company->checks;
    }

    public function create(Request $request, Company $company)
    {
        $this->authorize('create', Check::class);

        $request->validate([
            'account_id' => ['required', Rule::in($company->accounts()->pluck('id'))],
            'payee_id' => ['required', Rule::in($company->payees()->pluck('id'))],
            'amount' => 'required|numeric|gt:0',
            'date' => 'required|date',
        ]);

        $check = Check::create([
            'status_id' => 1, // created
            'company_id' => $company->id,
            'account_id' => $request->get('account_id'),
            'payee_id' => $request->get('payee_id'),
            'amount' => $request->get('amount'),
            'details' => $request->get('details'),
            'date' => $request->get('date'),
        ]);

        $this->recordLog($check, 'crt');

        return ['message' => 'Check successfully created.'];
    }

    public function transmit(Request $request, Company $company)
    {
        $this->authorize('transmit', Check::class);
        // validate
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'incharge' => 'required|exists:users,id',
            'date' => 'required|date',
            'ref' => 'required|unique:transmittals,ref',
            'series' => 'required|integer',
            'checks' => 'required|array'
        ]);

        $checks = Check::whereIn('id', $request->get('checks'))->get();
        // check if checks belong to company
        $this->checkCompany($company, $checks);
        // create transmittal
        Transmittal::create([
            'branch_id' => $request->get('branch_id'),
            'user_id' => $request->user()->id,
            'incharge' => $request->get('incharge'),
            'date' => $request->get('date'),
            'due' => Carbon::create( $request->get('date') )->addDays(30)->format("Y/m/d"),
            'ref' => $request->get('ref'),
            'series' => $request->get('series'),
        ])->checks()->sync($checks);
        // record history and update status
        $checks->each( function($check) {
            $check->update([ 'status_id' => 2, 'received' => 0 ]); // transmitted

            $this->recordLog($check, 'trm');
        });

        return ['message' => 'Checks successfully transmitted.'];
    }

    public function show(Company $company, Check $check)
    {
        abort_unless($check->company_id === $company->id, 404, 'Not Found');

        $check->history;
        $check->transmittals;

        return $check;
    }
    // record check log
    protected function recordLog(Check $check, $action)
    {
        History::create([
            'check_id' => $check->id,
            'action_id' => Action::where('code', $action)->first()->id,
            'user_id' => Auth::user()->id
        ]);
    }
    // if check belongs to company
    protected function checkCompany(Company $company, Collection $checks)
    {
        $checks->each( function($check) use ($company) {
            abort_unless($check->company_id === $company->id, 403, 'Unauthorized');
        });
    }
}

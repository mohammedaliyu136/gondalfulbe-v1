@extends('layouts.admin')

@section('page-title')
    {{ __('General Ledger & Accounting') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('Accounting') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'accounts' ? 'active' : '' }}" href="{{ route('gondal.accounting', ['tab' => 'accounts']) }}">{{ __('Chart of Accounts') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'entries' ? 'active' : '' }}" href="{{ route('gondal.accounting', ['tab' => 'entries']) }}">{{ __('Journal Entries') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'ledger' ? 'active' : '' }}" href="{{ route('gondal.accounting', ['tab' => 'ledger']) }}">{{ __('General Ledger') }}</a>
                    </li>
                </ul>

                <hr/>

                @if($tab === 'accounts')
                    <div class="table-responsive">
                        <table class="table table-flush dataTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('Code') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('System Default') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($accounts as $account)
                                    <tr>
                                        <td>{{ $account->code }}</td>
                                        <td>{{ $account->name }}</td>
                                        <td><span class="badge bg-primary">{{ ucfirst($account->type) }}</span></td>
                                        <td>{{ $account->is_system ? 'Yes' : 'No' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($tab === 'entries')
                    <div class="table-responsive">
                        <table class="table table-flush dataTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('Entry #') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Description') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($entries as $entry)
                                    <tr>
                                        <td>{{ $entry->entry_number }}</td>
                                        <td>{{ $entry->entry_date }}</td>
                                        <td><span class="badge bg-info">{{ $entry->entry_type }}</span></td>
                                        <td>{{ $entry->description }}</td>
                                        <td><span class="badge bg-{{ $entry->status == 'posted' ? 'success' : 'danger' }}">{{ ucfirst($entry->status) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        {{ $entries->links() }}
                    </div>
                @endif

                @if($tab === 'ledger')
                    <div class="table-responsive">
                        <table class="table table-flush dataTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Account') }}</th>
                                    <th>{{ __('Farmer / Vender') }}</th>
                                    <th>{{ __('Direction') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Memo') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lines as $line)
                                    <tr>
                                        <td>{{ optional($line->entry)->entry_date }}</td>
                                        <td><strong>{{ optional($line->account)->code }}</strong><br/><small>{{ optional($line->account)->name }}</small></td>
                                        <td>{{ optional($line->farmer)->name ?? '-' }}</td>
                                        <td>
                                            @if($line->direction === 'debit')
                                                <span class="badge bg-danger">Dr</span>
                                            @else
                                                <span class="badge bg-success">Cr</span>
                                            @endif
                                        </td>
                                        <td><strong>{{ number_format($line->amount, 2) }}</strong></td>
                                        <td>{{ $line->memo }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        {{ $lines->links() }}
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection

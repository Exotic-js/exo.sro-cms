@extends('admin.layouts.app')
@section('title', __('Donate Logs'))

@section('content')
    <div>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Donate Logs</h1>
        </div>

        <form method="GET" action="{{ route('admin.logs.donate') }}" class="mb-4 row g-3">
            <div class="col-lg-2">
                <input type="text" name="transaction_id" class="form-control" placeholder="Transaction ID" value="{{ request('transaction_id') }}">
            </div>
            <div class="col-lg-2">
                <select name="method_type" class="form-select">
                    <option value="">All Methods</option>
                    <option value="AdminPanel" {{ request('method') == 'AdminPanel' ? 'selected' : '' }}>AdminPanel</option>
                    <option value="Voucher" {{ request('method') == 'Voucher' ? 'selected' : '' }}>Voucher</option>
                    <option value="Referral" {{ request('method') == 'Referral' ? 'selected' : '' }}>Referral</option>
                    <option value="Vote" {{ request('method') == 'Vote' ? 'selected' : '' }}>Vote</option>

                    @foreach(config('donate') as $key => $method)
                        <option value="{{ $method['name'] }}" {{ request('method') == $method['name'] ? 'selected' : '' }}>{{ $method['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="true" {{ request('status') == 'true' ? 'selected' : '' }}>Completed</option>
                    <option value="false" {{ request('status') == 'false' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            <div class="col-lg-2">
                <input type="number" name="jid" class="form-control" placeholder="JID" value="{{ request('jid') }}">
            </div>
            <div class="col-lg-2">
                <input type="text" name="ip" class="form-control" placeholder="IP Address" value="{{ request('ip') }}">
            </div>
            <div class="col-lg-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>

        <div class="table-responsive small">
            <table class="table table-striped table-sm">
                <thead>
                <tr>
                    <th scope="col">Method</th>
                    <th scope="col">Transaction ID</th>
                    <th scope="col">User JID</th>
                    <th scope="col">User IP</th>
                    <th scope="col">Amount</th>
                    <th scope="col">Silk</th>
                    <th scope="col">Date</th>
                    <th scope="col">Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse($data as $row)
                    <tr>
                        <td>{{ $row->method }}</td>
                        <td>{{ $row->transaction_id }}</td>
                        <td>{{ $row->jid }}</td>
                        <td>{{ $row->ip }}</td>
                        <td>{{ $row->amount }}</td>
                        <td>{{ $row->value }}</td>
                        <td>{{ $row->updated_at->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $row->status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No Records Found!</td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            {{ $data->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection

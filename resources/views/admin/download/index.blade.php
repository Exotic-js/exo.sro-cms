@extends('admin.layouts.app')
@section('title', __('Downloads'))

@section('content')
    <div>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Downloads</h1>

            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="{{ route('admin.download.create') }}" class="btn btn-sm btn-outline-secondary">+ New</a>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="alert">
                {{ session('success') }}
            </div>
        @endif

        <div class="table-responsive small">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Name</th>
                        <th scope="col">Category</th>
                        <th scope="col">Link</th>
                        <th scope="col">Options</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->name }}</td>
                            <td><span class="badge text-bg-{{ $row->category === 'client' ? 'primary' : 'success' }}">{{ ucfirst($row->category) }}</span></td>
                            <td><a href="{{ $row->url }}" target="_blank">{{ $row->url }}</a></td>
                            <td>
                                <a href="{{ route('admin.download.edit', $row->id) }}" class="btn btn-secondary btn-sm">Edit</a>
                                <a href="{{ route('admin.download.delete', $row->id) }}" class="btn btn-danger btn-sm">Delete</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No Records Found!</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{ $data->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection

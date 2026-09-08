@extends('admin.layouts.app')
@section('title', __('Edit Download'))

@section('content')
    <div>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Edit Download</h1>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.download.update', $data->id) }}">
            @csrf
            @method('PUT')

            <div class="row mb-3">
                <label for="name" class="col-lg-2 col-form-label text-md-end">{{ __('Name') }}</label>

                <div class="col-lg-10">
                    <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $data->name) }}" required>

                    @error('name')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <label for="desc" class="col-lg-2 col-form-label text-md-end">{{ __('Description') }}</label>

                <div class="col-lg-10">
                    <input id="desc" type="text" class="form-control @error('desc') is-invalid @enderror" name="desc" value="{{ old('desc', $data->desc) }}">

                    @error('desc')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <label for="category" class="col-lg-2 col-form-label text-md-end">{{ __('Category') }}</label>

                <div class="col-lg-10">
                    <select class="form-select" name="category" required>
                        <option value="client" {{ old('category', $data->category) === 'client' ? 'selected' : '' }}>Client</option>
                        <option value="patches" {{ old('category', $data->category) === 'patches' ? 'selected' : '' }}>Patches</option>
                    </select>

                    @error('category')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <label for="url" class="col-lg-2 col-form-label text-md-end">{{ __('Link') }}</label>

                <div class="col-lg-10">
                    <input id="url" type="url" class="form-control @error('url') is-invalid @enderror" name="url" value="{{ old('url', $data->url) }}" required>

                    @error('url')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <label for="image" class="col-lg-2 col-form-label text-md-end">{{ __('Image') }}</label>

                <div class="col-lg-10">
                    <input id="image" type="text" class="form-control @error('image') is-invalid @enderror" name="image" value="{{ old('image', $data->image) }}">

                    @error('image')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                    @enderror
                </div>
            </div>

            <div class="row mb-0">
                <div class="col-lg-10 offset-md-2">
                    <button type="submit" class="btn btn-primary">
                        {{ __('Update Download') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('styles')

@endpush
@push('scripts')

@endpush

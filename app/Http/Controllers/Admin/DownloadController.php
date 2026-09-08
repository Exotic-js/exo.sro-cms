<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Download;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    public function index()
    {
        $data = Download::latest()->paginate(20);

        return view('admin.download.index', compact('data'));
    }

    public function create()
    {
        return view('admin.download.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'desc' => 'nullable|string',
            'url' => 'required|url|max:2048',
            'image' => 'nullable|string|max:2048',
            'category' => 'required|in:client,patches',
        ]);

        Download::create($validated);

        return redirect()->route('admin.download.index')->with('success', 'Download created successfully!');
    }

    public function edit(Download $download)
    {
        return view('admin.download.edit', ['data' => $download]);
    }

    public function update(Request $request, Download $download)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'desc' => 'nullable|string',
            'url' => 'required|url|max:2048',
            'image' => 'nullable|string|max:2048',
            'category' => 'required|in:client,patches',
        ]);

        $download->update($validated);

        return redirect()->route('admin.download.index')->with('success', 'Download updated successfully.');
    }

    public function confirmDelete(Download $download)
    {
        return view('admin.download.delete', ['data' => $download]);
    }

    public function destroy(Download $download)
    {
        $download->delete();

        return redirect()->route('admin.download.index')->with('success', 'Download deleted successfully.');
    }
}

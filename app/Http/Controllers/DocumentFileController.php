<?php

namespace App\Http\Controllers;

use App\Models\DocumentFile;
use App\Models\FileCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentFileController extends Controller
{
    public function categories(Request $request)
    {
        $this->authorizeView();
        $edit = $request->filled('edit')
            ? FileCategory::query()->find((int) $request->input('edit'))
            : null;

        return view('documents.categories', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'documents.categories',
            'categories' => FileCategory::query()->orderBy('name')->get(),
            'edit' => $edit,
            'canManage' => $this->canManage(),
        ]));
    }

    public function storeCategory(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('file_categories', 'name')->where('company_id', $companyId)],
            'description' => 'nullable|string|max:500',
        ]);

        FileCategory::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('documents.categories')->with('success', 'Category saved.');
    }

    public function updateCategory(Request $request, FileCategory $category)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('file_categories', 'name')->where('company_id', $companyId)->ignore($category->id)],
            'description' => 'nullable|string|max:500',
        ]);

        $category->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('documents.categories')->with('success', 'Category updated.');
    }

    public function destroyCategory(FileCategory $category)
    {
        $this->authorizeManage();
        if ($category->files()->exists()) {
            return back()->with('error', 'Category has files and cannot be deleted.');
        }
        $category->delete();

        return redirect()->route('documents.categories')->with('success', 'Category deleted.');
    }

    public function index()
    {
        $this->authorizeView();

        $files = DocumentFile::query()
            ->with(['category', 'user'])
            ->orderByDesc('id')
            ->get();

        return view('documents.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'documents.index',
            'files' => $files,
            'canManage' => $this->canManage(),
        ]));
    }

    public function create()
    {
        $this->authorizeManage();
        $this->ensureGeneralCategory();

        return view('documents.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'documents.create',
            'file' => new DocumentFile(),
            'categories' => FileCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'canManage' => true,
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;

        $data = $request->validate([
            'file_category_id' => ['required', Rule::exists('file_categories', 'id')->where('company_id', $companyId)],
            'title' => 'required|string|max:255',
            'upload' => 'required|file|max:20480',
            'expires_at' => 'nullable|date',
            'description' => 'nullable|string|max:2000',
        ]);

        $upload = $request->file('upload');
        $path = $upload->store('documents/' . $companyId, 'public');

        $file = DocumentFile::query()->create([
            'company_id' => $companyId,
            'branch_id' => $this->currentBranchId(),
            'file_category_id' => $data['file_category_id'],
            'user_id' => auth()->id(),
            'title' => $data['title'],
            'file_path' => $path,
            'original_name' => $upload->getClientOriginalName(),
            'mime_type' => $upload->getClientMimeType(),
            'file_size' => (int) $upload->getSize(),
            'expires_at' => $data['expires_at'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => DocumentFile::STATUS_ACTIVE,
        ]);
        $file->update(['status' => $file->resolveStatus()]);

        return redirect()->route('documents.index')->with('success', 'File saved.');
    }

    public function edit(DocumentFile $file)
    {
        $this->authorizeManage();
        $this->ensureGeneralCategory();

        return view('documents.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'documents.create',
            'file' => $file,
            'categories' => FileCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'canManage' => true,
        ]));
    }

    public function update(Request $request, DocumentFile $file)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;

        $data = $request->validate([
            'file_category_id' => ['required', Rule::exists('file_categories', 'id')->where('company_id', $companyId)],
            'title' => 'required|string|max:255',
            'upload' => 'nullable|file|max:20480',
            'expires_at' => 'nullable|date',
            'description' => 'nullable|string|max:2000',
        ]);

        $payload = [
            'file_category_id' => $data['file_category_id'],
            'title' => $data['title'],
            'expires_at' => $data['expires_at'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        if ($request->hasFile('upload')) {
            $this->deleteStored($file->file_path);
            $upload = $request->file('upload');
            $payload['file_path'] = $upload->store('documents/' . $companyId, 'public');
            $payload['original_name'] = $upload->getClientOriginalName();
            $payload['mime_type'] = $upload->getClientMimeType();
            $payload['file_size'] = (int) $upload->getSize();
        }

        $file->update($payload);
        $file->update(['status' => $file->resolveStatus()]);

        return redirect()->route('documents.index')->with('success', 'File updated.');
    }

    public function destroy(DocumentFile $file)
    {
        $this->authorizeManage();
        $this->deleteStored($file->file_path);
        $file->delete();

        return redirect()->route('documents.index')->with('success', 'File deleted.');
    }

    public function download(DocumentFile $file)
    {
        $this->authorizeView();
        abort_unless(Storage::disk('public')->exists($file->file_path), 404);

        return Storage::disk('public')->download($file->file_path, $file->original_name ?: $file->title);
    }

    private function ensureGeneralCategory(): void
    {
        if (! FileCategory::query()->exists()) {
            FileCategory::query()->create([
                'company_id' => auth()->user()->company_id,
                'name' => 'General',
                'description' => 'Default file category',
                'is_active' => true,
            ]);
        }
    }

    private function deleteStored(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function authorizeView(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('settings.view') || $user->hasPermission('sales.view') || $user->hasPermission('settings.company')), 403);
    }

    private function authorizeManage(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('settings.company') || $user->hasPermission('sales.create') || $user->hasPermission('settings.update')), 403);
    }

    private function canManage(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasPermission('settings.company') || $user->hasPermission('sales.create') || $user->hasPermission('settings.update'));
    }
}

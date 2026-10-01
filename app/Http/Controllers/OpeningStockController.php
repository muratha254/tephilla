<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\ColourRegisterImporter;
use App\Services\OpeningStockImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OpeningStockController extends Controller
{
    public function create()
    {
        $this->authorizePermission('inventory.adjust');

        return view('stock.opening-import', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'stock.opening',
            'result' => session('import_result'),
            'preview' => session('register_preview'),
        ]));
    }

    public function store(Request $request, OpeningStockImporter $importer, AuditLogger $audit)
    {
        $this->authorizePermission('inventory.adjust');
        $branchId = $this->currentBranchId();
        abort_unless($branchId, 422, 'Select a branch first.');

        $request->validate([
            'file' => 'required|file|max:5120',
        ]);

        $result = $importer->import(
            $request->file('file'),
            (int) auth()->user()->company_id,
            (int) $branchId,
            (int) auth()->id()
        );

        $audit->record('import', 'opening_stock', auth()->user(), null, [
            'imported' => $result['imported'],
            'errors' => count($result['errors']),
        ]);

        $message = $result['imported'] . ' opening stock row' . ($result['imported'] === 1 ? '' : 's') . ' imported.';
        if ($result['errors']) {
            $message .= ' ' . count($result['errors']) . ' row' . (count($result['errors']) === 1 ? '' : 's') . ' rejected.';
        }

        return redirect()
            ->route('stock.opening')
            ->with('success', $message)
            ->with('import_result', $result);
    }

    public function previewRegister(Request $request, ColourRegisterImporter $importer)
    {
        $this->authorizePermission('inventory.adjust');
        $request->validate([
            'register' => 'required|file|max:10240',
        ]);

        $preview = $importer->preview($request->file('register'));
        if ($preview['errors']) {
            return redirect()
                ->route('stock.opening')
                ->with('error', 'The colour register was not imported.')
                ->with('import_result', ['errors' => $preview['errors'], 'imported' => 0, 'skipped' => 0]);
        }

        $name = Str::uuid()->toString() . '.' . $request->file('register')->getClientOriginalExtension();
        $request->file('register')->storeAs('register-previews', $name);

        return redirect()
            ->route('stock.opening')
            ->with('register_preview', array_merge($preview, ['file' => $name]));
    }

    public function confirmRegister(Request $request, ColourRegisterImporter $importer, AuditLogger $audit)
    {
        $this->authorizePermission('inventory.adjust');
        $branchId = $this->currentBranchId();
        abort_unless($branchId, 422, 'Select a branch first.');

        $preview = session('register_preview');
        $name = is_array($preview) ? ($preview['file'] ?? '') : '';
        $path = $name !== '' ? storage_path('app/register-previews/' . $name) : '';
        abort_unless($path !== '' && is_file($path), 422, 'Preview the register again before confirming.');

        $result = $importer->import(
            $path,
            (int) auth()->user()->company_id,
            (int) $branchId,
            (int) auth()->id()
        );
        Storage::delete('register-previews/' . $name);
        $request->session()->forget('register_preview');

        $audit->record('import', 'colour_register', auth()->user(), null, [
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'errors' => count($result['errors']),
        ]);

        if ($result['errors']) {
            return redirect()
                ->route('stock.opening')
                ->with('error', 'The colour register was not imported.')
                ->with('import_result', $result);
        }

        return redirect()
            ->route('stock.opening')
            ->with('success', $result['imported'] . ' register lines imported. ' . $result['skipped'] . ' already recorded.')
            ->with('import_result', $result);
    }

    public function storeRegister(Request $request, ColourRegisterImporter $importer, AuditLogger $audit)
    {
        $this->authorizePermission('inventory.adjust');
        $branchId = $this->currentBranchId();
        abort_unless($branchId, 422, 'Select a branch first.');

        $request->validate([
            'register' => 'required|file|max:10240',
        ]);

        $result = $importer->import(
            $request->file('register'),
            (int) auth()->user()->company_id,
            (int) $branchId,
            (int) auth()->id()
        );

        $audit->record('import', 'colour_register', auth()->user(), null, [
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'errors' => count($result['errors']),
        ]);

        if ($result['errors']) {
            return redirect()
                ->route('stock.opening')
                ->with('error', 'The colour register was not imported.')
                ->with('import_result', $result);
        }

        return redirect()
            ->route('stock.opening')
            ->with('success', $result['imported'] . ' register lines imported. ' . $result['skipped'] . ' already recorded.')
            ->with('import_result', $result);
    }
}

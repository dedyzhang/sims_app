<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Http\Requests\SupplierRequest;
use App\Sarpras\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        $supplier = Supplier::orderBy('nama')->get();

        return view('sarpras.supplier.index', compact('supplier'));
    }

    public function create(): View
    {
        return view('sarpras.supplier.form', ['supplier' => new Supplier()]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        return redirect()->route('sarpras.supplier.index')->with('sukses', 'Supplier ditambahkan.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('sarpras.supplier.form', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('sarpras.supplier.index')->with('sukses', 'Supplier diperbarui.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return redirect()->route('sarpras.supplier.index')->with('sukses', 'Supplier dihapus.');
    }
}

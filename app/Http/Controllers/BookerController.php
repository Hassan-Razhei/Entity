<?php

namespace App\Http\Controllers;

use App\Models\Booker;
use App\Helpers\SlugHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class BookerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = Booker::query();

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $bookers = $query->withCount(['books', 'videos', 'audios', 'manuscripts'])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Bookers/Index', [
            'bookers' => $bookers,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Bulk destroy resource.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:bookers,id',
        ]);

        Booker::whereIn('id', $request->ids)->delete();

        return back()->with('success', 'تم حذف المساهمين بنجاح.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Bookers/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Booker::create([
            'name' => $validated['name'],
            'slug' => SlugHelper::generate($validated['name']),
        ]);

        return redirect()->route('bookers.index')
            ->with('message', 'تم إضافة المساهم بنجاح');
    }

    /**
     * Display the specified resource.
     */
    public function show(Booker $booker): Response
    {
        return Inertia::render('Bookers/Show', [
            'booker' => $booker->loadCount(['books', 'videos', 'audios', 'manuscripts']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Booker $booker): Response
    {
        return Inertia::render('Bookers/Edit', [
            'booker' => $booker,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Booker $booker): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $booker->update([
            'name' => $validated['name'],
            'slug' => SlugHelper::generate($validated['name']),
        ]);

        return redirect()->route('bookers.index')
            ->with('message', 'تم تحديث بيانات المساهم بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Booker $booker): RedirectResponse
    {
        $booker->delete();

        return redirect()->route('bookers.index')
            ->with('message', 'تم حذف المساهم بنجاح');
    }
}

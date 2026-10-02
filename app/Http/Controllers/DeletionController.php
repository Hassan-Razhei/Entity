<?php

namespace App\Http\Controllers;


use App\Models\Deletion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeletionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search']);

        $deletions = Deletion::with('user')
            ->when($request->input('search'), function ($query, $search) {
                $query->where('reason', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
            })
            ->latest('deleted_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Deletions/Index', [
            'deletions' => $deletions,
            'filters' => $filters,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Deletion $deletion): Response
    {
        return Inertia::render('Deletions/Show', [
            'deletion' => $deletion->load('user'),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Mall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** SRS FR-020 / FR-021 - mall registration and structure. */
class MallController extends Controller
{
    public function index(): View
    {
        return view('admin.malls.index', [
            'malls' => Mall::query()->withCount(['blocks', 'shops'])->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.malls.form', ['mall' => new Mall]);
    }

    public function store(Request $request): RedirectResponse
    {
        $mall = Mall::create($this->validated($request));

        return redirect()->route('admin.malls.show', $mall)->with('status', 'Mall registered.');
    }

    public function show(Mall $mall): View
    {
        $mall->load(['blocks.floors', 'blocks' => fn ($q) => $q->withCount('shops')]);

        return view('admin.malls.show', ['mall' => $mall]);
    }

    public function edit(Mall $mall): View
    {
        return view('admin.malls.form', ['mall' => $mall]);
    }

    public function update(Request $request, Mall $mall): RedirectResponse
    {
        $mall->update($this->validated($request, $mall));

        return redirect()->route('admin.malls.show', $mall)->with('status', 'Mall updated.');
    }

    /** FR-021 - blocks within a mall. */
    public function storeBlock(Request $request, Mall $mall): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('blocks')->where('mall_id', $mall->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $mall->blocks()->create($validated + ['status' => 'active']);

        return back()->with('status', 'Block added.');
    }

    /** FR-021 - floors within a block. */
    public function storeFloor(Request $request, Block $block): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('floors')->where('block_id', $block->id)],
            'level' => ['required', 'integer', 'between:-5,200'],
        ]);

        $block->floors()->create($validated);

        return back()->with('status', 'Floor added.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Mall $mall = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', Rule::unique('malls')->ignore($mall)],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Block;
use App\Models\Floor;
use App\Models\Mall;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** SRS FR-030 to FR-032 - shop registration, status and availability. */
class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $shops = Shop::query()
            ->with(['mall', 'block', 'floor', 'activeAllocation.owner'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('block')->toString(), fn ($q, $b) => $q->where('block_id', $b))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($sub) => $sub->where('shop_number', 'like', "%{$term}%")
                    ->orWhere('shop_code', 'like', "%{$term}%")
            ))
            ->orderBy('shop_number')
            ->paginate(25)
            ->withQueryString();

        return view('admin.shops.index', [
            'shops' => $shops,
            'blocks' => Block::query()->with('mall')->orderBy('name')->get(),
            'statuses' => Shop::STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('admin.shops.form', [
            'shop' => new Shop(['status' => Shop::AVAILABLE]),
            'malls' => Mall::query()->orderBy('name')->get(),
            'blocks' => Block::query()->orderBy('name')->get(),
            'floors' => Floor::query()->orderBy('level')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = Shop::create($this->validated($request));

        return redirect()->route('admin.shops.show', $shop)->with('status', 'Shop registered.');
    }

    public function show(Shop $shop): View
    {
        $shop->load(['mall', 'block', 'floor']);

        return view('admin.shops.show', [
            'shop' => $shop,
            // FR-123 - the shop's permanent ownership history.
            'history' => $shop->ownershipHistory()->with('owner')->get(),
            'activeAllocation' => $shop->allocations()
                ->whereIn('status', Allocation::OCCUPYING_STATUSES)
                ->with('owner')->first(),
        ]);
    }

    public function edit(Shop $shop): View
    {
        return view('admin.shops.form', [
            'shop' => $shop,
            'malls' => Mall::query()->orderBy('name')->get(),
            'blocks' => Block::query()->orderBy('name')->get(),
            'floors' => Floor::query()->orderBy('level')->get(),
        ]);
    }

    public function update(Request $request, Shop $shop): RedirectResponse
    {
        $shop->update($this->validated($request, $shop));

        return redirect()->route('admin.shops.show', $shop)->with('status', 'Shop updated.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Shop $shop = null): array
    {
        return $request->validate([
            'mall_id' => ['required', 'exists:malls,id'],
            'block_id' => ['nullable', 'exists:blocks,id'],
            'floor_id' => ['nullable', 'exists:floors,id'],
            'shop_code' => ['required', 'string', 'max:50', Rule::unique('shops')->ignore($shop)],
            'shop_number' => ['required', 'string', 'max:50'],
            'shop_type' => ['required', 'string', 'max:50'],
            'size_sqm' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(Shop::STATUSES)],
            'annual_rent' => ['required', 'numeric', 'min:0'],
            'service_charge' => ['required', 'numeric', 'min:0'],
            'other_fees' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}

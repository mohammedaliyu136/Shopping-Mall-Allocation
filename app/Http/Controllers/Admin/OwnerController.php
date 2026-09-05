<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\OwnerDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\ReferenceGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** SRS FR-040 to FR-043 - owner registration, documents and search. */
class OwnerController extends Controller
{
    public function __construct(private readonly ReferenceGenerator $references) {}

    /** FR-043 - search by name, phone, email, shop number or allocation reference. */
    public function index(Request $request): View
    {
        $owners = Owner::query()
            ->search($request->string('q')->toString())
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->withCount('allocations')
            ->orderBy('full_name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.owners.index', ['owners' => $owners]);
    }

    public function create(): View
    {
        return view('admin.owners.form', ['owner' => new Owner(['status' => 'active'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $createAccount = $request->boolean('create_portal_account');

        $owner = Owner::create($validated + ['owner_code' => $this->references->ownerCode()]);

        if ($photo = $request->file('photo')) {
            $owner->update(['photo_path' => $photo->store('owners/photos')]);
        }

        // SRS 3.2 - optionally provision the owner's self-service account.
        if ($createAccount && $owner->email) {
            $this->provisionPortalAccount($owner);
        }

        return redirect()->route('admin.owners.show', $owner)->with('status', 'Owner registered.');
    }

    public function show(Owner $owner): View
    {
        $owner->load([
            'documents.uploader',
            'allocations.shop.block',
            'invoices' => fn ($q) => $q->latest()->limit(10),
            'payments' => fn ($q) => $q->latest()->limit(10),
            'tickets' => fn ($q) => $q->latest()->limit(10),
            'user',
        ]);

        return view('admin.owners.show', [
            'owner' => $owner,
            'outstanding' => $owner->outstandingBalance(),
        ]);
    }

    public function edit(Owner $owner): View
    {
        return view('admin.owners.form', ['owner' => $owner]);
    }

    public function update(Request $request, Owner $owner): RedirectResponse
    {
        $owner->update($this->validated($request, $owner));

        if ($photo = $request->file('photo')) {
            $owner->update(['photo_path' => $photo->store('owners/photos')]);
        }

        return redirect()->route('admin.owners.show', $owner)->with('status', 'Owner updated.');
    }

    /** FR-042 - upload a document against the owner. */
    public function storeDocument(Request $request, Owner $owner): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['identification', 'business_registration', 'allocation_agreement', 'transfer', 'other'])],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $file = $request->file('file');

        $owner->documents()->create([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'file_path' => $file->store('owners/documents'),
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => Auth::id(),
        ]);

        return back()->with('status', 'Document uploaded.');
    }

    public function downloadDocument(Owner $owner, OwnerDocument $document): StreamedResponse
    {
        abort_if($document->owner_id !== $owner->id, 404);
        abort_unless(Storage::exists($document->file_path), 404, 'The stored file is no longer available.');

        return Storage::download($document->file_path, $document->original_name ?? basename($document->file_path));
    }

    private function provisionPortalAccount(Owner $owner): void
    {
        $role = Role::query()->where('slug', Role::SHOP_OWNER)->first();

        if (! $role || User::query()->where('email', $owner->email)->exists()) {
            return;
        }

        User::create([
            'name' => $owner->full_name,
            'email' => $owner->email,
            // A random password; the owner sets their own via the reset link (FR-002).
            'password' => Hash::make(Str::random(32)),
            'role_id' => $role->id,
            'owner_id' => $owner->id,
            'phone' => $owner->phone,
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Owner $owner = null): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('owners')->ignore($owner)],
            'address' => ['nullable', 'string', 'max:255'],
            'id_type' => ['nullable', Rule::in(['national_id', 'passport', 'drivers_licence', 'voters_card', 'other'])],
            'id_number' => ['nullable', 'string', 'max:100'],
            'nok_name' => ['nullable', 'string', 'max:150'],
            'nok_relationship' => ['nullable', 'string', 'max:100'],
            'nok_phone' => ['nullable', 'string', 'max:50'],
            'nok_address' => ['nullable', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'business_reg_number' => ['nullable', 'string', 'max:100'],
            'business_address' => ['nullable', 'string', 'max:255'],
            'business_nature' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);
    }
}

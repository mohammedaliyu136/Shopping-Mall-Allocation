# Configurable Ward View

A Laravel 13 implementation of the *Configurable Ward View: Build Plan* (Nov 2026).
Every ward is built from one master template and belongs to one of three fixed
types: **General**, **Critical care** or **Emergency**. Wards switch buttons, tabs, beds
and observation charts on or off from an admin screen. Nothing is laid out at runtime.

> This is a standalone application. It lives in `ward-view/` and shares nothing with
> the Shopping Mall Allocation app at the repository root.

## Try it in the browser (no install)

Open **[Ward View in GitHub Codespaces](https://codespaces.new/mohammedaliyu136/Shopping-Mall-Allocation?ref=claude/hopeful-clarke-it1wkb&devcontainer_path=.devcontainer/ward-view/devcontainer.json)**
and press **Create codespace**. It installs everything, loads the demo hospital and
starts the app. When it is done, open port 8000 from the **Ports** tab. This works
from a phone or tablet browser.

To host it at a permanent URL, `ward-view/Dockerfile` builds a container. On Render,
choose New → Web Service, pick the Docker runtime and set the root directory to
`ward-view`. Set `DEMO_PASSWORD` there, because the demo accounts include an admin.

## Quick start (local)

```bash
cd ward-view
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Open <http://127.0.0.1:8000>. Every demo account uses the password `password`. Set
`DEMO_PASSWORD` before seeding to change it.

| Email | Role | Ward |
|---|---|---|
| `admin@hospital.test` | Admin | Ward setup screen |
| `dr.okafor@hospital.test` / `nurse.bello@hospital.test` | Doctor / Nurse | Medical Ward A (general, **pilot**) |
| `dr.sani@hospital.test` / `nurse.lawal@hospital.test` | Doctor / Nurse | Surgical Ward |
| `dr.adeyemi@hospital.test` / `nurse.yusuf@hospital.test` | Doctor / Nurse | ICU (critical care, **pilot**) |
| `dr.eze@hospital.test` / `nurse.danjuma@hospital.test` | Doctor / Nurse | A&E (emergency) |

Run the tests with `php artisan test`.

## How the plan maps to the code

### Phase 1: Data model
- `App\Enums\WardType` has three fixed values: `general`, `critical_care` and `emergency`.
- `App\Models\WardConfig` (one per ward) stores the ward type, bed layout and count,
  enabled tabs, enabled buttons per role, obs chart type and frequency, rollout stage
  and a `version`.
- `App\Services\WardConfigDefaults` holds the defaults for each type, so a new ward
  works the moment it is created.

### Phase 2: Shared components
- **Buttons.** Each button is a class under `app/Ward/Actions/{Doctor,Nurse}` registered
  under a fixed key in `ActionRegistry`. Each class declares its label, the line that
  tells the user everything inside it, its role, its type selector, the ward types it
  supports, its validation and its effect. Its form is `resources/views/actions/{key}.blade.php`.
- **Tabs.** Each tab is a class under `app/Ward/Tabs` registered in `TabRegistry`.
- The patient screen reads the config and shows only the enabled keys.

| Doctor buttons | Nurse buttons |
|---|---|
| Ward Round Note: progress, problem list, nursing instructions (become nurse tasks), diet, obs frequency | Record Observations: vitals with automatic NEWS2, pain, fluid in/out, plus ventilator settings and infusion rates on critical care |
| Prescribe: new, stop or modify, IV fluids, transfusion | Administer Medication: given, delayed or missed, from the drug chart |
| Request: labs, radiology, physio/OT, dietitian, other | Nursing Note: shift, care given, wound care (sets a redress task), concern |
| Procedure: order, or book theatre (sets a nil-by-mouth task) | Collect Sample: tick off requested labs |
| Refer, Consult, Transfer: a consult keeps the patient with your team; a team transfer happens now; a ward transfer or ICU escalation waits for the receiving nurse | Alert Doctor: SBAR escalation; stays in the header until a doctor acknowledges it |
| Discharge: home, against medical advice, another facility, or deceased; starts the nurse checklist | Move Patient: bed change, accept a transfer, off ward for scan or theatre, return |
| *A&E only:* Disposition: admit, discharge or refer | *A&E only:* Triage: category sets the waiting-time target |

**Outside the button bar:**
- DNR, allergies and the critical flag are header badges you tap to edit. Doctors own
  resuscitation status and the critical flag.
- Header badges also show open alerts, pending transfers, off-ward status, obs due or
  overdue, and the falls, pressure and nutrition assessments (due or overdue; nurses
  tap to complete).
- Sign and co-sign sit inline on each entry. Saving an entry signs it, and anyone
  clinical except the author can co-sign.
- Handover and tasks are on the ward level (`/wards/{id}/handover`).
- The discharge checklist appears for nurses only once a doctor has started a
  discharge. Completing it closes the stay and frees the bed.

### Phase 3: Config loading and speed
- `WardConfigRepository::for()` caches each ward's config forever. It is loaded at
  login and on ward selection, and replaced only when an admin saves (the version
  bumps and the cache entry is cleared).
- `GET /wards/{id}/config.json` serves the config plus the resolved buttons and tabs,
  with an `ETag` of the version. `public/js/ward.js` keeps it in `localStorage` and
  sends `If-None-Match`, so it gets `304` until the config changes.
- The ward screen shell renders first, and the patient list loads separately
  (refreshed every 60 s). On the patient screen, the header and buttons render first
  and each tab loads when it is opened.
- There is no page builder. Every layout is a fixed Blade view.

### Phase 4: Ward type screens
- **General:** bed board grouped by bay, with NEWS2, alerts, allergy, DNR,
  obs-overdue, samples-pending and discharge flags on each bed, and a list of
  incoming patients.
- **Critical care:** pod board with live vitals and ventilator settings, a 1:1 nurse on
  each bed, a "My 1:1 patients" view, an hourly obs chart and a
  *Vent & Infusions* tab.
- **Emergency:** triage board sorted by category then wait, with breach highlighting
  against each category's target, *Register arrival* and disposition. Admitted
  patients stay on the board until the ward accepts them.

### Phase 5: Admin setup screen
`/admin/wards` (admin role only) has checkboxes for buttons and tabs, dropdowns for
ward type, layout, obs chart and frequency, a bed count and the rollout stage.
- Options not valid for the chosen type are hidden and also stripped on the server.
- Changing the type offers to reset everything to that type's defaults.
- Bed count changes never remove an occupied bed.
- Every change is written to an append-only log with who made it and a field-by-field
  diff (`/admin/wards/{id}/history`).

### Phase 6: Migration and rollout
```bash
php artisan wards:map-types --dry-run                  # preview
php artisan wards:map-types --pilot=MWA --pilot=ICU     # map, with two pilots
php artisan wards:map-types --type=W12=critical_care    # override a guess
```
- The command guesses each ward's type from its name or code (ICU/HDU/critical goes to
  critical care; A&E/emergency/casualty goes to emergency; everything else is
  general) and creates its default config.
- New configs start as **Not started**, which hides them from clinical staff, unless
  named as pilots or `--live` is passed.
- After the pilot, move wards to **Live** one at a time on the setup screen.

## Success checks
| Check | How it is covered |
|---|---|
| Ward screen loads in under 2 s | The shell and the async list render separately, and config comes from the cache. A test caps the patient-list query count. Locally, with demo data, a full load takes about 140 ms. |
| Any action within 2 taps | Each button opens its form in one tap, and the type selector sits inside the form. A test opens every enabled button on every ward type. |
| New ward without code changes | A test creates a ward on the admin screen and opens it as a doctor straight away. |
| Transfer and referral are easy to find | The button is labelled *Refer, Consult, Transfer* and lists all four types under the label. Each type explains inside the form whether ownership moves. |

## Adding a new button or tab later
1. Create a class extending `WardAction` (or `WardTab`) and its Blade view.
2. Add it to `ActionRegistry::ACTIONS` (or `TabRegistry::TABS`) under a new, permanent
   key.
3. Optionally add it to a type's defaults in `WardConfigDefaults`.

It then appears as a checkbox on the admin screen, and wards switch it on from there.

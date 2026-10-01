@extends('layouts.staff')

@section('title', $user->name . ' — User Details')

@section('content')

{{-- Breadcrumb --}}
<nav class="mb-5 flex items-center gap-2 text-sm text-slate-500">
    <a href="{{ route('staff.users.index') }}" class="font-medium text-emerald-700 hover:underline">Users &amp; Farmers</a>
    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    <span class="text-slate-800">{{ $user->name }}</span>
</nav>

{{-- User Hero Banner --}}
<div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-2xl font-bold text-emerald-800 uppercase shadow-sm"
                 style="{{ $user->avatar_color ? 'background-color: '.$user->avatar_color.'25; color: '.$user->avatar_color : '' }}">
                {{ substr($user->name, 0, 1) }}
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl font-bold text-slate-900">{{ $user->name }}</h1>
                    @php($roleStyle = match($user->role) {
                        'admin'      => 'bg-amber-100 text-amber-800 border-amber-200',
                        'agronomist' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                        default      => 'bg-slate-100 text-slate-700 border-slate-200',
                    })
                    <span class="inline-flex items-center rounded-full border px-3 py-0.5 text-xs font-semibold {{ $roleStyle }}">
                        {{ ucfirst($user->role) }}
                    </span>
                    @if($user->email_verified_at)
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Verified
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                            Unverified Email
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-500">{{ $user->email }} · Member since {{ $user->created_at?->format('F d, Y') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="mailto:{{ $user->email }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Send Email
            </a>
        </div>
    </div>
</div>

{{-- Activity Stats Strip --}}
<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between text-slate-400">
            <span class="text-xs font-semibold uppercase tracking-wide">Total Crop Scans</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                </svg>
            </div>
        </div>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($scanStateCounts['total']) }}</p>
        <p class="mt-1 text-xs text-slate-400">Total diagnostic scans uploaded</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between text-slate-400">
            <span class="text-xs font-semibold uppercase tracking-wide">Verified Scans</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($scanStateCounts['verified']) }}</p>
        <p class="mt-1 text-xs text-slate-400">Confirmed by expert agronomist</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between text-slate-400">
            <span class="text-xs font-semibold uppercase tracking-wide">Registered Fields</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
            </div>
        </div>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($user->farm_fields_count) }}</p>
        <p class="mt-1 text-xs text-slate-400">Farmland fields mapped</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between text-slate-400">
            <span class="text-xs font-semibold uppercase tracking-wide">Pending / Disputed</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($scanStateCounts['pending']) }}</p>
        <p class="mt-1 text-xs text-slate-400">Awaiting expert review</p>
    </div>

</div>

<div class="mb-8 grid gap-6 lg:grid-cols-3">

    {{-- ── Left 2 Columns: Farm Profile & Account Info ── --}}
    <div class="space-y-6 lg:col-span-2">

        {{-- Farm & Agronomic Profile --}}
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="font-bold text-slate-900">Farm &amp; Agronomic Profile</h2>
                <p class="mt-0.5 text-xs text-slate-500">Agricultural configuration and environmental setup for this user.</p>
            </div>
            <div class="p-6">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Farm Name</dt>
                        <dd class="mt-0.5 font-medium text-slate-800">{{ $user->farm_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Location / Region</dt>
                        <dd class="mt-0.5 font-medium text-slate-800">{{ $user->farm_location ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Farm Coordinates</dt>
                        <dd class="mt-0.5 font-mono text-xs text-slate-700">
                            @if($user->hasFarmCoordinates())
                                {{ number_format($user->farm_latitude, 5) }}, {{ number_format($user->farm_longitude, 5) }}
                            @else
                                <span class="text-slate-400 font-sans">No GPS set</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Farm Size</dt>
                        <dd class="mt-0.5 font-medium text-slate-800">
                            @if($user->farm_size_hectares > 0)
                                {{ $user->farm_size_hectares }} hectares
                            @elseif($user->farm_size_m2 > 0)
                                {{ number_format($user->farm_size_m2) }} m²
                            @else
                                <span class="text-slate-400">Not specified</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Experience Level</dt>
                        <dd class="mt-0.5">
                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-800">
                                {{ ucfirst($user->experience_level ?? 'beginner') }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Soil Type</dt>
                        <dd class="mt-0.5 font-medium text-slate-800">{{ $user->soil_type ?: 'Loamy' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Irrigation Access</dt>
                        <dd class="mt-0.5 font-medium text-slate-800">{{ ucfirst($user->irrigation_access ?? 'rain-fed') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Phone Number</dt>
                        <dd class="mt-0.5 font-medium text-slate-800">{{ $user->phone_number ?: '—' }}</dd>
                    </div>
                </dl>

                {{-- Crops cultivated --}}
                <div class="mt-5 border-t border-slate-100 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Crops Cultivated</p>
                    @if(!empty($user->crops) && is_array($user->crops))
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($user->crops as $crop)
                            <span class="inline-flex items-center rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800">
                                🌱 {{ ucfirst($crop) }}
                            </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-slate-400">No specific crops logged in profile.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Registered Fields --}}
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="font-bold text-slate-900">Registered Farm Fields ({{ $fields->count() }})</h2>
                <p class="mt-0.5 text-xs text-slate-500">Land plots managed under this farmer's account.</p>
            </div>
            @if($fields->isEmpty())
                <div class="p-8 text-center text-sm text-slate-400">
                    No farm fields created by this user yet.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50 text-xs text-slate-400 uppercase font-semibold">
                            <tr>
                                <th class="px-6 py-3">Field Name</th>
                                <th class="px-6 py-3">Crop</th>
                                <th class="px-6 py-3">Size (m²)</th>
                                <th class="px-6 py-3">Planted</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($fields as $field)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-6 py-3 font-semibold text-slate-800">{{ $field->name }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $field->crop ?: '—' }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $field->area_m2 ? number_format($field->area_m2) : '—' }}</td>
                                <td class="px-6 py-3 text-xs text-slate-400">{{ $field->planted_at ? \Carbon\Carbon::parse($field->planted_at)->format('d M Y') : '—' }}</td>
                                <td class="px-6 py-3">
                                    @if($field->active ?? true)
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Inactive</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>

    {{-- ── Right Column: Role Assignment & Activity Summary ── --}}
    <div class="space-y-6">

        {{-- Role Management Card --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-bold text-slate-900">Role &amp; Permissions</h2>
            <p class="mt-1 text-xs text-slate-500">Modify this account's platform role. Demoting your own active administrator account is restricted.</p>

            <form method="post" action="{{ route('staff.users.role', $user) }}" class="mt-5 space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Assigned Role</label>
                    <select name="role" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        @foreach(['farmer' => 'Farmer (Standard User)', 'agronomist' => 'Agronomist (Staff)', 'admin' => 'Administrator (Full Access)'] as $rKey => $rLabel)
                            <option value="{{ $rKey }}" @selected($user->role === $rKey)>
                                {{ $rLabel }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
                    <p class="font-semibold">Security note</p>
                    <p class="mt-0.5 text-amber-700">All role adjustments are cryptographically logged in the immutable audit trail.</p>
                </div>

                <button type="submit" class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    Update Account Role
                </button>
            </form>
        </div>

        {{-- Account Metadata Card --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-bold text-slate-900">System Record</h2>
            <div class="mt-4 space-y-3 text-xs text-slate-500">
                <div class="flex justify-between border-b border-slate-100 pb-2">
                    <span>User ID</span>
                    <span class="font-mono text-slate-700">#{{ $user->id }}</span>
                </div>
                <div class="flex justify-between border-b border-slate-100 pb-2">
                    <span>Registration</span>
                    <span class="text-slate-700">{{ $user->created_at?->format('d M Y, H:i') }}</span>
                </div>
                <div class="flex justify-between border-b border-slate-100 pb-2">
                    <span>Last Profile Update</span>
                    <span class="text-slate-700">{{ $user->updated_at?->format('d M Y, H:i') }}</span>
                </div>
                <div class="flex justify-between border-b border-slate-100 pb-2">
                    <span>Journal Entries</span>
                    <span class="font-semibold text-slate-700">{{ $user->journal_entries_count }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Calendar Tasks</span>
                    <span class="font-semibold text-slate-700">{{ $user->calendar_tasks_count }}</span>
                </div>
            </div>
        </div>

    </div>

</div>

{{-- Crop Scans History --}}
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-6 py-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="font-bold text-slate-900">Crop Scans History ({{ $scans->total() }})</h2>
            <p class="mt-0.5 text-xs text-slate-500">Complete record of crop images and AI disease diagnoses for this user.</p>
        </div>
        @if($scans->isNotEmpty())
        <span class="text-xs text-slate-400">
            Showing {{ $scans->firstItem() }}–{{ $scans->lastItem() }} of {{ $scans->total() }}
        </span>
        @endif
    </div>

    @if($scans->isEmpty())
        <div class="p-12 text-center text-sm text-slate-400">
            This farmer has not submitted any crop scans yet.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-100 bg-slate-50 text-xs font-semibold uppercase text-slate-400">
                    <tr>
                        <th class="px-6 py-3">Scan</th>
                        <th class="px-6 py-3">Image</th>
                        <th class="px-6 py-3">Field / Crop</th>
                        <th class="px-6 py-3">Predicted Disease</th>
                        <th class="px-6 py-3">Confidence</th>
                        <th class="px-6 py-3">State</th>
                        <th class="px-6 py-3">Submitted</th>
                        <th class="px-6 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($scans as $scan)
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-6 py-3 font-mono text-xs text-slate-500">#{{ $scan->id }}</td>
                        <td class="px-6 py-3">
                            <img src="{{ route('staff.scans.image', $scan) }}" alt="Scan thumbnail"
                                 class="h-10 w-14 rounded-lg object-cover border border-slate-200">
                        </td>
                        <td class="px-6 py-3 font-medium text-slate-800">
                            {{ $scan->farmField?->name ?? '—' }}
                            @if($scan->farmField?->crop)
                                <span class="text-xs text-slate-400 font-normal">({{ $scan->farmField->crop }})</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-slate-700">
                            {{ $scan->predictedDiseaseLabel?->name ?? $scan->disease_name ?? '—' }}
                        </td>
                        <td class="px-6 py-3 text-slate-700">
                            {{ $scan->normalized_confidence !== null ? number_format($scan->normalized_confidence * 100, 1).'%' : '—' }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap">
                            @php($stateStyle = match($scan->verification_state) {
                                'expert_verified'   => 'bg-emerald-100 text-emerald-800',
                                'disputed'          => 'bg-orange-100 text-orange-800',
                                'expert_rejected'   => 'bg-red-100 text-red-800',
                                default             => 'bg-amber-100 text-amber-800',
                            })
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $stateStyle }}">
                                {{ str_replace('_', ' ', ucfirst($scan->verification_state)) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-xs text-slate-400 whitespace-nowrap">
                            {{ $scan->created_at?->format('d M Y, H:i') }}
                        </td>
                        <td class="px-6 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('staff.scans.show', $scan) }}"
                               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm transition-colors hover:bg-emerald-50 hover:border-emerald-300">
                                Review scan →
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($scans->hasPages())
        <div class="border-t border-slate-100 px-6 py-4">
            {{ $scans->links() }}
        </div>
        @endif
    @endif
</div>

@endsection

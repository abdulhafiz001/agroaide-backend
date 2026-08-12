@extends('layouts.staff')

@section('title', 'Policies & users')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Administration</h1>
    <p class="mt-1 text-sm text-slate-500">Staff accounts, roles, and confidence policies. Restricted to administrators.</p>
</div>

{{-- Confidence policies --}}
<div class="mb-6 rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="font-semibold text-slate-900">Confidence-policy versions</h2>
        <p class="mt-0.5 text-sm text-slate-500">Immutable once created. Each version gets a SHA-256 checksum.</p>
    </div>
    <div class="p-6">
        <form method="post" action="{{ route('staff.policies.store') }}" class="grid gap-3 sm:grid-cols-5">
            @csrf
            <input name="name" required placeholder="Policy name"
                   class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
            <input name="version" required placeholder="Version (e.g. v1.2)"
                   class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
            <input name="retake_below" value="0.60" readonly
                   title="Retake threshold — fixed at 0.60"
                   class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-500 cursor-not-allowed">
            <input name="review_below" value="0.85" readonly
                   title="Review threshold — fixed at 0.85"
                   class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-500 cursor-not-allowed">
            <input type="hidden" name="require_canonical" value="1">
            <button type="submit"
                    class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-800">
                Create version
            </button>
        </form>

        @if($policies->isNotEmpty())
        <div class="mt-5 space-y-2">
            @foreach($policies as $policy)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                <div>
                    <span class="font-semibold text-slate-800">{{ $policy->name }}</span>
                    <span class="ml-1 text-slate-500">v{{ $policy->version }}</span>
                    <span class="ml-3 text-sm text-slate-500">&lt;{{ $policy->retake_below }} retake · &lt;{{ $policy->review_below }} review</span>
                </div>
                <div class="flex items-center gap-3">
                    @if($policy->active)
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Inactive</span>
                        <form method="post" action="{{ route('staff.policies.activate', $policy) }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-100">
                                Activate
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- Staff role assignment --}}
<div class="mb-6 rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="font-semibold text-slate-900">Staff role assignment</h2>
        <p class="mt-0.5 text-sm text-slate-500">Administrators cannot demote their own active account.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Name</th>
                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Email</th>
                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Role</th>
                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Assign</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($users as $user)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-3 font-medium text-slate-800">{{ $user->name }}</td>
                    <td class="px-6 py-3 text-slate-500">{{ $user->email }}</td>
                    <td class="px-6 py-3">
                        @php($roleColor = match($user->role) {
                            'admin'      => 'bg-amber-100 text-amber-800',
                            'agronomist' => 'bg-emerald-100 text-emerald-800',
                            default      => 'bg-slate-100 text-slate-700',
                        })
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $roleColor }}">
                            {{ ucfirst($user->role) }}
                        </span>
                    </td>
                    <td class="px-6 py-3">
                        <form method="post" action="{{ route('staff.users.role', $user) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <select name="role"
                                    class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                @foreach(['farmer', 'agronomist', 'admin'] as $role)
                                <option value="{{ $role }}" @selected($user->role === $role)>{{ ucfirst($role) }}</option>
                                @endforeach
                            </select>
                            <button type="submit"
                                    class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-100">
                                Save
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-100 px-6 py-3">
        {{ $users->links() }}
    </div>
</div>

{{-- Link to audit --}}
<div class="flex justify-end">
    <a href="{{ route('staff.audit') }}" class="text-sm font-medium text-emerald-700 hover:underline">
        View full audit log →
    </a>
</div>

@endsection

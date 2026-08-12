<?php

namespace App\Http\Controllers;

use App\Jobs\RunEvaluation;
use App\Models\CanonicalLabel;
use App\Models\ConfidencePolicy;
use App\Models\EvaluationDataset;
use App\Models\EvaluationRun;
use App\Models\FarmImageAnalysis;
use App\Models\ModelVersion;
use App\Models\PromptVersion;
use App\Models\ScanFeedback;
use App\Models\ScanReview;
use App\Models\User;
use App\Services\FarmImageAnalysisService;
use App\Services\ScanVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    // ─── Auth ──────────────────────────────────────────────────────────────

    public function login(): View
    {
        return view('staff.login', [
            'needsSetup' => ! User::query()->whereIn('role', ['admin', 'agronomist'])->exists(),
        ]);
    }

    public function setup(): View|RedirectResponse
    {
        if (User::query()->whereIn('role', ['admin', 'agronomist'])->exists()) {
            return redirect()->route('staff.login');
        }

        return view('staff.setup');
    }

    public function storeSetup(Request $request): RedirectResponse
    {
        if (User::query()->whereIn('role', ['admin', 'agronomist'])->exists()) {
            return redirect()->route('staff.login')
                ->withErrors(['email' => 'Staff setup is already complete. Sign in instead.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'role' => 'admin',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('staff.dashboard');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        if (! $request->user()?->isStaff()) {
            Auth::logout();
            $request->session()->invalidate();

            return back()->withErrors(['email' => 'Staff access is required.']);
        }

        return redirect()->intended(route('staff.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login');
    }

    // ─── Dashboard ─────────────────────────────────────────────────────────

    public function dashboard(): View
    {
        $latestRun = DB::table('evaluation_runs')->where('status', 'completed')->latest('completed_at')->first();
        $latestAccuracy = $latestRun && $latestRun->metrics
            ? data_get(json_decode($latestRun->metrics, true), 'accuracy')
            : null;

        $pendingScans = FarmImageAnalysis::whereIn('verification_state', ['pending_review', 'disputed'])->count();

        $outbreakCount = DB::table('outbreak_events')->where('distinct_farmer_count', '>=', 3)->count();

        $feedbackCount = DB::table('scan_feedback')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $recentScans = FarmImageAnalysis::with(['predictedDiseaseLabel', 'farmField:id,name,crop'])
            ->select(['id', 'farm_field_id', 'predicted_disease_label_id', 'disease_name', 'normalized_confidence', 'verification_state', 'created_at'])
            ->whereIn('verification_state', ['pending_review', 'disputed'])
            ->latest()
            ->limit(5)
            ->get();

        $activeFarmCount = $this->activeFarmCount();

        return view('staff.dashboard', compact(
            'latestAccuracy', 'pendingScans', 'outbreakCount', 'feedbackCount',
            'recentScans', 'activeFarmCount',
        ));
    }

    // ─── Scan review ───────────────────────────────────────────────────────

    public function scans(): View
    {
        $queue = FarmImageAnalysis::with(['predictedDiseaseLabel', 'farmField:id,name,crop'])
            ->select(['id', 'farm_field_id', 'predicted_disease_label_id', 'disease_name',
                'normalized_confidence', 'verification_state', 'created_at'])
            ->whereIn('verification_state', ['pending_review', 'disputed'])
            ->latest()
            ->paginate(24);

        return view('staff.scans.index', compact('queue'));
    }

    public function scanShow(int $scan): View
    {
        $scan = FarmImageAnalysis::with([
            'farmField:id,name,crop',
            'predictedCropLabel:id,name,kind',
            'predictedDiseaseLabel:id,name,kind',
            'effectiveDiseaseLabel:id,name,kind',
            'reviews',
            'feedback',
        ])->findOrFail($scan);

        Gate::authorize('view', $scan);

        // Load review history actors
        $scan->reviews->load('actor:id,name');

        $cropLabels = CanonicalLabel::where('kind', 'crop')->where('active', true)->orderBy('name')->get(['id', 'name']);
        $diseaseLabels = CanonicalLabel::whereIn('kind', ['disease', 'condition'])->where('active', true)->orderBy('name')->get(['id', 'name', 'kind']);
        $feedback = $scan->feedback()->latest()->get();

        // Map result_json to human-readable labels
        $resultFields = $this->mapResultFields($scan->result_json ?? []);

        return view('staff.scans.show', compact('scan', 'cropLabels', 'diseaseLabels', 'feedback', 'resultFields'));
    }

    public function scanImage(Request $request, int $scan)
    {
        $record = FarmImageAnalysis::findOrFail($scan);
        Gate::authorize('view', $record);

        return app(FarmImageAnalysisService::class)
            ->getImageResponseForUser($record->user, $scan)
            ?? abort(404);
    }

    public function review(Request $request, int $scan, ScanVerificationService $verification): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:confirm,correct,reject,reopen'],
            'crop_label_id' => ['nullable', 'exists:canonical_labels,id'],
            'disease_label_id' => ['nullable', 'exists:canonical_labels,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $record = FarmImageAnalysis::findOrFail($scan);
        Gate::authorize('review', $record);
        $this->validateCorrectionLabels($data, $record);
        $from = $record->verification_state;
        $to = match ($data['action']) {
            'confirm', 'correct' => 'expert_verified',
            'reject' => 'expert_rejected',
            'reopen' => 'pending_review',
        };
        $verification->transition(
            $record, $to, $request->user(),
            $data['crop_label_id'] ?? null, $data['disease_label_id'] ?? null, $data['reason'] ?? null,
        );
        DB::table('audit_logs')->insert([
            'actor_user_id' => $request->user()->id,
            'action' => 'scan.review.'.$data['action'],
            'subject_type' => FarmImageAnalysis::class,
            'subject_id' => $record->id,
            'safe_context' => json_encode(['from' => $from, 'to' => $to]),
            'request_fingerprint' => hash('sha256', (string) $request->ip()),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'Review saved.');
    }

    // ─── Farmer feedback ───────────────────────────────────────────────────

    public function feedback(): View
    {
        $feedback = ScanFeedback::with(['analysis:id,farm_field_id,verification_state'])
            ->select(['id', 'farm_image_analysis_id', 'verdict', 'comment', 'created_at'])
            ->latest()
            ->paginate(40);

        $verdictSummary = DB::table('scan_feedback')
            ->select('verdict', DB::raw('count(*) as total'))
            ->groupBy('verdict')
            ->orderByDesc('total')
            ->get();

        return view('staff.feedback', compact('feedback', 'verdictSummary'));
    }

    // ─── Outbreaks ─────────────────────────────────────────────────────────

    public function outbreaks(): View
    {
        $outbreaks = DB::table('outbreak_events')
            ->join('canonical_labels', 'canonical_labels.id', '=', 'outbreak_events.canonical_label_id')
            ->select('outbreak_events.*', 'canonical_labels.name as label_name')
            ->where('distinct_farmer_count', '>=', 3)
            ->latest('period_start')
            ->paginate(50);

        $levelSummary = DB::table('outbreak_events')
            ->where('distinct_farmer_count', '>=', 3)
            ->select('level', DB::raw('count(*) as total'))
            ->groupBy('level')
            ->orderByDesc('total')
            ->get();

        return view('staff.outbreaks', compact('outbreaks', 'levelSummary'));
    }

    // ─── System health ─────────────────────────────────────────────────────

    public function health(): View
    {
        $health = DB::table('provider_health_snapshots')
            ->orderByDesc('checked_at')
            ->limit(30)
            ->get();

        $jobs = DB::table('system_job_runs')
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        return view('staff.health', compact('health', 'jobs'));
    }

    // ─── Advanced: evaluations ─────────────────────────────────────────────

    public function evaluations(): View
    {
        $datasets = EvaluationDataset::withCount('items')->latest()->get();
        $runs = EvaluationRun::latest()->limit(20)->get();

        return view('staff.evaluations', compact('datasets', 'runs'));
    }

    public function dataset(EvaluationDataset $dataset): View
    {
        $dataset->loadCount('items');
        $runs = EvaluationRun::where('evaluation_dataset_id', $dataset->id)->latest()->get();

        return view('staff.dataset', compact('dataset', 'runs'));
    }

    public function run(EvaluationRun $run): View
    {
        $metrics = DB::table('evaluation_class_metrics')
            ->join('canonical_labels', 'canonical_labels.id', '=', 'evaluation_class_metrics.canonical_label_id')
            ->where('evaluation_run_id', $run->id)
            ->select('evaluation_class_metrics.*', 'canonical_labels.name as label_name')
            ->orderBy('canonical_labels.name')
            ->get();

        return view('staff.run', compact('run', 'metrics'));
    }

    public function compare(Request $request): View
    {
        $ids = $request->validate(['runs' => ['required', 'array', 'min:2', 'max:5'], 'runs.*' => ['integer', 'distinct']])['runs'];
        $runs = EvaluationRun::whereIn('id', $ids)->orderBy('id')->get();
        if ($runs->count() !== count($ids)) {
            abort(404);
        }

        return view('staff.compare', compact('runs'));
    }

    // ─── Admin ─────────────────────────────────────────────────────────────

    public function admin(): View
    {
        Gate::authorize('administer', User::class);
        $policies = ConfidencePolicy::latest()->get();
        $users = User::select('id', 'name', 'email', 'role')->orderBy('name')->paginate(100);

        return view('staff.admin', compact('policies', 'users'));
    }

    public function audit(): View
    {
        Gate::authorize('administer', User::class);
        $events = DB::table('audit_logs')->latest()->paginate(100);

        return view('staff.audit', compact('events'));
    }

    public function queueRun(Request $request, EvaluationDataset $dataset): RedirectResponse
    {
        Gate::authorize('administer', User::class);
        if (! $dataset->locked_at || ! $dataset->items()->exists()) {
            throw ValidationException::withMessages(['dataset' => 'Only locked datasets with imported items are ready to run.']);
        }
        $model = ModelVersion::where('active', true)->latest('id')->firstOrFail();
        $prompt = PromptVersion::where('active', true)->latest('id')->firstOrFail();
        $policy = ConfidencePolicy::where('active', true)->latest('id')->firstOrFail();
        $run = EvaluationRun::create([
            'evaluation_dataset_id' => $dataset->id,
            'model_version_id' => $model->id,
            'prompt_version_id' => $prompt->id,
            'confidence_policy_id' => $policy->id,
            'created_by' => $request->user()->id,
            'status' => 'queued',
            'sample_count' => $dataset->items()->count(),
        ]);
        RunEvaluation::dispatch($run->id)->onQueue('evaluation');
        $this->writeAudit($request, 'evaluation.run.queued', $run);

        return redirect()->route('staff.evaluations.runs.show', $run)->with('status', 'Evaluation run queued.');
    }

    public function createPolicy(Request $request): RedirectResponse
    {
        Gate::authorize('administer', User::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:64', Rule::unique('confidence_policies')->where('name', $request->input('name'))],
            'retake_below' => ['required', 'numeric', 'in:0.6'],
            'review_below' => ['required', 'numeric', 'in:0.85'],
            'require_canonical' => ['required', 'boolean'],
        ]);
        $policy = ConfidencePolicy::create([
            ...$data,
            'checksum' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)),
            'active' => false,
        ]);
        $this->writeAudit($request, 'confidence_policy.created', $policy);

        return back()->with('status', 'Immutable confidence-policy version created.');
    }

    public function activatePolicy(Request $request, ConfidencePolicy $policy): RedirectResponse
    {
        Gate::authorize('administer', User::class);
        DB::transaction(function () use ($policy): void {
            DB::table('confidence_policies')->where('active', true)->update(['active' => false, 'updated_at' => now()]);
            DB::table('confidence_policies')->where('id', $policy->id)->update(['active' => true, 'updated_at' => now()]);
        });
        $this->writeAudit($request, 'confidence_policy.activated', $policy);

        return back()->with('status', 'Confidence policy activated.');
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('administer', User::class);
        $data = $request->validate(['role' => ['required', Rule::in(['farmer', 'agronomist', 'admin'])]]);
        if ($request->user()->is($user) && $data['role'] !== 'admin') {
            throw ValidationException::withMessages(['role' => 'Administrators cannot demote their own active account.']);
        }
        $from = $user->role;
        $user->update(['role' => $data['role']]);
        $this->writeAudit($request, 'staff.role.assigned', $user, ['from' => $from, 'to' => $data['role']]);

        return back()->with('status', 'Staff role updated.');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    private function activeFarmCount(): int
    {
        $cutoff = now()->subDays(30);

        return collect()
            ->merge(DB::table('farm_image_analyses')->where('created_at', '>=', $cutoff)->pluck('user_id'))
            ->merge(DB::table('journal_entries')->where('created_at', '>=', $cutoff)->pluck('user_id'))
            ->merge(DB::table('calendar_tasks')->where('completed', true)->where('completed_at', '>=', $cutoff)->pluck('user_id'))
            ->merge(DB::table('field_transactions')->where('created_at', '>=', $cutoff)->pluck('user_id'))
            ->filter()->unique()->count();
    }

    /**
     * Map result_json keys to human-readable display labels.
     * Renders any string/numeric leaf values, skipping raw arrays.
     *
     * @param  array<string, mixed>  $resultJson
     * @return array<string, string>
     */
    private function mapResultFields(array $resultJson): array
    {
        $labelMap = [
            'summary' => 'AI summary',
            'personalized_note' => 'Personalised note',
            'treatment' => 'Treatment',
            'treatment_recommendation' => 'Treatment recommendation',
            'recommendations' => 'Recommendations',
            'severity' => 'Severity',
            'spread_risk' => 'Spread risk',
            'confidence_explanation' => 'Confidence explanation',
            'next_steps' => 'Next steps',
        ];

        $fields = [];
        foreach ($resultJson as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_filter(array_values($value), fn ($v) => is_scalar($v)));
            }
            if (! is_scalar($value) || $value === null || $value === '') {
                continue;
            }
            $label = $labelMap[$key] ?? ucwords(str_replace('_', ' ', $key));
            $fields[$label] = (string) $value;
        }

        return $fields;
    }

    private function validateCorrectionLabels(array $data, FarmImageAnalysis $scan): void
    {
        if (($data['action'] ?? null) !== 'correct') {
            return;
        }
        $cropId = $data['crop_label_id'] ?? $scan->effective_crop_label_id;
        $diseaseId = $data['disease_label_id'] ?? $scan->effective_disease_label_id;
        $crop = $cropId ? CanonicalLabel::find($cropId) : null;
        $disease = $diseaseId ? CanonicalLabel::find($diseaseId) : null;
        $errors = [];
        if (! $crop || $crop->kind !== 'crop') {
            $errors['crop_label_id'] = 'Select a canonical crop label.';
        }
        if (! $disease || ! in_array($disease->kind, ['disease', 'condition'], true)) {
            $errors['disease_label_id'] = 'Select a canonical disease or condition label.';
        } elseif ($disease->crop_label_id && $crop && $disease->crop_label_id !== $crop->id) {
            $errors['disease_label_id'] = 'The selected disease is not associated with the selected crop.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function writeAudit(Request $request, string $action, object $subject, array $context = []): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'safe_context' => $context ? json_encode($context) : null,
            'request_fingerprint' => hash('sha256', (string) $request->ip()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

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
use App\Models\User;
use App\Services\FarmImageAnalysisService;
use App\Services\ScanVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function login(): View
    {
        return view('staff.login');
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

    public function dashboard(): View
    {
        $latestRun = DB::table('evaluation_runs')->where('status', 'completed')->latest('completed_at')->first();
        $queue = FarmImageAnalysis::with(['predictedDiseaseLabel', 'farmField:id,name,crop'])
            ->select([
                'id', 'farm_field_id', 'predicted_disease_label_id', 'disease_name',
                'normalized_confidence', 'verification_state',
            ])
            ->whereIn('verification_state', ['pending_review', 'disputed'])
            ->latest()->limit(50)->get();
        $feedback = DB::table('scan_feedback')
            ->select('farm_image_analysis_id', 'verdict', 'created_at')
            ->latest()->limit(30)->get();
        $datasets = DB::table('evaluation_datasets')->latest()->get();
        $runs = DB::table('evaluation_runs')->latest()->limit(30)->get();
        $classMetrics = $latestRun
            ? DB::table('evaluation_class_metrics')
                ->join('canonical_labels', 'canonical_labels.id', '=', 'evaluation_class_metrics.canonical_label_id')
                ->where('evaluation_run_id', $latestRun->id)
                ->select('evaluation_class_metrics.*', 'canonical_labels.name as label_name')->get()
            : collect();
        $outbreaks = DB::table('outbreak_events')->where('distinct_farmer_count', '>=', 3)->latest()->limit(30)->get();
        $health = DB::table('provider_health_snapshots')->latest('checked_at')->limit(20)->get();
        $jobs = DB::table('system_job_runs')->latest()->limit(30)->get();
        $labels = DB::table('canonical_labels')->where('active', true)->orderBy('kind')->orderBy('name')->get();
        $activeFarmCount = $this->activeFarmCount();
        $isAdmin = request()->user()->isAdmin();

        return view('staff.dashboard', compact(
            'latestRun', 'queue', 'feedback', 'datasets', 'runs', 'classMetrics',
            'outbreaks', 'health', 'jobs', 'labels', 'activeFarmCount', 'isAdmin',
        ));
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

    public function audit(): View
    {
        Gate::authorize('administer', User::class);
        $events = DB::table('audit_logs')->latest()->paginate(100);

        return view('staff.audit', compact('events'));
    }

    public function admin(): View
    {
        Gate::authorize('administer', User::class);
        $policies = ConfidencePolicy::latest()->get();
        $users = User::select('id', 'name', 'email', 'role')->orderBy('name')->paginate(100);
        $datasets = EvaluationDataset::withCount('items')->latest()->get();

        return view('staff.admin', compact('policies', 'users', 'datasets'));
    }

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

<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApplicationResource;
use App\Models\LodgeApplication;
use App\Models\Member;
use App\Models\Page;
use App\Services\ApplicationCounts;
use App\Services\ApplicationNotificationService;
use App\Services\ApplicationService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ApiApplicationController extends Controller
{
    public function counts(ApplicationCounts $counts)
    {
        return response()->json($counts->get());
    }

    public function dashboard(ApplicationCounts $counts)
    {
        $applications = $counts->get();
        $members = Member::query()->selectRaw("COUNT(*) AS total, COUNT(CASE WHEN status = 'active' THEN 1 END) AS active")->first();

        return response()->json(['counts' => ['total members' => (int) $members->total, 'active members' => (int) $members->active, 'pending applications' => $applications['pending'], 'unread applications' => $applications['unread'], 'applications this month' => $applications['this_month']], 'recentApplications' => LodgeApplication::latest('submitted_at')->limit(8)->get(['id', 'reference_number', 'first_name', 'last_name', 'submitted_at', 'status', 'is_read_by_admin']), 'activity' => DB::table('activity_logs')->latest()->limit(12)->get(), 'pages' => Page::all(['id', 'slug', 'name'])]);
    }

    public function index(Request $r)
    {
        $data = $r->validate(['search' => 'nullable|string|max:255', 'status' => 'nullable|in:pending,under_review,approved,rejected', 'unread' => 'nullable|boolean', 'sort' => 'nullable|in:submitted_at,reference_number,last_name,status', 'direction' => 'nullable|in:asc,desc']);
        $q = LodgeApplication::query()->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($r->boolean('unread'), fn ($q) => $q->where('is_read_by_admin', false))->when($data['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->whereLike('reference_number', "%$v%")->orWhereLike('first_name', "%$v%")->orWhereLike('last_name', "%$v%")->orWhereLike('email', "%$v%")))->orderBy($data['sort'] ?? 'submitted_at', $data['direction'] ?? 'desc');
        $p = $q->paginate(20)->withQueryString();
        $p->setCollection(collect(ApplicationResource::collection($p->getCollection())->resolve()));

        return response()->json(['applications' => $p]);
    }

    public function show(LodgeApplication $application)
    {
        if (! $application->is_read_by_admin) {
            $application->update(['is_read_by_admin' => true]);
        }

        return response()->json(['application' => (new ApplicationResource($application))->resolve()]);
    }

    public function status(Request $r, LodgeApplication $application)
    {
        $application->update($r->validate(['status' => 'required|in:pending,under_review,approved,rejected', 'admin_notes' => 'sometimes|nullable|string|max:10000']) + ['reviewed_at' => now()]);
        Audit::log('Application Status Changed', ['application_id' => $application->id]);

        return response()->json(['message' => 'Application status updated.']);
    }

    public function notes(Request $r, LodgeApplication $application)
    {
        $application->update($r->validate(['admin_notes' => 'nullable|string|max:10000']));

        return response()->json(['message' => 'Private notes saved.']);
    }

    public function markRead(LodgeApplication $application)
    {
        $application->update(['is_read_by_admin' => true]);

        return response()->json(['message' => 'Application marked read.']);
    }

    public function convert(LodgeApplication $application, ApplicationService $service)
    {
        $member = $service->convert($application);

        return response()->json(['message' => 'Application approved and a private member record created.', 'member_id' => $member->id], 201);
    }

    public function resend(LodgeApplication $application, ApplicationNotificationService $service)
    {
        $sent = $service->send($application, true);

        return response()->json(['message' => $sent ? 'Email notification sent.' : 'Delivery failed. The application remains safely saved.', 'sent' => $sent]);
    }

    public function testEmail(ApplicationNotificationService $service)
    {
        try {
            $recipient = $service->recipient();
            abort_unless(filter_var($recipient, FILTER_VALIDATE_EMAIL), 422, 'Configure an application notification email first.');
            Mail::raw('This is a test email from the Golden Friendship Masonic Lodge No. 40 website. If you received this message, email notifications are configured correctly.', fn ($m) => $m->to($recipient)->subject('Golden Friendship Lodge Website — Test Email'));

            return response()->json(['message' => 'Test email sent.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Test email failed. Check the configured recipient and server mail settings.'], 422);
        }
    }
}

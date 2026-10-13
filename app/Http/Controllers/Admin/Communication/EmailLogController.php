<?php

namespace App\Http\Controllers\Admin\Communication;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Store;
use App\Models\EmailLog;
use App\Mail\CustomUserMail;
use Illuminate\Http\Request;
use App\Models\EmailTemplate;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SendCustomerEmailJob;

class EmailLogController extends Controller
{

    public function index(Request $request)
    {

        if ($request->ajax()) {
            $data = EmailLog::with('user')->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('body', function ($data) {
                    return $data->body ?? 'N/A';
                })
                ->addColumn('user', function ($data) {
                    return $data->user->name ?? 'N/A';
                })
                ->addColumn('status', function ($data) {
                    $status = ucfirst($data->status ?? 'pending'); // Capital first letter

                    switch (strtolower($status)) {
                        case 'success':
                            $class = 'btn btn-success btn-sm';
                            break;
                        case 'pending':
                            $class = 'btn btn-warning btn-sm';
                            break;
                        case 'failed':
                            $class = 'btn btn-danger btn-sm';
                            break;
                        default:
                            $class = 'btn btn-info btn-sm';
                    }

                    return "<button type='button' class='{$class}'>{$status}</button>";
                })
                // action buttons
                ->addColumn('action', function ($data) {
                    $buttons = '<div class="btn-group btn-group-sm" role="group" aria-label="Offer Actions">';
                    $buttons .= '<button type="button" onclick="showDeleteConfirm(' . $data->id . ')" class="btn btn-danger text-white" title="Delete"><i class="fe fe-trash"></i></button>';
                    $buttons .= '</div>';
                    return $buttons;
                })

                ->rawColumns(['action', 'body', 'status'])
                ->make(true);
        }

        return view('backend.layouts.EmailLog.index');
    }

    public function create()
    {
        $users = User::all();

        $templates = EmailTemplate::all();

        return view('backend.layouts.EmailLog.create', compact('users', 'templates'));
    }

    public function sendBulkMail(Request $request)
    {
        $request->validate([
            'user_ids'       => 'nullable|array',
            'template_id'    => 'required|exists:email_templates,id',
            'schedule_time'  => 'nullable|date',
        ]);

        $template = EmailTemplate::findOrFail($request->template_id);
        $scheduleTime = $request->schedule_time ? Carbon::parse($request->schedule_time) : null;

        // ------------------------
        // Fetch Users
        // ------------------------
        $users = collect();
        if (!empty($request->user_ids)) {
            $users = User::whereIn('id', $request->user_ids)->get();
        }


        // ------------------------
        // Send Emails to Users
        // ------------------------
        foreach ($users as $user) {
            $subject = $this->parseTemplate($template->subject, $user);
            $body    = $this->parseTemplate($template->body, $user);

            $log = EmailLog::create([
                'template_id' => $template->id,
                'user_id'     => $user->id,
                'status'      => 'pending',
                'subject'     => $subject,
                'body'        => $body,
            ]);

            try {
                $mailable = new CustomUserMail($subject, $body);
                
                if ($scheduleTime && $scheduleTime->isFuture()) {
                    SendCustomerEmailJob::dispatch($mailable, $user->email, 'bulk_mail_' . $log->id)
                        ->delay($scheduleTime);

                    $log->update([
                        'status'  => 'scheduled',
                        'sent_at' => $scheduleTime,
                    ]);
                } else {
                    \Illuminate\Support\Facades\Mail::to($user->email)->send($mailable);
                    
                    $log->update([
                        'status'  => 'sent',
                        'sent_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                $log->update(['status' => 'failed']);
                // Continue to next user/store instead of stopping all
                Log::error("Email failed for User ID {$user->id}: {$e->getMessage()}");
            }
        }
        return redirect()->route('email.index')->with('t-success', 'Emails have been sent or scheduled successfully!');
    }


    private function parseTemplate($text, $user)
    {
        $latestPayment = $user->payment()->latest()->first();

        $replacements = [
            '{{name}}' => $user->name,
            '{{email}}' => $user->email,
            '{{plan_name}}' => optional($latestPayment->plan)->name ?? '',
            '{{start_date}}' => optional($latestPayment)->start_date
                ? Carbon::parse($latestPayment->start_date)->format('d M Y')
                : '',
            '{{end_date}}' => optional($latestPayment)->end_date
                ? Carbon::parse($latestPayment->end_date)->format('d M Y')
                : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    public function destroy($id)
    {
        $data = EmailLog::findOrFail($id);
        $data->delete();
        return response([
            't-success' => true,
            'message' => 'Data Deleted Successfully'
        ]);
    }
}



@extends('mail.layout')

@section('title', 'New membership application')
@section('preview', 'A new membership application is ready for review. Reference: '.$reference)

@section('content')
    <p style="margin:0 0 12px;color:#8a661d;font-size:11px;line-height:18px;font-weight:bold;letter-spacing:1px;">ADMINISTRATOR NOTIFICATION</p>
    <h1 style="margin:0 0 18px;color:#19283b;font-family:Georgia,'Times New Roman',serif;font-size:28px;line-height:35px;font-weight:normal;">A new application awaits review.</h1>
    <p style="margin:0 0 22px;color:#465469;font-size:15px;line-height:24px;">A membership application has been submitted. Sign in to the administration dashboard to review the applicant's details.</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#faf7ee;border:1px solid #e5d6b1;">
        <tr><td style="padding:20px;">
            <p style="margin:0 0 8px;color:#715724;font-size:11px;line-height:18px;font-weight:bold;letter-spacing:1px;">APPLICATION REFERENCE</p>
            <p style="margin:0 0 18px;color:#19283b;font-family:Georgia,'Times New Roman',serif;font-size:24px;line-height:32px;overflow-wrap:anywhere;word-break:break-word;">{{ $reference }}</p>
            <p style="margin:0 0 12px;color:#465469;font-size:14px;line-height:22px;overflow-wrap:anywhere;word-break:break-word;"><strong style="color:#19283b;">Applicant</strong><br>{{ $applicant }}</p>
            <p style="margin:0 0 12px;color:#465469;font-size:14px;line-height:22px;"><strong style="color:#19283b;">Submitted</strong><br>{{ $submitted }}</p>
            <p style="margin:0;color:#465469;font-size:14px;line-height:22px;"><strong style="color:#19283b;">Status</strong><br>Pending review</p>
        </td></tr>
    </table>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;">
        <tr><td bgcolor="#19283b" style="border:1px solid #19283b;border-radius:4px;">
            <a href="{{ $reviewUrl }}" style="display:inline-block;padding:14px 22px;color:#ffffff;font-size:14px;line-height:20px;font-weight:bold;text-decoration:none;">Review application</a>
        </td></tr>
    </table>
    <p style="margin:16px 0 0;color:#647084;font-size:12px;line-height:20px;">Administrator login is required. If the button does not work, open this link:<br><a href="{{ $reviewUrl }}" style="color:#365779;overflow-wrap:anywhere;word-break:break-all;">{{ $reviewUrl }}</a></p>
@endsection

@section('footer', 'This notification was sent to the configured application notification recipient. Please handle applicant information confidentially.')

@extends('mail.layout')

@section('title', 'Application received')
@section('preview', 'Your membership application has been received. Reference: '.$reference)

@section('content')
    <p style="margin:0 0 12px;color:#8a661d;font-size:11px;line-height:18px;font-weight:bold;letter-spacing:1px;">MEMBERSHIP APPLICATION</p>
    <h1 style="margin:0 0 18px;color:#19283b;font-family:Georgia,'Times New Roman',serif;font-size:28px;line-height:35px;font-weight:normal;">Your application is received.</h1>
    <p style="margin:0 0 22px;color:#465469;font-size:15px;line-height:24px;">Thank you for your interest in Golden Friendship Masonic Lodge No. 40. We have received your membership application.</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#faf7ee;border:1px solid #e5d6b1;">
        <tr><td style="padding:20px;">
            <p style="margin:0 0 8px;color:#715724;font-size:11px;line-height:18px;font-weight:bold;letter-spacing:1px;">YOUR APPLICATION REFERENCE</p>
            <p style="margin:0;color:#19283b;font-family:Georgia,'Times New Roman',serif;font-size:24px;line-height:32px;overflow-wrap:anywhere;word-break:break-word;">{{ $reference }}</p>
            <p style="margin:10px 0 0;color:#647084;font-size:12px;line-height:19px;">Please keep this number for your records and quote it when contacting the lodge about your application.</p>
        </td></tr>
    </table>
    <h2 style="margin:24px 0 10px;color:#19283b;font-size:16px;line-height:24px;">What happens next?</h2>
    <p style="margin:0 0 12px;color:#465469;font-size:15px;line-height:24px;">Your application is pending review. The lodge will contact you if further information or a next step is required.</p>
    <p style="margin:0;color:#647084;font-size:13px;line-height:21px;">This acknowledgment confirms receipt of your application and does not confirm membership approval.</p>
@endsection

@section('footer', 'You received this email because a membership application was submitted using your email address.')

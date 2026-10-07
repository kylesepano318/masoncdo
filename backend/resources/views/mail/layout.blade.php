<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background-color:#f2f3f5;color:#19283b;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
    <div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">@yield('preview')</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f2f3f5;">
        <tr><td align="center" style="padding:24px 12px;">
            <!--[if mso]><table role="presentation" width="600" align="center"><tr><td><![endif]-->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background-color:#ffffff;border:1px solid #e0e4e9;">
                <tr><td align="center" style="padding:28px 24px;background-color:#101827;border-top:4px solid #c69b42;">
                    <img src="{{ rtrim(config('lodge.frontend_url'), '/') }}/images/golden-friendship-lodge-no-40.png" width="88" height="88" alt="Golden Friendship lodge emblem" style="display:block;width:88px;height:88px;object-fit:contain;margin:0 auto 16px;border:0;">
                    <p style="margin:0 0 6px;color:#ffffff;font-family:Georgia,'Times New Roman',serif;font-size:25px;line-height:32px;">Golden Friendship</p>
                    <p style="margin:0;color:#e1c481;font-size:12px;line-height:20px;letter-spacing:1px;">MASONIC LODGE NO. 40</p>
                    <p style="margin:8px 0 0;color:#c9d1dd;font-size:12px;line-height:18px;">Cagayan de Oro City</p>
                </td></tr>
                <tr><td style="padding:30px 24px;">@yield('content')</td></tr>
                <tr><td style="padding:20px 24px;background-color:#f8f7f3;border-top:1px solid #e7e3d8;">
                    <p style="margin:0 0 6px;color:#39485b;font-size:12px;line-height:19px;">Golden Friendship Masonic Lodge No. 40<br>Cagayan de Oro City</p>
                    <p style="margin:0;color:#647084;font-size:12px;line-height:19px;">@yield('footer')</p>
                </td></tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td></tr>
    </table>
</body>
</html>

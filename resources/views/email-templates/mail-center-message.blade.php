<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mailSubject }}</title>
</head>
<body style="margin:0;padding:0;background:#f7fbff;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;background:#f7fbff;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:100%;max-width:640px;border-collapse:collapse;background:#ffffff;">
                <tr>
                    <td style="padding:28px 24px 12px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:24px;color:#334257;">
                        {!! $html !!}
                    </td>
                </tr>
                <tr>
                    <td>
                        @include('email-templates.partials.fastxhorizon-footer')
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

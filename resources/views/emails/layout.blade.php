<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title ?? 'AgroAide' }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f0e6d4;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#171b16;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f0e6d4;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background-color:#f9f7f2;border-radius:24px;overflow:hidden;border:1px solid #e5dcc8;">
          <tr>
            <td style="background:linear-gradient(135deg,#57b346 0%,#2c5c2a 100%);padding:28px 32px;">
              <p style="margin:0;font-size:13px;letter-spacing:1.5px;text-transform:uppercase;color:#d7f5ce;font-weight:600;">AgroAide</p>
              <h1 style="margin:8px 0 0;font-size:26px;line-height:1.25;color:#ffffff;font-weight:700;">{{ $heading }}</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:32px;">
              {!! $slot !!}
            </td>
          </tr>
          <tr>
            <td style="padding:0 32px 28px;">
              <p style="margin:0;font-size:12px;line-height:1.5;color:#7b5e36;">
                You’re receiving this because you use AgroAide. If you didn’t expect this message, you can safely ignore it.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>

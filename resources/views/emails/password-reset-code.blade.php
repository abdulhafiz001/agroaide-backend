@php
  $heading = 'Your recovery code';
  $title = 'AgroAide recovery code';
@endphp
@component('emails.layout', ['heading' => $heading, 'title' => $title])
  <p style="margin:0 0 16px;font-size:16px;line-height:1.55;color:#171b16;">
    Hi {{ $name }},
  </p>
  <p style="margin:0 0 20px;font-size:16px;line-height:1.55;color:#171b16;">
    Use this code to reset your AgroAide password. It expires in <strong>{{ $expiresInMinutes }} minutes</strong>.
  </p>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px;">
    <tr>
      <td align="center" style="background-color:#eef8ea;border:1px solid #b7dfad;border-radius:16px;padding:22px 16px;">
        <p style="margin:0 0 6px;font-size:12px;letter-spacing:1.4px;text-transform:uppercase;color:#2c5c2a;font-weight:600;">Recovery code</p>
        <p style="margin:0;font-size:36px;letter-spacing:10px;font-weight:700;color:#2c5c2a;font-family:Consolas,Menlo,monospace;">{{ $code }}</p>
      </td>
    </tr>
  </table>
  <p style="margin:0 0 12px;font-size:15px;line-height:1.55;color:#171b16;">
    Enter the code in the app, then choose a new password. If you didn’t request this, ignore this email — your account stays secure.
  </p>
  <p style="margin:0;font-size:13px;line-height:1.5;color:#7b5e36;">
    For your security, never share this code with anyone.
  </p>
@endcomponent

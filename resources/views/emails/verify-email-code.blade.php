@php
  $heading = 'Verify Your Email Address';
  $subtitle = 'Email Verification';
  $title = 'Verify Your AgroAide Email Address';
@endphp
@component('emails.layout', ['heading' => $heading, 'subtitle' => $subtitle, 'title' => $title])
  <p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#1e293b;">
    Hello <strong>{{ $name }}</strong>,
  </p>

  <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155;">
    Please verify that this email address belongs to you. Verifying your email protects your farm records and enables seamless account recovery if you ever change your device or forget your password.
  </p>

  <!-- OTP CODE CARD -->
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
    <tr>
      <td align="center" style="background-color:#f0fdf4;border:1.5px solid #86efac;border-radius:16px;padding:26px 20px;">
        <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#15803d;">
          Your 6-Digit Verification Code
        </p>
        <p class="code-display" style="margin:0 0 8px;font-size:40px;letter-spacing:12px;font-weight:800;color:#14532d;font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace;">
          {{ $code }}
        </p>
        <p style="margin:0;font-size:12px;font-weight:600;color:#166534;">
          ⏱️ Valid for {{ $expiresInMinutes }} minutes
        </p>
      </td>
    </tr>
  </table>

  <!-- HOW TO VERIFY -->
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;background-color:#f8fafc;border-radius:12px;padding:16px;">
    <tr>
      <td>
        <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#1e293b;">
          Quick instructions:
        </p>
        <ol style="margin:0;padding-left:20px;font-size:13px;line-height:1.6;color:#475569;">
          <li style="margin-bottom:4px;">Open the AgroAide app on your phone.</li>
          <li style="margin-bottom:4px;">Tap the verification banner on your dashboard or go to <strong>Settings &rarr; Account</strong>.</li>
          <li>Enter the 6-digit code shown above.</li>
        </ol>
      </td>
    </tr>
  </table>

  <p style="margin:0 0 20px;font-size:13px;line-height:1.6;color:#64748b;">
    <strong>Notice:</strong> If you did not create an account on AgroAide, you can safely ignore this message. No further action is required.
  </p>

  <p style="margin:0;font-size:14px;line-height:1.5;color:#475569;">
    — The AgroAide Team
  </p>
@endcomponent

@php
  $heading = 'Welcome to your farm companion';
  $title = 'Welcome to AgroAide';
@endphp
@component('emails.layout', ['heading' => $heading, 'title' => $title])
  <p style="margin:0 0 16px;font-size:16px;line-height:1.55;color:#171b16;">
    Hi {{ $name }},
  </p>
  <p style="margin:0 0 16px;font-size:16px;line-height:1.55;color:#171b16;">
    Welcome to <strong style="color:#2c5c2a;">AgroAide</strong> — built to help Nigerian farmers grow smarter, react faster, and stay ahead of the season.
  </p>
  <p style="margin:0 0 12px;font-size:15px;line-height:1.5;color:#2b2b2b;font-weight:600;">
    Here’s what you can do in the app:
  </p>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px;">
    <tr>
      <td style="padding:12px 14px;background-color:#eef8ea;border-radius:14px;">
        <p style="margin:0;font-size:14px;line-height:1.5;color:#2c5c2a;"><strong>Weather &amp; soil for your farm</strong> — forecasts tied to your exact location, not a random city.</p>
      </td>
    </tr>
    <tr><td style="height:8px;"></td></tr>
    <tr>
      <td style="padding:12px 14px;background-color:#fdecea;border-radius:14px;">
        <p style="margin:0;font-size:14px;line-height:1.5;color:#8a1c1c;"><strong>Nearby disease detection</strong> — when farmers within <strong>5km</strong> scan the same crop and the AI finds a disease that can affect yours, AgroAide warns you early with prevention tips. If many reports pile up, you get an outbreak alert.</p>
      </td>
    </tr>
    <tr><td style="height:8px;"></td></tr>
    <tr>
      <td style="padding:12px 14px;background-color:#fff4e6;border-radius:14px;">
        <p style="margin:0;font-size:14px;line-height:1.5;color:#7b5e36;"><strong>AI farm advisor</strong> — ask questions with your crops, tasks, and weather already in context.</p>
      </td>
    </tr>
    <tr><td style="height:8px;"></td></tr>
    <tr>
      <td style="padding:12px 14px;background-color:#eaf3fc;border-radius:14px;">
        <p style="margin:0;font-size:14px;line-height:1.5;color:#265d8a;"><strong>Market &amp; calendar tools</strong> — plan tasks and track what matters on your farm.</p>
      </td>
    </tr>
  </table>
  <p style="margin:0 0 16px;font-size:16px;line-height:1.55;color:#171b16;">
    Open the app and finish your farm profile (crops + location) so disease alerts and weather can protect <em>your</em> fields.
  </p>
  <p style="margin:0;font-size:15px;line-height:1.5;color:#2c5c2a;font-weight:600;">
    — The AgroAide team
  </p>
@endcomponent

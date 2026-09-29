@php
  $heading = 'Welcome to AgroAide';
  $subtitle = 'Account Activated';
  $title = 'Welcome to AgroAide — Your Farm Companion';
@endphp
@component('emails.layout', ['heading' => $heading, 'subtitle' => $subtitle, 'title' => $title])
  <p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#1e293b;">
    Hello <strong>{{ $name }}</strong>,
  </p>

  <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#334155;">
    Welcome to <strong style="color:#1b5e20;">AgroAide</strong> — your dedicated farm intelligence partner. We built AgroAide to give Nigerian farmers actionable agronomic data, early pest and disease alerts, and climate intelligence so you can farm with confidence throughout every season.
  </p>

  <p style="margin:0 0 14px;font-size:14px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;color:#475569;">
    Key Tools Available in Your Account:
  </p>

  <!-- FEATURE CARDS -->
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
    <!-- WEATHER -->
    <tr>
      <td style="padding:14px 16px;background-color:#f0fdf4;border-left:4px solid #16a34a;border-radius:10px;border-top:1px solid #dcfce7;border-right:1px solid #dcfce7;border-bottom:1px solid #dcfce7;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td width="30" valign="top" style="font-size:20px;line-height:1;padding-right:12px;">🌦️</td>
            <td>
              <p style="margin:0 0 3px;font-size:14px;font-weight:700;color:#14532d;">Hyperlocal Weather &amp; Soil Moisture</p>
              <p style="margin:0;font-size:13px;line-height:1.5;color:#166534;">7-day forecasts and soil metrics anchored to your farm's exact GPS location, avoiding generic city weather inaccuracies.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr><td style="height:10px;"></td></tr>
    <!-- DISEASE RADAR -->
    <tr>
      <td style="padding:14px 16px;background-color:#fef2f2;border-left:4px solid #dc2626;border-radius:10px;border-top:1px solid #fee2e2;border-right:1px solid #fee2e2;border-bottom:1px solid #fee2e2;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td width="30" valign="top" style="font-size:20px;line-height:1;padding-right:12px;">🛡️</td>
            <td>
              <p style="margin:0 0 3px;font-size:14px;font-weight:700;color:#7f1d1d;">5km Disease Outbreak Radar</p>
              <p style="margin:0;font-size:13px;line-height:1.5;color:#991b1b;">When nearby farmers scan matching crops and detect infectious diseases, you receive instant early prevention advice before spores reach your plots.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr><td style="height:10px;"></td></tr>
    <!-- AI ADVISOR -->
    <tr>
      <td style="padding:14px 16px;background-color:#eff6ff;border-left:4px solid #2563eb;border-radius:10px;border-top:1px solid #dbeafe;border-right:1px solid #dbeafe;border-bottom:1px solid #dbeafe;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td width="30" valign="top" style="font-size:20px;line-height:1;padding-right:12px;">🧠</td>
            <td>
              <p style="margin:0 0 3px;font-size:14px;font-weight:700;color:#1e3a8a;">AI Agronomy Advisor</p>
              <p style="margin:0;font-size:13px;line-height:1.5;color:#1e40af;">Ask agronomic questions in English or Pidgin with your soil type, crops, and live farm microclimate automatically in context.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr><td style="height:10px;"></td></tr>
    <!-- HARVEST & CALENDAR -->
    <tr>
      <td style="padding:14px 16px;background-color:#fffbeb;border-left:4px solid #d97706;border-radius:10px;border-top:1px solid #fef3c7;border-right:1px solid #fef3c7;border-bottom:1px solid #fef3c7;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td width="30" valign="top" style="font-size:20px;line-height:1;padding-right:12px;">📅</td>
            <td>
              <p style="margin:0 0 3px;font-size:14px;font-weight:700;color:#78350f;">Field Lifecycle &amp; Harvest Planner</p>
              <p style="margin:0;font-size:13px;line-height:1.5;color:#92400e;">Automated planting date suggestions, task reminders at 07:00, and full lifecycle tracking from seed to harvest.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- ACTION CALLOUT -->
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 28px;background-color:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;padding:20px;">
    <tr>
      <td>
        <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#0f172a;">
          Recommended Next Step:
        </p>
        <p style="margin:0;font-size:14px;line-height:1.6;color:#334155;">
          Open the AgroAide app on your phone and complete your <strong>farm location GPS</strong> and <strong>crop inventory</strong>. This ensures all early warning radar alerts and planting windows accurately protect your fields.
        </p>
      </td>
    </tr>
  </table>

  <p style="margin:0 0 4px;font-size:14px;line-height:1.5;color:#64748b;">
    Wishing you a bountiful season,
  </p>
  <p style="margin:0;font-size:15px;line-height:1.5;font-weight:700;color:#1b5e20;">
    The AgroAide Agronomy &amp; Engineering Team
  </p>
@endcomponent

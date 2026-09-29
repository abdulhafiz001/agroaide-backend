<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="x-apple-disable-message-reformatting">
  <title>{{ $title ?? 'AgroAide' }}</title>
  <!--[if mso]>
  <style>
    table, td, div, p, a, h1, h2, h3 { font-family: Segoe UI, Arial, sans-serif !important; }
  </style>
  <![endif]-->
  <style>
    @media only screen and (max-width: 600px) {
      .email-container { width: 100% !important; margin: auto !important; }
      .email-card { border-radius: 16px !important; }
      .header-pad { padding: 28px 20px 24px !important; }
      .body-pad { padding: 24px 20px !important; }
      .footer-pad { padding: 20px 20px 28px !important; }
      .code-display { font-size: 32px !important; letter-spacing: 8px !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f1;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;color:#0f172a;line-height:1.6;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f1f5f1;padding:32px 12px;">
    <tr>
      <td align="center">
        <!--[if (gte mso 9)|(IE)]>
        <table role="presentation" width="580" align="center" style="width:580px;">
        <tr>
        <td>
        <![endif]-->
        <table role="presentation" class="email-container" width="100%" cellspacing="0" cellpadding="0" style="max-width:580px;margin:0 auto;">
          
          <!-- BRAND TOP BAR -->
          <tr>
            <td align="center" style="padding:0 0 16px 0;">
              <table role="presentation" cellspacing="0" cellpadding="0">
                <tr>
                  <td align="center">
                    <div style="display:inline-block;padding:6px 16px;background-color:#e8f5e9;border-radius:100px;border:1px solid #c8e6c9;">
                      <span style="font-size:14px;line-height:1;vertical-align:middle;">🌱</span>
                      <span style="font-size:12px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#1b5e20;margin-left:6px;vertical-align:middle;">AgroAide Farm Intelligence</span>
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- MAIN CARD -->
          <tr>
            <td>
              <table role="presentation" class="email-card" width="100%" cellspacing="0" cellpadding="0" style="background-color:#ffffff;border-radius:20px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 4px 12px rgba(15,23,42,0.04);">
                
                <!-- HEADER BANNER -->
                <tr>
                  <td class="header-pad" style="background:linear-gradient(135deg,#1b5e20 0%,#2e7d32 50%,#388e3c 100%);padding:36px 36px 30px;color:#ffffff;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                      <tr>
                        <td>
                          <p style="margin:0 0 6px;font-size:12px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:#c8e6c9;">
                            {{ $subtitle ?? 'Smart Farm Companion' }}
                          </p>
                          <h1 style="margin:0;font-size:24px;line-height:1.3;font-weight:700;color:#ffffff;letter-spacing:-0.3px;">
                            {{ $heading }}
                          </h1>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>

                <!-- EMAIL BODY CONTENT -->
                <tr>
                  <td class="body-pad" style="padding:36px;">
                    {!! $slot !!}
                  </td>
                </tr>

                <!-- CARD FOOTER / SECURITY NOTE -->
                <tr>
                  <td style="padding:0 36px 32px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-top:1px solid #f1f5f9;padding-top:20px;">
                      <tr>
                        <td>
                          <p style="margin:0;font-size:12px;line-height:1.6;color:#64748b;">
                            <strong style="color:#334155;">Security Notice:</strong> AgroAide will never ask for your account password or recovery codes via phone call or SMS. Keep your credentials private.
                          </p>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>

              </table>
            </td>
          </tr>

          <!-- GLOBAL EMAIL FOOTER -->
          <tr>
            <td class="footer-pad" style="padding:24px 16px;text-align:center;">
              <p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#334155;">
                AgroAide Agricultural Intelligence Platform
              </p>
              <p style="margin:0 0 12px;font-size:12px;line-height:1.5;color:#64748b;">
                Precision weather, disease outbreak radar, and agronomy tools for Nigerian farmers.
              </p>
              <p style="margin:0;font-size:11px;line-height:1.5;color:#94a3b8;">
                &copy; {{ date('Y') }} AgroAide. All rights reserved.<br>
                You received this email because you registered an account on AgroAide.
              </p>
            </td>
          </tr>

        </table>
        <!--[if (gte mso 9)|(IE)]>
        </td>
        </tr>
        </table>
        <![endif]-->
      </td>
    </tr>
  </table>
</body>
</html>

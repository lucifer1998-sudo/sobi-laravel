<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light">
<title>{{ __('emails.lead_received.subject') }}</title>
<!--[if mso]>
<style>
  table { border-collapse: collapse; }
  .fallback-font { font-family: Arial, sans-serif !important; }
</style>
<![endif]-->
</head>
<body style="margin:0; padding:0; background-color:#fbeff0;">
  <span style="display:none; font-size:1px; color:#fbeff0; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
    {{ __('emails.lead_received.preheader') }}
  </span>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fbeff0;">
    <tr>
      <td align="center" style="padding:32px 16px;">

        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:16px; overflow:hidden;">

          <!-- Header -->
          <tr>
            <td align="center" style="padding:32px 24px 24px 24px; background-color:#ffffff;">
              {{-- Attached to the message rather than linked, so the logo still shows when a client blocks remote images. --}}
              <img src="{{ $message->embed(public_path('assets/images/logo/sobi_logo.png')) }}" width="96" height="38" alt="Sobi" style="display:block; width:96px; height:38px; border:0;">
            </td>
          </tr>

          <!-- Divider -->
          <tr>
            <td style="padding:0 24px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr><td style="border-top:1px solid #dddddd; font-size:0; line-height:0;">&nbsp;</td></tr>
              </table>
            </td>
          </tr>

          <!-- Title -->
          <tr>
            <td style="padding:28px 32px 8px 32px;">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="background-color:#fbeff0; border-radius:999px; padding:6px 14px;">
                    <span style="font-family:Arial, Helvetica, sans-serif; font-weight:700; font-size:11px; letter-spacing:0.5px; text-transform:uppercase; color:#c31d2c;">{{ __('emails.lead_received.badge') }}</span>
                  </td>
                </tr>
              </table>
              <div style="height:14px; line-height:14px; font-size:0;">&nbsp;</div>
              <span style="font-family:Arial, Helvetica, sans-serif; font-weight:700; font-size:22px; color:#111827;">{{ __('emails.lead_received.heading') }}</span>
              <div style="height:8px; line-height:8px; font-size:0;">&nbsp;</div>
              <span style="font-family:Arial, Helvetica, sans-serif; font-weight:400; font-size:14px; line-height:1.6; color:#6b7280;">{{ __('emails.lead_received.intro') }}</span>
            </td>
          </tr>

          <!-- Their own details back to them -->
          <tr>
            <td style="padding:20px 32px 8px 32px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #d3d3d3; border-radius:10px;">
                <tr>
                  <td style="padding:18px 20px; border-bottom:1px solid #eeeeee;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td width="110" valign="top" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:0.3px;">{{ __('emails.lead_received.name') }}</td>
                        <td valign="top" style="font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:600; color:#111827;">{{ $lead->firstname }} {{ $lead->lastname }}</td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td style="padding:18px 20px; border-bottom:1px solid #eeeeee;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td width="110" valign="top" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:0.3px;">{{ __('emails.lead_received.email') }}</td>
                        <td valign="top" style="font-family:Arial, Helvetica, sans-serif; font-size:15px; color:#111827;">{{ $lead->email }}</td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td style="padding:18px 20px; border-bottom:1px solid #eeeeee;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td width="110" valign="top" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:0.3px;">{{ __('emails.lead_received.phone') }}</td>
                        <td valign="top" style="font-family:Arial, Helvetica, sans-serif; font-size:15px; color:#111827;">{{ $lead->phone }}</td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td style="padding:18px 20px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td width="110" valign="top" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:0.3px;">{{ __('emails.lead_received.message') }}</td>
                        <td valign="top" style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:1.6; color:#374151;">
                          @if ($lead->message)
                            {!! nl2br(e($lead->message)) !!}
                          @else
                            <span style="color:#9ca3af;">{{ __('emails.lead_received.no_message') }}</span>
                          @endif
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Closing line and a way back to the site -->
          <tr>
            <td style="padding:20px 32px 4px 32px;">
              <span style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:1.6; color:#6b7280;">{{ __('emails.lead_received.closing') }}</span>
            </td>
          </tr>

          @if ($listingsUrl)
            <tr>
              <td style="padding:20px 32px 8px 32px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                  <tr>
                    <td style="background-color:#c31d2c; border-radius:999px;">
                      <a href="{{ $listingsUrl }}" style="display:inline-block; padding:12px 28px; font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">{{ __('emails.lead_received.cta') }}</a>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          @endif

          <!-- Footer -->
          <tr>
            <td align="center" style="padding:28px 32px 32px 32px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr><td style="border-top:1px solid #dddddd; font-size:0; line-height:0;">&nbsp;</td></tr>
              </table>
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>
</body>
</html>

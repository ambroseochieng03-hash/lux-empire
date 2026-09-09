<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

/**
 * LUX EMPIRE
 * Wraps every transactional email body in a consistent branded
 * shell: logo, name, tagline, brand colors from config/app.php.
 *
 * Uses assets/images/logo.svg via an absolute URL rather than an
 * embedded/attached image. Most modern mail clients (Gmail web/app,
 * Apple Mail, Outlook.com) render externally-hosted SVG <img> tags
 * fine; classic Outlook desktop (Word rendering engine) has patchy
 * SVG support and may show a broken image icon there. If a PNG
 * logo exists under assets/images/logo/, swap LOGO_PATH below to
 * point at that instead for wider compatibility — not done here
 * since I haven't seen what's inside that folder.
 */
final class EmailTemplate
{
    private const LOGO_PATH = '/assets/images/apple-touch-icon.png';

    public static function render(string $title, string $bodyHtml, ?string $ctaText = null, ?string $ctaUrl = null): string
    {
        $logoUrl = BASE_URL . self::LOGO_PATH;
        $year = date('Y');

        $ctaHtml = '';
        if ($ctaText !== null && $ctaUrl !== null) {
            $ctaHtml = '
                <tr>
                    <td style="padding:0 40px 40px 40px;" align="center">
                        <a href="' . htmlspecialchars($ctaUrl) . '"
                           style="display:inline-block; background:' . BRAND_PRIMARY . '; color:#0A0A0A;
                                  text-decoration:none; font-weight:bold; padding:14px 28px;
                                  border-radius:10px; font-family:Georgia,serif; font-size:15px;">
                            ' . htmlspecialchars($ctaText) . '
                        </a>
                    </td>
                </tr>';
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0; padding:0; background:#0A0A0A; font-family:Georgia,serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A; padding:30px 0;">
<tr>
<td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0"
       style="background:#111214; border:1px solid rgba(212,175,55,0.25); border-radius:20px; overflow:hidden; max-width:600px; width:100%;">

<tr>
<td style="padding:36px 40px 20px 40px;" align="center">
    <img src="' . htmlspecialchars($logoUrl) . '" alt="LUX EMPIRE" width="56" height="56" style="display:block; margin-bottom:14px;">
    <div style="color:' . BRAND_PRIMARY . '; font-size:22px; font-weight:bold; letter-spacing:1px;">LUX EMPIRE</div>
    <div style="color:#999999; font-size:12px; letter-spacing:0.5px; margin-top:4px;">Elite Homes &bull; Effortless Moves</div>
</td>
</tr>

<tr><td style="padding:0 40px;"><hr style="border:none; border-top:1px solid rgba(255,255,255,0.08); margin:0;"></td></tr>

<tr>
<td style="padding:32px 40px;">
    <h1 style="color:#ffffff; font-size:20px; margin:0 0 18px 0;">' . htmlspecialchars($title) . '</h1>
    <div style="color:#cccccc; font-size:15px; line-height:1.7;">' . $bodyHtml . '</div>
</td>
</tr>

' . $ctaHtml . '

<tr><td style="padding:0 40px;"><hr style="border:none; border-top:1px solid rgba(255,255,255,0.08); margin:0;"></td></tr>

<tr>
<td style="padding:24px 40px 32px 40px;" align="center">
    <div style="color:#666666; font-size:12px; line-height:1.6;">
        &copy; ' . $year . ' LUX EMPIRE. All Rights Reserved.<br>
        Luxury Living. Elite Movement. One Empire.
    </div>
</td>
</tr>

</table>
</td>
</tr>
</table>
</body>
</html>';
    }
}

Add-Type -AssemblyName System.Drawing

$dir = 'C:\Users\user\Desktop\Market Clearance System\soda\models'
if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Force -Path $dir | Out-Null }

function New-Brush([int]$r,[int]$g,[int]$b) {
    New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb($r,$g,$b))
}
function Pick-Font([single]$size) {
    foreach ($name in @('Arial Black','Arial','Segoe UI','Tahoma')) {
        try { return New-Object System.Drawing.Font($name,$size,[System.Drawing.FontStyle]::Bold) } catch {}
    }
    return New-Object System.Drawing.Font([System.Drawing.FontFamily]::GenericSansSerif,$size,[System.Drawing.FontStyle]::Bold)
}

# ---------------- FACADE TEXTURE ----------------
$fw = 2048; $fh = 640
$bmp = New-Object System.Drawing.Bitmap $fw,$fh
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

$orange  = New-Brush 232 100 30
$orangeD = New-Brush 210 84 20
$blue    = New-Brush 20 60 140
$blueD   = New-Brush 14 44 104
$glass   = New-Brush 58 74 96
$shop    = New-Brush 42 32 27
$white   = New-Brush 255 255 255
$signCols = @((New-Brush 206 36 38),(New-Brush 236 182 24),(New-Brush 36 132 72),(New-Brush 210 210 214),(New-Brush 60 120 196))

$g.FillRectangle($orange, 0, 0, $fw, $fh)

# ---- band layout (top -> bottom) ----
# 0..158   : top orange band with the big white DAET PUBLIC MARKET lettering
# 158..196 : cream cornice under the lettering
# 196..640 : windows and open shop bays (no vertical bars)
$letterBandH = 158
$cream = New-Brush 246 236 220
$creamD = New-Brush 232 214 188

# cream cornice under the big lettering
$g.FillRectangle($cream, 0, $letterBandH, $fw, 38)

# upper floor window strip
$winTop = 236; $winH = 150; $winCount = 17
$winSlot = ($fw - 120) / $winCount
for ($i=0; $i -lt $winCount; $i++) {
    $wx = 60 + $i*$winSlot + 14
    $ww = $winSlot - 28
    $g.FillRectangle($creamD, [single]$wx, [single]$winTop, [single]$ww, [single]$winH)
    $g.FillRectangle($glass, [single]($wx+8), [single]($winTop+8), [single]($ww-16), [single]($winH-16))
}

# cream cornice between the upper floor and the shops
$g.FillRectangle($cream, 0, 420, $fw, 34)

# ground floor shops
$g.FillRectangle($shop, 0, 454, $fw, $fh-454)
$stallCount = 9
$stallSlot = $fw / $stallCount
$rand = New-Object System.Random 7
for ($i=0; $i -lt $stallCount; $i++) {
    $sx = $i*$stallSlot + 18
    $sw2 = $stallSlot - 36
    # dark shop opening
    $g.FillRectangle((New-Brush 24 18 15), [single]$sx, [single]474, [single]$sw2, [single]($fh-488))
    # colored signage board above each shop
    $col = $signCols[$rand.Next(0,$signCols.Length)]
    $g.FillRectangle($col, [single]($sx+6), [single]458, [single]($sw2-12), [single]34)
}

# big white lettering: DAET PUBLIC MARKET (stays on the building face)

$text = 'DAET PUBLIC MARKET'
$chars = $text.ToCharArray()
$n = $chars.Length
$margin = 70
$avail = $fw - 2*$margin
$font = Pick-Font 120
$sf = New-Object System.Drawing.StringFormat
$sf.Alignment = [System.Drawing.StringAlignment]::Center
$sf.LineAlignment = [System.Drawing.StringAlignment]::Center
$sf.FormatFlags = [System.Drawing.StringFormatFlags]::NoWrap
for ($i=0; $i -lt $n; $i++) {
    $ch = [string]$chars[$i]
    if ($ch -eq ' ') { continue }
    $cx = $margin + ($i + 0.5) * $avail / $n
    $rect = New-Object System.Drawing.RectangleF ([single]($cx-70),[single]6,[single]140,[single]150)
    $g.DrawString($ch, $font, $white, $rect, $sf)
}

$g.Dispose()
$bmp.Save("$dir\_facade.png", [System.Drawing.Imaging.ImageFormat]::Png)
$bmp.Dispose()

# ---------------- ROOFTOP SIGN TEXTURE ----------------
$sw3 = 2048; $sh3 = 360
$sbmp = New-Object System.Drawing.Bitmap $sw3,$sh3
$sg = [System.Drawing.Graphics]::FromImage($sbmp)
$sg.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$sg.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
$sg.FillRectangle($blue, 0, 0, $sw3, $sh3)
$sg.FillRectangle((New-Brush 236 150 30), 0, 0, $sw3, 18)
$sg.FillRectangle((New-Brush 236 150 30), 0, $sh3-18, $sw3, 18)
$ssf = New-Object System.Drawing.StringFormat
$ssf.Alignment = [System.Drawing.StringAlignment]::Center
$ssf.LineAlignment = [System.Drawing.StringAlignment]::Center
$ssf.FormatFlags = [System.Drawing.StringFormatFlags]::NoWrap
$stext = 'MARKET CLEARANCE SYSTEM'
$schars = $stext.ToCharArray()
$snn = $schars.Length
$smargin = 70
$savail = $sw3 - 2*$smargin
$slot = $savail / $snn
$sfont = Pick-Font 118
for ($i=0; $i -lt $snn; $i++) {
    $ch = [string]$schars[$i]
    if ($ch -eq ' ') { continue }
    $cx = $smargin + ($i + 0.5) * $slot
    $srect = New-Object System.Drawing.RectangleF ([single]($cx - $slot/2), 8, [single]$slot, [single]($sh3-16))
    $sg.DrawString($ch, $sfont, $white, $srect, $ssf)
}
$sfont.Dispose()
$sg.Dispose()
$sbmp.Save("$dir\_sign.png", [System.Drawing.Imaging.ImageFormat]::Png)
$sbmp.Dispose()

# ---------------- SIDEWALK TILES ----------------
$tileW = 118; $tileH = 118; $gap = 8; $pad = 8
$cols = 12; $rows = 2
$tw = $pad * 2 + $cols * $tileW + ($cols - 1) * $gap
$th = $pad * 2 + $rows * $tileH + ($rows - 1) * $gap
$tbmp = New-Object System.Drawing.Bitmap $tw,$th
$tg = [System.Drawing.Graphics]::FromImage($tbmp)
$tg.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::None
$grout = New-Brush 168 160 148
$tg.FillRectangle($grout, 0, 0, $tw, $th)
$tones = @(
    (New-Brush 236 230 220),
    (New-Brush 228 222 210),
    (New-Brush 232 226 214),
    (New-Brush 222 216 204)
)
$randW = New-Object System.Random 11
for ($r = 0; $r -lt $rows; $r++) {
    for ($c = 0; $c -lt $cols; $c++) {
        $tx = $pad + $c * ($tileW + $gap)
        $ty = $pad + $r * ($tileH + $gap)
        $tone = $tones[$randW.Next(0, $tones.Length)]
        $tg.FillRectangle($tone, $tx, $ty, $tileW, $tileH)
    }
}
$tg.Dispose()
$tbmp.Save("$dir\_sidewalk.png", [System.Drawing.Imaging.ImageFormat]::Png)
$tbmp.Dispose()

Write-Output ('FACADE ' + (Get-Item "$dir\_facade.png").Length)
Write-Output ('SIGN ' + (Get-Item "$dir\_sign.png").Length)
Write-Output ('WALK ' + (Get-Item "$dir\_sidewalk.png").Length)

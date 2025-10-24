param(
  [string[]]$IncludeExt = @('php','js','css')
)

function Remove-CommentsGeneric {
  param(
    [string]$Text,
    [switch]$Js,
    [switch]$Php,
    [switch]$Css,
    [switch]$Html
  )

  $chars = $Text.ToCharArray()
  $len = $chars.Length
  $sb = New-Object System.Text.StringBuilder

  $inS = $false  # single quote
  $inD = $false  # double quote
  $inB = $false  # backtick (JS)
  $esc = $false
  $inLine = $false
  $inBlock = $false
  $blockEnd1 = '/' ; $blockEnd2 = '*'

  for ($i = 0; $i -lt $len; $i++) {
    $c = $chars[$i]
    $n = if ($i + 1 -lt $len) { $chars[$i+1] } else { [char]0 }

    if ($inLine) {
      if ($c -eq "`n") {
        $inLine = $false
        [void]$sb.Append($c)
      } elseif ($c -eq "`r") {
        [void]$sb.Append($c)
      }
      continue
    }

    if ($inBlock) {
      if ($c -eq $blockEnd2 -and $n -eq $blockEnd1) {
        $inBlock = $false
        $i++
      }
      continue
    }

    if ($esc) {
      $esc = $false
      [void]$sb.Append($c)
      continue
    }

    if ($inS) {
      if ($c -eq '\\') { $esc = $true; [void]$sb.Append($c); continue }
      if ($c -eq "'") { $inS = $false }
      [void]$sb.Append($c)
      continue
    }
    if ($inD) {
      if ($c -eq '\\') { $esc = $true; [void]$sb.Append($c); continue }
      if ($c -eq '"') { $inD = $false }
      [void]$sb.Append($c)
      continue
    }
    if ($Js -and $inB) {
      if ($c -eq '\\') { $esc = $true; [void]$sb.Append($c); continue }
      if ($c -eq '`') { $inB = $false }
      [void]$sb.Append($c)
      continue
    }

    if ($Js -and $c -eq '`') { $inB = $true; [void]$sb.Append($c); continue }
    if ($c -eq "'") { $inS = $true; [void]$sb.Append($c); continue }
    if ($c -eq '"') { $inD = $true; [void]$sb.Append($c); continue }

    if ($c -eq '/' -and ($Js -or $Php)) {
      if ($n -eq '/') {
        $inLine = $true
        $i++
        continue
      }
      if ($n -eq '*') {
        $inBlock = $true
        $i++
        continue
      }
    }
    if ($Css -and $c -eq '/' -and $n -eq '*') {
      $inBlock = $true
      $i++
      continue
    }
    if ($Php -and $c -eq '#') {
      $inLine = $true
      continue
    }

    [void]$sb.Append($c)
  }

  $out = $sb.ToString()

  if ($Html) {
    $out = [Regex]::Replace($out, '<!--.*?-->', '', 'Singleline')
  }

  return $out
}

$files = Get-ChildItem -Recurse -File | Where-Object {
  $ext = $_.Extension.TrimStart('.').ToLower()
  $IncludeExt -contains $ext
}

foreach ($f in $files) {
  $ext = $f.Extension.TrimStart('.').ToLower()
  $text = Get-Content -Raw -LiteralPath $f.FullName -Encoding UTF8
  switch ($ext) {
    'js'  { $new = Remove-CommentsGeneric -Text $text -Js }
    'css' { $new = Remove-CommentsGeneric -Text $text -Css }
    'php' { $new = Remove-CommentsGeneric -Text $text -Php -Html }
    default { $new = $text }
  }
  if ($new -ne $text) {
    Set-Content -LiteralPath $f.FullName -Value $new -Encoding UTF8
  }
}

Write-Host "Done stripping comments." -ForegroundColor Green


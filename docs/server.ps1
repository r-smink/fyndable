$root = "c:\Users\rick\Desktop\Rick\Fyndable\docs"
$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://localhost:8080/")
$listener.Start()
Write-Host "Server started on http://localhost:8080"

while ($listener.IsListening) {
    $context = $listener.GetContext()
    $request = $context.Request
    $response = $context.Response
    
    $localPath = $request.Url.LocalPath
    if ($localPath -eq "/") { $localPath = "/index.html" }
    
    $filePath = Join-Path $root $localPath.TrimStart("/")
    
    if (Test-Path $filePath -PathType Leaf) {
        $bytes = [System.IO.File]::ReadAllBytes($filePath)
        $ext = [System.IO.Path]::GetExtension($filePath)
        switch ($ext) {
            ".html" { $response.ContentType = "text/html; charset=utf-8" }
            ".css" { $response.ContentType = "text/css" }
            ".js" { $response.ContentType = "application/javascript" }
            ".json" { $response.ContentType = "application/json" }
            ".png" { $response.ContentType = "image/png" }
            ".svg" { $response.ContentType = "image/svg+xml" }
            default { $response.ContentType = "application/octet-stream" }
        }
        $response.ContentLength64 = $bytes.Length
        $response.OutputStream.Write($bytes, 0, $bytes.Length)
    } else {
        $response.StatusCode = 404
        $body = [System.Text.Encoding]::UTF8.GetBytes("404 - File not found: $localPath")
        $response.ContentLength64 = $body.Length
        $response.OutputStream.Write($body, 0, $body.Length)
    }
    
    $response.Close()
}

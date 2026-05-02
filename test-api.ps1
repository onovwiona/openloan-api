function Test-Endpoint {
    param($Uri, $Method = 'GET', $Body = $null, $Token = $null)
    $headers = @{}
    if ($Token) { $headers['Authorization'] = "Bearer $Token" }
    try {
        $params = @{ Uri = $Uri; Method = $Method; Headers = $headers }
        if ($Body) { $params['ContentType'] = 'application/json'; $params['Body'] = $Body }
        $r = Invoke-WebRequest @params
        return @{ Status = [int]$r.StatusCode; Body = $r.Content }
    } catch {
        $s = $_.Exception.Response
        $st = [int]$s.StatusCode
        $stream = $s.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($stream)
        $b = $reader.ReadToEnd()
        return @{ Status = $st; Body = $b }
    }
}

Write-Host "`n=== TEST 1: 404 Not Found ==="
$r = Test-Endpoint -Uri 'http://127.0.0.1:8001/api/v1/nonexistent'
Write-Host "Status: $($r.Status)"
Write-Host "Body: $($r.Body)"

Write-Host "`n=== TEST 2: 401 Unauthenticated ==="
$r = Test-Endpoint -Uri 'http://127.0.0.1:8001/api/v1/me'
Write-Host "Status: $($r.Status)"
Write-Host "Body: $($r.Body)"

Write-Host "`n=== TEST 3: 422 Validation Error ==="
$r = Test-Endpoint -Uri 'http://127.0.0.1:8001/api/v1/register' -Method POST -Body '{}'
Write-Host "Status: $($r.Status)"
Write-Host "Body: $($r.Body)"

Write-Host "`n=== TEST 4: 405 Method Not Allowed ==="
$r = Test-Endpoint -Uri 'http://127.0.0.1:8001/api/v1/login' -Method DELETE
Write-Host "Status: $($r.Status)"
Write-Host "Body: $($r.Body)"

Write-Host "`n=== TEST 5: Register ==="
$body = '{"first_name":"Test","last_name":"User","email":"test@example.com","phone":"1234567890","password":"password123","password_confirmation":"password123"}'
$r = Test-Endpoint -Uri 'http://127.0.0.1:8001/api/v1/register' -Method POST -Body $body
Write-Host "Status: $($r.Status)"
Write-Host "Body: $($r.Body)"

if ($r.Status -eq 201) {
    $json = $r.Body | ConvertFrom-Json
    $token = $json.data.access_token

    Write-Host "`n=== TEST 6: Me (with token) ==="
    $r = Test-Endpoint -Uri 'http://127.0.0.1:8001/api/v1/me' -Token $token
    Write-Host "Status: $($r.Status)"
    Write-Host "Body: $($r.Body)"

    Write-Host "`n=== TEST 7: Protected admin route (customer should get 403) ==="
    $r = Test-Endpoint -Uri 'http://127.0.0.1:8001/api/v1/users' -Token $token
    Write-Host "Status: $($r.Status)"
    Write-Host "Body: $($r.Body)"

    Write-Host "`n=== TEST 8: Logout ==="
    $r = Test-Endpoint -Uri 'http://127.0.0.1:8001/api/v1/logout' -Method POST -Token $token
    Write-Host "Status: $($r.Status)"
    Write-Host "Body: $($r.Body)"

    Write-Host "`n=== TEST 9: Me after logout (should be 401) ==="
    $r = Test-Endpoint -Uri 'http://127.0.0.1:8001/api/v1/me' -Token $token
    Write-Host "Status: $($r.Status)"
    Write-Host "Body: $($r.Body)"
}

Write-Host "`n=== TEST 10: Login ==="
$body = '{"email":"test@example.com","password":"password123"}'
$r = Test-Endpoint -Uri 'http://127.0.0.1:8001/api/v1/login' -Method POST -Body $body
Write-Host "Status: $($r.Status)"
Write-Host "Body: $($r.Body)"

Write-Host "`nAll tests complete!"


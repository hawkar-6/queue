param([string]$BaseUrl = 'http://127.0.0.1:8000/api')
$ErrorActionPreference = 'Stop'
$email = 'demo-' + [guid]::NewGuid().ToString('N') + '@example.test'
$account = @{ name = 'Demo'; email = $email; password = [guid]::NewGuid().ToString('N') } | ConvertTo-Json
Invoke-RestMethod "$BaseUrl/register" -Method Post -ContentType application/json -Body $account | Out-Null
$login = Invoke-RestMethod "$BaseUrl/login" -Method Post -ContentType application/json -Body $account
$headers = @{ Authorization = "Bearer $($login.token)"; Accept = 'application/json' }
$report = Invoke-RestMethod "$BaseUrl/reports" -Method Post -Headers $headers -ContentType application/json -Body '{"scores":[60,80,100]}'
$report | ConvertTo-Json
Write-Host 'Waiting for the queue worker...'
for ($i = 0; $i -lt 30; $i++) {
    $current = Invoke-RestMethod "$BaseUrl/reports/$($report.id)" -Headers $headers
    if ($current.status -eq 'ready') {
        $current | ConvertTo-Json
        Invoke-RestMethod "$BaseUrl/logout" -Method Post -Headers $headers | Out-Null
        return
    }
    if ($current.status -eq 'failed') { throw 'Report generation failed.' }
    Start-Sleep -Seconds 1
}
throw 'Report is still pending. Start php artisan queue:work database and check the report again.'

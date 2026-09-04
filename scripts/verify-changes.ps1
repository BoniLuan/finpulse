param(
    [string]$BaseRef = "HEAD",
    [switch]$BuildAffected
)

$ErrorActionPreference = "Stop"
$ComposeFiles = @("-f", "docker-compose.yml", "-f", "compose.dev.yml")

git rev-parse --verify $BaseRef *> $null
if ($LASTEXITCODE -ne 0) {
    throw "Unknown Git reference: $BaseRef"
}

$trackedChanges = @(git diff --name-only $BaseRef --)
$untrackedChanges = @(git ls-files --others --exclude-standard)
$changed = @($trackedChanges + $untrackedChanges) |
    Where-Object { $_ } |
    Sort-Object -Unique

if ($changed.Count -eq 0) {
    Write-Host "No changes found relative to $BaseRef."
    exit 0
}

Write-Host "Changed files:"
$changed | ForEach-Object { Write-Host "  $_" }

$touchesApi = $changed | Where-Object { $_ -like "services/api/*" }
$touchesAi = $changed | Where-Object { $_ -like "services/ai-worker/*" }
$touchesWeb = $changed | Where-Object { $_ -like "services/web/*" }
$touchesCompose = $changed | Where-Object { $_ -in @("docker-compose.yml", "compose.dev.yml") }

if ($touchesCompose) {
    docker compose @ComposeFiles config --quiet
    if ($LASTEXITCODE -ne 0) { throw "Docker Compose configuration is invalid." }
}

if ($touchesApi) {
    docker compose @ComposeFiles exec -T api composer test
    if ($LASTEXITCODE -ne 0) { throw "API tests failed." }
}

if ($touchesAi) {
    docker compose @ComposeFiles exec -T ai-worker pytest -q
    if ($LASTEXITCODE -ne 0) { throw "AI worker tests failed." }
}

if ($touchesWeb) {
    $node = Get-Command node -ErrorAction SilentlyContinue
    if ($node) {
        node --check services/web/src/api.js
        if ($LASTEXITCODE -ne 0) { throw "Web API client syntax check failed." }
        node --check services/web/src/main.js
        if ($LASTEXITCODE -ne 0) { throw "Web application syntax check failed." }
    } else {
        Write-Warning "Node.js is unavailable; JavaScript syntax checks were skipped."
    }
    $response = Invoke-WebRequest -UseBasicParsing http://localhost/
    if ($response.StatusCode -ne 200) { throw "Web smoke test failed." }
    Write-Host "Web smoke test passed."
}

if ($BuildAffected) {
    $services = [System.Collections.Generic.HashSet[string]]::new()

    foreach ($file in $changed) {
        if ($file -in @("services/web/Dockerfile", "services/web/nginx.conf")) {
            [void]$services.Add("web")
        }
        if ($file -match '^services/api/(Dockerfile|composer\.(json|lock)|docker/)') {
            [void]$services.Add("api")
            [void]$services.Add("collector")
            [void]$services.Add("scheduler")
        }
        if ($file -match '^services/ai-worker/(Dockerfile|pyproject\.toml|uv\.lock|requirements.*)') {
            [void]$services.Add("ai-worker")
        }
        if ($file -like "infra/gateway/*") {
            [void]$services.Add("gateway")
        }
    }

    if ($touchesCompose) {
        Write-Host "Compose changed; rebuilding the stack."
        docker compose @ComposeFiles build
        if ($LASTEXITCODE -ne 0) { throw "Stack build failed." }
        docker compose @ComposeFiles up -d
        if ($LASTEXITCODE -ne 0) { throw "Stack restart failed." }
    } elseif ($services.Count -gt 0) {
        [string[]]$orderedServices = @($services | Sort-Object)
        Write-Host "Rebuilding: $($orderedServices -join ', ')"
        docker compose @ComposeFiles build @orderedServices
        if ($LASTEXITCODE -ne 0) { throw "Affected image build failed." }
        docker compose @ComposeFiles up -d @orderedServices
        if ($LASTEXITCODE -ne 0) { throw "Affected service restart failed." }
    } else {
        Write-Host "No image rebuild is required; development bind mounts provide the changes live."
    }
}

Write-Host "Affected-change verification passed."

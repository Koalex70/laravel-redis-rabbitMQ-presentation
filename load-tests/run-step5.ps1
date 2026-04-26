$ErrorActionPreference = "Stop"

$baseUrl = if ($env:BASE_URL) { $env:BASE_URL } else { "http://host.docker.internal:8080" }
$resultsDir = "load-tests/results"

New-Item -ItemType Directory -Force -Path $resultsDir | Out-Null

Write-Host "===> Cache load test"
docker run --rm -i `
  -v "${PWD}/load-tests:/scripts" `
  grafana/k6 run `
  -e BASE_URL=$baseUrl `
  --summary-export "/scripts/results/cache-summary.json" `
  /scripts/k6-cache.js

Write-Host "===> Jobs enqueue load test"
docker run --rm -i `
  -v "${PWD}/load-tests:/scripts" `
  grafana/k6 run `
  -e BASE_URL=$baseUrl `
  --summary-export "/scripts/results/jobs-summary.json" `
  /scripts/k6-jobs.js

Write-Host "===> Done. Results are in load-tests/results"

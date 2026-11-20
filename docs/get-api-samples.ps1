$Base = "http://localhost:3001/api/v2"
$PatientID = 1; $PrescriptionID = 1; $DrugID = 1
$Out = Join-Path $PSScriptRoot "api-samples"
New-Item -ItemType Directory -Force -Path $Out | Out-Null

$login = Invoke-RestMethod -Method Post -Uri "$Base/auth/login" -ContentType "application/json" -Body '{"Username":"duocsi01","Password":"123456"}'
$H = @{ Authorization = "Bearer $($login.token)" }
[pscustomobject]@{ success = $login.success; user = $login.user } | ConvertTo-Json -Depth 5 | Set-Content -Encoding UTF8 (Join-Path $Out "auth-login.json")

function Save($name, $path) {
  try {
    $data = Invoke-RestMethod -Method Get -Uri "$Base/$path" -Headers $H
    ($data | ConvertTo-Json -Depth 12) | Set-Content -Encoding UTF8 (Join-Path $Out "$name.json")
    Write-Host "OK    $name" -ForegroundColor Green
  } catch {
    Write-Host "LOI   $name : $($_.Exception.Message)" -ForegroundColor Red
  }
}

Save "catalogs-init"                   "catalogs/init"
Save "inventory-drugs"                 "inventory/drugs"
Save "inventory-substitutes"           "inventory/drugs/substitutes?DrugID=$DrugID"
Save "inventory-expiring"              "inventory/expiring-batches?days=90"
Save "inventory-controlled-logs"       "inventory/controlled-logs"
Save "reports-department-consumption"  "reports/department-consumption"
Save "patients-list"                   "clinical/patients"
Save "patients-360"                    "clinical/patients/360?PatientID=$PatientID"
Save "prescriptions-list"              "prescriptions"
Save "prescriptions-detail"            "prescriptions?PrescriptionID=$PrescriptionID"
Save "smartcare-logs"                  "smartcare/logs"
foreach ($t in "InventoryBatches","Prescriptions","Patients","PatientAllergies","ControlledDrugLogs") { Save "audit-$t" "audit/as-of?table=$t" }
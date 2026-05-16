#Requires -Version 5.1
<#
.SYNOPSIS
    Seeds the student roster into Supabase for Nex CEC Basic School.
.DESCRIPTION
    Uses the Supabase REST API with the service_role key to bulk-insert
    student records. Designed to run AFTER migrate-all.sql and seed-data.sql
    have been executed.
.NOTES
    Usage: .\seed-students.ps1
    Requires: SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY env vars
#>

$ErrorActionPreference = "Stop"

$SUPABASE_URL = $env:SUPABASE_URL
$SERVICE_KEY  = $env:SUPABASE_SERVICE_ROLE_KEY

if (-not $SUPABASE_URL -or -not $SERVICE_KEY) {
    Write-Error "Missing SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY environment variables."
    exit 1
}

$headers = @{
    "apikey"             = $SERVICE_KEY
    "Authorization"      = "Bearer $SERVICE_KEY"
    "Content-Type"       = "application/json"
    "Prefer"             = "resolution=merge-duplicates"
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  NEX CEC STUDENT SEEDER (PowerShell)"   -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# ─── Student Data ────────────────────────────────────────────────────────────
$student_data = @{
    "KG 1" = @{
        boys = @(
            'ADDAI R. JOHNSON','ANUA BAFFOUR','ADOM O. KOFI','ASARE NK JAYDEN','BOSIAKO KENDRICK',
            'BOATENG F. SETH','DANKWA O. CYRIL','OWUSU ROLAND','ASI O.K. EMMANUEL','OPOKU GERALD',
            'OSEI JAYDEN','OHENE DANSO','OKOAHENE A. OTHNIEL'
        )
        girls = @(
            'ODURO LESLEY','MENSAH G. ORDAIN','BOAKYE OLIVIA','BOAKYEWAA MELISSA','ACHEAMPONG STEPHIDELA',
            'ANTWIWAA MARGERET','AGYEMANG VICTORIA','ACHIAA M. ELSIE','AGYAPONG CANDYBELL','ABUBAKAR U. KHADIJAH',
            'BOADUWAA MELISA','AMPONSAH GIFTY','DANSO GODLOVE','KENDRA AGYEI','MENSAH O. PRISCILLA',
            'VICENTIA ADAMA','ANTWIM M. POKUAA','NYHIRABA A. DEBORAH'
        )
    }
    "KG 2" = @{
        boys = @(
            'ANTWI SAMUEL','AKWATSI M. ALEXANDER','ADOMAKO JULIUS','AMANKWAH BENJAMIN','KHALIDU TERRY EDEM',
            'ODURO KWADWO BONSU','OWUSU BOATENG PHILIP','PAKIA EPHRIAM','AGYAPONG M. RICHIESAM'
        )
        girls = @(
            'AMANE PRECIOUS','AKOWUAH MARY','ASIEDU FLORENCE','AMA MINTAH ANTHONETTE','ASANTEWAA JANET','DORIS AFREH'
        )
    }
    "BASIC ONE" = @{
        boys = @(
            'AHMED FATAYIYA','ANTWI KENDRA','ASENSO K. VICENTIA','BOATENG O.A. FAITHFUL','POKUWAA VICENTIA',
            'AFRIYIE MAYO SERENA','ANGLE EXCELLENT'
        )
        girls = @(
            'ANTWI ELEAZER','EYAN DERRICK','NAMA ADU RUBBIN','OWUSU D. CHRISTIAN','KYEREMANTENG A. WISE'
        )
    }
    "BASIC TWO" = @{
        boys = @(
            'BRIGT SARPPONG','MARVIH OWUSU BLESSING','JOHN JANBAH'
        )
        girls = @(
            'ABUBAKARI V. FATIMAT','BOATENG A.F. LORDIBEL','OWUSU CHRISTABEL','OTORI MENSAH JESSICA',
            'OWUSU M. ANASTASIAH','BOTCHWAY A. VICTORIA'
        )
    }
    "BASIC THREE" = @{
        boys = @(
            'NSIAH MICHEAL','KUSI MIRACLE','ALEXIS AKIM BOATENG'
        )
        girls = @(
            'ADOMAKO EMMANUELLA','TWIENEBOA QUEENSTABLE','ARTHUR KORAMAH VICTORIA'
        )
    }
    "BASIC FOUR" = @{
        boys = @(
            'ABDUL NASAL MALIK','OBENG A.B. MORDECAI','OSEI DANQUAH SAMUEL'
        )
        girls = @(
            'AGYEI POMAA MARY'
        )
    }
    "BASIC FIVE" = @{
        boys = @(
            'ABUBAKAR UMAR HASSAN','ADJEI OPOKU HARRY','FRIMPONG JEFFERY'
        )
        girls = @(
            'ACHEAMPONG LINDA','ADOMAKO N. QUEENCILLA'
        )
    }
}

# Class name normalisation
$classMap = @{
    'KG 1'       = 'KG 1'
    'KG 2'       = 'KG 2'
    'BASIC ONE'   = 'Basic 1'
    'BASIC TWO'   = 'Basic 2'
    'BASIC THREE' = 'Basic 3'
    'BASIC FOUR'  = 'Basic 4'
    'BASIC FIVE'  = 'Basic 5'
}

# Birth year estimates per class (child age ~2025)
$ageMap = @{
    'KG 1'    = 2020
    'KG 2'    = 2019
    'Basic 1' = 2018
    'Basic 2' = 2017
    'Basic 3' = 2016
    'Basic 4' = 2015
    'Basic 5' = 2014
}

# ─── Helper: Generate one student record ────────────────────────────────────
function New-StudentRecord {
    param([string]$Name, [string]$ClassName, [string]$Gender)

    $admission = "CEC-{0:yyMMdd}-{1:D4}" -f (Get-Date), (Get-Random -Max 9999)
    $enrollId  = "ENR-{0}-{1}" -f (Get-Date).Year, [System.Guid]::NewGuid().ToString().Substring(0,6).ToUpper()
    $birthYear = if ($ageMap.ContainsKey($ClassName)) { $ageMap[$ClassName] } else { 2017 }
    $dob       = Get-Date -Year $birthYear -Month (Get-Random -Min 1 -Max 13) -Day (Get-Random -Min 1 -Max 29) -Format "yyyy-MM-dd"
    $nhis      = "NHIS-{0}" -f (Get-Random -Min 1000000 -Max 9999999)

    $title   = if ($Gender -eq 'Male') { 'Mr.' } else { 'Mrs.' }
    $rel     = if ($Gender -eq 'Male') { 'Father' } else { 'Mother' }
    $ph      = "024{0:D8}" -f (Get-Random -Max 99999999)
    $ph2     = "024{0:D8}" -f (Get-Random -Max 99999999)
    $first   = $Name.Split()[0]
    $gEmail  = "guardian.{0}@parent.com" -f $first.ToLower()

    return @{
        full_name                = $Name
        admission_number         = $admission
        enrollment_id            = $enrollId
        class_name               = $ClassName
        gender                   = $Gender
        date_of_birth            = $dob
        nationality              = 'Ghanaian'
        health_insurance_id      = $nhis
        guardian_name            = "$title Parent of $first"
        guardian_email           = $gEmail
        guardian_phone_primary   = $ph
        guardian_phone_emergency = $ph2
        guardian_relationship    = $rel
        guardian_occupation      = 'Business'
        guardian_address         = 'Kumasi, Ghana'
        address                  = 'Kumasi, Ghana'
        academic_year            = '2025/2026'
        admission_date           = (Get-Date -Format 'yyyy-MM-dd')
        status                   = 'Active'
        payment_status           = 'Unpaid'
        enrollment_type          = 'Manual'
    }
}

# ─── Build and Submit Batches ───────────────────────────────────────────────
$allRecords = @()
$total      = 0

foreach ($rawClass in $student_data.Keys) {
    $normClass = $classMap[$rawClass]
    if (-not $normClass) { $normClass = $rawClass }

    Write-Host "`n--- Processing: $rawClass → $normClass ---" -ForegroundColor Yellow

    $classData = $student_data[$rawClass]

    # Boys
    if ($classData.ContainsKey('boys')) {
        foreach ($name in $classData['boys']) {
            $allRecords += New-StudentRecord -Name $name -ClassName $normClass -Gender 'Male'
            $total++
        }
    }
    # Girls
    if ($classData.ContainsKey('girls')) {
        foreach ($name in $classData['girls']) {
            $allRecords += New-StudentRecord -Name $name -ClassName $normClass -Gender 'Female'
            $total++
        }
    }
}

Write-Host "`nTotal records to insert: $total" -ForegroundColor Cyan

# ─── Bulk Insert (batches of 20 to avoid payload limits) ───────────────────
$apiUrl  = "$SUPABASE_URL/rest/v1/students"
$inserted = 0
$skipped  = 0
$errors   = 0
$batchSize = 20

for ($i = 0; $i -lt $allRecords.Count; $i += $batchSize) {
    $batch = $allRecords[$i..[Math]::Min($i + $batchSize - 1, $allRecords.Count - 1)]
    $json  = $batch | ConvertTo-Json -Depth 3

    try {
        $response = Invoke-RestMethod -Uri $apiUrl -Method Post -Headers $headers -Body $json
        $inserted += $batch.Count
        $last = [Math]::Min($i + $batchSize, $allRecords.Count)
        Write-Host "  + Batch $($i/$batchSize + 1): $($batch.Count) inserted (rows $($i+1)-$last)" -ForegroundColor Green
    }
    catch {
        $errMsg = $_.Exception.Message
        # If "duplicate", count them as "already exists"
        if ($errMsg -match 'duplicate|unique|23505|already exists') {
            $skipped += $batch.Count
            Write-Host "  ~ Batch contains duplicates ($($batch.Count) skipped)" -ForegroundColor DarkYellow
        }
        else {
            $errors += $batch.Count
            Write-Host "  ! Batch failed: $errMsg" -ForegroundColor Red

            # Retry one-by-one
            foreach ($rec in $batch) {
                try {
                    $singleJson = $rec | ConvertTo-Json -Depth 2
                    Invoke-RestMethod -Uri $apiUrl -Method Post -Headers $headers -Body $singleJson | Out-Null
                    $inserted++
                }
                catch {
                    if ($_.Exception.Message -match 'duplicate|unique|23505|already exists') {
                        $skipped++
                    } else {
                        $errors++
                        Write-Host "    ! Failed: $($rec.full_name)" -ForegroundColor Red
                    }
                }
            }
        }
    }
}

# ─── Final Summary ──────────────────────────────────────────────────────────
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  SEEDING COMPLETE"                      -ForegroundColor Cyan
Write-Host "  Total:    $total"                       -ForegroundColor Cyan
Write-Host "  Inserted: $inserted"                    -ForegroundColor Green
Write-Host "  Skipped:  $skipped"                     -ForegroundColor DarkYellow
Write-Host "  Errors:   $errors"                      -ForegroundColor Red
Write-Host "========================================" -ForegroundColor Cyan

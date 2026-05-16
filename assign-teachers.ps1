# Assign class teachers to their classes via subjects table
# This gives teachers permission to mark attendance for their classes
# because getTeacherClassIds() checks subjects.teacher_id -> subjects.class_id

$key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRia2luYWdsdWdhZ2xvaW5lY2xlIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3ODAwMjE0MCwiZXhwIjoyMDkzNTc4MTQwfQ.gfbNYIZUhkfZxhr6U1BBrGWALz8M7OsNxvkHbe2pWDI"
$base = "https://tbkinaglugagloinecle.supabase.co"
$h = @{ "apikey"=$key; "Authorization"="Bearer $key"; "Content-Type"="application/json" }

# teacher_id -> @(class_id, class_name) 
$mapping = @(
    @{staff_name="Ms. Efua Darko";   teacher_id=117; class_id=1;   class_name="Creche"},
    @{staff_name="Ms. Efua Darko";   teacher_id=117; class_id=4;   class_name="KG 2"},
    @{staff_name="Mrs. Abena Gyamfi";teacher_id=119; class_id=287; class_name="Nursery 2"},
    @{staff_name="Mr. Kofi Owusu";   teacher_id=118; class_id=2;   class_name="Nursery 1"},
    @{staff_name="Mr. Yaw Boateng";  teacher_id=120; class_id=3;   class_name="KG 1"},
    @{staff_name="Ms. Akosua Frimpong"; teacher_id=121; class_id=5;   class_name="Basic 1"},
    @{staff_name="Mr. Emmanuel Tetteh"; teacher_id=122; class_id=6;   class_name="Basic 2"},
    @{staff_name="Mrs. Grace Adjei";    teacher_id=123; class_id=7;   class_name="Basic 3"},
    @{staff_name="Mr. Daniel Kwarteng"; teacher_id=124; class_id=8;   class_name="Basic 4"},
    @{staff_name="Ms. Victoria Appiah"; teacher_id=125; class_id=9;   class_name="Basic 5"},
    @{staff_name="Mr. Samuel Osei";     teacher_id=126; class_id=10;  class_name="Basic 6"},
    @{staff_name="Mrs. Nana Yeboah";    teacher_id=127; class_id=11;  class_name="JHS 1"},
    @{staff_name="Mr. Isaac Mensah";    teacher_id=128; class_id=12;  class_name="JHS 2"},
    @{staff_name="Mr. Frank Agyemang";  teacher_id=129; class_id=13;  class_name="JHS 3"}
)

$ok = 0; $skip = 0; $fail = 0

foreach ($m in $mapping) {
    $body = @{name="Class Teacher (Attendance)"; code="CTA"; class_id=$m.class_id; teacher_id=$m.teacher_id} | ConvertTo-Json
    try {
        Invoke-RestMethod -Uri "$base/rest/v1/subjects" -Method Post -Headers $h -Body $body -TimeoutSec 10 | Out-Null
        Write-Host "[INSERTED] $($m.class_name) -> $($m.staff_name) (teacher_id=$($m.teacher_id))" -ForegroundColor Green
        $ok++
    }
    catch {
        if ($_.Exception.Response.StatusCode.value__ -eq 409) {
            Write-Host "[ALREADY EXISTS] $($m.class_name) -> $($m.staff_name)" -ForegroundColor Yellow
            $skip++
        } else {
            Write-Host "[ERROR] $($m.class_name): $($_.Exception.Message)" -ForegroundColor Red
            $fail++
        }
    }
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  ASSIGNMENT COMPLETE" -ForegroundColor Cyan
Write-Host "  Inserted: $ok" -ForegroundColor Cyan
Write-Host "  Skipped (already existed): $skip" -ForegroundColor Cyan
Write-Host "  Failed: $fail" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# Verify by querying subjects with non-null class_id and teacher_id
Write-Host "`n=== VERIFICATION: Subject records with teacher assignments ===" -ForegroundColor Cyan
$result = Invoke-RestMethod -Uri "$base/rest/v1/subjects?select=id,name,class_id,teacher_id&class_id=not.is.null&teacher_id=not.is.null&order=id" -Headers $h -TimeoutSec 10
$result | Format-Table -AutoSize

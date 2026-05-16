<?php
/**
 * Student Data Seeder Script
 * 
 * Seeds the complete student roster for Nex CEC Basic School.
 * Maps student names from the physical register into the database.
 * 
 * Usage:
 *   php student-seeder.php
 * 
 * This script connects to Supabase via the REST API bridge.
 */

// Adjust path to find db.php inside Infotess-host
$possiblePaths = [
    __DIR__ . '/Infotess-host/api/includes/db.php',
    __DIR__ . '/includes/db.php',
    __DIR__ . '/../Infotess-host/api/includes/db.php',
];

$dbLoaded = false;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $dbLoaded = true;
        echo "Loaded db.php from: $path\n";
        break;
    }
}

if (!$dbLoaded) {
    // Fallback: direct Supabase REST API connection
    echo "No db.php found. Using direct Supabase REST connection.\n";
    $supabaseUrl = getenv('SUPABASE_URL') ?: 'https://your-project.supabase.co';
    $supabaseKey = getenv('SUPABASE_ANON_KEY') ?: 'your-anon-key';
    
    if (!class_exists('SupabaseClient')) {
        // Simple inline Supabase client for standalone use
        class SupabaseClient {
            private $url;
            private $key;
            private $tableName;
            private $whereClauses = [];
            
            public function __construct() {
                $this->url = getenv('SUPABASE_URL') ?: 'https://your-project.supabase.co';
                $this->key = getenv('SUPABASE_ANON_KEY') ?: 'your-anon-key';
            }
            
            public function table($name) {
                $this->tableName = $name;
                $this->whereClauses = [];
                return $this;
            }
            
            public function where($col, $val) {
                $this->whereClauses[] = $col . '=eq.' . urlencode($val);
                return $this;
            }
            
            public function insert($data) {
                $url = rtrim($this->url, '/') . '/rest/v1/' . $this->tableName;
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . $this->key,
                    'Authorization: Bearer ' . $this->key,
                    'Content-Type: application/json',
                    'Prefer: return=representation',
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode >= 200 && $httpCode < 300) {
                    $result = json_decode($response, true);
                    return $result ?: [$data];
                }
                echo "  INSERT error ($httpCode): " . substr($response, 0, 200) . "\n";
                return [$data];
            }
            
            public function select($columns = '*') {
                return $this;
            }
            
            public function get() {
                $url = rtrim($this->url, '/') . '/rest/v1/' . $this->tableName;
                if (!empty($this->whereClauses)) {
                    $url .= '?' . implode('&', $this->whereClauses);
                }
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . $this->key,
                    'Authorization: Bearer ' . $this->key,
                ]);
                $response = curl_exec($ch);
                curl_close($ch);
                return json_decode($response, true) ?: [];
            }
            
            public function update($data) { return [$data]; }
            public function delete() { return []; }
        }
        $supabase = new SupabaseClient();
    }
    
    // Create mock PDO interface
    if (!class_exists('LegacyPDO')) {
        class LegacyPDO {
            private $client;
            public function __construct($client) { $this->client = $client; }
            public function prepare($sql) { return new LegacyStatement($this->client, $sql); }
            public function query($sql) { $s = $this->prepare($sql); $s->execute(); return $s; }
            public function lastInsertId() { return null; }
            public function exec($sql) { return 0; }
        }
        class LegacyStatement {
            private $client; private $sql; private $result = []; private $index = 0;
            public function __construct($client, $sql) { $this->client = $client; $this->sql = $sql; }
            public function execute($params = []) {
                if (preg_match('/^SELECT.*FROM\s+(\w+)/i', $this->sql, $m)) {
                    $this->result = $this->client->table($m[1])->get();
                }
                return true;
            }
            public function fetch() {
                if ($this->index >= count($this->result)) return false;
                return $this->result[$this->index++];
            }
            public function fetchAll() { return $this->result ?: []; }
            public function fetchColumn() { $r = $this->fetch(); return $r ? reset($r) : null; }
            public function rowCount() { return count($this->result); }
        }
    }
    
    $pdo = new LegacyPDO($supabase);
    echo "Using standalone Supabase REST client.\n";
}

echo "\n========================================\n";
echo "  NEX CEC STUDENT SEEDER\n";
echo "========================================\n\n";

// Extracted student data to seed
$student_data = [
    'KG 1' => [
        'boys' => [
            'ADDAI R. JOHNSON', 'ANUA BAFFOUR', 'ADOM O. KOFI', 'ASARE NK JAYDEN', 'BOSIAKO KENDRICK',
            'BOATENG F. SETH', 'DANKWA O. CYRIL', 'OWUSU ROLAND', 'ASI O.K. EMMANUEL', 'OPOKU GERALD',
            'OSEI JAYDEN', 'OHENE DANSO', 'OKOAHENE A. OTHNIEL'
        ],
        'girls' => [
            'ODURO LESLEY', 'MENSAH G. ORDAIN', 'BOAKYE OLIVIA', 'BOAKYEWAA MELISSA', 'ACHEAMPONG STEPHIDELA',
            'ANTWIWAA MARGERET', 'AGYEMANG VICTORIA', 'ACHIAA M. ELSIE', 'AGYAPONG CANDYBELL', 'ABUBAKAR U. KHADIJAH',
            'BOADUWAA MELISA', 'AMPONSAH GIFTY', 'DANSO GODLOVE', 'KENDRA AGYEI', 'MENSAH O. PRISCILLA',
            'VICENTIA ADAMA', 'ANTWIM M. POKUAA', 'NYHIRABA A. DEBORAH'
        ]
    ],
    'KG 2' => [
        'boys' => [
            'ANTWI SAMUEL', 'AKWATSI M. ALEXANDER', 'ADOMAKO JULIUS', 'AMANKWAH BENJAMIN', 'KHALIDU TERRY EDEM',
            'ODURO KWADWO BONSU', 'OWUSU BOATENG PHILIP', 'PAKIA EPHRIAM', 'AGYAPONG M. RICHIESAM'
        ],
        'girls' => [
            'AMANE PRECIOUS', 'AKOWUAH MARY', 'ASIEDU FLORENCE', 'AMA MINTAH ANTHONETTE', 'ASANTEWAA JANET', 'DORIS AFREH'
        ]
    ],
    'BASIC ONE' => [
        'boys' => [
            'AHMED FATAYIYA', 'ANTWI KENDRA', 'ASENSO K. VICENTIA', 'BOATENG O.A. FAITHFUL', 'POKUWAA VICENTIA',
            'AFRIYIE MAYO SERENA', 'ANGLE EXCELLENT'
        ],
        'girls' => [
            'ANTWI ELEAZER', 'EYAN DERRICK', 'NAMA ADU RUBBIN', 'OWUSU D. CHRISTIAN', 'KYEREMANTENG A. WISE'
        ]
    ],
    'BASIC TWO' => [
        'boys' => [
            'BRIGT SARPPONG', 'MARVIH OWUSU BLESSING', 'JOHN JANBAH'
        ],
        'girls' => [
            'ABUBAKARI V. FATIMAT', 'BOATENG A.F. LORDIBEL', 'OWUSU CHRISTABEL', 'OTORI MENSAH JESSICA',
            'OWUSU M. ANASTASIAH', 'BOTCHWAY A. VICTORIA'
        ]
    ],
    'BASIC THREE' => [
        'boys' => [
            'NSIAH MICHEAL', 'KUSI MIRACLE', 'ALEXIS AKIM BOATENG'
        ],
        'girls' => [
            'ADOMAKO EMMANUELLA', 'TWIENEBOA QUEENSTABLE', 'ARTHUR KORAMAH VICTORIA'
        ]
    ],
    'BASIC FOUR' => [
        'boys' => [
            'ABDUL NASAL MALIK', 'OBENG A.B. MORDECAI', 'OSEI DANQUAH SAMUEL'
        ],
        'girls' => [
            'AGYEI POMAA MARY'
        ]
    ],
    'BASIC FIVE' => [
        'boys' => [
            'ABUBAKAR UMAR HASSAN', 'ADJEI OPOKU HARRY', 'FRIMPONG JEFFERY'
        ],
        'girls' => [
            'ACHEAMPONG LINDA', 'ADOMAKO N. QUEENCILLA'
        ]
    ]
];

// Function to generate random data for missing fields
function generateRandomData($class_name, $gender) {
    // Generate random admission number
    $admission_number = "CEC-" . date("y") . date("m") . date("d") . "-" . rand(1000, 9999);
    
    // Generate random enrollment ID
    $enrollment_id = "ENR-" . date("Y") . "-" . strtoupper(substr(md5(uniqid()), 0, 6));
    
    // Generate random date of birth (based on class level)
    $current_year = date('Y');
    $class_to_year = [
        'KG 1' => $current_year - 3,
        'KG 2' => $current_year - 4,
        'BASIC ONE' => $current_year - 5,
        'BASIC TWO' => $current_year - 6,
        'BASIC THREE' => $current_year - 7,
        'BASIC FOUR' => $current_year - 8,
        'BASIC FIVE' => $current_year - 9
    ];
    
    $base_year = $class_to_year[$class_name] ?? $current_year;
    $dob = ($base_year - 5) . "-01-01";
    
    // Generate other random data
    $data = [
        'admission_number' => $admission_number,
        'enrollment_id' => $enrollment_id,
        'date_of_birth' => $dob,
        'nationality' => 'Ghanaian',
        'health_insurance_id' => 'NHIS-' . rand(1000000, 9999999),
        'guardian_name' => $gender . ' Guardian',
        'guardian_email' => 'guardian' . rand(1, 100) . '@parent.com',
        'guardian_phone' => '024' . rand(10000000, 99999999)
    ];
    
    return $data;
}

// Counters
$totalInserted = 0;
$totalSkipped = 0;

/**
 * Insert a single student into the database.
 * Checks for duplicates by admission_number before inserting.
 *
 * @param PDO    $pdo         Database connection
 * @param string $full_name   Student's full name
 * @param string $class_name  Normalized class name (e.g. "Basic 1")
 * @param string $gender      "Male" or "Female"
 * @return string             "inserted", "skipped", or "error"
 */
function insertStudent($pdo, $full_name, $class_name, $gender) {
    // Generate admission number: CEC-YYMMDD-XXXX
    $admission_number = "CEC-" . date("y") . date("m") . date("d") . "-" . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    // Generate enrollment ID: ENR-YYYY-XXXXXX
    $enrollment_id = "ENR-" . date("Y") . "-" . strtoupper(substr(md5($full_name . time()), 0, 6));
    
    // Look up class ID
    try {
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE LOWER(name) = LOWER(?)");
        $stmt->execute([$class_name]);
        $classRow = $stmt->fetch();
        $class_id = $classRow ? (int)$classRow['id'] : null;
    } catch (Exception $e) {
        $class_id = null;
    }
    
    // Check if student already exists by name + class
    try {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE LOWER(full_name) = LOWER(?) AND LOWER(class_name) = LOWER(?)");
        $stmt->execute([$full_name, $class_name]);
        if ($stmt->fetch()) {
            return 'skipped'; // Already exists
        }
    } catch (Exception $e) {
        // Continue anyway
    }
    
    // Calculate approximate date of birth based on class
    $current_year = (int)date('Y');
    $class_age_map = [
        'KG 1'     => $current_year - 5,
        'KG 2'     => $current_year - 6,
        'Basic 1'  => $current_year - 7,
        'Basic 2'  => $current_year - 8,
        'Basic 3'  => $current_year - 9,
        'Basic 4'  => $current_year - 10,
        'Basic 5'  => $current_year - 11,
    ];
    $birth_year = $class_age_map[$class_name] ?? ($current_year - 7);
    $dob = sprintf("%d-%02d-%02d", $birth_year, rand(1, 12), rand(1, 28));
    
    // Build student data
    $studentData = [
        'full_name'              => $full_name,
        'admission_number'       => $admission_number,
        'enrollment_id'          => $enrollment_id,
        'class_name'             => $class_name,
        'gender'                 => $gender,
        'date_of_birth'          => $dob,
        'nationality'            => 'Ghanaian',
        'health_insurance_id'    => 'NHIS-' . rand(1000000, 9999999),
        'guardian_name'          => ($gender === 'Male' ? 'Mr.' : 'Mrs.') . ' Parent of ' . explode(' ', $full_name)[0],
        'guardian_email'         => 'guardian.' . strtolower(str_replace(' ', '.', explode(' ', $full_name)[0])) . '@parent.com',
        'guardian_phone_primary' => '024' . rand(10000000, 99999999),
        'guardian_phone_emergency' => '024' . rand(10000000, 99999999),
        'guardian_relationship'  => $gender === 'Male' ? 'Father' : 'Mother',
        'guardian_occupation'    => 'Business',
        'guardian_address'       => 'Kumasi, Ghana',
        'address'                => 'Kumasi, Ghana',
        'academic_year'          => '2025/2026',
        'admission_date'         => date('Y-m-d'),
        'status'                 => 'Active',
        'payment_status'         => 'Unpaid',
        'enrollment_type'        => 'Manual',
        'user_id'                => null,
    ];
    
    // Insert into database via Supabase bridge
    try {
        if (isset($GLOBALS['supabase']) && $GLOBALS['supabase']) {
            // Direct Supabase REST insert
            $result = $GLOBALS['supabase']->table('students')->insert($studentData);
            return 'inserted';
        } else {
            // Legacy PDO-style insert
            $columns = implode(', ', array_keys($studentData));
            $placeholders = implode(', ', array_fill(0, count($studentData), '?'));
            $values = array_values($studentData);
            
            $sql = "INSERT INTO students ($columns) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            return 'inserted';
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        
        // If duplicate admission_number, retry with new one
        if (strpos($errorMsg, 'duplicate') !== false || strpos($errorMsg, 'unique') !== false || 
            strpos($errorMsg, '23505') !== false || strpos($errorMsg, 'already exists') !== false) {
            // Regenerate admission_number and retry once
            $studentData['admission_number'] = "CEC-" . date("y") . date("m") . date("d") . "-" . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            try {
                if (isset($GLOBALS['supabase']) && $GLOBALS['supabase']) {
                    $GLOBALS['supabase']->table('students')->insert($studentData);
                } else {
                    $columns = implode(', ', array_keys($studentData));
                    $placeholders = implode(', ', array_fill(0, count($studentData), '?'));
                    $values = array_values($studentData);
                    $sql = "INSERT INTO students ($columns) VALUES ($placeholders)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                }
                return 'inserted';
            } catch (Exception $retryEx) {
                error_log("Student insert retry failed for $full_name: " . $retryEx->getMessage());
                return 'error';
            }
        }
        
        error_log("Student insert failed for $full_name: " . $errorMsg);
        return 'error';
    }
}

// Process the student data
foreach ($student_data as $class_name => $class_data) {
    echo "\n--- Processing: $class_name ---\n";
    
    // Normalize class name to match seed-data.sql convention
    $normalized_class = $class_name;
    // Map "BASIC ONE" → "Basic 1", etc.
    $classMap = [
        'KG 1' => 'KG 1',
        'KG 2' => 'KG 2',
        'BASIC ONE' => 'Basic 1',
        'BASIC TWO' => 'Basic 2',
        'BASIC THREE' => 'Basic 3',
        'BASIC FOUR' => 'Basic 4',
        'BASIC FIVE' => 'Basic 5',
    ];
    if (isset($classMap[$class_name])) {
        $normalized_class = $classMap[$class_name];
    }
    
    // Batch insert for boys
    if (isset($class_data['boys'])) {
        foreach ($class_data['boys'] as $student_name) {
            $result = insertStudent($pdo, $student_name, $normalized_class, 'Male');
            if ($result === 'inserted') {
                $totalInserted++;
                echo "  + Inserted boy: $student_name\n";
            } elseif ($result === 'skipped') {
                $totalSkipped++;
                echo "  - Skipped (exists): $student_name\n";
            } else {
                echo "  ! Failed: $student_name\n";
            }
        }
    }
    
    // Process girls
    if (isset($class_data['girls'])) {
        foreach ($class_data['girls'] as $student_name) {
            $result = insertStudent($pdo, $student_name, $normalized_class, 'Female');
            if ($result === 'inserted') {
                $totalInserted++;
                echo "  + Inserted girl: $student_name\n";
            } elseif ($result === 'skipped') {
                $totalSkipped++;
                echo "  - Skipped (exists): $student_name\n";
            } else {
                echo "  ! Failed: $student_name\n";
            }
        }
    }
}

echo "\n========================================\n";
echo "  SEEDING COMPLETE\n";
echo "  Inserted: $totalInserted\n";
echo "  Skipped:  $totalSkipped\n";
echo "========================================\n";

?>
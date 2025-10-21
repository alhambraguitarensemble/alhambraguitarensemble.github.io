<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// DB 파일 경로
$dbFile = 'visits.db';

// SQLite DB 초기화
function initDB($dbFile) {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 테이블 생성 (없는 경우)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date_key TEXT UNIQUE NOT NULL,
            count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    return $pdo;
}

// 방문 카운트 증가
function incrementVisit($pdo, $dateKey) {
    try {
        // UPSERT (INSERT OR UPDATE)
        $stmt = $pdo->prepare("
            INSERT INTO visits (date_key, count) 
            VALUES (?, 1) 
            ON CONFLICT(date_key) 
            DO UPDATE SET count = count + 1, updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$dateKey]);
        
        // 현재 카운트 조회
        $stmt = $pdo->prepare("SELECT count FROM visits WHERE date_key = ?");
        $stmt->execute([$dateKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['count'] : 0;
    } catch (Exception $e) {
        error_log("DB 오류: " . $e->getMessage());
        return 0;
    }
}

// 월별 카운트 조회
function getMonthCount($pdo, $yearMonth) {
    try {
        $stmt = $pdo->prepare("
            SELECT SUM(count) as total 
            FROM visits 
            WHERE date_key LIKE ?
        ");
        $stmt->execute([$yearMonth . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['total'] : 0;
    } catch (Exception $e) {
        error_log("DB 오류: " . $e->getMessage());
        return 0;
    }
}

// 일별 카운트 조회
function getDayCount($pdo, $dateKey) {
    try {
        $stmt = $pdo->prepare("SELECT count FROM visits WHERE date_key = ?");
        $stmt->execute([$dateKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['count'] : 0;
    } catch (Exception $e) {
        error_log("DB 오류: " . $e->getMessage());
        return 0;
    }
}

try {
    // DB 초기화
    $pdo = initDB($dbFile);
    
    // 현재 날짜 정보
    $now = new DateTime();
    $yearMonth = $now->format('Y-m');
    $dateKey = $now->format('Y-m-d');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['action']) && $input['action'] === 'increment') {
            // 방문 카운트 증가
            $dayCount = incrementVisit($pdo, $dateKey);
            $monthCount = getMonthCount($pdo, $yearMonth);
            
            echo json_encode([
                'success' => true,
                'dayCount' => $dayCount,
                'monthCount' => $monthCount,
                'date' => $dateKey
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 카운트 조회만
        $dayCount = getDayCount($pdo, $dateKey);
        $monthCount = getMonthCount($pdo, $yearMonth);
        
        echo json_encode([
            'success' => true,
            'dayCount' => $dayCount,
            'monthCount' => $monthCount,
            'date' => $dateKey
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("서버 오류: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Server error',
        'dayCount' => 0,
        'monthCount' => 0
    ]);
}
?>

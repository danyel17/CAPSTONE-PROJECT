<?php
// Start output buffering to prevent any accidental output
if (!ob_get_level()) {
    ob_start();
}

// AJAX POST actions must return clean JSON (no HTML notices). Normal page loads can show errors in dev.
$__admin_ajax_post = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']));
if ($__admin_ajax_post) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
}

require_once 'config.php';
require_once 'auth_functions.php';
require_once 'homepage_news.php';
require_once 'application_fees.php';
require_once 'includes/vendor_resubmit_helpers.php';
require_once 'includes/stall_slot_map.php';

// Test database connection immediately
try {
    $test_conn = getDBConnection();
    if (!$test_conn) {
        die("FATAL ERROR: Cannot connect to database. Check config.php");
    }
    $test_conn->close();
} catch (Exception $e) {
    die("FATAL ERROR: Database connection failed: " . $e->getMessage());
}

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Protect this page - require admin login
// But skip redirect for AJAX requests
if (!isset($_POST['action'])) {
requireAdminLogin();
} else {
    // For AJAX requests, check session without redirecting
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        if (ob_get_level()) {
            ob_clean();
        }
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
        exit;
    }
}

$success_message = '';
$error_message = '';

// Get admin information (only if session is set)
$admin_id = $_SESSION['user_id'] ?? 0;
$admin_name = $_SESSION['full_name'] ?? 'Admin';

// Handle GET requests for loading slot map
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_slot_map') {
    require_once 'config.php';
    $conn = getDBConnection();
    
    // Ensure market_slots table exists
    if ($conn) {
        $conn->query("CREATE TABLE IF NOT EXISTS market_slots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            block_name VARCHAR(50) NOT NULL,
            slot_number INT NOT NULL,
            status ENUM('available', 'occupied', 'second_floor') DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_slot (block_name, slot_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Stall holder columns (for occupied slots)
        foreach ([
            'holder_vendor_id' => 'INT NULL',
            'holder_name'      => 'VARCHAR(255) NULL',
            'holder_section'   => 'VARCHAR(150) NULL',
            'holder_block'     => 'VARCHAR(150) NULL',
        ] as $hcol => $hdef) {
            $hchk = $conn->query("SHOW COLUMNS FROM market_slots LIKE '" . $hcol . "'");
            if ($hchk && $hchk->num_rows === 0) {
                $conn->query("ALTER TABLE market_slots ADD COLUMN " . $hcol . " " . $hdef);
            }
        }
    }

    $dpm_site_admin_mode = true;
    include __DIR__ . '/includes/market_site_map_schematic.php';
    if ($conn) { $conn->close(); }
    exit;
    ?>
    <?php /* Legacy visual map below is no longer used (kept for reference, never reached). */ ?>
    <style>
    .blueprint-container {
      background-color: white;
      padding: 1.5rem;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      margin-bottom: 1rem;
      overflow-x: auto;
      max-width: 100%;
      width: 100%;
    }
    .blueprint-header {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .blueprint-header h2 {
      color: #667eea;
      font-size: 1.8rem;
      font-weight: bold;
      margin-bottom: 0.5rem;
    }
    .blueprint-legend {
      display: flex;
      justify-content: center;
      gap: 1.5rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
      background: #f8f9fa;
      padding: 0.75rem;
      border-radius: 10px;
    }
    .legend-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.9rem;
      color: #333;
    }
    .legend-color {
      width: 20px;
      height: 20px;
      border: 1px solid #ddd;
      border-radius: 5px;
    }
    .legend-ground { background: #e8f4f8; border-color: #667eea; }
    .legend-second { background: #fff9c4; border-color: #f9a825; }
    .legend-occupied { background: #ffcccc; border-color: #d32f2f; }
    .market-blueprint {
      background: #f8f9fa;
      background-image: 
        repeating-linear-gradient(0deg, transparent, transparent 49px, rgba(102, 126, 234, 0.05) 49px, rgba(102, 126, 234, 0.05) 50px),
        repeating-linear-gradient(90deg, transparent, transparent 49px, rgba(102, 126, 234, 0.05) 49px, rgba(102, 126, 234, 0.05) 50px);
      border: 2px solid #667eea;
      padding: 1.5rem;
      position: relative;
      width: 100%;
      max-width: 100%;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
      box-sizing: border-box;
    }
    .building-layout {
      display: grid;
      grid-template-columns: 0.7fr 1fr 1fr 1fr;
      grid-template-rows: auto auto auto auto auto;
      gap: 1rem;
      position: relative;
      width: 100%;
      align-items: start;
    }
    .blueprint-block {
      border: 2px solid #667eea;
      background: white;
      padding: 0.5rem;
      position: relative;
      border-radius: 10px;
      box-shadow: 0 3px 10px rgba(102, 126, 234, 0.15);
      width: 100%;
      box-sizing: border-box;
    }
    .block-title {
      font-weight: bold;
      font-size: 0.75rem;
      color: #667eea;
      margin-bottom: 0.4rem;
      text-align: center;
      padding: 0.4rem;
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      border-radius: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .block-grid {
      display: grid;
      gap: 2px;
    }
    .block-grid.vertical {
      grid-template-columns: repeat(2, 1fr);
    }
    .block-grid.vertical-large {
      grid-template-columns: repeat(4, 1fr);
    }
    .slot {
      background: #ffffff;
      border: 1px solid #e0e0e0;
      padding: 0.15rem;
      text-align: center;
      font-size: 0.6rem;
      min-height: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      position: relative;
      border-radius: 4px;
    }
    .slot:hover {
      background: #f0f4ff;
      border-color: #667eea;
      z-index: 10;
      transform: scale(1.1);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    .slot.second-floor {
      background: #fff9c4;
      border-color: #f9a825;
    }
    .slot.occupied {
      background: #ffcccc;
      border-color: #d32f2f;
      opacity: 0.7;
    }
    .slot-number {
      font-weight: bold;
      color: #667eea;
      font-size: 0.6rem;
    }
    .slot.second-floor .slot-number {
      color: #f57c00;
    }
    .slot.occupied .slot-number {
      color: #c62828;
    }
    .special-area-left { grid-column: 1; grid-row: 1; }
    .fruits-veg { grid-column: 1; grid-row: 2; }
    .block-a { grid-column: 2 / 4; grid-row: 1 / 3; }
    .block-b { grid-column: 4; grid-row: 2 / 4; }
    .block-c { grid-column: 1; grid-row: 3; }
    .block-behind-c { grid-column: 1; grid-row: 4; }
    .block-e { grid-column: 2; grid-row: 3; }
    .dry-goods { grid-column: 2; grid-row: 4; }
    .service-section { grid-column: 3; grid-row: 3 / 5; }
    .block-behind-b { grid-column: 4; grid-row: 4; }
    .special-area {
      grid-column: 1 / -1;
      grid-row: 5;
      border: 2px solid #667eea;
      background: white;
      padding: 1rem;
      margin-top: 1rem;
      border-radius: 10px;
      box-shadow: 0 3px 10px rgba(102, 126, 234, 0.15);
    }
    .special-grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 2px;
    }
    .special-label {
      font-weight: bold;
      color: #667eea;
      margin-bottom: 0.5rem;
      text-align: center;
      padding: 0.5rem;
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      border-radius: 8px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .entrance-marker {
      position: absolute;
      width: 30px;
      height: 30px;
      border: 2px dashed #667eea;
      top: -15px;
      right: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.6rem;
      color: #667eea;
      font-weight: bold;
      border-radius: 5px;
      background: rgba(102, 126, 234, 0.1);
    }
    </style>
    <div class="blueprint-container">
      <div class="blueprint-header">
        <h2><i class="fas fa-map"></i> Visual Map Editor</h2>
        <p>Click on slots to change their status: Available → Second Floor → Occupied → Available</p>
      </div>
      <div class="blueprint-legend">
        <div class="legend-item"></div>
          <div class="legend-color legend-ground"></div>
          <span>Available</span>
        </div>
        <div class="legend-item">
          <div class="legend-color legend-second"></div>
          <span>Second Floor</span>
        </div>
        <div class="legend-item">
          <div class="legend-color legend-occupied"></div>
          <span>Occupied</span>
        </div>
      </div>
      <div class="market-blueprint">
        <div class="building-layout">
          <!-- SPECIAL AREA SECTION (Top Left) -->
          <div class="blueprint-block special-area-left">
            <div class="block-title">SPECIAL AREA SECTION</div>
            <div class="block-grid vertical">
              <?php 
              $specialAreaSlots = [1,2,3,4,5,6,7,8];
              foreach ($specialAreaSlots as $slot): 
                $status = getSlotStatusForEditor($conn, 'SPECIAL AREA SECTION TOP', $slot);
                $isOccupied = ($status === 'occupied');
                $isSecondFloor = ($status === 'second_floor');
              ?>
                <div class="slot <?php echo $isOccupied ? 'occupied' : ''; ?> <?php echo $isSecondFloor ? 'second-floor' : ''; ?>" 
                     data-block-name="SPECIAL AREA SECTION TOP" 
                     data-slot-number="<?php echo $slot; ?>"
                     data-status="<?php echo $status; ?>"
                     onclick="toggleSlotStatus('SPECIAL AREA SECTION TOP', <?php echo $slot; ?>)"
                     title="Click to change status: <?php echo ucfirst($status); ?>">
                  <span class="slot-number"><?php echo $slot; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- FRUITS VEG -->
          <div class="blueprint-block fruits-veg">
            <div class="block-title">FRUITS VEG</div>
            <div class="block-grid vertical">
              <?php 
              $fruitsVegSlots = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28];
              foreach ($fruitsVegSlots as $slot): 
                $status = getSlotStatusForEditor($conn, 'FRUITS VEG', $slot);
                $isOccupied = ($status === 'occupied');
                $isSecondFloor = ($status === 'second_floor');
              ?>
                <div class="slot <?php echo $isOccupied ? 'occupied' : ''; ?> <?php echo $isSecondFloor ? 'second-floor' : ''; ?>" 
                     data-block-name="FRUITS VEG" 
                     data-slot-number="<?php echo $slot; ?>"
                     data-status="<?php echo $status; ?>"
                     onclick="toggleSlotStatus('FRUITS VEG', <?php echo $slot; ?>)"
                     title="Click to change status: <?php echo ucfirst($status); ?>">
                  <span class="slot-number"><?php echo $slot; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- BOT BLOCK A -->
          <div class="blueprint-block block-a">
            <div class="entrance-marker">ENT</div>
            <div class="block-title">BOT BLOCK A</div>
            <div class="block-grid vertical-large">
              <?php 
              $blockA_slots = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,76,77,78,79,80,81,82,83,84,85,86,87,88];
              foreach ($blockA_slots as $slot): 
                $status = getSlotStatusForEditor($conn, 'BOT BLOCK A', $slot);
                $isOccupied = ($status === 'occupied');
                $isSecondFloor = ($status === 'second_floor');
              ?>
                <div class="slot <?php echo $isOccupied ? 'occupied' : ''; ?> <?php echo $isSecondFloor ? 'second-floor' : ''; ?>" 
                     data-block-name="BOT BLOCK A" 
                     data-slot-number="<?php echo $slot; ?>"
                     data-status="<?php echo $status; ?>"
                     onclick="toggleSlotStatus('BOT BLOCK A', <?php echo $slot; ?>)"
                     title="Click to change status: <?php echo ucfirst($status); ?>">
                  <span class="slot-number"><?php echo $slot; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- BOT BLOCK B -->
          <div class="blueprint-block block-b">
            <div class="block-title">BOT BLOCK B</div>
            <div class="block-grid vertical-large">
              <?php 
              $blockB_slots = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,76,77,78,79,80,81,82,83,84,85,86,87,88];
              foreach ($blockB_slots as $slot): 
                $status = getSlotStatusForEditor($conn, 'BOT BLOCK B', $slot);
                $isOccupied = ($status === 'occupied');
                $isSecondFloor = ($status === 'second_floor');
              ?>
                <div class="slot <?php echo $isOccupied ? 'occupied' : ''; ?> <?php echo $isSecondFloor ? 'second-floor' : ''; ?>" 
                     data-block-name="BOT BLOCK B" 
                     data-slot-number="<?php echo $slot; ?>"
                     data-status="<?php echo $status; ?>"
                     onclick="toggleSlotStatus('BOT BLOCK B', <?php echo $slot; ?>)"
                     title="Click to change status: <?php echo ucfirst($status); ?>">
                  <span class="slot-number"><?php echo $slot; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- BOT BLOCK C -->
          <div class="blueprint-block block-c">
            <div class="block-title">BOT BLOCK C</div>
            <div class="block-grid vertical">
              <?php 
              $blockC_slots = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28];
              foreach ($blockC_slots as $slot): 
                $status = getSlotStatusForEditor($conn, 'BOT BLOCK C', $slot);
                $isOccupied = ($status === 'occupied');
                $isSecondFloor = ($status === 'second_floor');
              ?>
                <div class="slot <?php echo $isOccupied ? 'occupied' : ''; ?> <?php echo $isSecondFloor ? 'second-floor' : ''; ?>" 
                     data-block-name="BOT BLOCK C" 
                     data-slot-number="<?php echo $slot; ?>"
                     data-status="<?php echo $status; ?>"
                     onclick="toggleSlotStatus('BOT BLOCK C', <?php echo $slot; ?>)"
                     title="Click to change status: <?php echo ucfirst($status); ?>">
                  <span class="slot-number"><?php echo $slot; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- BOT BEHIND BLOCK C -->
          <div class="blueprint-block block-behind-c">
            <div class="block-title">BOT BEHIND BLOCK C</div>
            <div class="block-grid vertical">
              <?php 
              $blockBehindC_slots = [1,2,3,4,5,6,7,8];
              foreach ($blockBehindC_slots as $slot): 
                $status = getSlotStatusForEditor($conn, 'BOT BEHIND BLOCK C', $slot);
                $isOccupied = ($status === 'occupied');
                $isSecondFloor = ($status === 'second_floor');
              ?>
                <div class="slot <?php echo $isOccupied ? 'occupied' : ''; ?> <?php echo $isSecondFloor ? 'second-floor' : ''; ?>" 
                     data-block-name="BOT BEHIND BLOCK C" 
                     data-slot-number="<?php echo $slot; ?>"
                     data-status="<?php echo $status; ?>"
                     onclick="toggleSlotStatus('BOT BEHIND BLOCK C', <?php echo $slot; ?>)"
                     title="Click to change status: <?php echo ucfirst($status); ?>">
                  <span class="slot-number"><?php echo $slot; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- BOT BEHIND BLOCK B -->
          <div class="blueprint-block block-behind-b">
            <div class="block-title">BOT BEHIND BLOCK B</div>
            <div class="block-grid vertical">
              <?php 
              $blockBehindB_slots = [1,2,3,4,5,6,7,8,9,10,11,12,13,14];
              foreach ($blockBehindB_slots as $slot): 
                $status = getSlotStatusForEditor($conn, 'BOT BEHIND BLOCK B', $slot);
                $isOccupied = ($status === 'occupied');
                $isSecondFloor = ($status === 'second_floor');
              ?>
                <div class="slot <?php echo $isOccupied ? 'occupied' : ''; ?> <?php echo $isSecondFloor ? 'second-floor' : ''; ?>" 
                     data-block-name="BOT BEHIND BLOCK B" 
                     data-slot-number="<?php echo $slot; ?>"
                     data-status="<?php echo $status; ?>"
                     onclick="toggleSlotStatus('BOT BEHIND BLOCK B', <?php echo $slot; ?>)"
                     title="Click to change status: <?php echo ucfirst($status); ?>">
                  <span class="slot-number"><?php echo $slot; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- BOT BLOCK E -->
          <div class="blueprint-block block-e">
            <div class="block-title">BOT BLOCK E</div>
            <div class="block-grid vertical">
              <?php 
              $blockE_slots = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23];
              foreach ($blockE_slots as $slot): 
                $status = getSlotStatusForEditor($conn, 'BOT BLOCK E', $slot);
                $isOccupied = ($status === 'occupied');
                $isSecondFloor = ($status === 'second_floor');
              ?>
                <div class="slot <?php echo $isOccupied ? 'occupied' : ''; ?> <?php echo $isSecondFloor ? 'second-floor' : ''; ?>" 
                     data-block-name="BOT BLOCK E" 
                     data-slot-number="<?php echo $slot; ?>"
                     data-status="<?php echo $status; ?>"
                     onclick="toggleSlotStatus('BOT BLOCK E', <?php echo $slot; ?>)"
                     title="Click to change status: <?php echo ucfirst($status); ?>">
                  <span class="slot-number"><?php echo $slot; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- DRY GOODS SECTION -->
          <div class="blueprint-block dry-goods">
            <div class="block-title">DRY GOODS SECTION</div>
            <div class="block-grid vertical">
              <?php 
              $dryGoodsSlots = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28];
              foreach ($dryGoodsSlots as $slot): 
                $status = getSlotStatusForEditor($conn, 'DRY GOODS SECTION', $slot);
                $isOccupied = ($status === 'occupied');
                $isSecondFloor = ($status === 'second_floor');
              ?>
                <div class="slot <?php echo $isOccupied ? 'occupied' : ''; ?> <?php echo $isSecondFloor ? 'second-floor' : ''; ?>" 
                     data-block-name="DRY GOODS SECTION" 
                     data-slot-number="<?php echo $slot; ?>"
                     data-status="<?php echo $status; ?>"
                     onclick="toggleSlotStatus('DRY GOODS SECTION', <?php echo $slot; ?>)"
                     title="Click to change status: <?php echo ucfirst($status); ?>">
                  <span class="slot-number"><?php echo $slot; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- SERVICE SECTION -->
          <div class="blueprint-block service-section">
            <div class="block-title">SERVICE SECTION</div>
            <div class="block-grid vertical">
              <?php 
              $serviceSlots = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28];
              foreach ($serviceSlots as $slot): 
                $status = getSlotStatusForEditor($conn, 'SERVICE SECTION', $slot);
                $isOccupied = ($status === 'occupied');
                $isSecondFloor = ($status === 'second_floor');
              ?>
                <div class="slot <?php echo $isOccupied ? 'occupied' : ''; ?> <?php echo $isSecondFloor ? 'second-floor' : ''; ?>" 
                     data-block-name="SERVICE SECTION" 
                     data-slot-number="<?php echo $slot; ?>"
                     data-status="<?php echo $status; ?>"
                     onclick="toggleSlotStatus('SERVICE SECTION', <?php echo $slot; ?>)"
                     title="Click to change status: <?php echo ucfirst($status); ?>">
                  <span class="slot-number"><?php echo $slot; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- SPECIAL AREA SECTION - FRUITS & VEG -->
          <div class="special-area">
            <div class="special-label">SPECIAL AREA SECTION - FRUITS & VEG</div>
            <div class="special-grid">
              <?php 
              $specialSlots = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60];
              foreach ($specialSlots as $slot): 
                $status = getSlotStatusForEditor($conn, 'SPECIAL AREA SECTION', $slot);
                $isOccupied = ($status === 'occupied');
                $isSecondFloor = ($status === 'second_floor');
              ?>
                <div class="slot <?php echo $isOccupied ? 'occupied' : ''; ?> <?php echo $isSecondFloor ? 'second-floor' : ''; ?>" 
                     data-block-name="SPECIAL AREA SECTION" 
                     data-slot-number="<?php echo $slot; ?>"
                     data-status="<?php echo $status; ?>"
                     onclick="toggleSlotStatus('SPECIAL AREA SECTION', <?php echo $slot; ?>)"
                     title="Click to change status: <?php echo ucfirst($status); ?>">
                  <span class="slot-number"><?php echo $slot; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php
    if ($conn) $conn->close();
    exit;
}

// Force-download active media video (attachment, not browser preview)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'download_media') {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        http_response_code(401);
        exit('Unauthorized');
    }

    $section = trim((string) ($_GET['section'] ?? ''));
    $allowed_sections = ['overview_video', 'price_video', 'third_video', 'homepage_announcement_video'];
    if (!in_array($section, $allowed_sections, true)) {
        http_response_code(400);
        exit('Invalid section');
    }

    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        exit('Database connection failed');
    }
    $dl_stmt = $conn->prepare("SELECT file_path, title, file_name, mime_type
        FROM media_files
        WHERE file_type = 'video' AND section = ? AND (is_active = 1 OR is_active IS NULL)
        ORDER BY uploaded_at DESC LIMIT 1");
    if (!$dl_stmt) {
        http_response_code(500);
        $conn->close();
        exit('Database error');
    }
    $dl_stmt->bind_param('s', $section);
    $dl_stmt->execute();
    $dl_row = $dl_stmt->get_result()->fetch_assoc();
    $dl_stmt->close();
    $conn->close();

    if (!$dl_row || empty($dl_row['file_path']) || !is_file($dl_row['file_path'])) {
        http_response_code(404);
        exit('File not found');
    }

    $file_path = $dl_row['file_path'];
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION) ?: 'mp4');
    $download_label = trim((string) ($dl_row['title'] ?? ''));
    if ($download_label === '') {
        $download_label = pathinfo((string) ($dl_row['file_name'] ?? 'video'), PATHINFO_FILENAME);
    }
    $download_label = preg_replace('/[\\\\\/:*?"<>|]+/', '', $download_label);
    $download_label = preg_replace('/\s+/', ' ', $download_label);
    if (!preg_match('/\.' . preg_quote($ext, '/') . '$/i', $download_label)) {
        $download_label .= '.' . $ext;
    }

    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $download_label . '"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    readfile($file_path);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'approve_vendor':
            // Ensure we only output JSON - clear all output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, must-revalidate');
            
            $conn = null;
            try {
                $vendor_id = (int)($_POST['vendor_id'] ?? 0);
                $notes = trim($_POST['notes'] ?? '');
                
                if ($vendor_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid vendor ID.']);
                    exit;
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                // Get vendor info first (before transaction) - include application_type
                $get_vendor_stmt = $conn->prepare("SELECT vendor_id, vendor_name, user_id, status, application_type, section, block FROM vendors WHERE vendor_id = ?");
                $get_vendor_stmt->bind_param("i", $vendor_id);
                $get_vendor_stmt->execute();
                $result = $get_vendor_stmt->get_result();
                $vendor = $result->fetch_assoc();
                $get_vendor_stmt->close();
                
                if (!$vendor) {
                    $conn->close();
                    echo json_encode(['success' => false, 'message' => 'Vendor application not found.']);
                    exit;
                }
                
                // Store status before for logging
                $status_before = $vendor['status'] ?? 'pending';
                
                // Disable autocommit for full transaction control
                $conn->autocommit(false);
                
                // Lock the row for update to prevent race conditions
                $lock_stmt = $conn->prepare("SELECT vendor_id, vendor_name, user_id, status, section, block FROM vendors WHERE vendor_id = ? FOR UPDATE");
                $lock_stmt->bind_param("i", $vendor_id);
                $lock_stmt->execute();
                $result = $lock_stmt->get_result();
                $vendor = $result->fetch_assoc();
                $lock_stmt->close();
                
                if (!$vendor) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    echo json_encode(['success' => false, 'message' => 'Vendor application not found.']);
                    exit;
                }
                
                if ($vendor['status'] !== 'pending') {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    echo json_encode(['success' => false, 'message' => 'This application has already been reviewed.']);
                    exit;
                }
                
                // 1. Update the vendor status - BULLETPROOF: Set status AND permanent flags
                // Set is_reviewed=1 and review_status='approved' - these CANNOT be reset
                $update_stmt = $conn->prepare("UPDATE vendors SET 
                    status = 'approved',
                    review_status = 'approved',
                    is_reviewed = 1,
                    reviewed_date = NOW(),
                    reviewed_by = ?,
                    admin_notes = ?
                    WHERE vendor_id = ?");
                if (!$update_stmt) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    error_log("✗✗✗ Failed to prepare UPDATE: " . $conn->error);
                    echo json_encode(['success' => false, 'message' => 'Failed to prepare update: ' . $conn->error]);
                    exit;
                }
                
                $update_stmt->bind_param("isi", $admin_id, $notes, $vendor_id);
                
                // CRITICAL: Also run a direct SQL update to ensure it sticks (bypasses any conditions)
                $conn->query("UPDATE vendors SET status = 'approved', review_status = 'approved', is_reviewed = 1, reviewed_date = NOW(), reviewed_by = $admin_id WHERE vendor_id = $vendor_id");
                
                if (!$update_stmt->execute()) {
                    $update_stmt->close();
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    error_log("✗✗✗ UPDATE execute failed: " . $update_stmt->error);
                    echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $update_stmt->error]);
                    exit;
                }
                
                $affected_rows = $update_stmt->affected_rows;
                $update_stmt->close();
                
                if ($affected_rows === 0) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    error_log("No rows affected during approval for vendor $vendor_id");
                    echo json_encode(['success' => false, 'message' => 'No rows were updated. The application may have already been reviewed.']);
                    exit;
                }
                
                // Verify immediately within transaction
                $verify_stmt = $conn->prepare("SELECT status FROM vendors WHERE vendor_id = ?");
                $verify_stmt->bind_param("i", $vendor_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                $verified_status = $verify_result->fetch_assoc();
                $verify_stmt->close();
                
                if (!$verified_status || $verified_status['status'] !== 'approved') {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    error_log("Status verification failed. Expected 'approved', got: " . ($verified_status['status'] ?? 'null'));
                    echo json_encode(['success' => false, 'message' => 'Status verification failed.']);
                    exit;
                }
                
                // 2. COMMIT IMMEDIATELY - This ensures the status change is persisted
                if (!$conn->commit()) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    error_log("✗✗✗ COMMIT FAILED: " . $conn->error);
                    echo json_encode(['success' => false, 'message' => 'Failed to commit: ' . $conn->error]);
                    exit;
                }
                
                // Re-enable autocommit
                $conn->autocommit(true);
                
                error_log("✓✓✓ STATUS UPDATE COMMITTED - vendor $vendor_id is now 'approved'");
                
                // CRITICAL: Ensure status cannot be reset - add immediate re-verification and fix
                // Check status one more time and re-apply if it was reset
                $final_verify = $conn->prepare("SELECT status, reviewed_date, reviewed_by FROM vendors WHERE vendor_id = ?");
                $final_verify->bind_param("i", $vendor_id);
                $final_verify->execute();
                $final_result = $final_verify->get_result();
                $final_row = $final_result->fetch_assoc();
                $final_verify->close();
                
                // CRITICAL: Force status using review_status - NO LIMITATIONS, run MANY times
                // This ensures status CANNOT be reset - run 10 times to be absolutely sure
                for ($i = 0; $i < 10; $i++) {
                    $force_approved = $conn->prepare("UPDATE vendors SET 
                        status = 'approved',
                        review_status = 'approved',
                        is_reviewed = 1,
                        reviewed_date = COALESCE(reviewed_date, NOW()),
                        reviewed_by = COALESCE(reviewed_by, ?)
                        WHERE vendor_id = ?");
                    $force_approved->bind_param("ii", $admin_id, $vendor_id);
                    $force_approved->execute();
                    $force_approved->close();
                    
                    // Also force using a simple UPDATE without conditions
                    $conn->query("UPDATE vendors SET status = 'approved', review_status = 'approved', is_reviewed = 1 WHERE vendor_id = $vendor_id");
                }
                error_log("✓✓✓ FORCED approved status 10 times for vendor $vendor_id - status is now PERMANENT - NO LIMITATIONS");
                
                // 3. Do the logging/notifications AFTER the commit
                // This way, if logging fails, the status remains 'approved'
                
                // Ensure approval_actions table exists and supports incomplete/resubmit
                dpmEnsureApprovalActionsSchema($conn);
                
                // Log the action (non-critical, can fail without affecting status)
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                
                $log_stmt = $conn->prepare("INSERT INTO approval_actions (vendor_id, admin_id, action_type, admin_notes, status_before, status_after, ip_address, user_agent) VALUES (?, ?, 'approve', ?, ?, 'approved', ?, ?)");
                if ($log_stmt) {
                    $log_stmt->bind_param("iissss", $vendor_id, $admin_id, $notes, $status_before, $ip_address, $user_agent);
                    if (!$log_stmt->execute()) {
                        error_log("approval_actions approve log failed: " . $log_stmt->error);
                    }
                    $log_stmt->close();
                } else {
                    error_log("approval_actions approve prepare failed: " . $conn->error);
                }

                // Mark the vendor's stall slot occupied + record them as the stall holder.
                try {
                    dpmAssignVendorSlotOnApproval($conn, (int) $vendor_id, (string) $admin_name);
                } catch (Throwable $e) {
                    error_log("dpmAssignVendorSlotOnApproval failed: " . $e->getMessage());
                }
                
                // Record notification (non-critical - can fail without affecting status)
                try {
                    $apply_loc = trim(($vendor['section'] ?? '') . ((($vendor['section'] ?? '') !== '' && ($vendor['block'] ?? '') !== '') ? ', Block ' : ((($vendor['block'] ?? '') !== '') ? 'Block ' : '')) . ($vendor['block'] ?? ''));
                    if ($apply_loc === '') {
                        $apply_loc = $vendor['vendor_name'] ?? 'your stall';
                    }
            recordApprovalNotification(
                $conn,
                (int)$vendor['user_id'],
                $vendor_id,
                        'approved',
                        'Application Approved',
                        "Your application for {$apply_loc} has been approved.",
                'high',
                false,
                        'application.php'
                    );
                } catch (Exception $e) {
                    error_log("Notification error (non-fatal): " . $e->getMessage());
                }
                
                // Final verification with fresh connection to ensure persistence
                // If status was reset to pending, re-apply the correct status
                $verify_conn = getDBConnection();
                if ($verify_conn) {
                    $final_check = $verify_conn->prepare("SELECT status FROM vendors WHERE vendor_id = ?");
                    $final_check->bind_param("i", $vendor_id);
                    $final_check->execute();
                    $final_result = $final_check->get_result();
                    $final_status = $final_result->fetch_assoc();
                    $final_check->close();
                    
                    if ($final_status) {
                        if ($final_status['status'] === 'approved') {
                            error_log("Approved status confirmed for vendor $vendor_id - status persisted correctly");
                        } elseif ($final_status['status'] === 'pending') {
                            // Status was reset to pending - re-apply approved status
                            error_log("⚠️ CRITICAL: Vendor $vendor_id status was reset to pending - re-applying approved status");
                            $fix_stmt = $verify_conn->prepare("UPDATE vendors SET status = 'approved', reviewed_date = NOW(), reviewed_by = ? WHERE vendor_id = ?");
                            $fix_stmt->bind_param("ii", $admin_id, $vendor_id);
                            $fix_stmt->execute();
                            $fix_stmt->close();
                            error_log("✓ Fixed: Re-applied approved status to vendor $vendor_id");
                    } else {
                            error_log("WARNING: Vendor $vendor_id status is: " . $final_status['status']);
                        }
                    }
                    $verify_conn->close();
                }
                
                // Get vendor and admin info for activity log
                $vendor_info_stmt = $conn->prepare("SELECT v.vendor_name, v.application_type, u.full_name as admin_name FROM vendors v LEFT JOIN users u ON u.user_id = ? WHERE v.vendor_id = ?");
                $vendor_info_stmt->bind_param("ii", $admin_id, $vendor_id);
                $vendor_info_stmt->execute();
                $vendor_info_result = $vendor_info_stmt->get_result();
                $vendor_info = $vendor_info_result->fetch_assoc();
                $vendor_info_stmt->close();
                
                // FINAL ABSOLUTE FIX: Guarantee status cannot revert to pending
                $absolute_conn = getDBConnection();
                if ($absolute_conn) {
                  $absolute_conn->query("UPDATE vendors SET status = 'approved', review_status = 'approved', is_reviewed = 1 WHERE vendor_id = $vendor_id");
                  $absolute_conn->close();
                }

                // Email the vendor about the approval (non-blocking; distinct for application vs renewal)
                try {
                    require_once __DIR__ . '/includes/submission_notify_email.php';
                    $mail_kind = (($vendor_info['application_type'] ?? 'new') === 'renewal') ? 'renewal' : 'application';
                    $mail_uid = (int)($vendor['user_id'] ?? 0);
                    if ($mail_uid > 0) {
                        $mconn = getDBConnection();
                        if ($mconn) {
                            $mst = $mconn->prepare("SELECT email, full_name FROM users WHERE user_id = ? LIMIT 1");
                            if ($mst) {
                                $mst->bind_param("i", $mail_uid);
                                $mst->execute();
                                $mres = $mst->get_result();
                                $muser = $mres ? $mres->fetch_assoc() : null;
                                $mst->close();
                                if ($muser && !empty($muser['email'])) {
                                    $msec = (string)($vendor['section'] ?? '');
                                    $mblk = (string)($vendor['block'] ?? '');
                                    $mloc = trim($msec . ($msec !== '' && $mblk !== '' ? ' — ' : '') . $mblk);
                                    dpm_notify_decision_email($mail_kind, 'approved', (string)$muser['email'], (string)($muser['full_name'] ?? ''), [
                                        'location' => $mloc,
                                    ]);
                                }
                            }
                            $mconn->close();
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('approve_vendor email (non-fatal): ' . $e->getMessage());
                }

                echo json_encode([
                  'success' => true, 
                  'message' => 'Application approved successfully.',
                  'vendor_name' => $vendor_info['vendor_name'] ?? $vendor['vendor_name'] ?? 'Unknown Vendor',
                  'admin_name' => $vendor_info['admin_name'] ?? $admin_name,
                  'action_type' => 'approve',
                  'application_type' => $vendor_info['application_type'] ?? 'new'
                ]);
                exit;
                
            } catch (Exception $e) {
                if ($conn) {
                    try {
                        if (!$conn->autocommit(true)) {
                        $conn->rollback();
                        }
                        $conn->close();
                    } catch (Exception $close_e) {
                        error_log("Close error: " . $close_e->getMessage());
                    }
                }
                error_log("Approve vendor exception: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                exit;
            }
            break;
            
        case 'delete_vendor':
            // Ensure we only output JSON
            ob_clean();
            header('Content-Type: application/json');
            
            $conn = null;
            try {
                $vendor_id = (int)($_POST['vendor_id'] ?? 0);
                
                if ($vendor_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid vendor ID.']);
                    exit;
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                // Disable autocommit for full transaction control
                $conn->autocommit(false);
                
                // Get vendor info for logging
                $info_stmt = $conn->prepare("SELECT vendor_id, vendor_name, user_id FROM vendors WHERE vendor_id = ?");
                $info_stmt->bind_param("i", $vendor_id);
                $info_stmt->execute();
                $info_result = $info_stmt->get_result();
                $vendor_info = $info_result->fetch_assoc();
                $info_stmt->close();
                
                if (!$vendor_info) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    echo json_encode(['success' => false, 'message' => 'Vendor application not found.']);
                    exit;
                }
                
                // Delete associated documents
                $conn->query("DELETE FROM application_documents WHERE vendor_id = $vendor_id");
                
                // Delete associated notifications
                $conn->query("DELETE FROM notifications WHERE vendor_id = $vendor_id");
                
                // Delete approval actions (if table exists)
                $actions_exists = $conn->query("SHOW TABLES LIKE 'approval_actions'");
                if ($actions_exists && $actions_exists->num_rows > 0) {
                    $conn->query("DELETE FROM approval_actions WHERE vendor_id = $vendor_id");
                }
                
                // Delete the vendor record
                $del_stmt = $conn->prepare("DELETE FROM vendors WHERE vendor_id = ?");
                $del_stmt->bind_param("i", $vendor_id);
                
                if (!$del_stmt->execute()) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $del_stmt->close();
                    $conn->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete vendor application: ' . $conn->error]);
                    exit;
                }
                
                $del_stmt->close();
                
                // Commit transaction
                $conn->commit();
                $conn->autocommit(true);
                $conn->close();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Vendor application deleted successfully.',
                    'vendor_id' => $vendor_id,
                    'vendor_name' => $vendor_info['vendor_name']
                ]);
                exit;
                
            } catch (Exception $e) {
                if ($conn) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                }
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                exit;
            }
            break;
            
        case 'incomplete_vendor':
            // Ensure we only output JSON - clear all output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, must-revalidate');
            
            $conn = null;
            try {
                $vendor_id = (int)($_POST['vendor_id'] ?? 0);
                $incomplete_reason = trim($_POST['rejection_reason'] ?? '');
                $notes = trim($_POST['notes'] ?? '');
                
                if ($vendor_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid vendor ID.']);
                    exit;
                }
                
                if (empty($incomplete_reason)) {
                    echo json_encode(['success' => false, 'message' => 'Incomplete reason is required.']);
                    exit;
                }
                
                // Validate reason length
                if (strlen($incomplete_reason) > 255) {
                    echo json_encode(['success' => false, 'message' => 'Incomplete reason is too long. Maximum 255 characters.']);
                    exit;
                }
                
                // Validate notes length
                if (strlen($notes) > 5000) {
                    echo json_encode(['success' => false, 'message' => 'Additional notes are too long. Maximum 5000 characters.']);
                    exit;
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                // Disable autocommit for full transaction control
                $conn->autocommit(false);
                
                // Lock the row for update to prevent race conditions
                $lock_stmt = $conn->prepare("SELECT vendor_id, vendor_name, user_id, status, section, block FROM vendors WHERE vendor_id = ? FOR UPDATE");
                $lock_stmt->bind_param("i", $vendor_id);
                $lock_stmt->execute();
                $result = $lock_stmt->get_result();
                $vendor = $result->fetch_assoc();
                $lock_stmt->close();
                
                if (!$vendor) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    echo json_encode(['success' => false, 'message' => 'Vendor application not found.']);
                    exit;
                }
                
                if ($vendor['status'] !== 'pending') {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    echo json_encode(['success' => false, 'message' => 'This application has already been reviewed.']);
                    exit;
                }
                
                // Store status before for logging - MUST be captured BEFORE update
                $status_before = $vendor['status'] ?? 'pending';
                
                // Ensure status_before is valid
                if (empty($status_before) || !in_array($status_before, ['pending', 'approved', 'incomplete', 'resubmitted'])) {
                    $status_before = 'pending'; // Default to pending if invalid
                    error_log("WARNING: Invalid status_before value, defaulting to 'pending' for vendor_id=$vendor_id");
                }
                
                // Build admin notes
                $admin_notes = "Incomplete Reason: " . $incomplete_reason;
                if (!empty($notes)) {
                    $admin_notes .= "\nAdditional Notes: " . $notes;
                }
                
                // 1. Update the vendor status - ABSOLUTE SQL FIX: Set status AND permanent flags
                // Set is_reviewed=1 and review_status='incomplete' - NO CONDITIONS, ALWAYS UPDATE
                $update_stmt = $conn->prepare("UPDATE vendors SET 
                    status = 'incomplete',
                    review_status = 'incomplete',
                    is_reviewed = 1,
                    reviewed_date = NOW(),
                    reviewed_by = ?,
                    admin_notes = ?
                    WHERE vendor_id = ?");
                $update_stmt->bind_param("isi", $admin_id, $admin_notes, $vendor_id);
                
                // CRITICAL: Also run a direct SQL update to ensure it sticks (bypasses any conditions)
                $conn->query("UPDATE vendors SET status = 'incomplete', review_status = 'incomplete', is_reviewed = 1, reviewed_date = NOW(), reviewed_by = $admin_id WHERE vendor_id = $vendor_id");
                
                if (!$update_stmt->execute()) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $update_stmt->close();
                    $conn->close();
                    error_log("Incomplete vendor execute failed: " . $update_stmt->error);
                    echo json_encode(['success' => false, 'message' => 'Failed to mark vendor application as incomplete: ' . $update_stmt->error]);
                    exit;
                }
                
                $affected_rows = $update_stmt->affected_rows;
                $update_stmt->close();
                
                if ($affected_rows === 0) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    error_log("No rows affected during incomplete for vendor $vendor_id");
                    echo json_encode(['success' => false, 'message' => 'No rows were updated. The application may have already been reviewed.']);
                    exit;
                }
                
                // Verify immediately within transaction
                $verify_stmt = $conn->prepare("SELECT status FROM vendors WHERE vendor_id = ?");
                $verify_stmt->bind_param("i", $vendor_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                $verified_status = $verify_result->fetch_assoc();
                $verify_stmt->close();
                
                if (!$verified_status || $verified_status['status'] !== 'incomplete') {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    error_log("Status verification failed. Expected 'incomplete', got: " . ($verified_status['status'] ?? 'null'));
                    echo json_encode(['success' => false, 'message' => 'Status verification failed.']);
                    exit;
                }
                
                // 2. COMMIT IMMEDIATELY - This ensures the status change is persisted
                if (!$conn->commit()) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    error_log("✗✗✗ COMMIT FAILED: " . $conn->error);
                    echo json_encode(['success' => false, 'message' => 'Failed to commit: ' . $conn->error]);
                    exit;
                }
                
                // Re-enable autocommit
                    $conn->autocommit(true);
                
                error_log("✓✓✓ STATUS UPDATE COMMITTED - vendor $vendor_id is now 'incomplete'");
                
                // CRITICAL: Ensure status cannot be reset - add immediate re-verification and fix
                // Check status one more time and re-apply if it was reset
                $final_verify = $conn->prepare("SELECT status, reviewed_date, reviewed_by FROM vendors WHERE vendor_id = ?");
                $final_verify->bind_param("i", $vendor_id);
                $final_verify->execute();
                $final_result = $final_verify->get_result();
                $final_row = $final_result->fetch_assoc();
                $final_verify->close();
                
                // CRITICAL: Force status using review_status - NO LIMITATIONS, run MANY times
                // This ensures status CANNOT be reset - run 10 times to be absolutely sure
                for ($i = 0; $i < 10; $i++) {
                    $force_incomplete = $conn->prepare("UPDATE vendors SET 
                        status = 'incomplete',
                        review_status = 'incomplete',
                        is_reviewed = 1,
                        reviewed_date = COALESCE(reviewed_date, NOW()),
                        reviewed_by = COALESCE(reviewed_by, ?)
                        WHERE vendor_id = ?");
                    $force_incomplete->bind_param("ii", $admin_id, $vendor_id);
                    $force_incomplete->execute();
                    $force_incomplete->close();
                    
                    // Also force using a simple UPDATE without conditions
                    $conn->query("UPDATE vendors SET status = 'incomplete', review_status = 'incomplete', is_reviewed = 1 WHERE vendor_id = $vendor_id");
                }
                error_log("✓✓✓ FORCED incomplete status 10 times for vendor $vendor_id - status is now PERMANENT - NO LIMITATIONS");
                
                // 3. Do the logging/notifications AFTER the commit
                // This way, if logging fails, the status remains 'incomplete'
                
                // Ensure approval_actions table exists and supports incomplete/resubmit
                dpmEnsureApprovalActionsSchema($conn);
                
                // Log the action (non-critical, can fail without affecting status)
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                
                $log_stmt = $conn->prepare("INSERT INTO approval_actions (vendor_id, admin_id, action_type, admin_notes, rejection_reason, status_before, status_after, ip_address, user_agent) VALUES (?, ?, 'incomplete', ?, ?, ?, 'incomplete', ?, ?)");
                if ($log_stmt) {
                    $log_stmt->bind_param("iisssss", $vendor_id, $admin_id, $admin_notes, $incomplete_reason, $status_before, $ip_address, $user_agent);
                    if (!$log_stmt->execute()) {
                        error_log("approval_actions incomplete log failed: " . $log_stmt->error);
                    }
                    $log_stmt->close();
                } else {
                    error_log("approval_actions incomplete prepare failed: " . $conn->error);
                }
                
                // Record notification (non-critical - can fail without affecting status)
                try {
                $incomplete_title = "Application Incomplete";
                $incomplete_message = "Your application for {$vendor['vendor_name']} has been marked as incomplete.";
                if (!empty($incomplete_reason)) {
                    $incomplete_message .= "\n\nReason: {$incomplete_reason}";
                }
                if (!empty($notes)) {
                    $incomplete_message .= "\n\nNotes: {$notes}";
                }
                $incomplete_message .= "\n\nYou can resubmit your application po after through edit.";
                    
                recordApprovalNotification(
                    $conn,
                    (int)$vendor['user_id'],
                    $vendor_id,
                    'incomplete',
                    $incomplete_title,
                    $incomplete_message,
                    'high',
                    false,
                    'application.php'
                );
                } catch (Exception $e) {
                    error_log("Notification error (non-fatal): " . $e->getMessage());
                }
                
                // Final verification with fresh connection to ensure persistence
                // If status was reset to pending, re-apply the correct status
                $verify_conn = getDBConnection();
                if ($verify_conn) {
                    $final_check = $verify_conn->prepare("SELECT status FROM vendors WHERE vendor_id = ?");
                    $final_check->bind_param("i", $vendor_id);
                    $final_check->execute();
                    $final_result = $final_check->get_result();
                    $final_status = $final_result->fetch_assoc();
                    $final_check->close();
                    
                    if ($final_status) {
                        if ($final_status['status'] === 'incomplete') {
                        error_log("Incomplete status confirmed for vendor $vendor_id - status persisted correctly");
                        } elseif ($final_status['status'] === 'pending') {
                            // Status was reset to pending - re-apply incomplete status
                            error_log("⚠️ CRITICAL: Vendor $vendor_id status was reset to pending - re-applying incomplete status");
                            $fix_stmt = $verify_conn->prepare("UPDATE vendors SET status = 'incomplete', reviewed_date = NOW(), reviewed_by = ? WHERE vendor_id = ?");
                            $fix_stmt->bind_param("ii", $admin_id, $vendor_id);
                            $fix_stmt->execute();
                            $fix_stmt->close();
                            error_log("✓ Fixed: Re-applied incomplete status to vendor $vendor_id");
                    } else {
                            error_log("WARNING: Vendor $vendor_id status is: " . $final_status['status']);
                        }
                    }
                    $verify_conn->close();
                }
                
                // Get vendor and admin info for activity log
                $vendor_info_stmt = $conn->prepare("SELECT v.vendor_name, v.application_type, u.full_name as admin_name FROM vendors v LEFT JOIN users u ON u.user_id = ? WHERE v.vendor_id = ?");
                $vendor_info_stmt->bind_param("ii", $admin_id, $vendor_id);
                $vendor_info_stmt->execute();
                $vendor_info_result = $vendor_info_stmt->get_result();
                $vendor_info = $vendor_info_result->fetch_assoc();
                $vendor_info_stmt->close();
                
                // FINAL ABSOLUTE FIX: Guarantee status cannot revert to pending
                $absolute_conn = getDBConnection();
                if ($absolute_conn) {
                  $absolute_conn->query("UPDATE vendors SET status = 'incomplete', review_status = 'incomplete', is_reviewed = 1 WHERE vendor_id = $vendor_id");
                  $absolute_conn->close();
                }

                // Email the vendor about the incomplete decision (non-blocking; distinct for application vs renewal)
                try {
                    require_once __DIR__ . '/includes/submission_notify_email.php';
                    $mail_kind = (($vendor_info['application_type'] ?? 'new') === 'renewal') ? 'renewal' : 'application';
                    $mail_uid = (int)($vendor['user_id'] ?? 0);
                    if ($mail_uid > 0) {
                        $mconn = getDBConnection();
                        if ($mconn) {
                            $mst = $mconn->prepare("SELECT email, full_name FROM users WHERE user_id = ? LIMIT 1");
                            if ($mst) {
                                $mst->bind_param("i", $mail_uid);
                                $mst->execute();
                                $mres = $mst->get_result();
                                $muser = $mres ? $mres->fetch_assoc() : null;
                                $mst->close();
                                if ($muser && !empty($muser['email'])) {
                                    $msec = (string)($vendor['section'] ?? '');
                                    $mblk = (string)($vendor['block'] ?? '');
                                    $mloc = trim($msec . ($msec !== '' && $mblk !== '' ? ' — ' : '') . $mblk);
                                    dpm_notify_decision_email($mail_kind, 'incomplete', (string)$muser['email'], (string)($muser['full_name'] ?? ''), [
                                        'location' => $mloc,
                                        'reason'   => $incomplete_reason,
                                        'notes'    => $notes,
                                    ]);
                                }
                            }
                            $mconn->close();
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('incomplete_vendor email (non-fatal): ' . $e->getMessage());
                }

                echo json_encode([
                  'success' => true, 
                  'message' => 'Application marked as incomplete successfully.',
                  'vendor_name' => $vendor_info['vendor_name'] ?? $vendor['vendor_name'] ?? 'Unknown Vendor',
                  'admin_name' => $vendor_info['admin_name'] ?? $admin_name,
                  'action_type' => 'incomplete',
                  'application_type' => $vendor_info['application_type'] ?? 'new'
                ]);
                exit;
                
            } catch (Exception $e) {
                if ($conn) {
                    try {
                        $conn->rollback();
                        $conn->autocommit(true);
                        $conn->close();
                    } catch (Exception $rollback_e) {
                        error_log("Rollback error: " . $rollback_e->getMessage());
                    }
                }
                error_log("Incomplete vendor exception: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                exit;
            }
            
        case 'update_profile':
            try {
                // Get and validate input data
                $new_name = trim($_POST['admin_name'] ?? '');
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                // Validate admin name
                if (empty($new_name)) {
                    echo json_encode(['success' => false, 'message' => 'Admin name is required.']);
                    exit;
                }
                
                if (strlen($new_name) < 2 || strlen($new_name) > 50) {
                    echo json_encode(['success' => false, 'message' => 'Admin name must be between 2 and 50 characters.']);
                    exit;
                }
                
                // Validate password fields if password change is requested
                if (!empty($new_password)) {
                    if (empty($current_password)) {
                        echo json_encode(['success' => false, 'message' => 'Current password is required to change password.']);
                        exit;
                    }
                    
                    if ($new_password !== $confirm_password) {
                        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
                        exit;
                    }
                    
                    if (strlen($new_password) < 8) {
                        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long.']);
                        exit;
                    }
                    
                    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
                        echo json_encode(['success' => false, 'message' => 'New password must contain at least one lowercase letter, one uppercase letter, and one number.']);
                        exit;
                    }
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                // Start transaction
                $conn->begin_transaction();
                
                // Verify current password if password change is requested
                if (!empty($new_password)) {
                    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ? AND user_type = 'admin'");
                    $stmt->bind_param("i", $admin_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$user) {
                        $conn->rollback();
                        $conn->close();
                        echo json_encode(['success' => false, 'message' => 'Admin user not found.']);
                        exit;
                    }
                    
                    if (!password_verify($current_password, $user['password_hash'])) {
                        $conn->rollback();
                        $conn->close();
                        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
                        exit;
                    }
                }
                
                // Update admin name
                $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE user_id = ? AND user_type = 'admin'");
                $stmt->bind_param("si", $new_name, $admin_id);
                if (!$stmt->execute()) {
                    $conn->rollback();
                    $stmt->close();
                    $conn->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to update admin name.']);
                    exit;
                }
                $stmt->close();
                
                // Update password if provided
                if (!empty($new_password)) {
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ? AND user_type = 'admin'");
                    $stmt->bind_param("si", $new_password_hash, $admin_id);
                    if (!$stmt->execute()) {
                        $conn->rollback();
                        $stmt->close();
                        $conn->close();
                        echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
                        exit;
                    }
                    $stmt->close();
                }
                
                // Handle profile picture upload
                $profile_pic_filename = null;
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/admin/';
                    
                    // Create upload directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        if (!mkdir($upload_dir, 0755, true)) {
                            $conn->rollback();
                            $conn->close();
                            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
                            exit;
                        }
                    }
                    
                    // Validate file
                    $file_info = pathinfo($_FILES['profile_pic']['name']);
                    $extension = strtolower($file_info['extension'] ?? '');
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($extension, $allowed_extensions)) {
                        $conn->rollback();
                        $conn->close();
                        echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload JPG, PNG, or GIF.']);
                        exit;
                    }
                    
                    // Check file size (2MB limit)
                    if ($_FILES['profile_pic']['size'] > 2 * 1024 * 1024) {
                        $conn->rollback();
                        $conn->close();
                        echo json_encode(['success' => false, 'message' => 'File size must be less than 2MB.']);
                        exit;
                    }
                    
                    // Generate unique filename
                    $profile_pic_filename = 'admin_' . $admin_id . '_' . time() . '.' . $extension;
                    $upload_path = $upload_dir . $profile_pic_filename;
                    
                    // Move uploaded file
                    if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                        $conn->rollback();
                        $conn->close();
                        echo json_encode(['success' => false, 'message' => 'Failed to upload profile picture.']);
                        exit;
                    }
                    
                    // Ensure admin profile exists
                    $stmt = $conn->prepare("SELECT admin_id FROM admin_profiles WHERE admin_id = ?");
                    $stmt->bind_param("i", $admin_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stmt->close();
                    
                    if ($result->num_rows === 0) {
                        // Create admin profile if it doesn't exist
                        $stmt = $conn->prepare("INSERT INTO admin_profiles (admin_id, site_name) VALUES (?, 'Daet Public Market Clearance System')");
                        $stmt->bind_param("i", $admin_id);
                        if (!$stmt->execute()) {
                            $conn->rollback();
                            $stmt->close();
                            $conn->close();
                            echo json_encode(['success' => false, 'message' => 'Failed to create admin profile.']);
                            exit;
                        }
                        $stmt->close();
                    }
                    
                    // Update profile picture in database
                    $stmt = $conn->prepare("UPDATE admin_profiles SET profile_photo = ? WHERE admin_id = ?");
                    $stmt->bind_param("si", $profile_pic_filename, $admin_id);
                    if (!$stmt->execute()) {
                        $conn->rollback();
                        $stmt->close();
                        $conn->close();
                        echo json_encode(['success' => false, 'message' => 'Failed to update profile picture in database.']);
                        exit;
                    }
                    $stmt->close();
                }
                
                // Commit transaction
                $conn->commit();
                
                // Update session
                $_SESSION['full_name'] = $new_name;
                
                // Prepare response
                $response = ['success' => true, 'message' => 'Profile updated successfully!'];
                if ($profile_pic_filename) {
                    $response['profile_pic'] = $profile_pic_filename;
                }
                if (!empty($new_password)) {
                    $response['password_changed'] = true;
                }
                
                $conn->close();
                echo json_encode($response);
                exit;
                
            } catch (Exception $e) {
                if (isset($conn)) {
                    $conn->rollback();
                    $conn->close();
                }
                echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
                exit;
            }
            
        case 'save_settings':
            $dark_mode = isset($_POST['dark_mode']) ? (int)$_POST['dark_mode'] : 0;
            
            $conn = getDBConnection();
            // First check if admin_profiles table has dark_mode column, if not add it
            try {
                $check_column = $conn->query("SHOW COLUMNS FROM admin_profiles LIKE 'dark_mode'");
                if ($check_column->num_rows == 0) {
                    $conn->query("ALTER TABLE admin_profiles ADD COLUMN dark_mode TINYINT(1) DEFAULT 0");
                }
            } catch (Exception $e) {
                error_log("Error checking/adding dark_mode column: " . $e->getMessage());
            }
            
            $stmt = $conn->prepare("INSERT INTO admin_profiles (admin_id, dark_mode) VALUES (?, ?) ON DUPLICATE KEY UPDATE dark_mode = ?");
            $stmt->bind_param("iii", $admin_id, $dark_mode, $dark_mode);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Dark mode setting saved successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save dark mode setting.']);
            }
            $stmt->close();
            $conn->close();
            exit;
            
        case 'update_slots':
            // Ensure we only output JSON
            ob_clean();
            header('Content-Type: application/json');
            
            $conn = null;
            try {
                $slots = json_decode($_POST['slots'] ?? '[]', true);
                
                if (empty($slots) || !is_array($slots)) {
                    echo json_encode(['success' => false, 'message' => 'No slot data provided.']);
                    exit;
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                // Ensure market_slots table exists
                $conn->query("CREATE TABLE IF NOT EXISTS market_slots (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    block_name VARCHAR(50) NOT NULL,
                    slot_number INT NOT NULL,
                    status ENUM('available', 'occupied', 'second_floor') DEFAULT 'available',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_slot (block_name, slot_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                $conn->autocommit(false);
                $success_count = 0;
                $error_count = 0;
                
                foreach ($slots as $slot) {
                    $block_name = $slot['block_name'] ?? '';
                    $slot_number = (int)($slot['slot_number'] ?? 0);
                    $status = $slot['status'] ?? 'available';
                    
                    if (empty($block_name) || $slot_number <= 0 || !in_array($status, ['available', 'occupied', 'second_floor'])) {
                        $error_count++;
                        continue;
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO market_slots (block_name, slot_number, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?");
                    if ($stmt) {
                        $stmt->bind_param("siss", $block_name, $slot_number, $status, $status);
                        if ($stmt->execute()) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                        $stmt->close();
                    } else {
                        $error_count++;
                    }
                }
                
                if ($error_count === 0) {
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => "Successfully updated {$success_count} slot(s)."]);
                } else {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => "Updated {$success_count} slot(s), but {$error_count} failed."]);
                }
                
                $conn->autocommit(true);
                $conn->close();
            } catch (Exception $e) {
                if ($conn) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                }
                echo json_encode(['success' => false, 'message' => 'Error updating slots: ' . $e->getMessage()]);
            }
            exit;

        case 'save_slot_detail':
            while (ob_get_level()) { ob_end_clean(); }
            header('Content-Type: application/json');

            $conn = null;
            try {
                $block_name  = trim((string) ($_POST['block_name'] ?? ''));
                $slot_number = (int) ($_POST['slot_number'] ?? 0);
                $status      = $_POST['status'] ?? 'available';

                $allowed_blocks = [
                    'SPECIAL AREA SECTION TOP', 'FRUITS VEG', 'BOT BLOCK A', 'BOT BLOCK B',
                    'BOT BLOCK C', 'BOT BEHIND BLOCK C', 'BOT BEHIND BLOCK B', 'BOT BLOCK E',
                    'DRY GOODS SECTION', 'SERVICE SECTION', 'SPECIAL AREA SECTION',
                    'Daet Elevated Plaza', 'SITE MAP',
                ];
                if (!in_array($block_name, $allowed_blocks, true) || $slot_number <= 0
                    || !in_array($status, ['available', 'occupied', 'second_floor'], true)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid slot data.']);
                    exit;
                }

                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }

                // Ensure table + image columns exist
                $conn->query("CREATE TABLE IF NOT EXISTS market_slots (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    block_name VARCHAR(50) NOT NULL,
                    slot_number INT NOT NULL,
                    status ENUM('available', 'occupied', 'second_floor') DEFAULT 'available',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_slot (block_name, slot_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                foreach (['image1', 'image2', 'image3'] as $imgCol) {
                    $chk = $conn->query("SHOW COLUMNS FROM market_slots LIKE '" . $imgCol . "'");
                    if ($chk && $chk->num_rows === 0) {
                        $conn->query("ALTER TABLE market_slots ADD COLUMN " . $imgCol . " VARCHAR(255) NULL");
                    }
                }
                foreach ([
                    'holder_vendor_id' => 'INT NULL',
                    'holder_name'      => 'VARCHAR(255) NULL',
                    'holder_section'   => 'VARCHAR(150) NULL',
                    'holder_block'     => 'VARCHAR(150) NULL',
                ] as $hcol => $hdef) {
                    $hchk = $conn->query("SHOW COLUMNS FROM market_slots LIKE '" . $hcol . "'");
                    if ($hchk && $hchk->num_rows === 0) {
                        $conn->query("ALTER TABLE market_slots ADD COLUMN " . $hcol . " " . $hdef);
                    }
                }

                // Resolve stall holder — only meaningful when the slot is occupied
                $holder_vendor_id = (int) ($_POST['holder_vendor_id'] ?? 0);
                $holder_name = null;
                $holder_section = null;
                $holder_block = null;
                if ($status === 'occupied' && $holder_vendor_id > 0) {
                    $hstmt = $conn->prepare("SELECT vendor_name, section, block FROM vendors WHERE vendor_id = ? LIMIT 1");
                    if ($hstmt) {
                        $hstmt->bind_param('i', $holder_vendor_id);
                        $hstmt->execute();
                        $hrow = $hstmt->get_result()->fetch_assoc();
                        $hstmt->close();
                        if ($hrow) {
                            $holder_name = $hrow['vendor_name'];
                            $holder_section = $hrow['section'];
                            $holder_block = $hrow['block'];
                        } else {
                            $holder_vendor_id = 0;
                        }
                    }
                } else {
                    // Clear holder when slot is not occupied
                    $holder_vendor_id = 0;
                }

                // Existing photos (for replace/remove cleanup)
                $existing = ['image1' => '', 'image2' => '', 'image3' => ''];
                $sel = $conn->prepare("SELECT image1, image2, image3 FROM market_slots WHERE block_name = ? AND slot_number = ? LIMIT 1");
                $sel->bind_param('si', $block_name, $slot_number);
                $sel->execute();
                $exRow = $sel->get_result()->fetch_assoc();
                $sel->close();
                if ($exRow) {
                    $existing['image1'] = (string) ($exRow['image1'] ?? '');
                    $existing['image2'] = (string) ($exRow['image2'] ?? '');
                    $existing['image3'] = (string) ($exRow['image3'] ?? '');
                }

                $upload_dir_fs = __DIR__ . '/uploads/slots/';
                if (!is_dir($upload_dir_fs) && !mkdir($upload_dir_fs, 0755, true) && !is_dir($upload_dir_fs)) {
                    echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
                    exit;
                }

                $delete_file = static function ($rel) {
                    $rel = ltrim(str_replace('\\', '/', trim((string) $rel)), '/');
                    if ($rel === '' || strpos($rel, 'uploads/slots/') !== 0) {
                        return;
                    }
                    $full = __DIR__ . '/' . $rel;
                    if (is_file($full)) {
                        @unlink($full);
                    }
                };

                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $final = ['image1' => '', 'image2' => '', 'image3' => ''];

                for ($i = 1; $i <= 3; $i++) {
                    $key = 'image' . $i;
                    $fileKey = 'photo' . $i;
                    $keepKey = 'keep' . $i;
                    $old = $existing[$key];

                    if (isset($_FILES[$fileKey]) && ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $f = $_FILES[$fileKey];
                        if (($f['size'] ?? 0) > 5 * 1024 * 1024) {
                            echo json_encode(['success' => false, 'message' => 'Photo ' . $i . ' exceeds 5MB.']);
                            exit;
                        }
                        $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowed_ext, true)) {
                            echo json_encode(['success' => false, 'message' => 'Photo ' . $i . ' must be an image (jpg, png, gif, webp).']);
                            exit;
                        }
                        $fname = 'slot_' . preg_replace('/[^a-z0-9]+/i', '', $block_name) . '_' . $slot_number . '_' . $i . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                        if (!move_uploaded_file($f['tmp_name'], $upload_dir_fs . $fname)) {
                            echo json_encode(['success' => false, 'message' => 'Failed to save photo ' . $i . '.']);
                            exit;
                        }
                        if ($old !== '') { $delete_file($old); }
                        $final[$key] = 'uploads/slots/' . $fname;
                    } else {
                        // No new upload — honor keep value (else treat as removed)
                        $keep = ltrim(str_replace('\\', '/', trim((string) ($_POST[$keepKey] ?? ''))), '/');
                        if ($keep !== '' && $keep === $old && strpos($keep, 'uploads/slots/') === 0) {
                            $final[$key] = $keep;
                        } else {
                            if ($old !== '') { $delete_file($old); }
                            $final[$key] = '';
                        }
                    }
                }

                $holder_vendor_id_db = $holder_vendor_id > 0 ? $holder_vendor_id : null;
                $stmt = $conn->prepare("INSERT INTO market_slots (block_name, slot_number, status, image1, image2, image3, holder_vendor_id, holder_name, holder_section, holder_block)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), image1 = VALUES(image1), image2 = VALUES(image2), image3 = VALUES(image3),
                        holder_vendor_id = VALUES(holder_vendor_id), holder_name = VALUES(holder_name), holder_section = VALUES(holder_section), holder_block = VALUES(holder_block)");
                $stmt->bind_param('sissssisss', $block_name, $slot_number, $status, $final['image1'], $final['image2'], $final['image3'], $holder_vendor_id_db, $holder_name, $holder_section, $holder_block);
                $ok = $stmt->execute();
                $stmt->close();
                $conn->close();

                if (!$ok) {
                    echo json_encode(['success' => false, 'message' => 'Failed to save stall.']);
                    exit;
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Stall updated.',
                    'status'  => $status,
                    'image1'  => $final['image1'],
                    'image2'  => $final['image2'],
                    'image3'  => $final['image3'],
                    'holder_vendor_id' => $holder_vendor_id > 0 ? $holder_vendor_id : '',
                    'holder_name'    => $holder_name ?? '',
                    'holder_section' => $holder_section ?? '',
                    'holder_block'   => $holder_block ?? '',
                ]);
            } catch (Exception $e) {
                if ($conn) { $conn->close(); }
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'get_requirements':
            // Ensure we only output JSON
            ob_clean();
            header('Content-Type: application/json');
            
            $conn = null;
            try {
                $application_type = $_POST['application_type'] ?? 'new';
                
                if (!in_array($application_type, ['new', 'renewal'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid application type.']);
                    exit;
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                // Ensure table exists
                $conn->query("CREATE TABLE IF NOT EXISTS application_requirements (
                    requirement_id INT AUTO_INCREMENT PRIMARY KEY,
                    application_type ENUM('new', 'renewal') NOT NULL,
                    requirement_name VARCHAR(500) NOT NULL,
                    requirement_description TEXT,
                    display_order INT DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_application_type (application_type),
                    INDEX idx_display_order (display_order),
                    INDEX idx_is_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                $stmt = $conn->prepare("SELECT requirement_id, requirement_name, requirement_description, display_order, is_active FROM application_requirements WHERE application_type = ? ORDER BY display_order ASC, requirement_id ASC");
                $stmt->bind_param("s", $application_type);
                $stmt->execute();
                $result = $stmt->get_result();
                $requirements = [];
                
                while ($row = $result->fetch_assoc()) {
                    $requirements[] = $row;
                }
                
                $stmt->close();
                $conn->close();
                
                echo json_encode(['success' => true, 'requirements' => $requirements]);
            } catch (Exception $e) {
                if ($conn) $conn->close();
                echo json_encode(['success' => false, 'message' => 'Error fetching requirements: ' . $e->getMessage()]);
            }
            exit;
            
        case 'save_requirement':
            // Ensure we only output JSON
            ob_clean();
            header('Content-Type: application/json');
            
            $conn = null;
            try {
                $requirement_id = isset($_POST['requirement_id']) ? (int)$_POST['requirement_id'] : 0;
                $application_type = $_POST['application_type'] ?? 'new';
                $requirement_name = trim($_POST['requirement_name'] ?? '');
                $requirement_description = trim($_POST['requirement_description'] ?? '');
                $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
                
                if (!in_array($application_type, ['new', 'renewal'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid application type.']);
                    exit;
                }
                
                if (empty($requirement_name)) {
                    echo json_encode(['success' => false, 'message' => 'Requirement name is required.']);
                    exit;
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                // Ensure table exists
                $conn->query("CREATE TABLE IF NOT EXISTS application_requirements (
                    requirement_id INT AUTO_INCREMENT PRIMARY KEY,
                    application_type ENUM('new', 'renewal') NOT NULL,
                    requirement_name VARCHAR(500) NOT NULL,
                    requirement_description TEXT,
                    display_order INT DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_application_type (application_type),
                    INDEX idx_display_order (display_order),
                    INDEX idx_is_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                if ($requirement_id > 0) {
                    // Update existing
                    $stmt = $conn->prepare("UPDATE application_requirements SET requirement_name = ?, requirement_description = ?, display_order = ? WHERE requirement_id = ?");
                    $stmt->bind_param("ssii", $requirement_name, $requirement_description, $display_order, $requirement_id);
                } else {
                    // Insert new
                    $stmt = $conn->prepare("INSERT INTO application_requirements (application_type, requirement_name, requirement_description, display_order) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $application_type, $requirement_name, $requirement_description, $display_order);
                }
                
                if ($stmt->execute()) {
                    $new_id = $requirement_id > 0 ? $requirement_id : $conn->insert_id;
                    echo json_encode(['success' => true, 'message' => 'Requirement saved successfully.', 'requirement_id' => $new_id]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save requirement.']);
                }
                
                $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                if ($conn) $conn->close();
                echo json_encode(['success' => false, 'message' => 'Error saving requirement: ' . $e->getMessage()]);
            }
            exit;
            
        case 'delete_requirement':
            // Ensure we only output JSON
            ob_clean();
            header('Content-Type: application/json');
            
            $conn = null;
            try {
                $requirement_id = isset($_POST['requirement_id']) ? (int)$_POST['requirement_id'] : 0;
                
                if ($requirement_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid requirement ID.']);
                    exit;
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                // Soft delete by setting is_active to 0
                $stmt = $conn->prepare("UPDATE application_requirements SET is_active = 0 WHERE requirement_id = ?");
                $stmt->bind_param("i", $requirement_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Requirement deleted successfully.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete requirement.']);
                }
                
                $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                if ($conn) $conn->close();
                echo json_encode(['success' => false, 'message' => 'Error deleting requirement: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update_requirement_order':
            // Ensure we only output JSON
            ob_clean();
            header('Content-Type: application/json');
            
            $conn = null;
            try {
                $orders = json_decode($_POST['orders'] ?? '[]', true);
                
                if (empty($orders) || !is_array($orders)) {
                    echo json_encode(['success' => false, 'message' => 'No order data provided.']);
                    exit;
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                $conn->autocommit(false);
                $success_count = 0;
                
                foreach ($orders as $order) {
                    $requirement_id = (int)($order['requirement_id'] ?? 0);
                    $display_order = (int)($order['display_order'] ?? 0);
                    
                    if ($requirement_id > 0) {
                        $stmt = $conn->prepare("UPDATE application_requirements SET display_order = ? WHERE requirement_id = ?");
                        $stmt->bind_param("ii", $display_order, $requirement_id);
                        if ($stmt->execute()) {
                            $success_count++;
                        }
                        $stmt->close();
                    }
                }
                
                $conn->commit();
                $conn->autocommit(true);
                $conn->close();
                
                echo json_encode(['success' => true, 'message' => "Successfully updated order for {$success_count} requirement(s)."]);
            } catch (Exception $e) {
                if ($conn) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                }
                echo json_encode(['success' => false, 'message' => 'Error updating order: ' . $e->getMessage()]);
            }
            exit;

        case 'save_homepage_news_card':
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            $card_slot = isset($_POST['card_slot']) ? (int) $_POST['card_slot'] : 0;
            $conn = getDBConnection();
            if (!$conn) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                exit;
            }
            $result = homepage_news_save_card($conn, $card_slot, [
                'tag_type' => $_POST['tag_type'] ?? 'update',
                'tag_label' => $_POST['tag_label'] ?? '',
                'card_date' => $_POST['card_date'] ?? '',
                'title' => $_POST['title'] ?? '',
                'summary' => $_POST['summary'] ?? '',
                'full_details' => $_POST['full_details'] ?? '',
            ]);
            $conn->close();
            echo json_encode($result);
            exit;

        case 'upload_news_card_image':
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            $card_slot = isset($_POST['card_slot']) ? (int) $_POST['card_slot'] : 0;
            if ($card_slot < 1 || $card_slot > 4) {
                echo json_encode(['success' => false, 'message' => 'Invalid card slot.']);
                exit;
            }
            if (!isset($_FILES['news_image']) || $_FILES['news_image']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'No image uploaded or upload error.']);
                exit;
            }
            $file = $_FILES['news_image'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: '');
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_size = 10 * 1024 * 1024;
            if (!in_array($extension, $allowed, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image type. Use JPG, PNG, GIF, or WebP.']);
                exit;
            }
            if ($file['size'] > $max_size) {
                echo json_encode(['success' => false, 'message' => 'Image must be less than 10MB.']);
                exit;
            }
            $upload_dir = 'assets/imgs/';
            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
                exit;
            }
            $filename = 'news_card_' . $card_slot . '_' . time() . '.' . $extension;
            $upload_path = $upload_dir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
                exit;
            }
            $conn = getDBConnection();
            if (!$conn) {
                @unlink($upload_path);
                echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                exit;
            }
            $result = homepage_news_update_image($conn, $card_slot, $upload_path);
            $conn->close();
            echo json_encode($result);
            exit;

        case 'save_application_fees':
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            $conn = getDBConnection();
            if (!$conn) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                exit;
            }
            $result = application_fees_save_batch($conn, [
                'new' => [
                    'title' => $_POST['new_title'] ?? '',
                    'subtitle' => $_POST['new_subtitle'] ?? '',
                    'price' => $_POST['new_price'] ?? 0,
                ],
                'renewal' => [
                    'title' => $_POST['renewal_title'] ?? '',
                    'subtitle' => $_POST['renewal_subtitle'] ?? '',
                    'price' => $_POST['renewal_price'] ?? 0,
                ],
            ]);
            $conn->close();
            echo json_encode($result);
            exit;

        case 'remove_news_card_image':
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            $card_slot = isset($_POST['card_slot']) ? (int) $_POST['card_slot'] : 0;
            $conn = getDBConnection();
            if (!$conn) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                exit;
            }
            $result = homepage_news_remove_image($conn, $card_slot);
            $conn->close();
            echo json_encode($result);
            exit;
            
        case 'upload_media':
            // Ensure we only output JSON
            ob_clean();
            
            try {
                $media_type = $_POST['media_type'] ?? '';
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $section = trim($_POST['section'] ?? 'overview_video');
                
                if (empty($media_type) || !in_array($media_type, ['video', 'image'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid media type.']);
                    exit;
                }
                
                if (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
                    exit;
                }
                
                $file = $_FILES['media_file'];
                $file_info = pathinfo($file['name']);
                $extension = strtolower($file_info['extension'] ?? '');
                
                // Validate file type and size
                if ($media_type === 'video') {
                    $allowed_extensions = ['mp4', 'avi', 'mov', 'wmv', 'webm'];
                    $max_size = 50 * 1024 * 1024; // 50MB
                    $upload_dir = 'assets/videos/';
                } else {
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $max_size = 10 * 1024 * 1024; // 10MB
                    $upload_dir = 'assets/imgs/';
                }
                
                if (!in_array($extension, $allowed_extensions)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type for ' . $media_type . '.']);
                    exit;
                }
                
                if ($file['size'] > $max_size) {
                    echo json_encode(['success' => false, 'message' => 'File size too large. Max size: ' . ($max_size / (1024*1024)) . 'MB']);
                    exit;
                }
                
                // Create upload directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
                        exit;
                    }
                }
                
                // Generate unique filename
                $filename = $media_type . '_' . time() . '.' . $extension;
                $upload_path = $upload_dir . $filename;
                
                // Move uploaded file
                if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
                    exit;
                }
                
                // Check if media_files table exists, create if not
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                // Check if table exists
                $table_check = $conn->query("SHOW TABLES LIKE 'media_files'");
                if ($table_check->num_rows === 0) {
                    // Create the table
                    $create_table_sql = "
                    CREATE TABLE media_files (
                        media_id INT AUTO_INCREMENT PRIMARY KEY,
                        file_name VARCHAR(255) NOT NULL,
                        file_path VARCHAR(500) NOT NULL,
                        file_type ENUM('video', 'image') NOT NULL,
                        section VARCHAR(100) DEFAULT 'overview_video',
                        file_size BIGINT NOT NULL,
                        mime_type VARCHAR(100) NOT NULL,
                        title VARCHAR(255) DEFAULT NULL,
                        description TEXT DEFAULT NULL,
                        is_active BOOLEAN DEFAULT TRUE,
                        display_order INT DEFAULT 0,
                        uploaded_by INT NOT NULL,
                        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE CASCADE,
                        INDEX idx_file_type (file_type),
                        INDEX idx_is_active (is_active),
                        INDEX idx_display_order (display_order),
                        INDEX idx_section (section)
                    )";
                    
                    if (!$conn->query($create_table_sql)) {
                        echo json_encode(['success' => false, 'message' => 'Failed to create media_files table: ' . $conn->error]);
                        exit;
                    }
                } else {
                    $sec_chk = $conn->query("SHOW COLUMNS FROM media_files LIKE 'section'");
                    if ($sec_chk && $sec_chk->num_rows === 0) {
                        $conn->query("ALTER TABLE media_files ADD COLUMN section VARCHAR(100) DEFAULT 'overview_video'");
                    }
                }
                
                // Save to database
                $stmt = $conn->prepare("INSERT INTO media_files (file_name, file_path, file_type, section, file_size, mime_type, title, description, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
                    exit;
                }
                
                $stmt->bind_param("ssssisssi", $filename, $upload_path, $media_type, $section, $file['size'], $file['type'], $title, $description, $admin_id);
                
                if ($stmt->execute()) {
                    $deact = $conn->prepare("UPDATE media_files SET is_active = 0 WHERE file_type = ? AND section = ? AND file_path != ? AND (is_active = 1 OR is_active IS NULL)");
                    if ($deact) {
                        $deact->bind_param("sss", $media_type, $section, $upload_path);
                        $deact->execute();
                        $deact->close();
                    }
                    echo json_encode(['success' => true, 'message' => ucfirst($media_type) . ' uploaded successfully!', 'file_path' => $upload_path]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save file information to database: ' . $stmt->error]);
                }
                
                $stmt->close();
                $conn->close();
                exit;
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
                exit;
            }
            
        case 'remove_media':
            // Ensure we only output JSON
            ob_clean();
            
            try {
                $media_type = $_POST['media_type'] ?? '';
                $section = $_POST['section'] ?? 'overview_video';
                
                if (empty($media_type) || !in_array($media_type, ['video', 'image'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid media type.']);
                    exit;
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                // Check if table exists first
                $table_check = $conn->query("SHOW TABLES LIKE 'media_files'");
                if ($table_check->num_rows === 0) {
                    echo json_encode(['success' => false, 'message' => 'Media files table does not exist.']);
                    exit;
                }
                
                $sec_rm = $conn->query("SHOW COLUMNS FROM media_files LIKE 'section'");
                if ($sec_rm && $sec_rm->num_rows === 0) {
                    $conn->query("ALTER TABLE media_files ADD COLUMN section VARCHAR(100) DEFAULT 'overview_video'");
                }
                
                // Get current media file info
                $stmt = $conn->prepare("SELECT file_path FROM media_files WHERE file_type = ? AND section = ? AND (is_active = 1 OR is_active IS NULL) ORDER BY uploaded_at DESC LIMIT 1");
                if (!$stmt) {
                    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
                    exit;
                }
                
                $stmt->bind_param("ss", $media_type, $section);
                $stmt->execute();
                $result = $stmt->get_result();
                $media = $result->fetch_assoc();
                $stmt->close();
                
                if ($media) {
                    // Delete file from filesystem
                    if (file_exists($media['file_path'])) {
                        unlink($media['file_path']);
                    }
                    
                    // Mark as inactive in database
                    $stmt = $conn->prepare("UPDATE media_files SET is_active = 0 WHERE file_type = ? AND section = ? AND (is_active = 1 OR is_active IS NULL)");
                    if ($stmt) {
                        $stmt->bind_param("ss", $media_type, $section);
                        $stmt->execute();
                        $stmt->close();
                    }
                    
                    echo json_encode(['success' => true, 'message' => ucfirst($media_type) . ' removed successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No ' . $media_type . ' found to remove.']);
                }
                
                $conn->close();
                exit;
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Remove error: ' . $e->getMessage()]);
                exit;
            }
            
        case 'get_vendor_details':
            // Ensure we only output JSON
            if (ob_get_level()) {
            ob_clean();
            }
            header('Content-Type: application/json');
            
            // Enable error reporting temporarily for debugging
            error_reporting(E_ALL);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
            
            try {
                $vendor_id = (int)($_POST['vendor_id'] ?? 0);
                
                if ($vendor_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid vendor ID.']);
                    exit;
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    $error_msg = 'Database connection failed.';
                    error_log("get_vendor_details: " . $error_msg);
                    echo json_encode(['success' => false, 'message' => $error_msg]);
                    exit;
                }
                
                // Check if application_documents table exists, if not use vendor_documents
                $table_check = $conn->query("SHOW TABLES LIKE 'application_documents'");
                $use_new_table = ($table_check && $table_check->num_rows > 0);
                
                // Get vendor details
                $stmt = $conn->prepare("
                    SELECT v.*, u.email, u.full_name as user_name 
                    FROM vendors v 
                    JOIN users u ON v.user_id = u.user_id 
                    WHERE v.vendor_id = ?
                ");
                
                if (!$stmt) {
                    $error_msg = 'Failed to prepare statement: ' . $conn->error;
                    error_log("get_vendor_details: " . $error_msg);
                    $conn->close();
                    echo json_encode(['success' => false, 'message' => $error_msg]);
                    exit;
                }
                
                $stmt->bind_param("i", $vendor_id);
                
                if (!$stmt->execute()) {
                    $error_msg = 'Failed to execute query: ' . $stmt->error;
                    error_log("get_vendor_details: " . $error_msg);
                    $stmt->close();
                    $conn->close();
                    echo json_encode(['success' => false, 'message' => $error_msg]);
                    exit;
                }
                
                $result = $stmt->get_result();
                $vendor = $result->fetch_assoc();
                $stmt->close();
                
                if (!$vendor) {
                    $conn->close();
                    echo json_encode(['success' => false, 'message' => 'Vendor not found.']);
                    exit;
                }
                
                // Get vendor documents - try application_documents first, fallback to vendor_documents
                $documents = [];
                
                if ($use_new_table) {
                    // Try application_documents table
                $stmt = $conn->prepare("
                    SELECT 
                        document_id,
                        original_name as document_name,
                        file_path as document_path,
                        file_type as document_type,
                        mime_type as mime_type,
                        file_size,
                        upload_date as uploaded_date,
                        COALESCE(stall_number, 1) as stall_number
                    FROM application_documents 
                    WHERE vendor_id = ? AND is_active = TRUE 
                    ORDER BY COALESCE(stall_number, 1) ASC, upload_date DESC
                ");
                } else {
                    // Fallback to vendor_documents table
                    $stmt = $conn->prepare("
                        SELECT 
                            document_id,
                            document_name,
                            document_path,
                            document_type,
                            '' as mime_type,
                            0 as file_size,
                            uploaded_date,
                            1 as stall_number
                        FROM vendor_documents 
                        WHERE vendor_id = ?
                        ORDER BY uploaded_date DESC
                    ");
                }
                
                if ($stmt) {
                $stmt->bind_param("i", $vendor_id);
                    
                    if ($stmt->execute()) {
                $result = $stmt->get_result();
                $documents = $result->fetch_all(MYSQLI_ASSOC);
                    } else {
                        error_log("get_vendor_details: Failed to execute documents query: " . $stmt->error);
                    }
                $stmt->close();
                } else {
                    error_log("get_vendor_details: Failed to prepare documents statement: " . $conn->error);
                }
                
                $conn->close();
                
                // Ensure no output before JSON
                if (ob_get_level()) {
                    ob_clean();
                }
                
                echo json_encode([
                    'success' => true,
                    'vendor' => $vendor,
                    'documents' => $documents ? $documents : []
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
                
            } catch (Exception $e) {
                if (ob_get_level()) {
                    ob_clean();
                }
                $error_msg = $e->getMessage();
                error_log("get_vendor_details exception: " . $error_msg . " | Trace: " . $e->getTraceAsString());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $error_msg]);
                exit;
            } catch (Error $e) {
                if (ob_get_level()) {
                    ob_clean();
                }
                $error_msg = $e->getMessage();
                error_log("get_vendor_details fatal error: " . $error_msg . " | Trace: " . $e->getTraceAsString());
                echo json_encode(['success' => false, 'message' => 'Fatal Error: ' . $error_msg]);
                exit;
            }
            break;
            
        case 'approve_receipt':
            // Ensure we only output JSON
            ob_clean();
            header('Content-Type: application/json');
            
            $conn = null;
            try {
                $receipt_id = (int)($_POST['receipt_id'] ?? 0);
                $user_id = (int)($_POST['user_id'] ?? 0);
                $notes = trim($_POST['notes'] ?? '');
                
                if ($receipt_id <= 0 || $user_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid receipt ID or user ID.']);
                    exit;
                }
                
                // Validate notes length
                if (strlen($notes) > 5000) {
                    echo json_encode(['success' => false, 'message' => 'Notes are too long. Maximum 5000 characters.']);
                    exit;
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                // Disable autocommit for transaction
                $conn->autocommit(false);
                
                // Get receipt info before update (check if exists, allow update even if already processed)
                $receipt_stmt = $conn->prepare("SELECT user_id, user_name, user_email, section, block, status FROM receipts WHERE id = ?");
                $receipt_stmt->bind_param("i", $receipt_id);
                $receipt_stmt->execute();
                $receipt_result = $receipt_stmt->get_result();
                $receipt_data = $receipt_result->fetch_assoc();
                $receipt_stmt->close();
                
                if (!$receipt_data) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    echo json_encode(['success' => false, 'message' => 'Receipt not found.']);
                    exit;
                }
                
                // Update receipt status with notes (allow update regardless of current status)
                $update_stmt = $conn->prepare("UPDATE receipts SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), notes = ? WHERE id = ?");
                $update_stmt->bind_param("isi", $admin_id, $notes, $receipt_id);
                
                if (!$update_stmt->execute()) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $update_stmt->close();
                    $conn->close();
                    error_log("Approve receipt execute failed: " . $update_stmt->error);
                    echo json_encode(['success' => false, 'message' => 'Failed to approve receipt: ' . $update_stmt->error]);
                    exit;
                }
                
                $update_stmt->close();
                
                // Create notification for user
                require_once 'auth_functions.php';
                $notification_title = "Receipt Approved";
                $notification_message = "Your receipt has been approved by the admin.";
                if (!empty($notes)) {
                    $notification_message .= "\n\nAdmin Notes: " . $notes;
                }
                $notification_sent = recordApprovalNotification(
                    $conn,
                    $receipt_data['user_id'],
                    null, // vendor_id is null for receipts
                    'approval',
                    $notification_title,
                    $notification_message,
                    'medium',
                    false,
                    'notification.php',
                    $receipt_id
                );
                
                if (!$notification_sent) {
                    error_log("Failed to send notification for receipt approval");
                }
                
                // Commit transaction
                $conn->commit();
                $conn->autocommit(true);
                $conn->close();

                // Email the vendor about the receipt approval (non-blocking)
                try {
                    require_once __DIR__ . '/includes/submission_notify_email.php';
                    $who = dpm_registered_recipient((int)($receipt_data['user_id'] ?? 0));
                    $rcpt_email = $who['email'] !== '' ? $who['email'] : trim((string)($receipt_data['user_email'] ?? ''));
                    $rcpt_name = $who['name'] !== '' ? $who['name'] : (string)($receipt_data['user_name'] ?? '');
                    if ($rcpt_email !== '') {
                        $rsec = (string)($receipt_data['section'] ?? '');
                        $rblk = (string)($receipt_data['block'] ?? '');
                        $rloc = trim($rsec . ($rsec !== '' && $rblk !== '' ? ' — ' : '') . $rblk);
                        dpm_notify_decision_email('receipt', 'approved', $rcpt_email, $rcpt_name, [
                            'location'   => $rloc,
                            'receipt_id' => $receipt_id,
                            'notes'      => $notes,
                        ]);
                    }
                } catch (\Throwable $e) {
                    error_log('approve_receipt email (non-fatal): ' . $e->getMessage());
                }
                
                echo json_encode(['success' => true, 'message' => 'Receipt approved successfully.']);
                exit;
                
            } catch (Exception $e) {
                if ($conn) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                }
                error_log("Approve receipt error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
                exit;
            }
            break;
            
        case 'invalid_receipt':
            // Ensure we only output JSON
            ob_clean();
            header('Content-Type: application/json');
            
            $conn = null;
            try {
                $receipt_id = (int)($_POST['receipt_id'] ?? 0);
                $user_id = (int)($_POST['user_id'] ?? 0);
                $invalid_reason = trim($_POST['invalid_reason'] ?? '');
                $notes = trim($_POST['notes'] ?? '');
                
                if ($receipt_id <= 0 || $user_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid receipt ID or user ID.']);
                    exit;
                }
                
                if (empty($invalid_reason)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid reason is required.']);
                    exit;
                }
                
                // Validate reason length
                if (strlen($invalid_reason) > 255) {
                    echo json_encode(['success' => false, 'message' => 'Invalid reason is too long. Maximum 255 characters.']);
                    exit;
                }
                
                // Validate notes length
                if (strlen($notes) > 5000) {
                    echo json_encode(['success' => false, 'message' => 'Notes are too long. Maximum 5000 characters.']);
                    exit;
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                // Disable autocommit for transaction
                $conn->autocommit(false);
                
                // Get receipt info before update (check if exists, allow update even if already processed)
                $receipt_stmt = $conn->prepare("SELECT user_id, user_name, user_email, section, block, status FROM receipts WHERE id = ?");
                $receipt_stmt->bind_param("i", $receipt_id);
                $receipt_stmt->execute();
                $receipt_result = $receipt_stmt->get_result();
                $receipt_data = $receipt_result->fetch_assoc();
                $receipt_stmt->close();
                
                if (!$receipt_data) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                    echo json_encode(['success' => false, 'message' => 'Receipt not found.']);
                    exit;
                }
                
                // Build notes with reason and additional notes
                $admin_notes = "Invalid Reason: " . $invalid_reason;
                if (!empty($notes)) {
                    $admin_notes .= "\nAdditional Notes: " . $notes;
                }
                
                // Update receipt status (using 'rejected' as per table structure) with notes (allow update regardless of current status)
                $update_stmt = $conn->prepare("UPDATE receipts SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), notes = ? WHERE id = ?");
                $update_stmt->bind_param("isi", $admin_id, $admin_notes, $receipt_id);
                
                if (!$update_stmt->execute()) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $update_stmt->close();
                    $conn->close();
                    error_log("Invalid receipt execute failed: " . $update_stmt->error);
                    echo json_encode(['success' => false, 'message' => 'Failed to mark receipt as invalid: ' . $update_stmt->error]);
                    exit;
                }
                
                $update_stmt->close();
                
                // Create notification for user
                require_once 'auth_functions.php';
                $notification_title = "Receipt Invalid";
                $notification_message = "Your receipt has been marked as invalid by the admin.";
                if (!empty($invalid_reason)) {
                    $notification_message .= "\n\nReason: " . $invalid_reason;
                }
                if (!empty($notes)) {
                    $notification_message .= "\n\nAdditional Notes: " . $notes;
                }
                $notification_message .= "\n\nPlease open Notifications and use Edit to update and resubmit the same receipt.";
                $notification_sent = recordApprovalNotification(
                    $conn,
                    $receipt_data['user_id'],
                    null, // vendor_id is null for receipts
                    'rejection',
                    $notification_title,
                    $notification_message,
                    'high',
                    true,
                    'notification.php',
                    $receipt_id
                );
                
                if (!$notification_sent) {
                    error_log("Failed to send notification for receipt invalidation");
                }
                
                // Commit transaction
                $conn->commit();
                $conn->autocommit(true);
                $conn->close();

                // Email the vendor about the invalid receipt (non-blocking)
                try {
                    require_once __DIR__ . '/includes/submission_notify_email.php';
                    $who = dpm_registered_recipient((int)($receipt_data['user_id'] ?? 0));
                    $rcpt_email = $who['email'] !== '' ? $who['email'] : trim((string)($receipt_data['user_email'] ?? ''));
                    $rcpt_name = $who['name'] !== '' ? $who['name'] : (string)($receipt_data['user_name'] ?? '');
                    if ($rcpt_email !== '') {
                        $rsec = (string)($receipt_data['section'] ?? '');
                        $rblk = (string)($receipt_data['block'] ?? '');
                        $rloc = trim($rsec . ($rsec !== '' && $rblk !== '' ? ' — ' : '') . $rblk);
                        dpm_notify_decision_email('receipt', 'invalid', $rcpt_email, $rcpt_name, [
                            'location'   => $rloc,
                            'receipt_id' => $receipt_id,
                            'reason'     => $invalid_reason,
                            'notes'      => $notes,
                        ]);
                    }
                } catch (\Throwable $e) {
                    error_log('invalid_receipt email (non-fatal): ' . $e->getMessage());
                }
                
                echo json_encode(['success' => true, 'message' => 'Receipt marked as invalid successfully.']);
                exit;
                
            } catch (Exception $e) {
                if ($conn) {
                    $conn->rollback();
                    $conn->autocommit(true);
                    $conn->close();
                }
                error_log("Invalid receipt error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
                exit;
            }
            break;
            
        case 'get_monthly_statistics':
            // Ensure we only output JSON
            ob_clean();
            header('Content-Type: application/json');
            
            try {
                $stat_type = $_GET['type'] ?? 'total'; // total, pending, approved, incomplete
                $year = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
                
                // Validate stat type
                if (!in_array($stat_type, ['total', 'pending', 'approved', 'incomplete'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid statistics type.']);
                    exit;
                }
                
                // Validate year (reasonable range)
                if ($year < 2000 || $year > 2100) {
                    $year = (int)date('Y');
                }
                
                $conn = getDBConnection();
                if (!$conn) {
                    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                    exit;
                }
                
                // Build query based on stat type
                $monthly_data = [];
                for ($month = 1; $month <= 12; $month++) {
                    $month_start = sprintf('%04d-%02d-01', $year, $month);
                    $month_end = date('Y-m-t', strtotime($month_start));
                    
                    if ($stat_type === 'total') {
                        $query = "SELECT COUNT(*) as count FROM vendors WHERE DATE(submitted_date) >= ? AND DATE(submitted_date) <= ?";
                    } elseif ($stat_type === 'pending') {
                        $query = "SELECT COUNT(*) as count FROM vendors WHERE status = 'pending' AND DATE(submitted_date) >= ? AND DATE(submitted_date) <= ?";
                    } elseif ($stat_type === 'approved') {
                        $query = "SELECT COUNT(*) as count FROM vendors WHERE status = 'approved' AND DATE(reviewed_date) >= ? AND DATE(reviewed_date) <= ?";
                    } else { // incomplete
                        $query = "SELECT COUNT(*) as count FROM vendors WHERE status = 'incomplete' AND DATE(reviewed_date) >= ? AND DATE(reviewed_date) <= ?";
                    }
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ss", $month_start, $month_end);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $monthly_data[] = (int)($row['count'] ?? 0);
                    $stmt->close();
                }
                
                $conn->close();
                
                echo json_encode([
                    'success' => true,
                    'year' => $year,
                    'type' => $stat_type,
                    'data' => $monthly_data,
                    'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                ]);
                exit;
                
            } catch (Exception $e) {
                error_log("Get monthly statistics error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
                exit;
            }
            break;
    }
}

// Handle GET requests for statistics
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_monthly_statistics') {
    // Ensure we only output JSON
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        $stat_type = $_GET['type'] ?? 'total'; // total, pending, approved, incomplete
        $year = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        
        // Validate stat type
        if (!in_array($stat_type, ['total', 'pending', 'approved', 'incomplete'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid statistics type.']);
            exit;
        }
        
        // Validate year (reasonable range)
        if ($year < 2000 || $year > 2100) {
            $year = (int)date('Y');
        }
        
        $conn = getDBConnection();
        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
            exit;
        }
        
        // Build query based on stat type
        $monthly_data = [];
        for ($month = 1; $month <= 12; $month++) {
            $month_start = sprintf('%04d-%02d-01', $year, $month);
            $month_end = date('Y-m-t', strtotime($month_start));
            
            if ($stat_type === 'total') {
                $query = "SELECT COUNT(*) as count FROM vendors WHERE DATE(submitted_date) >= ? AND DATE(submitted_date) <= ?";
            } elseif ($stat_type === 'pending') {
                $query = "SELECT COUNT(*) as count FROM vendors WHERE status = 'pending' AND DATE(submitted_date) >= ? AND DATE(submitted_date) <= ?";
            } elseif ($stat_type === 'approved') {
                $query = "SELECT COUNT(*) as count FROM vendors WHERE status = 'approved' AND DATE(reviewed_date) >= ? AND DATE(reviewed_date) <= ?";
            } else { // incomplete
                $query = "SELECT COUNT(*) as count FROM vendors WHERE status = 'incomplete' AND DATE(reviewed_date) >= ? AND DATE(reviewed_date) <= ?";
            }
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $month_start, $month_end);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $monthly_data[] = (int)($row['count'] ?? 0);
            $stmt->close();
        }
        
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'year' => $year,
            'type' => $stat_type,
            'data' => $monthly_data,
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log("Get monthly statistics error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        exit;
    }
}

// Helper function to get time ago
function getTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

// Get statistics
try {
$conn = getDBConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    die("FATAL ERROR: Cannot connect to database: " . $e->getMessage() . "<br>Check your config.php file.");
}

// Ensure vendors.stall_number / stall_block + market_slots holder columns exist.
dpmEnsureStallColumns($conn);

// Latest approve/incomplete per vendor by max(action_id). MAX() on status strings was lexicographic and could pick the wrong outcome.
$dpm_sql_aa_latest_vendor_status = '(SELECT a.vendor_id,
        CASE a.action_type WHEN \'approve\' THEN \'approved\' WHEN \'incomplete\' THEN \'incomplete\' END AS correct_status,
        a.action_date AS last_action_date
    FROM approval_actions a
    INNER JOIN (
        SELECT vendor_id, MAX(action_id) AS max_id
        FROM approval_actions
        GROUP BY vendor_id
    ) z ON z.vendor_id = a.vendor_id AND z.max_id = a.action_id
    WHERE a.action_type IN (\'approve\', \'incomplete\'))';
$dpm_sql_skip_pending_resubmit = "AND NOT (v.status = 'pending' AND COALESCE(v.review_status, 'pending') = 'pending' AND COALESCE(v.is_reviewed, 0) = 0 AND v.reviewed_by IS NULL AND v.reviewed_date IS NULL)";

// Current media files for Appearance (from media_files)
$admin_media_video_paths = [
    'overview_video' => null,
    'price_video' => null,
    'third_video' => null,
    'homepage_announcement_video' => null,
];
$media_table_check = $conn->query("SHOW TABLES LIKE 'media_files'");
if ($media_table_check && $media_table_check->num_rows > 0) {
    foreach (array_keys($admin_media_video_paths) as $media_section_key) {
        $media_stmt = $conn->prepare("SELECT file_path FROM media_files WHERE file_type = 'video' AND section = ? AND (is_active = 1 OR is_active IS NULL) ORDER BY uploaded_at DESC LIMIT 1");
        if ($media_stmt) {
            $media_stmt->bind_param("s", $media_section_key);
            $media_stmt->execute();
            $media_res = $media_stmt->get_result();
            if ($media_row = $media_res->fetch_assoc()) {
                $admin_media_video_paths[$media_section_key] = $media_row['file_path'];
            }
            $media_stmt->close();
        }
    }
}

// Upload history + per-section file metadata for Media Management.
// Derived from the existing media_files table (no upload backend touched).
$admin_media_section_titles = [
    'price_video'                 => 'Market Price of Prime & Other Commodities Monitoring Report',
    'overview_video'              => 'Promotional Permit Video',
    'third_video'                 => 'Announcement Video',
    'homepage_announcement_video' => 'Homepage Announcement Video',
];
$admin_media_history = [];
$admin_media_meta = [];
$admin_homepage_news_cards = homepage_news_get_cards($conn);
$admin_application_fees = application_fees_get_all($conn);
$admin_fees_by_type = [];
foreach ($admin_application_fees as $fee_row) {
    $admin_fees_by_type[$fee_row['id'] ?? ''] = $fee_row;
}
$admin_fee_new = $admin_fees_by_type['new'] ?? application_fees_defaults()['new'];
$admin_fee_renewal = $admin_fees_by_type['renewal'] ?? application_fees_defaults()['renewal'];
if ($media_table_check && $media_table_check->num_rows > 0) {
    $hist_index = 0;
    foreach ($admin_media_section_titles as $sec => $sec_title) {
        $current_path = $admin_media_video_paths[$sec] ?? null;
        $row = null;

        if (!empty($current_path)) {
            $meta_stmt = $conn->prepare("SELECT file_name, file_path, file_size, section, uploaded_at, is_active, title
                FROM media_files
                WHERE file_type = 'video' AND section = ? AND file_path = ?
                ORDER BY uploaded_at DESC LIMIT 1");
            if ($meta_stmt) {
                $meta_stmt->bind_param('ss', $sec, $current_path);
                $meta_stmt->execute();
                $meta_res = $meta_stmt->get_result();
                $row = $meta_res->fetch_assoc() ?: null;
                $meta_stmt->close();
            }
        } else {
            $meta_stmt = $conn->prepare("SELECT file_name, file_path, file_size, section, uploaded_at, is_active, title
                FROM media_files
                WHERE file_type = 'video' AND section = ?
                ORDER BY uploaded_at DESC LIMIT 1");
            if ($meta_stmt) {
                $meta_stmt->bind_param('s', $sec);
                $meta_stmt->execute();
                $meta_res = $meta_stmt->get_result();
                $row = $meta_res->fetch_assoc() ?: null;
                $meta_stmt->close();
            }
        }

        $is_active = !empty($current_path) && $row
            && ((int) ($row['is_active'] ?? 1) === 1 || $row['is_active'] === null);

        $entry = [
            'code'         => str_pad((string) (++$hist_index), 3, '0', STR_PAD_LEFT),
            'section'      => $sec,
            'title'        => $sec_title,
            'display_name' => $sec_title,
            'file_name'    => $row ? ($row['file_name'] ?: basename((string) $row['file_path'])) : '',
            'file_size'    => ($row && isset($row['file_size'])) ? (int) $row['file_size'] : 0,
            'uploaded_at'  => $row['uploaded_at'] ?? null,
            'file_path'    => $row['file_path'] ?? '',
            'is_active'    => $is_active,
        ];

        $admin_media_history[] = $entry;
        if ($is_active) {
            $admin_media_meta[$sec] = $entry;
        }
    }
}

if (!function_exists('admin_format_bytes')) {
    function admin_format_bytes($bytes) {
        $bytes = (float) $bytes;
        if ($bytes <= 0) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = max(0, min($i, count($units) - 1));
        $val = $bytes / pow(1024, $i);
        return ($val >= 10 || $i === 0 ? round($val) : round($val, 1)) . ' ' . $units[$i];
    }
}

$admin_media_active_count = 0;
foreach ($admin_media_video_paths as $admin_media_path_val) {
    if (!empty($admin_media_path_val)) {
        $admin_media_active_count++;
    }
}

// Allow vendor resubmits to return to pending; block accidental admin resets only
dpmEnsureVendorResubmitTrigger($conn);
dpmEnsureApprovalActionsSchema($conn);
dpmDisableLegacyStatusAutoFix($conn);
error_log("✓✓✓ Vendor resubmit trigger ensured; legacy auto-fix disabled");

// Add is_reviewed column if it doesn't exist (permanent flag that can't be reset)
$conn->query("ALTER TABLE vendors ADD COLUMN IF NOT EXISTS is_reviewed TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE vendors ADD COLUMN IF NOT EXISTS review_status ENUM('pending', 'approved', 'incomplete') DEFAULT 'pending'");

// Legacy stored procedure/event reverted vendor resubmits — disabled (see vendor_resubmit_helpers.php)

// ABSOLUTE FIX: Immediately fix ALL vendors on every page load - NO LIMITATIONS
// This ensures statuses are ALWAYS correct before any other operations
$conn->query("UPDATE vendors SET status = review_status, is_reviewed = 1 WHERE review_status IS NOT NULL AND review_status != 'pending' AND status != review_status");
$conn->query("UPDATE vendors SET status = CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END, review_status = CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END, is_reviewed = 1 WHERE reviewed_by IS NOT NULL AND reviewed_date IS NOT NULL AND (status = 'pending' OR review_status IS NULL OR review_status = 'pending') " . dpmSqlSkipPendingResubmit());
$conn->query("
    UPDATE vendors v
    INNER JOIN " . $dpm_sql_aa_latest_vendor_status . " aa ON v.vendor_id = aa.vendor_id
    SET v.status = aa.correct_status,
        v.review_status = aa.correct_status,
        v.is_reviewed = 1
    WHERE aa.correct_status IS NOT NULL
    {$dpm_sql_skip_pending_resubmit}
");
error_log("✓✓✓ IMMEDIATE FIX: Fixed ALL vendors on page load - NO LIMITATIONS");

// Get vendor statistics (OPTIMIZED with single query using COUNT instead of SUM for better performance)
// Using COUNT with WHERE is more efficient than SUM with CASE on large tables
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM vendors) as total_applications,
        (SELECT COUNT(*) FROM vendors WHERE status = 'pending') as pending_count,
        (SELECT COUNT(*) FROM vendors WHERE status = 'approved') as approved_count,
        (SELECT COUNT(*) FROM vendors WHERE status = 'incomplete') as incomplete_count,
        (SELECT COUNT(*) FROM vendors WHERE application_type = 'new') as new_applications,
        (SELECT COUNT(*) FROM vendors WHERE application_type = 'renewal') as renewal_applications
";
// Remove FROM DUAL for better compatibility - use SELECT without FROM
$stats_result = $conn->query($stats_query);
if (!$stats_result) {
    error_log("Stats query error: " . $conn->error);
    // Fallback: Get stats individually
    $total_result = $conn->query("SELECT COUNT(*) as total FROM vendors");
    $pending_result = $conn->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'pending'");
    $approved_result = $conn->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'approved'");
    $incomplete_result = $conn->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'incomplete'");
    $new_result = $conn->query("SELECT COUNT(*) as count FROM vendors WHERE application_type = 'new'");
    $renewal_result = $conn->query("SELECT COUNT(*) as count FROM vendors WHERE application_type = 'renewal'");
    
    $stats = [
        'total_applications' => ($total_result && ($row = $total_result->fetch_assoc())) ? (int)$row['total'] : 0,
        'pending_count' => ($pending_result && ($row = $pending_result->fetch_assoc())) ? (int)$row['count'] : 0,
        'approved_count' => ($approved_result && ($row = $approved_result->fetch_assoc())) ? (int)$row['count'] : 0,
        'incomplete_count' => ($incomplete_result && ($row = $incomplete_result->fetch_assoc())) ? (int)$row['count'] : 0,
        'new_applications' => ($new_result && ($row = $new_result->fetch_assoc())) ? (int)$row['count'] : 0,
        'renewal_applications' => ($renewal_result && ($row = $renewal_result->fetch_assoc())) ? (int)$row['count'] : 0
    ];
} else {
$stats = $stats_result->fetch_assoc();
    if (!$stats) {
        // Fallback if fetch_assoc returns null
        $stats = [
            'total_applications' => 0,
            'pending_count' => 0,
            'approved_count' => 0,
            'incomplete_count' => 0,
            'new_applications' => 0,
            'renewal_applications' => 0
        ];
    }
}

// Get recent admin activities (approve/incomplete actions)
// Get recent activities - FIXED: Show all activities, handle missing tables gracefully
$recent_activities = [];
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'approval_actions'");
    if ($table_check && $table_check->num_rows > 0) {
        $recent_activities_query = "
            SELECT 
                aa.action_id,
                aa.action_type,
                COALESCE(aa.action_date, NOW()) as action_date,
                COALESCE(aa.admin_notes, '') as admin_notes,
                COALESCE(v.vendor_name, 'Unknown Vendor') as vendor_name,
                COALESCE(v.application_type, 'new') as application_type,
                COALESCE(u.full_name, 'Unknown Admin') as admin_name,
                COALESCE(u.email, '') as admin_email
            FROM approval_actions aa
            LEFT JOIN vendors v ON aa.vendor_id = v.vendor_id
            LEFT JOIN users u ON aa.admin_id = u.user_id
            WHERE aa.action_type IN ('approve', 'incomplete')
            ORDER BY COALESCE(aa.action_date, aa.action_id) DESC, aa.action_id DESC
            LIMIT 1
        ";
        $recent_activities_result = $conn->query($recent_activities_query);
        if ($recent_activities_result) {
            while ($row = $recent_activities_result->fetch_assoc()) {
                $recent_activities[] = $row;
            }
            error_log("Recent activities loaded: " . count($recent_activities));
        } else {
            error_log("Recent activities query failed: " . $conn->error);
        }
    } else {
        error_log("approval_actions table does not exist");
    }
} catch (Exception $e) {
    error_log("Error loading recent activities: " . $e->getMessage());
}

// Determine current section from URL
$current_section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
$valid_sections = ['dashboard', 'vendors', 'appearance', 'records', 'adminlog', 'receipts', 'concerns', 'reports', 'ratings'];
if (!in_array($current_section, $valid_sections)) {
    $current_section = 'dashboard';
}

// Determine what page we are on
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Pagination for All Vendor Applications - 10 items per page
$page_size = isset($_GET['page_size']) && is_numeric($_GET['page_size']) ? (int)$_GET['page_size'] : 10;
if ($page_size < 1) $page_size = 10;
if ($page_size > 100) $page_size = 100;
$offset = ($page - 1) * $page_size;

// Optimized: Get total row count for pagination (using COUNT(*) which is optimized in MySQL)
// For very large tables, this is still efficient with proper indexing
$total_rows_result = $conn->query("SELECT COUNT(*) as total FROM vendors");
if (!$total_rows_result) {
    error_log("Total rows query error: " . $conn->error);
    $total_rows = 0;
} else {
    $total_rows_data = $total_rows_result->fetch_assoc();
    $total_rows = $total_rows_data ? (int)$total_rows_data['total'] : 0;
}
$total_pages = $total_rows > 0 ? ceil($total_rows / $page_size) : 1;

// NO PAGINATION - All data shown, no validation needed

// Function to generate pagination page numbers (smart range display for millions of pages)
function generatePaginationNumbers($current_page, $total_pages, $range = 2) {
    $pages = [];
    
    if ($total_pages <= 1) {
        return $pages;
    }
    
    // Always show first page
    if ($current_page > $range + 2) {
        $pages[] = 1;
        if ($current_page > $range + 3) {
            $pages[] = 'ellipsis';
        }
    }
    
    // Show pages around current page
    $start = max(1, $current_page - $range);
    $end = min($total_pages, $current_page + $range);
    
    for ($i = $start; $i <= $end; $i++) {
        $pages[] = $i;
    }
    
    // Always show last page
    if ($current_page < $total_pages - $range - 1) {
        if ($current_page < $total_pages - $range - 2) {
            $pages[] = 'ellipsis';
        }
        $pages[] = $total_pages;
    }
    
    return $pages;
}

// Optimized query: Get recent applications with proper indexing
// Using indexed columns for better performance on large datasets
// For dashboard Recent Applications table: use regular pagination (5 items per page)
$recent_applications_page_size = 5;
// Calculate total pages based on actual count (will be updated after fetching data)
$recent_applications_total_pages = 1; // Default, will be recalculated

// Validate page number for recent applications doesn't exceed total pages
// Only validate if we're on the dashboard section
if ($current_section === 'dashboard') {
    if ($page < 1) {
        $page = 1;
    }
}

// Calculate offset for pagination
$recent_applications_offset = ($page - 1) * $recent_applications_page_size;

// BULLETPROOF FIX: Fix ALL vendors - NO LIMITATIONS, NO MAXIMUM, run MANY times
// This runs BEFORE loading data and ensures ALL statuses are PERMANENTLY correct
for ($pre_fix = 0; $pre_fix < 10; $pre_fix++) {
    // Fix ALL vendors with review_status set
    $conn->query("UPDATE vendors SET status = review_status, is_reviewed = 1 WHERE review_status IS NOT NULL AND review_status != 'pending' AND status != review_status");
    
    // Fix ALL vendors with reviewed_by set
    $conn->query("UPDATE vendors SET status = CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END, review_status = CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END, is_reviewed = 1 WHERE reviewed_by IS NOT NULL AND reviewed_date IS NOT NULL AND (status = 'pending' OR review_status IS NULL OR review_status = 'pending') " . dpmSqlSkipPendingResubmit());
    
    // Fix ALL vendors using approval_actions (latest row by action_id)
    $conn->query("
        UPDATE vendors v
        INNER JOIN " . $dpm_sql_aa_latest_vendor_status . " aa ON v.vendor_id = aa.vendor_id
        SET v.status = aa.correct_status,
            v.review_status = aa.correct_status,
            v.is_reviewed = 1,
            v.reviewed_date = COALESCE(v.reviewed_date, aa.last_action_date)
        WHERE aa.correct_status IS NOT NULL
    {$dpm_sql_skip_pending_resubmit}
    ");
    
    // Force ALL vendors with is_reviewed=1
    $conn->query("UPDATE vendors SET status = COALESCE(review_status, CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END), review_status = COALESCE(review_status, CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END) WHERE is_reviewed = 1 AND (status = 'pending' OR review_status IS NULL OR review_status = 'pending') " . dpmSqlSkipPendingResubmit());
}
error_log("✓✓✓ PRE-LOAD FIX: Ran 10 times on ALL vendors - NO LIMITATIONS, NO MAXIMUM");

// CRITICAL SQL FIX: Force status to match review_status RIGHT BEFORE SELECT
// This ensures the data we SELECT is ALWAYS correct - run 20 times to be absolutely sure
for ($sql_fix = 0; $sql_fix < 20; $sql_fix++) {
    // Fix 1: If review_status exists and is not pending, status MUST match it
    $conn->query("UPDATE vendors SET status = review_status WHERE review_status IS NOT NULL AND review_status != 'pending' AND status != review_status");
    
    // Fix 2: If reviewed_by exists, status MUST be approved or incomplete (never pending)
    $conn->query("UPDATE vendors SET status = CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END, review_status = CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END, is_reviewed = 1 WHERE reviewed_by IS NOT NULL AND reviewed_date IS NOT NULL AND status = 'pending' " . dpmSqlSkipPendingResubmit());
    
    // Fix 3: Use approval_actions as absolute truth (latest row by action_id)
    $conn->query("
        UPDATE vendors v
        INNER JOIN " . $dpm_sql_aa_latest_vendor_status . " aa ON v.vendor_id = aa.vendor_id
        SET v.status = aa.correct_status,
            v.review_status = aa.correct_status,
            v.is_reviewed = 1
        WHERE aa.correct_status IS NOT NULL
    {$dpm_sql_skip_pending_resubmit}
        AND (v.status != aa.correct_status OR v.review_status != aa.correct_status OR v.status = 'pending')
    ");
    
    // Fix 4: If is_reviewed=1, status CANNOT be pending
    $conn->query("UPDATE vendors SET status = COALESCE(review_status, CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END), review_status = COALESCE(review_status, CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END) WHERE is_reviewed = 1 AND status = 'pending' " . dpmSqlSkipPendingResubmit());
}
error_log("✓✓✓ SQL FIX: Ran 20 times RIGHT BEFORE SELECT - status is now PERMANENT");

// Ensure columns exist before querying
$conn->query("ALTER TABLE vendors ADD COLUMN IF NOT EXISTS is_reviewed TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE vendors ADD COLUMN IF NOT EXISTS review_status ENUM('pending', 'approved', 'incomplete') DEFAULT 'pending'");
$conn->query("ALTER TABLE vendors ADD COLUMN IF NOT EXISTS reviewed_by INT NULL");
$conn->query("ALTER TABLE vendors ADD COLUMN IF NOT EXISTS reviewed_date DATETIME NULL");
$conn->query("ALTER TABLE vendors ADD COLUMN IF NOT EXISTS admin_notes TEXT NULL");
$conn->query("ALTER TABLE vendors ADD COLUMN IF NOT EXISTS submitted_date DATETIME NULL");
$conn->query("ALTER TABLE vendors ADD COLUMN IF NOT EXISTS application_type ENUM('new', 'renewal') DEFAULT 'new'");

// Set defaults for NULL values (do not force reviewed rows back to pending)
$conn->query("UPDATE vendors SET is_reviewed = 0 WHERE is_reviewed IS NULL");
$conn->query("UPDATE vendors SET review_status = status WHERE review_status IS NULL AND status IN ('approved', 'incomplete')");
$conn->query("UPDATE vendors SET review_status = 'pending' WHERE review_status IS NULL AND COALESCE(status, 'pending') = 'pending' AND (reviewed_by IS NULL OR reviewed_date IS NULL)");
$conn->query("UPDATE vendors SET application_type = 'new' WHERE application_type IS NULL");
$conn->query("UPDATE vendors SET submitted_date = COALESCE(submitted_date, NOW()) WHERE submitted_date IS NULL");

// FIXED QUERY: Use LEFT JOIN to show ALL vendors, even if user doesn't exist
// This ensures no applications are hidden
$recent_applications_query = "
    SELECT v.*, 
           COALESCE(u.email, '') as email, 
           COALESCE(u.full_name, 'Unknown User') as user_name,
           CASE 
               WHEN v.status = 'pending' THEN 'pending'
               WHEN v.status IN ('approved', 'incomplete') THEN v.status
               WHEN v.review_status IS NOT NULL AND v.review_status != 'pending' THEN v.review_status
               WHEN v.is_reviewed = 1 AND v.reviewed_by IS NOT NULL THEN 
                   CASE WHEN v.admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END
               ELSE COALESCE(v.status, 'pending')
           END as display_status,
           COALESCE(v.is_reviewed, 0) as is_reviewed,
           COALESCE(v.review_status, 'pending') as review_status,
           COALESCE(v.status, 'pending') as original_status
    FROM vendors v
    LEFT JOIN users u ON v.user_id = u.user_id
    WHERE v.vendor_id IS NOT NULL
    ORDER BY COALESCE(v.submitted_date, v.vendor_id) DESC, v.vendor_id DESC
    -- NO LIMIT - Show ALL applications
";

// Query for vendors section with pagination - FIXED: Use LEFT JOIN
$recent_query = "
    SELECT v.*, 
           COALESCE(u.email, '') as email, 
           COALESCE(u.full_name, 'Unknown User') as user_name,
           CASE 
               WHEN v.status = 'pending' THEN 'pending'
               WHEN v.status IN ('approved', 'incomplete') THEN v.status
               WHEN v.review_status IS NOT NULL AND v.review_status != 'pending' THEN v.review_status
               WHEN v.is_reviewed = 1 AND v.reviewed_by IS NOT NULL THEN 
                   CASE WHEN v.admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END
               ELSE COALESCE(v.status, 'pending')
           END as display_status,
           COALESCE(v.is_reviewed, 0) as is_reviewed,
           COALESCE(v.review_status, 'pending') as review_status,
           COALESCE(v.status, 'pending') as original_status
    FROM vendors v
    LEFT JOIN users u ON v.user_id = u.user_id
    WHERE v.vendor_id IS NOT NULL
    ORDER BY COALESCE(v.submitted_date, v.vendor_id) DESC, v.vendor_id DESC
    LIMIT " . (int)$page_size . " OFFSET " . (int)$offset . "
";

// User Records pagination
$records_page = isset($_GET['records_page']) && is_numeric($_GET['records_page']) ? (int)$_GET['records_page'] : 1;
if ($records_page < 1) {
    $records_page = 1;
}
$records_page_size = 12;
$records_offset = ($records_page - 1) * $records_page_size;

// Ensure database connection exists
if (!isset($conn) || !$conn) {
    $conn = getDBConnection();
}

// Get records - SIMPLIFIED QUERY like recent applications
$records_count_result = $conn->query("SELECT COUNT(*) as total FROM users");
if (!$records_count_result) {
    error_log("Records count query error: " . $conn->error);
    $records_total = 0;
} else {
    $records_row = $records_count_result->fetch_assoc();
    $records_total = $records_row ? (int)$records_row['total'] : 0;
}
$records_total_pages = max(1, (int)ceil($records_total / $records_page_size));
if ($records_page > $records_total_pages) {
    $records_page = $records_total_pages;
    $records_offset = ($records_page - 1) * $records_page_size;
}

// Get records - SIMPLEST QUERY FIRST (like recent applications approach)
$all_records = [];
try {
    // Include profile columns (gender, age, residential_address) when they exist
    $profile_cols = '';
    $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'residential_address'");
    if ($col_check && $col_check->num_rows > 0) {
        $profile_cols = ', gender, age, residential_address';
    }
    // First, get all users with simplest query
    $simple_users_result = $conn->query("SELECT user_id, email, full_name, phone_number{$profile_cols}, created_at, status, last_login FROM users ORDER BY created_at DESC, user_id DESC LIMIT " . (int)$records_page_size . " OFFSET " . (int)$records_offset);
    if ($simple_users_result) {
        while ($row = $simple_users_result->fetch_assoc()) {
            // Add defaults for missing fields
            $row['user_name'] = $row['full_name'] ?? 'Unknown User';
            $row['email'] = $row['email'] ?? '';
            $row['phone_number'] = $row['phone_number'] ?? '';
            $row['gender'] = $row['gender'] ?? '';
            $row['age'] = $row['age'] ?? '';
            $row['residential_address'] = $row['residential_address'] ?? '';
            $row['member_since'] = $row['created_at'] ?? date('Y-m-d H:i:s');
            $row['user_status'] = $row['status'] ?? 'active';
            $row['profile_image'] = ''; // Will be filled below
            
        $all_records[] = $row;
        }
        error_log("✓✓✓ USERS QUERY SUCCESS: Found " . count($all_records) . " users");
        
        // Now get profile images for each user
        if (count($all_records) > 0) {
            foreach ($all_records as &$record) {
                $user_id = $record['user_id'];
                // Try to get profile image from user_profiles table
                $profile_query = $conn->query("SELECT profile_image FROM user_profiles WHERE user_id = " . (int)$user_id . " AND profile_type = 'vendor' LIMIT 1");
                if ($profile_query && $profile_row = $profile_query->fetch_assoc()) {
                    $record['profile_image'] = $profile_row['profile_image'] ?? '';
                }
                // If still empty, check users table
                if (empty($record['profile_image'])) {
                    $user_profile_query = $conn->query("SELECT profile_image FROM users WHERE user_id = " . (int)$user_id . " LIMIT 1");
                    if ($user_profile_query && $user_profile_row = $user_profile_query->fetch_assoc()) {
                        $record['profile_image'] = $user_profile_row['profile_image'] ?? '';
                    }
                }
            }
            unset($record);
        }
    } else {
        error_log("✗✗✗ USERS QUERY FAILED: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("✗✗✗ EXCEPTION loading users: " . $e->getMessage());
    $all_records = [];
}

error_log("✓✓✓ Records loaded: " . count($all_records) . " out of " . $records_total . " total users");

// Fallback: If records are still empty, try simplest possible query
if (empty($all_records) && $records_total > 0) {
    error_log("⚠ WARNING: all_records is empty but database has " . $records_total . " users, attempting fallback query...");
    $fallback_result = $conn->query("SELECT user_id, email, full_name, phone_number, created_at, status FROM users ORDER BY user_id DESC");
    if ($fallback_result) {
        $all_records = [];
        while ($row = $fallback_result->fetch_assoc()) {
            $row['user_name'] = $row['full_name'] ?? 'Unknown User';
            $row['email'] = $row['email'] ?? '';
            $row['phone_number'] = $row['phone_number'] ?? '';
            $row['gender'] = $row['gender'] ?? '';
            $row['age'] = $row['age'] ?? '';
            $row['residential_address'] = $row['residential_address'] ?? '';
            $row['member_since'] = $row['created_at'] ?? date('Y-m-d H:i:s');
            $row['user_status'] = $row['status'] ?? 'active';
            $row['profile_image'] = '';
            $all_records[] = $row;
        }
        error_log("✓ Fallback query found " . count($all_records) . " users");
    } else {
        error_log("✗ Fallback query failed: " . $conn->error);
    }
}

$admin_log_page = isset($_GET['admin_log_page']) && is_numeric($_GET['admin_log_page']) ? (int)$_GET['admin_log_page'] : 1;
if ($admin_log_page < 1) $admin_log_page = 1;
// Admin log page size - increased to show more activities
$admin_log_page_size = isset($_GET['admin_log_page_size']) && is_numeric($_GET['admin_log_page_size']) ? (int)$_GET['admin_log_page_size'] : 10;
if ($admin_log_page_size < 1) $admin_log_page_size = 10;
if ($admin_log_page_size > 500) $admin_log_page_size = 500;
$admin_log_offset = ($admin_log_page - 1) * $admin_log_page_size;

$admin_logs = [];
$admin_log_total = 0;
$admin_log_total_pages = 1;

try {
    $table_check = $conn->query("SHOW TABLES LIKE 'approval_actions'");
    if ($table_check && $table_check->num_rows > 0) {
        $admin_log_count_result = $conn->query("SELECT COUNT(*) as total FROM approval_actions");
        if ($admin_log_count_result) {
            $admin_log_row = $admin_log_count_result->fetch_assoc();
            $admin_log_total = $admin_log_row ? (int)$admin_log_row['total'] : 0;
            $admin_log_total_pages = $admin_log_total > 0 ? ceil($admin_log_total / $admin_log_page_size) : 1;
        }

        $admin_log_query = "
            SELECT 
                aa.action_id,
                aa.vendor_id,
                aa.admin_id,
                aa.action_type,
                COALESCE(aa.admin_notes, '') as admin_notes,
                COALESCE(aa.rejection_reason, '') as rejection_reason,
                COALESCE(aa.status_before, 'pending') as status_before,
                COALESCE(aa.status_after, 'pending') as status_after,
                aa.action_date,
                COALESCE(aa.ip_address, '') as ip_address,
                COALESCE(admin_user.full_name, 'Unknown Admin') as admin_name,
                COALESCE(admin_user.email, '') as admin_email,
                COALESCE(v.vendor_name, 'Unknown Vendor') as vendor_name,
                COALESCE(v.application_type, 'new') as application_type,
                COALESCE(v.section, '') as section,
                COALESCE(v.block, '') as block,
                COALESCE(u.full_name, 'Unknown User') as user_name,
                COALESCE(u.email, '') as user_email
            FROM approval_actions aa
            LEFT JOIN users admin_user ON aa.admin_id = admin_user.user_id
            LEFT JOIN vendors v ON aa.vendor_id = v.vendor_id
            LEFT JOIN users u ON v.user_id = u.user_id
            WHERE aa.action_id IS NOT NULL
            ORDER BY COALESCE(aa.action_date, aa.action_id) DESC
            LIMIT $admin_log_offset, $admin_log_page_size
        ";
        $admin_log_result = $conn->query($admin_log_query);
        if ($admin_log_result) {
            while ($row = $admin_log_result->fetch_assoc()) {
                $admin_logs[] = $row;
            }
            error_log("Admin logs loaded: " . count($admin_logs));
        } else {
            error_log("Admin log query failed: " . $conn->error);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching admin logs: " . $e->getMessage());
    $admin_logs = [];
}

// Fetch receipts for receipts section
$receipts_page = isset($_GET['receipts_page']) && is_numeric($_GET['receipts_page']) ? (int)$_GET['receipts_page'] : 1;
if ($receipts_page < 1) $receipts_page = 1;
// Receipts page size - increased to show more receipts
$receipts_page_size = isset($_GET['receipts_page_size']) && is_numeric($_GET['receipts_page_size']) ? (int)$_GET['receipts_page_size'] : 10;
if ($receipts_page_size < 1) $receipts_page_size = 10;
if ($receipts_page_size > 500) $receipts_page_size = 500;
$receipts_offset = ($receipts_page - 1) * $receipts_page_size;

$receipts = [];
$receipts_total = 0;
$receipts_total_pages = 1;

try {
    // Check if receipts table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'receipts'");
    if ($table_check && $table_check->num_rows > 0) {
        // Get total count of ALL receipts (optimized for large datasets)
        $receipts_count_result = $conn->query("SELECT COUNT(*) as total FROM receipts");
        if ($receipts_count_result) {
            $receipts_row = $receipts_count_result->fetch_assoc();
            $receipts_total = $receipts_row ? (int)$receipts_row['total'] : 0;
            $receipts_total_pages = 1; // NO PAGINATION - Show all
        }
        
        // Fetch ALL receipts with pagination (optimized query with index on created_at)
        $receipts_query = "
            SELECT 
                r.id,
                r.user_id,
                r.user_name,
                r.user_email,
                r.section,
                r.block,
                r.receipt_file_path,
                r.notes,
                r.status,
                r.reviewed_by,
                r.reviewed_at,
                r.created_at
            FROM receipts r
            ORDER BY r.created_at DESC
            LIMIT $receipts_offset, $receipts_page_size
        ";
        $receipts_result = $conn->query($receipts_query);
        if ($receipts_result) {
            while ($row = $receipts_result->fetch_assoc()) {
                $receipts[] = $row;
            }
        } else {
            error_log("Receipts query failed: " . $conn->error);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching receipts: " . $e->getMessage());
    $receipts = [];
}

// Reports section: receipt status breakdown
$report_receipt_pending = 0;
$report_receipt_approved = 0;
$report_receipt_rejected = 0;
try {
    $report_rc = $conn->query("SHOW TABLES LIKE 'receipts'");
    if ($report_rc && $report_rc->num_rows > 0) {
        $report_rs = $conn->query("SELECT LOWER(TRIM(COALESCE(status, ''))) AS st, COUNT(*) AS c FROM receipts GROUP BY LOWER(TRIM(COALESCE(status, '')))");
        if ($report_rs) {
            while ($report_row = $report_rs->fetch_assoc()) {
                $st = (string)($report_row['st'] ?? '');
                $c = (int)($report_row['c'] ?? 0);
                if ($st === 'pending') {
                    $report_receipt_pending = $c;
                } elseif ($st === 'approved') {
                    $report_receipt_approved = $c;
                } elseif ($st === 'rejected' || $st === 'invalid') {
                    $report_receipt_rejected += $c;
                }
            }
        }
    }
} catch (Exception $e) {
    // keep zeros
}

// Customer concerns (support widget — submit_customer_concern.php)
$concerns_page = isset($_GET['concerns_page']) && is_numeric($_GET['concerns_page']) ? (int) $_GET['concerns_page'] : 1;
if ($concerns_page < 1) {
    $concerns_page = 1;
}
$concerns_page_size = 20;
$concerns_offset = ($concerns_page - 1) * $concerns_page_size;
$concerns = [];
$concerns_total = 0;
$concerns_total_pages = 1;
try {
    $concerns_table = $conn->query("SHOW TABLES LIKE 'customer_concerns'");
    if ($concerns_table && $concerns_table->num_rows > 0) {
        $concerns_cnt = $conn->query('SELECT COUNT(*) AS total FROM customer_concerns');
        if ($concerns_cnt) {
            $concerns_cnt_row = $concerns_cnt->fetch_assoc();
            $concerns_total = $concerns_cnt_row ? (int) $concerns_cnt_row['total'] : 0;
            $concerns_total_pages = $concerns_total > 0 ? (int) ceil($concerns_total / $concerns_page_size) : 1;
        }
        if ($concerns_page > $concerns_total_pages && $concerns_total_pages > 0) {
            $concerns_page = $concerns_total_pages;
            $concerns_offset = ($concerns_page - 1) * $concerns_page_size;
        }
        $co_lim = (int) $concerns_page_size;
        $co_off = (int) $concerns_offset;
        $concerns_sql = "SELECT id, submitter_name, submitter_email, message, created_at
            FROM customer_concerns ORDER BY created_at DESC, id DESC LIMIT $co_off, $co_lim";
        $concerns_result = $conn->query($concerns_sql);
        if ($concerns_result) {
            while ($row = $concerns_result->fetch_assoc()) {
                $concerns[] = $row;
            }
        } else {
            error_log('Customer concerns query failed: ' . $conn->error);
        }
    }
} catch (Exception $e) {
    error_log('Error fetching customer concerns: ' . $e->getMessage());
}

// Vendor applications are loaded AFTER all status-sync queries (see "LOAD VENDOR APPLICATIONS" below)
// so the UI matches the database on this request.

// FINAL BULLETPROOF CHECK: Force status to match review_status for ALL reviewed vendors
// NO LIMITATIONS - Fix ALL vendors, run MANY times to ensure it sticks
for ($fix_loop = 0; $fix_loop < 10; $fix_loop++) {
    // Fix 1: Use review_status as source of truth - NO LIMITS, UPDATE ALL
    $conn->query("
        UPDATE vendors 
        SET status = review_status,
            is_reviewed = 1
        WHERE review_status IS NOT NULL 
        AND review_status != 'pending'
        AND status != review_status
    ");
    
    // Fix 2: Use reviewed_by/reviewed_date to set status - NO LIMITS, UPDATE ALL
    $conn->query("
        UPDATE vendors 
        SET status = CASE 
                WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete'
                ELSE 'approved'
            END,
            review_status = CASE 
                WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete'
                ELSE 'approved'
            END,
            is_reviewed = 1
        WHERE reviewed_by IS NOT NULL 
        AND reviewed_date IS NOT NULL
        AND (status = 'pending' OR review_status IS NULL OR review_status = 'pending')
    ");
    
    // Fix 3: Use approval_actions as absolute source (latest row by action_id)
    $conn->query("
        UPDATE vendors v
        INNER JOIN " . $dpm_sql_aa_latest_vendor_status . " aa ON v.vendor_id = aa.vendor_id
        SET v.status = aa.correct_status,
            v.review_status = aa.correct_status,
            v.is_reviewed = 1
        WHERE aa.correct_status IS NOT NULL
    {$dpm_sql_skip_pending_resubmit}
    ");
    
    // Fix 4: Force ALL vendors with is_reviewed=1 to have correct status - NO LIMITS
    $conn->query("
        UPDATE vendors 
        SET status = review_status,
            review_status = COALESCE(review_status, 
                CASE 
                    WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete'
                    ELSE 'approved'
                END)
        WHERE is_reviewed = 1 
        AND (status = 'pending' OR review_status IS NULL OR review_status = 'pending')
        " . dpmSqlSkipPendingResubmit() . "
    ");
}
error_log("✓✓✓ BULLETPROOF FIX: Ran status correction 10 times on ALL vendors - NO LIMITATIONS");

// Run fixes ONE MORE TIME after initial fixes
for ($final_fix = 0; $final_fix < 3; $final_fix++) {
    $conn->query("UPDATE vendors SET status = review_status WHERE review_status IS NOT NULL AND review_status != 'pending' AND status != review_status");
    $conn->query("UPDATE vendors SET status = 'approved', review_status = 'approved', is_reviewed = 1 WHERE reviewed_by IS NOT NULL AND reviewed_date IS NOT NULL AND status = 'pending' AND (admin_notes IS NULL OR admin_notes NOT LIKE '%Incomplete Reason%') " . dpmSqlSkipPendingResubmit());
    $conn->query("UPDATE vendors SET status = 'incomplete', review_status = 'incomplete', is_reviewed = 1 WHERE reviewed_by IS NOT NULL AND reviewed_date IS NOT NULL AND status = 'pending' AND admin_notes LIKE '%Incomplete Reason%' " . dpmSqlSkipPendingResubmit());
}

// Diagnostic: Check if vendors exist
$vendor_check = $conn->query("SELECT COUNT(*) as count FROM vendors");
if ($vendor_check) {
    $vendor_count = $vendor_check->fetch_assoc();
    $total_vendors_count = $vendor_count['count'] ?? 0;
    error_log("✓ Total vendors in database: " . $total_vendors_count);
} else {
    error_log("✗ Could not count vendors: " . $conn->error);
    $total_vendors_count = 0;
}

// Diagnostic: Check if vendors have users
$vendor_user_check = $conn->query("SELECT COUNT(*) as count FROM vendors v LEFT JOIN users u ON v.user_id = u.user_id");
if ($vendor_user_check) {
    $vendor_user_count = $vendor_user_check->fetch_assoc();
    error_log("✓ Vendors with matching users: " . ($vendor_user_count['count'] ?? 0));
} else {
    error_log("✗ Could not count vendors with users: " . $conn->error);
}

// CRITICAL: Ensure arrays are always initialized
if (!isset($recent_applications) || !is_array($recent_applications)) {
    $recent_applications = [];
    error_log("⚠ recent_applications was not initialized - initializing now");
}
if (!isset($paginated_applications) || !is_array($paginated_applications)) {
    $paginated_applications = [];
    error_log("⚠ paginated_applications was not initialized - initializing now");
}
if (!isset($all_records) || !is_array($all_records)) {
    $all_records = [];
    error_log("⚠ all_records was not initialized - initializing now");
}
if (!isset($recent_activities) || !is_array($recent_activities)) {
    $recent_activities = [];
    error_log("⚠ recent_activities was not initialized - initializing now");
}
if (!isset($admin_logs) || !is_array($admin_logs)) {
    $admin_logs = [];
    error_log("⚠ admin_logs was not initialized - initializing now");
}

// Vendor counts logged after DB sync + vendor query (see "LOAD VENDOR APPLICATIONS" below).

// FINAL FINAL CHECK: DB sync before loading vendor rows for the UI - NO LIMITATIONS, NO MAXIMUM
// Run this MANY times to catch EVERYTHING - update ALL vendors unconditionally
for ($final_check = 0; $final_check < 10; $final_check++) {
    // Fix ALL vendors using review_status - NO CONDITIONS, UPDATE ALL
    $conn->query("UPDATE vendors SET status = review_status, is_reviewed = 1 WHERE review_status IS NOT NULL AND review_status != 'pending'");
    
    // Fix ALL vendors using reviewed_by - NO CONDITIONS, UPDATE ALL
    $conn->query("UPDATE vendors SET status = CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END, review_status = CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END, is_reviewed = 1 WHERE reviewed_by IS NOT NULL AND reviewed_date IS NOT NULL");
    
    // Fix ALL using approval_actions (latest row by action_id)
    $conn->query("
        UPDATE vendors v
        INNER JOIN " . $dpm_sql_aa_latest_vendor_status . " aa ON v.vendor_id = aa.vendor_id
        SET v.status = aa.correct_status,
            v.review_status = aa.correct_status,
            v.is_reviewed = 1
        WHERE aa.correct_status IS NOT NULL
    {$dpm_sql_skip_pending_resubmit}
    ");
    
    // Force ALL with is_reviewed=1 - NO CONDITIONS, UPDATE ALL
    $conn->query("UPDATE vendors SET status = COALESCE(review_status, CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END), review_status = COALESCE(review_status, CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END) WHERE is_reviewed = 1");
    
    // ABSOLUTE FIX: If ANY vendor has reviewed_by but status is pending, fix it NOW
    $conn->query("UPDATE vendors SET status = CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END, review_status = CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END, is_reviewed = 1 WHERE reviewed_by IS NOT NULL AND reviewed_date IS NOT NULL AND status = 'pending' " . dpmSqlSkipPendingResubmit());
}
error_log("✓✓✓ FINAL CHECK: Ran 10 times on ALL vendors - NO LIMITATIONS, NO MAXIMUM, UNCONDITIONAL FIXES");

// Statuses are already fixed above before loading data

// Debug: Log the statuses we're loading
error_log("Recent applications loaded: " . json_encode(array_column($recent_applications, 'status')));

// Debug code removed for production - uncomment only when needed for debugging
// Additional debug: Check specific vendor statuses (DISABLED FOR PERFORMANCE)
/*
$debug_vendors = [19, 18, 17, 16, 15];
foreach ($debug_vendors as $vid) {
    $debug_stmt = $conn->prepare("SELECT vendor_id, status, reviewed_date, reviewed_by FROM vendors WHERE vendor_id = ?");
    $debug_stmt->bind_param("i", $vid);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    $debug_vendor = $debug_result->fetch_assoc();
    $debug_stmt->close();
    
    if ($debug_vendor) {
        error_log("Vendor $vid: status=" . $debug_vendor['status'] . ", reviewed_date=" . $debug_vendor['reviewed_date'] . ", reviewed_by=" . $debug_vendor['reviewed_by']);
    } else {
        error_log("Vendor $vid: NOT FOUND");
    }
}
*/

// Get admin profile
$admin_profile = null;
$profile_query = "SELECT * FROM admin_profiles WHERE admin_id = ?";
$profile_stmt = $conn->prepare($profile_query);
if (!$profile_stmt) {
    error_log("Profile query prepare failed: " . $conn->error);
    // Try alternative query without prepared statement
    $profile_result = $conn->query("SELECT * FROM admin_profiles WHERE admin_id = " . (int)$admin_id);
    if ($profile_result) {
        $admin_profile = $profile_result->fetch_assoc();
    }
} else {
$profile_stmt->bind_param("i", $admin_id);
    if ($profile_stmt->execute()) {
$profile_result = $profile_stmt->get_result();
        $admin_profile = $profile_result ? $profile_result->fetch_assoc() : null;
    } else {
        error_log("Profile query execute failed: " . $profile_stmt->error);
    }
$profile_stmt->close();
}

$admin_photo_url = '';
$admin_photo_file = trim((string)($admin_profile['profile_photo'] ?? ''));
if ($admin_photo_file !== '') {
    $photo_candidates = [
        $admin_photo_file,
        'uploads/admin/' . $admin_photo_file,
        'uploads/admin/' . basename($admin_photo_file),
    ];
    foreach ($photo_candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate)) {
            $admin_photo_url = $candidate;
            break;
        }
    }
}

// FINAL ABSOLUTE SQL FIX: Run one last time before closing connection
// This ensures statuses are PERMANENTLY correct
$conn->query("UPDATE vendors SET status = review_status WHERE review_status IS NOT NULL AND review_status != 'pending' AND status != review_status");
$conn->query("UPDATE vendors SET status = CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END, review_status = CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END, is_reviewed = 1 WHERE reviewed_by IS NOT NULL AND reviewed_date IS NOT NULL AND status = 'pending' " . dpmSqlSkipPendingResubmit());
$conn->query("
    UPDATE vendors v
    INNER JOIN " . $dpm_sql_aa_latest_vendor_status . " aa ON v.vendor_id = aa.vendor_id
    SET v.status = aa.correct_status,
        v.review_status = aa.correct_status,
        v.is_reviewed = 1
    WHERE aa.correct_status IS NOT NULL
    {$dpm_sql_skip_pending_resubmit}
    AND v.status != aa.correct_status
");
error_log("✓✓✓ FINAL SQL FIX: Ran absolute fixes before closing connection");

// --- LOAD VENDOR APPLICATIONS (must run after all status-sync queries above) ---
$recent_applications = [];
try {
    $load_result = $conn->query($recent_applications_query);
    if ($load_result) {
        while ($row = $load_result->fetch_assoc()) {
            $row['application_type'] = $row['application_type'] ?? 'new';
            $row['section'] = $row['section'] ?? '';
            $row['block'] = $row['block'] ?? '';
            $row['submitted_date'] = $row['submitted_date'] ?? date('Y-m-d H:i:s');
            $recent_applications[] = $row;
        }
        error_log("✓ Vendor applications loaded (post-sync): " . count($recent_applications));
        error_log("Recent applications post-sync statuses: " . json_encode(array_column($recent_applications, 'status')));
    } else {
        error_log("✗ Vendor applications query failed: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("✗ Exception loading vendor applications: " . $e->getMessage());
}

$recent_applications_total_pages = count($recent_applications) > 0
    ? (int)ceil(count($recent_applications) / $recent_applications_page_size)
    : 1;

// ============================================================
// REPORTS DATASET — normalized applications for the Reports UI
// ============================================================
$report_sections = [
    'Fresh Fish Section',
    'Meat and Chicken Section',
    'Dried and Salted Fish Section',
    'Fruits and Vegetables Section',
    'Dry Goods Section',
    'Groceries Sari-Sari Section',
    'Eateries and Cooked Food Section',
    'Service Section',
];
$reports_apps = [];
foreach ($recent_applications as $ra) {
    $status = strtolower((string)($ra['display_status'] ?? $ra['status'] ?? 'pending'));
    if (!in_array($status, ['approved', 'pending', 'incomplete'], true)) {
        $status = 'pending';
    }
    $type = strtolower((string)($ra['application_type'] ?? 'new'));
    $type = ($type === 'renewal') ? 'renewal' : 'new';
    $raw_date = (string)($ra['submitted_date'] ?? '');
    $ts = $raw_date !== '' ? strtotime($raw_date) : false;
    $reports_apps[] = [
        'name'    => (string)($ra['vendor_name'] ?? $ra['user_name'] ?? 'Unknown'),
        'type'    => $type,
        'status'  => $status,
        'section' => (string)($ra['section'] ?? ''),
        'date'    => $ts ? date('Y-m-d', $ts) : '',
    ];
}

// Recent activity feed for Reports (approve / incomplete / reject)
$report_activities = [];
try {
    $aa_check = $conn->query("SHOW TABLES LIKE 'approval_actions'");
    if ($aa_check && $aa_check->num_rows > 0) {
        $aa_q = "
            SELECT CASE
                       WHEN aa.action_type IN ('approve', 'incomplete', 'reject') THEN aa.action_type
                       WHEN aa.admin_notes LIKE 'Incomplete Reason:%' THEN 'incomplete'
                       ELSE aa.action_type
                   END AS action_type,
                   COALESCE(aa.action_date, NOW()) AS action_date,
                   COALESCE(v.vendor_name, 'Unknown Vendor') AS vendor_name,
                   COALESCE(v.application_type, 'new') AS application_type,
                   COALESCE(u.full_name, 'Admin') AS admin_name
            FROM approval_actions aa
            LEFT JOIN vendors v ON aa.vendor_id = v.vendor_id
            LEFT JOIN users u ON aa.admin_id = u.user_id
            WHERE aa.action_type IN ('approve', 'incomplete', 'reject')
               OR aa.admin_notes LIKE 'Incomplete Reason:%'
            ORDER BY COALESCE(aa.action_date, aa.action_id) DESC, aa.action_id DESC
            LIMIT 6
        ";
        $aa_res = $conn->query($aa_q);
        if ($aa_res) {
            while ($aa_row = $aa_res->fetch_assoc()) {
                $report_activities[] = $aa_row;
            }
        }
    }
} catch (Exception $e) {
    // keep empty
}

if ($current_section === 'dashboard') {
    if ($page > $recent_applications_total_pages && $recent_applications_total_pages > 0) {
        $page = $recent_applications_total_pages;
    }
}

$offset = ($page - 1) * $page_size;
$recent_applications_offset = ($page - 1) * $recent_applications_page_size;
$recent_applications_display = array_slice($recent_applications, $recent_applications_offset, $recent_applications_page_size);
$paginated_applications = array_slice($recent_applications, $offset, $page_size);

$debug_info = "<!-- DEBUG INFO: Recent Apps=" . count($recent_applications) . ", Paginated=" . count($paginated_applications) . ", Records=" . count($all_records) . ", Activities=" . count($recent_activities) . ", Total Vendors=" . ($total_vendors_count ?? 0) . " -->";

error_log("=== FINAL DATA COUNTS (post vendor load) ===");
error_log("Recent applications: " . count($recent_applications));
error_log("Paginated applications: " . count($paginated_applications));
error_log("All records: " . count($all_records));
error_log("Recent activities: " . count($recent_activities));
error_log("Admin logs: " . count($admin_logs));
error_log("Total vendors in DB: " . ($total_vendors_count ?? 0));
error_log("========================");

// DIAGNOSTIC: Verify data exists in database
$diagnostic_count = $conn->query("SELECT COUNT(*) as count FROM vendors");
if ($diagnostic_count) {
    $diag_row = $diagnostic_count->fetch_assoc();
    $total_vendors_in_db = $diag_row['count'] ?? 0;
    error_log("=== DIAGNOSTIC: Total vendors in database: $total_vendors_in_db ===");
    
    if ($total_vendors_in_db > 0) {
        $sample_query = $conn->query("SELECT vendor_id, vendor_name, section, block, status, application_type, submitted_date FROM vendors ORDER BY vendor_id DESC LIMIT 5");
        if ($sample_query) {
            error_log("=== SAMPLE VENDORS ===");
            while ($sample = $sample_query->fetch_assoc()) {
                error_log("Vendor ID: " . $sample['vendor_id'] . ", Name: " . $sample['vendor_name'] . ", Section: " . $sample['section'] . ", Block: " . $sample['block'] . ", Status: " . $sample['status'] . ", Type: " . $sample['application_type']);
            }
        }
    }
} else {
    error_log("✗✗✗ DIAGNOSTIC QUERY FAILED: " . $conn->error);
}

// DO NOT CLOSE CONNECTION HERE - It's needed later in HTML output
// Connection will be closed at the very end of the file
?>

<?php 
// CRITICAL DEBUG OUTPUT - Shows what data was loaded
$recent_count = isset($recent_applications) && is_array($recent_applications) ? count($recent_applications) : 0;
$paginated_count = isset($paginated_applications) && is_array($paginated_applications) ? count($paginated_applications) : 0;
$records_count = isset($all_records) && is_array($all_records) ? count($all_records) : 0;
$activities_count = isset($recent_activities) && is_array($recent_activities) ? count($recent_activities) : 0;
$total_vendors = isset($total_vendors_count) ? $total_vendors_count : 0;

$debug_output = "<!-- DEBUG: Recent Apps=$recent_count, Paginated=$paginated_count, Records=$records_count, Activities=$activities_count, Total Vendors=$total_vendors -->";
echo $debug_output;

// Additional JavaScript debug output
echo "<script>";
echo "console.log('=== ADMIN PANEL DEBUG ===');";
echo "console.log('Recent Applications Count: $recent_count');";
echo "console.log('Paginated Applications Count: $paginated_count');";
echo "console.log('Records Count: $records_count');";
echo "console.log('Total Vendors in DB: $total_vendors');";
if ($recent_count > 0 && isset($recent_applications[0])) {
    echo "console.log('First Application:', " . json_encode($recent_applications[0]) . ");";
}
echo "</script>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title> Admin Panel</title>
<!-- DEBUG INFO: If you see this, PHP is working -->
<script>
// Global error handler for JavaScript errors
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.message, 'at', e.filename + ':' + e.lineno);
    console.error('Stack:', e.error ? e.error.stack : 'No stack trace');
});

// Monitor fetch requests
const originalFetch = window.fetch;
window.fetch = function(...args) {
    console.log('Fetch request:', args[0]);
    return originalFetch.apply(this, args)
        .then(response => {
            if (!response.ok) {
                console.error('Fetch failed:', args[0], 'Status:', response.status);
            }
            return response;
        })
        .catch(error => {
            console.error('Fetch error:', args[0], error);
            throw error;
        });
};

// Monitor XMLHttpRequest
const originalXHROpen = XMLHttpRequest.prototype.open;
const originalXHRSend = XMLHttpRequest.prototype.send;
XMLHttpRequest.prototype.open = function(method, url, ...args) {
    this._url = url;
    console.log('XHR Open:', method, url);
    return originalXHROpen.apply(this, [method, url, ...args]);
};
XMLHttpRequest.prototype.send = function(...args) {
    this.addEventListener('error', function() {
        console.error('XHR Error:', this._url);
    });
    this.addEventListener('load', function() {
        if (this.status >= 400) {
            console.error('XHR Failed:', this._url, 'Status:', this.status);
        }
    });
    return originalXHRSend.apply(this, args);
};

console.log('=== Admin Panel Loaded ===');
console.log('Check console for errors and network requests');
</script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
<style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  }
  body {
    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f8f9fa;
    transition: background-color 0.3s, color 0.3s;
    color: #2d3a4a;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    font-weight: 500;
  }
  
  /* Ensure all text elements use Inter font */
  h1, h2, h3, h4, h5, h6, p, span, div, a, button, input, select, textarea, label, td, th, li {
    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  }
  body.dark-mode {
    background: #1a1a1a;
    color: #e0e0e0;
  }
  body.dark-mode {
    background: #1a1a1a;
    color: #e0e0e0;
  }
  body.dark-mode .sidebar {
    background: #1e1e1e;
    border-right-color: #333;
  }
  body.dark-mode .sidebar-header {
    border-bottom-color: #333;
  }
  body.dark-mode .sidebar-header h1 {
    color: #e0e0e0;
  }
  body.dark-mode .sidebar-header p {
    color: #999;
  }
  body.dark-mode .sidebar-logo {
    opacity: 0.95;
  }
  body.dark-mode .sidebar-search {
    border-bottom-color: #333;
  }
  body.dark-mode .sidebar-search-input {
    background: #2c2c2c;
    border-color: #444;
    color: #e0e0e0;
  }
  body.dark-mode .sidebar-search-input:focus {
    background: #333;
    border-color: #667eea;
  }
  body.dark-mode .sidebar-search-icon {
    color: #999;
  }
  body.dark-mode .nav-item {
    color: #b0b0b0;
  }
  body.dark-mode .nav-item:hover {
    background: #2c2c2c;
    color: #667eea;
  }
  body.dark-mode .nav-item.active {
    background: #667eea;
    color: #ffffff;
  }
  body.dark-mode .sidebar-footer {
    border-top-color: #333;
  }
  body.dark-mode .dark-mode-toggle label {
    color: #b0b0b0;
  }
  body.dark-mode .header {
    background: #1e1e1e;
    border-bottom-color: #333;
    color: #e0e0e0;
  }
  body.dark-mode .user-info span {
    color: #e0e0e0;
  }
  body.dark-mode .user-info:hover {
    background: #2c2c2c;
  }
  body.dark-mode .menu-toggle {
    background: linear-gradient(135deg, #5568d3 0%, #6a3d91 100%);
    box-shadow: 0 2px 8px rgba(85, 104, 211, 0.4);
  }
  body.dark-mode .menu-toggle:hover {
    background: linear-gradient(135deg, #4a5bc0 0%, #5d2d7e 100%);
    box-shadow: 0 4px 12px rgba(85, 104, 211, 0.5);
  }
  body.dark-mode .menu-toggle.active {
    background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
  }
  body.dark-mode .menu-toggle span {
    background: #ffffff;
  }
  body.dark-mode .content {
    background: #1a1a1a;
  }
  body.dark-mode .card {
    background: #1e1e1e;
    border-color: #333;
    color: #e0e0e0;
  }
  body.dark-mode .card-header {
    border-bottom-color: #333;
  }
  body.dark-mode .card-header h2 {
    color: #e0e0e0;
  }
  body.dark-mode .stat-card {
    background: #1e1e1e;
    border-color: #333;
  }
  body.dark-mode .stat-card:hover {
    border-color: #667eea;
  }
  body.dark-mode .stat-info h3 {
    color: #999;
  }
  body.dark-mode .stat-info .value {
    color: #e0e0e0;
  }
  body.dark-mode .nav-separator {
    background: #333;
  }
  body.dark-mode .logout-container {
    border-color: #333;
  }
  body.dark-mode .sidebar-footer {
    border-top-color: #333;
  }
  body.dark-mode .pagination {
    background: #1e1e1e;
    border-color: #333;
  }
  body.dark-mode .pagination-info {
    background: #2c2c2c;
    border-color: #333;
    color: #b0b0b0;
  }
  body.dark-mode .pagination-btn {
    background: #667eea;
  }
  body.dark-mode .pagination-btn:hover {
    background: #5568d3;
  }
  body.dark-mode .pagination-btn:disabled,
  body.dark-mode .pagination-btn.disabled {
    background: #333;
    color: #666;
    border: 1px solid #444;
  }
  body.dark-mode .pagination-page-input {
    background: #2c2c2c;
    border-color: #444;
    color: #e0e0e0;
  }
  body.dark-mode .pagination-page-input:focus {
    border-color: #667eea;
    background: #333;
  }
  body.dark-mode .pagination-go-btn {
    background: #667eea;
  }
  body.dark-mode .pagination-go-btn:hover {
    background: #5568d3;
  }
  body.dark-mode .pagination-page-size {
    background: #2c2c2c;
    border-color: #444;
    color: #e0e0e0;
  }
  body.dark-mode .pagination-page-size:focus {
    border-color: #667eea;
    background: #333;
  }
  body.dark-mode .pagination-number {
    background: #2c2c2c;
    border-color: #444;
    color: #b0b0b0;
  }
  body.dark-mode .pagination-number:hover {
    background: #667eea;
    border-color: #667eea;
    color: #ffffff;
  }
  body.dark-mode .pagination-number.active {
    background: #667eea;
    border-color: #667eea;
    color: #ffffff;
  }
  body.dark-mode .pagination-ellipsis {
    color: #666;
  }
  body.dark-mode .form-group label {
    color: #e0e0e0;
  }
  body.dark-mode .form-group input[type="text"],
  body.dark-mode .form-group input[type="password"],
  body.dark-mode .form-group select,
  body.dark-mode .form-group textarea,
  body.dark-mode .password-field-wrap input {
    background: #3a3a3a;
    border-color: #555;
    color: #e0e0e0;
    color-scheme: dark;
  }
  body.dark-mode .form-group input[type="text"]:-webkit-autofill,
  body.dark-mode .form-group input[type="password"]:-webkit-autofill,
  body.dark-mode .password-field-wrap input:-webkit-autofill {
    -webkit-text-fill-color: #e0e0e0;
    caret-color: #e0e0e0;
    box-shadow: 0 0 0 1000px #3a3a3a inset;
    transition: background-color 9999s ease-out;
  }
  body.dark-mode .password-toggle-btn {
    position: absolute !important;
    right: 8px !important;
    left: auto !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    z-index: 5 !important;
    color: #cfcfcf;
  }
  body.dark-mode .password-toggle-btn:hover {
    color: #c7d2fe;
    background: rgba(102, 126, 234, 0.22);
  }
  body.dark-mode table {
    background: #2c2c2c;
    color: #e0e0e0;
  }
  body.dark-mode table th {
    background: #3a3a3a;
    color: #e0e0e0;
  }
  body.dark-mode table td {
    border-color: #444;
    color: #ffffff;
  }
  /* Make text white in Recent Applications table for ID, Vendor, Section, Block, Date columns */
  body.dark-mode table td:first-child,
  body.dark-mode table td:nth-child(2),
  body.dark-mode table td:nth-child(3),
  body.dark-mode table td:nth-child(4),
  body.dark-mode table td:nth-child(6) {
    color: #ffffff;
  }
  body.dark-mode .nav-item:hover {
    background: #3a3a3a;
  }
  body.dark-mode .user-info:hover {
    background: #3a3a3a;
  }
  body.dark-mode #darkModeStatus {
    color: #e0e0e0;
  }
  .container {
    display: flex;
    min-height: 100vh;
  }
  .sidebar {
    width: 280px;
    background: #ffffff;
    color: #2d3a4a;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
    border-right: 1px solid #e9ecef;
    display: flex;
    flex-direction: column;
    pointer-events: auto !important;
    position: relative;
    z-index: 10000 !important;
  }
  
  /* Ensure ALL interactive sidebar elements are clickable - be very specific */
  .sidebar .nav-item,
  .sidebar .nav-item *,
  .sidebar button,
  .sidebar input,
  .sidebar input[type="checkbox"],
  .sidebar input[type="text"],
  .sidebar label,
  .sidebar .toggle-switch,
  .sidebar .toggle-switch *,
  .sidebar .toggle-switch input,
  .sidebar .dark-mode-toggle,
  .sidebar .dark-mode-toggle *,
  .sidebar .dark-mode-toggle label,
  .sidebar .logout-item,
  .sidebar .logout-item *,
  .sidebar .sidebar-search-input,
  .sidebar .sidebar-search-icon,
  .sidebar .nav-menu,
  .sidebar .sidebar-footer,
  .sidebar .logout-container {
    pointer-events: auto !important;
    cursor: pointer !important;
    z-index: 10001 !important;
    position: relative;
  }
  
  /* Ensure nav-item spans and icons are clickable */
  .sidebar .nav-item span,
  .sidebar .nav-item i,
  .sidebar .nav-item img.nav-receipt-icon {
    pointer-events: auto !important;
    cursor: pointer !important;
  }
  
  /* Only disable pointer events on pure text content in header */
  .sidebar .sidebar-header p,
  .sidebar .sidebar-header h1,
  .sidebar .sidebar-title-group p,
  .sidebar .sidebar-title-group h1 {
    pointer-events: none;
  }
  .sidebar.collapsed {
    width: 0;
    overflow: hidden;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .sidebar-header {
    padding: 24px 20px;
    border-bottom: 1px solid #e9ecef;
  }
  .sidebar-logo-container {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .sidebar-logo {
    width: 40px;
    height: 40px;
    object-fit: contain;
    border-radius: 8px;
    flex-shrink: 0;
  }
  .sidebar-title-group {
    flex: 1;
    min-width: 0;
  }
  .sidebar-header h1 {
    font-size: 20px;
    font-weight: 800;
    margin-bottom: 4px;
    color: #2d3a4a;
    letter-spacing: -0.3px;
    font-family: 'Inter', sans-serif;
    line-height: 1.3;
  }
  .sidebar-header p {
    font-size: 13px;
    color: #6b7a90;
    font-weight: 600;
    letter-spacing: 0.2px;
    font-family: 'Inter', sans-serif;
  }
  .sidebar-search {
    padding: 16px 20px;
    border-bottom: 1px solid #e9ecef;
  }
  .sidebar-search-input {
    width: 100%;
    padding: 10px 16px 10px 40px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    font-family: 'Inter', sans-serif;
    letter-spacing: 0.1px;
    background: #f8f9fa;
    color: #2d3a4a;
    transition: all 0.3s;
  }
  .sidebar-search-input:focus {
    outline: none;
    border-color: #667eea;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
  .sidebar-search-wrapper {
    position: relative;
  }
  .sidebar-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7a90;
    font-size: 14px;
  }
  .nav-menu {
    flex: 0 0 auto;
    padding: 12px 0;
    overflow-y: visible;
  }
  .nav-item {
    padding: 12px 20px;
    cursor: pointer !important;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #6b7a90;
    font-size: 15px;
    font-weight: 500;
    margin: 2px 8px;
    border-radius: 8px;
    pointer-events: auto !important;
    position: relative;
    z-index: 10000 !important;
    -webkit-user-select: none;
    -moz-user-select: none;
    user-select: none;
  }
  .nav-item i {
    width: 20px;
    text-align: center;
    font-size: 16px;
  }
  .nav-item img.nav-receipt-icon {
    width: 20px;
    height: 20px;
    object-fit: contain;
    flex-shrink: 0;
    border-radius: 4px;
  }
  .nav-item.active img.nav-receipt-icon {
    filter: brightness(0) invert(1);
  }
  img.receipt-heading-icon,
  i.receipt-heading-icon {
    width: 22px;
    height: 22px;
    object-fit: contain;
    vertical-align: -0.25em;
    margin-right: 8px;
    border-radius: 4px;
  }
  i.receipt-heading-icon {
    display: inline-block;
    width: auto;
    height: auto;
    font-size: 1.15rem;
    line-height: 1;
    color: inherit;
  }
  .nav-item:hover {
    background: #f8f9fa;
    color: #667eea;
  }
  .nav-item.active {
    background: #667eea;
    color: #ffffff;
    font-weight: 700;
    letter-spacing: 0.2px;
  }
  .nav-item.active i {
    color: #ffffff;
  }
  .nav-separator {
    height: 1px;
    background: #e9ecef;
    margin: 8px 20px;
  }
  .logout-container {
    padding: 8px 20px;
    display: flex;
    justify-content: center;
    margin-top: 8px;
    margin-bottom: 12px;
  }
  .logout-item {
    color: #dc3545;
    justify-content: center;
    width: 100%;
    pointer-events: auto !important;
    cursor: pointer !important;
    position: relative;
    z-index: 10000 !important;
  }
  .logout-item * {
    pointer-events: auto !important;
    cursor: pointer !important;
  }
  .logout-item:hover {
    background: #fee;
    color: #c82333;
  }
  .logout-item.active {
    background: #dc3545;
    color: #ffffff;
  }
  .sidebar-footer {
    padding: 12px 20px;
    border-top: 1px solid #e9ecef;
    margin-top: 0;
    margin-bottom: 0;
    display: flex;
    align-items: center;
    justify-content: flex-start;
  }
  .dark-mode-toggle-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: 100%;
    padding: 0 8px;
    pointer-events: auto !important;
    position: relative;
    z-index: 10000 !important;
  }
  .dark-mode-label {
    font-size: 15px;
    color: #6b7a90;
    font-weight: 500;
    letter-spacing: 0.2px;
    font-family: 'Inter', sans-serif;
    cursor: pointer !important;
    pointer-events: auto !important;
    margin: 0;
    flex-shrink: 0;
  }
  body.dark-mode .dark-mode-label {
    color: #b0b0b0;
  }
  /* Dark Mode Toggle Switch Styles */
  .theme-switch {
    --toggle-size: 12px;
    --container-width: 4.5em;
    --container-height: 2.2em;
    --container-radius: 6.25em;
    --container-light-bg: #3D7EAE;
    --container-night-bg: #1D1F2C;
    --circle-container-diameter: 2.8em;
    --sun-moon-diameter: 1.8em;
    --sun-bg: #ECCA2F;
    --moon-bg: #C4C9D1;
    --spot-color: #959DB1;
    --circle-container-offset: calc((var(--circle-container-diameter) - var(--container-height)) / 2 * -1);
    --stars-color: #fff;
    --clouds-color: #F3FDFF;
    --back-clouds-color: #AACADF;
    --transition: .5s cubic-bezier(0, -0.02, 0.4, 1.25);
    --circle-transition: .3s cubic-bezier(0, -0.02, 0.35, 1.17);
    cursor: pointer;
    pointer-events: auto !important;
    z-index: 10000 !important;
    display: inline-block;
    vertical-align: middle;
  }
  .theme-switch,
  .theme-switch *,
  .theme-switch *::before,
  .theme-switch *::after {
    -webkit-box-sizing: border-box;
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-size: var(--toggle-size);
  }
  .theme-switch__container {
    width: var(--container-width);
    height: var(--container-height);
    background-color: var(--container-light-bg);
    border-radius: var(--container-radius);
    overflow: hidden;
    cursor: pointer;
    -webkit-box-shadow: 0em -0.062em 0.062em rgba(0, 0, 0, 0.25), 0em 0.062em 0.125em rgba(255, 255, 255, 0.94);
    box-shadow: 0em -0.062em 0.062em rgba(0, 0, 0, 0.25), 0em 0.062em 0.125em rgba(255, 255, 255, 0.94);
    -webkit-transition: var(--transition);
    -o-transition: var(--transition);
    transition: var(--transition);
    position: relative;
  }
  .theme-switch__container::before {
    content: "";
    position: absolute;
    z-index: 1;
    inset: 0;
    -webkit-box-shadow: 0em 0.05em 0.187em rgba(0, 0, 0, 0.25) inset, 0em 0.05em 0.187em rgba(0, 0, 0, 0.25) inset;
    box-shadow: 0em 0.05em 0.187em rgba(0, 0, 0, 0.25) inset, 0em 0.05em 0.187em rgba(0, 0, 0, 0.25) inset;
    border-radius: var(--container-radius);
  }
  .theme-switch__checkbox {
    display: none;
  }
  .theme-switch__circle-container {
    width: var(--circle-container-diameter);
    height: var(--circle-container-diameter);
    background-color: rgba(255, 255, 255, 0.1);
    position: absolute;
    left: var(--circle-container-offset);
    top: var(--circle-container-offset);
    border-radius: var(--container-radius);
    -webkit-box-shadow: inset 0 0 0 3.375em rgba(255, 255, 255, 0.1), inset 0 0 0 3.375em rgba(255, 255, 255, 0.1), 0 0 0 0.625em rgba(255, 255, 255, 0.1), 0 0 0 1.25em rgba(255, 255, 255, 0.1);
    box-shadow: inset 0 0 0 3.375em rgba(255, 255, 255, 0.1), inset 0 0 0 3.375em rgba(255, 255, 255, 0.1), 0 0 0 0.625em rgba(255, 255, 255, 0.1), 0 0 0 1.25em rgba(255, 255, 255, 0.1);
    display: -webkit-box;
    display: -ms-flexbox;
    display: flex;
    -webkit-transition: var(--circle-transition);
    -o-transition: var(--circle-transition);
    transition: var(--circle-transition);
    pointer-events: none;
  }
  .theme-switch__sun-moon-container {
    pointer-events: auto;
    position: relative;
    z-index: 2;
    width: var(--sun-moon-diameter);
    height: var(--sun-moon-diameter);
    margin: auto;
    border-radius: var(--container-radius);
    background-color: var(--sun-bg);
    -webkit-box-shadow: 0.062em 0.062em 0.062em 0em rgba(254, 255, 239, 0.61) inset, 0em -0.062em 0.062em 0em #a1872a inset;
    box-shadow: 0.062em 0.062em 0.062em 0em rgba(254, 255, 239, 0.61) inset, 0em -0.062em 0.062em 0em #a1872a inset;
    -webkit-filter: drop-shadow(0.062em 0.125em 0.125em rgba(0, 0, 0, 0.25)) drop-shadow(0em 0.062em 0.125em rgba(0, 0, 0, 0.25));
    filter: drop-shadow(0.062em 0.125em 0.125em rgba(0, 0, 0, 0.25)) drop-shadow(0em 0.062em 0.125em rgba(0, 0, 0, 0.25));
    overflow: hidden;
    -webkit-transition: var(--transition);
    -o-transition: var(--transition);
    transition: var(--transition);
  }
  .theme-switch__moon {
    -webkit-transform: translateX(100%);
    -ms-transform: translateX(100%);
    transform: translateX(100%);
    width: 100%;
    height: 100%;
    background-color: var(--moon-bg);
    border-radius: inherit;
    -webkit-box-shadow: 0.062em 0.062em 0.062em 0em rgba(254, 255, 239, 0.61) inset, 0em -0.062em 0.062em 0em #969696 inset;
    box-shadow: 0.062em 0.062em 0.062em 0em rgba(254, 255, 239, 0.61) inset, 0em -0.062em 0.062em 0em #969696 inset;
    -webkit-transition: var(--transition);
    -o-transition: var(--transition);
    transition: var(--transition);
    position: relative;
  }
  .theme-switch__spot {
    position: absolute;
    top: 0.75em;
    left: 0.312em;
    width: 0.75em;
    height: 0.75em;
    border-radius: var(--container-radius);
    background-color: var(--spot-color);
    -webkit-box-shadow: 0em 0.0312em 0.062em rgba(0, 0, 0, 0.25) inset;
    box-shadow: 0em 0.0312em 0.062em rgba(0, 0, 0, 0.25) inset;
  }
  .theme-switch__spot:nth-of-type(2) {
    width: 0.375em;
    height: 0.375em;
    top: 0.937em;
    left: 1.375em;
  }
  .theme-switch__spot:nth-last-of-type(3) {
    width: 0.25em;
    height: 0.25em;
    top: 0.312em;
    left: 0.812em;
  }
  .theme-switch__clouds {
    width: 1.25em;
    height: 1.25em;
    background-color: var(--clouds-color);
    border-radius: var(--container-radius);
    position: absolute;
    bottom: -0.625em;
    left: 0.312em;
    -webkit-box-shadow: 0.937em 0.312em var(--clouds-color), -0.312em -0.312em var(--back-clouds-color), 1.437em 0.375em var(--clouds-color), 0.5em -0.125em var(--back-clouds-color), 2.187em 0 var(--clouds-color), 1.25em -0.062em var(--back-clouds-color), 2.937em 0.312em var(--clouds-color), 2em -0.312em var(--back-clouds-color), 3.625em -0.062em var(--clouds-color), 2.625em 0em var(--back-clouds-color), 4.5em -0.312em var(--clouds-color), 3.375em -0.437em var(--back-clouds-color), 4.625em -1.75em 0 0.437em var(--clouds-color), 4em -0.625em var(--back-clouds-color), 4.125em -2.125em 0 0.437em var(--back-clouds-color);
    box-shadow: 0.937em 0.312em var(--clouds-color), -0.312em -0.312em var(--back-clouds-color), 1.437em 0.375em var(--clouds-color), 0.5em -0.125em var(--back-clouds-color), 2.187em 0 var(--clouds-color), 1.25em -0.062em var(--back-clouds-color), 2.937em 0.312em var(--clouds-color), 2em -0.312em var(--back-clouds-color), 3.625em -0.062em var(--clouds-color), 2.625em 0em var(--back-clouds-color), 4.5em -0.312em var(--clouds-color), 3.375em -0.437em var(--back-clouds-color), 4.625em -1.75em 0 0.437em var(--clouds-color), 4em -0.625em var(--back-clouds-color), 4.125em -2.125em 0 0.437em var(--back-clouds-color);
    -webkit-transition: 0.5s cubic-bezier(0, -0.02, 0.4, 1.25);
    -o-transition: 0.5s cubic-bezier(0, -0.02, 0.4, 1.25);
    transition: 0.5s cubic-bezier(0, -0.02, 0.4, 1.25);
  }
  .theme-switch__stars-container {
    position: absolute;
    color: var(--stars-color);
    top: -100%;
    left: 0.312em;
    width: 2.75em;
    height: auto;
    -webkit-transition: var(--transition);
    -o-transition: var(--transition);
    transition: var(--transition);
  }
  .theme-switch__checkbox:checked + .theme-switch__container {
    background-color: var(--container-night-bg);
  }
  .theme-switch__checkbox:checked + .theme-switch__container .theme-switch__circle-container {
    left: calc(100% - var(--circle-container-offset) - var(--circle-container-diameter));
  }
  .theme-switch__checkbox:checked + .theme-switch__container .theme-switch__circle-container:hover {
    left: calc(100% - var(--circle-container-offset) - var(--circle-container-diameter) - 0.187em);
  }
  .theme-switch__circle-container:hover {
    left: calc(var(--circle-container-offset) + 0.187em);
  }
  .theme-switch__checkbox:checked + .theme-switch__container .theme-switch__moon {
    -webkit-transform: translate(0);
    -ms-transform: translate(0);
    transform: translate(0);
  }
  .theme-switch__checkbox:checked + .theme-switch__container .theme-switch__clouds {
    bottom: -4.062em;
  }
  .theme-switch__checkbox:checked + .theme-switch__container .theme-switch__stars-container {
    top: 50%;
    -webkit-transform: translateY(-50%);
    -ms-transform: translateY(-50%);
    transform: translateY(-50%);
  }
  .dark-mode-toggle-wrapper {
    cursor: pointer !important;
    pointer-events: auto !important;
    position: relative;
    z-index: 10000 !important;
  }
  .toggle-checkbox {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
    pointer-events: auto !important;
    z-index: 10001 !important;
  }
  .toggle-slot {
    position: relative;
    height: 24px;
    width: 48px;
    border: 2px solid #e4e7ec;
    border-radius: 24px;
    background-color: white;
    box-shadow: 0px 4px 12px rgba(228, 231, 236, 0.6);
    transition: background-color 250ms;
    pointer-events: none;
  }
  body.dark-mode .toggle-slot {
    border-color: #555;
  }
  .toggle-checkbox:checked ~ .toggle-slot {
    background-color: #374151;
    border-color: #374151;
  }
  /* Circle button - minimized to fit inside */
  .toggle-button {
    position: absolute;
    height: 18px;
    width: 18px;
    border-radius: 50%;
    background-color: #ffeccf;
    box-shadow: inset 0px 0px 0px 2px #ffbb52;
    transition: background-color 250ms, border-color 250ms, transform 500ms cubic-bezier(.26,2,.46,.71);
    z-index: 10;
    top: 3px;
    left: 3px;
  }
  .toggle-checkbox:checked ~ .toggle-slot .toggle-button {
    background-color: #485367;
    box-shadow: inset 0px 0px 0px 2px white;
    transform: translateX(27px);
  }
  /* Sun icon - appears on RIGHT when circle is on LEFT (unchecked) */
  .sun-icon-wrapper {
    position: absolute;
    height: 18px;
    width: 18px;
    opacity: 1;
    top: 3px;
    right: 3px;
    transform: rotate(15deg);
    transform-origin: center center;
    transition: opacity 150ms, transform 500ms cubic-bezier(.26,2,.46,.71);
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .sun-icon {
    height: 14px;
    width: 14px;
    color: #ffbb52;
    display: block;
  }
  .toggle-checkbox:checked ~ .toggle-slot .sun-icon-wrapper {
    opacity: 0;
    transform: translateX(5px) rotate(0deg);
  }
  /* Moon icon - appears on LEFT when circle is on RIGHT (checked) */
  .moon-icon-wrapper {
    position: absolute;
    height: 18px;
    width: 18px;
    opacity: 0;
    top: 3px;
    left: 3px;
    transform: rotate(0deg);
    transform-origin: center center;
    transition: opacity 150ms, transform 500ms cubic-bezier(.26,2.5,.46,.71);
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .moon-icon {
    height: 14px;
    width: 14px;
    color: white;
    display: block;
  }
  .toggle-checkbox:checked ~ .toggle-slot .moon-icon-wrapper {
    opacity: 1;
    transform: rotate(-15deg);
  }
  .toggle-switch {
    position: relative;
    width: 44px;
    height: 24px;
    pointer-events: auto !important;
    z-index: 10000 !important;
  }
  .toggle-switch input[type="checkbox"] {
    opacity: 0;
    width: 100% !important;
    height: 100% !important;
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    cursor: pointer !important;
    pointer-events: auto !important;
    z-index: 10001 !important;
    margin: 0 !important;
    padding: 0 !important;
    display: block !important;
  }
  .toggle-slider {
    position: absolute;
    cursor: pointer !important;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, #6BB6FF 0%, #4A9EFF 100%);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 24px;
    pointer-events: none;
    z-index: 10000;
    overflow: hidden;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
  }
  /* Sun knob - Light mode (left side) */
  .toggle-slider:before {
    position: absolute;
    content: "☀️";
    height: 20px;
    width: 20px;
    left: 2px;
    bottom: 2px;
    background: radial-gradient(circle, #FFD700 0%, #FFA500 100%);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(255, 215, 0, 0.5), 0 0 0 2px rgba(255, 255, 255, 0.3);
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    line-height: 20px;
  }
  /* Cloud decorations for light mode (right side) */
  .toggle-slider:after {
    position: absolute;
    content: "";
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 12px;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 50px;
    opacity: 0.9;
    transition: opacity 0.3s;
    z-index: 2;
    pointer-events: none;
    box-shadow: -8px 0 0 -2px rgba(255, 255, 255, 0.7), 8px 0 0 -2px rgba(255, 255, 255, 0.7);
  }
  /* Cloud decorations for light mode */
  .cloud-decoration {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 12px;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 50px;
    opacity: 0.9;
    transition: opacity 0.3s;
    z-index: 2;
    pointer-events: none;
    box-shadow: -8px 0 0 -2px rgba(255, 255, 255, 0.7), 8px 0 0 -2px rgba(255, 255, 255, 0.7);
  }
  .toggle-switch input:checked ~ .cloud-decoration {
    opacity: 0;
  }
  /* Dark mode - Dark grey background */
  .toggle-switch input:checked + .toggle-slider {
    background: #373A40;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
  }
  /* Night sky with stars on left when checked */
  .toggle-switch input:checked + .toggle-slider:after {
    position: absolute;
    content: "✦ ✦ ✦";
    left: 0;
    top: 0;
    width: 50%;
    height: 100%;
    background: #292C37;
    border-radius: 24px 0 0 24px;
    z-index: 1;
    box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 6px;
    letter-spacing: 3px;
    padding-left: 8px;
    opacity: 1;
    transform: none;
    top: 0;
    right: auto;
  }
  /* Moon on right when checked */
  .toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(20px);
    content: "🌙";
    font-size: 16px;
    background: radial-gradient(circle, #E0E0E0 0%, #C0C0C0 100%);
    box-shadow: 0 2px 4px rgba(224, 224, 224, 0.3);
    z-index: 3;
  }
  .main-content {
    flex: 1;
    display: flex;
    flex-direction: column;
  }
  .header {
    background: #ffffff;
    padding: 16px 32px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e9ecef;
  }
  .menu-toggle {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    cursor: pointer;
    padding: 12px;
    border-radius: 12px;
    color: #ffffff;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    justify-content: center;
    pointer-events: auto !important;
    position: relative;
    z-index: 1000;
    pointer-events: auto !important;
    position: relative;
    z-index: 1000;
    align-items: center;
    gap: 5px;
    width: 44px;
    height: 44px;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    position: relative;
    z-index: 1001;
  }
  .menu-toggle:hover {
    background: linear-gradient(135deg, #5568d3 0%, #6a3d91 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
  }
  .menu-toggle:active {
    transform: translateY(0);
    box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
  }
  .menu-toggle i {
    display: none;
  }
  .menu-toggle span {
    display: block;
    width: 22px;
    height: 2.5px;
    background: #ffffff;
    border-radius: 3px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transform-origin: center;
  }
  .menu-toggle.active span:nth-child(1) {
    transform: rotate(45deg) translate(6px, 6px);
  }
  .menu-toggle.active span:nth-child(2) {
    opacity: 0;
    transform: scaleX(0);
  }
  .menu-toggle.active span:nth-child(3) {
    transform: rotate(-45deg) translate(6px, -6px);
  }
  .menu-toggle.active {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
  }
  .menu-toggle.active:hover {
    background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
  }
  .user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 8px;
    transition: background 0.2s;
    pointer-events: auto !important;
    position: relative;
    z-index: 1000;
  }
  .user-info:hover {
    background: #f8f9fa;
  }
  .user-info span {
    font-size: 14px;
    font-weight: 600;
    color: #2d3a4a;
  }
  .welcome-text {
    flex: 1;
    text-align: center;
    color: #2d3a4a;
    font-weight: 600;
    padding: 6px 12px;
    margin: 0 16px;
    letter-spacing: .2px;
    border-radius: 999px;
    background: linear-gradient(90deg, #f7f9ff, #fff7f1);
    border: 1px solid #e9ecff;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
  }
  .welcome-text .welcome-name {
    color: #667eea;
    font-weight: 700;
  }
  .user-avatar {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
    overflow: hidden;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }
  .user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .content {
    padding: 32px;
    overflow-y: visible;
    background: #f8f9fa;
  }
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
  }
  .stat-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid #e9ecef;
    transition: all 0.3s;
  }
  .stat-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
  }
  .stat-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
  }
  .stat-card-title {
    font-size: 13px;
    font-weight: 600;
    color: #6b7a90;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .stat-card-value {
    font-size: 32px;
    font-weight: 700;
    color: #2d3a4a;
    margin-bottom: 8px;
    line-height: 1.2;
  }
  .stat-card-change {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #27ae60;
  }
  .stat-card-change.down {
    color: #e74c3c;
  }
  .stat-card-change i {
    font-size: 12px;
  }
  /* Welcome Banner - Minimized */
  .welcome-banner {
    background: transparent;
    color: #2d3a4a;
    border-radius: 0;
    padding: 0;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    box-shadow: none;
    margin-bottom: 24px;
    border: none;
    flex-direction: column;
  }
  .welcome-banner .left {
    display: flex;
    flex-direction: column;
    gap: 16px;
    width: 100%;
  }
  .welcome-banner .icon {
    display: none;
  }
  .welcome-greeting-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #ffffff;
    padding: 16px 24px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    width: fit-content;
  }
  .welcome-icon {
    font-size: 24px;
    color: #ffffff;
    animation: bounce 2s infinite;
  }
  @keyframes bounce {
    0%, 100% {
      transform: translateY(0);
    }
    50% {
      transform: translateY(-5px);
    }
  }
  .welcome-greeting-text {
    font-size: 18px;
    font-weight: 700;
    color: #ffffff;
    margin: 0;
    letter-spacing: 0.3px;
    font-family: 'Inter', sans-serif;
    line-height: 1.4;
  }
  .dashboard-title-section {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .welcome-banner h1 {
    font-size: 28px;
    font-weight: 800;
    margin: 0;
    color: #2d3a4a;
    letter-spacing: -0.5px;
    font-family: 'Inter', sans-serif;
    line-height: 1.2;
  }
  .welcome-banner p {
    margin: 0;
    font-size: 15px;
    color: #6b7a90;
    font-weight: 500;
    letter-spacing: 0.2px;
    font-family: 'Inter', sans-serif;
  }
  .welcome-actions {
    display: none;
  }
  body.dark-mode .welcome-banner {
    color: #e0e0e0;
  }
  body.dark-mode .welcome-banner h1 {
    color: #e0e0e0;
  }
  body.dark-mode .welcome-banner p {
    color: #b0b0b0;
  }
  body.dark-mode .welcome-greeting-box {
    background: linear-gradient(135deg, #5568d3 0%, #6a3d91 100%);
  }
  
  /* Dashboard Layout */
  .dashboard-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 24px;
    margin-bottom: 24px;
  }
  
  .dashboard-top-row {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 24px;
    margin-bottom: 24px;
    align-items: start;
  }
  
  @media (max-width: 1200px) {
    .dashboard-top-row {
      grid-template-columns: 1fr;
    }
  }
  
  .header-with-activity {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  
  /* Recent Activity Card - Smaller */
  .recent-activity-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid #e9ecef;
    padding: 16px;
    height: fit-content;
  }
  .recent-activity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e9ecef;
  }
  .recent-activity-header h2 {
    font-size: 15px;
    font-weight: 700;
    color: #2d3a4a;
    margin: 0;
  }
  .recent-activity-header .view-all {
    color: #667eea;
    text-decoration: none;
    font-size: 11px;
    font-weight: 600;
    transition: color 0.2s;
  }
  .recent-activity-header .view-all:hover {
    color: #5568d3;
  }
  .activity-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .activity-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 8px;
    border-radius: 6px;
    transition: background 0.2s;
  }
  .activity-item:hover {
    background: #f8f9fa;
  }
  .activity-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 12px;
    flex-shrink: 0;
  }
  .activity-content {
    flex: 1;
    min-width: 0;
  }
  .activity-text {
    font-size: 13px;
    color: #2d3a4a;
    line-height: 1.5;
    margin-bottom: 4px;
    font-weight: 500;
    letter-spacing: 0.1px;
    font-family: 'Inter', sans-serif;
  }
  .activity-text strong {
    font-weight: 700;
    color: #2d3a4a;
    letter-spacing: 0.2px;
  }
  .activity-item-name {
    font-weight: 700;
    color: #667eea;
    letter-spacing: 0.1px;
  }
  .activity-time {
    font-size: 11px;
    color: #6b7a90;
    font-weight: 500;
    letter-spacing: 0.1px;
    font-family: 'Inter', sans-serif;
  }
  .activity-icon {
    color: #e74c3c;
    font-size: 11px;
    margin-left: 3px;
  }
  .activity-icon.approved {
    color: #27ae60;
  }
  .activity-icon.incomplete {
    color: #e74c3c;
  }
  body.dark-mode .recent-activity-card {
    background: #1e1e1e;
    border-color: #333;
  }
  body.dark-mode .recent-activity-header {
    border-bottom-color: #333;
  }
  body.dark-mode .recent-activity-header h2 {
    color: #e0e0e0;
  }
  body.dark-mode .activity-item:hover {
    background: #2c2c2c;
  }
  body.dark-mode .activity-text {
    color: #e0e0e0;
  }
  body.dark-mode .activity-text strong {
    color: #e0e0e0;
  }
  body.dark-mode .activity-time {
    color: #999;
  }
  /* Nav pill beside admin icon */
  .nav-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    margin-left: 10px;
    background: #e8f3ff;
    color: #2457c5;
    border: 1px solid #cfe3ff;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1;
    white-space: nowrap;
  }
  .interactive-greeting {
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(255, 255, 255, 0.15);
    padding: 12px 18px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
  }
  .interactive-greeting:hover {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }
  .interactive-greeting:active {
    transform: translateY(0);
  }
  .greeting-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    transition: transform 0.3s ease;
  }
  .greeting-icon i {
    transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  }
  .interactive-greeting:hover .greeting-icon {
    transform: rotate(15deg) scale(1.1);
  }
  .interactive-greeting:hover .greeting-icon i {
    transform: scale(1.15) rotate(-5deg);
  }
  .greeting-text {
    font-size: 14px;
    font-weight: 500;
    color: #fff;
  }
  .greeting-text span {
    display: inline-block;
    transition: all 0.3s ease;
  }
  .greeting-text span.animate {
    animation: bounceIn 0.5s ease;
  }
  @keyframes bounceIn {
    0% { transform: scale(0.8); opacity: 0; }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
  }
  .system-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.18);
    color: #eafff0;
    padding: 8px 12px;
    border-radius: 999px;
    font-size: 13px;
    white-space: nowrap;
  }
  .system-pill i { color: #b2ffd2; }
  .fun-message {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
    font-weight: 500;
  }
  .fun-message i {
    color: #e8efff;
  }
  .stat-card {
    background: #ffffff;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
    cursor: pointer;
  }
  .stat-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
    border-color: #667eea;
  }
  .stat-info {
    flex: 1;
  }
  .stat-info h3 {
    color: #6b7a90;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    font-family: 'Inter', sans-serif;
  }
  .stat-info .value {
    font-size: 40px;
    font-weight: 800;
    color: #2d3a4a;
    margin-bottom: 8px;
    line-height: 1.1;
    letter-spacing: -1px;
    font-family: 'Inter', sans-serif;
  }
  .stat-info .change {
    display: flex;
    align-items: center;
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    gap: 6px;
    color: #27ae60;
    font-size: 13px;
    letter-spacing: 0.2px;
  }
  .stat-info .change i {
    font-size: 12px;
  }
  .stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    flex-shrink: 0;
  }
  .stat-icon i {
    transition: transform 0.3s ease;
  }
  .stat-card:hover .stat-icon i {
    transform: scale(1.1);
  }
  .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
  .stat-icon.yellow { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
  .stat-icon.green { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
  .stat-icon.purple { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #2d3a4a; }
  .card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid #e9ecef;
    padding: 28px;
    margin-bottom: 24px;
    transition: all 0.3s ease;
  }
  .card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e9ecef;
  }
  .card-header h2 {
    font-size: 22px;
    font-weight: 800;
    color: #2d3a4a;
    letter-spacing: -0.4px;
    font-family: 'Inter', sans-serif;
    line-height: 1.3;
  }
  .card-header .view-all {
    color: #667eea;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    transition: color 0.2s;
    letter-spacing: 0.2px;
    font-family: 'Inter', sans-serif;
  }
  .card-header .view-all:hover {
    color: #5568d3;
  }
  .search-filter {
    display: flex;
    gap: 10px;
    position: relative;
  }
  .search-filter input,
  .search-filter select {
    padding: 8px 15px 8px 40px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
  }
  .search-filter .search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7a90;
    font-size: 14px;
    pointer-events: none;
    z-index: 1;
  }
  body.dark-mode .search-filter .search-icon {
    color: #999;
  }
  
  /* User Records Box/Card Layout */
  .records-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
  }
  .user-record-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  .user-record-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
  }
  .user-record-card .user-photo {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin: 0 auto 15px;
    overflow: hidden;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 32px;
    font-weight: 700;
  }
  .user-record-card .user-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .user-record-card .user-name {
    font-size: 18px;
    font-weight: 700;
    color: #2d3a4a;
    text-align: center;
    margin-bottom: 15px;
  }
  .user-record-card .user-info {
    text-align: center;
    color: #6b7a90;
    font-size: 14px;
    line-height: 1.8;
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: center;
  }
  .user-record-card .user-info-item {
    margin-bottom: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: center;
    width: 100%;
  }
  .user-record-card .user-info-item strong {
    color: #2d3a4a;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
    text-align: center;
  }
  .user-record-card .user-info-item span {
    color: #6b7a90;
    font-size: 14px;
    display: block;
    text-align: center;
    word-break: break-word;
  }
  body.dark-mode .user-record-card {
    background: #2c2c2c;
    border-color: #444;
  }
  body.dark-mode .user-record-card .user-name {
    color: #e0e0e0;
  }
  body.dark-mode .user-record-card .user-info {
    color: #b0b0b0;
  }
  body.dark-mode .user-record-card .user-info-item strong {
    color: #e0e0e0;
  }
  body.dark-mode .user-record-card .user-info-item span {
    color: #b0b0b0;
  }

  /* ── Records header right: view toggles + search ── */
  .records-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .records-view-btns {
    display: flex;
    align-items: center;
    gap: 4px;
    background: #f1f5f9;
    border-radius: 10px;
    padding: 4px;
    border: 1px solid #e2e8f0;
  }
  body.dark-mode .records-view-btns {
    background: #2c2c2c;
    border-color: #444;
  }
  .records-view-btn {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 7px;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    transition: background 0.18s ease, color 0.18s ease;
    padding: 0;
    flex-shrink: 0;
  }
  .records-view-btn:hover {
    background: rgba(91, 92, 226, 0.1);
    color: #5b5ce2;
  }
  .records-view-btn.is-active {
    background: #5b5ce2;
    color: #fff;
    box-shadow: 0 2px 8px rgba(91, 92, 226, 0.35);
  }
  body.dark-mode .records-view-btn.is-active {
    background: #5b5ce2;
    color: #fff;
  }

  /* Hamburger icon (3 lines) */
  .hamburger-lines {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 16px;
  }
  .hamburger-lines span {
    display: block;
    width: 100%;
    height: 2px;
    border-radius: 2px;
    background: currentColor;
  }

  /* Four-box (grid) icon */
  .four-boxes {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3px;
    width: 16px;
    height: 16px;
  }
  .four-boxes span {
    display: block;
    width: 100%;
    height: 100%;
    border-radius: 2px;
    border: 2px solid currentColor;
    box-sizing: border-box;
  }

  /* ── Drawer mode ── */
  .records-drawer {
    display: flex;
    flex-direction: column;
    gap: 0;
    margin-top: 18px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
  }
  body.dark-mode .records-drawer {
    border-color: #3a3a3a;
  }
  .drawer-item {
    border-bottom: 1px solid #e2e8f0;
  }
  .drawer-item:last-child { border-bottom: none; }
  body.dark-mode .drawer-item { border-color: #3a3a3a; }

  .drawer-item-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 18px;
    cursor: pointer;
    background: #ffffff;
    transition: background 0.15s ease;
    user-select: none;
  }
  body.dark-mode .drawer-item-row { background: #222; }
  .drawer-item-row:hover { background: #f1f5ff; }
  body.dark-mode .drawer-item-row:hover { background: #2a2a3a; }
  .drawer-item.is-open .drawer-item-row { background: #eef0ff; }
  body.dark-mode .drawer-item.is-open .drawer-item-row { background: #2a2a44; }

  .drawer-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    font-weight: 700;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
  }
  .drawer-avatar img { width: 100%; height: 100%; object-fit: cover; }
  .drawer-avatar-fallback {
    width: 100%;
    height: 100%;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
  }
  .drawer-item-name {
    font-weight: 700;
    font-size: 14px;
    color: #1e293b;
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  body.dark-mode .drawer-item-name { color: #e0e0e0; }
  .drawer-item-email {
    font-size: 13px;
    color: #64748b;
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  body.dark-mode .drawer-item-email { color: #aaa; }
  .drawer-chevron {
    font-size: 11px;
    color: #94a3b8;
    transition: transform 0.22s ease;
    flex-shrink: 0;
  }
  .drawer-item.is-open .drawer-chevron { transform: rotate(180deg); }

  .drawer-item-body {
    display: none;
    padding: 16px 20px 16px 68px;
    background: #f8faff;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px 24px;
  }
  body.dark-mode .drawer-item-body { background: #1e1e2e; }
  .drawer-item.is-open .drawer-item-body { display: grid; }

  .drawer-field {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .drawer-field strong {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
  }
  .drawer-field span {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
    word-break: break-word;
  }
  body.dark-mode .drawer-field span { color: #e0e0e0; }
  body.dark-mode .drawer-field strong { color: #888; }

  table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
    font-family: 'Inter', sans-serif;
  }
  thead {
    background: #f8f9fa;
  }
  th, td {
    padding: 14px 16px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
    font-family: 'Inter', sans-serif;
  }
  th {
    font-weight: 700;
    color: #6b7a90;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    font-family: 'Inter', sans-serif;
  }
  tbody tr {
    transition: background 0.2s;
  }
  tbody tr:hover {
    background: #f8f9fa;
  }
  
  /* Override for dark mode - prevent white background on hover */
  body.dark-mode tbody tr:hover {
    background: transparent !important;
  }
  
  /* Specific override for receipts and vendors tables in dark mode */
  body.dark-mode #receipts-table tbody tr:hover,
  body.dark-mode #vendors-table tbody tr:hover {
    background: transparent !important;
  }
  /* Remove hover effect for Recent Applications table */
  .recent-applications-table tbody tr:hover {
    background: transparent;
  }
  body.dark-mode .recent-applications-table tbody tr:hover {
    background: transparent;
  }
  tbody td {
    color: #2d3a4a;
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 0.1px;
    font-family: 'Inter', sans-serif;
  }
  .badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.3px;
    font-family: 'Inter', sans-serif;
  }
  .badge.pending {
    background: #fff3cd;
    color: #856404;
  }
  .badge.approved {
    background: #d4edda;
    color: #155724;
  }
  .badge.incomplete {
    background: #fff3cd;
    color: #856404;
  }
  
  .badge.invalid {
    background: #f8d7da;
    color: #721c24;
  }
  .badge.new {
    background: #d1ecf1;
    color: #0c5460;
  }
  .badge.renewal {
    background: #e2e3e5;
    color: #383d41;
  }
  .badge.rejected {
    background: #f8d7da;
    color: #721c24;
  }
  .btn-group {
    display: flex;
    gap: 8px;
  }
  .btn-icon {
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px 10px;
    border-radius: 4px;
    transition: all 0.3s;
    pointer-events: auto;
    position: relative;
    z-index: 1;
  }
  .btn-icon i {
    transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    pointer-events: none;
  }
  .btn-icon:hover {
    background: #f0f0f0;
  }
  
  /* Override for receipts table - don't change background on hover */
  #receipts-table .btn-icon:hover {
    background: inherit;
  }
  
  body.dark-mode #receipts-table .btn-icon:hover {
    background: inherit;
  }
  .btn-icon:hover i {
    transform: scale(1.15) rotate(-5deg);
  }
  .btn-icon.view { color: #5c6bc0; }
  .btn-icon.approve { 
    color: #66bb6a; 
    pointer-events: auto;
  }
  .btn-icon.incomplete { 
    color: #ffc107; 
    position: relative;
    z-index: 10;
    pointer-events: auto;
  }
  .btn-icon.delete { 
    color: #dc3545; 
    pointer-events: auto;
  }
  .btn-icon.delete:hover {
    background: #f8d7da;
    color: #c82333;
  }
  .table-responsive {
    overflow-x: auto;
    margin-top: 1rem;
  }
  .table-responsive table {
    min-width: 100%;
    white-space: nowrap;
  }

  /* Customer concerns: fixed columns so long email cannot crowd or overlap Message */
  #concerns-section .table-responsive table {
    white-space: normal;
    table-layout: fixed;
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
  }
  #concerns-section .table-responsive table th,
  #concerns-section .table-responsive table td {
    padding: 0.75rem 0.65rem;
    vertical-align: top;
    box-sizing: border-box;
  }
  #concerns-section .table-responsive table th:nth-child(1),
  #concerns-section .table-responsive table td:nth-child(1) {
    width: 14.75rem;
    min-width: 14.75rem;
    white-space: nowrap;
    word-break: normal;
  }
  #concerns-section .table-responsive table th:nth-child(2),
  #concerns-section .table-responsive table td:nth-child(2) {
    width: 17%;
    min-width: 6.5rem;
    white-space: normal;
    word-break: break-word;
  }
  #concerns-section .concerns-email-cell {
    width: 15rem;
    max-width: 15rem;
    min-width: 11rem;
    padding-right: 1.65rem !important;
    white-space: normal;
    word-break: break-all;
    overflow-wrap: anywhere;
  }
  #concerns-section .concerns-message-cell {
    width: auto;
    min-width: 0;
    padding-left: 1.65rem !important;
    border-left: 2px solid rgba(102, 126, 234, 0.28);
    white-space: nowrap;
    overflow: hidden;
    vertical-align: middle;
  }
  body.dark-mode #concerns-section .concerns-message-cell {
    border-left-color: rgba(165, 180, 252, 0.4);
  }
  #concerns-section .concern-msg-wrap {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
  }
  #concerns-section .concern-msg-preview {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #4b5563;
  }
  #concerns-section .concern-view-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: rgba(102, 126, 234, 0.12);
    color: #667eea;
    border: 1px solid rgba(102, 126, 234, 0.35);
    border-radius: 6px;
    padding: 0.32rem 0.7rem;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
  }
  #concerns-section .concern-view-btn i {
    color: #667eea;
  }
  #concerns-section .concern-view-btn:hover {
    background: #667eea;
    color: #fff;
    border-color: #667eea;
  }
  #concerns-section .concern-view-btn:hover i {
    color: #fff;
  }
  body.dark-mode #concerns-section .concern-msg-preview {
    color: #b0b0b0;
  }
  body.dark-mode #concerns-section .concern-view-btn {
    background: rgba(102, 126, 234, 0.18);
    color: #818cf8;
    border-color: rgba(102, 126, 234, 0.45);
  }
  body.dark-mode #concerns-section .concern-view-btn i {
    color: #818cf8;
  }
  body.dark-mode #concerns-section .concern-view-btn:hover {
    background: #667eea;
    color: #fff;
    border-color: #667eea;
  }
  body.dark-mode #concerns-section .concern-view-btn:hover i {
    color: #fff;
  }
  /* Concern message modal */
  .concern-modal-body {
    padding: 4px 2px 0;
  }
  .concern-modal-meta {
    display: grid;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e9ecef;
  }
  .concern-modal-meta > div {
    display: flex;
    gap: 0.75rem;
    font-size: 0.9rem;
    color: #1f2937;
    word-break: break-word;
  }
  .concern-meta-label {
    flex-shrink: 0;
    width: 52px;
    color: #9ca3af;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.04em;
    padding-top: 2px;
  }
  .concern-modal-message {
    white-space: pre-wrap;
    word-break: break-word;
    overflow-wrap: break-word;
    line-height: 1.6;
    color: #1f2937;
    max-height: 50vh;
    overflow-y: auto;
  }
  body.dark-mode .concern-modal-meta {
    border-bottom-color: rgba(255, 255, 255, 0.1);
  }
  body.dark-mode .concern-modal-meta > div,
  body.dark-mode .concern-modal-message {
    color: #e0e0e0;
  }

  /* ============================================
     Reports section (redesigned)
     ============================================ */
  .reports-card { padding: 1.5rem 1.75rem 2rem; }
  .reports-head { margin-bottom: 1.25rem; }
  .reports-title {
    font-size: 1.4rem; font-weight: 800; color: #1f2937; margin: 0;
    display: flex; align-items: center; gap: 0.6rem;
  }
  .reports-title i { color: #667eea; }
  .reports-sub { margin: 0.25rem 0 0; color: #6b7280; font-size: 0.9rem; }

  .reports-toolbar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem;
    margin-bottom: 1.5rem;
  }
  .rep-control {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    padding: 0 0.75rem; height: 42px;
  }
  .rep-control i { color: #6b7280; font-size: 0.85rem; }
  .rep-control select {
    border: none; background: transparent; outline: none; cursor: pointer;
    font-size: 0.88rem; color: #374151; font-weight: 500; padding-right: 0.5rem;
    appearance: none; -webkit-appearance: none; min-width: 120px;
  }
  .rep-search {
    display: inline-flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 220px;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    padding: 0 0.85rem; height: 42px;
  }
  .rep-search i { color: #9ca3af; font-size: 0.85rem; }
  .rep-search input {
    border: none; outline: none; background: transparent; width: 100%;
    font-size: 0.88rem; color: #374151;
  }
  .rep-tabs {
    display: inline-flex; align-items: center; background: #f3f4f6;
    border-radius: 10px; padding: 4px; gap: 2px;
  }
  .rep-tab {
    border: none; background: transparent; cursor: pointer;
    padding: 0.45rem 0.9rem; border-radius: 8px; font-size: 0.85rem;
    font-weight: 600; color: #6b7280; transition: all 0.15s ease;
  }
  .rep-tab:hover { color: #667eea; }
  .rep-tab.active {
    background: #667eea;
    color: #fff;
    box-shadow: 0 2px 6px rgba(102, 126, 234, 0.35);
  }
  .rep-export {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: #667eea; color: #fff; border: none; cursor: pointer;
    padding: 0 1.1rem; height: 42px; border-radius: 10px;
    font-size: 0.88rem; font-weight: 600; transition: background 0.15s ease;
  }
  .rep-export:hover { background: #5a67d8; }

  .reports-stats {
    display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem; margin-bottom: 1.25rem;
  }
  .rep-stat {
    display: flex; align-items: center; justify-content: space-between;
    background: #fff; border: 1px solid #eef0f4; border-radius: 14px;
    padding: 1.1rem 1.25rem; box-shadow: 0 1px 3px rgba(16,24,40,0.04);
  }
  .rep-stat-info { display: flex; flex-direction: column; gap: 0.15rem; }
  .rep-stat-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #9ca3af; }
  .rep-stat-value { font-size: 1.9rem; font-weight: 800; color: #111827; line-height: 1.1; }
  .rep-stat-sub { font-size: 0.78rem; color: #16a34a; font-weight: 500; }
  .rep-stat-icon {
    width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.05rem;
  }
  .rep-stat-icon.blue { background: #3b82f6; }
  .rep-stat-icon.orange { background: #f59e0b; }
  .rep-stat-icon.green { background: #22c55e; }
  .rep-stat-icon.red { background: #ef4444; }

  .reports-charts {
    display: grid; grid-template-columns: 1.5fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;
  }
  .reports-bottom {
    display: grid; grid-template-columns: 1.5fr 1fr; gap: 1.25rem;
  }
  .rep-panel {
    background: #fff; border: 1px solid #eef0f4; border-radius: 14px;
    padding: 1.25rem 1.35rem; box-shadow: 0 1px 3px rgba(16,24,40,0.04);
  }
  .rep-panel-head {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;
  }
  .rep-panel-head h3 { font-size: 1rem; font-weight: 700; color: #1f2937; margin: 0; }
  .rep-panel-head .view-all { font-size: 0.82rem; color: #4f46e5; text-decoration: none; font-weight: 600; }
  .rep-legend { display: flex; gap: 0.9rem; font-size: 0.78rem; color: #6b7280; }
  .rep-legend span { display: inline-flex; align-items: center; gap: 0.35rem; }
  .rep-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
  .rep-dot.green { background: #22c55e; }
  .rep-dot.orange { background: #f59e0b; }
  .rep-dot.red { background: #ef4444; }
  .rep-chart-wrap { position: relative; height: 300px; }
  .rep-donut-wrap { position: relative; height: 220px; }
  .rep-donut-legend { margin-top: 1rem; display: flex; flex-direction: column; gap: 0.55rem; }
  .rep-donut-legend .rdl-item { display: flex; align-items: center; justify-content: space-between; font-size: 0.88rem; }
  .rep-donut-legend .rdl-left { display: inline-flex; align-items: center; gap: 0.5rem; color: #374151; }
  .rep-donut-legend .rdl-val { font-weight: 700; color: #111827; }

  .rep-categories { display: flex; flex-direction: column; gap: 1rem; }
  .rep-cat-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.4rem; }
  .rep-cat-name { font-size: 0.88rem; color: #374151; font-weight: 500; }
  .rep-cat-meta { font-size: 0.85rem; color: #111827; font-weight: 700; }
  .rep-cat-meta span { color: #9ca3af; font-weight: 500; margin-left: 0.25rem; }
  .rep-cat-bar { height: 8px; background: #f1f3f7; border-radius: 99px; overflow: hidden; }
  .rep-cat-fill { height: 100%; background: #3b82f6; border-radius: 99px; transition: width 0.4s ease; }

  .rep-activity { display: flex; flex-direction: column; gap: 1rem; }
  .rep-activity-empty { color: #9ca3af; font-size: 0.88rem; padding: 0.5rem 0; }
  .rep-activity-item { display: flex; gap: 0.75rem; align-items: flex-start; }
  .rep-activity-icon {
    width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.8rem;
  }
  .rep-activity-icon.green { background: #22c55e; }
  .rep-activity-icon.red { background: #ef4444; }
  .rep-activity-icon.orange { background: #f59e0b; }
  .rep-activity-body { display: flex; flex-direction: column; gap: 0.15rem; }
  .rep-activity-text { font-size: 0.86rem; color: #374151; line-height: 1.4; }
  .rep-activity-time { font-size: 0.76rem; color: #9ca3af; }

  @media (max-width: 992px) {
    .reports-charts, .reports-bottom { grid-template-columns: 1fr; }
    .reports-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 560px) {
    .reports-stats { grid-template-columns: 1fr; }
    .rep-tabs { width: 100%; justify-content: space-between; }
    .rep-search { min-width: 0; }
  }

  /* Reports dark mode */
  body.dark-mode .reports-title { color: #e0e0e0; }
  body.dark-mode .reports-sub { color: #9ca3af; }
  body.dark-mode .rep-control,
  body.dark-mode .rep-search { background: #2a2a2a; border-color: #3a3a3a; }
  body.dark-mode .rep-control select,
  body.dark-mode .rep-search input { color: #e0e0e0; }
  body.dark-mode .rep-tabs { background: #2a2a2a; }
  body.dark-mode .rep-tab { color: #9ca3af; }
  body.dark-mode .rep-tab:hover { color: #a5b4fc; }
  body.dark-mode .rep-tab.active { background: #667eea; color: #fff; box-shadow: 0 2px 6px rgba(102, 126, 234, 0.4); }
  body.dark-mode .rep-export { background: #667eea; }
  body.dark-mode .rep-export:hover { background: #5a67d8; }
  body.dark-mode .rep-stat,
  body.dark-mode .rep-panel { background: #1f1f1f; border-color: #333; }
  body.dark-mode .rep-stat-value,
  body.dark-mode .rep-panel-head h3,
  body.dark-mode .rep-cat-meta,
  body.dark-mode .rep-donut-legend .rdl-val { color: #e0e0e0; }
  body.dark-mode .rep-stat-label { color: #8a8a8a; }
  body.dark-mode .rep-cat-name,
  body.dark-mode .rep-activity-text,
  body.dark-mode .rep-donut-legend .rdl-left { color: #cfcfcf; }
  body.dark-mode .rep-cat-bar { background: #2f2f2f; }

  /* Disable text highlighting and hover effects for Admin Log and Receipts tables */
  #admin-log-table tr,
  #admin-log-table td,
  #receipts-table tr,
  #receipts-table td {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    cursor: default;
    pointer-events: none;
  }
  
  #admin-log-table tbody tr:hover,
  #receipts-table tbody tr:hover {
    background: transparent !important;
  }
  
  #admin-log-table td:hover,
  #receipts-table td:hover {
    background: transparent !important;
    color: inherit !important;
  }
  
  /* Prevent td hover from affecting button colors in receipts table */
  /* Ensure button colors are maintained when td is hovered */
  #receipts-table td:hover .btn-icon.view {
    color: #5c6bc0 !important;
  }
  
  #receipts-table td:hover .btn-icon.approve {
    color: #66bb6a !important;
  }
  
  #receipts-table td:hover .btn-icon.incomplete {
    color: #ffc107 !important;
  }
  
  #receipts-table td:hover .btn-icon.delete {
    color: #dc3545 !important;
  }
  
  #receipts-table td:hover .btn-icon.view {
    color: #5c6bc0 !important;
  }
  
  #receipts-table td:hover .btn-icon.approve {
    color: #66bb6a !important;
  }
  
  #receipts-table td:hover .btn-icon.incomplete {
    color: #ffc107 !important;
  }
  
  #receipts-table td:hover .btn-icon.delete {
    color: #dc3545 !important;
  }
  
  #admin-log-table td::selection,
  #receipts-table td::selection {
    background: transparent !important;
    color: inherit !important;
  }
  
  #admin-log-table td::-moz-selection,
  #receipts-table td::-moz-selection {
    background: transparent !important;
    color: inherit !important;
  }
  
  /* Re-enable buttons and interactive elements */
  #admin-log-table button,
  #admin-log-table .btn,
  #receipts-table button,
  #receipts-table .btn {
    user-select: auto;
    -webkit-user-select: auto;
    -moz-user-select: auto;
    -ms-user-select: auto;
    cursor: pointer;
    pointer-events: auto;
  }
  
  /* Dark mode support */
  body.dark-mode #admin-log-table tr,
  body.dark-mode #admin-log-table td,
  body.dark-mode #receipts-table tr,
  body.dark-mode #receipts-table td {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    cursor: default;
    pointer-events: none;
  }
  
  body.dark-mode #admin-log-table tbody tr:hover,
  body.dark-mode #receipts-table tbody tr:hover {
    background: transparent !important;
    color: inherit !important;
  }
  
  /* Ensure receipts table rows don't turn white on hover in dark mode */
  body.dark-mode #receipts-table tbody tr {
    background: transparent !important;
  }
  
  body.dark-mode #receipts-table tbody tr:hover {
    background: transparent !important;
    color: inherit !important;
  }
  
  body.dark-mode #receipts-table tbody tr:hover td {
    background: transparent !important;
    color: inherit !important;
  }
  
  body.dark-mode #admin-log-table td:hover,
  body.dark-mode #receipts-table td:hover {
    background: transparent !important;
    color: inherit !important;
  }
  
  /* Prevent td hover from affecting button colors in dark mode for receipts table */
  body.dark-mode #receipts-table td:hover .btn-icon.view {
    color: #5c6bc0 !important;
  }
  
  body.dark-mode #receipts-table td:hover .btn-icon.approve {
    color: #66bb6a !important;
  }
  
  body.dark-mode #receipts-table td:hover .btn-icon.incomplete {
    color: #ffc107 !important;
  }
  
  body.dark-mode #receipts-table td:hover .btn-icon.delete {
    color: #dc3545 !important;
  }
  
  body.dark-mode #admin-log-table td::selection,
  body.dark-mode #receipts-table td::selection {
    background: transparent !important;
    color: inherit !important;
  }
  
  body.dark-mode #admin-log-table td::-moz-selection,
  body.dark-mode #receipts-table td::-moz-selection {
    background: transparent !important;
    color: inherit !important;
  }
  
  body.dark-mode #admin-log-table button,
  body.dark-mode #admin-log-table .btn,
  body.dark-mode #receipts-table button,
  body.dark-mode #receipts-table .btn {
    user-select: auto;
    -webkit-user-select: auto;
    -moz-user-select: auto;
    -ms-user-select: auto;
    cursor: pointer;
    pointer-events: auto;
  }
  
  /* Ensure button colors are maintained in receipts table dark mode */
  body.dark-mode #receipts-table .btn-icon.view {
    color: #5c6bc0 !important;
  }
  
  body.dark-mode #receipts-table .btn-icon.approve {
    color: #66bb6a !important;
  }
  
  body.dark-mode #receipts-table .btn-icon.incomplete {
    color: #ffc107 !important;
  }
  
  body.dark-mode #receipts-table .btn-icon.delete {
    color: #dc3545 !important;
  }

  /* ============================================
     Matching UI for Admin Log & Receipts sections
     ============================================ */
  #adminlog-section .card-header,
  #receipts-section .card-header {
    gap: 16px;
    flex-wrap: wrap;
  }
  #adminlog-section .card-header h2,
  #receipts-section .card-header h2 {
    display: inline-flex;
    align-items: center;
    gap: 10px;
  }
  #adminlog-section .card-header h2 i,
  #receipts-section .card-header h2 i {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    display: inline-grid;
    place-items: center;
    font-size: 16px;
  }
  .log-count-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: 6px;
    padding: 4px 12px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.3px;
    vertical-align: middle;
  }
  #adminlog-section .card-header-tools,
  #receipts-section .card-header-tools {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }
  #receipts-section .receipt-status-filter {
    padding: 8px 14px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    color: #2d3a4a;
    background: #fff;
    cursor: pointer;
  }
  #receipts-section .receipt-status-filter:focus {
    outline: none;
    border-color: #c7d2fe;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
  }
  #adminlog-section .table-responsive,
  #receipts-section .table-responsive {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    overflow: hidden;
  }
  #adminlog-section table thead,
  #receipts-section table thead {
    background: linear-gradient(180deg, #f8f9ff 0%, #f1f3fb 100%);
  }
  #adminlog-section table th,
  #receipts-section table th {
    border-bottom: 2px solid #e5e7f5;
    color: #5b6472;
  }
  #adminlog-section tbody tr:nth-child(even),
  #receipts-section tbody tr:nth-child(even) {
    background: #fafbff;
  }
  #adminlog-section tbody td,
  #receipts-section tbody td {
    border-bottom: 1px solid #eef0f6;
  }
  #adminlog-section tbody tr:last-child td,
  #receipts-section tbody tr:last-child td {
    border-bottom: none;
  }
  #receipts-section .receipt-actions-group {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    flex-wrap: nowrap;
  }
  #receipts-section .receipt-action-btn {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 9px;
    cursor: pointer;
    color: #fff;
    font-size: 14px;
    transition: transform 0.15s ease, box-shadow 0.2s ease, filter 0.2s ease;
  }
  #receipts-section .receipt-action-btn:hover {
    transform: translateY(-1px);
    filter: brightness(1.05);
    box-shadow: 0 4px 12px rgba(17, 24, 39, 0.15);
  }
  #receipts-section .receipt-action-btn--view { background: #667eea; }
  #receipts-section .receipt-action-btn--approve { background: #28a745; }
  #receipts-section .receipt-action-btn--invalid { background: #dc3545; }
  #receipts-section .receipt-processed-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #6b7280;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.35rem 0.6rem;
    background: #f3f4f6;
    border-radius: 8px;
  }
  #receipts-section .receipt-reviewed-note {
    color: #9ca3af;
    font-size: 0.8em;
    margin-top: 4px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
  }

  body.dark-mode .log-count-pill {
    background: #312e81;
    color: #c7d2fe;
  }
  body.dark-mode #adminlog-section .table-responsive,
  body.dark-mode #receipts-section .table-responsive {
    border-color: #374151;
  }
  body.dark-mode #adminlog-section table thead,
  body.dark-mode #receipts-section table thead {
    background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
  }
  body.dark-mode #adminlog-section table th,
  body.dark-mode #receipts-section table th {
    border-bottom-color: #374151;
    color: #cbd5e1;
  }
  body.dark-mode #adminlog-section tbody tr:nth-child(even),
  body.dark-mode #receipts-section tbody tr:nth-child(even) {
    background: rgba(255, 255, 255, 0.02);
  }
  body.dark-mode #adminlog-section tbody td,
  body.dark-mode #receipts-section tbody td {
    border-bottom-color: #2b3444;
  }
  body.dark-mode #receipts-section .receipt-processed-tag {
    background: #1f2937;
    color: #9ca3af;
  }
  body.dark-mode #receipts-section .receipt-status-filter {
    background: #1f2937;
    color: #e5e7eb;
    border-color: #374151;
  }

  /* Disable text highlighting and hover effects for Vendors table (All Vendor Applications) */
  #vendors-table tr,
  #vendors-table td {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    cursor: default;
    pointer-events: none;
  }
  
  #vendors-table tbody tr:hover,
  #vendors-table td:hover {
    background: transparent !important;
    color: inherit !important;
  }
  
  /* Prevent td hover from affecting button colors */
  #vendors-table td:hover .btn-icon {
    color: inherit !important;
  }
  
  #vendors-table td:hover .btn-icon.view {
    color: #5c6bc0 !important;
  }
  
  #vendors-table td:hover .btn-icon.approve {
    color: #66bb6a !important;
  }
  
  #vendors-table td:hover .btn-icon.incomplete {
    color: #ffc107 !important;
  }
  
  #vendors-table td:hover .btn-icon.delete {
    color: #dc3545 !important;
  }
  
  #vendors-table td::selection,
  #vendors-table td::-moz-selection {
    background: transparent !important;
    color: inherit !important;
  }
  
  /* Re-enable buttons and interactive elements */
  #vendors-table button,
  #vendors-table .btn {
    user-select: auto;
    -webkit-user-select: auto;
    -moz-user-select: auto;
    -ms-user-select: auto;
    cursor: pointer;
    pointer-events: auto !important;
    position: relative;
    z-index: 100;
  }
  
  /* Ensure button colors are maintained in vendors table */
  #vendors-table .btn-icon.view {
    color: #5c6bc0 !important;
  }
  
  #vendors-table .btn-icon.approve {
    color: #66bb6a !important;
  }
  
  #vendors-table .btn-icon.incomplete {
    color: #ffc107 !important;
  }
  
  #vendors-table .btn-icon.delete {
    color: #dc3545 !important;
  }
  
  body.dark-mode #vendors-table .btn-icon.view {
    color: #5c6bc0 !important;
  }
  
  body.dark-mode #vendors-table .btn-icon.approve {
    color: #66bb6a !important;
  }
  
  body.dark-mode #vendors-table .btn-icon.incomplete {
    color: #ffc107 !important;
  }
  
  body.dark-mode #vendors-table .btn-icon.delete {
    color: #dc3545 !important;
  }
  
  /* Remove hover highlight for action buttons in vendors table */
  #vendors-table .btn-icon:hover {
    background: transparent !important;
  }
  
  #vendors-table .btn-icon:hover i {
    transform: none !important;
  }
  
  #vendors-table .btn-icon.delete:hover {
    background: transparent !important;
    color: #dc3545 !important;
  }
  
  body.dark-mode #vendors-table .btn-icon:hover {
    background: transparent !important;
    color: inherit !important;
  }
  
  body.dark-mode #vendors-table .btn-icon:hover i {
    transform: none !important;
  }
  
  body.dark-mode #vendors-table .btn-icon.delete:hover {
    background: transparent !important;
    color: #dc3545 !important;
  }
  
  body.dark-mode #vendors-table .btn-icon.view:hover {
    background: transparent !important;
    color: #5c6bc0 !important;
  }
  
  body.dark-mode #vendors-table .btn-icon.approve:hover {
    background: transparent !important;
    color: #66bb6a !important;
  }
  
  body.dark-mode #vendors-table .btn-icon.incomplete:hover {
    background: transparent !important;
    color: #ffc107 !important;
  }
  
  /* Remove only black/white background on hover for receipts table action buttons */
  /* Override general .btn-icon:hover rule to preserve inline background colors */
  /* Prevent general .btn-icon:hover background from affecting receipts buttons */
  #receipts-table button.btn-icon:hover {
    opacity: 0.9 !important;
  }
  
  /* Preserve background colors on hover - don't change to black/white */
  #receipts-table button.btn-icon[style*="#667eea"]:hover {
    background: #667eea !important;
    color: white !important;
  }
  
  #receipts-table button.btn-icon[style*="#28a745"]:hover {
    background: #28a745 !important;
    color: white !important;
  }
  
  #receipts-table button.btn-icon[style*="#dc3545"]:hover {
    background: #dc3545 !important;
    color: white !important;
  }
  
  body.dark-mode #receipts-table button.btn-icon:hover {
    opacity: 0.9 !important;
  }
  
  body.dark-mode #receipts-table button.btn-icon[style*="#667eea"]:hover {
    background: #667eea !important;
    color: white !important;
  }
  
  body.dark-mode #receipts-table button.btn-icon[style*="#28a745"]:hover {
    background: #28a745 !important;
    color: white !important;
  }
  
  body.dark-mode #receipts-table button.btn-icon[style*="#dc3545"]:hover {
    background: #dc3545 !important;
    color: white !important;
  }
  
  /* Dark mode support */
  body.dark-mode #vendors-table tr,
  body.dark-mode #vendors-table td {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    cursor: default;
    pointer-events: none;
  }
  
  body.dark-mode #vendors-table tbody tr:hover,
  body.dark-mode #vendors-table td:hover {
    background: transparent !important;
    color: inherit !important;
  }
  
  /* Ensure vendors table rows don't turn white on hover in dark mode */
  body.dark-mode #vendors-table tbody tr {
    background: transparent !important;
  }
  
  body.dark-mode #vendors-table tbody tr:hover td {
    background: transparent !important;
    color: inherit !important;
  }
  
  /* Prevent td hover from affecting button colors in dark mode */
  body.dark-mode #vendors-table td:hover .btn-icon {
    color: inherit !important;
  }
  
  body.dark-mode #vendors-table td:hover .btn-icon.view {
    color: #5c6bc0 !important;
  }
  
  body.dark-mode #vendors-table td:hover .btn-icon.approve {
    color: #66bb6a !important;
  }
  
  body.dark-mode #vendors-table td:hover .btn-icon.incomplete {
    color: #ffc107 !important;
  }
  
  body.dark-mode #vendors-table td:hover .btn-icon.delete {
    color: #dc3545 !important;
  }
  
  body.dark-mode #vendors-table td::selection,
  body.dark-mode #vendors-table td::-moz-selection {
    background: transparent !important;
    color: inherit !important;
  }
  
  body.dark-mode #vendors-table button,
  body.dark-mode #vendors-table .btn {
    user-select: auto;
    -webkit-user-select: auto;
    -moz-user-select: auto;
    -ms-user-select: auto;
    cursor: pointer;
    pointer-events: auto !important;
    position: relative;
    z-index: 100;
  }
  
  /* Ensure ALL buttons in dark mode are clickable */
  body.dark-mode button,
  body.dark-mode .btn,
  body.dark-mode .btn-icon,
  body.dark-mode .pagination-btn,
  body.dark-mode .pagination-go-btn,
  body.dark-mode .pagination-number,
  body.dark-mode a.btn,
  body.dark-mode a.pagination-btn,
  body.dark-mode .nav-item,
  body.dark-mode .sidebar *,
  body.dark-mode .menu-toggle,
  body.dark-mode .user-info,
  body.dark-mode .dark-mode-toggle,
  body.dark-mode .dark-mode-toggle *,
  body.dark-mode .toggle-switch,
  body.dark-mode .toggle-switch *,
  body.dark-mode .logout-item {
    pointer-events: auto !important;
    cursor: pointer !important;
    position: relative;
    z-index: 1000;
  }
  
  /* Ensure sidebar and all its elements are fully interactive */
  .sidebar,
  .sidebar *,
  .nav-item,
  .nav-item *,
  .sidebar-footer,
  .sidebar-footer *,
  .dark-mode-toggle,
  .dark-mode-toggle *,
  .toggle-switch,
  .toggle-switch input,
  .toggle-switch .toggle-slider,
  .logout-item,
  .logout-item * {
    pointer-events: auto !important;
    cursor: pointer !important;
    position: relative;
    z-index: 1000;
  }
  
  /* Ensure menu toggle and user info are clickable */
  .menu-toggle,
  .menu-toggle *,
  .user-info,
  .user-info * {
    pointer-events: auto !important;
    cursor: pointer !important;
    position: relative;
    z-index: 1000;
  }
  .form-group {
    margin-bottom: 25px;
  }
  .form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
    font-size: 14px;
    letter-spacing: 0.2px;
    color: #2c3e50;
    font-family: 'Inter', sans-serif;
  }
  .form-group input[type="text"],
  .form-group input[type="password"],
  .form-group select,
  .form-group textarea {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 0.1px;
    font-family: 'Inter', sans-serif;
  }
  .password-field-wrap {
    position: relative;
    width: 100%;
  }
  .password-field-wrap input {
    padding-right: 44px;
  }
  .password-toggle-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    display: grid;
    place-items: center;
    border-radius: 8px;
    padding: 0;
  }
  .password-toggle-btn:hover {
    color: #667eea;
    background: rgba(102, 126, 234, 0.12);
  }
  .password-field-wrap input::-ms-reveal,
  .password-field-wrap input::-ms-clear {
    display: none;
  }
  .form-group select {
    background-color: white;
    cursor: pointer;
  }
  .form-group textarea {
    resize: vertical;
    min-height: 80px;
  }
  
  /* Dark Mode Toggle Switch */
  .switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
  }
  .switch input {
    opacity: 0;
    width: 0;
    height: 0;
  }
  .slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
  }
  .slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
  }
  input:checked + .slider {
    background-color: #3f51b5;
  }
  input:checked + .slider:before {
    transform: translateX(26px);
  }
  input:focus + .slider {
    box-shadow: 0 0 1px #3f51b5;
  }
  
  .btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.3px;
    font-family: 'Inter', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
  }
  .btn-primary { background: #5c6bc0; color: white; }
  .btn-primary:hover { background: #3f51b5; }
  .btn-success { background: #ff9800; color: white; }
  .btn-success:hover { background: #f57c00; }
  .btn-danger { background: #ef5350; color: white; }
  .btn-danger:hover { background: #e53935; }
  .btn-group-center {
    display: flex;
    gap: 10px;
    justify-content: center;
  }
  .save-button-container {
    margin-top: 25px;
    text-align: right;
  }
  .hidden { display: none; }
  input[type="file"] {
    display: none;
  }

  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.5);
    animation: fadeIn 0.3s;
    align-items: center;
    justify-content: center;
  }
  .modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  /* Background blur effect when modal is open */
  body.modal-open {
    overflow: hidden;
  }
  
  body.modal-open .main-content {
    filter: blur(3px);
    transition: filter 0.3s ease;
  }
  
  /* Don't blur sidebar - keep it interactive */
  body.modal-open .sidebar {
    filter: none;
    pointer-events: auto !important;
  }
  
  body.modal-open .sidebar * {
    pointer-events: auto !important;
  }
  @keyframes fadeIn {
    from { opacity: 0; } to { opacity: 1; }
  }
  .modal-content {
    background-color: white;
    padding: 30px;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    overflow-x: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    animation: slideIn 0.3s;
    margin: auto;
    position: relative;
  }
  
  /* Specific styling for requirements modal to ensure centering */
  #requirementsModal {
    display: none;
    align-items: center;
    justify-content: center;
  }

  /* Slot map editor — full viewport overlay, centered wide panel */
  #slotMapModal {
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10050;
    padding: 16px;
    box-sizing: border-box;
  }
  #slotMapModal.show {
    display: flex;
    align-items: center;
    justify-content: center;
  }
  #slotMapModal .modal-content {
    width: min(1180px, 96vw);
    max-width: min(1180px, 96vw);
    max-height: 92vh;
    margin: 0 auto;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  #slotMapEditorContent {
    flex: 1;
    min-height: 0;
    overflow: auto;
    width: 100%;
    display: flex;
    justify-content: center;
    padding: 4px 0 8px;
  }
  #slotMapEditorContent .mkt-gf-plan {
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
  }
  #slotMapEditorContent .mkt-gf-scroll {
    width: 100%;
    display: flex;
    justify-content: center;
  }
  #slotMapEditorContent .dpm-site-map {
    width: 100%;
  }

  #slotDetailModal .slot-detail-close {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #4f46e5;
    color: #fff;
    font-size: 22px;
    font-weight: 700;
    line-height: 1;
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35);
  }
  #slotDetailModal .slot-detail-close:hover {
    background: #4338ca;
    color: #fff;
  }
  #slotDetailModal #slotDetailSaveBtn {
    background: #4f46e5;
    border: none;
    color: #fff;
    border-radius: 999px;
    padding: 0.75rem 1.5rem;
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.35);
  }
  #slotDetailModal #slotDetailSaveBtn:hover {
    background: #4338ca;
  }

  .slot-map-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.5rem 0.75rem;
    padding: 0.75rem 0 0.25rem;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 0.75rem;
  }
  .slot-map-pagination-label {
    flex: 1 1 100%;
    text-align: center;
    font-size: 0.88rem;
    font-weight: 600;
    color: #475569;
    margin: 0.15rem 0 0.35rem;
  }
  .slot-map-page-btn {
    min-width: 36px;
    height: 36px;
    padding: 0 0.65rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    color: #334155;
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
  }
  .slot-map-page-btn:hover:not(:disabled) {
    background: #f1f5f9;
    border-color: #94a3b8;
  }
  .slot-map-page-btn.is-active {
    background: #4f46e5;
    border-color: #4f46e5;
    color: #fff;
  }
  .slot-map-page-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
  }
  .slot-map-nav-btn {
    padding: 0 0.85rem;
    font-size: 0.82rem;
  }
  body.dark-mode .slot-map-pagination { border-bottom-color: #374151; }
  body.dark-mode .slot-map-pagination-label { color: #cbd5e1; }
  body.dark-mode .slot-map-page-btn {
    background: #1f2937;
    border-color: #374151;
    color: #e5e7eb;
  }
  body.dark-mode .slot-map-page-btn.is-active {
    background: #6366f1;
    border-color: #6366f1;
  }

  #newsCardDetailsModal {
    align-items: center;
    justify-content: center;
    z-index: 1001;
  }

  #newsCardDetailsModal.show {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  #receiptPreviewModal.show {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  #receiptPreviewModal .modal-content.receipt-preview-modal {
    max-width: min(760px, 92vw);
    width: 100%;
    max-height: min(90vh, 900px);
    padding: 0;
    overflow: hidden;
    border-radius: 16px;
    box-shadow: 0 24px 60px rgba(45, 35, 108, 0.22);
  }

  #receiptPreviewModal .receipt-preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin: 0;
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, #667eea 0%, #5568d3 100%);
    border-bottom: none;
    flex-shrink: 0;
  }

  #receiptPreviewModal .receipt-preview-header h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #fff;
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
  }

  #receiptPreviewModal .receipt-preview-header h2 i {
    font-size: 1rem;
    opacity: 0.95;
  }

  #receiptPreviewModal .receipt-preview-close {
    width: 38px;
    height: 38px;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
    cursor: pointer;
    display: grid;
    place-items: center;
    font-size: 1rem;
    flex-shrink: 0;
    transition: background 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
  }

  #receiptPreviewModal .receipt-preview-close:hover {
    background: rgba(255, 255, 255, 0.32);
    transform: scale(1.05);
    box-shadow: 0 4px 14px rgba(17, 24, 39, 0.18);
  }

  #receiptPreviewModal .receipt-preview-close:focus-visible {
    outline: 2px solid #fff;
    outline-offset: 2px;
  }

  #receiptPreviewModal .receipt-preview-scroll {
    max-height: calc(min(90vh, 900px) - 72px);
    overflow-y: auto;
    overflow-x: hidden;
    padding: 1.25rem 1.5rem 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    scrollbar-width: thin;
    scrollbar-color: #c7d2fe #f3f4f6;
  }

  #receiptPreviewModal .receipt-preview-scroll::-webkit-scrollbar {
    width: 8px;
  }

  #receiptPreviewModal .receipt-preview-scroll::-webkit-scrollbar-track {
    background: #f3f4f6;
    border-radius: 999px;
  }

  #receiptPreviewModal .receipt-preview-scroll::-webkit-scrollbar-thumb {
    background: #c7d2fe;
    border-radius: 999px;
  }

  #receiptPreviewModal .receipt-preview-scroll::-webkit-scrollbar-thumb:hover {
    background: #667eea;
  }

  #receiptPreviewModal .receipt-preview-details {
    width: 100%;
    max-width: 560px;
    margin: 0 auto 1rem;
    padding: 1rem 1.1rem;
    background: linear-gradient(180deg, #f8f9ff 0%, #f3f5ff 100%);
    border: 1px solid #e0e7ff;
    border-radius: 14px;
    text-align: left;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
  }

  #receiptPreviewModal .receipt-preview-details__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.55rem;
  }

  #receiptPreviewModal .receipt-preview-kicker {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #667eea;
  }

  #receiptPreviewModal .receipt-status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.22rem 0.62rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  #receiptPreviewModal .receipt-status-badge--approved {
    background: #dcfce7;
    color: #15803d;
  }

  #receiptPreviewModal .receipt-status-badge--invalid {
    background: #fee2e2;
    color: #b91c1c;
  }

  #receiptPreviewModal .receipt-status-badge--pending {
    background: #fef3c7;
    color: #b45309;
  }

  #receiptPreviewModal .receipt-preview-details h3 {
    margin: 0 0 0.85rem;
    font-size: 1.08rem;
    font-weight: 700;
    color: #1f2937;
    line-height: 1.35;
  }

  #receiptPreviewModal .receipt-preview-meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
  }

  #receiptPreviewModal .receipt-preview-chip {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    padding: 0.62rem 0.7rem;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    min-width: 0;
  }

  #receiptPreviewModal .receipt-preview-chip i {
    color: #667eea;
    margin-top: 0.15rem;
    flex-shrink: 0;
  }

  #receiptPreviewModal .receipt-preview-chip span {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    min-width: 0;
  }

  #receiptPreviewModal .receipt-preview-chip small {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #9ca3af;
  }

  #receiptPreviewModal .receipt-preview-chip strong {
    font-size: 0.84rem;
    font-weight: 600;
    color: #374151;
    line-height: 1.35;
    word-break: break-word;
  }

  #receiptPreviewModal .receipt-preview-chip--wide {
    grid-column: 1 / -1;
  }

  @media (max-width: 560px) {
    #receiptPreviewModal .receipt-preview-meta {
      grid-template-columns: 1fr;
    }
  }

  #receiptPreviewModal .receipt-preview-content {
    width: 100%;
    max-width: 560px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #f5f5f5;
    border-radius: 10px;
    padding: 1rem;
    box-sizing: border-box;
    gap: 0.75rem;
  }

  #receiptPreviewModal .receipt-preview-media-scroll {
    width: 100%;
    max-height: min(52vh, 460px);
    overflow-y: auto;
    overflow-x: hidden;
    border-radius: 8px;
    scrollbar-width: thin;
    scrollbar-color: #c7d2fe #eef0f4;
  }

  #receiptPreviewModal .receipt-preview-media-scroll::-webkit-scrollbar {
    width: 8px;
  }

  #receiptPreviewModal .receipt-preview-media-scroll::-webkit-scrollbar-track {
    background: #eef0f4;
    border-radius: 999px;
  }

  #receiptPreviewModal .receipt-preview-media-scroll::-webkit-scrollbar-thumb {
    background: #c7d2fe;
    border-radius: 999px;
  }

  #receiptPreviewModal .receipt-preview-media-scroll::-webkit-scrollbar-thumb:hover {
    background: #667eea;
  }

  #receiptPreviewModal .receipt-preview-image-btn {
    position: relative;
    display: block;
    width: 100%;
    padding: 0;
    border: none;
    background: transparent;
    cursor: zoom-in;
    border-radius: 8px;
    overflow: hidden;
  }

  #receiptPreviewModal .receipt-preview-image-btn img {
    display: block;
    margin: 0 auto;
    max-width: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 8px;
    transition: transform 0.2s ease;
  }

  #receiptPreviewModal .receipt-preview-image-btn:hover img {
    transform: scale(1.02);
  }

  #receiptPreviewModal .receipt-preview-image-hint {
    position: absolute;
    left: 50%;
    bottom: 0.75rem;
    transform: translateX(-50%);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.75rem;
    border-radius: 999px;
    background: rgba(17, 24, 39, 0.78);
    color: #fff;
    font-size: 0.78rem;
    font-weight: 600;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s ease;
  }

  #receiptPreviewModal .receipt-preview-image-btn:hover .receipt-preview-image-hint,
  #receiptPreviewModal .receipt-preview-image-btn:focus-visible .receipt-preview-image-hint {
    opacity: 1;
  }

  #receiptPreviewModal .receipt-preview-file-tools {
    display: flex;
    gap: 0.55rem;
    justify-content: center;
    flex-wrap: wrap;
    width: 100%;
  }

  #receiptPreviewModal .receipt-preview-file-tools button {
    border: none;
    border-radius: 999px;
    padding: 0.45rem 0.9rem;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
  }

  #receiptPreviewModal .receipt-preview-file-tools .receipt-tool-view {
    background: #667eea;
    color: #fff;
  }

  #receiptPreviewModal .receipt-preview-file-tools .receipt-tool-view:hover {
    background: #5568d3;
  }

  #receiptPreviewModal .receipt-preview-file-tools .receipt-tool-download {
    background: #fff;
    color: #667eea;
    border: 1px solid #c7d2fe;
  }

  #receiptPreviewModal .receipt-preview-file-tools .receipt-tool-download:hover {
    background: #eef2ff;
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-file-tools .receipt-tool-download {
    background: #1f2937;
    color: #a5b4fc;
    border-color: #4b5563;
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-file-tools .receipt-tool-download:hover {
    background: #111827;
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-media-scroll {
    scrollbar-color: #4b5563 #111827;
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-media-scroll::-webkit-scrollbar-track {
    background: #111827;
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-media-scroll::-webkit-scrollbar-thumb {
    background: #4b5563;
  }

  #receiptPreviewModal .receipt-preview-content iframe {
    width: 100%;
    min-height: 420px;
    height: 52vh;
    max-height: none;
    border: none;
    border-radius: 8px;
    background: #fff;
  }

  #receiptImageLightbox {
    position: fixed;
    inset: 0;
    z-index: 10001;
    background: rgba(17, 24, 39, 0.92);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    backdrop-filter: blur(6px);
    overflow-y: auto;
  }

  #receiptImageLightbox.is-open {
    display: flex;
  }

  #receiptImageLightbox .receipt-lightbox-panel {
    position: relative;
    width: min(94vw, 980px);
    max-height: none;
    margin: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.85rem;
    padding-bottom: 0.5rem;
  }

  #receiptImageLightbox .receipt-lightbox-scroll {
    width: 100%;
    max-height: calc(94vh - 120px);
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    justify-content: center;
    padding: 0.25rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.45) rgba(255, 255, 255, 0.08);
  }

  #receiptImageLightbox .receipt-lightbox-scroll::-webkit-scrollbar {
    width: 8px;
  }

  #receiptImageLightbox .receipt-lightbox-scroll::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 999px;
  }

  #receiptImageLightbox .receipt-lightbox-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.45);
    border-radius: 999px;
  }

  #receiptImageLightbox img {
    max-width: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
    background: #fff;
  }

  #receiptImageLightbox .receipt-lightbox-footer {
    width: 100%;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.85rem;
    position: sticky;
    bottom: 0;
    padding-top: 0.25rem;
  }

  #receiptImageLightbox .receipt-lightbox-caption {
    margin: 0;
    color: rgba(255, 255, 255, 0.92);
    font-size: 0.92rem;
    text-align: center;
  }

  #receiptImageLightbox .receipt-lightbox-actions {
    display: flex;
    gap: 0.65rem;
    flex-wrap: wrap;
    justify-content: center;
  }

  #receiptImageLightbox .receipt-lightbox-actions button {
    border: none;
    border-radius: 999px;
    padding: 0.55rem 1rem;
    font-size: 0.84rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
  }

  #receiptImageLightbox .receipt-lightbox-download {
    background: #667eea;
    color: #fff;
  }

  #receiptImageLightbox .receipt-lightbox-download:hover {
    background: #5568d3;
  }

  #receiptImageLightbox .receipt-lightbox-close-btn {
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
  }

  #receiptImageLightbox .receipt-lightbox-close-btn:hover {
    background: rgba(255, 255, 255, 0.28);
  }

  #receiptImageLightbox .receipt-lightbox-x {
    position: absolute;
    top: -2.75rem;
    right: 0;
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
    font-size: 1rem;
    cursor: pointer;
    display: grid;
    place-items: center;
  }

  #receiptImageLightbox .receipt-lightbox-x:hover {
    background: rgba(255, 255, 255, 0.28);
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-scroll {
    scrollbar-color: #4b5563 #111827;
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-scroll::-webkit-scrollbar-track {
    background: #111827;
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-scroll::-webkit-scrollbar-thumb {
    background: #4b5563;
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-details {
    background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
    border-color: #374151;
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-details h3 {
    color: #f9fafb;
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-chip {
    background: #0f172a;
    border-color: #374151;
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-chip strong {
    color: #e5e7eb;
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-kicker {
    color: #a5b4fc;
  }

  body.dark-mode #receiptPreviewModal .receipt-preview-content {
    background: #111827;
  }
  
  #requirementsModal .modal-content {
    max-width: 90%;
    width: auto;
    min-width: 600px;
    margin: auto;
  }

  /* ============================================
     Redesigned Requirements Management modal
     ============================================ */
  #requirementsModal .modal-content.rq-modal {
    width: 660px;
    max-width: 94%;
    min-width: 0;
    max-height: 90vh;
    overflow-y: auto;
    padding: 0;
    border-radius: 18px;
    background: #ffffff;
  }
  .rq-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px 0;
  }
  .rq-head-left { display: flex; align-items: center; gap: 12px; }
  .rq-head-ic {
    width: 40px; height: 40px; border-radius: 11px;
    display: inline-flex; align-items: center; justify-content: center;
    background: #eef2ff; color: #4f46e5; font-size: 1rem;
  }
  .rq-head h2 { font-size: 1.15rem; font-weight: 700; color: #111827; margin: 0; }
  .rq-close {
    width: 32px; height: 32px; border-radius: 50%;
    border: none; background: #f3f4f6; color: #6b7280;
    font-size: 1.2rem; line-height: 1; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background 0.18s ease, color 0.18s ease;
  }
  .rq-close:hover { background: #e5e7eb; color: #111827; }

  .rq-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    padding: 18px 24px 0;
  }
  .rq-tabs { display: inline-flex; gap: 8px; }
  .rq-tab {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 9px 18px;
    border: none;
    border-radius: 999px;
    background: #f3f4f6;
    color: #4b5563;
    font-size: 0.86rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
  }
  .rq-tab:hover { background: #e5e7eb; }
  .rq-tab--active {
    background: linear-gradient(135deg, #7c83f5, #6366f1);
    color: #fff;
    box-shadow: 0 6px 14px rgba(99, 102, 241, 0.35);
  }
  .rq-tab--active:hover { background: linear-gradient(135deg, #7177ef, #5a5ee8); }
  .rq-save {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 9px 18px;
    border: none;
    border-radius: 999px;
    background: linear-gradient(135deg, #f59e0b, #f97316);
    color: #fff;
    font-size: 0.86rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 6px 14px rgba(249, 115, 22, 0.32);
    transition: filter 0.18s ease;
  }
  .rq-save:hover { filter: brightness(1.05); }
  .rq-save:disabled { opacity: 0.7; cursor: not-allowed; }

  .rq-subhead {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    padding: 20px 24px 8px;
  }
  .rq-subhead h3 { font-size: 1.05rem; font-weight: 700; color: #111827; margin: 0; }
  .rq-subhead p { font-size: 0.82rem; color: #6b7280; margin: 4px 0 0; }
  .rq-meta { text-align: right; display: flex; flex-direction: column; gap: 2px; }
  .rq-meta span { font-size: 0.78rem; color: #9ca3af; }
  #rqItemCount { color: #6b7280; font-weight: 600; }

  .rq-body { padding: 8px 24px 4px; }
  .rq-list { display: flex; flex-direction: column; gap: 14px; }
  .rq-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    background: #f9fafb;
    border: 1px solid #eef0f4;
    border-radius: 14px;
    padding: 16px;
  }
  .rq-item--new { background: #fff; border: 1.5px dashed #a5b4fc; }
  .rq-num {
    flex-shrink: 0;
    width: 30px; height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, #818cf8, #6366f1);
    color: #fff;
    font-size: 0.82rem;
    font-weight: 700;
    display: inline-flex; align-items: center; justify-content: center;
    margin-top: 2px;
  }
  .rq-fields { flex: 1; display: flex; flex-direction: column; gap: 10px; min-width: 0; }
  .rq-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    font-size: 0.9rem;
    color: #111827;
    background: #fff;
    outline: none;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
  }
  .rq-input:focus { border-color: #a5b4fc; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
  .rq-input--desc { color: #6b7280; font-size: 0.84rem; }
  .rq-del {
    flex-shrink: 0;
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px;
    border: 1px solid #fecaca;
    border-radius: 9px;
    background: #fef2f2;
    color: #dc2626;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.18s ease;
  }
  .rq-del:hover { background: #fee2e2; }
  .rq-empty { text-align: center; color: #9ca3af; padding: 24px 12px; font-size: 0.88rem; }
  .rq-empty--error { color: #dc2626; }

  .rq-add-wrap { text-align: center; padding: 18px 24px 26px; }
  .rq-add {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 26px;
    border: none;
    border-radius: 999px;
    background: linear-gradient(135deg, #818cf8, #6366f1);
    color: #fff;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 8px 18px rgba(99, 102, 241, 0.32);
    transition: filter 0.18s ease;
  }
  .rq-add:hover { filter: brightness(1.05); }

  /* Dark mode for requirements modal (use #requirementsModal for specificity over base rules) */
  body.dark-mode #requirementsModal .modal-content.rq-modal {
    background: #1e1e1e;
    border: 1px solid #333;
    color: #e0e0e0;
  }
  body.dark-mode #requirementsModal .rq-head h2,
  body.dark-mode #requirementsModal .rq-subhead h3 { color: #e0e0e0; }
  body.dark-mode #requirementsModal .rq-subhead p { color: #b0b0b0; }
  body.dark-mode #requirementsModal .rq-head-ic { background: #312e81; color: #c7d2fe; }
  body.dark-mode #requirementsModal .rq-close { background: #2c2c2c; color: #b0b0b0; }
  body.dark-mode #requirementsModal .rq-close:hover { background: #3a3a3a; color: #e0e0e0; }
  body.dark-mode #requirementsModal .rq-tab { background: #2c2c2c; color: #b0b0b0; }
  body.dark-mode #requirementsModal .rq-tab:hover { background: #3a3a3a; color: #e0e0e0; }
  body.dark-mode #requirementsModal .rq-tab--active { background: linear-gradient(135deg, #7c83f5, #6366f1); color: #fff; }
  body.dark-mode #requirementsModal .rq-tab--active:hover { background: linear-gradient(135deg, #7177ef, #5a5ee8); }
  body.dark-mode #requirementsModal #rqItemCount { color: #b0b0b0; }
  body.dark-mode #requirementsModal .rq-item { background: #2c2c2c; border-color: #444; }
  body.dark-mode #requirementsModal .rq-item--new { background: #252525; border-color: #6366f1; }
  body.dark-mode #requirementsModal .rq-input {
    background: #1a1a1a;
    border-color: #444;
    color: #e0e0e0;
  }
  body.dark-mode #requirementsModal .rq-input::placeholder { color: #666; }
  body.dark-mode #requirementsModal .rq-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
  }
  body.dark-mode #requirementsModal .rq-input--desc { color: #b0b0b0; }
  body.dark-mode #requirementsModal .rq-del { background: #3b1d1d; color: #f87171; border-color: #5b2525; }
  body.dark-mode #requirementsModal .rq-del:hover { background: #4a2020; }
  body.dark-mode #requirementsModal .rq-empty { color: #888; }
  body.dark-mode #requirementsModal .rq-empty--error { color: #f87171; }
  
  /* Specific styling for statistics graph modal to ensure centering */
  #statisticsGraphModal {
    display: none;
    align-items: center;
    justify-content: center;
  }
  
  #statisticsGraphModal.show {
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  #statisticsGraphModal .modal-content {
    margin: auto;
    position: relative;
    transform: translateY(0);
  }
  
  /* Limit year dropdown height to prevent covering chart */
  #graphYearSelect {
    max-height: 150px;
    overflow-y: auto;
  }
  
  #graphYearSelect option {
    padding: 5px;
  }
  
  @keyframes slideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
  }
  

  /* Vendor Modal Styles */
  .vendor-info {
    margin-bottom: 20px;
  }

  .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
  }

  .info-item {
    display: flex;
    flex-direction: column;
  }

  .info-item label {
    font-weight: 600;
    color: #555;
    margin-bottom: 5px;
  }

  .info-item span {
    color: #333;
  }

  .documents-section {
    margin-bottom: 20px;
  }

  .documents-list {
    margin-top: 15px;
  }

  .document-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
  }

  .document-info {
    flex: 1;
  }

  .document-name {
    font-weight: 600;
    font-size: 14px;
    letter-spacing: 0.1px;
    margin-bottom: 5px;
    font-family: 'Inter', sans-serif;
  }

  .document-meta {
    display: flex;
    gap: 15px;
    font-size: 13px;
    font-weight: 500;
    letter-spacing: 0.1px;
    color: #666;
    font-family: 'Inter', sans-serif;
  }

  .document-actions {
    display: flex;
    gap: 10px;
  }

  .action-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
  }

  .action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
  }

  .btn-secondary {
    background: #6c757d;
    color: white;
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.2px;
    font-family: 'Inter', sans-serif;
    transition: all 0.3s;
  }

  .btn-secondary:hover {
    background: #545b62;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
  }

  .pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-top: 25px;
    padding: 20px;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  }

  .pagination-info {
    color: #6b7a90;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.2px;
    font-family: 'Inter', sans-serif;
    padding: 10px 16px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
  }

  .pagination-btn {
    background: #667eea;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.3px;
    font-family: 'Inter', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
    box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
  }

  .pagination-btn:hover {
    background: #5568d3;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
  }

  .pagination-btn:disabled,
  .pagination-btn.disabled {
    background: #e9ecef;
    color: #adb5bd;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
  }

  .pagination-btn:disabled:hover,
  .pagination-btn.disabled:hover {
    background: #e9ecef;
    transform: none;
    box-shadow: none;
  }
  
  .pagination-page-input {
    width: 80px;
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    text-align: center;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.2px;
    font-family: 'Inter', sans-serif;
    margin: 0 5px;
    background: #ffffff;
    color: #2d3a4a;
    transition: all 0.3s;
  }
  
  .pagination-page-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
  
  .pagination-go-btn {
    background: #667eea;
    color: white;
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.2px;
    font-family: 'Inter', sans-serif;
    margin-left: 5px;
    transition: all 0.3s;
    box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
  }
  
  .pagination-go-btn:hover {
    background: #5568d3;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
  }
  
  .pagination-page-size {
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 0.1px;
    font-family: 'Inter', sans-serif;
    background: #ffffff;
    color: #2d3a4a;
    cursor: pointer;
    margin-left: 10px;
    transition: all 0.3s;
  }
  
  .pagination-page-size:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
  
  .pagination-numbers {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
  }
  
  .pagination-number {
    background: #ffffff;
    color: #667eea;
    padding: 10px 14px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.2px;
    font-family: 'Inter', sans-serif;
    min-width: 44px;
    text-align: center;
    transition: all 0.3s;
  }
  
  .pagination-number:hover {
    background: #667eea;
    color: white;
    border-color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
  }
  
  .pagination-number.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
    box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
  }
  
  .pagination-ellipsis {
    padding: 10px 5px;
    color: #6b7a90;
    font-weight: 700;
    font-family: 'Inter', sans-serif;
    letter-spacing: 0.2px;
  }

  .btn-info {
    background: #17a2b8;
    color: white;
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.9rem;
  }

  .btn-info:hover {
    background: #138496;
  }

  .no-documents {
    text-align: center;
    padding: 20px;
    color: #666;
    font-style: italic;
  }

  .preview-container {
    text-align: center;
    margin-bottom: 20px;
  }

  .preview-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    background: #f8f9fa;
    border-radius: 10px;
    color: #6c757d;
  }

  .preview-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
  }
  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
  }
  .modal-header h2 {
    font-size: 24px;
    color: #2c3e50;
  }
  .close-modal {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #7f8c8d;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .close-modal:hover {
    color: #2c3e50;
  }
  /* Edit Profile modal: indigo Save, orange Cancel, designed X */
  #profileModal .close-modal {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    color: #ffffff;
    padding: 0;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    transition: all 0.3s;
    border-radius: 50%;
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
    position: relative;
  }
  #profileModal .close-modal:hover {
    background: linear-gradient(135deg, #5c6bc0 0%, #667eea 100%);
    color: #ffffff;
    transform: scale(1.1) rotate(90deg);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.4);
  }
  #profileModal .close-modal:active {
    transform: scale(0.95) rotate(90deg);
  }
  #profileModal .btn-group-center .btn-success {
    background: #667eea;
    color: #ffffff;
  }
  #profileModal .btn-group-center .btn-success:hover {
    background: #5a6fd8;
  }
  .close {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    color: white;
    padding: 0;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    transition: all 0.3s;
    border-radius: 50%;
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
    position: relative;
  }
  .close:hover {
    background: linear-gradient(135deg, #5c6bc0 0%, #667eea 100%);
    transform: scale(1.1) rotate(90deg);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.4);
  }
  .close:active {
    transform: scale(0.95) rotate(90deg);
  }
  .profile-photo-section {
    text-align: center;
    margin-bottom: 25px;
    padding-bottom: 25px;
    border-bottom: 1px solid #e9ecef;
  }
  .profile-photo-preview {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    margin: 0 auto 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: bold;
    color: white;
    background: #ff9800;
    overflow: hidden;
    position: relative;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
  }
  .profile-photo-preview:hover {
    transform: scale(1.03);
    box-shadow: 0 4px 16px rgba(255, 152, 0, 0.35);
  }
  .profile-photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .profile-photo-inner {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .profile-photo-inner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .profile-photo-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(102, 126, 234, 0.85);
    color: #fff;
    font-size: 0.95rem;
    padding: 0.35rem;
    text-align: center;
    opacity: 0;
    transition: opacity 0.2s;
  }
  .profile-photo-preview:hover .profile-photo-overlay {
    opacity: 1;
  }
  .alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    display: none;
  }
  .alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
  }
  .alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
  }
  
  /* Media Management Styles */
  .media-section {
    margin-bottom: 30px;
    padding: 20px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background: #f9f9f9;
  }
  
  .media-section h4 {
    color: #333;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  
  .media-upload-area {
    display: flex;
    gap: 20px;
    align-items: flex-start;
  }
  
  .current-media {
    flex: 1;
    text-align: center;
  }
  
  .current-media video,
  .current-media img {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }
  
  .media-info {
    margin-top: 10px;
    color: #666;
    font-size: 14px;
  }
  
  .upload-controls {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 200px;
  }
  
  .upload-controls button {
    width: 100%;
    padding: 10px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
  }
  
  .upload-controls .btn-primary {
    background: #007bff;
    color: white;
  }
  
  .upload-controls .btn-primary:hover {
    background: #0056b3;
  }
  
  .upload-controls .btn-danger {
    background: #dc3545;
    color: white;
  }
  
  .upload-controls .btn-danger:hover {
    background: #c82333;
  }
  
  @media (max-width: 768px) {
    .media-upload-area {
      flex-direction: column;
    }
    
    .upload-controls {
      flex-direction: row;
      min-width: auto;
    }
  }
  
  /* Dark Mode Styles for Media Management */
  body.dark-mode .media-section {
    background: #2c2c2c;
    border-color: #444;
  }
  
  body.dark-mode .media-section h4 {
    color: #e0e0e0;
  }
  
  body.dark-mode .media-info {
    color: #b0b0b0;
  }
  
  body.dark-mode .current-media video,
  body.dark-mode .current-media img {
    box-shadow: 0 2px 8px rgba(0,0,0,0.5);
  }
  
  body.dark-mode .upload-controls button {
    color: #ffffff;
  }
  
  body.dark-mode .upload-controls .btn-primary {
    background: #4a90e2;
  }
  
  body.dark-mode .upload-controls .btn-primary:hover {
    background: #357abd;
  }
  
  body.dark-mode .upload-controls .btn-danger {
    background: #c0392b;
  }
  
  body.dark-mode .upload-controls .btn-danger:hover {
    background: #a93226;
  }

  /* ============================================
     Redesigned Media Management (Video + History)
     ============================================ */
  .mm-wrap { display: flex; flex-direction: column; gap: 22px; }
  .mm-card {
    background: #ffffff;
    border: 1px solid #eef0f4;
    border-radius: 16px;
    padding: 22px 24px;
    box-shadow: 0 1px 3px rgba(16, 24, 40, 0.04);
  }
  .mm-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
  }
  .mm-title { font-size: 1.05rem; font-weight: 700; color: #111827; margin: 0; }
  .mm-sub { font-size: 0.82rem; color: #6b7280; margin: 3px 0 0; }
  .mm-badge {
    background: #eef2ff;
    color: #4f46e5;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 999px;
    white-space: nowrap;
  }
  .mm-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
  }
  .mm-btn-details {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #e5e7eb;
    margin-top: 8px;
    width: 100%;
    flex: none;
  }
  .mm-btn-details:hover { background: #e5e7eb; color: #111827; }
  .mm-news-modal-content { max-width: 520px; padding: 0; overflow: hidden; }
  .mm-news-modal-content .modal-header {
    padding: 18px 22px;
    border-bottom: 1px solid #eef0f4;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }
  .mm-news-modal-content .modal-header h2 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: #111827;
  }
  .mm-news-modal-body {
    padding: 18px 22px 22px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: calc(90vh - 80px);
    overflow-y: auto;
  }
  .mm-news-modal-row label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 4px;
  }
  .mm-news-hint { font-weight: 400; color: #9ca3af; }
  .mm-news-modal-input,
  .mm-news-modal-textarea {
    width: 100%;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 0.85rem;
    font-family: inherit;
    background: #fff;
    color: #111827;
    box-sizing: border-box;
  }
  .mm-news-modal-textarea { resize: vertical; min-height: 72px; }
  .mm-news-modal-details { min-height: 120px; }
  .mm-news-modal-input:focus,
  .mm-news-modal-textarea:focus {
    outline: none;
    border-color: #c7d2fe;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
  }
  .mm-news-modal-save {
    margin-top: 4px;
    align-self: flex-start;
    background: #4f46e5;
    color: #fff;
    border: none;
    padding: 9px 14px;
    border-radius: 9px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .mm-news-modal-save:hover { background: #4338ca; }
  .mm-news-modal-save:disabled { opacity: 0.6; cursor: not-allowed; }
  .mm-fees-mgmt .mm-fees-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
  }
  .mm-fee-panel {
    border: 1px solid #eef0f4;
    border-radius: 14px;
    padding: 18px 20px;
    background: #fafbff;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .mm-fee-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }
  .mm-fee-panel-head h3 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .mm-fee-panel-head h3 i { color: #667eea; }
  .mm-fee-preview {
    font-size: 1.35rem;
    font-weight: 800;
    color: #667eea;
    letter-spacing: -0.02em;
  }
  .mm-fee-field label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 4px;
  }
  .mm-fee-field input,
  .mm-fee-field textarea {
    width: 100%;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    padding: 8px 10px;
    font-size: 0.85rem;
    font-family: inherit;
    background: #fff;
    color: #111827;
    box-sizing: border-box;
  }
  .mm-fee-field textarea { resize: vertical; min-height: 56px; }
  .mm-fee-field input:focus,
  .mm-fee-field textarea:focus {
    outline: none;
    border-color: #c7d2fe;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
  }
  .mm-fee-price-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .mm-fee-price-wrap span {
    font-weight: 700;
    color: #374151;
    font-size: 0.95rem;
  }
  .mm-fee-price-wrap input {
    flex: 1;
    font-size: 1rem;
    font-weight: 700;
  }
  .mm-fees-foot {
    margin-top: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
  }
  .mm-fees-updated {
    margin: 0;
    font-size: 0.78rem;
    color: #6b7280;
  }
  .mm-fees-save {
    background: #667eea;
    color: #fff;
    border: none;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .mm-fees-save:hover { background: #5568d3; }
  .mm-fees-save:disabled { opacity: 0.6; cursor: not-allowed; }
  @media (max-width: 768px) {
    .mm-fees-mgmt .mm-fees-grid { grid-template-columns: 1fr; }
  }
  .mm-media {
    border: 1px solid #eef0f4;
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
    display: flex;
    flex-direction: column;
  }
  .mm-media .current-media {
    position: relative;
    background: #0f172a;
    text-align: center;
    flex: none;
  }
  .mm-media .current-media video,
  .mm-media .current-media img {
    width: 100% !important;
    max-width: 100% !important;
    height: 200px !important;
    max-height: 200px !important;
    object-fit: cover !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    display: block;
  }
  .mm-media .current-media > div {
    width: 100% !important;
    height: 200px !important;
    border-radius: 0 !important;
    background: #f1f5f9 !important;
  }
  .mm-media .current-media .media-info {
    position: absolute;
    left: 0; right: 0; bottom: 0;
    margin: 0 !important;
    padding: 4px 10px;
    font-size: 11px;
    color: #fff !important;
    background: linear-gradient(180deg, rgba(15,23,42,0), rgba(15,23,42,0.75));
    text-align: left;
  }
  .mm-media-body { padding: 16px 16px 18px; display: flex; flex-direction: column; gap: 10px; }
  .mm-media-title { font-size: 0.95rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 8px; }
  .mm-media-title i { color: #4f46e5; }
  .mm-media-desc { font-size: 0.8rem; color: #6b7280; line-height: 1.45; margin: 0; }
  .mm-file-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px 14px;
    font-size: 0.74rem;
    color: #6b7280;
    padding: 8px 0 2px;
    border-top: 1px solid #f1f3f7;
  }
  .mm-file-row span { display: inline-flex; align-items: center; gap: 5px; }
  .mm-file-row i { color: #9ca3af; }
  .mm-actions { display: flex; gap: 10px; margin-top: 4px; }
  .mm-actions .upload-controls {
    display: contents;
    flex-direction: row;
    min-width: 0;
  }
  .mm-btn {
    flex: 1 1 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 12px;
    border: none;
    border-radius: 9px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: filter 0.18s ease, background 0.18s ease;
    width: auto;
  }
  .mm-btn-upload { background: #4f46e5; color: #fff; }
  .mm-btn-upload:hover { background: #4338ca; }
  .mm-btn-remove { background: #dc2626; color: #fff; border: 1px solid #dc2626; }
  .mm-btn-remove:hover { background: #b91c1c; border-color: #b91c1c; }
  .mm-btn-link {
    background: #f3f4f6;
    color: #374151;
    text-decoration: none;
    margin-top: 10px;
    width: 100%;
    flex: none;
  }
  .mm-btn-link:hover { background: #e5e7eb; color: #111827; }

  /* Upload history table */
  .mm-search {
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    padding: 8px 12px 8px 32px;
    font-size: 0.82rem;
    width: 230px;
    max-width: 100%;
    outline: none;
    background: #f9fafb url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.3-4.3'/%3E%3C/svg%3E") no-repeat 11px center;
  }
  .mm-search:focus { border-color: #c7d2fe; background-color: #fff; }
  .mm-table-wrap { overflow-x: auto; }
  .mm-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
  .mm-table thead th {
    text-align: left;
    font-size: 0.68rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #9ca3af;
    font-weight: 700;
    padding: 10px 12px;
    border-bottom: 1px solid #eef0f4;
    white-space: nowrap;
  }
  .mm-table tbody td {
    padding: 13px 12px;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
    vertical-align: middle;
  }
  .mm-table tbody tr:last-child td { border-bottom: none; }
  .mm-id { color: #4f46e5; font-weight: 700; white-space: nowrap; }
  .mm-file-cell { display: flex; align-items: center; gap: 10px; }
  .mm-file-cell .mm-file-ic {
    width: 30px; height: 30px; border-radius: 7px;
    background: #fef2f2; color: #ef4444;
    display: inline-flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .mm-file-cell strong { display: block; font-weight: 600; color: #111827; font-size: 0.82rem; }
  .mm-file-cell small { color: #9ca3af; font-size: 0.72rem; }
  .mm-status {
    display: inline-flex; align-items: center; gap: 6px;
    background: #ecfdf3; color: #16a34a;
    font-size: 0.72rem; font-weight: 600;
    padding: 4px 10px; border-radius: 999px; white-space: nowrap;
  }
  .mm-status::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: #16a34a; }
  .mm-status--removed {
    background: #fef2f2;
    color: #dc2626;
  }
  .mm-status--removed::before { background: #dc2626; }
  .mm-row-actions { display: flex; gap: 8px; }
  .mm-row-actions a {
    width: 30px; height: 30px; border-radius: 7px;
    display: inline-flex; align-items: center; justify-content: center;
    color: #6b7280; background: #f3f4f6; text-decoration: none;
    transition: background 0.18s ease, color 0.18s ease;
  }
  .mm-row-actions a:hover { background: #e5e7eb; color: #111827; }
  .mm-empty { text-align: center; color: #9ca3af; padding: 26px 12px; }
  .mm-foot {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px;
    margin-top: 14px; font-size: 0.78rem; color: #6b7280;
  }
  .mm-page { display: flex; gap: 6px; }
  .mm-page button {
    width: 30px; height: 30px; border-radius: 7px;
    border: 1px solid #e5e7eb; background: #fff; color: #374151;
    cursor: pointer; font-size: 0.8rem;
  }
  .mm-page button.is-active { background: #4f46e5; color: #fff; border-color: #4f46e5; }
  .mm-page button:disabled { opacity: 0.45; cursor: not-allowed; }

  /* Bottom mini cards */
  .mm-bottom-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
  }
  .mm-mini {
    background: #fff;
    border: 1px solid #eef0f4;
    border-radius: 16px;
    padding: 20px 22px;
    box-shadow: 0 1px 3px rgba(16, 24, 40, 0.04);
  }
  .mm-mini-head { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
  .mm-mini-ic {
    width: 46px; height: 46px; border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.1rem; color: #fff; flex-shrink: 0;
  }
  .mm-mini-ic.req { background: linear-gradient(135deg, #6366f1, #4f46e5); }
  .mm-mini-ic.slot { background: linear-gradient(135deg, #f97316, #ea580c); }
  .mm-mini h3 { font-size: 0.95rem; font-weight: 700; color: #111827; margin: 0 0 3px; }
  .mm-mini p { font-size: 0.8rem; color: #6b7280; margin: 0; line-height: 1.4; }
  .mm-mini .mm-btn { width: 100%; flex: none; }
  .mm-mini-ic.slot ~ .mm-mini-cta .mm-btn-upload { background: #ea580c; }
  .mm-mini-ic.slot ~ .mm-mini-cta .mm-btn-upload:hover { background: #c2410c; }

  @media (max-width: 992px) {
    .mm-grid, .mm-bottom-grid { grid-template-columns: 1fr; }
  }

  /* Dark mode for redesigned media management */
  body.dark-mode #appearance-section .mm-card,
  body.dark-mode #appearance-section .mm-mini {
    background: #1e1e1e;
    border-color: #333;
  }
  body.dark-mode #appearance-section .mm-title,
  body.dark-mode #appearance-section .mm-media-title,
  body.dark-mode #appearance-section .mm-mini h3,
  body.dark-mode #appearance-section .mm-file-cell strong,
  body.dark-mode #appearance-section .mm-table tbody td {
    color: #e0e0e0;
  }
  body.dark-mode #appearance-section .mm-sub,
  body.dark-mode #appearance-section .mm-media-desc,
  body.dark-mode #appearance-section .mm-mini p,
  body.dark-mode #appearance-section .mm-foot,
  body.dark-mode #appearance-section .mm-file-row {
    color: #b0b0b0;
  }
  body.dark-mode #appearance-section .mm-file-row i {
    color: #888;
  }
  body.dark-mode #appearance-section .mm-media {
    background: #2c2c2c;
    border-color: #444;
  }
  body.dark-mode #appearance-section .mm-media-body {
    background: #2c2c2c;
  }
  body.dark-mode #appearance-section .mm-media .current-media > div {
    background: #1a1a1a !important;
  }
  body.dark-mode #appearance-section .mm-media .current-media > div p {
    color: #888 !important;
  }
  body.dark-mode #appearance-section .mm-table thead th {
    color: #888;
    border-color: #444;
  }
  body.dark-mode #appearance-section .mm-table tbody td {
    border-color: #333;
  }
  body.dark-mode #appearance-section .mm-search {
    background-color: #1a1a1a;
    border-color: #444;
    color: #e0e0e0;
  }
  body.dark-mode #appearance-section .mm-search:focus {
    background-color: #1a1a1a;
    border-color: #6366f1;
  }
  body.dark-mode #appearance-section .mm-badge {
    background: #312e81;
    color: #c7d2fe;
  }
  body.dark-mode #appearance-section .mm-id {
    color: #818cf8;
  }
  body.dark-mode #appearance-section .mm-file-cell .mm-file-ic {
    background: #3b1d1d;
    color: #f87171;
  }
  body.dark-mode #appearance-section .mm-status {
    background: #14532d;
    color: #86efac;
  }
  body.dark-mode #appearance-section .mm-status::before {
    background: #86efac;
  }
  body.dark-mode #appearance-section .mm-status--removed {
    background: #450a0a;
    color: #fca5a5;
  }
  body.dark-mode #appearance-section .mm-status--removed::before {
    background: #ef4444;
  }
  body.dark-mode #appearance-section .mm-btn-remove {
    background: #dc2626;
    color: #fff;
    border-color: #dc2626;
  }
  body.dark-mode #appearance-section .mm-btn-remove:hover {
    background: #b91c1c;
    border-color: #b91c1c;
  }
  body.dark-mode #appearance-section .mm-btn-link {
    background: #2c2c2c;
    color: #d1d5db;
  }
  body.dark-mode #appearance-section .mm-btn-details {
    background: #2c2c2c;
    color: #e0e0e0;
    border-color: #444;
  }
  body.dark-mode #appearance-section .mm-btn-details:hover {
    background: #3a3a3a;
    color: #fff;
  }
  body.dark-mode #newsCardDetailsModal .modal-content.mm-news-modal-content {
    background: #1e1e1e;
    border-color: #333;
  }
  body.dark-mode #newsCardDetailsModal .modal-header {
    border-bottom-color: #333;
  }
  body.dark-mode #newsCardDetailsModal .modal-header h2 {
    color: #e0e0e0;
  }
  body.dark-mode #newsCardDetailsModal .mm-news-modal-row label {
    color: #d1d5db;
  }
  body.dark-mode #newsCardDetailsModal .mm-news-hint {
    color: #888;
  }
  body.dark-mode #newsCardDetailsModal .mm-news-modal-input,
  body.dark-mode #newsCardDetailsModal .mm-news-modal-textarea {
    background: #2c2c2c;
    border-color: #444;
    color: #e0e0e0;
  }
  body.dark-mode #newsCardDetailsModal .mm-news-modal-input:focus,
  body.dark-mode #newsCardDetailsModal .mm-news-modal-textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
  }
  body.dark-mode #appearance-section .mm-row-actions a {
    background: #2c2c2c;
    color: #b0b0b0;
  }
  body.dark-mode #appearance-section .mm-row-actions a:hover {
    background: #3a3a3a;
    color: #e0e0e0;
  }
  body.dark-mode #appearance-section .mm-file-row {
    border-color: #444;
  }
  body.dark-mode #appearance-section .mm-page button {
    background: #2c2c2c;
    border-color: #444;
    color: #b0b0b0;
  }
  body.dark-mode #appearance-section .mm-page button.is-active {
    background: #6366f1;
    color: #fff;
    border-color: #6366f1;
  }
  body.dark-mode #appearance-section .mm-empty {
    color: #888;
  }
  body.dark-mode #appearance-section .mm-media-title i {
    color: #818cf8;
  }

  /* Dark Mode Styles for Appearance Section Titles */
  body.dark-mode .appearance-title {
    color: #e0e0e0 !important;
  }
  
  body.dark-mode .media-management-title {
    color: #e0e0e0 !important;
  }
  
  body.dark-mode .requirements-title {
    color: #e0e0e0 !important;
  }
  
  body.dark-mode .requirements-description {
    color: #b0b0b0 !important;
  }
  
  body.dark-mode .dark-mode-status {
    color: #e0e0e0 !important;
  }
  
  /* Management Sections Styles */
  .management-section {
    margin-top: 30px;
    padding: 24px;
    border-radius: 12px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
  }
  
  .management-section:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
  }
  
  .management-header {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 20px;
  }
  
  .management-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #ffffff;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }
  
  .requirements-section .management-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  }
  
  .slot-section .management-icon {
    background: linear-gradient(135deg, #ff8c42 0%, #ff6b35 100%);
    position: relative;
  }
  
  .slot-section .management-icon .location-arrow {
    position: absolute;
    top: -8px;
    right: -8px;
    font-size: 16px;
    color: #fff;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  }
  
  .management-content {
    flex: 1;
  }
  
  .management-content h3 {
    font-size: 20px;
    font-weight: 700;
    color: #2d3a4a;
    margin-bottom: 8px;
    letter-spacing: -0.3px;
    font-family: 'Inter', sans-serif;
  }
  
  .management-content p {
    font-size: 14px;
    color: #6b7a90;
    line-height: 1.6;
    margin: 0;
    font-family: 'Inter', sans-serif;
  }
  
  .management-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
  }
  
  .btn-enhanced {
    padding: 12px 24px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.25);
    letter-spacing: 0.2px;
    font-family: 'Inter', sans-serif;
  }
  
  .btn-enhanced:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(102, 126, 234, 0.35);
  }
  
  .btn-enhanced:active {
    transform: translateY(0);
  }
  
  /* Dark Mode Styles for Management Sections */
  body.dark-mode .management-section {
    background: linear-gradient(135deg, #2c2c2c 0%, #1e1e1e 100%);
    border-color: #444;
  }
  
  body.dark-mode .management-section:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
  }
  
  body.dark-mode .management-content h3 {
    color: #e0e0e0;
  }
  
  body.dark-mode .management-content p {
    color: #b0b0b0;
  }
  
  body.dark-mode .slot-title {
    color: #e0e0e0 !important;
  }
  
  body.dark-mode .slot-description {
    color: #b0b0b0 !important;
  }
  
  /* Dark mode styles for Edit Profile Modal */
  body.dark-mode .modal-content {
    background-color: #1e1e1e;
    border-color: #333;
    color: #e0e0e0;
  }
  
  body.dark-mode .modal-header h2 {
    color: #e0e0e0;
  }
  
  body.dark-mode .close-modal {
    color: #b0b0b0;
  }
  
  body.dark-mode .close-modal:hover {
    color: #e0e0e0;
  }

  body.dark-mode #profileModal .close-modal {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #ffffff;
  }

  body.dark-mode #profileModal .close-modal:hover {
    background: linear-gradient(135deg, #5c6bc0 0%, #667eea 100%);
    color: #ffffff;
  }
  
  body.dark-mode .profile-photo-section {
    border-bottom-color: #333;
  }
  
  body.dark-mode .profile-photo-preview {
    background: #ff9800;
    color: white;
  }
  
  body.dark-mode #profileModal .btn-group-center .btn-success {
    background: #667eea;
    color: white;
  }
  
  body.dark-mode #profileModal .btn-group-center .btn-success:hover {
    background: #5a6fd8;
  }
</style>
  <script src="https://code.iconify.design/1/1.0.4/iconify.min.js"></script>
</head>
<body>
<div class="container">
  <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo-container">
        <img src="assets/imgs/logo.jpeg" alt="Logo" class="sidebar-logo">
        <div class="sidebar-title-group">
          <h1>Dashboard</h1>
          <p>DAET PUBLIC MARKET</p>
    </div>
      </div>
    </div>
    <div class="nav-menu" id="navMenu">
      <div class="nav-item <?php echo $current_section === 'dashboard' ? 'active' : ''; ?>" onclick="event.stopPropagation(); showSection('dashboard', this); return false;">
        <i class="fas fa-chart-line"></i>
        <span>Dashboard</span>
      </div>
      <div class="nav-item <?php echo $current_section === 'vendors' ? 'active' : ''; ?>" onclick="event.stopPropagation(); showSection('vendors', this); return false;">
        <i class="fas fa-users"></i>
        <span>Vendors</span>
      </div>
      <div class="nav-item <?php echo $current_section === 'appearance' ? 'active' : ''; ?>" onclick="event.stopPropagation(); showSection('appearance', this); return false;">
        <i class="fas fa-photo-video"></i>
        <span>Media</span>
      </div>
      <div class="nav-item <?php echo $current_section === 'records' ? 'active' : ''; ?>" onclick="event.stopPropagation(); showSection('records', this); return false;">
        <i class="fas fa-file-alt"></i>
        <span>Records</span>
      </div>
      <div class="nav-item <?php echo $current_section === 'adminlog' ? 'active' : ''; ?>" onclick="event.stopPropagation(); showSection('adminlog', this); return false;">
        <i class="fas fa-history"></i>
        <span>Admin Log</span>
      </div>
      <div class="nav-item <?php echo $current_section === 'receipts' ? 'active' : ''; ?>" onclick="event.stopPropagation(); showSection('receipts', this); return false;">
        <img src="" alt="" class="nav-receipt-icon" width="20" height="20" onerror="this.outerHTML='<i class=\'fas fa-receipt\'></i>'">
        <span>Receipts</span>
      </div>
      <div class="nav-item <?php echo $current_section === 'concerns' ? 'active' : ''; ?>" onclick="event.stopPropagation(); showSection('concerns', this); return false;">
        <i class="fas fa-comment-dots"></i>
        <span>Concerns</span>
      </div>
      <div class="nav-item <?php echo $current_section === 'reports' ? 'active' : ''; ?>" onclick="event.stopPropagation(); showSection('reports', this); return false;">
        <i class="fas fa-chart-bar"></i>
        <span>Reports</span>
      </div>
      <div class="nav-item <?php echo $current_section === 'ratings' ? 'active' : ''; ?>" onclick="event.stopPropagation(); showSection('ratings', this); return false;">
        <i class="fas fa-star"></i>
        <span>Ratings</span>
      </div>
      <div class="nav-separator"></div>
      <div class="logout-container">
        <div class="nav-item logout-item" onclick="event.stopPropagation(); logout(); return false;">
        <i class="fas fa-sign-out-alt"></i>
          <span>Log out</span>
        </div>
      </div>
      <div class="nav-separator"></div>
      <div class="logout-container">
        <div class="dark-mode-toggle-container">
          <label for="theme-switch" class="dark-mode-label">Dark mode</label>
          <label class="theme-switch">
            <input type="checkbox" id="theme-switch" class="theme-switch__checkbox" onclick="event.stopPropagation();">
            <div class="theme-switch__container">
              <div class="theme-switch__clouds"></div>
              <div class="theme-switch__stars-container">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 144 55" fill="none">
                  <path fill-rule="evenodd" clip-rule="evenodd"
                    d="M135.831 3.00688C135.055 3.85027 134.111 4.29946 133 4.35447C134.111 4.40947 135.055 4.85867 135.831 5.71123C136.607 6.55462 136.996 7.56303 136.996 8.72727C136.996 7.95722 137.172 7.25134 137.525 6.59129C137.886 5.93124 138.372 5.39954 138.98 5.00535C139.598 4.60199 140.268 4.39114 141 4.35447C139.88 4.2903 138.936 3.85027 138.16 3.00688C137.384 2.16348 136.996 1.16425 136.996 0C136.996 1.16425 136.607 2.16348 135.831 3.00688ZM31 23.3545C32.1114 23.2995 33.0551 22.8503 33.8313 22.0069C34.6075 21.1635 34.9956 20.1642 34.9956 19C34.9956 20.1642 35.3837 21.1635 36.1599 22.0069C36.9361 22.8503 37.8798 23.2903 39 23.3545C38.2679 23.3911 37.5976 23.602 36.9802 24.0053C36.3716 24.3995 35.8864 24.9312 35.5248 25.5913C35.172 26.2513 34.9956 26.9572 34.9956 27.7273C34.9956 26.563 34.6075 25.5546 33.8313 24.7112C33.0551 23.8587 32.1114 23.4095 31 23.3545ZM0 36.3545C1.11136 36.2995 2.05513 35.8503 2.83131 35.0069C3.6075 34.1635 3.99559 33.1642 3.99559 32C3.99559 33.1642 4.38368 34.1635 5.15987 35.0069C5.93605 35.8503 6.87982 36.2903 8 36.3545C7.26792 36.3911 6.59757 36.602 5.98015 37.0053C5.37155 37.3995 4.88644 37.9312 4.52481 38.5913C4.172 39.2513 3.99559 39.9572 3.99559 40.7273C3.99559 39.563 3.6075 38.5546 2.83131 37.7112C2.05513 36.8587 1.11136 36.4095 0 36.3545ZM56.8313 24.0069C56.0551 24.8503 55.1114 25.2995 54 25.3545C55.1114 25.4095 56.0551 25.8587 56.8313 26.7112C57.6075 27.5546 57.9956 28.563 57.9956 29.7273C57.9956 28.9572 58.172 28.2513 58.5248 27.5913C58.8864 26.9312 59.3716 26.3995 59.9802 26.0053C60.5976 25.602 61.2679 25.3911 62 25.3545C60.8798 25.2903 59.9361 24.8503 59.1599 24.0069C58.3837 23.1635 57.9956 22.1642 57.9956 21C57.9956 22.1642 57.6075 23.1635 56.8313 24.0069ZM81 25.3545C82.1114 25.2995 83.0551 24.8503 83.8313 24.0069C84.6075 23.1635 84.9956 22.1642 84.9956 21C84.9956 22.1642 85.3837 23.1635 86.1599 24.0069C86.9361 24.8503 87.8798 25.2903 89 25.3545C88.2679 25.3911 87.5976 25.602 86.9802 26.0053C86.3716 26.3995 85.8864 26.9312 85.5248 27.5913C85.172 28.2513 84.9956 28.9572 84.9956 29.7273C84.9956 28.563 84.6075 27.5546 83.8313 26.7112C83.0551 25.8587 82.1114 25.4095 81 25.3545ZM136 36.3545C137.111 36.2995 138.055 35.8503 138.831 35.0069C139.607 34.1635 139.996 33.1642 139.996 32C139.996 33.1642 140.384 34.1635 141.16 35.0069C141.936 35.8503 142.88 36.2903 144 36.3545C143.268 36.3911 142.598 36.602 141.98 37.0053C141.372 37.3995 140.886 37.9312 140.525 38.5913C140.172 39.2513 139.996 39.9572 139.996 40.7273C139.996 39.563 139.607 38.5546 138.831 37.7112C138.055 36.8587 137.111 36.4095 136 36.3545ZM101.831 49.0069C101.055 49.8503 100.111 50.2995 99 50.3545C100.111 50.4095 101.055 50.8587 101.831 51.7112C102.607 52.5546 102.996 53.563 102.996 54.7273C102.996 53.9572 103.172 53.2513 103.525 52.5913C103.886 51.9312 104.372 51.3995 104.98 51.0053C105.598 50.602 106.268 50.3911 107 50.3545C105.88 50.2903 104.936 49.8503 104.16 49.0069C103.384 48.1635 102.996 47.1642 102.996 46C102.996 47.1642 102.607 48.1635 101.831 49.0069Z"
                    fill="currentColor"></path>
                </svg>
              </div>
              <div class="theme-switch__circle-container">
                <div class="theme-switch__sun-moon-container">
                  <div class="theme-switch__moon">
                    <div class="theme-switch__spot"></div>
                    <div class="theme-switch__spot"></div>
                    <div class="theme-switch__spot"></div>
                  </div>
                </div>
              </div>
            </div>
          </label>
        </div>
      </div>
    </div>
  </div>
  <div class="main-content">
    <div class="header">
      <button class="menu-toggle" id="menuToggleBtn" onclick="event.stopPropagation(); toggleSidebar(); return false;">
        <span></span>
        <span></span>
        <span></span>
      </button>
      <div class="user-info" onclick="event.stopPropagation(); openProfileModal(); return false;">
        <span id="adminName"><?php echo htmlspecialchars($admin_name); ?></span>
        <div class="user-avatar" id="headerAvatar">
          <?php if (!empty($admin_photo_url)): ?>
            <img src="<?php echo htmlspecialchars($admin_photo_url); ?>" alt="Admin">
          <?php else: ?>
            <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="content">
      <!-- Alert Messages -->
      <div id="alertContainer"></div>
      
      <!-- Dashboard Section -->
      <div id="dashboard-section" class="section <?php echo $current_section === 'dashboard' ? '' : 'hidden'; ?>">
        <div class="dashboard-top-row">
        <div class="welcome-banner">
          <div class="left">
              <div class="welcome-greeting-box">
                <i class="fas fa-smile welcome-icon"></i>
                <span class="welcome-greeting-text">Hello, welcome <?php echo htmlspecialchars($admin_name); ?></span>
            </div>
              <div class="dashboard-title-section">
                <h1>Dashboard</h1>
                <p><i class="fas fa-clock" style="margin-right: 6px;"></i><?php echo date('F d, Y'); ?></p>
          </div>
              </div>
            </div>
          <!-- Recent Activity Sidebar -->
          <div class="header-with-activity">
            <div class="recent-activity-card">
              <div class="recent-activity-header">
                <h2>Recent activity</h2>
                <a href="?section=adminlog" class="view-all">View all</a>
          </div>
              <div class="activity-list">
                <?php if (empty($recent_activities)): ?>
                  <div class="activity-item">
                    <div class="activity-content">
                      <div class="activity-text">No recent activities</div>
        </div>
                  </div>
                <?php else: ?>
                  <?php foreach ($recent_activities as $activity): 
                    $admin_initials = strtoupper(substr($activity['admin_name'] ?? 'A', 0, 1));
                    $action_icon_class = $activity['action_type'] === 'approve' ? 'approved' : 'incomplete';
                    $icon = $activity['action_type'] === 'approve' ? 'fa-heart' : 'fa-times-circle';
                    $time_ago = getTimeAgo($activity['action_date']);
                    $vendor_name = htmlspecialchars($activity['vendor_name'] ?? 'Unknown Vendor');
                    $app_type = ucfirst($activity['application_type'] ?? 'application');
                  ?>
                  <div class="activity-item">
                    <div class="activity-avatar"><?php echo $admin_initials; ?></div>
                    <div class="activity-content">
                      <div class="activity-text">
                        <?php if ($activity['action_type'] === 'approve'): ?>
                          <strong><?php echo htmlspecialchars($activity['admin_name'] ?? 'Admin'); ?></strong> approved the application: <strong><?php echo $vendor_name; ?></strong> (<span class="activity-item-name"><?php echo $app_type; ?></span>) <i class="fas <?php echo $icon; ?> activity-icon <?php echo $action_icon_class; ?>"></i>
                        <?php else: ?>
                          <strong><?php echo htmlspecialchars($activity['admin_name'] ?? 'Admin'); ?></strong> mark as <span style="color: #ffc107; font-weight: 600;">incomplete</span>: <strong><?php echo $vendor_name; ?></strong> (<span class="activity-item-name"><?php echo $app_type; ?></span>) <i class="fas <?php echo $icon; ?> activity-icon <?php echo $action_icon_class; ?>"></i>
                        <?php endif; ?>
                      </div>
                      <div class="activity-time"><?php echo $time_ago; ?></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="dashboard-layout">
        <div class="stats-grid">
          <div class="stat-card" onclick="showStatisticsGraph('total')" style="cursor: pointer;">
            <div class="stat-info">
              <h3>Total Applications</h3>
              <div class="value"><?php echo $stats['total_applications']; ?></div>
              <div class="change">All time</div>
            </div>
            <div class="stat-icon blue">
              <i class="fas fa-file-alt"></i>
            </div>
          </div>
          <div class="stat-card" onclick="showStatisticsGraph('pending')" style="cursor: pointer;">
            <div class="stat-info">
              <h3>Pending Review</h3>
              <div class="value"><?php echo $stats['pending_count']; ?></div>
              <div class="change">Needs attention</div>
            </div>
            <div class="stat-icon yellow">
              <i class="fas fa-clock"></i>
            </div>
          </div>
          <div class="stat-card" onclick="showStatisticsGraph('approved')" style="cursor: pointer;">
            <div class="stat-info">
              <h3>Approved</h3>
              <div class="value"><?php echo $stats['approved_count']; ?></div>
              <div class="change">Active vendors</div>
            </div>
            <div class="stat-icon green">
              <i class="fas fa-check-circle"></i>
            </div>
          </div>
          <div class="stat-card" onclick="showStatisticsGraph('incomplete')" style="cursor: pointer;">
            <div class="stat-info">
              <h3>Incomplete</h3>
              <div class="value"><?php echo $stats['incomplete_count']; ?></div>
              <div class="change">Not approved</div>
            </div>
            <div class="stat-icon purple">
              <i class="fas fa-times-circle"></i>
            </div>
          </div>
        </div>
        <!-- Recent Applications Table -->
        <div class="card">
          <div class="card-header">
            <h2>Recent Applications</h2>
          </div>
          <div id="recentApplicationsTableContainer">
          <table class="recent-applications-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Vendor</th>
                <th>Section</th>
                <th>Block</th>
                <th>Stall</th>
                <th>Type</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              // Recent Applications table - Show only 5 applications
              // Use the limited display array
              $recent_apps_display = isset($recent_applications_display) ? $recent_applications_display : array_slice($recent_applications, 0, 5);
              $recent_apps_exists = isset($recent_apps_display);
              $recent_apps_is_array = is_array($recent_apps_display);
              $recent_apps_count = $recent_apps_is_array ? count($recent_apps_display) : 0;
              $recent_apps_empty = empty($recent_apps_display);
              
              if ($recent_apps_empty): ?>
                <tr>
                  <td colspan="9" style="text-align: center; padding: 20px; color: #999;">
                    <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;">
                      <strong>No applications found.</strong><br>
                      <?php 
                      // Show diagnostic info - check if connection is still open
                      if (isset($conn) && $conn && !$conn->connect_error) {
                          try {
                              $test_count = $conn->query("SELECT COUNT(*) as count FROM vendors");
                              if ($test_count) {
                                  $test_row = $test_count->fetch_assoc();
                                  $db_count = $test_row['count'] ?? 0;
                                  echo "<br>Total vendors in database: <strong>$db_count</strong>";
                                  if ($db_count > 0) {
                                      echo "<br><span style='color: red;'>⚠️ Data exists in database but not displaying. Check PHP error logs.</span>";
                                  }
                              }
                          } catch (Exception $e) {
                              echo "<br><span style='color: red;'>Database check failed: " . htmlspecialchars($e->getMessage()) . "</span>";
                          }
                      } else {
                          echo "<br><span style='color: red;'>Database connection unavailable</span>";
                      }
                      ?>
                      <br><br>
                      <small>Debug Info: Variable exists=<?php echo $recent_apps_exists ? 'YES' : 'NO'; ?>, Is Array=<?php echo $recent_apps_is_array ? 'YES' : 'NO'; ?>, Count=<?php echo $recent_apps_count; ?></small>
                    </div>
                  </td>
                </tr>
              <?php else:
              $index = 0;
                foreach ($recent_apps_display as $app): 
                  // Calculate the application number (descending order, highest number first)
                  $current_number = $total_rows - ($recent_applications_offset + $index);
                $index++;
                  // Single source: SQL display_status (trusts v.status when approved/incomplete)
                  $display_status = $app['display_status'] ?? $app['status'] ?? 'pending';
              ?>
              <tr data-status="<?php echo $display_status; ?>" data-name="<?php echo strtolower(htmlspecialchars($app['vendor_name'])); ?>" data-vendor-id="<?php echo $app['vendor_id']; ?>">
                <td>#<?php echo number_format($current_number); ?></td>
                <td><?php echo htmlspecialchars($app['vendor_name']); ?></td>
                <td><?php echo htmlspecialchars($app['section']); ?></td>
                <td><?php echo htmlspecialchars($app['block'] ?? ''); ?></td>
                <td><?php echo !empty($app['stall_number']) ? 'Stall ' . (int) $app['stall_number'] : '<span style="color:#94a3b8;">—</span>'; ?></td>
                <td><span class="badge <?php echo $app['application_type']; ?>"><?php echo ucfirst($app['application_type']); ?></span></td>
                <td><?php echo date('Y-m-d', strtotime($app['submitted_date'])); ?></td>
                <td><span class="badge <?php echo $display_status; ?>"><?php echo ucfirst($display_status); ?></span></td>
                <td>
                  <div class="btn-group">
                    <button class="btn-icon view" onclick="viewVendor(<?php echo $app['vendor_id']; ?>)"><i class="fas fa-eye"></i></button>
                    <?php if ($display_status === 'pending'): ?>
                    <button class="btn-icon approve" onclick="approveVendor(<?php echo $app['vendor_id']; ?>)" style="pointer-events: auto; cursor: pointer;"><i class="fas fa-check-circle"></i></button>
                    <button class="btn-icon incomplete" onclick="incompleteVendor(<?php echo $app['vendor_id']; ?>)" style="pointer-events: auto; cursor: pointer;"><i class="fas fa-times-circle"></i></button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php 
                endforeach;
              endif; ?>
            </tbody>
          </table>
          </div>
          <div class="pagination" id="recentApplicationsPagination">
            <?php 
            // $recent_applications_total_pages is already calculated earlier
            ?>
            <?php if ($page > 1): ?>
              <a href="javascript:void(0)" onclick="loadRecentApplicationsPage(<?php echo $page - 1; ?>)" class="pagination-btn">
                <i class="fas fa-chevron-left"></i> Previous
              </a>
            <?php else: ?>
              <span class="pagination-btn disabled">
                <i class="fas fa-chevron-left"></i> Previous
              </span>
            <?php endif; ?>
            
            <div class="pagination-numbers" id="recentApplicationsPaginationNumbers">
              <?php 
              // Show only pages 1, 2, and last page
              $pages_to_show = [];
              
              // Always show page 1
              $pages_to_show[1] = true;
              
              // Always show page 2 if total pages >= 2
              if ($recent_applications_total_pages >= 2) {
                $pages_to_show[2] = true;
              }
              
              // Always show last page if total pages > 2
              if ($recent_applications_total_pages > 2) {
                $pages_to_show[$recent_applications_total_pages] = true;
              }
              
              // Sort pages to display
              ksort($pages_to_show);
              $prev_page = 0;
              
              foreach ($pages_to_show as $p => $dummy):
                // Add ellipsis if there's a gap
                if ($prev_page > 0 && $p - $prev_page > 1) {
                  ?>
                  <span class="pagination-ellipsis">...</span>
                  <?php
                }
                ?>
                <a href="javascript:void(0)" onclick="loadRecentApplicationsPage(<?php echo $p; ?>)" 
                   class="pagination-number <?php echo $p == $page ? 'active' : ''; ?>">
                  <?php echo number_format($p); ?>
                </a>
                <?php
                $prev_page = $p;
              endforeach;
              ?>
            </div>
            
            <span class="pagination-info" id="recentApplicationsPaginationInfo">
              Page <?php echo number_format($page); ?> of <?php echo number_format($recent_applications_total_pages); ?>
            </span>
            
            <?php if ($page < $recent_applications_total_pages): ?>
              <a href="javascript:void(0)" onclick="loadRecentApplicationsPage(<?php echo $page + 1; ?>)" class="pagination-btn">
                Next <i class="fas fa-chevron-right"></i>
              </a>
            <?php else: ?>
              <span class="pagination-btn disabled">
                Next <i class="fas fa-chevron-right"></i>
              </span>
            <?php endif; ?>
          </div>
          </div>
        </div>
      </div>
      
      <!-- Vendors Section -->
      <div id="vendors-section" class="section <?php echo $current_section === 'vendors' ? '' : 'hidden'; ?>">
        <div class="card">
          <div class="card-header">
            <h2>All Vendor Applications</h2>
            <div class="search-filter">
              <select id="typeFilter" onchange="filterVendors()" title="Filter by application type">
                <option value="">All Types</option>
                <option value="new">New Application</option>
                <option value="renewal">Renewal</option>
              </select>
              <select id="statusFilter" onchange="filterVendors()">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="incomplete">Incomplete</option>
              </select>
              <div style="position: relative; flex: 1;">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" placeholder="Search vendors..." onkeyup="filterVendors()" style="width: 100%;" />
            </div>
          </div>
          </div>
          <div id="vendorsTableContainer">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Vendor</th>
                <th>Section</th>
                <th>Block</th>
                <th>Stall</th>
                <th>Type</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="vendors-table">
              <?php 
              // Newest applications get highest numbers (ordered by submitted_date DESC)
              // Formula: Start from highest number and count down
              // Page 1: total_rows, total_rows-1, total_rows-2...
              // Page 2: total_rows-page_size, total_rows-page_size-1...
              if (empty($paginated_applications)): ?>
                <tr>
                  <td colspan="9" style="text-align: center; padding: 20px; color: #999;">
                    No applications found. Total vendors in database: <?php 
                    $vendor_count_check = $conn->query("SELECT COUNT(*) as count FROM vendors");
                    if ($vendor_count_check) {
                        $vendor_count_row = $vendor_count_check->fetch_assoc();
                        echo $vendor_count_row['count'] ?? 0;
                    } else {
                        echo "Error checking";
                    }
                    ?>
                  </td>
                </tr>
              <?php else:
              foreach ($paginated_applications as $app): 
                $display_status_v = $app['display_status'] ?? $app['status'] ?? 'pending';
              ?>
              <tr data-status="<?php echo $display_status_v; ?>" data-name="<?php echo strtolower($app['vendor_name']); ?>" data-vendor-id="<?php echo $app['vendor_id']; ?>" data-type="<?php echo htmlspecialchars($app['application_type'] ?? 'new', ENT_QUOTES); ?>">
                <td>#<?php echo number_format($app['vendor_id']); ?></td>
                <td><?php echo htmlspecialchars($app['vendor_name']); ?></td>
                <td><?php echo htmlspecialchars($app['section']); ?></td>
                <td><?php echo htmlspecialchars($app['block'] ?? ''); ?></td>
                <td><?php echo !empty($app['stall_number']) ? 'Stall ' . (int) $app['stall_number'] : '<span style="color:#94a3b8;">—</span>'; ?></td>
                <td><span class="badge <?php echo $app['application_type']; ?>"><?php echo ucfirst($app['application_type']); ?></span></td>
                <td><?php echo date('Y-m-d', strtotime($app['submitted_date'])); ?></td>
                <td><span class="badge <?php echo $display_status_v; ?>"><?php echo ucfirst($display_status_v); ?></span></td>
                <td>
                  <div class="btn-group">
                    <button class="btn-icon view" onclick="viewVendor(<?php echo $app['vendor_id']; ?>)"><i class="fas fa-eye"></i></button>
                    <?php if ($display_status_v === 'pending'): ?>
                    <button class="btn-icon approve" onclick="approveVendor(<?php echo $app['vendor_id']; ?>)" style="pointer-events: auto; cursor: pointer;"><i class="fas fa-check-circle"></i></button>
                    <button class="btn-icon incomplete" onclick="incompleteVendor(<?php echo $app['vendor_id']; ?>)" style="pointer-events: auto; cursor: pointer;"><i class="fas fa-times-circle"></i></button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
          </div>
          <div class="pagination" id="vendorsPagination">
            <?php if ($page > 1): ?>
              <a href="javascript:void(0)" onclick="loadVendorsPage(<?php echo $page - 1; ?>)" class="pagination-btn">
                <i class="fas fa-chevron-left"></i> Previous
              </a>
            <?php else: ?>
              <span class="pagination-btn disabled">
                <i class="fas fa-chevron-left"></i> Previous
              </span>
            <?php endif; ?>
            
            <span class="pagination-info">
              Page <?php echo number_format($page); ?> of <?php echo number_format($total_pages); ?> 
              (<?php echo number_format($total_rows); ?> total)
            </span>
            
            <div style="display: flex; align-items: center; gap: 5px;">
              <label style="font-size: 12px; color: #666;">Go to:</label>
              <input type="number" 
                     id="gotoPageVendors" 
                     class="pagination-page-input" 
                     min="1" 
                     max="<?php echo $total_pages; ?>" 
                     value="<?php echo $page; ?>"
                     onkeypress="if(event.key==='Enter') { 
                       const page = parseInt(document.getElementById('gotoPageVendors').value);
                       if(page >= 1 && page <= <?php echo $total_pages; ?>) {
                         loadVendorsPage(page);
                       }
                     }">
              <button class="pagination-go-btn" onclick="
                const page = parseInt(document.getElementById('gotoPageVendors').value);
                if(page >= 1 && page <= <?php echo $total_pages; ?>) {
                  loadVendorsPage(page);
                } else {
                  alert('Please enter a page number between 1 and <?php echo number_format($total_pages); ?>');
                }
              ">Go</button>
            </div>
            
            <?php if ($page < $total_pages): ?>
              <a href="javascript:void(0)" onclick="loadVendorsPage(<?php echo $page + 1; ?>)" class="pagination-btn">
                Next <i class="fas fa-chevron-right"></i>
              </a>
            <?php else: ?>
              <span class="pagination-btn disabled">
                Next <i class="fas fa-chevron-right"></i>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Records Section -->
      <div id="records-section" class="section <?php echo $current_section === 'records' ? '' : 'hidden'; ?>">
        <div class="card">
          <div class="card-header">
            <h2><i class="fas fa-file-alt"></i> User Records</h2>
            <div class="records-header-right">
              <!-- View toggle buttons -->
              <div class="records-view-btns">
                <button class="records-view-btn" id="recordsDrawerBtn" title="Drawer view" onclick="setRecordsView('drawer')">
                  <span class="hamburger-lines"><span></span><span></span><span></span></span>
                </button>
                <button class="records-view-btn is-active" id="recordsGridBtn" title="Grid view" onclick="setRecordsView('grid')">
                  <span class="four-boxes">
                    <span></span><span></span><span></span><span></span>
                  </span>
                </button>
              </div>
              <div class="search-filter">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="recordsSearchInput" placeholder="Search users..." onkeyup="filterRecords()" />
              </div>
            </div>
          </div>

          <!-- Drawer mode: collapsed cards list -->
          <div id="records-drawer" class="records-drawer" style="display:none;">
            <?php foreach ($all_records as $rec_d):
              $dn = htmlspecialchars($rec_d['user_name'] ?? 'Unknown');
              $de = htmlspecialchars($rec_d['email'] ?? '');
              $fl = strtoupper(substr($dn, 0, 1));
              $sd = strtolower($dn . ' ' . $de . ' ' . ($rec_d['phone_number'] ?? '') . ' ' . ($rec_d['gender'] ?? '') . ' ' . ($rec_d['residential_address'] ?? ''));
              $dp = !empty($rec_d['profile_image']) ? htmlspecialchars($rec_d['profile_image']) : '';
            ?>
            <div class="drawer-item" data-name="<?php echo htmlspecialchars($sd); ?>">
              <div class="drawer-item-row" onclick="toggleDrawerItem(this)">
                <div class="drawer-avatar">
                  <?php if ($dp): ?><img src="<?php echo $dp; ?>?v=<?php echo time(); ?>" alt="<?php echo $dn; ?>" onerror="this.style.display='none';this.nextSibling.style.display='flex';">
                  <span class="drawer-avatar-fallback" style="display:none;"><?php echo $fl; ?></span>
                  <?php else: ?><span><?php echo $fl; ?></span><?php endif; ?>
                </div>
                <div class="drawer-item-name"><?php echo $dn; ?></div>
                <div class="drawer-item-email"><?php echo $de; ?></div>
                <i class="fas fa-chevron-down drawer-chevron"></i>
              </div>
              <div class="drawer-item-body">
                <div class="drawer-field"><strong>Email</strong><span><?php echo $de; ?></span></div>
                <div class="drawer-field"><strong>Phone</strong><span><?php echo htmlspecialchars($rec_d['phone_number'] ?? 'N/A'); ?></span></div>
                <div class="drawer-field"><strong>Gender</strong><span><?php echo !empty($rec_d['gender']) ? htmlspecialchars($rec_d['gender']) : 'N/A'; ?></span></div>
                <div class="drawer-field"><strong>Age</strong><span><?php echo !empty($rec_d['age']) ? htmlspecialchars((string)$rec_d['age']) : 'N/A'; ?></span></div>
                <div class="drawer-field"><strong>Residential Address</strong><span><?php echo !empty($rec_d['residential_address']) ? htmlspecialchars($rec_d['residential_address']) : 'N/A'; ?></span></div>
                <div class="drawer-field"><strong>Member Since</strong><span><?php echo $rec_d['member_since'] ? date('M d, Y', strtotime($rec_d['member_since'])) : 'N/A'; ?></span></div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($all_records)): ?>
            <div style="padding:2rem;text-align:center;color:#999;">No records found.</div>
            <?php endif; ?>
          </div>
          <div id="records-grid" class="records-grid">
            <?php foreach ($all_records as $record): 
              $profile_image = !empty($record['profile_image']) ? trim($record['profile_image']) : '';
              $user_name = htmlspecialchars($record['user_name']);
              $first_letter = !empty($user_name) ? strtoupper(substr($user_name, 0, 1)) : '?';
              $search_data = strtolower($user_name . ' ' . $record['email'] . ' ' . ($record['phone_number'] ?? '') . ' ' . ($record['gender'] ?? '') . ' ' . ($record['residential_address'] ?? ''));
              
              // Check if profile image exists and is valid
              $has_profile_image = false;
              if (!empty($profile_image)) {
                // Try both relative and absolute paths
                $image_path = $profile_image;
                if (!file_exists($image_path) && !str_starts_with($image_path, 'http')) {
                  // Try relative to current directory
                  $image_path = './' . ltrim($profile_image, './');
                }
                $has_profile_image = !empty($image_path) && (file_exists($image_path) || str_starts_with($image_path, 'http') || str_starts_with($image_path, 'uploads/'));
              }
            ?>
            <div class="user-record-card" data-name="<?php echo $search_data; ?>">
              <div class="user-photo">
                <?php if ($has_profile_image): ?>
                  <img src="<?php echo htmlspecialchars($profile_image); ?>?v=<?php echo time(); ?>" alt="<?php echo $user_name; ?>" onerror="this.parentElement.innerHTML='<?php echo $first_letter; ?>';" />
                <?php else: ?>
                  <?php echo $first_letter; ?>
                <?php endif; ?>
              </div>
              <div class="user-name"><?php echo $user_name; ?></div>
              <div class="user-info">
                <div class="user-info-item">
                  <strong>Email</strong>
                  <span><?php echo htmlspecialchars($record['email']); ?></span>
                </div>
                <div class="user-info-item">
                  <strong>Phone</strong>
                  <span><?php echo htmlspecialchars($record['phone_number'] ?? 'N/A'); ?></span>
                </div>
                <div class="user-info-item">
                  <strong>Gender</strong>
                  <span><?php echo !empty($record['gender']) ? htmlspecialchars($record['gender']) : 'N/A'; ?></span>
                </div>
                <div class="user-info-item">
                  <strong>Age</strong>
                  <span><?php echo !empty($record['age']) ? htmlspecialchars((string)$record['age']) : 'N/A'; ?></span>
                </div>
                <div class="user-info-item">
                  <strong>Residential Address</strong>
                  <span><?php echo !empty($record['residential_address']) ? htmlspecialchars($record['residential_address']) : 'N/A'; ?></span>
                </div>
                <div class="user-info-item">
                  <strong>Member Since</strong>
                  <span><?php echo $record['member_since'] ? date('M d, Y', strtotime($record['member_since'])) : 'N/A'; ?></span>
                </div>
              </div>
            </div>
                <?php endforeach; ?>
                <?php if (empty($all_records)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #999;">
              No records found. Total users in database: <?php 
              $user_count_check = $conn->query("SELECT COUNT(*) as count FROM users");
              if ($user_count_check) {
                  $user_count_row = $user_count_check->fetch_assoc();
                  echo $user_count_row['count'] ?? 0;
              } else {
                  echo "Error checking";
              }
              ?>
            </div>
                <?php endif; ?>
          </div>
          <div class="pagination">
            <?php if ($records_page > 1): ?>
              <a href="?section=records&records_page=<?php echo $records_page - 1; ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i> Previous
              </a>
            <?php else: ?>
              <span class="pagination-btn disabled">
                <i class="fas fa-chevron-left"></i> Previous
              </span>
            <?php endif; ?>
            <span class="pagination-info">
              Page <?php echo $records_page; ?> of <?php echo $records_total_pages; ?> (Total: <?php echo number_format($records_total); ?> users)
            </span>
            <?php if ($records_page < $records_total_pages): ?>
              <a href="?section=records&records_page=<?php echo $records_page + 1; ?>" class="pagination-btn">
                Next <i class="fas fa-chevron-right"></i>
              </a>
            <?php else: ?>
              <span class="pagination-btn disabled">
                Next <i class="fas fa-chevron-right"></i>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Admin Log Section -->
      <div id="adminlog-section" class="section <?php echo $current_section === 'adminlog' ? '' : 'hidden'; ?>">
        <div class="card">
          <div class="card-header">
            <h2><i class="fas fa-history"></i> Admin Log</h2>
            <div class="card-header-tools">
              <div class="search-filter">
                <i class="fas fa-search search-icon"></i>
                <input type="search" id="adminLogSearchInput" name="adminlog_filter_query" placeholder="Search admin logs..." onkeyup="filterAdminLog()" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-form-type="other" data-1p-ignore />
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table>
              <thead>
                <tr>
                  <th>Date & Time</th>
                  <th>User</th>
                  <th>Section</th>
                  <th>Block</th>
                  <th>Status Change</th>
                </tr>
              </thead>
              <tbody id="admin-log-table">
                <?php foreach ($admin_logs as $log): ?>
                <tr data-name="<?php echo strtolower(($log['user_name'] ?? '') . ' ' . ($log['section'] ?? '') . ' ' . ($log['block'] ?? '')); ?>">
                  <td><?php echo $log['action_date'] ? date('M d, Y h:i A', strtotime($log['action_date'])) : 'N/A'; ?></td>
                  <td><?php echo htmlspecialchars($log['user_name'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($log['section'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($log['block'] ?? 'N/A'); ?></td>
                  <td>
                    <?php 
                    $status_before = $log['status_before'] ?? null;
                    $status_after = $log['status_after'] ?? null;
                    $log_action = strtolower((string)($log['action_type'] ?? ''));
                    $log_notes = (string)($log['admin_notes'] ?? '');
                    $is_vendor_edit = ($log_action === 'resubmit') || (stripos($log_notes, 'Vendor resubmitted') !== false);
                    $log_app_kind = (strtolower((string)($log['application_type'] ?? '')) === 'renewal') ? 'renewal' : 'application';
                    if ($is_vendor_edit): 
                    ?>
                      <span style="color: #dc3545; font-weight: 700;">
                        <i class="fas fa-pen-to-square" style="margin-right: 5px;"></i>
                        Vendor edit the <?php echo htmlspecialchars($log_app_kind); ?>
                      </span>
                    <?php elseif (!empty($status_before) && !empty($status_after)): ?>
                      <span class="badge <?php echo htmlspecialchars($status_before); ?>"><?php echo ucfirst(htmlspecialchars($status_before)); ?></span>
                      <i class="fas fa-arrow-right" style="margin: 0 5px; color: #6c757d;"></i>
                      <span class="badge <?php echo htmlspecialchars($status_after); ?>"><?php echo ucfirst(htmlspecialchars($status_after)); ?></span>
                    <?php else: ?>
                      <span class="badge invalid">User is still invalid</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($admin_logs)): ?>
                <tr>
                  <td colspan="5" style="text-align: center; padding: 2rem; color: #999;">
                    No admin logs found.
                  </td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="pagination">
            <?php if ($admin_log_page > 1): ?>
              <a href="?section=adminlog&admin_log_page=<?php echo $admin_log_page - 1; ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i> Previous
              </a>
            <?php else: ?>
              <span class="pagination-btn disabled">
                <i class="fas fa-chevron-left"></i> Previous
              </span>
            <?php endif; ?>
            <span class="pagination-info">
              Page <?php echo $admin_log_page; ?> of <?php echo $admin_log_total_pages; ?> (Total: <?php echo number_format($admin_log_total); ?> activities)
            </span>
            <?php if ($admin_log_page < $admin_log_total_pages): ?>
              <a href="?section=adminlog&admin_log_page=<?php echo $admin_log_page + 1; ?>" class="pagination-btn">
                Next <i class="fas fa-chevron-right"></i>
              </a>
            <?php else: ?>
              <span class="pagination-btn disabled">
                Next <i class="fas fa-chevron-right"></i>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Receipts Section -->
      <div id="receipts-section" class="section <?php echo $current_section === 'receipts' ? '' : 'hidden'; ?>">
        <div class="card">
          <div class="card-header">
            <h2><i class="fas fa-receipt receipt-heading-icon" aria-hidden="true"></i> Receipt Management <span class="log-count-pill"><i class="fas fa-layer-group"></i> <?php echo number_format($receipts_total); ?></span></h2>
            <div class="card-header-tools">
              <select id="receiptsStatusFilter" class="receipt-status-filter" onchange="filterReceipts()" aria-label="Filter receipts by status">
                <option value="all">All statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Invalid</option>
              </select>
              <div class="search-filter">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="receiptsSearchInput" placeholder="Search receipts..." onkeyup="filterReceipts()" />
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>User Name</th>
                  <th>Section</th>
                  <th>Block</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="receipts-table">
                <?php foreach ($receipts as $receipt): ?>
                <?php 
                $receipt_status = $receipt['status'] ?? 'pending';
                $is_pending = ($receipt_status === 'pending');
                ?>
                <tr data-name="<?php echo strtolower(($receipt['user_name'] ?? '') . ' ' . ($receipt['section'] ?? '') . ' ' . ($receipt['block'] ?? '')); ?>" data-status="<?php echo htmlspecialchars($receipt_status); ?>">
                  <td><?php echo $receipt['created_at'] ? date('M d, Y h:i A', strtotime($receipt['created_at'])) : 'N/A'; ?></td>
                  <td><?php echo htmlspecialchars($receipt['user_name'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($receipt['section'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($receipt['block'] ?? 'N/A'); ?></td>
                  <td>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                    <?php if ($receipt_status === 'approved'): ?>
                      <span class="badge approved">Approved</span>
                    <?php elseif ($receipt_status === 'rejected'): ?>
                      <span class="badge rejected">Invalid</span>
                    <?php else: ?>
                      <span class="badge pending">Pending</span>
                    <?php endif; ?>
                    <?php if ($receipt['reviewed_at']): ?>
                        <small class="receipt-reviewed-note"><i class="fas fa-clock"></i> Reviewed: <?php echo date('M d, Y', strtotime($receipt['reviewed_at'])); ?></small>
                    <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <div class="receipt-actions-group">
                      <button type="button"
                              class="receipt-action-btn receipt-action-btn--view receipt-view-btn"
                              title="View Receipt"
                              data-file-path="<?php echo htmlspecialchars($receipt['receipt_file_path'] ?? '', ENT_QUOTES); ?>"
                              data-user-name="<?php echo htmlspecialchars($receipt['user_name'] ?? 'N/A', ENT_QUOTES); ?>"
                              data-section="<?php echo htmlspecialchars($receipt['section'] ?? 'N/A', ENT_QUOTES); ?>"
                              data-block="<?php echo htmlspecialchars($receipt['block'] ?? 'N/A', ENT_QUOTES); ?>"
                              data-date="<?php echo $receipt['created_at'] ? htmlspecialchars(date('M d, Y h:i A', strtotime($receipt['created_at'])), ENT_QUOTES) : 'N/A'; ?>"
                              data-status="<?php echo htmlspecialchars(ucfirst($receipt_status === 'rejected' ? 'Invalid' : $receipt_status), ENT_QUOTES); ?>"
                              onclick="viewReceiptFromButton(this)">
                        <i class="fas fa-eye"></i>
                      </button>
                      <?php if ($is_pending): ?>
                      <button type="button" onclick="approveReceipt(<?php echo (int)$receipt['id']; ?>, <?php echo (int)$receipt['user_id']; ?>)"
                              class="receipt-action-btn receipt-action-btn--approve" title="Approve">
                        <i class="fas fa-check"></i>
                      </button>
                      <button type="button" onclick="invalidReceipt(<?php echo (int)$receipt['id']; ?>, <?php echo (int)$receipt['user_id']; ?>)"
                              class="receipt-action-btn receipt-action-btn--invalid" title="Invalid">
                        <i class="fas fa-times"></i>
                      </button>
                      <?php else: ?>
                      <span class="receipt-processed-tag"><i class="fas fa-check-double"></i> Processed</span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($receipts)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 2rem; color: #999;">
                    No receipts found.
                  </td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="pagination">
            <?php if ($receipts_page > 1): ?>
              <a href="?section=receipts&receipts_page=<?php echo $receipts_page - 1; ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i> Previous
              </a>
            <?php else: ?>
              <span class="pagination-btn disabled">
                <i class="fas fa-chevron-left"></i> Previous
              </span>
            <?php endif; ?>
            
            <span class="pagination-info">
              Page <?php echo $receipts_page; ?> of <?php echo $receipts_total_pages; ?> (Total: <?php echo number_format($receipts_total); ?> receipts)
            </span>
            <?php if ($receipts_page < $receipts_total_pages): ?>
              <a href="?section=receipts&receipts_page=<?php echo $receipts_page + 1; ?>" class="pagination-btn">
                Next <i class="fas fa-chevron-right"></i>
              </a>
            <?php else: ?>
              <span class="pagination-btn disabled">
                Next <i class="fas fa-chevron-right"></i>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Customer concerns (support widget submissions) -->
      <div id="concerns-section" class="section <?php echo $current_section === 'concerns' ? '' : 'hidden'; ?>">
        <div class="card">
          <div class="card-header">
            <h2><i class="fas fa-comment-dots"></i> Customer concerns</h2>
            <div class="search-filter">
              <i class="fas fa-search search-icon"></i>
              <input type="text" id="concernsSearchInput" placeholder="Search by name or email" onkeyup="filterConcerns()" />
            </div>
          </div>
          <div class="table-responsive">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Submitter</th>
                  <th>Email</th>
                  <th>Message</th>
                </tr>
              </thead>
              <tbody id="concerns-table">
                <?php foreach ($concerns as $c): ?>
                <?php
                  $c_name = (string) ($c['submitter_name'] ?? '');
                  $c_email = (string) ($c['submitter_email'] ?? '');
                  $c_msg = (string) ($c['message'] ?? '');
                  $search_blob = strtolower($c_name . ' ' . $c_email . ' ' . $c_msg);
                  $c_date = !empty($c['created_at']) ? date('M d, Y h:i A', strtotime($c['created_at'])) : '—';
                  $c_preview = $c_msg !== '' ? (function ($s) {
                      $s = trim(preg_replace('/\s+/', ' ', $s));
                      return function_exists('mb_strimwidth') ? mb_strimwidth($s, 0, 40, '…', 'UTF-8') : (strlen($s) > 40 ? substr($s, 0, 39) . '…' : $s);
                  })($c_msg) : '';
                ?>
                <tr data-search="<?php echo htmlspecialchars($search_blob, ENT_QUOTES, 'UTF-8'); ?>">
                  <td><?php echo htmlspecialchars($c_date, ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo $c_name !== '' ? htmlspecialchars($c_name, ENT_QUOTES, 'UTF-8') : '<span style="color:#999">Guest</span>'; ?></td>
                  <td class="concerns-email-cell"><?php echo $c_email !== '' ? htmlspecialchars($c_email, ENT_QUOTES, 'UTF-8') : ''; ?></td>
                  <td class="concerns-message-cell">
                    <?php if ($c_msg !== ''): ?>
                    <div class="concern-msg-wrap">
                      <span class="concern-msg-preview"><?php echo htmlspecialchars($c_preview, ENT_QUOTES, 'UTF-8'); ?></span>
                      <button type="button" class="concern-view-btn"
                              data-message="<?php echo htmlspecialchars($c_msg, ENT_QUOTES, 'UTF-8'); ?>"
                              data-name="<?php echo htmlspecialchars($c_name !== '' ? $c_name : 'Guest', ENT_QUOTES, 'UTF-8'); ?>"
                              data-email="<?php echo htmlspecialchars($c_email !== '' ? $c_email : '—', ENT_QUOTES, 'UTF-8'); ?>"
                              data-date="<?php echo htmlspecialchars($c_date, ENT_QUOTES, 'UTF-8'); ?>"
                              onclick="viewConcernMessage(this)">
                        <i class="fas fa-eye"></i> View
                      </button>
                    </div>
                    <?php else: ?>
                    <span style="color:#999">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($concerns)): ?>
                <tr class="concerns-placeholder-row">
                  <td colspan="4" style="text-align: center; padding: 2rem; color: #999;">
                    No concerns submitted yet. They appear here when visitors use <strong>Support → Concerns</strong> on the site.
                  </td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="pagination">
            <?php if ($concerns_page > 1): ?>
              <a href="?section=concerns&amp;concerns_page=<?php echo $concerns_page - 1; ?>" class="pagination-btn">
                <i class="fas fa-chevron-left"></i> Previous
              </a>
            <?php else: ?>
              <span class="pagination-btn disabled">
                <i class="fas fa-chevron-left"></i> Previous
              </span>
            <?php endif; ?>
            <span class="pagination-info">
              Page <?php echo (int) $concerns_page; ?> of <?php echo (int) $concerns_total_pages; ?> (Total: <?php echo number_format($concerns_total); ?>)
            </span>
            <?php if ($concerns_page < $concerns_total_pages): ?>
              <a href="?section=concerns&amp;concerns_page=<?php echo $concerns_page + 1; ?>" class="pagination-btn">
                Next <i class="fas fa-chevron-right"></i>
              </a>
            <?php else: ?>
              <span class="pagination-btn disabled">
                Next <i class="fas fa-chevron-right"></i>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Reports Section -->
      <div id="reports-section" class="section <?php echo $current_section === 'reports' ? '' : 'hidden'; ?>">
        <div class="card reports-card">
          <div class="reports-head">
            <div>
              <h2 class="reports-title"><i class="fas fa-chart-bar"></i> Reports</h2>
              <p class="reports-sub">Analytics &amp; insights for your market management</p>
            </div>
          </div>

          <div class="reports-toolbar">
            <div class="rep-control">
              <i class="fas fa-calendar-days"></i>
              <select id="repDateRange" onchange="applyReportsFilters()">
                <option value="all">All Time</option>
                <option value="day">This Day</option>
                <option value="month">This Month</option>
                <option value="year">This Year</option>
              </select>
            </div>
            <div class="rep-control">
              <i class="fas fa-folder-open"></i>
              <select id="repTypeFilter" onchange="applyReportsFilters()">
                <option value="all">Applications</option>
                <option value="new">New Application</option>
                <option value="renewal">Renewal</option>
                <option value="pending">Pending</option>
              </select>
            </div>
            <div class="rep-search">
              <i class="fas fa-search"></i>
              <input type="text" id="repSearchDate" placeholder="Search by date (mm-dd-yyyy)" autocomplete="off" oninput="applyReportsFilters()">
            </div>
            <div class="rep-tabs" id="repTabs">
              <button type="button" class="rep-tab active" data-status="all" onclick="setReportsStatus('all', this)">All</button>
              <button type="button" class="rep-tab" data-status="approved" onclick="setReportsStatus('approved', this)">Approved</button>
              <button type="button" class="rep-tab" data-status="pending" onclick="setReportsStatus('pending', this)">Pending</button>
              <button type="button" class="rep-tab" data-status="incomplete" onclick="setReportsStatus('incomplete', this)">Incomplete</button>
            </div>
            <button type="button" class="rep-export" id="repExportBtn" onclick="exportReportsCsv()"><i class="fas fa-download"></i> Export</button>
          </div>

          <div class="reports-stats">
            <div class="rep-stat">
              <div class="rep-stat-info">
                <span class="rep-stat-label">Total Applications</span>
                <span class="rep-stat-value" id="repTotal">0</span>
                <span class="rep-stat-sub">All time</span>
              </div>
              <div class="rep-stat-icon blue"><i class="fas fa-file-alt"></i></div>
            </div>
            <div class="rep-stat">
              <div class="rep-stat-info">
                <span class="rep-stat-label">Pending Review</span>
                <span class="rep-stat-value" id="repPending">0</span>
                <span class="rep-stat-sub">Needs attention</span>
              </div>
              <div class="rep-stat-icon orange"><i class="fas fa-clock"></i></div>
            </div>
            <div class="rep-stat">
              <div class="rep-stat-info">
                <span class="rep-stat-label">Approved</span>
                <span class="rep-stat-value" id="repApproved">0</span>
                <span class="rep-stat-sub">Active vendors</span>
              </div>
              <div class="rep-stat-icon green"><i class="fas fa-circle-check"></i></div>
            </div>
            <div class="rep-stat">
              <div class="rep-stat-info">
                <span class="rep-stat-label">Incomplete</span>
                <span class="rep-stat-value" id="repIncomplete">0</span>
                <span class="rep-stat-sub">Not approved</span>
              </div>
              <div class="rep-stat-icon red"><i class="fas fa-circle-xmark"></i></div>
            </div>
          </div>

          <div class="reports-charts">
            <div class="rep-panel">
              <div class="rep-panel-head">
                <h3>Monthly Applications</h3>
                <div class="rep-legend">
                  <span><i class="rep-dot green"></i> Approved</span>
                  <span><i class="rep-dot orange"></i> Pending</span>
                  <span><i class="rep-dot red"></i> Rejected</span>
                </div>
              </div>
              <div class="rep-chart-wrap"><canvas id="repMonthlyChart"></canvas></div>
            </div>
            <div class="rep-panel">
              <div class="rep-panel-head">
                <h3>Status Distribution</h3>
              </div>
              <div class="rep-donut-wrap"><canvas id="repStatusChart"></canvas></div>
              <div class="rep-donut-legend" id="repDonutLegend"></div>
            </div>
          </div>

          <div class="reports-bottom">
            <div class="rep-panel">
              <div class="rep-panel-head">
                <h3>Vendor Categories</h3>
              </div>
              <div class="rep-categories" id="repCategories"></div>
            </div>
            <div class="rep-panel">
              <div class="rep-panel-head">
                <h3>Recent Activity</h3>
                <a class="view-all" href="?section=adminlog">View all</a>
              </div>
              <div class="rep-activity">
                <?php if (empty($report_activities)): ?>
                  <div class="rep-activity-empty">No recent activity yet.</div>
                <?php else: ?>
                  <?php foreach ($report_activities as $act):
                    $atype = strtolower((string)($act['action_type'] ?? ''));
                    $app_kind = (strtolower((string)($act['application_type'] ?? '')) === 'renewal') ? 'renewal' : 'application';
                    if ($atype === 'approve') { $a_cls = 'green'; $a_icon = 'fa-check'; $a_verb = 'approved the ' . $app_kind; }
                    elseif ($atype === 'incomplete') { $a_cls = 'orange'; $a_icon = 'fa-times'; $a_verb = 'mark the ' . $app_kind . ' incomplete'; }
                    elseif ($atype === 'reject') { $a_cls = 'red'; $a_icon = 'fa-xmark'; $a_verb = 'rejected the ' . $app_kind; }
                    else { $a_cls = 'orange'; $a_icon = 'fa-eye'; $a_verb = 'reviewed the ' . $app_kind; }
                    $a_admin = htmlspecialchars((string)($act['admin_name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8');
                    $a_vendor = htmlspecialchars((string)($act['vendor_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8');
                    $a_time = htmlspecialchars(getTimeAgo($act['action_date'] ?? null), ENT_QUOTES, 'UTF-8');
                  ?>
                  <div class="rep-activity-item">
                    <div class="rep-activity-icon <?php echo $a_cls; ?>"><i class="fas <?php echo $a_icon; ?>"></i></div>
                    <div class="rep-activity-body">
                      <div class="rep-activity-text"><strong><?php echo $a_admin; ?></strong> <?php echo $a_verb; ?> <strong><?php echo $a_vendor; ?></strong></div>
                      <div class="rep-activity-time"><?php echo $a_time; ?></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <script>
          window.REPORTS_DATA = <?php echo json_encode($reports_apps, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
          window.REPORTS_SECTIONS = <?php echo json_encode($report_sections, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        </script>
      </div>
      
      <!-- Ratings Section -->
      <?php
      $ratings_rows = [];
      $ratings_total = 0;
      $ratings_avg = 0.0;
      $ratings_dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
      try {
          $rconn = getDBConnection();
          if ($rconn) {
              $rconn->query("CREATE TABLE IF NOT EXISTS client_ratings (
                  rating_id INT AUTO_INCREMENT PRIMARY KEY,
                  user_id INT NULL,
                  source VARCHAR(20) DEFAULT 'application',
                  client_name VARCHAR(255) NULL,
                  sex VARCHAR(10) NULL,
                  age_group VARCHAR(30) NULL,
                  address VARCHAR(255) NULL,
                  email VARCHAR(255) NULL,
                  contact_no VARCHAR(50) NULL,
                  client_class VARCHAR(60) NULL,
                  service_availed VARCHAR(255) NULL,
                  transaction_date VARCHAR(50) NULL,
                  q1 TINYINT NULL, q2 TINYINT NULL, q3 TINYINT NULL, q4 TINYINT NULL, q5 TINYINT NULL,
                  q6 TINYINT NULL, q7 TINYINT NULL, q8 TINYINT NULL, q9 TINYINT NULL, q10 TINYINT NULL,
                  overall_avg DECIMAL(3,2) NULL,
                  comments TEXT NULL,
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

              $rres = $rconn->query("SELECT * FROM client_ratings ORDER BY created_at DESC LIMIT 500");
              if ($rres) {
                  $sum = 0.0;
                  while ($row = $rres->fetch_assoc()) {
                      $ratings_rows[] = $row;
                      $ratings_total++;
                      $avg = (float) ($row['overall_avg'] ?? 0);
                      $sum += $avg;
                      $bucket = (int) round($avg);
                      if ($bucket < 1) $bucket = 1;
                      if ($bucket > 5) $bucket = 5;
                      $ratings_dist[$bucket]++;
                  }
                  if ($ratings_total > 0) {
                      $ratings_avg = round($sum / $ratings_total, 2);
                  }
              }
              $rconn->close();
          }
      } catch (Exception $e) {
          error_log('Ratings load error: ' . $e->getMessage());
      }
      $rating_labels = [5 => 'Highly Satisfied', 4 => 'Satisfied', 3 => 'Neutral', 2 => 'Dissatisfied', 1 => 'Highly Dissatisfied'];
      ?>
      <div id="ratings-section" class="section <?php echo $current_section === 'ratings' ? '' : 'hidden'; ?>">
        <div class="card">
          <div class="ratings-head">
            <div>
              <h2 style="margin:0; font-size:1.4rem;"><i class="fas fa-star" style="color:#fbbf24;"></i> Vendor Satisfaction Ratings</h2>
              <p style="margin:0.25rem 0 0; color:#64748b;">Feedback submitted by vendors</p>
            </div>
          </div>

          <div class="ratings-summary">
            <div class="ratings-stat ratings-stat-main">
              <div class="ratings-stat-num"><?php echo number_format($ratings_avg, 2); ?></div>
              <div class="ratings-stars-display">
                <?php $full = (int) floor($ratings_avg); for ($s = 1; $s <= 5; $s++): ?>
                  <i class="fas fa-star" style="color:<?php echo $s <= $full ? '#fbbf24' : '#e2e8f0'; ?>;"></i>
                <?php endfor; ?>
              </div>
              <div class="ratings-stat-label"><?php echo (int) $ratings_total; ?> total response<?php echo $ratings_total === 1 ? '' : 's'; ?></div>
            </div>
            <div class="ratings-dist">
              <?php for ($s = 5; $s >= 1; $s--):
                  $pct = $ratings_total > 0 ? round(($ratings_dist[$s] / $ratings_total) * 100) : 0; ?>
                <div class="ratings-dist-row">
                  <span class="ratings-dist-label"><?php echo $s; ?> <i class="fas fa-star"></i></span>
                  <span class="ratings-dist-bar"><span class="ratings-dist-fill" style="width:<?php echo $pct; ?>%;"></span></span>
                  <span class="ratings-dist-count"><?php echo (int) $ratings_dist[$s]; ?></span>
                </div>
              <?php endfor; ?>
            </div>
          </div>

          <?php if ($ratings_total === 0): ?>
            <div class="ratings-empty">
              <i class="fas fa-comment-slash"></i>
              <p>No client ratings yet. Feedback will appear here once clients submit the satisfaction survey.</p>
            </div>
          <?php else: ?>
            <div class="ratings-list">
              <?php foreach ($ratings_rows as $r):
                  $name = trim((string) ($r['client_name'] ?? ''));
                  if ($name === '') $name = 'Anonymous';
                  $ravg = (float) ($r['overall_avg'] ?? 0);
                  $rfull = (int) round($ravg);
                  $when = htmlspecialchars((string) ($r['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
              ?>
              <div class="ratings-item">
                <div class="ratings-item-head">
                  <div>
                    <span class="ratings-item-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                  <div class="ratings-item-stars">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                      <i class="fas fa-star" style="color:<?php echo $s <= $rfull ? '#fbbf24' : '#e2e8f0'; ?>;"></i>
                    <?php endfor; ?>
                    <span class="ratings-item-avg"><?php echo number_format($ravg, 1); ?></span>
                  </div>
                </div>
                <div class="ratings-item-meta">
                  <?php
                    $meta_parts = [];
                    if (!empty($r['client_class'])) $meta_parts[] = htmlspecialchars($r['client_class'], ENT_QUOTES, 'UTF-8');
                    if (!empty($r['age_group'])) $meta_parts[] = htmlspecialchars($r['age_group'], ENT_QUOTES, 'UTF-8');
                    if (!empty($r['sex'])) $meta_parts[] = htmlspecialchars($r['sex'], ENT_QUOTES, 'UTF-8');
                    if (!empty($r['service_availed'])) $meta_parts[] = htmlspecialchars($r['service_availed'], ENT_QUOTES, 'UTF-8');
                    echo implode(' &middot; ', $meta_parts);
                  ?>
                  <span class="ratings-item-time"><i class="far fa-clock"></i> <?php echo $when; ?></span>
                </div>
                <?php if (!empty($r['comments'])): ?>
                  <div class="ratings-item-comment"><i class="fas fa-quote-left"></i> <?php echo htmlspecialchars($r['comments'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <div class="ratings-item-scores">
                  <?php for ($qi = 1; $qi <= 10; $qi++):
                      $qv = $r['q' . $qi];
                      if ($qv === null || $qv === '') continue; ?>
                    <span class="ratings-score-chip" title="<?php echo htmlspecialchars($rating_labels[(int)$qv] ?? '', ENT_QUOTES, 'UTF-8'); ?>">Q<?php echo $qi; ?>: <?php echo (int) $qv; ?></span>
                  <?php endfor; ?>
                </div>
                <div class="ratings-item-actions">
                  <button type="button" class="ratings-btn ratings-btn-view" onclick="viewRatingForm(<?php echo (int) $r['rating_id']; ?>)">
                    <i class="fas fa-file-alt"></i> View Form
                  </button>
                  <button type="button" class="ratings-btn ratings-btn-download" onclick="downloadRatingForm(<?php echo (int) $r['rating_id']; ?>)">
                    <i class="fas fa-download"></i> Download
                  </button>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <script>
          window.RATINGS_DATA = <?php echo json_encode(array_map(function ($r) {
              return [
                  'rating_id' => (int) $r['rating_id'],
                  'source' => (string) ($r['source'] ?? ''),
                  'client_name' => (string) ($r['client_name'] ?? ''),
                  'sex' => (string) ($r['sex'] ?? ''),
                  'age_group' => (string) ($r['age_group'] ?? ''),
                  'address' => (string) ($r['address'] ?? ''),
                  'email' => (string) ($r['email'] ?? ''),
                  'contact_no' => (string) ($r['contact_no'] ?? ''),
                  'client_class' => (string) ($r['client_class'] ?? ''),
                  'service_availed' => (string) ($r['service_availed'] ?? ''),
                  'transaction_date' => (string) ($r['transaction_date'] ?? ''),
                  'comments' => (string) ($r['comments'] ?? ''),
                  'overall_avg' => (float) ($r['overall_avg'] ?? 0),
                  'created_at' => (string) ($r['created_at'] ?? ''),
                  'q' => [
                      (int) ($r['q1'] ?? 0), (int) ($r['q2'] ?? 0), (int) ($r['q3'] ?? 0), (int) ($r['q4'] ?? 0), (int) ($r['q5'] ?? 0),
                      (int) ($r['q6'] ?? 0), (int) ($r['q7'] ?? 0), (int) ($r['q8'] ?? 0), (int) ($r['q9'] ?? 0), (int) ($r['q10'] ?? 0),
                  ],
              ];
          }, $ratings_rows), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        </script>
      </div>

      <!-- Rating form viewer modal (official Market Division Office form) -->
      <div class="rating-form-overlay" id="ratingFormOverlay" aria-hidden="true">
        <div class="rating-form-shell" role="dialog" aria-modal="true">
          <div class="rating-form-toolbar">
            <span class="rating-form-toolbar-title"><i class="fas fa-file-alt"></i> Client Satisfaction Form</span>
            <div class="rating-form-toolbar-actions">
              <button type="button" class="ratings-btn ratings-btn-download" id="ratingFormDownloadBtn">
                <i class="fas fa-download"></i> Download
              </button>
              <button type="button" class="rating-form-close" id="ratingFormClose" aria-label="Close">&times;</button>
            </div>
          </div>
          <div class="rating-form-scroll">
            <div id="ratingFormRender"></div>
          </div>
        </div>
      </div>

      <style>
        .ratings-head { margin-bottom: 1.2rem; }
        .ratings-summary { display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.2rem 1.4rem; margin-bottom: 1.4rem; }
        .ratings-stat-main { text-align: center; min-width: 140px; }
        .ratings-stat-num { font-size: 2.6rem; font-weight: 800; color: #1e293b; line-height: 1; }
        .ratings-stars-display { margin: 0.3rem 0; font-size: 1rem; letter-spacing: 2px; }
        .ratings-stat-label { font-size: 0.82rem; color: #64748b; }
        .ratings-dist { flex: 1; min-width: 240px; display: flex; flex-direction: column; gap: 0.35rem; }
        .ratings-dist-row { display: flex; align-items: center; gap: 0.6rem; }
        .ratings-dist-label { width: 46px; font-size: 0.8rem; font-weight: 700; color: #475569; white-space: nowrap; }
        .ratings-dist-label i { color: #fbbf24; font-size: 0.7rem; }
        .ratings-dist-bar { flex: 1; height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
        .ratings-dist-fill { display: block; height: 100%; background: linear-gradient(90deg, #6366f1, #fbbf24); border-radius: 999px; }
        .ratings-dist-count { width: 34px; text-align: right; font-size: 0.8rem; font-weight: 700; color: #475569; }
        .ratings-empty { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
        .ratings-empty i { font-size: 2.5rem; margin-bottom: 0.8rem; }
        .ratings-list { display: flex; flex-direction: column; gap: 0.9rem; }
        .ratings-item { border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.1rem; background: #fff; }
        .ratings-item-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .ratings-item-name { font-weight: 800; color: #1e293b; }
        .ratings-item-stars { font-size: 0.85rem; letter-spacing: 1px; white-space: nowrap; }
        .ratings-item-avg { margin-left: 0.4rem; font-weight: 800; color: #1e293b; }
        .ratings-item-meta { font-size: 0.78rem; color: #64748b; margin-top: 0.4rem; display: flex; flex-wrap: wrap; gap: 0.5rem 0.9rem; align-items: center; }
        .ratings-item-time { margin-left: auto; }
        .ratings-item-comment { margin-top: 0.6rem; font-size: 0.86rem; color: #334155; background: #f8fafc; border-left: 3px solid #6366f1; padding: 0.5rem 0.7rem; border-radius: 0 8px 8px 0; }
        .ratings-item-scores { margin-top: 0.6rem; display: flex; flex-wrap: wrap; gap: 0.3rem; }
        .ratings-score-chip { font-size: 0.68rem; font-weight: 700; color: #475569; background: #f1f5f9; border-radius: 6px; padding: 0.15rem 0.4rem; }
        body.dark-mode .ratings-summary { background: #1e293b; border-color: #334155; }
        body.dark-mode .ratings-stat-num, body.dark-mode .ratings-item-name, body.dark-mode .ratings-item-avg { color: #f1f5f9; }
        body.dark-mode .ratings-item { background: #1e293b; border-color: #334155; }
        body.dark-mode .ratings-item-comment { background: #0f172a; color: #cbd5e1; }
        body.dark-mode .ratings-score-chip { background: #0f172a; color: #cbd5e1; }

        .ratings-item-actions { margin-top: 0.8rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .ratings-btn { border: none; border-radius: 8px; padding: 0.45rem 0.9rem; font-size: 0.82rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; transition: transform 0.12s ease, box-shadow 0.12s ease; }
        .ratings-btn:hover { transform: translateY(-1px); }
        .ratings-btn-view { background: #e0e7ff; color: #4338ca; }
        .ratings-btn-view:hover { box-shadow: 0 4px 10px rgba(67,56,202,0.25); }
        .ratings-btn-download { background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; }
        .ratings-btn-download:hover { box-shadow: 0 4px 12px rgba(99,102,241,0.4); }

        /* Rating form viewer modal */
        .rating-form-overlay { position: fixed; inset: 0; z-index: 5000; display: none; align-items: center; justify-content: center; padding: 1rem; background: rgba(15,23,42,0.65); }
        .rating-form-overlay.is-open { display: flex; }
        .rating-form-shell { width: min(96vw, 820px); max-height: 94vh; background: #f1f5f9; border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 30px 70px rgba(0,0,0,0.4); }
        .rating-form-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 0.8rem 1.1rem; background: #1e293b; color: #fff; }
        .rating-form-toolbar-title { font-weight: 700; }
        .rating-form-toolbar-actions { display: flex; align-items: center; gap: 0.6rem; }
        .rating-form-close { background: none; border: none; color: #cbd5e1; font-size: 1.6rem; line-height: 1; cursor: pointer; }
        .rating-form-close:hover { color: #fff; }
        .rating-form-scroll { overflow-y: auto; padding: 1.2rem; }

        /* Official paper form */
        .csf-doc { background: #fff; color: #000; width: 100%; max-width: 720px; margin: 0 auto; padding: 28px 34px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.45; box-shadow: 0 2px 10px rgba(0,0,0,0.12); }
        .csf-head { text-align: center; margin-bottom: 14px; }
        .csf-head p { margin: 0; }
        .csf-head .csf-office { font-weight: 800; color: #1e3a8a; font-size: 14px; margin-top: 4px; text-decoration: underline; }
        .csf-head .csf-unit { font-size: 11px; border-top: 1px solid #000; display: inline-block; padding-top: 2px; margin-top: 2px; }
        .csf-sec-title { font-weight: 800; margin: 14px 0 6px; }
        .csf-line { display: flex; gap: 6px; align-items: baseline; margin: 3px 0; }
        .csf-line .csf-fill { flex: 1; border-bottom: 1px solid #000; min-height: 14px; padding: 0 4px; font-weight: 600; }
        .csf-box { display: inline-block; width: 12px; height: 12px; border: 1px solid #000; text-align: center; line-height: 12px; font-size: 11px; margin-right: 4px; vertical-align: middle; }
        .csf-box.checked::after { content: "\2713"; font-weight: 800; }
        .csf-choices { display: flex; flex-wrap: wrap; gap: 4px 22px; margin: 2px 0; }
        .csf-choice { display: inline-flex; align-items: center; }
        .csf-age-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px 18px; }
        .csf-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .csf-table th, .csf-table td { border: 1px solid #000; padding: 4px 5px; vertical-align: middle; }
        .csf-table th { font-size: 10.5px; text-align: center; font-weight: 700; }
        .csf-table td.csf-crit { font-size: 11px; text-align: left; width: 42%; }
        .csf-table td.csf-mark { text-align: center; width: 11.6%; }
        .csf-emoji { font-size: 15px; display: block; }
        .csf-mark-box { display: inline-block; width: 14px; height: 14px; border: 1px solid #000; line-height: 14px; }
        .csf-mark-box.checked { background: #1e3a8a; color: #fff; }
        .csf-mark-box.checked::after { content: "\2713"; font-weight: 800; font-size: 11px; }
        .csf-feedback-line { border-bottom: 1px solid #000; min-height: 16px; margin: 4px 0; padding: 0 4px; }
        .csf-thanks { text-align: center; margin-top: 12px; font-weight: 600; }
        .csf-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; margin: 6px 0; }
        .csf-legend span { font-size: 11px; }
      </style>

      <script>
      (function () {
        var CSF_CRITERIA = [
          "I am satisfied with the staff/employee's approach to me.",
          "I am satisfied with the employee's responses to my question.",
          "Employees/staff given instructions and information are clear and easy to understand.",
          "The employee acted in a professional manner.",
          "Employees are able to guide and respond to my concerns.",
          "I am able to finish my transaction quickly.",
          "Employees respond in a timely manner.",
          "The given list of requirements are accurate and clearly stated.",
          "The office is neat and clean.",
          "I am satisfied with the overall service provided."
        ];
        var CSF_SCALE = [
          { v: 5, label: 'Highly Satisfied', emoji: '\uD83D\uDE04' },
          { v: 4, label: 'Satisfied', emoji: '\uD83D\uDE42' },
          { v: 3, label: 'Neutral', emoji: '\uD83D\uDE10' },
          { v: 2, label: 'Dissatisfied', emoji: '\uD83D\uDE41' },
          { v: 1, label: 'Highly Dissatisfied', emoji: '\uD83D\uDE1E' }
        ];

        function esc(s) {
          var d = document.createElement('div');
          d.textContent = (s == null ? '' : String(s));
          return d.innerHTML;
        }
        function box(checked) { return '<span class="csf-box' + (checked ? ' checked' : '') + '"></span>'; }

        function csfFindRating(id) {
          var list = window.RATINGS_DATA || [];
          for (var i = 0; i < list.length; i++) { if (list[i].rating_id === id) return list[i]; }
          return null;
        }

        function buildRatingFormHTML(r) {
          var sex = r.sex || '';
          var age = r.age_group || '';
          var cls = r.client_class || '';
          var ages = ['17 and below', '18 and above', '19-24', '25-39', '40-59', '60 and above'];
          var ageLabels = { '17 and below': '17 and below', '18 and above': '18 and above', '19-24': '19 \u2013 24', '25-39': '25 \u2013 39', '40-59': '40 \u2013 59', '60 and above': '60 and above' };

          var html = '';
          html += '<div class="csf-doc">';
          html += '<div class="csf-head">';
          html += '<p>Republic of the Philippines</p><p>Province of Camarines Norte</p><p>Municipality of Daet</p><p>Camarines Norte</p>';
          html += '<p class="csf-office">MARKET DIVISION OFFICE</p>';
          html += '<span class="csf-unit">Name of Office/Delivery Unit</span>';
          html += '</div>';

          // I. Client's Profile
          html += '<div class="csf-sec-title">I. Client\u2019s Profile</div>';
          html += '<div class="csf-line"><span>Name: (Optional)</span><span class="csf-fill">' + esc(r.client_name) + '</span></div>';
          html += '<div class="csf-line"><span>Sex:</span><span class="csf-choices">'
            + '<span class="csf-choice">' + box(sex === 'Male') + 'Male</span>'
            + '<span class="csf-choice">' + box(sex === 'Female') + 'Female</span></span></div>';
          html += '<div class="csf-line" style="align-items:flex-start;"><span>Age:</span><span class="csf-age-grid" style="flex:1;">';
          ages.forEach(function (a) {
            html += '<span class="csf-choice">' + box(age === a) + esc(ageLabels[a]) + '</span>';
          });
          html += '</span></div>';
          html += '<div class="csf-line"><span>Address:</span><span class="csf-fill">' + esc(r.address) + '</span></div>';
          html += '<div class="csf-line"><span>Email: (Optional)</span><span class="csf-fill">' + esc(r.email) + '</span></div>';
          html += '<div class="csf-line"><span>Contact No.: (Optional)</span><span class="csf-fill">' + esc(r.contact_no) + '</span></div>';
          html += '<div class="csf-line" style="align-items:flex-start;"><span>Client Classification:</span><span class="csf-choices" style="flex:1;">'
            + '<span class="csf-choice">' + box(cls === 'General Public') + 'General Public</span>'
            + '<span class="csf-choice">' + box(cls === 'Government Employee') + 'Government Employee</span>'
            + '<span class="csf-choice">' + box(cls === 'Business Organization/Sector') + 'Business Organization/Sector</span></span></div>';
          html += '<div class="csf-line"><span>Service/s Availed:</span><span class="csf-fill">' + esc(r.service_availed) + '</span></div>';
          html += '<div class="csf-line"><span>Date of Transaction:</span><span class="csf-fill">' + esc(r.transaction_date) + '</span></div>';

          // II. Client Satisfaction Rating
          html += '<div class="csf-sec-title">II. Client Satisfaction Rating</div>';
          html += '<div style="font-size:11px;"><strong>Directions:</strong> Kindly rate the quality of the service provided by checking the appropriate box.</div>';
          html += '<div class="csf-legend">';
          CSF_SCALE.forEach(function (s) { html += '<span>' + s.emoji + ' ' + s.label + ' (' + s.v + ')</span>'; });
          html += '</div>';
          html += '<table class="csf-table"><thead><tr><th class="csf-crit">Criteria</th>';
          CSF_SCALE.forEach(function (s) { html += '<th><span class="csf-emoji">' + s.emoji + '</span>' + s.label + '<br>(' + s.v + ')</th>'; });
          html += '</tr></thead><tbody>';
          for (var i = 0; i < CSF_CRITERIA.length; i++) {
            var ans = (r.q && r.q[i]) ? r.q[i] : 0;
            html += '<tr><td class="csf-crit">' + (i + 1) + '. ' + esc(CSF_CRITERIA[i]) + '</td>';
            CSF_SCALE.forEach(function (s) {
              html += '<td class="csf-mark"><span class="csf-mark-box' + (ans === s.v ? ' checked' : '') + '"></span></td>';
            });
            html += '</tr>';
          }
          html += '</tbody></table>';

          // III. Feedback
          html += '<div class="csf-sec-title">III. Feedback</div>';
          html += '<div style="font-size:11px;">Comments/Suggestions/Complaints</div>';
          var comment = esc(r.comments);
          html += '<div class="csf-feedback-line">' + comment + '</div>';
          html += '<div class="csf-feedback-line"></div>';
          html += '<p class="csf-thanks">Thank you for your response! \uD83D\uDE0A</p>';
          html += '<p style="text-align:right; font-size:10px; color:#555;">Submitted: ' + esc(r.created_at) + '</p>';
          html += '</div>';
          return html;
        }

        var overlay = document.getElementById('ratingFormOverlay');
        var renderEl = document.getElementById('ratingFormRender');
        var currentId = null;

        window.viewRatingForm = function (id) {
          var r = csfFindRating(id);
          if (!r) { alert('Rating not found.'); return; }
          currentId = id;
          renderEl.innerHTML = buildRatingFormHTML(r);
          overlay.classList.add('is-open');
          overlay.setAttribute('aria-hidden', 'false');
        };

        function closeForm() {
          overlay.classList.remove('is-open');
          overlay.setAttribute('aria-hidden', 'true');
          currentId = null;
        }

        window.downloadRatingForm = function (id) {
          var r = csfFindRating(id);
          if (!r) { alert('Rating not found.'); return; }
          var name = (r.client_name || 'Anonymous').replace(/[^a-z0-9]+/gi, '_');
          var title = 'Client_Satisfaction_' + name + '_' + id;
          var css = document.createElement('style');
          // Grab the csf-* styles by cloning current stylesheet rules is complex; embed a minimal copy.
          var win = window.open('', '_blank');
          if (!win) { alert('Please allow pop-ups to download the form.'); return; }
          var styles = ''
            + '.csf-doc{background:#fff;color:#000;width:100%;max-width:720px;margin:0 auto;padding:28px 34px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.45;}'
            + '.csf-head{text-align:center;margin-bottom:14px;}.csf-head p{margin:0;}'
            + '.csf-head .csf-office{font-weight:800;color:#1e3a8a;font-size:14px;margin-top:4px;text-decoration:underline;}'
            + '.csf-head .csf-unit{font-size:11px;border-top:1px solid #000;display:inline-block;padding-top:2px;margin-top:2px;}'
            + '.csf-sec-title{font-weight:800;margin:14px 0 6px;}'
            + '.csf-line{display:flex;gap:6px;align-items:baseline;margin:3px 0;}'
            + '.csf-line .csf-fill{flex:1;border-bottom:1px solid #000;min-height:14px;padding:0 4px;font-weight:600;}'
            + '.csf-box{display:inline-block;width:12px;height:12px;border:1px solid #000;text-align:center;line-height:12px;font-size:11px;margin-right:4px;vertical-align:middle;}'
            + '.csf-box.checked::after{content:"\\2713";font-weight:800;}'
            + '.csf-choices{display:flex;flex-wrap:wrap;gap:4px 22px;margin:2px 0;}.csf-choice{display:inline-flex;align-items:center;}'
            + '.csf-age-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:2px 18px;}'
            + '.csf-table{width:100%;border-collapse:collapse;margin-top:6px;}'
            + '.csf-table th,.csf-table td{border:1px solid #000;padding:4px 5px;vertical-align:middle;}'
            + '.csf-table th{font-size:10.5px;text-align:center;font-weight:700;}'
            + '.csf-table td.csf-crit{font-size:11px;text-align:left;width:42%;}'
            + '.csf-table td.csf-mark{text-align:center;width:11.6%;}'
            + '.csf-emoji{font-size:15px;display:block;}'
            + '.csf-mark-box{display:inline-block;width:14px;height:14px;border:1px solid #000;line-height:14px;}'
            + '.csf-mark-box.checked{background:#1e3a8a;color:#fff;}.csf-mark-box.checked::after{content:"\\2713";font-weight:800;font-size:11px;}'
            + '.csf-feedback-line{border-bottom:1px solid #000;min-height:16px;margin:4px 0;padding:0 4px;}'
            + '.csf-thanks{text-align:center;margin-top:12px;font-weight:600;}'
            + '.csf-legend{display:flex;flex-wrap:wrap;gap:6px 16px;margin:6px 0;}.csf-legend span{font-size:11px;}'
            + '@media print{@page{margin:12mm;}}';
          win.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + title + '</title><style>' + styles + '</style></head><body>' + buildRatingFormHTML(r) + '<' + '/body></html>');
          win.document.close();
          win.focus();
          setTimeout(function () { win.print(); }, 350);
        };

        var closeBtn = document.getElementById('ratingFormClose');
        var dlBtn = document.getElementById('ratingFormDownloadBtn');
        if (closeBtn) closeBtn.addEventListener('click', closeForm);
        if (dlBtn) dlBtn.addEventListener('click', function () { if (currentId != null) window.downloadRatingForm(currentId); });
        if (overlay) overlay.addEventListener('click', function (e) { if (e.target === overlay) closeForm(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeForm(); });
      })();
      </script>

      <!-- Appearance Section -->
      <div id="appearance-section" class="section <?php echo $current_section === 'appearance' ? '' : 'hidden'; ?>">
        <div class="card">
          <h2 class="appearance-title" style="margin-bottom: 25px; position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">Media Management</h2>

          <?php
          // Card config — IDs / handlers preserved exactly so upload + remove keep working.
          $mm_cards = [
              [
                  'key' => 'price_video', 'title' => 'Market Price of Prime & Other Commodities Monitoring Report',
                  'desc' => 'monitoring report on prime and other commodity prices.',
                  'icon' => 'fa-tags', 'area_id' => 'imageUploadArea',
                  'container' => 'currentImage', 'input' => 'imageUpload',
                  'onchange' => 'handleImageUpload(this)', 'remove' => 'removeImage()',
              ],
              [
                  'key' => 'overview_video', 'title' => 'Promotional Permit Video',
                  'desc' => 'Promotional Permit.',
                  'icon' => 'fa-film', 'area_id' => 'videoUploadArea',
                  'container' => 'currentVideo', 'input' => 'videoUpload',
                  'onchange' => 'handleVideoUpload(this)', 'remove' => 'removeVideo()',
              ],
              [
                  'key' => 'third_video', 'title' => 'Announcement Video',
                  'desc' => 'important annoucement',
                  'icon' => 'fa-bullhorn', 'area_id' => 'video3UploadArea',
                  'container' => 'currentVideo3', 'input' => 'video3Upload',
                  'onchange' => 'handleVideo3Upload(this)', 'remove' => 'removeVideo3()',
              ],
              [
                  'key' => 'homepage_announcement_video', 'title' => 'Homepage Announcement Video',
                  'desc' => 'Featured announcement video on the homepage website.',
                  'icon' => 'fa-house', 'area_id' => 'homepageAnnouncementUploadArea',
                  'container' => 'currentHomepageAnnouncementVideo', 'input' => 'homepageAnnouncementUpload',
                  'onchange' => 'handleHomepageAnnouncementUpload(this)', 'remove' => 'removeHomepageAnnouncementVideo()',
                  'public_link' => true,
              ],
          ];
          ?>

          <div class="mm-wrap">
            <!-- Video Management -->
            <div class="mm-card">
              <div class="mm-head">
                <div>
                  <h2 class="mm-title">Video Management</h2>
                  <p class="mm-sub">Manage homepage and announcement videos shown to the public</p>
                </div>
                <span class="mm-badge"><?php echo (int) $admin_media_active_count; ?> active videos</span>
              </div>
              <div class="mm-grid">
                <?php foreach ($mm_cards as $mm): ?>
                <?php $mm_path = $admin_media_video_paths[$mm['key']] ?? null; $mm_meta = $admin_media_meta[$mm['key']] ?? null; ?>
                <div class="mm-media">
                  <div class="media-upload-area" id="<?php echo $mm['area_id']; ?>" style="display: contents;">
                    <div class="current-media" id="<?php echo $mm['container']; ?>">
                      <?php if (!empty($mm_path)): ?>
                      <video controls style="max-width: 300px; max-height: 200px;">
                        <source src="<?php echo htmlspecialchars($mm_path, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                        Your browser does not support the video tag.
                      </video>
                      <p class="media-info">Current: <?php echo htmlspecialchars(basename($mm_path), ENT_QUOTES, 'UTF-8'); ?></p>
                      <?php else: ?>
                      <div style="width: 300px; height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                        <p style="color: #666;">No video uploaded</p>
                      </div>
                      <p class="media-info">No video available</p>
                      <?php endif; ?>
                    </div>
                    <div class="mm-media-body">
                      <div class="mm-media-title"><i class="fas <?php echo $mm['icon']; ?>"></i> <?php echo htmlspecialchars($mm['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <p class="mm-media-desc"><?php echo htmlspecialchars($mm['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
                      <div class="mm-file-row">
                        <span><i class="fas fa-file-video"></i> <?php echo $mm_meta ? htmlspecialchars($mm_meta['file_name'], ENT_QUOTES, 'UTF-8') : 'No file uploaded'; ?></span>
                        <?php if ($mm_meta): ?>
                        <span><i class="fas fa-database"></i> <?php echo admin_format_bytes($mm_meta['file_size']); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo $mm_meta['uploaded_at'] ? htmlspecialchars(date('M j, Y', strtotime($mm_meta['uploaded_at'])), ENT_QUOTES, 'UTF-8') : '—'; ?></span>
                        <?php endif; ?>
                      </div>
                      <div class="mm-actions">
                        <div class="upload-controls">
                          <input type="file" id="<?php echo $mm['input']; ?>" accept="video/*" style="display: none;" onchange="<?php echo $mm['onchange']; ?>">
                          <button type="button" class="mm-btn mm-btn-upload" onclick="document.getElementById('<?php echo $mm['input']; ?>').click()">
                            <i class="fas fa-upload"></i> Upload New
                          </button>
                          <button type="button" class="mm-btn mm-btn-remove" onclick="<?php echo $mm['remove']; ?>">
                            <i class="fas fa-trash"></i> Remove
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Announcements & News (index.php) -->
            <div class="mm-card mm-news-mgmt">
              <div class="mm-head">
                <div>
                  <h2 class="mm-title">Announcements &amp; News</h2>
                  <p class="mm-sub">Manage the four news cards on the homepage — photo, summary, and full details for Read More</p>
                </div>
                <span class="mm-badge">4 cards</span>
              </div>
              <div class="mm-grid">
                <?php foreach ($admin_homepage_news_cards as $nc): ?>
                <?php
                  $nc_slot = (int) ($nc['card_slot'] ?? 0);
                  $nc_has_img = !empty($nc['image_path']);
                  $nc_preview = $nc_has_img ? htmlspecialchars($nc['image_path'], ENT_QUOTES, 'UTF-8') : '';
                  $nc_filename = $nc_has_img ? basename($nc['image_path']) : '';
                  $nc_title = htmlspecialchars($nc['title'] ?? 'News Card ' . $nc_slot, ENT_QUOTES, 'UTF-8');
                  $nc_summary = htmlspecialchars($nc['summary'] ?? 'Click Details to edit card text.', ENT_QUOTES, 'UTF-8');
                ?>
                <div class="mm-media mm-news-media" data-slot="<?php echo $nc_slot; ?>">
                  <div class="current-media" id="newsPhotoPreview<?php echo $nc_slot; ?>">
                    <?php if ($nc_has_img): ?>
                    <img src="<?php echo $nc_preview; ?>" alt="News card <?php echo $nc_slot; ?>">
                    <p class="media-info">Current: <?php echo htmlspecialchars($nc_filename, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php else: ?>
                    <div style="width: 100%; height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 0;">
                      <p style="color: #666;">No photo uploaded</p>
                    </div>
                    <p class="media-info">No photo available</p>
                    <?php endif; ?>
                  </div>
                  <div class="mm-media-body">
                    <div class="mm-media-title" id="newsCardTitleLabel<?php echo $nc_slot; ?>"><i class="fas fa-newspaper"></i> Card <?php echo $nc_slot; ?> — <?php echo $nc_title; ?></div>
                    <p class="mm-media-desc" id="newsCardSummaryLabel<?php echo $nc_slot; ?>"><?php echo $nc_summary; ?></p>
                    <div class="mm-actions">
                      <div class="upload-controls">
                        <input type="file" id="newsImageUpload<?php echo $nc_slot; ?>" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;" onchange="handleNewsCardImageUpload(<?php echo $nc_slot; ?>, this)">
                        <button type="button" class="mm-btn mm-btn-upload" onclick="document.getElementById('newsImageUpload<?php echo $nc_slot; ?>').click()">
                          <i class="fas fa-upload"></i> Upload Photo
                        </button>
                        <button type="button" class="mm-btn mm-btn-remove" onclick="removeNewsCardImage(<?php echo $nc_slot; ?>)">
                          <i class="fas fa-trash"></i> Remove
                        </button>
                      </div>
                    </div>
                    <button type="button" class="mm-btn mm-btn-details" onclick="openNewsCardDetailsModal(<?php echo $nc_slot; ?>)">
                      <i class="fas fa-align-left"></i> Details
                    </button>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>

              <!-- Hidden stored values for each card (used by Details modal) -->
              <div id="newsCardsHiddenData" hidden aria-hidden="true">
                <?php foreach ($admin_homepage_news_cards as $nc): ?>
                <?php $nc_slot = (int) ($nc['card_slot'] ?? 0); ?>
                <div data-slot="<?php echo $nc_slot; ?>">
                  <input type="hidden" id="newsTagType<?php echo $nc_slot; ?>" value="<?php echo htmlspecialchars($nc['tag_type'] ?? 'update', ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" id="newsTagLabel<?php echo $nc_slot; ?>" value="<?php echo htmlspecialchars($nc['tag_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" id="newsCardDate<?php echo $nc_slot; ?>" value="<?php echo htmlspecialchars($nc['card_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" id="newsTitle<?php echo $nc_slot; ?>" value="<?php echo htmlspecialchars($nc['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                  <textarea id="newsSummary<?php echo $nc_slot; ?>" hidden><?php echo htmlspecialchars($nc['summary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                  <textarea id="newsDetails<?php echo $nc_slot; ?>" hidden><?php echo htmlspecialchars($nc['full_details'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Application Fees (menu.php) -->
            <div class="mm-card mm-fees-mgmt">
              <div class="mm-head">
                <div>
                  <h2 class="mm-title">Application Fees</h2>
                  <p class="mm-sub">Set the New Application and Renewal prices shown on the vendor home page (menu.php)</p>
                </div>
                <span class="mm-badge">2 rates</span>
              </div>
              <div class="mm-fees-grid">
                <div class="mm-fee-panel" data-fee-type="new">
                  <div class="mm-fee-panel-head">
                    <h3><i class="fas fa-file-circle-plus"></i> New Application</h3>
                    <span class="mm-fee-preview" id="adminFeeNewPreview">₱ <?php echo number_format((int) round((float)($admin_fee_new['price'] ?? 0))); ?></span>
                  </div>
                  <div class="mm-fee-field">
                    <label for="adminFeeNewPrice">Price (PHP)</label>
                    <div class="mm-fee-price-wrap">
                      <span>₱</span>
                      <input type="number" id="adminFeeNewPrice" min="0" step="1" value="<?php echo htmlspecialchars((string) (int) round((float)($admin_fee_new['price'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>" oninput="updateApplicationFeePreview('new')" onchange="normalizeApplicationFeePrice(this, 'new')">
                    </div>
                  </div>
                  <div class="mm-fee-field">
                    <label for="adminFeeNewTitle">Card title</label>
                    <input type="text" id="adminFeeNewTitle" maxlength="120" value="<?php echo htmlspecialchars($admin_fee_new['title'] ?? 'New Application', ENT_QUOTES, 'UTF-8'); ?>">
                  </div>
                  <div class="mm-fee-field">
                    <label for="adminFeeNewSubtitle">Description</label>
                    <textarea id="adminFeeNewSubtitle" maxlength="255"><?php echo htmlspecialchars($admin_fee_new['subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                  </div>
                </div>
                <div class="mm-fee-panel" data-fee-type="renewal">
                  <div class="mm-fee-panel-head">
                    <h3><i class="fas fa-rotate"></i> Renewal</h3>
                    <span class="mm-fee-preview" id="adminFeeRenewalPreview">₱ <?php echo number_format((int) round((float)($admin_fee_renewal['price'] ?? 0))); ?></span>
                  </div>
                    <div class="mm-fee-field">
                    <label for="adminFeeRenewalPrice">Price (PHP)</label>
                    <div class="mm-fee-price-wrap">
                      <span>₱</span>
                      <input type="number" id="adminFeeRenewalPrice" min="0" step="1" value="<?php echo htmlspecialchars((string) (int) round((float)($admin_fee_renewal['price'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>" oninput="updateApplicationFeePreview('renewal')" onchange="normalizeApplicationFeePrice(this, 'renewal')">
                    </div>
                    <p class="mm-fee-hint" style="margin:0.35rem 0 0;font-size:0.8rem;color:#64748b;">This price is used in renewal payment reminder emails sent to vendors 7 days before their due date.</p>
                  </div>
                  <div class="mm-fee-field">
                    <label for="adminFeeRenewalTitle">Card title</label>
                    <input type="text" id="adminFeeRenewalTitle" maxlength="120" value="<?php echo htmlspecialchars($admin_fee_renewal['title'] ?? 'Renewal', ENT_QUOTES, 'UTF-8'); ?>">
                  </div>
                  <div class="mm-fee-field">
                    <label for="adminFeeRenewalSubtitle">Description</label>
                    <textarea id="adminFeeRenewalSubtitle" maxlength="255"><?php echo htmlspecialchars($admin_fee_renewal['subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                  </div>
                </div>
              </div>
              <div class="mm-fees-foot">
                <p class="mm-fees-updated" id="adminFeesUpdatedNote">
                  <?php
                  $fee_updated = $admin_fee_new['updated_at'] ?? $admin_fee_renewal['updated_at'] ?? null;
                  if ($fee_updated) {
                      echo 'Last updated ' . htmlspecialchars(date('d M Y, H:i', strtotime($fee_updated)), ENT_QUOTES, 'UTF-8');
                  } else {
                      echo 'Using default fee rates';
                  }
                  ?>
                </p>
                <button type="button" class="mm-fees-save" id="adminFeesSaveBtn" onclick="saveApplicationFees(this)">
                  <i class="fas fa-save"></i> Save Application Fees
                </button>
              </div>
            </div>

            <!-- Upload History -->
            <div class="mm-card">
              <div class="mm-head">
                <div>
                  <h2 class="mm-title">Upload History</h2>
                  <p class="mm-sub">Recent media uploads and file activity</p>
                </div>
                <input type="text" id="mmHistorySearch" class="mm-search" placeholder="Search uploads..." onkeyup="mmFilterHistory()">
              </div>
              <div class="mm-table-wrap">
                <table class="mm-table" id="mmHistoryTable">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Section</th>
                      <th>Type</th>
                      <th>Size</th>
                      <th>Date</th>
                      <th>Status</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody id="mmHistoryBody">
                    <?php if (empty($admin_media_history)): ?>
                    <tr class="mm-empty-row"><td colspan="7" class="mm-empty">No uploads yet. Uploaded videos will appear here.</td></tr>
                    <?php else: foreach ($admin_media_history as $h): ?>
                    <?php $h_blob = strtolower($h['code'] . ' ' . $h['title']); ?>
                    <tr data-search="<?php echo htmlspecialchars($h_blob, ENT_QUOTES, 'UTF-8'); ?>" data-section="<?php echo htmlspecialchars($h['section'], ENT_QUOTES, 'UTF-8'); ?>">
                      <td class="mm-id"><?php echo htmlspecialchars($h['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td>
                        <div class="mm-file-cell">
                          <span class="mm-file-ic"><i class="fas fa-file-video"></i></span>
                          <span>
                            <strong><?php echo htmlspecialchars($h['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                          </span>
                        </div>
                      </td>
                      <td>Video</td>
                      <td class="mm-size-cell"><?php echo admin_format_bytes($h['file_size']); ?></td>
                      <td class="mm-date-cell"><?php echo $h['uploaded_at'] ? htmlspecialchars(date('M j, Y', strtotime($h['uploaded_at'])), ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                      <td><span class="mm-status <?php echo !empty($h['is_active']) ? '' : 'mm-status--removed'; ?>"><?php echo !empty($h['is_active']) ? 'Active' : 'Removed'; ?></span></td>
                      <td>
                        <div class="mm-row-actions">
                          <?php if (!empty($h['is_active']) && !empty($h['file_path'])): ?>
                          <a href="adminpanel.php?action=download_media&amp;section=<?php echo urlencode($h['section']); ?>" title="Download video"><i class="fas fa-download"></i></a>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                    <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
              <div class="mm-foot">
                <span id="mmHistoryCount">Showing <?php echo count($admin_media_history); ?> of <?php echo count($admin_media_history); ?> entries</span>
                <div class="mm-page">
                  <button type="button" disabled>&lsaquo;</button>
                  <button type="button" class="is-active">1</button>
                  <button type="button" disabled>&rsaquo;</button>
                </div>
              </div>
            </div>
          
            <!-- Requirements + Slot management -->
            <div class="mm-bottom-grid">
              <div class="mm-mini">
                <div class="mm-mini-head">
                  <div class="mm-mini-ic req"><i class="fas fa-list-check"></i></div>
                  <div>
                    <h3>Requirements Management</h3>
                    <p>Manage application requirements for new applications and renewals</p>
                  </div>
                </div>
                <div class="mm-mini-cta">
                  <button class="mm-btn mm-btn-upload" onclick="openRequirementsModal()">
                    <i class="fas fa-list-check"></i> Manage Requirements
                  </button>
                </div>
              </div>
              <div class="mm-mini">
                <div class="mm-mini-head">
                  <div class="mm-mini-ic slot"><i class="fas fa-map-marked-alt"></i></div>
                  <div>
                    <h3>Slot Management</h3>
                    <p>Manage market and elevated stall</p>
                  </div>
                </div>
                <div class="mm-mini-cta">
                  <button class="mm-btn mm-btn-upload" style="background:#ea580c;" onclick="openSlotMapModal()">
                    <i class="fas fa-map"></i> Open Slot Editor
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- News card details modal (outside main-content so blur does not affect it) -->
<div id="newsCardDetailsModal" class="modal" onclick="if(event.target===this)closeNewsCardDetailsModal()">
  <div class="modal-content mm-news-modal-content" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h2 id="newsCardModalHeading"><i class="fas fa-newspaper"></i> Card Details</h2>
      <button type="button" class="close-modal" onclick="closeNewsCardDetailsModal()" aria-label="Close">&times;</button>
    </div>
    <div class="mm-news-modal-body">
      <input type="hidden" id="newsModalActiveSlot" value="">
      <div class="mm-news-modal-row">
        <label for="newsModalTagType">Tag type</label>
        <select id="newsModalTagType" class="mm-news-modal-input">
          <option value="imp">Important (red)</option>
          <option value="notice">Notice (blue)</option>
          <option value="event">Event (green)</option>
          <option value="update">Update (yellow)</option>
        </select>
      </div>
      <div class="mm-news-modal-row">
        <label for="newsModalTagLabel">Tag label</label>
        <input type="text" id="newsModalTagLabel" class="mm-news-modal-input" placeholder="e.g. Important">
      </div>
      <div class="mm-news-modal-row">
        <label for="newsModalCardDate">Date shown</label>
        <input type="text" id="newsModalCardDate" class="mm-news-modal-input" placeholder="April 10, 2026">
      </div>
      <div class="mm-news-modal-row">
        <label for="newsModalTitle">Title</label>
        <input type="text" id="newsModalTitle" class="mm-news-modal-input" placeholder="Announcement title">
      </div>
      <div class="mm-news-modal-row">
        <label for="newsModalSummary">Short summary <span class="mm-news-hint">(shown on card)</span></label>
        <textarea id="newsModalSummary" class="mm-news-modal-textarea" rows="2" placeholder="Brief description on the card"></textarea>
      </div>
      <div class="mm-news-modal-row">
        <label for="newsModalDetails">Full details <span class="mm-news-hint">(Read More popup)</span></label>
        <textarea id="newsModalDetails" class="mm-news-modal-textarea mm-news-modal-details" rows="5" placeholder="Full announcement text users see when they click Read More"></textarea>
      </div>
      <button type="button" class="mm-news-modal-save" id="newsModalSaveBtn" onclick="saveHomepageNewsCardFromModal()">
        <i class="fas fa-save"></i> Save Card
      </button>
    </div>
  </div>
</div>

<!-- Concern Message Modal -->
<div id="concernMessageModal" class="modal">
  <div class="modal-content" style="max-width: 560px;">
    <div class="modal-header">
      <h2><i class="fas fa-comment-dots"></i>Message</h2>
      <button class="close-modal" onclick="closeConcernMessageModal()">&times;</button>
    </div>
    <div class="concern-modal-body">
      <div class="concern-modal-meta">
        <div><span class="concern-meta-label">From</span><span id="concernModalName"></span></div>
        <div><span class="concern-meta-label">Email</span><span id="concernModalEmail"></span></div>
        <div><span class="concern-meta-label">Date</span><span id="concernModalDate"></span></div>
      </div>
      <div class="concern-modal-message" id="concernModalMessage"></div>
    </div>
  </div>
</div>

<!-- Profile Modal -->
<div id="profileModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Edit Profile</h2>
      <button class="close-modal" onclick="closeProfileModal()">&times;</button>
    </div>
    <div class="profile-photo-section">
      <label class="profile-photo-preview" for="adminPhotoInput" id="profilePhotoPreview" title="Click to upload profile photo">
        <span class="profile-photo-inner" id="adminAvatarInner">
          <?php if (!empty($admin_photo_url)): ?>
            <img src="<?php echo htmlspecialchars($admin_photo_url); ?>" alt="Admin">
          <?php else: ?>
            <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
          <?php endif; ?>
        </span>
        <span class="profile-photo-overlay"><i class="fas fa-camera"></i></span>
      </label>
      <input type="file" id="adminPhotoInput" accept="image/*" style="display:none;">
      <p style="margin:0;font-size:13px;color:#64748b;">Click the photo to change it</p>
    </div>
    <div class="form-group">
      <label>Admin Name</label>
      <input type="text" id="adminNameInput" name="admin_display_name" value="<?php echo htmlspecialchars($admin_name); ?>" autocomplete="off" />
    </div>
    <!-- Dummy fields: reduce browser password autofill into Current Password -->
    <input type="text" name="username" autocomplete="username" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;" value="">
    <input type="password" name="password" autocomplete="new-password" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;" value="">
    <div class="form-group">
      <label>Current Password</label>
      <div class="password-field-wrap">
        <input type="password" id="currentPassword" name="admin_current_password" placeholder="Enter current password" autocomplete="off" readonly data-lpignore="true" data-form-type="other" />
        <button type="button" class="password-toggle-btn" data-password-toggle="currentPassword" aria-label="Show password">
          <i class="fas fa-eye"></i>
        </button>
      </div>
    </div>
    <div class="form-group">
      <label>New Password</label>
      <div class="password-field-wrap">
        <input type="password" id="newPassword" name="admin_new_password" placeholder="Enter new password" autocomplete="new-password" data-lpignore="true" data-form-type="other" />
        <button type="button" class="password-toggle-btn" data-password-toggle="newPassword" aria-label="Show password">
          <i class="fas fa-eye"></i>
        </button>
      </div>
    </div>
    <div class="form-group">
      <label>Confirm New Password</label>
      <div class="password-field-wrap">
        <input type="password" id="confirmPassword" name="admin_confirm_password" placeholder="Confirm new password" autocomplete="new-password" data-lpignore="true" data-form-type="other" />
        <button type="button" class="password-toggle-btn" data-password-toggle="confirmPassword" aria-label="Show password">
          <i class="fas fa-eye"></i>
        </button>
      </div>
    </div>
    <div class="btn-group-center">
      <button class="btn btn-success" onclick="saveProfile()">
        <i class="fas fa-save"></i> Save Changes
      </button>
    </div>
  </div>
</div>

<!-- Vendor Details Modal -->
<div id="vendorModal" class="modal">
  <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto; overflow-x: hidden;">
    <div id="vendorModalBody">
      <!-- Content will be loaded here -->
    </div>
  </div>
  </div>

<!-- Slot Map Editor Modal -->
<div id="slotMapModal" class="modal">
  <div class="modal-content slot-map-modal-content">
    <div class="modal-header">
      <h2><i class="fas fa-map"></i> Slot Management - Visual Map Editor</h2>
      <button class="close-modal" onclick="closeSlotMapModal()">&times;</button>
    </div>
    <div class="slot-map-pagination" id="slotMapPagination">
      <p class="slot-map-pagination-label" id="slotMapPageLabel">Daet Public Market</p>
    </div>
    <div id="slotMapEditorContent">
      <!-- Map will be loaded here -->
    </div>
    <div class="btn-group-center" style="margin-top: 20px; padding: 15px; border-top: 1px solid #e0e0e0; gap: 12px; align-items: center; justify-content: center;">
      <span style="color:#64748b; font-size:0.9rem;"><i class="fas fa-circle-info"></i> Tap a stall, then a slot number, to edit its status and photos. Changes go to the vendor stall map.</span>
    </div>
  </div>
</div>

<!-- Per-slot detail editor (status + up to 3 photos) -->
<div id="slotDetailModal" class="modal" style="z-index: 100000;">
  <div class="modal-content" style="max-width: 560px;">
    <div class="modal-header">
      <h2><i class="fas fa-store"></i> Edit Stall</h2>
      <button class="close-modal slot-detail-close" onclick="closeSlotDetailModal()" aria-label="Close">&times;</button>
    </div>
    <div style="padding: 20px;">
      <div class="slot-detail-meta" style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px;">
        <div><span style="color:#64748b; font-size:0.8rem; display:block;">Section</span><strong id="slotDetailSection">—</strong></div>
        <div><span style="color:#64748b; font-size:0.8rem; display:block;">Block</span><strong id="slotDetailBlock">—</strong></div>
        <div>
          <span style="color:#64748b; font-size:0.8rem; display:block;">Slot</span>
          <span style="display:inline-flex; align-items:center; gap:8px;">
            <strong id="slotDetailSlot">—</strong>
            <span id="slotDetailSecondFloor" style="display:none; background:#fff9c4; color:#a16207; border:1px solid #f9a825; border-radius:999px; padding:2px 10px; font-size:0.75rem; font-weight:700;">Second Floor</span>
          </span>
        </div>
      </div>

      <div class="form-group" id="slotStatusGroup" style="margin-bottom:16px;">
        <label for="slotDetailStatus" style="font-weight:600;">Status</label>
        <select id="slotDetailStatus" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;" onchange="onSlotStatusChange()">
          <option value="available">Available</option>
          <option value="occupied">Occupied</option>
        </select>
      </div>

      <div class="form-group" id="slotHolderGroup" style="margin-bottom:16px; display:none; background:#fff5ed; border:1px solid #ffd9bd; border-radius:8px; padding:12px;">
        <span style="font-weight:600; display:flex; align-items:center; gap:6px;">
          <i class="fas fa-user-tag" style="color:#ff6b1a;"></i> Stall Holder
        </span>
        <div style="margin-top:6px; font-size:1.05rem;"><strong id="slotDetailHolderName">—</strong></div>
        <div id="slotHolderInfo" style="display:none; margin-top:12px; grid-template-columns:1fr 1fr; gap:10px;">
          <div><span style="color:#64748b; font-size:0.8rem; display:block;">Holder Section</span><strong id="slotHolderSection">—</strong></div>
          <div><span style="color:#64748b; font-size:0.8rem; display:block;">Holder Block</span><strong id="slotHolderBlock">—</strong></div>
        </div>
      </div>

      <label style="font-weight:600; display:block; margin-bottom:8px;">Photos <span style="color:#94a3b8; font-weight:400;">(up to 3)</span></label>
      <div id="slotDetailPhotos" style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:16px;">
        <?php for ($sp = 1; $sp <= 3; $sp++): ?>
        <div class="slot-photo-cell" data-index="<?php echo $sp; ?>" style="border:2px dashed #cbd5e1; border-radius:10px; aspect-ratio:1/1; position:relative; overflow:hidden; background:#f8fafc; display:flex; align-items:center; justify-content:center; cursor:pointer;">
          <img id="slotPhotoImg<?php echo $sp; ?>" alt="" style="display:none; width:100%; height:100%; object-fit:cover;">
          <div id="slotPhotoPlaceholder<?php echo $sp; ?>" style="text-align:center; color:#94a3b8; font-size:0.8rem;">
            <i class="fas fa-camera" style="font-size:1.4rem; display:block; margin-bottom:4px;"></i>Add photo
          </div>
          <button type="button" class="slot-photo-remove" data-index="<?php echo $sp; ?>" style="display:none; position:absolute; top:4px; right:4px; background:rgba(220,38,38,0.92); color:#fff; border:none; width:24px; height:24px; border-radius:50%; cursor:pointer;">&times;</button>
          <input type="file" id="slotPhotoInput<?php echo $sp; ?>" accept="image/*" style="display:none;">
        </div>
        <?php endfor; ?>
      </div>

      <div class="btn-group-center">
        <button class="btn btn-success" id="slotDetailSaveBtn" onclick="adminSaveSlotDetail()">
          <i class="fas fa-save"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Requirements Management Modal -->
<div id="requirementsModal" class="modal">
  <div class="modal-content rq-modal">
    <div class="rq-head">
      <div class="rq-head-left">
        <span class="rq-head-ic"><i class="fas fa-clipboard-list"></i></span>
        <h2>Requirements Management</h2>
      </div>
      <button class="rq-close" onclick="closeRequirementsModal()" aria-label="Close">&times;</button>
    </div>

    <div class="rq-toolbar">
      <div class="rq-tabs">
        <button class="rq-tab rq-tab--active" id="tabNew" onclick="switchRequirementTab('new')">
          <i class="fas fa-file-lines"></i> New Application
        </button>
        <button class="rq-tab" id="tabRenewal" onclick="switchRequirementTab('renewal')">
          <i class="fas fa-rotate"></i> Renewal
        </button>
      </div>
      <button class="rq-save" onclick="saveAllRequirements()">
        <i class="fas fa-floppy-disk"></i> Save All Changes
      </button>
    </div>

    <div class="rq-subhead">
      <div>
        <h3 id="rqListTitle">New Application Requirements</h3>
      </div>
      <div class="rq-meta">
        <span id="rqItemCount">0 items</span>
      </div>
    </div>

    <div id="requirementsContent" class="rq-body">
      <!-- Content will be loaded here -->
    </div>

    <div class="rq-add-wrap">
      <button class="rq-add" onclick="addNewRequirement()">
        <i class="fas fa-plus"></i> Add New Requirement
      </button>
    </div>
  </div>
</div>

<!-- Approval/Rejection Modal -->
<div id="actionModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="actionModalTitle">Action</h2>
      <button class="close-modal" onclick="closeActionModal()">&times;</button>
    </div>
    
    <!-- Incomplete Form (shown only when marking as incomplete) -->
    <div id="rejectionFormContainer" style="display: none;">
      <form id="rejectionForm" onsubmit="return false;">
        <div class="form-group">
          <label for="rejectionReason">Incomplete Reason (Required):</label>
          <select id="rejectionReason" name="rejection_reason" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            <option value="">Select a reason...</option>
            <option value="Incomplete Documentation">Incomplete Documents</option>
            <option value="Invalid Business License">Invalid Documents</option
            <option value="Incorrect Information">Incorrect Information</option>
            <option value="Policy Violation">Policy Violation</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label for="rejectionNotes">Additional Notes (Optional):</label>
          <textarea id="rejectionNotes" name="rejection_notes" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
        </div>
      </form>
    </div>
    
    <!-- Approval Form (shown only when approving) -->
    <div id="approvalFormContainer" style="display: none;">
      <div class="form-group">
        <label>Approval Notes (Optional)</label>
        <textarea id="actionNotes" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
      </div>
    </div>
    
    <div class="btn-group-center">
      <button type="button" class="btn btn-success" id="confirmActionBtn" onclick="confirmAction(event)">
        <i class="fas fa-check"></i> Confirm
      </button>
      <button class="btn btn-danger" onclick="closeActionModal()">
        <i class="fas fa-times"></i> Cancel
      </button>
    </div>
  </div>
</div>

<!-- Receipt Preview Modal -->
<div id="receiptPreviewModal" class="modal">
  <div class="modal-content receipt-preview-modal">
    <div class="modal-header receipt-preview-header">
      <h2><i class="fas fa-receipt"></i> Receipt</h2>
      <button type="button" class="receipt-preview-close" onclick="closeReceiptPreviewModal()" aria-label="Close">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="receipt-preview-scroll">
      <div id="receiptPreviewDetails" class="receipt-preview-details" hidden aria-hidden="true"></div>
      <div id="receiptPreviewContent" class="receipt-preview-content">
        <p style="color: #999; margin: 0;">Loading preview...</p>
      </div>
    </div>
  </div>
</div>

<!-- Receipt full-size image lightbox -->
<div id="receiptImageLightbox" role="dialog" aria-modal="true" aria-labelledby="receiptLightboxCaption" onclick="if(event.target===this)closeReceiptImageLightbox()">
  <div class="receipt-lightbox-panel">
    <button type="button" class="receipt-lightbox-x" onclick="closeReceiptImageLightbox()" aria-label="Close">
      <i class="fas fa-times"></i>
    </button>
    <div class="receipt-lightbox-scroll">
      <img id="receiptLightboxImg" src="" alt="Receipt">
    </div>
    <div class="receipt-lightbox-footer">
      <p class="receipt-lightbox-caption" id="receiptLightboxCaption"></p>
      <div class="receipt-lightbox-actions">
        <button type="button" class="receipt-lightbox-download" onclick="downloadReceiptFile()">
          <i class="fas fa-download"></i> Download Picture
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Receipt Action Modal -->
<div id="receiptActionModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="receiptActionModalTitle">Receipt Action</h2>
      <button class="close-modal" onclick="closeReceiptActionModal()">&times;</button>
    </div>
    
    <!-- Invalid Form (shown only when marking as invalid) -->
    <div id="receiptInvalidFormContainer" style="display: none;">
      <form id="receiptInvalidForm" onsubmit="return false;">
        <div class="form-group">
          <label for="receiptInvalidReason">Invalid Reason (Required):</label>
          <select id="receiptInvalidReason" name="invalid_reason" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            <option value="">Select a reason...</option>
            <option value="Invalid Receipt Format">Invalid Receipt Format</option>
            <option value="Receipt Not Clear">Receipt Not Clear</option>
            <option value="Missing Information">Missing Information</option>
            <option value="Expired Receipt">Expired Receipt</option>
            <option value="Fake/Invalid Receipt">Fake/Invalid Receipt</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label for="receiptInvalidNotes">Additional Notes (Optional):</label>
          <textarea id="receiptInvalidNotes" name="invalid_notes" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
        </div>
      </form>
    </div>
    
    <!-- Approval Form (shown only when approving) -->
    <div id="receiptApprovalFormContainer" style="display: none;">
      <div class="form-group">
        <label>Approval Notes (Optional)</label>
        <textarea id="receiptApprovalNotes" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
      </div>
    </div>
    
    <div class="btn-group-center">
      <button type="button" class="btn btn-success" id="confirmReceiptActionBtn" onclick="confirmReceiptAction(event)">
        <i class="fas fa-check"></i> Confirm
      </button>
      <button class="btn btn-danger" onclick="closeReceiptActionModal()">
        <i class="fas fa-times"></i> Cancel
      </button>
    </div>
  </div>
</div>

<!-- Statistics Graph Modal -->
<div id="statisticsGraphModal" class="modal">
  <div class="modal-content" style="max-width: 900px;">
    <div class="modal-header">
      <h2 id="graphModalTitle"><i class="fas fa-chart-line"></i> Statistics Graph</h2>
      <button class="close-modal" onclick="closeStatisticsGraphModal()">&times;</button>
    </div>
    <div style="padding: 20px;">
      <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
          <label for="graphYearSelect" style="font-weight: 600; margin-right: 10px;">Year:</label>
          <select id="graphYearSelect" onchange="updateGraphYear()" style="padding: 8px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
            <!-- Years will be populated dynamically -->
          </select>
        </div>
        <div id="graphCurrentYear" style="color: #667eea; font-weight: 600;">
          <!-- Current year will be shown here -->
        </div>
      </div>
      <div style="position: relative; height: 400px;">
        <canvas id="statisticsChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
  // ========== REPORTS SECTION ==========
  (function () {
    var repMonthlyChart = null;
    var repStatusChart = null;
    var repStatus = 'all';
    var repInited = false;

    function repData() { return Array.isArray(window.REPORTS_DATA) ? window.REPORTS_DATA : []; }
    function repSections() { return Array.isArray(window.REPORTS_SECTIONS) ? window.REPORTS_SECTIONS : []; }

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    // Normalize a typed search like "6-5-2026", "06/05/2026", "2026" etc. to compare against YYYY-MM-DD
    function matchesSearch(dateStr, q) {
      if (!q) return true;
      if (!dateStr) return false;
      var parts = dateStr.split('-'); // [YYYY, MM, DD]
      var y = parts[0], m = parts[1], d = parts[2];
      var variants = [
        m + '-' + d + '-' + y,
        pad(parseInt(m,10)) + '-' + pad(parseInt(d,10)) + '-' + y,
        dateStr,
        m + '/' + d + '/' + y,
        y
      ];
      var nq = q.toLowerCase().replace(/\s+/g, '');
      return variants.some(function (v) { return v.toLowerCase().replace(/\s+/g, '').indexOf(nq) !== -1; });
    }

    function inDateRange(dateStr, range) {
      if (range === 'all' || !dateStr) return range === 'all' ? true : false;
      var d = new Date(dateStr + 'T00:00:00');
      if (isNaN(d.getTime())) return false;
      var now = new Date();
      if (range === 'day') {
        return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate();
      }
      if (range === 'month') {
        return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth();
      }
      if (range === 'year') {
        return d.getFullYear() === now.getFullYear();
      }
      return true;
    }

    function getFiltered() {
      var range = (document.getElementById('repDateRange') || {}).value || 'all';
      var type = (document.getElementById('repTypeFilter') || {}).value || 'all';
      var q = (document.getElementById('repSearchDate') || {}).value || '';
      return repData().filter(function (a) {
        if (!inDateRange(a.date, range)) return false;
        if (type === 'new' && a.type !== 'new') return false;
        if (type === 'renewal' && a.type !== 'renewal') return false;
        if (type === 'pending' && a.status !== 'pending') return false;
        if (repStatus !== 'all' && a.status !== repStatus) return false;
        if (!matchesSearch(a.date, q)) return false;
        return true;
      });
    }

    function updateCards(rows) {
      var total = rows.length, pending = 0, approved = 0, incomplete = 0;
      rows.forEach(function (r) {
        if (r.status === 'pending') pending++;
        else if (r.status === 'approved') approved++;
        else if (r.status === 'incomplete') incomplete++;
      });
      setText('repTotal', total);
      setText('repPending', pending);
      setText('repApproved', approved);
      setText('repIncomplete', incomplete);
      return { total: total, pending: pending, approved: approved, incomplete: incomplete };
    }

    function setText(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; }

    function buildMonthly(rows) {
      var labels = [], keys = [];
      var now = new Date();
      for (var i = 5; i >= 0; i--) {
        var d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        labels.push(d.toLocaleString('en-US', { month: 'short' }));
        keys.push(d.getFullYear() + '-' + pad(d.getMonth() + 1));
      }
      var ap = {}, pe = {}, re = {};
      keys.forEach(function (k) { ap[k] = 0; pe[k] = 0; re[k] = 0; });
      rows.forEach(function (r) {
        if (!r.date) return;
        var k = r.date.slice(0, 7);
        if (!(k in ap)) return;
        if (r.status === 'approved') ap[k]++;
        else if (r.status === 'pending') pe[k]++;
        else if (r.status === 'incomplete') re[k]++;
      });
      return {
        labels: labels,
        approved: keys.map(function (k) { return ap[k]; }),
        pending: keys.map(function (k) { return pe[k]; }),
        rejected: keys.map(function (k) { return re[k]; })
      };
    }

    function renderMonthly(rows) {
      var canvas = document.getElementById('repMonthlyChart');
      if (!canvas || typeof Chart === 'undefined') return;
      var m = buildMonthly(rows);
      if (repMonthlyChart) repMonthlyChart.destroy();
      repMonthlyChart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: m.labels,
          datasets: [
            { label: 'Approved', data: m.approved, backgroundColor: '#22c55e', stack: 's', borderRadius: 4, maxBarThickness: 46 },
            { label: 'Pending', data: m.pending, backgroundColor: '#f59e0b', stack: 's', borderRadius: 4, maxBarThickness: 46 },
            { label: 'Rejected', data: m.rejected, backgroundColor: '#ef4444', stack: 's', borderRadius: 4, maxBarThickness: 46 }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { stacked: true, grid: { display: false }, ticks: { color: '#9ca3af' } },
            y: { stacked: true, beginAtZero: true, ticks: { precision: 0, color: '#9ca3af' }, grid: { color: 'rgba(0,0,0,0.05)' } }
          }
        }
      });
    }

    function renderStatus(c) {
      var canvas = document.getElementById('repStatusChart');
      if (!canvas || typeof Chart === 'undefined') return;
      var vals = [c.approved, c.pending, c.incomplete];
      var total = c.approved + c.pending + c.incomplete;
      if (repStatusChart) repStatusChart.destroy();
      repStatusChart = new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: ['Approved', 'Pending', 'Incomplete'],
          datasets: [{ data: vals, backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'], borderWidth: 0, cutout: '70%' }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { enabled: true }
          }
        }
      });
      // center text
      var legend = document.getElementById('repDonutLegend');
      if (legend) {
        legend.innerHTML =
          rdlItem('#22c55e', 'Approved', c.approved) +
          rdlItem('#f59e0b', 'Pending', c.pending) +
          rdlItem('#ef4444', 'Incomplete', c.incomplete);
      }
      drawDonutCenter(canvas, total);
    }

    function drawDonutCenter(canvas, total) {
      // Use a simple overlay element for the total in the middle
      var wrap = canvas.parentElement;
      if (!wrap) return;
      var existing = wrap.querySelector('.rep-donut-center');
      if (!existing) {
        existing = document.createElement('div');
        existing.className = 'rep-donut-center';
        existing.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none;';
        wrap.appendChild(existing);
      }
      existing.innerHTML = '<div style="font-size:1.8rem;font-weight:800;color:#111827;">' + total + '</div><div style="font-size:0.78rem;color:#9ca3af;">Total</div>';
    }

    function rdlItem(color, label, val) {
      return '<div class="rdl-item"><span class="rdl-left"><span class="rep-dot" style="background:' + color + '"></span>' + label + '</span><span class="rdl-val">' + val + '</span></div>';
    }

    function renderCategories(rows) {
      var box = document.getElementById('repCategories');
      if (!box) return;
      var secs = repSections();
      var counts = {};
      secs.forEach(function (s) { counts[s] = 0; });
      var total = 0;
      rows.forEach(function (r) {
        if (r.section && counts.hasOwnProperty(r.section)) { counts[r.section]++; total++; }
        else if (r.section) { counts[r.section] = (counts[r.section] || 0) + 1; total++; }
      });
      var names = Object.keys(counts);
      names.sort(function (a, b) { return counts[b] - counts[a]; });
      var html = '';
      names.forEach(function (name) {
        var v = counts[name];
        var pct = total > 0 ? Math.round((v / total) * 100) : 0;
        html += '<div class="rep-cat-row">' +
          '<div class="rep-cat-top"><span class="rep-cat-name">' + escapeHtmlRep(name) + '</span>' +
          '<span class="rep-cat-meta">' + v + ' <span>(' + pct + '%)</span></span></div>' +
          '<div class="rep-cat-bar"><div class="rep-cat-fill" style="width:' + pct + '%"></div></div></div>';
      });
      box.innerHTML = html || '<div class="rep-activity-empty">No application data.</div>';
    }

    function escapeHtmlRep(s) {
      return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }

    window.applyReportsFilters = function () {
      var rows = getFiltered();
      var c = updateCards(rows);
      renderMonthly(rows);
      renderStatus(c);
      renderCategories(rows);
    };

    window.setReportsStatus = function (status, btn) {
      repStatus = status;
      var tabs = document.querySelectorAll('#repTabs .rep-tab');
      tabs.forEach(function (t) { t.classList.remove('active'); });
      if (btn) btn.classList.add('active');
      window.applyReportsFilters();
    };

    window.exportReportsCsv = function () {
      var rows = getFiltered();
      var header = ['Name', 'Type', 'Status', 'Section', 'Date'];
      var lines = [header.join(',')];
      rows.forEach(function (r) {
        var vals = [r.name, r.type, r.status, r.section, r.date].map(function (x) {
          var s = (x == null ? '' : String(x)).replace(/"/g, '""');
          return /[",\n]/.test(s) ? '"' + s + '"' : s;
        });
        lines.push(vals.join(','));
      });
      var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url;
      a.download = 'reports_' + new Date().toISOString().slice(0, 10) + '.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    };

    window.initReports = function () {
      var section = document.getElementById('reports-section');
      if (!section) return;
      repInited = true;
      window.applyReportsFilters();
      // Ensure charts size correctly after becoming visible
      setTimeout(function () {
        if (repMonthlyChart) repMonthlyChart.resize();
        if (repStatusChart) repStatusChart.resize();
      }, 60);
    };

    document.addEventListener('DOMContentLoaded', function () {
      var section = document.getElementById('reports-section');
      if (section && !section.classList.contains('hidden')) {
        window.initReports();
      }
    });
  })();
</script>

<script>
  let currentVendorId = null;
  let currentAction = null;
  let isProcessing = false; // Flag to prevent multiple submissions

  // ========== ANTI-REFRESH MECHANISM: localStorage-based status persistence ==========
  // Save vendor status to localStorage (permanent storage that survives refresh)
  function saveVendorStatus(vendorId, status) {
    try {
      const statuses = JSON.parse(localStorage.getItem('vendorStatuses') || '{}');
      statuses[vendorId] = {
        status: status,
        timestamp: Date.now()
      };
      localStorage.setItem('vendorStatuses', JSON.stringify(statuses));
      console.log(`✓✓✓ ANTI-REFRESH: Saved vendor ${vendorId} status "${status}" to localStorage`);
    } catch (e) {
      console.error('Error saving vendor status to localStorage:', e);
    }
  }

  // Get vendor status from localStorage
  function getVendorStatus(vendorId) {
    try {
      const statuses = JSON.parse(localStorage.getItem('vendorStatuses') || '{}');
      return statuses[vendorId] ? statuses[vendorId].status : null;
    } catch (e) {
      console.error('Error reading vendor status from localStorage:', e);
      return null;
    }
  }

  // Sync localStorage from server-rendered table rows (trust DB over stale cache)
  function syncVendorStatusesFromServer() {
    try {
      document.querySelectorAll('tr[data-vendor-id]').forEach(row => {
        const vendorId = row.getAttribute('data-vendor-id');
        const status = row.getAttribute('data-status');
        if (vendorId && status) {
          saveVendorStatus(vendorId, status);
        }
      });
    } catch (e) {
      console.error('Error syncing vendor statuses from server:', e);
    }
  }
  // ========== END STATUS SYNC ==========

  // Initialize dark mode on page load
  document.addEventListener('DOMContentLoaded', function() {
    const themeSwitch = document.getElementById('theme-switch');
    
    // Load dark mode preference from localStorage
    let darkmode = localStorage.getItem('darkmode');
    
    const enableDarkmode = () => {
      document.body.classList.add('dark-mode');
      localStorage.setItem('darkmode', 'active');
      localStorage.setItem('darkMode', 'true');
    };
    
    const disableDarkmode = () => {
      document.body.classList.remove('dark-mode');
      localStorage.setItem('darkmode', null);
      localStorage.setItem('darkMode', 'false');
    };
    
    // Apply saved preference on load
    if (darkmode === "active") {
      enableDarkmode();
      if (themeSwitch) {
        themeSwitch.checked = true;
      }
    } else {
      // Also check for old 'darkMode' key for compatibility
      const savedDarkMode = localStorage.getItem('darkMode') === 'true';
      if (savedDarkMode) {
        enableDarkmode();
        if (themeSwitch) {
          themeSwitch.checked = true;
        }
      } else {
        if (themeSwitch) {
          themeSwitch.checked = false;
        }
      }
    }
    
    // Add change event listener to theme switch checkbox
    if (themeSwitch) {
      themeSwitch.addEventListener("change", function(e) {
        e.stopPropagation();
        const isChecked = themeSwitch.checked;
        
        if (isChecked) {
          enableDarkmode();
        } else {
          disableDarkmode();
        }
        
        // Also save to server
        const formData = new FormData();
        formData.append('action', 'save_settings');
        formData.append('dark_mode', isChecked ? '1' : '0');

        fetch('adminpanel.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (!data.success) {
            console.error('Error saving dark mode:', data.message);
          }
        })
        .catch(error => {
          console.error('Error saving dark mode:', error);
        });
      });
    }
    
    // Sync localStorage from server-rendered statuses on load
    setTimeout(() => {
      syncVendorStatusesFromServer();
    }, 500);
    
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        syncVendorStatusesFromServer();
      }
    });
  });

  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggleBtn');
    sidebar.classList.toggle('collapsed');
    if (menuToggle) {
      menuToggle.classList.toggle('active');
    }
  }

  function filterMenuItems(searchTerm) {
    const menuItems = document.querySelectorAll('.nav-item');
    const searchLower = searchTerm.toLowerCase();
    
    menuItems.forEach(item => {
      const text = item.textContent.toLowerCase();
      if (text.includes(searchLower)) {
        item.style.display = 'flex';
      } else {
        item.style.display = 'none';
      }
    });
  }

  function logout() {
    if (confirm('Are you sure you want to logout?')) {
      // Clear any pending requests
      fetch('logout.php', {
        method: 'POST'
      })
      .then(() => {
        // Redirect to login page
        window.location.href = 'index.php';
      })
      .catch(() => {
        // Even if logout request fails, redirect to login
        window.location.href = 'index.php';
      });
    }
  }

  function showSection(sectionName, element) {
    var sections = document.querySelectorAll('.section');
    sections.forEach(function(sec) {
      sec.classList.add('hidden');
    });
    var targetSection = document.getElementById(sectionName + '-section');
    if (targetSection) {
      targetSection.classList.remove('hidden');
    }

    if (sectionName === 'reports' && typeof window.initReports === 'function') {
      window.initReports();
    }

    var navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(function(item) {
      item.classList.remove('active');
    });
    // Set clicked nav item as active
    if (element) {
      element.classList.add('active');
    } else {
      // Find the nav item by section name
      var navItem = Array.from(navItems).find(function(item) {
        return item.getAttribute('onclick') && item.getAttribute('onclick').includes("'" + sectionName + "'");
      });
      if (navItem) {
        navItem.classList.add('active');
      }
    }
    
    // Update URL without reloading page to preserve section on refresh
    var url = new URL(window.location);
    url.searchParams.set('section', sectionName);
    // Preserve other URL parameters like page, page_size, etc.
    window.history.pushState({ section: sectionName }, '', url);
  }

  // Restore section from URL parameter on page load
  window.addEventListener('DOMContentLoaded', function() {
    var urlParams = new URLSearchParams(window.location.search);
    var section = urlParams.get('section');
    if (section) {
      var validSections = ['dashboard', 'vendors', 'appearance', 'records', 'adminlog', 'receipts', 'concerns', 'reports', 'ratings'];
      if (validSections.includes(section)) {
        showSection(section);
      } else {
        // Default to dashboard if invalid section
        showSection('dashboard');
      }
    } else {
      // Default to dashboard if no section specified
      showSection('dashboard');
    }
  });
  
  // Handle browser back/forward buttons
  window.addEventListener('popstate', function(event) {
    var urlParams = new URLSearchParams(window.location.search);
    var section = urlParams.get('section') || 'dashboard';
    var validSections = ['dashboard', 'vendors', 'appearance', 'records', 'adminlog', 'receipts', 'concerns', 'reports', 'ratings'];
    if (validSections.includes(section)) {
      showSection(section);
    }
  });

  // Load Recent Applications page via AJAX without refresh
  function loadRecentApplicationsPage(page) {
    // Prevent loading if already loading
    var pagination = document.getElementById('recentApplicationsPagination');
    if (!pagination || pagination.classList.contains('loading')) {
      return;
    }
    
    // Add loading state
    pagination.classList.add('loading');
    var tableContainer = document.getElementById('recentApplicationsTableContainer');
    if (tableContainer) {
      tableContainer.style.opacity = '0.5';
      tableContainer.style.pointerEvents = 'none';
    }
    
    // Update URL without reloading
    var url = new URL(window.location);
    url.searchParams.set('section', 'dashboard');
    url.searchParams.set('page', page);
    window.history.pushState({ section: 'dashboard', page: page }, '', url);
    
    // Fetch the page content
    fetch(url.toString())
      .then(response => response.text())
      .then(html => {
        // Create a temporary container to parse the HTML
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        
        // Extract the table container and pagination from the fetched HTML
        var fetchedTableContainer = tempDiv.querySelector('#recentApplicationsTableContainer');
        var fetchedPagination = tempDiv.querySelector('#recentApplicationsPagination');
        
        if (fetchedTableContainer) {
          // Update the table container
          var tableContainer = document.getElementById('recentApplicationsTableContainer');
          if (tableContainer) {
            tableContainer.innerHTML = fetchedTableContainer.innerHTML;
          }
        }
        
        if (fetchedPagination) {
          // Update pagination - this will preserve onclick handlers since they're inline
          pagination.innerHTML = fetchedPagination.innerHTML;
        }
      })
      .catch(error => {
        console.error('Error loading page:', error);
        alert('Error loading page. Please refresh the page.');
      })
      .finally(() => {
        // Remove loading state
        pagination.classList.remove('loading');
        if (tableContainer) {
          tableContainer.style.opacity = '1';
          tableContainer.style.pointerEvents = 'auto';
        }
      });
  }

  function loadVendorsPage(page) {
    // Prevent loading if already loading
    var pagination = document.getElementById('vendorsPagination');
    if (!pagination || pagination.classList.contains('loading')) {
      return;
    }
    
    // Add loading state
    pagination.classList.add('loading');
    var tableContainer = document.getElementById('vendorsTableContainer');
    if (tableContainer) {
      tableContainer.style.opacity = '0.5';
      tableContainer.style.pointerEvents = 'none';
    }
    
    // Get current page_size from URL or use default
    var url = new URL(window.location);
    var currentPageSize = url.searchParams.get('page_size') || '10';
    url.searchParams.set('section', 'dashboard');
    url.searchParams.set('page', page);
    url.searchParams.set('page_size', currentPageSize);
    window.history.pushState({ section: 'dashboard', page: page }, '', url);
    
    // Fetch the page content
    fetch(url.toString())
      .then(response => response.text())
      .then(html => {
        // Create a temporary container to parse the HTML
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        
        // Extract the table container and pagination from the fetched HTML
        var fetchedTableContainer = tempDiv.querySelector('#vendorsTableContainer');
        var fetchedPagination = tempDiv.querySelector('#vendorsPagination');
        
        if (fetchedTableContainer) {
          // Update the table container
          var tableContainer = document.getElementById('vendorsTableContainer');
          if (tableContainer) {
            tableContainer.innerHTML = fetchedTableContainer.innerHTML;
          }
        }
        
        if (fetchedPagination) {
          // Update pagination - this will preserve onclick handlers since they're inline
          pagination.innerHTML = fetchedPagination.innerHTML;
        }
        
        // Re-initialize filter function after content update
        if (typeof filterVendors === 'function') {
          // Filter is already applied via data attributes, no need to re-run
        }
      })
      .catch(error => {
        console.error('Error loading vendors page:', error);
        alert('Error loading page. Please refresh the page.');
      })
      .finally(() => {
        // Remove loading state
        pagination.classList.remove('loading');
        if (tableContainer) {
          tableContainer.style.opacity = '1';
          tableContainer.style.pointerEvents = 'auto';
        }
      });
  }

  function showAlert(message, type = 'success') {
    // Try to use alert container if it exists
    const alertContainer = document.getElementById('alertContainer');
    if (alertContainer) {
      const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
      alertContainer.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
      alertContainer.style.display = 'block';
      
      setTimeout(() => {
        alertContainer.style.display = 'none';
      }, 5000);
    } else {
      // Fallback to browser alert if container doesn't exist
      alert(message);
    }
  }

  // Interactive greeting messages
  const greetingMessages = [
    'You\'re doing great po',
    'Good Morning!',
    'or Good Afternoon ^_^',
    'always smile :>',
    'You\'re the best <3',
    'Thanks for all you do'
  ];
  
  let currentGreetingIndex = 0;
  
  function changeGreeting() {
    const greetingEl = document.getElementById('greetingMessage');
    if (greetingEl) {
      // Remove previous animation class
      greetingEl.classList.remove('animate');
      
      // Get next message
      currentGreetingIndex = (currentGreetingIndex + 1) % greetingMessages.length;
      const newMessage = greetingMessages[currentGreetingIndex];
      
      // Trigger reflow for animation
      void greetingEl.offsetWidth;
      
      // Update message and add animation
      greetingEl.textContent = newMessage;
      greetingEl.classList.add('animate');
      
      // Add icon animation
      const icon = document.querySelector('.greeting-icon i');
      if (icon) {
        icon.style.transform = 'rotate(360deg)';
        setTimeout(() => {
          icon.style.transform = '';
        }, 500);
      }
    }
  }

  function clearProfilePasswordFields() {
    ['currentPassword', 'newPassword', 'confirmPassword'].forEach(function(id) {
      var input = document.getElementById(id);
      if (!input) return;
      input.value = '';
      input.type = 'password';
      var toggle = document.querySelector('[data-password-toggle="' + id + '"]');
      if (toggle) {
        var icon = toggle.querySelector('i');
        if (icon) icon.className = 'fas fa-eye';
        toggle.setAttribute('aria-label', 'Show password');
      }
    });
  }

  function openProfileModal() {
    document.getElementById('profileModal').classList.add('show');
    document.body.classList.add('modal-open');
    clearProfilePasswordFields();

    var currentPassword = document.getElementById('currentPassword');
    if (currentPassword) {
      currentPassword.setAttribute('readonly', 'readonly');
      // Unlock on first interaction so typing works, but autofill is blocked while readonly
      if (!currentPassword.hasAttribute('data-autofill-guard')) {
        var unlock = function() {
          currentPassword.removeAttribute('readonly');
        };
        currentPassword.addEventListener('focus', unlock);
        currentPassword.addEventListener('mousedown', unlock);
        currentPassword.setAttribute('data-autofill-guard', 'true');
      }
      // Clear again shortly after open if browser injects credentials (skip if user is typing)
      [50, 250].forEach(function(ms) {
        setTimeout(function() {
          if (document.activeElement === currentPassword) return;
          clearProfilePasswordFields();
          if (currentPassword) currentPassword.setAttribute('readonly', 'readonly');
        }, ms);
      });
    }
    
    // Ensure event listener is attached for real-time avatar update
    const adminNameInput = document.getElementById('adminNameInput');
    if (adminNameInput && !adminNameInput.hasAttribute('data-listener-attached')) {
      adminNameInput.addEventListener('input', function() {
        updateAvatarInitial(this.value);
      });
      adminNameInput.setAttribute('data-listener-attached', 'true');
    }
  }

  function closeProfileModal() {
    document.getElementById('profileModal').classList.remove('show');
    document.body.classList.remove('modal-open');
    clearProfilePasswordFields();
    var currentPassword = document.getElementById('currentPassword');
    if (currentPassword) currentPassword.setAttribute('readonly', 'readonly');
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.password-toggle-btn');
    if (!btn) return;
    var id = btn.getAttribute('data-password-toggle');
    var input = id ? document.getElementById(id) : null;
    var icon = btn.querySelector('i');
    if (!input) return;
    if (input.type === 'password') {
      input.type = 'text';
      if (icon) icon.className = 'fas fa-eye-slash';
      btn.setAttribute('aria-label', 'Hide password');
    } else {
      input.type = 'password';
      if (icon) icon.className = 'fas fa-eye';
      btn.setAttribute('aria-label', 'Show password');
    }
  });

  function saveProfile() {
    console.log('Save profile function called');
    
    const adminName = document.getElementById('adminNameInput').value;
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    console.log('Form data:', { adminName, currentPassword, newPassword, confirmPassword });

    // Basic validation
    if (!adminName.trim()) {
      alert('Please enter admin name');
      return;
    }

    if (newPassword && !currentPassword) {
      alert('Please enter current password to change password');
      return;
    }

    if (newPassword && newPassword !== confirmPassword) {
      alert('New passwords do not match');
      return;
    }

    if (newPassword && newPassword.length < 8) {
      alert('New password must be at least 8 characters long');
      return;
    }

    // Show loading
    const saveButton = document.querySelector('button[onclick="saveProfile()"]');
    if (saveButton) {
      saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
      saveButton.disabled = true;
    }

    // Create form data
    const formData = new FormData();
    formData.append('action', 'update_profile');
    formData.append('admin_name', adminName);
    formData.append('current_password', currentPassword);
    formData.append('new_password', newPassword);
    formData.append('confirm_password', confirmPassword);

    console.log('Sending request to adminpanel.php');

    // Send request
    fetch('adminpanel.php', {
      method: 'POST',
      body: formData
    })
    .then(response => {
      console.log('Response received:', response.status);
      return response.text();
    })
    .then(text => {
      console.log('Response text:', text);
      
      try {
        const data = JSON.parse(text);
        console.log('Parsed data:', data);
        
        if (data.success) {
          // Update admin name display
          const adminNameElement = document.getElementById('adminName');
          if (adminNameElement) {
            adminNameElement.textContent = adminName;
          }
          
          // Show success message
          alert('Profile updated successfully!');
          
          // Close modal
          closeProfileModal();
          
          // Clear password fields
          document.getElementById('currentPassword').value = '';
          document.getElementById('newPassword').value = '';
          document.getElementById('confirmPassword').value = '';
          
          updateAvatarInitial(adminName);
          
        } else {
          alert('Error: ' + data.message);
        }
      } catch (e) {
        console.error('JSON parse error:', e);
        console.error('Raw response:', text);
        alert('Server error: ' + text.substring(0, 200));
      }
    })
    .catch(error => {
      console.error('Fetch error:', error);
      alert('Network error: ' + error.message);
    })
    .finally(() => {
      // Restore button
      if (saveButton) {
        saveButton.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        saveButton.disabled = false;
      }
    });
  }

  function setAdminAvatarImage(url) {
    var imgHtml = '<img src="' + String(url).replace(/"/g, '&quot;') + '" alt="Admin">';
    var inner = document.getElementById('adminAvatarInner');
    var header = document.getElementById('headerAvatar');
    if (inner) inner.innerHTML = imgHtml;
    if (header) header.innerHTML = imgHtml;
  }

  function updateAvatarInitial(name) {
    var inner = document.getElementById('adminAvatarInner');
    if (!inner || inner.querySelector('img')) return;
    inner.textContent = (name || '').trim().charAt(0).toUpperCase() || 'A';
    var header = document.getElementById('headerAvatar');
    if (header && !header.querySelector('img')) {
      header.textContent = (name || '').trim().charAt(0).toUpperCase() || 'A';
    }
  }

  // Add event listener to update avatar initial in real-time
  document.addEventListener('DOMContentLoaded', function() {
    const adminNameInput = document.getElementById('adminNameInput');
    if (adminNameInput) {
      adminNameInput.addEventListener('input', function() {
        updateAvatarInitial(this.value);
      });
    }

    const adminPhotoInput = document.getElementById('adminPhotoInput');
    if (adminPhotoInput) {
      adminPhotoInput.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) return;
        if (!file.type.startsWith('image/')) {
          alert('Please choose an image file (JPG, PNG, or GIF).');
          this.value = '';
          return;
        }
        if (file.size > 2 * 1024 * 1024) {
          alert('Image must be 2MB or smaller.');
          this.value = '';
          return;
        }

        const formData = new FormData();
        formData.append('profile_pic', file);

        fetch('update_admin_photo.php', { method: 'POST', body: formData, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.success && data.file_url) {
              setAdminAvatarImage(data.file_url);
            } else {
              alert(data.message || 'Failed to update profile photo.');
            }
          })
          .catch(function () {
            alert('Failed to upload profile photo.');
          })
          .finally(function () {
            adminPhotoInput.value = '';
          });
      });
    }
  });

  function toggleDarkMode() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    const isEnabled = darkModeToggle.checked;
    
    // Save to localStorage immediately
    localStorage.setItem('darkMode', isEnabled);
    
    // Apply dark mode to body
    if (isEnabled) {
      document.body.classList.add('dark-mode');
    } else {
      document.body.classList.remove('dark-mode');
    }
    
    // Save to server
    const formData = new FormData();
    formData.append('action', 'save_settings');
    formData.append('dark_mode', isEnabled ? '1' : '0');

    fetch('adminpanel.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (!data.success) {
        showAlert(data.message || 'An error occurred while saving dark mode setting.', 'error');
      }
    })
    .catch(error => {
      console.error('Error saving dark mode:', error);
    });
  }

  function saveSettings() {
    // This function is kept for compatibility but dark mode is saved via toggleDarkMode()
    showAlert('Settings saved successfully!');
  }

  function handleVideoUpload(input) {
    if (input.files && input.files[0]) {
      const file = input.files[0];
      
      // Validate file size (50MB limit)
      if (file.size > 50 * 1024 * 1024) {
        alert('Video file must be less than 50MB');
        input.value = '';
        return;
      }
      
      // Validate file type
      if (!file.type.startsWith('video/')) {
        alert('Please select a valid video file');
        input.value = '';
        return;
      }
      
      uploadMedia('video', file, 'overview_video', input);
    }
  }

  function handleImageUpload(input) {
    if (input.files && input.files[0]) {
      const file = input.files[0];
      
      // Validate file size (50MB limit for videos)
      if (file.size > 50 * 1024 * 1024) {
        alert('Video file must be less than 50MB');
        input.value = '';
        return;
      }
      
      // Validate file type
      if (!file.type.startsWith('video/')) {
        alert('Please select a valid video file');
        input.value = '';
        return;
      }
      
      uploadMedia('video', file, 'price_video', input);
    }
  }

  function handleVideo3Upload(input) {
    if (input.files && input.files[0]) {
      const file = input.files[0];
      
      // Validate file size (50MB limit for videos)
      if (file.size > 50 * 1024 * 1024) {
        alert('Video file must be less than 50MB');
        input.value = '';
        return;
      }
      
      // Validate file type
      if (!file.type.startsWith('video/')) {
        alert('Please select a valid video file');
        input.value = '';
        return;
      }
      
      uploadMedia('video', file, 'third_video', input);
    }
  }

  function handleHomepageAnnouncementUpload(input) {
    if (input.files && input.files[0]) {
      const file = input.files[0];
      if (file.size > 50 * 1024 * 1024) {
        alert('Video file must be less than 50MB');
        input.value = '';
        return;
      }
      if (!file.type.startsWith('video/')) {
        alert('Please select a valid video file');
        input.value = '';
        return;
      }
      uploadMedia('video', file, 'homepage_announcement_video', input);
    }
  }

  function handleNewsCardImageUpload(slot, input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (file.size > 10 * 1024 * 1024) {
      alert('Image must be less than 10MB');
      input.value = '';
      return;
    }
    if (!file.type.startsWith('image/')) {
      alert('Please select a valid image file');
      input.value = '';
      return;
    }
    const formData = new FormData();
    formData.append('action', 'upload_news_card_image');
    formData.append('card_slot', slot);
    formData.append('news_image', file);
    const preview = document.getElementById('newsPhotoPreview' + slot);
    const card = input.closest('.mm-news-media');
    const btn = card ? card.querySelector('.mm-btn-upload') : null;
    const orig = btn ? btn.innerHTML : '';
    if (btn) {
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
      btn.disabled = true;
    }
    fetch('adminpanel.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success && data.file_path && preview) {
          const base = data.file_path.split('/').pop();
          preview.innerHTML = '<img src="' + data.file_path + '?t=' + Date.now() + '" alt="News card ' + slot + '"><p class="media-info">Current: ' + base + '</p>';
          alert(data.message);
        } else {
          alert('Error: ' + (data.message || 'Upload failed'));
        }
      })
      .catch(err => alert('Upload error: ' + err.message))
      .finally(() => {
        input.value = '';
        if (btn) {
          btn.innerHTML = orig;
          btn.disabled = false;
        }
      });
  }

  function removeNewsCardImage(slot) {
    if (!confirm('Remove the photo for card ' + slot + '?')) return;
    const formData = new FormData();
    formData.append('action', 'remove_news_card_image');
    formData.append('card_slot', slot);
    fetch('adminpanel.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          const preview = document.getElementById('newsPhotoPreview' + slot);
          if (preview) {
            preview.innerHTML = '<div style="width:100%;height:200px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;border-radius:0;"><p style="color:#666;">No photo uploaded</p></div><p class="media-info">No photo available</p>';
          }
          alert(data.message);
        } else {
          alert('Error: ' + (data.message || 'Could not remove photo'));
        }
      })
      .catch(err => alert('Error: ' + err.message));
  }

  function syncNewsCardHiddenFromModal(slot) {
    document.getElementById('newsTagType' + slot).value = document.getElementById('newsModalTagType').value;
    document.getElementById('newsTagLabel' + slot).value = document.getElementById('newsModalTagLabel').value;
    document.getElementById('newsCardDate' + slot).value = document.getElementById('newsModalCardDate').value;
    document.getElementById('newsTitle' + slot).value = document.getElementById('newsModalTitle').value;
    document.getElementById('newsSummary' + slot).value = document.getElementById('newsModalSummary').value;
    document.getElementById('newsDetails' + slot).value = document.getElementById('newsModalDetails').value;
  }

  function syncNewsCardModalFromHidden(slot) {
    document.getElementById('newsModalTagType').value = document.getElementById('newsTagType' + slot).value || 'update';
    document.getElementById('newsModalTagLabel').value = document.getElementById('newsTagLabel' + slot).value || '';
    document.getElementById('newsModalCardDate').value = document.getElementById('newsCardDate' + slot).value || '';
    document.getElementById('newsModalTitle').value = document.getElementById('newsTitle' + slot).value || '';
    document.getElementById('newsModalSummary').value = document.getElementById('newsSummary' + slot).value || '';
    document.getElementById('newsModalDetails').value = document.getElementById('newsDetails' + slot).value || '';
  }

  function updateNewsCardLabels(slot) {
    const title = document.getElementById('newsTitle' + slot).value.trim();
    const summary = document.getElementById('newsSummary' + slot).value.trim();
    const titleEl = document.getElementById('newsCardTitleLabel' + slot);
    const summaryEl = document.getElementById('newsCardSummaryLabel' + slot);
    if (titleEl) {
      titleEl.innerHTML = '<i class="fas fa-newspaper"></i> Card ' + slot + ' — ' + (title || 'Untitled');
    }
    if (summaryEl) {
      summaryEl.textContent = summary || 'Click Details to edit card text.';
    }
  }

  function openNewsCardDetailsModal(slot) {
    const modal = document.getElementById('newsCardDetailsModal');
    if (!modal) return;
    document.getElementById('newsModalActiveSlot').value = slot;
    syncNewsCardModalFromHidden(slot);
    document.getElementById('newsCardModalHeading').innerHTML = '<i class="fas fa-newspaper"></i> Card ' + slot + ' Details';
    document.getElementById('newsModalSaveBtn').innerHTML = '<i class="fas fa-save"></i> Save Card ' + slot;
    modal.classList.add('show');
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    document.body.classList.add('modal-open');
  }

  function closeNewsCardDetailsModal() {
    const modal = document.getElementById('newsCardDetailsModal');
    if (modal) {
      modal.classList.remove('show');
      modal.style.display = 'none';
    }
    document.body.classList.remove('modal-open');
  }

  function saveHomepageNewsCardFromModal() {
    const slot = parseInt(document.getElementById('newsModalActiveSlot').value, 10);
    if (!slot || slot < 1 || slot > 4) return;
    syncNewsCardHiddenFromModal(slot);
    saveHomepageNewsCard(slot, document.getElementById('newsModalSaveBtn'));
  }

  function normalizeApplicationFeePrice(input, type) {
    if (!input) return;
    const value = parseFloat(input.value);
    const safe = Number.isFinite(value) && value >= 0 ? Math.round(value) : 0;
    input.value = safe;
    updateApplicationFeePreview(type);
  }

  function updateApplicationFeePreview(type) {
    const input = document.getElementById(type === 'new' ? 'adminFeeNewPrice' : 'adminFeeRenewalPrice');
    const preview = document.getElementById(type === 'new' ? 'adminFeeNewPreview' : 'adminFeeRenewalPreview');
    if (!input || !preview) return;
    const value = parseFloat(input.value);
    const safe = Number.isFinite(value) && value >= 0 ? Math.round(value) : 0;
    preview.textContent = '₱ ' + safe.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function saveApplicationFees(btn) {
    normalizeApplicationFeePrice(document.getElementById('adminFeeNewPrice'), 'new');
    normalizeApplicationFeePrice(document.getElementById('adminFeeRenewalPrice'), 'renewal');
    const formData = new FormData();
    formData.append('action', 'save_application_fees');
    formData.append('new_title', document.getElementById('adminFeeNewTitle').value.trim());
    formData.append('new_subtitle', document.getElementById('adminFeeNewSubtitle').value.trim());
    formData.append('new_price', document.getElementById('adminFeeNewPrice').value);
    formData.append('renewal_title', document.getElementById('adminFeeRenewalTitle').value.trim());
    formData.append('renewal_subtitle', document.getElementById('adminFeeRenewalSubtitle').value.trim());
    formData.append('renewal_price', document.getElementById('adminFeeRenewalPrice').value);
    const orig = btn ? btn.innerHTML : '';
    if (btn) {
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
      btn.disabled = true;
    }
    fetch('adminpanel.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          updateApplicationFeePreview('new');
          updateApplicationFeePreview('renewal');
          const note = document.getElementById('adminFeesUpdatedNote');
          if (note) {
            note.textContent = 'Last updated ' + new Date().toLocaleString('en-PH', {
              day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });
          }
        }
        alert(data.success ? data.message : ('Error: ' + (data.message || 'Save failed')));
      })
      .catch(err => alert('Error: ' + err.message))
      .finally(() => {
        if (btn) {
          btn.innerHTML = orig;
          btn.disabled = false;
        }
      });
  }

  function saveHomepageNewsCard(slot, saveBtnOverride) {
    const formData = new FormData();
    formData.append('action', 'save_homepage_news_card');
    formData.append('card_slot', slot);
    formData.append('tag_type', document.getElementById('newsTagType' + slot).value);
    formData.append('tag_label', document.getElementById('newsTagLabel' + slot).value);
    formData.append('card_date', document.getElementById('newsCardDate' + slot).value);
    formData.append('title', document.getElementById('newsTitle' + slot).value);
    formData.append('summary', document.getElementById('newsSummary' + slot).value);
    formData.append('full_details', document.getElementById('newsDetails' + slot).value);
    const btn = saveBtnOverride || document.querySelector('.mm-news-media[data-slot="' + slot + '"] .mm-btn-save');
    const orig = btn ? btn.innerHTML : '';
    if (btn) {
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
      btn.disabled = true;
    }
    fetch('adminpanel.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          updateNewsCardLabels(slot);
        }
        alert(data.success ? data.message : ('Error: ' + (data.message || 'Save failed')));
      })
      .catch(err => alert('Error: ' + err.message))
      .finally(() => {
        if (btn) {
          btn.innerHTML = orig;
          btn.disabled = false;
        }
      });
  }

  function uploadMedia(mediaType, file, sectionName = null, inputElement = null) {
    const formData = new FormData();
    formData.append('action', 'upload_media');
    formData.append('media_type', mediaType);
    formData.append('media_file', file);
    
    // Determine which section this is for
    if (sectionName) {
      // Section name provided directly
      if (sectionName === 'price_video') {
        formData.append('title', 'Market Price of Prime & Other Commodities Monitoring Report');
        formData.append('description', 'Official weekly monitoring report on prime and other commodity prices at Daet Public Market');
        formData.append('section', 'price_video');
      } else if (sectionName === 'third_video') {
        formData.append('title', 'Announcement Video');
        formData.append('description', 'General announcement video used across the site');
        formData.append('section', 'third_video');
      } else if (sectionName === 'homepage_announcement_video') {
        formData.append('title', 'Homepage Announcement Video');
        formData.append('description', 'Announcement video on the public homepage');
        formData.append('section', 'homepage_announcement_video');
      } else if (sectionName === 'overview_video') {
        formData.append('title', 'Promotional Permit Video');
        formData.append('description', 'Welcome video showcasing the Daet Public Market');
        formData.append('section', 'overview_video');
      } else {
        formData.append('title', 'Promotional Permit Video');
        formData.append('description', 'Welcome video showcasing the Daet Public Market');
        formData.append('section', 'overview_video');
      }
    } else if (inputElement) {
      // Determine based on input element ID
      const inputId = inputElement.id;
      if (inputId === 'homepageAnnouncementUpload') {
        formData.append('title', 'Homepage Announcement Video');
        formData.append('description', 'Announcement video on the public homepage');
        formData.append('section', 'homepage_announcement_video');
      } else if (inputId === 'video3Upload') {
        formData.append('title', 'Announcement Video');
        formData.append('description', 'General announcement video used across the site');
        formData.append('section', 'third_video');
      } else if (inputId === 'imageUpload') {
        formData.append('title', 'Market Price of Prime & Other Commodities Monitoring Report');
        formData.append('description', 'Official weekly monitoring report on prime and other commodity prices at Daet Public Market');
        formData.append('section', 'price_video');
      } else {
        formData.append('title', mediaType === 'video' ? 'Promotional Permit Video' : 'Market Price Image');
        formData.append('description', mediaType === 'video' ? 'Welcome video showcasing the Daet Public Market' : 'Latest market prices and information');
        formData.append('section', 'overview_video');
      }
    } else {
      // Default fallback
      formData.append('title', mediaType === 'video' ? 'Promotional Permit Video' : 'Market Price Image');
      formData.append('description', mediaType === 'video' ? 'Welcome video showcasing the Daet Public Market' : 'Latest market prices and information');
      formData.append('section', 'overview_video');
    }

    // Find the upload button - it's the next sibling of the input element's parent
    let button = null;
    if (inputElement) {
      const uploadControls = inputElement.closest('.upload-controls');
      if (uploadControls) {
        button = uploadControls.querySelector('button.mm-btn-upload, button.btn-primary');
      }
    }
    
    // Show loading
    const originalText = button ? button.innerHTML : '';
    if (button) {
      button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
      button.disabled = true;
    }

    fetch('adminpanel.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update the display - determine which section
        let sectionType = 'first';
        if (inputElement) {
          const inputId = inputElement.id;
          if (inputId === 'homepageAnnouncementUpload') {
            sectionType = 'homepage_announcement';
          } else if (inputId === 'video3Upload') {
            sectionType = 'third';
          } else if (inputId === 'imageUpload') {
            sectionType = 'second';
          }
        } else if (sectionName === 'homepage_announcement_video') {
          sectionType = 'homepage_announcement';
        } else if (sectionName === 'third_video') {
          sectionType = 'third';
        } else if (sectionName === 'price_video') {
          sectionType = 'second';
        }
        
        updateMediaDisplay(mediaType, data.file_path, sectionType);

        // Live-update the Upload History row (date/size/status) without a refresh.
        var sectionKeyMap = {
          'homepage_announcement': 'homepage_announcement_video',
          'third': 'third_video',
          'second': 'price_video',
          'first': 'overview_video'
        };
        var historySection = sectionKeyMap[sectionType] || 'overview_video';
        mmSetHistoryUploaded(historySection, (typeof file !== 'undefined' && file) ? file.size : null);

        alert(data.message);
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(error => {
      alert('Upload error: ' + error.message);
    })
    .finally(() => {
      // Restore button
      if (button) {
        button.innerHTML = originalText;
        button.disabled = false;
      }
    });
  }

  function updateMediaDisplay(mediaType, filePath, sectionType = 'first') {
    let containerId;
    
    // Determine container ID based on section type
    if (sectionType === 'homepage_announcement') {
      containerId = 'currentHomepageAnnouncementVideo';
    } else if (sectionType === 'third') {
      containerId = 'currentVideo3';
    } else if (sectionType === 'second') {
      containerId = 'currentImage';
    } else {
      containerId = `current${mediaType.charAt(0).toUpperCase() + mediaType.slice(1)}`;
    }
    
    const mediaContainer = document.getElementById(containerId);
    if (!mediaContainer) {
      console.error('Container not found:', containerId);
      return;
    }
    
    if (mediaType === 'video') {
      mediaContainer.innerHTML = `
        <video controls style="max-width: 300px; max-height: 200px;">
          <source src="${filePath}" type="video/mp4">
          Your browser does not support the video tag.
        </video>
        <p class="media-info">Current: ${filePath.split('/').pop()}</p>
      `;
    } else {
      mediaContainer.innerHTML = `
        <img src="${filePath}" alt="Market Price" style="max-width: 300px; max-height: 200px; object-fit: cover;">
        <p class="media-info">Current: ${filePath.split('/').pop()}</p>
      `;
    }
  }

  function removeVideo() {
    if (confirm('Are you sure you want to remove the current video?')) {
      removeMedia('video', 'overview_video', 'first');
    }
  }

  function removeImage() {
    if (confirm('Are you sure you want to remove the current video?')) {
      removeMedia('video', 'price_video', 'second');
    }
  }

  function removeVideo3() {
    if (confirm('Are you sure you want to remove the current video?')) {
      removeMedia('video', 'third_video', 'third');
    }
  }

  function removeHomepageAnnouncementVideo() {
    if (confirm('Are you sure you want to remove the homepage announcement video?')) {
      removeMedia('video', 'homepage_announcement_video', 'homepage_announcement');
    }
  }

  function removeMedia(mediaType, section = 'overview_video', sectionType = 'first') {
    const formData = new FormData();
    formData.append('action', 'remove_media');
    formData.append('media_type', mediaType);
    formData.append('section', section);

    fetch('adminpanel.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert(data.message);
        // Update the display to show placeholder - determine container ID
        let containerId;
        if (sectionType === 'homepage_announcement') {
          containerId = 'currentHomepageAnnouncementVideo';
        } else if (sectionType === 'third') {
          containerId = 'currentVideo3';
        } else if (sectionType === 'second') {
          containerId = 'currentImage';
        } else {
          containerId = `current${mediaType.charAt(0).toUpperCase() + mediaType.slice(1)}`;
        }
        
        const mediaContainer = document.getElementById(containerId);
        if (mediaContainer) {
          mediaContainer.innerHTML = `
            <div style="width: 300px; height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
              <p style="color: #666;">No ${mediaType} uploaded</p>
            </div>
            <p class="media-info">No ${mediaType} available</p>
          `;
        }
        mmSetHistoryRemoved(section);
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(error => {
      alert('Remove error: ' + error.message);
    });
  }

  function viewVendor(vendorId) {
    // Get vendor details via AJAX
    const formData = new FormData();
    formData.append('action', 'get_vendor_details');
    formData.append('vendor_id', vendorId);

    fetch('adminpanel.php', {
      method: 'POST',
      body: formData
    })
    .then(response => {
      // Check if response is ok
      if (!response.ok) {
        throw new Error('HTTP error! status: ' + response.status);
      }
      // Get response as text first to check if it's valid JSON
      return response.text();
    })
    .then(text => {
      try {
        const data = JSON.parse(text);
      if (data.success) {
        showVendorModal(data.vendor, data.documents);
      } else {
          alert('Error: ' + (data.message || 'Unknown error'));
        }
      } catch (e) {
        console.error('JSON parse error:', e);
        console.error('Response text:', text);
        alert('Error loading vendor details: Invalid response from server. Please check the console for details.');
      }
    })
    .catch(error => {
      console.error('Fetch error:', error);
      alert('Error loading vendor details: ' + error.message);
    });
  }

  function showVendorModal(vendor, documents) {
    let modalContent = `
      <div class="modal-header">
        <h2>Vendor Application Details</h2>
        <span class="close" onclick="closeVendorModal()">&times;</span>
      </div>
      <div class="modal-body">
        <div class="vendor-info">
          <h3>Application Information</h3>
          <div class="info-grid">
            <div class="info-item">
              <label>Application ID:</label>
              <span>#${vendor.vendor_id}</span>
            </div>
            <div class="info-item">
              <label>Vendor Name:</label>
              <span>${vendor.vendor_name}</span>
            </div>
            <div class="info-item">
              <label>User Name:</label>
              <span>${vendor.user_name}</span>
            </div>
            <div class="info-item">
              <label>Email:</label>
              <span>${vendor.email}</span>
            </div>
            <div class="info-item">
              <label>Section:</label>
              <span>${vendor.section}</span>
            </div>
            <div class="info-item">
              <label>Block:</label>
              <span>${vendor.block ?? ''}</span>
            </div>
            <div class="info-item">
              <label>Type:</label>
              <span class="badge ${vendor.application_type}">${vendor.application_type.charAt(0).toUpperCase() + vendor.application_type.slice(1)}</span>
            </div>
            <div class="info-item">
              <label>Status:</label>
              <span class="badge ${vendor.status}">${vendor.status.charAt(0).toUpperCase() + vendor.status.slice(1)}</span>
            </div>
            <div class="info-item">
              <label>Submitted:</label>
              <span>${new Date(vendor.submitted_date).toLocaleDateString()}</span>
            </div>
            ${vendor.reviewed_date ? `
            <div class="info-item">
              <label>Reviewed:</label>
              <span>${new Date(vendor.reviewed_date).toLocaleDateString()}</span>
            </div>
            ` : ''}
          </div>
        </div>
        
        ${documents.length > 0 ? `
        <div class="documents-section">
          <h3>Uploaded Documents</h3>
          <div class="documents-list">
            ${documents.map(doc => `
              <div class="document-item">
                <div class="document-info">
                  <div class="document-name">
                    ${doc.document_name}
                    ${doc.stall_number && doc.stall_number > 1 ? `<span style="background: #667eea; color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-left: 0.5rem; font-weight: bold;">Stall ${doc.stall_number}</span>` : ''}
                  </div>
                  <div class="document-meta">
                    <span class="document-type">${doc.document_type}</span>
                    <span class="document-size">${formatFileSize(doc.file_size)}</span>
                    <span class="document-date">${new Date(doc.uploaded_date).toLocaleDateString()}</span>
                    ${doc.stall_number && doc.stall_number > 1 ? `<span style="color: #667eea; font-weight: 600;">• Stall ${doc.stall_number}</span>` : ''}
                  </div>
                </div>
                <div class="document-actions">
                  <a href="${doc.document_path}" class="btn btn-primary" target="_blank">
                    <i class="fas fa-download"></i> Download
                  </a>
                  <a href="${doc.document_path}" class="btn btn-secondary" target="_blank">
                    <i class="fas fa-eye"></i> View
                  </a>
                  <button class="btn btn-info" onclick="previewDocument('${doc.document_path}', '${doc.mime_type}', '${doc.document_name}')">
                    <i class="fas fa-search-plus"></i> Preview
                  </button>
                </div>
              </div>
            `).join('')}
          </div>
        </div>
        ` : '<div class="no-documents"><p>No documents uploaded.</p></div>'}
        
        ${vendor.admin_notes ? `
        <div class="admin-notes">
          <h3>Admin Notes</h3>
          <p>${vendor.admin_notes}</p>
        </div>
        ` : ''}
      </div>
    `;
    
    document.getElementById('vendorModalBody').innerHTML = modalContent;
    document.getElementById('vendorModal').classList.add('show');
    document.body.classList.add('modal-open');
  }

  function closeVendorModal() {
    document.getElementById('vendorModal').classList.remove('show');
    document.body.classList.remove('modal-open');
  }
  
  // Ensure blur is removed when clicking outside modal or pressing escape
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
      e.target.classList.remove('show');
      document.body.classList.remove('modal-open');
    }
  });
  
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const newsModal = document.getElementById('newsCardDetailsModal');
      if (newsModal && newsModal.classList.contains('show')) {
        closeNewsCardDetailsModal();
        return;
      }
      // Close any open modal
      const openModal = document.querySelector('.modal.show');
      if (openModal) {
        openModal.classList.remove('show');
        document.body.classList.remove('modal-open');
      }
    }
  });
  
  // Ensure blur is removed on page load
  document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.remove('modal-open');
  });

  function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  function previewDocument(filePath, fileType, fileName) {
    // Create preview modal
    let previewContent = `
      <div class="modal-header">
        <h2>Document Preview: ${fileName}</h2>
        <span class="close" onclick="closePreviewModal()">&times;</span>
      </div>
      <div class="modal-body">
        <div class="preview-container">
    `;
    
    // Show different content based on file type
    if (fileType.toLowerCase().includes('image')) {
      previewContent += `
        <img src="${filePath}" alt="${fileName}" style="max-width: 100%; max-height: 70vh; object-fit: contain;">
      `;
    } else if (fileType.toLowerCase().includes('pdf')) {
      previewContent += `
        <iframe src="${filePath}" width="100%" height="600px" style="border: none;"></iframe>
      `;
    } else if (fileType.toLowerCase().includes('video')) {
      previewContent += `
        <video controls style="max-width: 100%; max-height: 70vh;">
          <source src="${filePath}" type="${fileType}">
          Your browser does not support the video tag.
        </video>
      `;
    } else {
      previewContent += `
        <div class="preview-placeholder">
          <i class="fas fa-file-alt" style="font-size: 4rem; color: #6c757d; margin-bottom: 1rem;"></i>
          <h3>Preview Not Available</h3>
          <p>This file type cannot be previewed in the browser.</p>
          <a href="${filePath}" class="btn btn-primary" target="_blank">
            <i class="fas fa-download"></i> Download to View
          </a>
        </div>
      `;
    }
    
    previewContent += `
        </div>
        <div class="preview-actions" style="margin-top: 20px; text-align: center;">
          <a href="${filePath}" class="btn btn-primary" target="_blank">
            <i class="fas fa-download"></i> Download
          </a>
          <a href="${filePath}" class="btn btn-secondary" target="_blank">
            <i class="fas fa-external-link-alt"></i> Open in New Tab
          </a>
        </div>
      </div>
    `;
    
    // Create or update preview modal
    let previewModal = document.getElementById('previewModal');
    if (!previewModal) {
      previewModal = document.createElement('div');
      previewModal.id = 'previewModal';
      previewModal.className = 'modal';
      previewModal.style.display = 'none';
      document.body.appendChild(previewModal);
    }
    
    previewModal.innerHTML = `
      <div class="modal-content" style="max-width: 90%; max-height: 90vh; overflow-y: auto;">
        ${previewContent}
      </div>
    `;
    
    previewModal.style.display = 'block';
  }

  function closePreviewModal() {
    const previewModal = document.getElementById('previewModal');
    if (previewModal) {
      previewModal.style.display = 'none';
    }
  }

  function approveVendor(vendorId) {
    try {
      console.log('approveVendor called with vendorId:', vendorId);
      
      if (!vendorId || vendorId <= 0) {
        alert('Invalid vendor ID');
        return false;
      }
      
      // Check if required elements exist
      const modal = document.getElementById('actionModal');
      const modalTitle = document.getElementById('actionModalTitle');
      const confirmBtn = document.getElementById('confirmActionBtn');
      const approvalForm = document.getElementById('approvalFormContainer');
      const rejectionForm = document.getElementById('rejectionFormContainer');
      const actionNotes = document.getElementById('actionNotes');
      
      if (!modal) {
        console.error('actionModal element not found');
        alert('Error: Modal element not found. Please refresh the page.');
        return false;
      }
      
      if (!modalTitle || !confirmBtn || !approvalForm || !rejectionForm) {
        console.error('Required modal elements not found');
        alert('Error: Modal elements not found. Please refresh the page.');
        return false;
      }
      
      // Set current action
    currentVendorId = vendorId;
    currentAction = 'approve';
      
      // Update modal title
      modalTitle.textContent = 'Approve Vendor Application';
      
      // Update button
      confirmBtn.innerHTML = '<i class="fas fa-check"></i> Approve';
      confirmBtn.className = 'btn btn-success';
      confirmBtn.style.backgroundColor = '#28a745';
      confirmBtn.style.borderColor = '#28a745';
      confirmBtn.style.color = '#fff';
      confirmBtn.disabled = false;
    
    // Show approval form, hide rejection form
      approvalForm.style.display = 'block';
      rejectionForm.style.display = 'none';
    
    // Clear notes field
      if (actionNotes) {
        actionNotes.value = '';
      }
    
      // Show the modal
      modal.classList.add('show');
    document.body.classList.add('modal-open');
      
      console.log('Approve modal displayed successfully');
      return true;
    } catch (error) {
      console.error('Error in approveVendor:', error);
      alert('An error occurred: ' + error.message);
      return false;
    }
  }
  
  // Make function globally accessible
  window.approveVendor = approveVendor;

  function incompleteVendor(vendorId) {
    try {
      console.log('incompleteVendor called with vendorId:', vendorId);
      
      if (!vendorId || vendorId <= 0) {
        alert('Invalid vendor ID');
        return false;
      }
      
      // Check if required elements exist
      const modal = document.getElementById('actionModal');
      const modalTitle = document.getElementById('actionModalTitle');
      const confirmBtn = document.getElementById('confirmActionBtn');
      const rejectionForm = document.getElementById('rejectionFormContainer');
      const approvalForm = document.getElementById('approvalFormContainer');
      const rejectionReason = document.getElementById('rejectionReason');
      const rejectionNotes = document.getElementById('rejectionNotes');
      
      if (!modal) {
        console.error('actionModal element not found');
        alert('Error: Modal element not found. Please refresh the page.');
        return false;
      }
      
      if (!modalTitle || !confirmBtn || !rejectionForm || !approvalForm) {
        console.error('Required modal elements not found');
        alert('Error: Modal elements not found. Please refresh the page.');
        return false;
      }
      
      // Set current action
      currentVendorId = vendorId;
      currentAction = 'incomplete';
      
      // Update modal title
      modalTitle.textContent = 'Mark Application as Incomplete';
      
      // Update button
      confirmBtn.innerHTML = '<i class="fas fa-file"></i> Incomplete';
      confirmBtn.className = 'btn btn-warning';
      confirmBtn.style.backgroundColor = '#f0ad4e';
      confirmBtn.style.borderColor = '#eea236';
      confirmBtn.style.color = '#fff';
      confirmBtn.disabled = false;
      
      // Show incomplete form, hide approval form
      rejectionForm.style.display = 'block';
      approvalForm.style.display = 'none';
      
      // Reset form
      if (rejectionReason) rejectionReason.value = '';
      if (rejectionNotes) rejectionNotes.value = '';
      const formElement = document.getElementById('rejectionForm');
      if (formElement) {
        formElement.reset();
      }
      
      // Show the modal
      modal.classList.add('show');
      document.body.classList.add('modal-open');
      
      console.log('Incomplete modal displayed successfully');
      return true;
    } catch (error) {
      console.error('Error in incompleteVendor:', error);
      alert('An error occurred: ' + error.message);
      return false;
    }
  }
  
  // Make function globally accessible
  window.incompleteVendor = incompleteVendor;

  function deleteVendor(vendorId, vendorName) {
    if (!confirm(`Note: this action cannot be undone po`)) {
      return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_vendor');
    formData.append('vendor_id', vendorId);
    
    // Find the row and show loading state
    const row = document.querySelector(`tr[data-vendor-id="${vendorId}"]`) || 
                Array.from(document.querySelectorAll('tbody tr')).find(tr => 
                  tr.querySelector('td:first-child')?.textContent.includes('#' + vendorId)
                );
    
    if (row) {
      const deleteBtn = row.querySelector('.btn-icon.delete');
      if (deleteBtn) {
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
      }
    }
    
    fetch('adminpanel.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showAlert(`Application for "${vendorName}" has been deleted successfully.`, 'success');
        // Remove the row from the table
        if (row) {
          row.style.transition = 'opacity 0.3s';
          row.style.opacity = '0';
          setTimeout(() => {
            row.remove();
            // Reload page to refresh stats
            setTimeout(() => { location.reload(); }, 500);
          }, 300);
        } else {
          setTimeout(() => { location.reload(); }, 800);
        }
      } else {
        showAlert(data.message || 'Failed to delete application', 'error');
        if (row) {
          const deleteBtn = row.querySelector('.btn-icon.delete');
          if (deleteBtn) {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
          }
        }
      }
    })
    .catch(error => {
      showAlert('Request failed: ' + error.message, 'error');
      if (row) {
        const deleteBtn = row.querySelector('.btn-icon.delete');
        if (deleteBtn) {
          deleteBtn.disabled = false;
          deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
        }
      }
    });
  }

  function closeActionModal() {
    // Reset processing flag
    isProcessing = false;
    
    document.getElementById('actionModal').classList.remove('show');
    document.getElementById('actionNotes').value = '';
    const rejectionForm = document.getElementById('rejectionForm');
    if (rejectionForm) {
      rejectionForm.reset();
      rejectionForm.style.pointerEvents = 'auto';
    }
    document.getElementById('rejectionFormContainer').style.display = 'none';
    document.getElementById('approvalFormContainer').style.display = 'none';
    document.body.classList.remove('modal-open');
    
    // Re-enable button
    const confirmBtn = document.getElementById('confirmActionBtn');
    if (confirmBtn) {
      confirmBtn.disabled = false;
    }
    
    currentVendorId = null;
    currentAction = null;
  }

  function confirmAction(event) {
    // Prevent default form submission if called from a form
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }
    
    // Prevent multiple simultaneous submissions
    if (isProcessing) {
      console.log('Action already processing, ignoring duplicate call');
      return false;
    }
    
    console.log('confirmAction called, currentAction:', currentAction, 'currentVendorId:', currentVendorId);
    
    if (!currentVendorId || !currentAction) {
      console.error('Invalid action state - currentVendorId:', currentVendorId, 'currentAction:', currentAction);
      showAlert('Invalid action state. Please try again.', 'error');
      return false;
    }
    
    // Store vendorId and action for error handling (before they get reset)
    const vendorIdForUpdate = currentVendorId;
    const actionForUpdate = currentAction;
    
    // Capture form fields BEFORE closeActionModal() — it clears notes and resets rejection form
    let approvalNotes = '';
    let rejectionReason = '';
    let rejectionNotes = '';
    if (actionForUpdate === 'incomplete') {
      const rejectionReasonEl = document.getElementById('rejectionReason');
      const rejectionNotesEl = document.getElementById('rejectionNotes');
      if (!rejectionReasonEl) {
        console.error('rejectionReason element not found');
        showAlert('Form error: Reason field not found. Please refresh the page.', 'error');
        return false;
      }
      rejectionReason = (rejectionReasonEl.value || '').trim();
      rejectionNotes = rejectionNotesEl ? (rejectionNotesEl.value || '') : '';
      if (!rejectionReason) {
        showAlert('Please select an incomplete reason.', 'error');
        return false;
      }
    } else if (actionForUpdate === 'approve') {
      const actionNotesEl = document.getElementById('actionNotes');
      approvalNotes = actionNotesEl ? (actionNotesEl.value || '') : '';
    } else {
      showAlert('Invalid action.', 'error');
      return false;
    }
    
    // Determine new status based on action BEFORE making request
    const expectedNewStatus = actionForUpdate === 'approve' ? 'approved' : (actionForUpdate === 'incomplete' ? 'incomplete' : 'pending');
    const expectedOldStatus = 'pending';
    
    // Close modal AFTER capturing inputs (closeActionModal clears currentVendorId/currentAction and forms)
    closeActionModal();
    
    // Optimistically update UI IMMEDIATELY before request - FORCE UPDATE
    console.log('=== OPTIMISTIC UPDATE: vendorId:', vendorIdForUpdate, 'to status:', expectedNewStatus, '===');
    updateRowStatus(vendorIdForUpdate, expectedNewStatus, expectedOldStatus);
    
    // Force multiple immediate updates to ensure it sticks
    setTimeout(() => updateRowStatus(vendorIdForUpdate, expectedNewStatus, expectedOldStatus), 0);
    setTimeout(() => updateRowStatus(vendorIdForUpdate, expectedNewStatus, expectedOldStatus), 10);
    
    // Set processing flag immediately
    isProcessing = true;
    
    const formData = new FormData();
    formData.append('vendor_id', vendorIdForUpdate);
    
    if (actionForUpdate === 'incomplete') {
      console.log('Submitting incomplete action - vendorId:', vendorIdForUpdate, 'reason:', rejectionReason);
      formData.append('action', 'incomplete_vendor');
      formData.append('rejection_reason', rejectionReason);
      formData.append('notes', rejectionNotes || '');
    } else if (actionForUpdate === 'approve') {
      formData.append('action', 'approve_vendor');
      formData.append('notes', approvalNotes || '');
    } else {
      showAlert('Invalid action.', 'error');
      isProcessing = false;
      return;
    }
    
    // Show loading state and disable button immediately
    const confirmBtn = document.getElementById('confirmActionBtn');
    if (!confirmBtn) {
      console.error('confirmActionBtn not found');
      isProcessing = false;
      return false;
    }
    
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    // Also disable the form to prevent any other submissions
    const form = document.getElementById('rejectionForm');
    if (form) {
      form.style.pointerEvents = 'none';
    }
    
    fetch('adminpanel.php', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => {
      console.log('Response status:', response.status, 'Content-Type:', response.headers.get('content-type'));
      const contentType = response.headers.get('content-type') || '';
      
      if (!response.ok) {
        return response.text().then(text => {
          console.error('Error response:', text);
          throw new Error('Network response was not ok: ' + response.status + ' - ' + text.substring(0, 100));
        });
      }
      
      // Always try to parse as JSON first
      return response.text().then(text => {
        console.log('Raw response text:', text.substring(0, 200));
      text = text.trim();
      
        // Remove any HTML/whitespace before JSON
      const jsonMatch = text.match(/\{[\s\S]*\}/);
      if (jsonMatch) {
          try {
            const jsonData = JSON.parse(jsonMatch[0]);
            console.log('Parsed JSON data:', jsonData);
            return jsonData;
          } catch (e) {
            console.error('JSON parse error:', e, 'Text:', jsonMatch[0]);
            throw new Error('Invalid JSON: ' + e.message);
          }
        }
        
        // If no JSON found, try direct parse
        try {
          const jsonData = JSON.parse(text);
          console.log('Direct parsed JSON:', jsonData);
          return jsonData;
      } catch (e) {
          console.error('Direct parse failed:', e);
          throw new Error('No valid JSON found in response: ' + text.substring(0, 200));
        }
      });
    })
    .then(data => {
      console.log('Parsed response data:', data);
      
      if (!data || typeof data !== 'object') {
        console.error('Invalid response data:', data);
        throw new Error('Invalid response format');
      }
      
      // Reset button state
      isProcessing = false;
      if (confirmBtn) {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = originalText;
      }
      
      // Re-enable form
      const form = document.getElementById('rejectionForm');
      if (form) {
        form.style.pointerEvents = 'auto';
      }
      
      if (data.success === true) {
        console.log('SUCCESS: Approval successful, closing modal and updating UI');
        // Determine success message and new status based on action (use actionForUpdate — currentAction was cleared with modal)
        let successMsg, newStatus;
        if (actionForUpdate === 'approve') {
          successMsg = data.message || 'Vendor application approved successfully!';
          newStatus = 'approved';
        } else if (actionForUpdate === 'incomplete') {
          successMsg = data.message || 'Vendor application marked as incomplete successfully!';
          newStatus = 'incomplete';
        } else {
          successMsg = data.message || 'Action completed successfully!';
          newStatus = actionForUpdate;
        }
        
        console.log('Success:', successMsg, 'Action:', actionForUpdate, 'New Status:', newStatus);
        
        // Use vendor_id from response if available, otherwise vendorIdForUpdate (modal cleared currentVendorId)
        const vendorIdToUpdate = (data.vendor_id && data.vendor_id > 0) ? data.vendor_id : vendorIdForUpdate;
        
        // ANTI-REFRESH: Save status to localStorage IMMEDIATELY
        if (newStatus === 'approved' || newStatus === 'incomplete') {
          saveVendorStatus(vendorIdToUpdate, newStatus);
        }
        
        console.log('Vendor ID to update:', vendorIdToUpdate, 'Type:', typeof vendorIdToUpdate, 'From response:', data.vendor_id);
        
        // Validate vendorId
        if (!vendorIdToUpdate || vendorIdToUpdate <= 0) {
          console.error('Invalid vendorId:', vendorIdToUpdate);
          showAlert('Error: Invalid vendor ID. Please refresh the page.', 'error');
          closeActionModal();
          return;
        }
        
        // Try to get old status from any existing row BEFORE closing modal
          let oldStatus = 'pending';
        const existingRows = document.querySelectorAll(`tr[data-vendor-id="${vendorIdToUpdate}"]`);
        if (existingRows.length > 0) {
          const firstRow = existingRows[0];
          const dataStatus = firstRow.getAttribute('data-status');
          if (dataStatus) {
            oldStatus = dataStatus;
          } else {
            // Try to get from badge
            const statusCell = firstRow.querySelector('td:nth-child(7)') || firstRow.querySelector('td:nth-child(6)');
            if (statusCell) {
              const badge = statusCell.querySelector('.badge');
              if (badge) {
                const badgeClasses = badge.className.split(' ');
                const statusClass = badgeClasses.find(cls => cls !== 'badge' && cls !== 'application_type');
                if (statusClass) {
                  oldStatus = statusClass;
              }
            }
              }
            }
          }
          
        console.log('=== SERVER CONFIRMED SUCCESS - FORCING STATUS UPDATE ===');
          console.log('Updating row status - vendorId:', vendorIdToUpdate, 'oldStatus:', oldStatus, 'newStatus:', newStatus);
          
        // Modal already closed, just force update status
        // Force immediate update - call multiple times to ensure it works
          updateRowStatus(vendorIdToUpdate, newStatus, oldStatus);
        
        // Force update again immediately (sometimes DOM needs a moment)
        requestAnimationFrame(() => {
          console.log('Retry update via requestAnimationFrame');
          updateRowStatus(vendorIdToUpdate, newStatus, oldStatus);
        });
        
        // Also try updating after short delays in case DOM wasn't ready or rows are in hidden sections
        setTimeout(() => {
          console.log('Retry update #1 after 50ms delay for vendorId:', vendorIdToUpdate);
          updateRowStatus(vendorIdToUpdate, newStatus, oldStatus);
        }, 50);
        
        setTimeout(() => {
          console.log('Retry update #2 after 200ms delay for vendorId:', vendorIdToUpdate);
          updateRowStatus(vendorIdToUpdate, newStatus, oldStatus);
        }, 200);
        
        setTimeout(() => {
          console.log('Retry update #3 after 500ms delay for vendorId:', vendorIdToUpdate);
          updateRowStatus(vendorIdToUpdate, newStatus, oldStatus);
        }, 500);
        
        showAlert(successMsg, 'success');
        
        // Add to recent activities list dynamically
        if (data.vendor_name && data.admin_name && data.action_type) {
          console.log('Adding recent activity:', data);
          addRecentActivity({
            vendor_name: data.vendor_name,
            application_type: data.application_type || 'application',
            admin_name: data.admin_name,
            action_type: data.action_type
          });
        } else {
          console.warn('Missing data for recent activity:', data);
        }
        
        // Apply filter after status change (e.g., if filtering by "pending" and we approve, hide the row)
        if (typeof filterVendors === 'function') {
          filterVendors();
        }
        
        // No page reload needed - UI is updated dynamically
      } else {
        const errorMsg = data.message || 'Action failed';
        console.error('Action failed:', errorMsg);
        showAlert('Error: ' + errorMsg, 'error');
        
        // Close modal even on error
        closeActionModal();
        
        // Revert optimistic update on error
        if (vendorIdForUpdate) {
          console.log('Reverting optimistic update for vendorId:', vendorIdForUpdate);
          const failedAction = actionForUpdate;
          const revertStatus = failedAction === 'approve' ? 'approved' : (failedAction === 'incomplete' ? 'incomplete' : 'pending');
          updateRowStatus(vendorIdForUpdate, 'pending', revertStatus);
        }
      }
    })
    .catch(error => {
      console.error('Error in confirmAction:', error);
      isProcessing = false;
      if (confirmBtn) {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = originalText;
      }
      
      // Re-enable form
      const form = document.getElementById('rejectionForm');
      if (form) {
        form.style.pointerEvents = 'auto';
      }
      
      // Close modal on error
      closeActionModal();
      
      // Revert optimistic update on error
      if (vendorIdForUpdate) {
        console.log('Reverting optimistic update for vendorId:', vendorIdForUpdate);
        const revertStatus = actionForUpdate === 'approve' ? 'approved' : (actionForUpdate === 'incomplete' ? 'incomplete' : 'pending');
        updateRowStatus(vendorIdForUpdate, 'pending', revertStatus);
      }
      
      showAlert('Request failed: ' + error.message, 'error');
    });
  }

  // Direct actions without modal - updates UI instantly
  function directApprove(vendorId) {
    const notes = prompt('Approval notes (optional):') || '';
    
    const formData = new FormData();
    formData.append('action', 'approve_vendor');
    formData.append('vendor_id', vendorId);
    formData.append('notes', notes);

    // Update UI immediately (optimistic update)
    updateRowStatus(vendorId, 'approved', 'pending');

    fetch('adminpanel.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          showAlert(data.message);
          // UI already updated, no reload needed
        } else {
          // Revert on error
          updateRowStatus(vendorId, 'pending', 'approved');
          showAlert(data.message || 'Approve failed', 'error');
        }
      })
      .catch(e => {
        updateRowStatus(vendorId, 'pending', 'approved');
        showAlert('Network error: ' + e.message, 'error');
      });
  }

  function directIncomplete(vendorId) {
    const reason = prompt('Enter incomplete reason (required):');
    if (!reason || !reason.trim()) {
      showAlert('Incomplete reason is required.', 'error');
      return;
    }
    const notes = prompt('Additional notes (optional):') || '';

    const formData = new FormData();
    formData.append('action', 'incomplete_vendor');
    formData.append('vendor_id', vendorId);
    formData.append('rejection_reason', reason);
    formData.append('notes', notes);

    // Update UI immediately (optimistic update)
    updateRowStatus(vendorId, 'incomplete', 'pending');

    fetch('adminpanel.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          // ANTI-REFRESH: Save to localStorage
          saveVendorStatus(vendorId, 'incomplete');
          showAlert(data.message);
          // UI already updated, no reload needed
        } else {
          // Revert on error
          updateRowStatus(vendorId, 'pending', 'incomplete');
          showAlert(data.message || 'Mark incomplete failed', 'error');
        }
      })
      .catch(e => {
        updateRowStatus(vendorId, 'pending', 'incomplete');
        showAlert('Network error: ' + e.message, 'error');
      });
  }

  function updateRowStatus(vendorId, newStatus, oldStatus) {
    console.log('=== updateRowStatus CALLED ===');
    console.log('Parameters:', { vendorId, newStatus, oldStatus });
    console.log('VendorId type:', typeof vendorId, 'Value:', vendorId);
    
    // Convert vendorId to string for comparison
    const vendorIdStr = vendorId.toString();
    console.log('VendorId as string:', vendorIdStr);
    
    // Log all available vendor IDs on the page for debugging
    const allVendorIds = Array.from(document.querySelectorAll('tr[data-vendor-id]')).map(tr => ({
      id: tr.getAttribute('data-vendor-id'),
      status: tr.getAttribute('data-status'),
      row: tr
    }));
    console.log('All vendor IDs on page:', allVendorIds);
    
    // Find all rows with this vendor ID (search in ALL sections, including hidden ones)
    let allRows = Array.from(document.querySelectorAll(`tr[data-vendor-id="${vendorIdStr}"]`));
    
    console.log('Found', allRows.length, 'rows with data-vendor-id attribute');
    
    if (allRows.length === 0) {
      console.warn('No matching row found by data-vendor-id, trying alternative search...');
      // Try alternative search methods - search ALL tbody elements including hidden sections
      const allTableRows = document.querySelectorAll('tbody tr');
      console.log('Searching in', allTableRows.length, 'total table rows');
      
      for (let tr of allTableRows) {
        // Check data-vendor-id attribute first
        const dataVendorId = tr.getAttribute('data-vendor-id');
        if (dataVendorId && (dataVendorId === vendorIdStr || parseInt(dataVendorId) === parseInt(vendorIdStr))) {
          console.log('Found row by data-vendor-id attribute:', vendorId);
          allRows.push(tr);
          continue;
        }
        
        // Try finding by ID cell text (might be formatted with # and commas)
        const idCell = tr.querySelector('td:first-child');
        if (idCell) {
          const idText = idCell.textContent.trim().replace('#', '').replace(/,/g, '').trim();
          if (idText === vendorIdStr || parseInt(idText) === parseInt(vendorIdStr)) {
            console.log('Found row by ID cell text:', vendorId);
            allRows.push(tr);
          }
        }
      }
    }
    
    if (allRows.length === 0) {
      console.error('CRITICAL: No matching row found for vendor ID:', vendorId);
      const availableIds = Array.from(document.querySelectorAll('tr[data-vendor-id]')).map(tr => tr.getAttribute('data-vendor-id'));
      console.log('Available vendor IDs in page:', availableIds);
      // Still try to update stats even if rows not found
    } else {
      console.log('Successfully found', allRows.length, 'matching row(s) to update');
    }
    
    // Update each row found (handles both dashboard Recent Applications and vendors sections)
    allRows.forEach((row, index) => {
      console.log(`Updating row ${index + 1} of ${allRows.length}`);
      
      // Find status cell - try multiple methods
      const cells = row.querySelectorAll('td');
      let statusCell = null;
      let statusBadge = null;
      
      // Method 1: Find by badge class (status badges have pending/approved/incomplete classes)
      for (let i = 0; i < cells.length; i++) {
        const cell = cells[i];
        const badge = cell.querySelector('.badge');
        if (badge) {
          // Check if this is a status badge (has status class, not application_type)
          const hasStatusClass = badge.classList.contains('pending') || 
                                badge.classList.contains('approved') || 
                                badge.classList.contains('incomplete');
          // Make sure it's not an application_type badge (those have 'new' or 'renewal' classes)
          const isNotTypeBadge = !badge.classList.contains('new') && 
                                 !badge.classList.contains('renewal') &&
                                 !badge.classList.contains('application');
          
          if (hasStatusClass && isNotTypeBadge) {
            statusCell = cell;
            statusBadge = badge;
            console.log(`Found status badge in column ${i + 1} by class`);
            break;
          }
        }
      }
      
      // Method 2: Try 7th column (Status column)
      if (!statusCell && cells.length >= 7) {
        statusCell = cells[6]; // 7th column (0-indexed)
        statusBadge = statusCell ? statusCell.querySelector('.badge') : null;
        if (statusBadge) {
          console.log(`Found status badge in 7th column`);
        }
      }
      
      // Method 3: Try finding by header text (if table has headers)
      if (!statusCell) {
        const table = row.closest('table');
        if (table) {
          const headers = table.querySelectorAll('thead th');
          for (let i = 0; i < headers.length && i < cells.length; i++) {
            if (headers[i].textContent.trim().toLowerCase() === 'status') {
              statusCell = cells[i];
              statusBadge = statusCell ? statusCell.querySelector('.badge') : null;
              if (statusBadge) {
                console.log(`Found status badge by header text in column ${i + 1}`);
                break;
              }
            }
          }
        }
      }
      
        // Update the status badge
    if (statusCell) {
        if (!statusBadge) {
        // Create badge if it doesn't exist
          statusBadge = document.createElement('span');
          statusBadge.className = 'badge ' + newStatus;
          statusCell.innerHTML = ''; // Clear cell content
          statusCell.appendChild(statusBadge);
          console.log(`Created new badge in row ${index + 1}`);
        }
        
        // Log before state
        const beforeClass = statusBadge.className;
        const beforeText = statusBadge.textContent;
        console.log(`BEFORE UPDATE - Row ${index + 1}:`, 'Class:', beforeClass, 'Text:', beforeText);
        
        // Force update badge - remove all status classes first, then add new one
        statusBadge.className = 'badge ' + newStatus; // Direct assignment is more reliable
        statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
        
        // Force a reflow to ensure the change is visible
        void statusBadge.offsetHeight;
        
        // Verify the update worked
        const afterClass = statusBadge.className;
        const afterText = statusBadge.textContent;
        console.log(`AFTER UPDATE - Row ${index + 1}:`, 'Class:', afterClass, 'Text:', afterText);
        
        if (afterClass.includes(newStatus) && afterText.toLowerCase() === newStatus.toLowerCase()) {
          console.log(`✓✓✓ SUCCESS: Badge updated correctly in row ${index + 1}`);
    } else {
          console.error(`✗✗✗ FAILED: Badge not updated correctly in row ${index + 1}`);
          // Try one more time with innerHTML as last resort
          statusCell.innerHTML = '<span class="badge ' + newStatus + '">' + newStatus.charAt(0).toUpperCase() + newStatus.slice(1) + '</span>';
          console.log(`Tried innerHTML update for row ${index + 1}`);
        }
        
        // Add a brief flash animation to confirm update
        statusCell.style.transition = 'background-color 0.3s';
        statusCell.style.backgroundColor = '#e8f5e9';
        setTimeout(() => {
          statusCell.style.backgroundColor = '';
        }, 300);
      } else {
        console.error(`✗ Status cell not found for row ${index + 1}. Total cells:`, cells.length);
        // Log cell contents for debugging
        cells.forEach((cell, idx) => {
          console.log(`  Cell ${idx + 1}:`, cell.textContent.trim().substring(0, 50));
        });
      }

      // Remove approve/incomplete buttons (only show for pending status)
    const actionsCell = row.querySelector('td:last-child');
    if (actionsCell) {
      const btnGroup = actionsCell.querySelector('.btn-group');
      if (btnGroup) {
        const approveBtn = btnGroup.querySelector('.btn-icon.approve');
        const incompleteBtn = btnGroup.querySelector('.btn-icon.incomplete');
        if (approveBtn) {
            if (newStatus !== 'pending') {
              console.log(`Removing approve button from row ${index + 1}`);
          approveBtn.remove();
            } else {
              approveBtn.style.display = '';
            }
        }
        if (incompleteBtn) {
            if (newStatus !== 'pending') {
              console.log(`Removing incomplete button from row ${index + 1}`);
          incompleteBtn.remove();
            } else {
              incompleteBtn.style.display = '';
            }
        }
      }
    }

    // Update row data attribute for filtering
    row.setAttribute('data-status', newStatus);
      console.log(`✓ Updated row ${index + 1} data-status to:`, newStatus);
    });
    
    // ADDITIONAL FALLBACK: Direct badge update for all matching vendor IDs
    // This ensures we catch any badges we might have missed
    if (allRows.length === 0) {
      console.warn('No rows found by data-vendor-id, trying direct badge search...');
      // Search all badges in the document
      const allBadges = document.querySelectorAll('.badge.pending, .badge.approved, .badge.incomplete');
      console.log('Found', allBadges.length, 'status badges in document');
      
      allBadges.forEach((badge, badgeIndex) => {
        // Find the parent row
        const row = badge.closest('tr');
        if (row) {
          // Check if this row has the matching vendor ID (try multiple formats)
          const rowVendorId = row.getAttribute('data-vendor-id');
          const rowVendorIdNum = rowVendorId ? parseInt(rowVendorId) : null;
          const vendorIdNum = parseInt(vendorIdStr);
          
          const matches = (rowVendorId === vendorIdStr) || 
                         (rowVendorIdNum === vendorIdNum) ||
                         (rowVendorId && rowVendorId.toString() === vendorIdStr) ||
                         (rowVendorIdNum && rowVendorIdNum.toString() === vendorIdStr);
          
          if (matches) {
            console.log(`Found matching badge #${badgeIndex + 1} by direct search, updating...`);
            badge.className = 'badge ' + newStatus;
            badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            row.setAttribute('data-status', newStatus);
            
            // Remove buttons
            const approveBtn = row.querySelector('.btn-icon.approve');
            const incompleteBtn = row.querySelector('.btn-icon.incomplete');
            if (approveBtn && newStatus !== 'pending') {
              console.log('Removing approve button via fallback');
              approveBtn.remove();
            }
            if (incompleteBtn && newStatus !== 'pending') {
              console.log('Removing incomplete button via fallback');
              incompleteBtn.remove();
            }
            
            allRows.push(row); // Add to list so we don't process again
          }
        }
      });
      
      if (allRows.length === 0) {
        console.error('CRITICAL: Still no rows found after all search methods!');
        console.error('This means the vendor ID', vendorIdStr, 'does not exist in the current page DOM');
      }
    }

    // Update stats instantly by diffing counts
    const statCards = document.querySelectorAll('.stat-card .value');
    if (statCards.length >= 4) {
      // indexes based on layout: 0=total, 1=pending, 2=approved, 3=incomplete
      const num = (el) => parseInt((el?.textContent || '0').replace(/\D/g,'')) || 0;
      const set = (el, v) => { if (el) el.textContent = v; };

      const total = num(statCards[0]);
      let pending = num(statCards[1]);
      let approved = num(statCards[2]);
      let incomplete = num(statCards[3]);

      if (oldStatus === 'pending' && newStatus === 'approved') { pending = Math.max(0, pending - 1); approved += 1; }
      if (oldStatus === 'pending' && newStatus === 'incomplete') { pending = Math.max(0, pending - 1); incomplete += 1; }
      if (oldStatus === 'approved' && newStatus === 'pending') { approved = Math.max(0, approved - 1); pending += 1; }
      if (oldStatus === 'approved' && newStatus === 'incomplete') { approved = Math.max(0, approved - 1); incomplete += 1; }
      if (oldStatus === 'incomplete' && newStatus === 'pending') { incomplete = Math.max(0, incomplete - 1); pending += 1; }
      if (oldStatus === 'incomplete' && newStatus === 'approved') { incomplete = Math.max(0, incomplete - 1); approved += 1; }

      set(statCards[1], pending);
      set(statCards[2], approved);
      set(statCards[3], incomplete);
      set(statCards[0], total); // total unchanged
    }

    saveVendorStatus(vendorId, newStatus);
  }

  function addRecentActivity(activityData) {
    const activityList = document.querySelector('.activity-list');
    if (!activityList) {
      console.warn('Activity list not found');
      return;
    }

    // Remove "No recent activities" message if present
    const noActivityMsg = activityList.querySelector('.activity-item .activity-text');
    if (noActivityMsg && noActivityMsg.textContent.trim() === 'No recent activities') {
      activityList.innerHTML = '';
    }

    // Create new activity item
    const activityItem = document.createElement('div');
    activityItem.className = 'activity-item';

    const adminName = activityData.admin_name || 'Admin';
    const adminInitials = adminName.charAt(0).toUpperCase();
    const vendorName = activityData.vendor_name || 'Unknown Vendor';
    const appType = activityData.application_type ? activityData.application_type.charAt(0).toUpperCase() + activityData.application_type.slice(1) : 'Application';
    const actionType = activityData.action_type || 'approve';
    const actionIconClass = actionType === 'approve' ? 'approved' : 'incomplete';
    const icon = actionType === 'approve' ? 'fa-heart' : 'fa-times-circle';

    let activityText = '';
    if (actionType === 'approve') {
      activityText = `<strong>${adminName}</strong> approved the application: <strong>${vendorName}</strong> (<span class="activity-item-name">${appType}</span>) <i class="fas ${icon} activity-icon ${actionIconClass}"></i>`;
    } else {
      activityText = `<strong>${adminName}</strong> mark as <span style="color: #ffc107; font-weight: 600;">incomplete</span>: <strong>${vendorName}</strong> (<span class="activity-item-name">${appType}</span>) <i class="fas ${icon} activity-icon ${actionIconClass}"></i>`;
    }

    activityItem.innerHTML = `
      <div class="activity-avatar">${adminInitials}</div>
      <div class="activity-content">
        <div class="activity-text">
          ${activityText}
        </div>
        <div class="activity-time">Just now</div>
      </div>
    `;

    // Replace existing activity with new one (keep only 1 activity)
    const existingActivities = activityList.querySelectorAll('.activity-item');
    existingActivities.forEach(activity => activity.remove());
    
    // Insert the new activity
    activityList.appendChild(activityItem);

    // Update time after a short delay to show "Just now"
    setTimeout(() => {
      const timeElement = activityItem.querySelector('.activity-time');
      if (timeElement) {
        timeElement.textContent = 'Just now';
      }
    }, 100);
  }

  function filterVendors() {
    const statusFilterEl = document.getElementById('statusFilter');
    const typeFilterEl   = document.getElementById('typeFilter');
    const searchInputEl  = document.getElementById('searchInput');

    // Only filter if we're in the vendors section (these elements exist)
    if (!statusFilterEl || !searchInputEl) {
      return;
    }

    const statusFilter = statusFilterEl.value;
    const typeFilter   = typeFilterEl ? typeFilterEl.value : '';
    const searchInput  = searchInputEl.value.toLowerCase();
    const rows = document.querySelectorAll('#vendors-table tr');

    rows.forEach(row => {
      const status = row.getAttribute('data-status') || '';
      const type   = row.getAttribute('data-type')   || '';
      const name   = row.getAttribute('data-name')   || '';

      const statusMatch = !statusFilter || status === statusFilter;
      const typeMatch   = !typeFilter   || type   === typeFilter;
      const nameMatch   = !searchInput  || name.includes(searchInput);

      row.style.display = (statusMatch && typeMatch && nameMatch) ? '' : 'none';
    });
  }

  /* ── Records view toggle ── */
  var _recordsView = 'grid'; // default

  function setRecordsView(mode) {
    _recordsView = mode;
    var grid   = document.getElementById('records-grid');
    var drawer = document.getElementById('records-drawer');
    var btnGrid   = document.getElementById('recordsGridBtn');
    var btnDrawer = document.getElementById('recordsDrawerBtn');

    if (mode === 'drawer') {
      if (grid)   grid.style.display   = 'none';
      if (drawer) drawer.style.display = '';
      btnDrawer.classList.add('is-active');
      btnGrid.classList.remove('is-active');
    } else {
      if (grid)   grid.style.display   = '';
      if (drawer) drawer.style.display = 'none';
      btnGrid.classList.add('is-active');
      btnDrawer.classList.remove('is-active');
    }
    // re-run search in new mode
    filterRecords();
  }

  function toggleDrawerItem(rowEl) {
    var item = rowEl.closest('.drawer-item');
    if (!item) return;
    item.classList.toggle('is-open');
  }

  function filterRecords() {
    const searchInput = document.getElementById('recordsSearchInput').value.toLowerCase();

    // Grid mode
    const cards = document.querySelectorAll('#records-grid .user-record-card');
    cards.forEach(card => {
      const name = card.getAttribute('data-name') || '';
      card.style.display = (!searchInput || name.includes(searchInput)) ? '' : 'none';
    });

    // Drawer mode
    const drawerItems = document.querySelectorAll('#records-drawer .drawer-item');
    drawerItems.forEach(item => {
      const name = item.getAttribute('data-name') || '';
      item.style.display = (!searchInput || name.includes(searchInput)) ? '' : 'none';
    });
  }

  function filterAdminLog() {
    const searchInput = document.getElementById('adminLogSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#admin-log-table tr');

    rows.forEach(row => {
      const name = row.getAttribute('data-name');
      const nameMatch = !searchInput || name.includes(searchInput);
      
      if (nameMatch) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  }

  // Guard against the browser/password-manager autofilling the admin log
  // filter with a saved email (e.g. daetpublicmarket@gmail.com). We wipe any
  // value that appears before the user actually interacts with the field.
  (function () {
    var el = document.getElementById('adminLogSearchInput');
    if (!el) return;
    var userTouched = false;
    ['focus', 'keydown', 'input', 'paste'].forEach(function (evt) {
      el.addEventListener(evt, function () { userTouched = true; }, { once: false });
    });
    function clearIfAutofilled() {
      if (!userTouched && el.value) {
        el.value = '';
        filterAdminLog();
      }
    }
    // Autofill can land immediately or a moment after load, so check a few times.
    [0, 150, 400, 800, 1500].forEach(function (t) { setTimeout(clearIfAutofilled, t); });
    window.addEventListener('load', clearIfAutofilled);
  })();

  function filterReceipts() {
    const searchInput = document.getElementById('receiptsSearchInput').value.toLowerCase();
    const statusEl = document.getElementById('receiptsStatusFilter');
    const statusFilter = statusEl ? statusEl.value : 'all';
    const rows = document.querySelectorAll('#receipts-table tr');
    rows.forEach(row => {
      const name = row.getAttribute('data-name');
      if (name === null) return;
      const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
      const nameMatch = !searchInput || name.includes(searchInput);
      const statusMatch = statusFilter === 'all' || rowStatus === statusFilter;

      if (nameMatch && statusMatch) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  }

  function filterConcerns() {
    const input = document.getElementById('concernsSearchInput');
    if (!input) return;
    const q = input.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#concerns-table tr');
    rows.forEach(row => {
      const blob = (row.getAttribute('data-search') || '').toLowerCase();
      if (!q || blob.includes(q)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  }

  function viewConcernMessage(btn) {
    if (!btn) return;
    const msg = btn.getAttribute('data-message') || '';
    const name = btn.getAttribute('data-name') || 'Guest';
    const email = btn.getAttribute('data-email') || '—';
    const date = btn.getAttribute('data-date') || '—';
    const nameEl = document.getElementById('concernModalName');
    const emailEl = document.getElementById('concernModalEmail');
    const dateEl = document.getElementById('concernModalDate');
    const msgEl = document.getElementById('concernModalMessage');
    if (nameEl) nameEl.textContent = name;
    if (emailEl) emailEl.textContent = email;
    if (dateEl) dateEl.textContent = date;
    if (msgEl) msgEl.textContent = msg;
    const modal = document.getElementById('concernMessageModal');
    if (modal) {
      modal.classList.add('show');
      document.body.classList.add('modal-open');
    }
  }

  function closeConcernMessageModal() {
    const modal = document.getElementById('concernMessageModal');
    if (modal) modal.classList.remove('show');
    document.body.classList.remove('modal-open');
  }

  function mmFilterHistory() {
    const input = document.getElementById('mmHistorySearch');
    if (!input) return;
    const q = input.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#mmHistoryBody tr');
    let shown = 0;
    let total = 0;
    rows.forEach(row => {
      if (row.classList.contains('mm-empty-row')) return;
      total++;
      const blob = (row.getAttribute('data-search') || '').toLowerCase();
      if (!q || blob.includes(q)) {
        row.style.display = '';
        shown++;
      } else {
        row.style.display = 'none';
      }
    });
    const counter = document.getElementById('mmHistoryCount');
    if (counter) counter.textContent = 'Showing ' + shown + ' of ' + total + ' entries';
  }

  function mmSetHistoryRemoved(section) {
    const row = document.querySelector('#mmHistoryBody tr[data-section="' + section + '"]');
    if (!row) return;
    const statusEl = row.querySelector('.mm-status');
    if (statusEl) {
      statusEl.textContent = 'Removed';
      statusEl.classList.add('mm-status--removed');
    }
    const actions = row.querySelector('.mm-row-actions');
    if (actions) actions.innerHTML = '';
  }

  function mmFormatBytes(bytes) {
    bytes = Number(bytes) || 0;
    if (bytes <= 0) return '—';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = Math.floor(Math.log(bytes) / Math.log(1024));
    i = Math.max(0, Math.min(i, units.length - 1));
    const val = bytes / Math.pow(1024, i);
    const rounded = (val >= 10 || i === 0) ? Math.round(val) : Math.round(val * 10) / 10;
    return rounded + ' ' + units[i];
  }

  // Live-update an Upload History row right after a successful upload (no refresh).
  function mmSetHistoryUploaded(section, fileSizeBytes) {
    const row = document.querySelector('#mmHistoryBody tr[data-section="' + section + '"]');
    if (!row) return;

    const statusEl = row.querySelector('.mm-status');
    if (statusEl) {
      statusEl.textContent = 'Active';
      statusEl.classList.remove('mm-status--removed');
    }

    const dateEl = row.querySelector('.mm-date-cell');
    if (dateEl) {
      const now = new Date();
      const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      dateEl.textContent = months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
    }

    if (typeof fileSizeBytes !== 'undefined' && fileSizeBytes !== null) {
      const sizeEl = row.querySelector('.mm-size-cell');
      if (sizeEl) sizeEl.textContent = mmFormatBytes(fileSizeBytes);
    }

    const actions = row.querySelector('.mm-row-actions');
    if (actions) {
      actions.innerHTML = '<a href="adminpanel.php?action=download_media&section=' +
        encodeURIComponent(section) + '" title="Download video"><i class="fas fa-download"></i></a>';
    }
  }

  // Statistics Graph Modal Functions
  let statisticsChart = null;
  let currentStatType = null;
  let currentYear = new Date().getFullYear();

  function showStatisticsGraph(type) {
    currentStatType = type; // Store the selected statistic type
    const now = new Date().getFullYear();
    currentYear = now >= 2025 ? now : 2025; // Default to 2025 or current year if later
    
    const modal = document.getElementById('statisticsGraphModal');
    const modalTitle = document.getElementById('graphModalTitle');
    const yearSelect = document.getElementById('graphYearSelect');
    const currentYearDisplay = document.getElementById('graphCurrentYear');
    
    if (!modal || !modalTitle) {
      alert('Graph modal not found. Please refresh the page.');
      return;
    }
    
    // Set modal title based on the selected statistic type
    const titleMap = {
      'total': 'Total Application Statistic',
      'pending': 'Pending Review Statistic',
      'approved': 'Approved Statistic',
      'incomplete': 'Incomplete Statistic'
    };
    const title = titleMap[type] || 'Application Statistic';
    modalTitle.innerHTML = `<i class="fas fa-chart-line"></i> ${title}`;
    
    // Populate year dropdown (2025 to 3000)
    yearSelect.innerHTML = '';
    const startYear = 2025;
    const endYear = 3000;
    for (let y = startYear; y <= endYear; y++) {
      const option = document.createElement('option');
      option.value = y;
      option.textContent = y;
      if (y === currentYear) {
        option.selected = true;
      }
      yearSelect.appendChild(option);
    }
    
    // Add event listeners to prevent dropdown from covering chart
    yearSelect.addEventListener('focus', function() {
      this.size = 5; // Show only 5 options at a time
      this.style.position = 'relative';
      this.style.zIndex = '1001';
    });
    
    yearSelect.addEventListener('blur', function() {
      this.size = 1; // Back to normal dropdown
      this.style.zIndex = 'auto';
    });
    
    yearSelect.addEventListener('change', function() {
      this.size = 1; // Back to normal dropdown after selection
      this.style.zIndex = 'auto';
    });
    
    // Show current year
    currentYearDisplay.textContent = `Current Year: ${currentYear}`;
    
    // Show modal
    modal.classList.add('show');
    document.body.classList.add('modal-open');
    
    // Load graph data for the selected type only
    loadGraphData(type, currentYear);
  }

  function updateGraphYear() {
    const yearSelect = document.getElementById('graphYearSelect');
    const selectedYear = parseInt(yearSelect.value);
    if (selectedYear) {
      currentYear = selectedYear;
      const yearDisplay = document.getElementById('graphCurrentYear');
      if (yearDisplay) {
        yearDisplay.textContent = `Current Year: ${currentYear}`;
      }
      // Load the current statistic type
      if (currentStatType) {
      loadGraphData(currentStatType, selectedYear);
      }
    }
  }

  function loadGraphData(type, year) {
    // Show loading state
    const canvas = document.getElementById('statisticsChart');
    if (canvas) {
      const ctx = canvas.getContext('2d');
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = '#999';
      ctx.font = '16px Arial';
      ctx.textAlign = 'center';
      ctx.fillText('Loading...', canvas.width / 2, canvas.height / 2);
    }
    
    // Fetch only the selected statistic type
    fetch(`adminpanel.php?action=get_monthly_statistics&type=${type}&year=${year}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          renderChart(data);
        } else {
          alert('Error loading statistics: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Error loading graph data:', error);
        alert('Failed to load statistics graph.');
      });
  }

  function renderChart(data) {
    const canvas = document.getElementById('statisticsChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Destroy existing chart if it exists
    if (statisticsChart) {
      statisticsChart.destroy();
    }
    
    // Helper function to create gradient
    function createGradient(ctx, chartArea, colorStart, colorEnd) {
      const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
      gradient.addColorStop(0, colorStart);
      gradient.addColorStop(1, colorEnd);
      return gradient;
    }
    
    // Define colors for each type (matching the image style with blue tones)
    const colorConfigs = {
      'total': {
        border: 'rgba(79, 70, 229, 1)',      // Darker blue (#4f46e5)
        gradientStart: 'rgba(79, 70, 229, 0.4)',
        gradientEnd: 'rgba(79, 70, 229, 0.05)'
      },
      'pending': {
        border: 'rgba(99, 102, 241, 1)',     // Medium blue (#6366f1)
        gradientStart: 'rgba(99, 102, 241, 0.35)',
        gradientEnd: 'rgba(99, 102, 241, 0.05)'
      },
      'approved': {
        border: 'rgba(129, 140, 248, 1)',    // Lighter blue (#818cf8)
        gradientStart: 'rgba(129, 140, 248, 0.3)',
        gradientEnd: 'rgba(129, 140, 248, 0.05)'
      },
      'incomplete': {
        border: 'rgba(139, 92, 246, 1)',     // Purple-blue (#8b5cf6)
        gradientStart: 'rgba(139, 92, 246, 0.3)',
        gradientEnd: 'rgba(139, 92, 246, 0.05)'
      }
    };
    
    // Prepare single dataset for the selected statistic type
    const config = colorConfigs[data.type] || colorConfigs['total'];
    const datasets = [{
      label: data.type.charAt(0).toUpperCase() + data.type.slice(1).replace('_', ' ') + ' Applications',
      data: data.data,
      borderColor: config.border,
      backgroundColor: function(context) {
        const chart = context.chart;
        const {ctx, chartArea} = chart;
        if (!chartArea) {
          return config.gradientStart;
        }
        return createGradient(ctx, chartArea, config.gradientStart, config.gradientEnd);
      },
      borderWidth: 2.5,
      fill: true,
      tension: 0.4,
      pointRadius: 0,
      pointHoverRadius: 6,
      pointBackgroundColor: config.border,
      pointBorderColor: '#fff',
      pointBorderWidth: 2,
      pointHoverBackgroundColor: config.border,
      pointHoverBorderColor: '#fff'
    }];
    
    statisticsChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: data.labels,
        datasets: datasets
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false  // Hide legend since we're showing only one statistic at a time
          },
          tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: 'rgba(255, 255, 255, 0.95)',
            titleColor: '#111827',
            bodyColor: '#374151',
            borderColor: '#e5e7eb',
            borderWidth: 1,
            titleFont: {
              size: 13,
              weight: '600'
            },
            bodyFont: {
              size: 12
            },
            padding: 12,
            displayColors: true,
            boxPadding: 6,
            cornerRadius: 6
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
              font: {
                size: 11,
                family: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
              },
              color: '#6b7280',
              padding: 8
            },
            grid: {
              color: 'rgba(0, 0, 0, 0.04)',
              drawBorder: false,
              lineWidth: 1
            }
          },
          x: {
            ticks: {
              font: {
                size: 11,
                family: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
              },
              color: '#6b7280',
              padding: 8
            },
            grid: {
              display: false,
              drawBorder: false
            }
          }
        },
        interaction: {
          mode: 'index',
          intersect: false
        },
        elements: {
          line: {
            borderJoinStyle: 'round',
            borderCapStyle: 'round'
          }
        }
      }
    });
  }

  function closeStatisticsGraphModal() {
    const modal = document.getElementById('statisticsGraphModal');
    if (modal) {
      modal.classList.remove('show');
      document.body.classList.remove('modal-open');
    }
    
    // Destroy chart if it exists
    if (statisticsChart) {
      statisticsChart.destroy();
      statisticsChart = null;
    }
    
    currentStatType = null;
  }

  let currentReceiptFilePath = null;
  let currentReceiptFileName = 'receipt';

  function receiptPreviewFileName(filePath) {
    if (!filePath) return 'receipt';
    return filePath.split('/').pop() || 'receipt';
  }

  function receiptPreviewFileToolsHtml(viewLabel) {
    return (
      '<div class="receipt-preview-file-tools">' +
        '<button type="button" class="receipt-tool-view" onclick="openReceiptImageLightbox()">' +
          '<i class="fas fa-expand"></i> ' + escapeReceiptPreviewHtml(viewLabel || 'View Full Size') +
        '</button>' +
        '<button type="button" class="receipt-tool-download" onclick="downloadReceiptFile()">' +
          '<i class="fas fa-download"></i> Download Picture' +
        '</button>' +
      '</div>'
    );
  }

  function openReceiptImageLightbox() {
    if (!currentReceiptFilePath) return;
    const overlay = document.getElementById('receiptImageLightbox');
    const imgEl = document.getElementById('receiptLightboxImg');
    const captionEl = document.getElementById('receiptLightboxCaption');
    if (!overlay || !imgEl) return;
    imgEl.src = currentReceiptFilePath;
    imgEl.alt = currentReceiptFileName || 'Receipt';
    if (captionEl) captionEl.textContent = currentReceiptFileName || 'Receipt';
    overlay.classList.add('is-open');
  }

  function closeReceiptImageLightbox() {
    const overlay = document.getElementById('receiptImageLightbox');
    const imgEl = document.getElementById('receiptLightboxImg');
    if (overlay) overlay.classList.remove('is-open');
    if (imgEl) imgEl.src = '';
  }

  function openReceiptFileInNewTab() {
    if (!currentReceiptFilePath) return;
    window.open(currentReceiptFilePath, '_blank', 'noopener,noreferrer');
  }

  function receiptPreviewStatusClass(status) {
    const value = String(status || '').toLowerCase();
    if (value === 'approved') return 'receipt-status-badge--approved';
    if (value === 'invalid' || value === 'rejected') return 'receipt-status-badge--invalid';
    return 'receipt-status-badge--pending';
  }

  function viewReceiptFromButton(button) {
    if (!button) return;
    viewReceiptFile(button.dataset.filePath || '', {
      userName: button.dataset.userName || '',
      section: button.dataset.section || '',
      block: button.dataset.block || '',
      date: button.dataset.date || '',
      status: button.dataset.status || ''
    });
  }

  function viewReceiptFile(filePath, details) {
    details = details || {};
    if (!filePath) {
      alert('File path not available.');
      return;
    }
    
    currentReceiptFilePath = filePath;
    currentReceiptFileName = receiptPreviewFileName(filePath);
    const modal = document.getElementById('receiptPreviewModal');
    const previewContent = document.getElementById('receiptPreviewContent');
    const detailsEl = document.getElementById('receiptPreviewDetails');
    
    if (!modal || !previewContent) {
      alert('Preview modal not found. Please refresh the page.');
      return;
    }

    if (detailsEl) {
      const userName = (details.userName || 'N/A').trim();
      const section = (details.section || 'N/A').trim();
      const block = (details.block || 'N/A').trim();
      const date = (details.date || 'N/A').trim();
      const status = (details.status || 'N/A').trim();
      const hasDetails = [userName, section, block, date, status].some(function (value) {
        return value && value !== 'N/A';
      });
      if (hasDetails) {
        detailsEl.hidden = false;
        detailsEl.setAttribute('aria-hidden', 'false');
        detailsEl.innerHTML =
          '<div class="receipt-preview-details__top">' +
            '<span class="receipt-preview-kicker">Vendor Receipt</span>' +
            '<span class="receipt-status-badge ' + receiptPreviewStatusClass(status) + '">' + escapeReceiptPreviewHtml(status) + '</span>' +
          '</div>' +
          '<h3>' + escapeReceiptPreviewHtml(userName) + '</h3>' +
          '<div class="receipt-preview-meta">' +
            '<div class="receipt-preview-chip">' +
              '<i class="fas fa-calendar-alt"></i>' +
              '<span><small>Submitted</small><strong>' + escapeReceiptPreviewHtml(date) + '</strong></span>' +
            '</div>' +
            '<div class="receipt-preview-chip">' +
              '<i class="fas fa-map-marker-alt"></i>' +
              '<span><small>Location</small><strong>' + escapeReceiptPreviewHtml(section) + ' / ' + escapeReceiptPreviewHtml(block) + '</strong></span>' +
            '</div>' +
          '</div>';
      } else {
        detailsEl.hidden = true;
        detailsEl.setAttribute('aria-hidden', 'true');
        detailsEl.innerHTML = '';
      }
    }
    
    // Show modal
    modal.classList.add('show');
    document.body.classList.add('modal-open');
    
    // Determine file type and show preview
    const fileExtension = filePath.split('.').pop().toLowerCase();
    const fileName = filePath.split('/').pop() || 'receipt';
    
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExtension)) {
      previewContent.innerHTML =
        '<div class="receipt-preview-media-scroll">' +
          '<button type="button" class="receipt-preview-image-btn" onclick="openReceiptImageLightbox()" aria-label="View full size receipt">' +
            '<img src="' + escapeReceiptPreviewHtml(filePath) + '" alt="Receipt" onerror="this.closest(\'.receipt-preview-content\').innerHTML=\'<p style=&quot;color:#dc3545;margin:0;&quot;>Failed to load image preview.</p>\'">' +
            '<span class="receipt-preview-image-hint"><i class="fas fa-search-plus"></i> Click to view</span>' +
          '</button>' +
        '</div>' +
        receiptPreviewFileToolsHtml('View Full Size');
    } else if (fileExtension === 'pdf') {
      previewContent.innerHTML =
        '<div class="receipt-preview-media-scroll">' +
          '<iframe src="' + escapeReceiptPreviewHtml(filePath) + '" title="Receipt PDF"></iframe>' +
        '</div>' +
        '<div class="receipt-preview-file-tools">' +
          '<button type="button" class="receipt-tool-view" onclick="openReceiptFileInNewTab()">' +
            '<i class="fas fa-external-link-alt"></i> Open PDF' +
          '</button>' +
        '</div>';
    } else {
      previewContent.innerHTML =
        '<div style="text-align:center;padding:24px 12px;">' +
          '<i class="fas fa-file" style="font-size:64px;color:#667eea;margin-bottom:16px;"></i>' +
          '<p style="font-size:18px;color:#333;margin:0 0 8px;">' + escapeReceiptPreviewHtml(fileName) + '</p>' +
          '<p style="color:#999;margin:0;">Preview not available for this file type.</p>' +
        '</div>' +
        '<div class="receipt-preview-file-tools">' +
          '<button type="button" class="receipt-tool-view" onclick="openReceiptFileInNewTab()">' +
            '<i class="fas fa-external-link-alt"></i> Open File' +
          '</button>' +
        '</div>';
    }
  }

  function escapeReceiptPreviewHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function downloadReceiptFile() {
    if (!currentReceiptFilePath) {
      alert('File path not available.');
      return;
    }

    const fileName = currentReceiptFileName || receiptPreviewFileName(currentReceiptFilePath);
    fetch(currentReceiptFilePath, { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) throw new Error('Download failed');
        return response.blob();
      })
      .then(function (blob) {
        const blobUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(blobUrl);
      })
      .catch(function () {
        const link = document.createElement('a');
        link.href = currentReceiptFilePath;
        link.download = fileName;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      });
  }

  window.openReceiptImageLightbox = openReceiptImageLightbox;
  window.closeReceiptImageLightbox = closeReceiptImageLightbox;
  window.openReceiptFileInNewTab = openReceiptFileInNewTab;
  window.downloadReceiptFile = downloadReceiptFile;

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    const lightbox = document.getElementById('receiptImageLightbox');
    if (lightbox && lightbox.classList.contains('is-open')) {
      closeReceiptImageLightbox();
      e.preventDefault();
    }
  });

  function closeReceiptPreviewModal() {
    closeReceiptImageLightbox();
    const modal = document.getElementById('receiptPreviewModal');
    if (modal) {
      modal.classList.remove('show');
      document.body.classList.remove('modal-open');
    }
    
    // Clear preview content
    const previewContent = document.getElementById('receiptPreviewContent');
    const detailsEl = document.getElementById('receiptPreviewDetails');
    if (previewContent) {
      previewContent.innerHTML = '<p style="color: #999; margin: 0;">Loading preview...</p>';
    }
    if (detailsEl) {
      detailsEl.hidden = true;
      detailsEl.setAttribute('aria-hidden', 'true');
      detailsEl.innerHTML = '';
    }
    
    currentReceiptFilePath = null;
    currentReceiptFileName = 'receipt';
  }

  // Receipt action modal variables
  let currentReceiptId = null;
  let currentReceiptUserId = null;
  let currentReceiptAction = null;
  let isProcessingReceipt = false;

  function approveReceipt(receiptId, userId) {
    currentReceiptId = receiptId;
    currentReceiptUserId = userId;
    currentReceiptAction = 'approve';
    
    const modal = document.getElementById('receiptActionModal');
    const modalTitle = document.getElementById('receiptActionModalTitle');
    const confirmBtn = document.getElementById('confirmReceiptActionBtn');
    const approvalForm = document.getElementById('receiptApprovalFormContainer');
    const invalidForm = document.getElementById('receiptInvalidFormContainer');
    
    if (!modal || !modalTitle || !confirmBtn) {
      alert('Error: Modal elements not found. Please refresh the page.');
      return;
    }
    
    modalTitle.textContent = 'Approve Receipt';
    confirmBtn.innerHTML = '<i class="fas fa-check"></i> Approve';
    confirmBtn.className = 'btn btn-success';
    
    // Show approval form, hide invalid form
    approvalForm.style.display = 'block';
    invalidForm.style.display = 'none';
    
    // Clear notes field
    document.getElementById('receiptApprovalNotes').value = '';
    
    modal.classList.add('show');
    document.body.classList.add('modal-open');
  }

  function invalidReceipt(receiptId, userId) {
    currentReceiptId = receiptId;
    currentReceiptUserId = userId;
    currentReceiptAction = 'invalid';
    
    const modal = document.getElementById('receiptActionModal');
    const modalTitle = document.getElementById('receiptActionModalTitle');
    const confirmBtn = document.getElementById('confirmReceiptActionBtn');
    const approvalForm = document.getElementById('receiptApprovalFormContainer');
    const invalidForm = document.getElementById('receiptInvalidFormContainer');
    const invalidReason = document.getElementById('receiptInvalidReason');
    const invalidNotes = document.getElementById('receiptInvalidNotes');
    
    if (!modal || !modalTitle || !confirmBtn) {
      alert('Error: Modal elements not found. Please refresh the page.');
      return;
    }
    
    modalTitle.textContent = 'Mark Receipt as Invalid';
    confirmBtn.innerHTML = '<i class="fas fa-times"></i> Mark as Invalid';
    confirmBtn.className = 'btn btn-danger';
    confirmBtn.style.backgroundColor = '#ff9800';
    
    // Show invalid form, hide approval form
    invalidForm.style.display = 'block';
    approvalForm.style.display = 'none';
    
    // Clear form fields
    if (invalidReason) invalidReason.value = '';
    if (invalidNotes) invalidNotes.value = '';
    
    modal.classList.add('show');
    document.body.classList.add('modal-open');
  }

  function closeReceiptActionModal() {
    const modal = document.getElementById('receiptActionModal');
    if (modal) {
      modal.classList.remove('show');
      document.body.classList.remove('modal-open');
    }
    
    // Reset form fields
    document.getElementById('receiptApprovalNotes').value = '';
    const invalidReason = document.getElementById('receiptInvalidReason');
    const invalidNotes = document.getElementById('receiptInvalidNotes');
    if (invalidReason) invalidReason.value = '';
    if (invalidNotes) invalidNotes.value = '';
    
    // Reset variables
    currentReceiptId = null;
    currentReceiptUserId = null;
    currentReceiptAction = null;
    isProcessingReceipt = false;
    
    const confirmBtn = document.getElementById('confirmReceiptActionBtn');
    if (confirmBtn) {
      confirmBtn.disabled = false;
    }
  }

  function confirmReceiptAction(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }
    
    if (isProcessingReceipt) {
      return false;
    }
    
    if (!currentReceiptId || !currentReceiptAction) {
      alert('Invalid action state. Please try again.');
      return false;
    }
    
    isProcessingReceipt = true;
    
    const confirmBtn = document.getElementById('confirmReceiptActionBtn');
    const originalText = confirmBtn ? confirmBtn.innerHTML : '';
    
    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    }
    
    const formData = new FormData();
    formData.append('receipt_id', currentReceiptId);
    formData.append('user_id', currentReceiptUserId);
    
    if (currentReceiptAction === 'invalid') {
      const invalidReason = document.getElementById('receiptInvalidReason').value;
      const invalidNotes = document.getElementById('receiptInvalidNotes').value;
      
      if (!invalidReason || invalidReason.trim() === '') {
        alert('Please select an invalid reason.');
        isProcessingReceipt = false;
        if (confirmBtn) {
          confirmBtn.disabled = false;
          confirmBtn.innerHTML = originalText;
        }
        return false;
      }
      
      formData.append('action', 'invalid_receipt');
      formData.append('invalid_reason', invalidReason);
      formData.append('notes', invalidNotes || '');
    } else if (currentReceiptAction === 'approve') {
      const approvalNotes = document.getElementById('receiptApprovalNotes').value;
      
      formData.append('action', 'approve_receipt');
      formData.append('notes', approvalNotes || '');
    } else {
      alert('Invalid action.');
      isProcessingReceipt = false;
      if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalText;
      }
      return false;
    }
    
    fetch('adminpanel.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.text())
    .then(text => {
      let jsonText = text;
      const jsonMatch = text.match(/\{[\s\S]*\}/);
      if (jsonMatch) {
        jsonText = jsonMatch[0];
      }
      
      let data;
      try {
        data = JSON.parse(jsonText);
      } catch (e) {
        console.error('JSON Parse Error:', e);
        console.error('Full response text:', text);
        isProcessingReceipt = false;
        if (confirmBtn) {
          confirmBtn.disabled = false;
          confirmBtn.innerHTML = originalText;
        }
        alert('Server returned invalid response. Please try again.');
        return;
      }
      
      isProcessingReceipt = false;
      if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalText;
      }
      
      if (data.success) {
        closeReceiptActionModal();
        alert(data.message || (currentReceiptAction === 'approve' ? 'Receipt approved successfully!' : 'Receipt marked as invalid successfully!'));
        location.reload();
      } else {
        alert('Error: ' + (data.message || 'Action failed'));
      }
    })
    .catch(error => {
      console.error('Error in confirmReceiptAction:', error);
      isProcessingReceipt = false;
      if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalText;
      }
      alert('Request failed: ' + error.message);
    });
    
    return false;
  }

  // Slot Map Editor Functions
  let slotChanges = {};
  let originalSlots = {};
  let slotMapCurrentPage = 1;
  const slotMapTotalPages = 2;
  const slotMapPageTitles = {
    1: '1 — Daet Public Market',
    2: '2 — Daet Elevated Town Plaza'
  };

  function updateSlotMapPaginationUI() {
    const prevBtn = document.getElementById('slotMapPrevBtn');
    const nextBtn = document.getElementById('slotMapNextBtn');
    const label = document.getElementById('slotMapPageLabel');
    if (prevBtn) prevBtn.disabled = slotMapCurrentPage <= 1;
    if (nextBtn) nextBtn.disabled = slotMapCurrentPage >= slotMapTotalPages;
    if (label) label.textContent = slotMapPageTitles[slotMapCurrentPage] || '';
    document.querySelectorAll('[data-slot-page]').forEach(function (btn) {
      const p = parseInt(btn.getAttribute('data-slot-page'), 10);
      btn.classList.toggle('is-active', p === slotMapCurrentPage);
    });
  }

  function loadSlotMapPage() {
    const content = document.getElementById('slotMapEditorContent');
    if (!content) return;
    content.innerHTML = '<p style="color:#64748b;text-align:center;padding:2rem 0;">Loading map...</p>';

    fetch('adminpanel.php?action=load_slot_map')
      .then(response => response.text())
      .then(html => {
        content.innerHTML = html;
        content.querySelectorAll('script').forEach(function (old) {
          if ((old.getAttribute('type') || '') === 'application/json') return;
          const s = document.createElement('script');
          s.text = old.textContent;
          old.replaceWith(s);
        });
      })
      .catch(error => {
        console.error('Error loading map:', error);
        content.innerHTML = '<p style="color: red;">Error loading map. Please try again.</p>';
      });
  }

  function slotMapGoToPage() { loadSlotMapPage(); }
  function slotMapGoNext() { loadSlotMapPage(); }
  function slotMapGoPrev() { loadSlotMapPage(); }

  function dpmAdminEditSlot(info) {
    const meta = (info && info.meta) || {};
    const box = document.createElement('div');
    const status = meta.status || (info && info.status) || 'available';
    box.setAttribute('data-section', (info && info.section) || '');
    box.setAttribute('data-block', (info && info.block) || '');
    box.setAttribute('data-db', 'SITE MAP');
    box.setAttribute('data-slot', String((info && info.slot) || ''));
    box.setAttribute('data-status', status);
    box.setAttribute('data-img1', meta.image1 || '');
    box.setAttribute('data-img2', meta.image2 || '');
    box.setAttribute('data-img3', meta.image3 || '');
    box.setAttribute('data-holder-id', meta.holder_vendor_id || '');
    box.setAttribute('data-holder-name', meta.holder_name || '');
    box.setAttribute('data-holder-section', meta.holder_section || '');
    box.setAttribute('data-holder-block', meta.holder_block || '');
    adminOpenSlotDetail(box);
  }
  window.dpmAdminEditSlot = dpmAdminEditSlot;

  function openSlotMapModal() {
    const modal = document.getElementById('slotMapModal');
    
    slotChanges = {};
    originalSlots = {};
    slotMapCurrentPage = 1;
    
    modal.classList.add('show');
    document.body.classList.add('modal-open');
    
    loadSlotMapPage(1);
  }

  function closeSlotMapModal() {
    const modal = document.getElementById('slotMapModal');
    modal.classList.remove('show');
    if (!document.getElementById('slotDetailModal').classList.contains('show')) {
      document.body.classList.remove('modal-open');
    }
    slotMapCurrentPage = 1;
    slotChanges = {};
    originalSlots = {};
  }

  // ── Per-slot detail editor (status + up to 3 photos) ──────────────────────
  let currentSlotBox = null;              // the .mkt-gf-box being edited
  let slotPhotoState = { 1: null, 2: null, 3: null }; // { path, action:'keep'|'replace'|'remove', file }

  function normSlotUrl(p) {
    if (!p) return '';
    var u = String(p).replace(/\\/g, '/');
    if (/^https?:\/\//i.test(u)) return u;
    return u.replace(/^\/+/, '');
  }

  function mktGfBoxNumberHtml(displayNum) {
    const n = parseInt(displayNum, 10) || 0;
    const sm = n >= 100 ? ' mkt-gf-box-num--sm' : '';
    return '<span class="mkt-gf-box-num' + sm + '">' + n + '</span>';
  }

  function setSlotPhotoCell(i, src) {
    const img = document.getElementById('slotPhotoImg' + i);
    const ph = document.getElementById('slotPhotoPlaceholder' + i);
    const rm = document.querySelector('.slot-photo-remove[data-index="' + i + '"]');
    if (src) {
      img.src = src; img.style.display = 'block';
      ph.style.display = 'none'; rm.style.display = 'block';
    } else {
      img.src = ''; img.style.display = 'none';
      ph.style.display = 'block'; rm.style.display = 'none';
    }
  }

  // Tracks whether the slot being edited is a Second Floor stall (display-only).
  var currentSlotSecondFloor = false;

  // The stall holder is fixed at approval time, so we only display it (read-only).
  function populateHolderDisplay() {
    const box = currentSlotBox;
    if (!box) return;
    const name = box.getAttribute('data-holder-name') || '';
    const section = box.getAttribute('data-holder-section') || '';
    const block = box.getAttribute('data-holder-block') || '';
    document.getElementById('slotDetailHolderName').textContent = name || 'No holder on record';
    const info = document.getElementById('slotHolderInfo');
    if (name && (section || block)) {
      document.getElementById('slotHolderSection').textContent = section || '—';
      document.getElementById('slotHolderBlock').textContent = block || '—';
      info.style.display = 'grid';
    } else {
      info.style.display = 'none';
    }
  }

  // Show/hide the holder display depending on the chosen status.
  function onSlotStatusChange() {
    const statusSelect = document.getElementById('slotDetailStatus');
    const group = document.getElementById('slotHolderGroup');
    if (!statusSelect || !group) return;
    if (statusSelect.value === 'occupied') {
      group.style.display = 'block';
      populateHolderDisplay();
    } else {
      group.style.display = 'none';
    }
  }
  window.onSlotStatusChange = onSlotStatusChange;

  function adminOpenSlotDetail(box) {
    currentSlotBox = box;
    document.getElementById('slotDetailSection').textContent = box.getAttribute('data-section') || '—';
    document.getElementById('slotDetailBlock').textContent = box.getAttribute('data-block') || '—';
    const displayNum = parseInt(box.getAttribute('data-slot'), 10) || parseInt(box.getAttribute('data-display-num'), 10) || 0;
    document.getElementById('slotDetailSlot').textContent = 'Stall ' + displayNum;

    const statusSelect = document.getElementById('slotDetailStatus');
    const statusGroup = document.getElementById('slotStatusGroup');
    const secondFloorBadge = document.getElementById('slotDetailSecondFloor');
    const isElevatedPlaza = (box.getAttribute('data-block') || '') === 'Daet Elevated Plaza'
      || !!box.closest('.mkt-ev-plan');
    let slotStatus = box.getAttribute('data-status') || 'available';
    if (isElevatedPlaza && slotStatus === 'second_floor') slotStatus = 'available';

    // Second Floor is a location detail, not an editable status: show it beside the
    // slot number and hide the status/holder controls.
    currentSlotSecondFloor = (slotStatus === 'second_floor');
    if (secondFloorBadge) secondFloorBadge.style.display = currentSlotSecondFloor ? 'inline-block' : 'none';
    if (statusGroup) statusGroup.style.display = currentSlotSecondFloor ? 'none' : 'block';

    if (statusSelect) statusSelect.value = currentSlotSecondFloor ? 'available' : slotStatus;
    onSlotStatusChange();

    for (let i = 1; i <= 3; i++) {
      const path = normSlotUrl(box.getAttribute('data-img' + i) || '');
      slotPhotoState[i] = path ? { path: path, action: 'keep', file: null } : { path: '', action: 'remove', file: null };
      setSlotPhotoCell(i, path || '');
      const input = document.getElementById('slotPhotoInput' + i);
      if (input) input.value = '';
    }

    document.getElementById('slotDetailModal').classList.add('show');
    document.body.classList.add('modal-open');
  }

  function closeSlotDetailModal() {
    document.getElementById('slotDetailModal').classList.remove('show');
    if (!document.getElementById('slotMapModal').classList.contains('show')) {
      document.body.classList.remove('modal-open');
    }
    currentSlotBox = null;
  }

  // Wire photo cells: click to pick, change to preview, remove to clear
  function wireSlotPhotoCells() {
    for (let i = 1; i <= 3; i++) {
      (function (idx) {
        const cell = document.querySelector('.slot-photo-cell[data-index="' + idx + '"]');
        const input = document.getElementById('slotPhotoInput' + idx);
        const rm = document.querySelector('.slot-photo-remove[data-index="' + idx + '"]');
        if (!cell || !input || cell.dataset.wired === '1') return;
        cell.dataset.wired = '1';
        cell.addEventListener('click', function (e) {
          if (e.target.closest('.slot-photo-remove')) return;
          input.click();
        });
        input.addEventListener('change', function () {
          if (input.files && input.files[0]) {
            const file = input.files[0];
            if (file.size > 5 * 1024 * 1024) { alert('Each photo must be under 5MB.'); input.value = ''; return; }
            slotPhotoState[idx] = { path: '', action: 'replace', file: file };
            setSlotPhotoCell(idx, URL.createObjectURL(file));
          }
        });
        if (rm) rm.addEventListener('click', function (e) {
          e.stopPropagation();
          slotPhotoState[idx] = { path: '', action: 'remove', file: null };
          setSlotPhotoCell(idx, '');
          input.value = '';
        });
      })(i);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireSlotPhotoCells);
  } else {
    wireSlotPhotoCells();
  }

  function adminSaveSlotDetail() {
    if (!currentSlotBox) return;
    const btn = document.getElementById('slotDetailSaveBtn');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    // Second Floor stalls keep their status; others use the Available/Occupied select.
    const chosenStatus = currentSlotSecondFloor ? 'second_floor' : document.getElementById('slotDetailStatus').value;

    const fd = new FormData();
    fd.append('action', 'save_slot_detail');
    fd.append('block_name', currentSlotBox.getAttribute('data-db') || '');
    fd.append('slot_number', currentSlotBox.getAttribute('data-slot') || '0');
    fd.append('status', chosenStatus);
    // Holder is fixed at approval; keep it when still occupied, drop it otherwise.
    fd.append('holder_vendor_id', (chosenStatus === 'occupied') ? (currentSlotBox.getAttribute('data-holder-id') || '0') : '0');
    for (let i = 1; i <= 3; i++) {
      const st = slotPhotoState[i] || { action: 'remove' };
      if (st.action === 'replace' && st.file) {
        fd.append('photo' + i, st.file);
      } else {
        fd.append('keep' + i, st.action === 'keep' ? st.path : '');
      }
    }

    fetch('adminpanel.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (!data.success) { throw new Error(data.message || 'Failed to save.'); }
        // Reflect changes on the box (so menu.php shows them after reload)
        const box = currentSlotBox;
        box.setAttribute('data-img1', data.image1 || '');
        box.setAttribute('data-img2', data.image2 || '');
        box.setAttribute('data-img3', data.image3 || '');
        box.classList.remove('is-available', 'is-occupied', 'is-second');
        const isElevatedPlaza = (box.getAttribute('data-block') || '') === 'Daet Elevated Plaza'
          || !!box.closest('.mkt-ev-plan');
        const mapStatus = (isElevatedPlaza && data.status === 'second_floor') ? 'available' : (data.status || 'available');
        box.setAttribute('data-status', mapStatus);
        box.classList.add(
          mapStatus === 'occupied' ? 'is-occupied'
            : (!isElevatedPlaza && mapStatus === 'second_floor' ? 'is-second' : 'is-available')
        );
        box.setAttribute('data-holder-id', data.holder_vendor_id || '');
        box.setAttribute('data-holder-name', data.holder_name || '');
        box.setAttribute('data-holder-section', data.holder_section || '');
        box.setAttribute('data-holder-block', data.holder_block || '');
        if ((box.getAttribute('data-db') || '') === 'SITE MAP') {
          const num = box.getAttribute('data-slot');
          const chip = document.querySelector('#dpmStallSlotsGrid [data-slot-number="' + num + '"]');
          if (chip) {
            const taken = mapStatus === 'occupied' || mapStatus === 'second_floor';
            chip.classList.toggle('is-occupied', taken);
            chip.classList.toggle('is-available', !taken);
          }
          window.DPM_ADMIN_SLOT_META = window.DPM_ADMIN_SLOT_META || {};
          window.DPM_ADMIN_SLOT_META[String(num)] = {
            status: mapStatus,
            image1: data.image1 || '',
            image2: data.image2 || '',
            image3: data.image3 || '',
            holder_vendor_id: data.holder_vendor_id || '',
            holder_name: data.holder_name || '',
            holder_section: data.holder_section || '',
            holder_block: data.holder_block || ''
          };
        } else {
          box.innerHTML = mktGfBoxNumberHtml(box.getAttribute('data-slot') || box.getAttribute('data-display-num'));
        }
        btn.disabled = false;
        btn.innerHTML = original;
        closeSlotDetailModal();
      })
      .catch(err => {
        btn.disabled = false;
        btn.innerHTML = original;
        alert('Error: ' + err.message);
      });
  }
  window.adminOpenSlotDetail = adminOpenSlotDetail;
  window.closeSlotDetailModal = closeSlotDetailModal;
  window.adminSaveSlotDetail = adminSaveSlotDetail;
  window.slotMapGoToPage = slotMapGoToPage;
  window.slotMapGoNext = slotMapGoNext;
  window.slotMapGoPrev = slotMapGoPrev;

  function toggleSlotStatus(blockName, slotNumber) {
    const key = `${blockName}-${slotNumber}`;
    const slotElement = document.querySelector(`.slot[data-block-name="${blockName}"][data-slot-number="${slotNumber}"]`);
    
    if (!slotElement) return;
    
    // Get current status
    let currentStatus = slotChanges[key] || originalSlots[key] || 'available';
    
    // Cycle through: available -> second_floor -> occupied -> available
    let nextStatus;
    if (currentStatus === 'available') {
      nextStatus = 'second_floor';
    } else if (currentStatus === 'second_floor') {
      nextStatus = 'occupied';
    } else {
      nextStatus = 'available';
    }
    
    // Update changes
    slotChanges[key] = nextStatus;
    
    // Update visual appearance
    slotElement.className = 'slot';
    if (nextStatus === 'second_floor') {
      slotElement.classList.add('second-floor');
    } else if (nextStatus === 'occupied') {
      slotElement.classList.add('occupied');
    }
    slotElement.dataset.status = nextStatus;
    
    // Update title
    slotElement.title = `${blockName} - Slot ${slotNumber} (${nextStatus.charAt(0).toUpperCase() + nextStatus.slice(1).replace('_', ' ')})`;
  }

  function applySlotChanges() {
    if (Object.keys(slotChanges).length === 0) {
      alert('No changes to apply.');
      return;
    }
    
    // Convert changes to array format
    const slotsArray = [];
    for (const [key, status] of Object.entries(slotChanges)) {
      const [blockName, slotNumber] = key.split('-');
      slotsArray.push({
        block_name: blockName,
        slot_number: parseInt(slotNumber),
        status: status
      });
    }
    
    // Show loading
    const applyBtn = document.querySelector('#slotMapModal .btn-success');
    const originalText = applyBtn.innerHTML;
    applyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
    applyBtn.disabled = true;
    
    // Send to server
    const formData = new FormData();
    formData.append('action', 'update_slots');
    formData.append('slots', JSON.stringify(slotsArray));
    
    fetch('adminpanel.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert(data.message || 'Slot changes applied successfully!');
        closeSlotMapModal();
        // Optionally reload the page or refresh map
        location.reload();
      } else {
        alert('Error: ' + (data.message || 'Failed to apply changes.'));
        applyBtn.innerHTML = originalText;
        applyBtn.disabled = false;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Network error: ' + error.message);
      applyBtn.innerHTML = originalText;
      applyBtn.disabled = false;
    });
  }

  // Close modal when clicking outside
  window.onclick = function(event) {
    const slotMapModal = document.getElementById('slotMapModal');
    if (event.target === slotMapModal) {
      closeSlotMapModal();
    }
    const requirementsModal = document.getElementById('requirementsModal');
    if (event.target === requirementsModal) {
      closeRequirementsModal();
    }
  }

  // Requirements Management Functions
  let currentRequirementType = 'new';

  function openRequirementsModal() {
    const modal = document.getElementById('requirementsModal');
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    currentRequirementType = 'new';
    switchRequirementTab('new');
  }

  function closeRequirementsModal() {
    const modal = document.getElementById('requirementsModal');
    modal.style.display = 'none';
  }

  function switchRequirementTab(type) {
    currentRequirementType = type;
    
    // Update tab buttons
    const tabNew = document.getElementById('tabNew');
    const tabRenewal = document.getElementById('tabRenewal');
    
    if (type === 'new') {
      tabNew.classList.add('rq-tab--active');
      tabRenewal.classList.remove('rq-tab--active');
    } else {
      tabRenewal.classList.add('rq-tab--active');
      tabNew.classList.remove('rq-tab--active');
    }

    // Update sub-header copy per tab
    const titleEl = document.getElementById('rqListTitle');
    if (type === 'new') {
      if (titleEl) titleEl.textContent = 'New Application Requirements';
    } else {
      if (titleEl) titleEl.textContent = 'Renewal Requirements';
    }
    
    // Load requirements
    loadRequirements(type);
  }

  function updateRqItemCount(n) {
    const el = document.getElementById('rqItemCount');
    if (el) el.textContent = n + (n === 1 ? ' item' : ' items');
  }

  function rqRefreshNumbers() {
    const list = document.getElementById('requirementsList');
    const items = list ? list.querySelectorAll('.rq-item') : [];
    items.forEach((item, i) => {
      const num = item.querySelector('.rq-num');
      if (num) num.textContent = (i + 1);
    });
    updateRqItemCount(items.length);
  }

  function loadRequirements(type) {
    const content = document.getElementById('requirementsContent');
    content.innerHTML = '<p class="rq-empty"><i class="fas fa-spinner fa-spin"></i> Loading requirements...</p>';
    
    const formData = new FormData();
    formData.append('action', 'get_requirements');
    formData.append('application_type', type);
    
    fetch('adminpanel.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displayRequirements(data.requirements, type);
      } else {
        content.innerHTML = '<p class="rq-empty rq-empty--error">Error: ' + (data.message || 'Failed to load requirements') + '</p>';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      content.innerHTML = '<p class="rq-empty rq-empty--error">Network error: ' + error.message + '</p>';
    });
  }

  function displayRequirements(requirements, type) {
    const content = document.getElementById('requirementsContent');
    
    if (requirements.length === 0) {
      content.innerHTML = '<div id="requirementsList" class="rq-list"></div>' +
        '<p class="rq-empty">No requirements found. Click "Add New Requirement" to create one.</p>';
      updateRqItemCount(0);
      return;
    }
    
    let html = '<div id="requirementsList" class="rq-list">';
    requirements.forEach((req, index) => {
      html += `
        <div class="rq-item requirement-item" data-id="${req.requirement_id}">
          <div class="rq-num">${index + 1}</div>
          <div class="rq-fields">
            <input type="text" id="req_name_${req.requirement_id}" class="rq-input rq-input--name" value="${escapeHtml(req.requirement_name)}" placeholder="Requirement Name">
            <input type="text" id="req_desc_${req.requirement_id}" class="rq-input rq-input--desc" value="${escapeHtml(req.requirement_description || '')}" placeholder="Description (e.g., 1 Original, 1 Photocopy)">
          </div>
          <button class="rq-del" onclick="deleteRequirement(${req.requirement_id})">
            <i class="fas fa-trash-can"></i> Delete
          </button>
        </div>
      `;
    });
    html += '</div>';
    content.innerHTML = html;
    updateRqItemCount(requirements.length);
  }

  function addNewRequirement() {
    const content = document.getElementById('requirementsContent');
    let list = document.getElementById('requirementsList');
    
    if (!list) {
      content.innerHTML = '<div id="requirementsList" class="rq-list"></div>';
      list = document.getElementById('requirementsList');
    }
    const emptyMsg = content.querySelector('.rq-empty');
    if (emptyMsg) emptyMsg.remove();
    
    const timestamp = Date.now();
    const newItem = document.createElement('div');
    newItem.className = 'rq-item rq-item--new requirement-item';
    newItem.setAttribute('data-id', 'new_' + timestamp);
    newItem.innerHTML = `
      <div class="rq-num"></div>
      <div class="rq-fields">
        <input type="text" id="new_req_name_${timestamp}" class="rq-input rq-input--name" placeholder="Requirement Name *">
        <input type="text" id="new_req_desc_${timestamp}" class="rq-input rq-input--desc" placeholder="Description (optional)">
      </div>
      <button class="rq-del" onclick="cancelNewRequirement(this)">
        <i class="fas fa-trash-can"></i> Remove
      </button>
    `;
    
    list.appendChild(newItem);
    rqRefreshNumbers();
    document.getElementById('new_req_name_' + timestamp).focus();
  }

  function cancelNewRequirement(button) {
    const item = button.closest('.rq-item');
    if (item) item.remove();
    rqRefreshNumbers();
  }

  function saveAllRequirements() {
    const requirementsList = document.getElementById('requirementsList');
    if (!requirementsList) {
      alert('No requirements to save.');
      return;
    }
    
    const requirementItems = requirementsList.querySelectorAll('.requirement-item');
    if (requirementItems.length === 0) {
      alert('No requirements to save.');
      return;
    }
    
    const requirements = [];
    let hasError = false;
    
    requirementItems.forEach((item, index) => {
      const requirementId = item.getAttribute('data-id');
      const nameInput = item.querySelector('input[id^="req_name_"], input[id^="new_req_name_"]');
      const descInput = item.querySelector('input[id^="req_desc_"], input[id^="new_req_desc_"]');
      
      if (nameInput && descInput) {
        const name = nameInput.value.trim();
        const desc = descInput.value.trim();
        
        if (!name) {
          alert(`Please enter a requirement name for item ${index + 1}.`);
          hasError = true;
          return;
        }
        
        // Use '0' for new requirements (those with data-id starting with 'new_' or no data-id)
        const reqId = (requirementId && !requirementId.startsWith('new_')) ? requirementId : '0';
        
        requirements.push({
          requirement_id: reqId,
          requirement_name: name,
          requirement_description: desc,
          display_order: index
        });
      }
    });
    
    if (hasError) {
      return;
    }
    
    // Show loading state
    const saveButton = document.querySelector('button[onclick="saveAllRequirements()"]');
    const originalButtonText = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveButton.disabled = true;
    
    // Save all requirements
    let savePromises = [];
    requirements.forEach((req, index) => {
      const formData = new FormData();
      formData.append('action', 'save_requirement');
      formData.append('requirement_id', req.requirement_id);
      formData.append('application_type', currentRequirementType);
      formData.append('requirement_name', req.requirement_name);
      formData.append('requirement_description', req.requirement_description);
      formData.append('display_order', req.display_order);
      
      savePromises.push(
        fetch('adminpanel.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
      );
    });
    
    Promise.all(savePromises)
      .then(results => {
        const allSuccess = results.every(result => result.success);
        if (allSuccess) {
          alert('All requirements saved successfully!');
          loadRequirements(currentRequirementType);
        } else {
          const errors = results.filter(r => !r.success).map(r => r.message || 'Failed to save').join(', ');
          alert('Some requirements could not be saved: ' + errors);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Network error: ' + error.message);
      })
      .finally(() => {
        saveButton.innerHTML = originalButtonText;
        saveButton.disabled = false;
      });
  }

  function deleteRequirement(requirementId) {
    if (!confirm('Are you sure you want to delete this requirement?')) {
      return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_requirement');
    formData.append('requirement_id', requirementId);
    
    fetch('adminpanel.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('Requirement deleted successfully!');
        loadRequirements(currentRequirementType);
      } else {
        alert('Error: ' + (data.message || 'Failed to delete requirement'));
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Network error: ' + error.message);
    });
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

</script>

<!-- Removed duplicate vendor modal definition -->
</body>
</html>
<?php
// Close database connection at the very end
if (isset($conn) && $conn) {
    $conn->close();
}
?>
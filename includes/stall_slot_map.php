<?php
/**
 * Shared stall-slot mapping used by application.php, renewal.php and adminpanel.php.
 *
 * The visual stall map (includes/market_stall_blueprint.php) stores occupancy in
 * `market_slots` keyed by (block_name, slot_number) where block_name is the internal
 * "db" identifier below and slot_number is the per-block stall number (1..count).
 *
 * A vendor is linked to a slot by storing the resolved db block on vendors.stall_block
 * and the per-block number on vendors.stall_number, so approval / display never has to
 * guess the mapping again.
 */

if (!function_exists('dpmStallCanonicalMap')) {
    /**
     * (Section || Block) => [db block_name, slot count]. These pairs mirror
     * the columns declared in includes/market_stall_blueprint.php exactly.
     */
    function dpmStallCanonicalMap(): array
    {
        return [
            'Fruits and Vegetables Section||Market Tienda Bldg' => ['db' => 'FRUITS VEG', 'count' => 34],
            'Meat and Chicken Section||Block A'                 => ['db' => 'BOT BLOCK A', 'count' => 110],
            'Fresh Fish Section||Block B'                       => ['db' => 'BOT BLOCK B', 'count' => 79],
            'Dried and Salted Fish Section||Block C'            => ['db' => 'BOT BLOCK C', 'count' => 33],
            'Groceries Sari-Sari Section||Market Tienda Bldg'   => ['db' => 'SPECIAL AREA SECTION TOP', 'count' => 11],
            'Service Section||Market Tienda Bldg'               => ['db' => 'SERVICE SECTION', 'count' => 22],
            'Eateries and Cooked Food Section||BOT Block E'     => ['db' => 'BOT BLOCK E', 'count' => 26],
            'Fresh Fish Section||BOT Behind Block B'            => ['db' => 'BOT BEHIND BLOCK B', 'count' => 16],
            'Dried and Salted Fish Section||BOT Behind Block C' => ['db' => 'BOT BEHIND BLOCK C', 'count' => 7],
            'Dry Goods Section||Market Tienda Bldg'             => ['db' => 'DRY GOODS SECTION', 'count' => 18],
            'Special Area Section||Market Tienda Bldg'          => ['db' => 'SPECIAL AREA SECTION', 'count' => 60],
        ];
    }
}

if (!function_exists('dpmResolveStall')) {
    /**
     * Resolve a section/block to its map block + slot count.
     * Falls back to the first block that matches the section so legacy vendor
     * records (with section/block combos that predate the map) still resolve.
     *
     * @return array{db:string,count:int}|null
     */
    function dpmResolveStall(string $section, string $block): ?array
    {
        $section = trim($section);
        $block = trim($block);

        if ($block === 'Daet Elevated Plaza') {
            return ['db' => 'Daet Elevated Plaza', 'count' => 80];
        }

        $map = dpmStallCanonicalMap();
        $key = $section . '||' . $block;
        if (isset($map[$key])) {
            return $map[$key];
        }

        // Legacy fallback: match on section alone.
        foreach ($map as $k => $v) {
            $parts = explode('||', $k);
            if (($parts[0] ?? '') === $section) {
                return $v;
            }
        }

        return null;
    }
}

if (!function_exists('dpmEnsureStallColumns')) {
    /** Make sure vendors + market_slots carry the columns this feature needs. */
    function dpmEnsureStallColumns(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        try {
            foreach (['stall_number' => 'INT NULL', 'stall_block' => 'VARCHAR(64) NULL'] as $col => $def) {
                $chk = $conn->query("SHOW COLUMNS FROM vendors LIKE '" . $col . "'");
                if ($chk && $chk->num_rows === 0) {
                    $conn->query("ALTER TABLE vendors ADD COLUMN " . $col . " " . $def);
                }
            }

            $conn->query("CREATE TABLE IF NOT EXISTS market_slots (
                id INT AUTO_INCREMENT PRIMARY KEY,
                block_name VARCHAR(50) NOT NULL,
                slot_number INT NOT NULL,
                status ENUM('available', 'occupied', 'second_floor') DEFAULT 'available',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_slot (block_name, slot_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            foreach ([
                'holder_vendor_id' => 'INT NULL',
                'holder_name'      => 'VARCHAR(255) NULL',
                'holder_section'   => 'VARCHAR(150) NULL',
                'holder_block'     => 'VARCHAR(150) NULL',
                'image1'           => 'VARCHAR(255) NULL',
                'image2'           => 'VARCHAR(255) NULL',
                'image3'           => 'VARCHAR(255) NULL',
            ] as $col => $def) {
                $chk = $conn->query("SHOW COLUMNS FROM market_slots LIKE '" . $col . "'");
                if ($chk && $chk->num_rows === 0) {
                    $conn->query("ALTER TABLE market_slots ADD COLUMN " . $col . " " . $def);
                }
            }
        } catch (Throwable $e) {
            error_log('dpmEnsureStallColumns: ' . $e->getMessage());
        }

        $done = true;
    }
}

if (!function_exists('dpmOccupiedSlotsByBlock')) {
    /**
     * @return array<string,int[]> db block_name => list of occupied slot numbers
     */
    function dpmOccupiedSlotsByBlock(mysqli $conn): array
    {
        $out = [];
        $res = @$conn->query("SELECT block_name, slot_number FROM market_slots WHERE status = 'occupied'");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[$row['block_name']][] = (int) $row['slot_number'];
            }
        }
        return $out;
    }
}

if (!function_exists('dpmOccupySlot')) {
    /** Mark a slot occupied and record the stall holder (idempotent upsert). */
    function dpmOccupySlot(mysqli $conn, string $db, int $slot, int $vendorId, string $vName, string $vSection, string $vBlock): bool
    {
        if ($db === '' || $slot <= 0) {
            return false;
        }
        dpmEnsureStallColumns($conn);
        $stmt = $conn->prepare("INSERT INTO market_slots (block_name, slot_number, status, holder_vendor_id, holder_name, holder_section, holder_block)
            VALUES (?, ?, 'occupied', ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = 'occupied', holder_vendor_id = VALUES(holder_vendor_id),
                holder_name = VALUES(holder_name), holder_section = VALUES(holder_section), holder_block = VALUES(holder_block)");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('siisss', $db, $slot, $vendorId, $vName, $vSection, $vBlock);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool) $ok;
    }
}

if (!function_exists('dpmAssignVendorSlotOnApproval')) {
    /**
     * Called when an admin approves a vendor. Resolves the vendor's stored
     * stall block/number (assigning a free one if missing) and marks it occupied.
     */
    function dpmAssignVendorSlotOnApproval(mysqli $conn, int $vendorId, string $approverName = ''): void
    {
        dpmEnsureStallColumns($conn);
        $stmt = $conn->prepare("SELECT vendor_name, section, block, stall_number, stall_block FROM vendors WHERE vendor_id = ? LIMIT 1");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $v = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$v) {
            return;
        }

        $section = (string) ($v['section'] ?? '');
        $block   = (string) ($v['block'] ?? '');
        $slot = (int) ($v['stall_number'] ?? 0);
        // Site-map numbers are continuous across the ground map. Keep the number
        // the vendor applied for and mark that pill occupied, even when it is
        // outside the older per-block slot list.
        if ($slot > 0) {
            $holderName = trim($approverName) !== '' ? trim($approverName) : (string) ($v['vendor_name'] ?? '');
            dpmOccupySlot($conn, 'SITE MAP', $slot, $vendorId, $holderName, $section, $block);
        }

        $resolved = dpmResolveStall($section, $block);
        if (!$resolved) {
            return;
        }
        $db = $resolved['db'];
        $count = (int) $resolved['count'];

        // If the vendor has no stall number yet (or it is out of range), give them a free one.
        if ($slot <= 0 || $slot > $count) {
            if ($slot > 0) {
                return;
            }
            $occupied = dpmOccupiedSlotsByBlock($conn)[$db] ?? [];
            $occupiedSet = array_flip($occupied);
            $free = [];
            for ($n = 1; $n <= $count; $n++) {
                if (!isset($occupiedSet[$n])) {
                    $free[] = $n;
                }
            }
            if (!$free) {
                return; // block full
            }
            $slot = $free[array_rand($free)];
        }

        // Persist the resolved block + number back onto the vendor.
        $upd = $conn->prepare("UPDATE vendors SET stall_number = ?, stall_block = ? WHERE vendor_id = ?");
        if ($upd) {
            $upd->bind_param('isi', $slot, $db, $vendorId);
            $upd->execute();
            $upd->close();
        }

        dpmOccupySlot($conn, $db, $slot, $vendorId, (string) ($v['vendor_name'] ?? ''), $section, $block);
    }
}

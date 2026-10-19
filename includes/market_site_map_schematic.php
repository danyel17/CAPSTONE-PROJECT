<?php
/**
 * Daet Public Market — site layout from hand-drawn sketch.
 * Street names are HTML (outside the market box). Interior stalls are SVG shapes only.
 *
 * Stall total for the whole ground map is exactly 1083.
 * User-annotated counts are fixed; remaining regions are sized by footprint area.
 */

$dpm_site_block = 'SITE MAP';

/**
 * Clickable stall regions. Continuous numbering runs in this array order.
 * shape: 'rect' needs x,y,w,h | 'path' needs d
 * label: optional overlay text (drawn after the shape, non-interactive)
 */
$dpm_site_stalls = [
    // Pineapple St. column — annotated 5 / 3 / 3 / 2 / 2
    ['id' => 'pineapple_1', 'count' => 5, 'shape' => 'rect', 'x' => 12, 'y' => 42, 'w' => 46, 'h' => 100],
    ['id' => 'pineapple_2', 'count' => 3, 'shape' => 'rect', 'x' => 12, 'y' => 150, 'w' => 46, 'h' => 48],
    ['id' => 'pineapple_3', 'count' => 3, 'shape' => 'rect', 'x' => 12, 'y' => 206, 'w' => 46, 'h' => 48],
    ['id' => 'pineapple_4', 'count' => 2, 'shape' => 'rect', 'x' => 12, 'y' => 262, 'w' => 46, 'h' => 40],
    ['id' => 'pineapple_5', 'count' => 2, 'shape' => 'rect', 'x' => 12, 'y' => 310, 'w' => 46, 'h' => 36],
    // Bottom-left L — annotated 8
    ['id' => 'l_shape', 'count' => 8, 'shape' => 'path', 'd' => 'M12 356 H58 V420 H118 V540 H12 Z'],
    // Top row — annotated 4 / 14 / 14 / 28
    ['id' => 'top_small_4', 'count' => 4, 'shape' => 'rect', 'x' => 136, 'y' => 42, 'w' => 50, 'h' => 32],
    ['id' => 'bot_block_a', 'count' => 14, 'shape' => 'rect', 'x' => 198, 'y' => 42, 'w' => 150, 'h' => 46, 'label' => 'BOT BLOCK A', 'label_x' => 273, 'label_y' => 70, 'label_class' => 'dpm-t'],
    ['id' => 'top_strip', 'count' => 14, 'shape' => 'rect', 'x' => 360, 'y' => 42, 'w' => 88, 'h' => 36],
    ['id' => 'bot_block_b', 'count' => 28, 'shape' => 'rect', 'x' => 460, 'y' => 42, 'w' => 160, 'h' => 46, 'label' => 'BOT BLOCK B', 'label_x' => 540, 'label_y' => 70, 'label_class' => 'dpm-t'],
    // Col 2 (size-based)
    ['id' => 'col2_a', 'count' => 16, 'shape' => 'rect', 'x' => 70, 'y' => 84, 'w' => 42, 'h' => 90],
    ['id' => 'col2_b', 'count' => 12, 'shape' => 'rect', 'x' => 70, 'y' => 182, 'w' => 42, 'h' => 70],
    // Three same-size tall strips — annotated 100+ each → 110
    ['id' => 'tall_1', 'count' => 110, 'shape' => 'rect', 'x' => 126, 'y' => 100, 'w' => 32, 'h' => 220],
    ['id' => 'tall_2', 'count' => 110, 'shape' => 'rect', 'x' => 168, 'y' => 100, 'w' => 32, 'h' => 220],
    ['id' => 'tall_3', 'count' => 110, 'shape' => 'rect', 'x' => 210, 'y' => 100, 'w' => 32, 'h' => 220],
    // Small blocks above D/E
    ['id' => 'small_a', 'count' => 8, 'shape' => 'rect', 'x' => 126, 'y' => 334, 'w' => 40, 'h' => 48],
    ['id' => 'small_b', 'count' => 4, 'shape' => 'rect', 'x' => 174, 'y' => 334, 'w' => 40, 'h' => 24],
    ['id' => 'small_c', 'count' => 4, 'shape' => 'rect', 'x' => 174, 'y' => 366, 'w' => 40, 'h' => 24],
    // Center towers (size-based)
    ['id' => 'tower_l', 'count' => 128, 'shape' => 'rect', 'x' => 256, 'y' => 100, 'w' => 110, 'h' => 270],
    ['id' => 'tower_r', 'count' => 128, 'shape' => 'rect', 'x' => 378, 'y' => 100, 'w' => 110, 'h' => 270],
    // Mid-right thin pair
    ['id' => 'mid_l', 'count' => 21, 'shape' => 'rect', 'x' => 506, 'y' => 100, 'w' => 26, 'h' => 200],
    ['id' => 'mid_r', 'count' => 21, 'shape' => 'rect', 'x' => 546, 'y' => 100, 'w' => 26, 'h' => 200],
    // Right stack
    ['id' => 'rs1', 'count' => 11, 'shape' => 'rect', 'x' => 588, 'y' => 100, 'w' => 64, 'h' => 40],
    ['id' => 'rs2', 'count' => 16, 'shape' => 'rect', 'x' => 588, 'y' => 148, 'w' => 64, 'h' => 60],
    ['id' => 'rs3', 'count' => 15, 'shape' => 'rect', 'x' => 588, 'y' => 216, 'w' => 64, 'h' => 56],
    // Thin strip (mouse target) — original size; caption is vertical + smaller text only
    [
        'id' => 'rs4',
        'count' => 23,
        'shape' => 'rect',
        'x' => 664,
        'y' => 148,
        'w' => 46,
        'h' => 124,
        'label' => 'behind dolor hotel bldg',
        'label_x' => 687,
        'label_y' => 210,
        'label_vertical' => true,
        'label_class' => 'dpm-tx dpm-tx--vertical',
    ],
    // Behind C / Block C
    ['id' => 'behind_c', 'count' => 32, 'shape' => 'rect', 'x' => 588, 'y' => 288, 'w' => 122, 'h' => 64, 'label' => 'BOT BEHIND BLOCK C', 'label_x' => 649, 'label_y' => 325, 'label_class' => 'dpm-ts'],
    ['id' => 'bot_block_c', 'count' => 88, 'shape' => 'rect', 'x' => 588, 'y' => 364, 'w' => 122, 'h' => 176, 'label' => 'BOT BLOCK C', 'label_x' => 649, 'label_y' => 456, 'label_class' => 'dpm-t'],
    // Far-right Vinzons strip — original size; vertical caption
    [
        'id' => 'vinzons',
        'count' => 51,
        'shape' => 'rect',
        'x' => 722,
        'y' => 42,
        'w' => 64,
        'h' => 498,
        'label' => 'Dolor Hotel Bldg Ground Floor',
        'label_x' => 754,
        'label_y' => 291,
        'label_vertical' => true,
        'label_class' => 'dpm-tx dpm-tx--vertical-wide',
    ],
    // Bottom D / E + thin
    ['id' => 'bot_block_d', 'count' => 34, 'shape' => 'rect', 'x' => 228, 'y' => 432, 'w' => 200, 'h' => 42, 'label' => 'BOT BLOCK D', 'label_x' => 328, 'label_y' => 458, 'label_class' => 'dpm-t'],
    ['id' => 'bot_block_e', 'count' => 44, 'shape' => 'rect', 'x' => 228, 'y' => 486, 'w' => 200, 'h' => 54, 'label' => 'BOT BLOCK E', 'label_x' => 328, 'label_y' => 518, 'label_class' => 'dpm-t'],
    ['id' => 'thin_de', 'count' => 14, 'shape' => 'rect', 'x' => 440, 'y' => 432, 'w' => 32, 'h' => 108],
];

$dpm_special_area_ids = [
    'pineapple_1', 'pineapple_2', 'pineapple_3', 'pineapple_4', 'pineapple_5',
    'top_small_4', 'bot_block_a', 'top_strip', 'bot_block_b',
    'small_a', 'small_b', 'small_c',
    'bot_block_c', 'bot_block_d', 'bot_block_e',
];
$dpm_meat_ids = ['col2_a', 'col2_b', 'l_shape', 'tall_1'];
$dpm_fish_ids = ['tall_2'];
$dpm_produce_ids = ['tall_3'];
$dpm_grocery_ids = ['tower_l'];
$dpm_service_ids = ['tower_r', 'behind_c'];
$dpm_dry_goods_ids = ['vinzons'];
$dpm_dried_fish_ids = ['rs1', 'rs2', 'rs3', 'rs4'];
$dpm_eateries_ids = ['mid_l', 'mid_r', 'thin_de'];
foreach ($dpm_site_stalls as &$dpm_stall_row) {
    if (in_array($dpm_stall_row['id'], $dpm_special_area_ids, true)) {
        $dpm_stall_row['section'] = 'Special Area Section';
    } elseif (in_array($dpm_stall_row['id'], $dpm_meat_ids, true)) {
        $dpm_stall_row['section'] = 'Meat and Chicken Section';
    } elseif (in_array($dpm_stall_row['id'], $dpm_fish_ids, true)) {
        $dpm_stall_row['section'] = 'Fresh Fish Section';
    } elseif (in_array($dpm_stall_row['id'], $dpm_produce_ids, true)) {
        $dpm_stall_row['section'] = 'Fruits and Vegetables Section';
    } elseif (in_array($dpm_stall_row['id'], $dpm_grocery_ids, true)) {
        $dpm_stall_row['section'] = 'Groceries Sari-Sari Section';
    } elseif (in_array($dpm_stall_row['id'], $dpm_service_ids, true)) {
        $dpm_stall_row['section'] = 'Service Section';
    } elseif (in_array($dpm_stall_row['id'], $dpm_dry_goods_ids, true)) {
        $dpm_stall_row['section'] = 'Dry Goods Section';
    } elseif (in_array($dpm_stall_row['id'], $dpm_dried_fish_ids, true)) {
        $dpm_stall_row['section'] = 'Dried and Salted Fish Section';
    } elseif (in_array($dpm_stall_row['id'], $dpm_eateries_ids, true)) {
        $dpm_stall_row['section'] = 'Eateries and Cooked Food Section';
    }
}
unset($dpm_stall_row);

// Block shown for a slot = the labeled block that stall region sits nearest to.
$dpm_nearby_blocks = [
    'pineapple_1' => 'BOT BLOCK A',
    'pineapple_2' => 'BOT BLOCK A',
    'pineapple_3' => 'BOT BLOCK A',
    'pineapple_4' => 'BOT BLOCK A',
    'pineapple_5' => 'BOT BLOCK D',
    'l_shape' => 'BOT BLOCK D',
    'top_small_4' => 'BOT BLOCK A',
    'bot_block_a' => 'BOT BLOCK A',
    'top_strip' => 'BOT BLOCK A',
    'bot_block_b' => 'BOT BLOCK B',
    'col2_a' => 'BOT BLOCK A',
    'col2_b' => 'BOT BLOCK A',
    'tall_1' => 'BOT BLOCK A',
    'tall_2' => 'BOT BLOCK A',
    'tall_3' => 'BOT BLOCK A',
    'small_a' => 'BOT BLOCK D',
    'small_b' => 'BOT BLOCK D',
    'small_c' => 'BOT BLOCK D',
    'tower_l' => 'BOT BLOCK A',
    'tower_r' => 'BOT BLOCK B',
    'mid_l' => 'BOT BLOCK B',
    'mid_r' => 'BOT BLOCK B',
    'rs1' => 'BOT BLOCK B',
    'rs2' => 'BOT BLOCK B',
    'rs3' => 'BOT BEHIND BLOCK C',
    'rs4' => 'BOT BEHIND BLOCK C',
    'behind_c' => 'BOT BEHIND BLOCK C',
    'bot_block_c' => 'BOT BLOCK C',
    'vinzons' => 'BOT BLOCK C',
    'bot_block_d' => 'BOT BLOCK D',
    'bot_block_e' => 'BOT BLOCK E',
    'thin_de' => 'BOT BLOCK E',
];
foreach ($dpm_site_stalls as &$dpm_stall_row) {
    $dpm_stall_id = (string) ($dpm_stall_row['id'] ?? '');
    if (isset($dpm_nearby_blocks[$dpm_stall_id])) {
        $dpm_stall_row['block'] = $dpm_nearby_blocks[$dpm_stall_id];
    }
}
unset($dpm_stall_row);

$dpm_site_total_slots = 0;
foreach ($dpm_site_stalls as $dpm_row) {
    $dpm_site_total_slots += (int) $dpm_row['count'];
}

$dpm_site_occupied_slots = [];
if (!empty($conn)) {
    if (!function_exists('dpmEnsureStallColumns')) {
        require_once __DIR__ . '/stall_slot_map.php';
    }
    dpmEnsureStallColumns($conn);

    // Mock occupancy: about 40% of each strip, stable across page loads.
    $dpm_mock_expected = 0;
    $dpm_mock_cursor = 1;
    $dpm_mock_plan = [];
    foreach ($dpm_site_stalls as $dpm_mock_stall) {
        $dpm_mock_count = (int) $dpm_mock_stall['count'];
        $dpm_mock_take = (int) round($dpm_mock_count * 0.4);
        $dpm_mock_expected += $dpm_mock_take;
        $dpm_mock_nums = $dpm_mock_count > 0 ? range($dpm_mock_cursor, $dpm_mock_cursor + $dpm_mock_count - 1) : [];
        $dpm_mock_seed = (string) ($dpm_mock_stall['id'] ?? $dpm_mock_cursor);
        usort($dpm_mock_nums, static function ($a, $b) use ($dpm_mock_seed) {
            return crc32($dpm_mock_seed . ':' . $a) <=> crc32($dpm_mock_seed . ':' . $b);
        });
        foreach (array_slice($dpm_mock_nums, 0, $dpm_mock_take) as $dpm_mock_num) {
            $dpm_mock_plan[] = [
                $dpm_mock_num,
                (string) ($dpm_mock_stall['section'] ?? ''),
                (string) ($dpm_mock_stall['block'] ?? ''),
            ];
        }
        $dpm_mock_cursor += $dpm_mock_count;
    }
    $dpm_mock_have = 0;
    $dpm_mock_count_res = $conn->query("SELECT COUNT(*) AS c FROM market_slots WHERE block_name = 'SITE MAP' AND status IN ('occupied', 'second_floor')");
    if ($dpm_mock_count_res) {
        $dpm_mock_have = (int) ($dpm_mock_count_res->fetch_assoc()['c'] ?? 0);
    }
    if ($dpm_mock_have < $dpm_mock_expected && $dpm_mock_plan) {
        $dpm_mock_ins = $conn->prepare(
            "INSERT IGNORE INTO market_slots (block_name, slot_number, status, holder_name, holder_section, holder_block)
             VALUES ('SITE MAP', ?, 'occupied', 'Mock vendor', ?, ?)"
        );
        if ($dpm_mock_ins) {
            foreach ($dpm_mock_plan as $dpm_mock_row) {
                $dpm_mock_num = (int) $dpm_mock_row[0];
                $dpm_mock_section = $dpm_mock_row[1];
                $dpm_mock_block = $dpm_mock_row[2];
                $dpm_mock_ins->bind_param('iss', $dpm_mock_num, $dpm_mock_section, $dpm_mock_block);
                $dpm_mock_ins->execute();
            }
            $dpm_mock_ins->close();
        }
    }

    $stmt = $conn->prepare(
        "SELECT slot_number FROM market_slots
         WHERE block_name = ? AND slot_number BETWEEN 1 AND ?
         AND status IN ('occupied', 'second_floor')"
    );
    if ($stmt) {
        $stmt->bind_param('si', $dpm_site_block, $dpm_site_total_slots);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $dpm_site_occupied_slots[] = (int) $row['slot_number'];
        }
        $stmt->close();
    }

    $dpm_slot_photos = [];
    $dpm_photo_res = $conn->query(
        "SELECT slot_number, image1, image2, image3
         FROM market_slots
         WHERE block_name = 'SITE MAP'
           AND (COALESCE(image1, '') <> '' OR COALESCE(image2, '') <> '' OR COALESCE(image3, '') <> '')"
    );
    if ($dpm_photo_res) {
        while ($dpm_photo_row = $dpm_photo_res->fetch_assoc()) {
            $dpm_photo_list = [];
            foreach (['image1', 'image2', 'image3'] as $dpm_photo_key) {
                $dpm_photo_src = trim((string) ($dpm_photo_row[$dpm_photo_key] ?? ''));
                if ($dpm_photo_src !== '') {
                    $dpm_photo_list[] = $dpm_photo_src;
                }
            }
            if ($dpm_photo_list) {
                $dpm_slot_photos[(string) (int) $dpm_photo_row['slot_number']] = $dpm_photo_list;
            }
        }
    }
}
sort($dpm_site_occupied_slots, SORT_NUMERIC);

$dpm_admin_slot_meta = [];
if (!empty($dpm_site_admin_mode) && !empty($conn)) {
    $dpm_meta_res = $conn->query(
        "SELECT slot_number, status, image1, image2, image3, holder_vendor_id, holder_name, holder_section, holder_block
         FROM market_slots WHERE block_name = 'SITE MAP'"
    );
    if ($dpm_meta_res) {
        while ($dpm_meta_row = $dpm_meta_res->fetch_assoc()) {
            $dpm_admin_slot_meta[(string) (int) $dpm_meta_row['slot_number']] = [
                'status' => (string) ($dpm_meta_row['status'] ?? 'available'),
                'image1' => (string) ($dpm_meta_row['image1'] ?? ''),
                'image2' => (string) ($dpm_meta_row['image2'] ?? ''),
                'image3' => (string) ($dpm_meta_row['image3'] ?? ''),
                'holder_vendor_id' => (string) ($dpm_meta_row['holder_vendor_id'] ?? ''),
                'holder_name' => (string) ($dpm_meta_row['holder_name'] ?? ''),
                'holder_section' => (string) ($dpm_meta_row['holder_section'] ?? ''),
                'holder_block' => (string) ($dpm_meta_row['holder_block'] ?? ''),
            ];
        }
    }
}

if (!function_exists('dpmRenderSiteStallGroup')) {
    function dpmRenderSiteStallGroup(array $stall, int $start): void
    {
        $cnt = (int) $stall['count'];
        $end = $start + $cnt - 1;
        $section = (string) ($stall['section'] ?? '');
        $block = (string) ($stall['block'] ?? '');
        $tone = '';
        if ($section === 'Special Area Section') {
            $tone = 'special';
        } elseif ($section === 'Meat and Chicken Section') {
            $tone = 'meat';
        } elseif ($section === 'Fresh Fish Section') {
            $tone = 'fish';
        } elseif ($section === 'Fruits and Vegetables Section') {
            $tone = 'produce';
        } elseif ($section === 'Groceries Sari-Sari Section') {
            $tone = 'grocery';
        } elseif ($section === 'Service Section') {
            $tone = 'service';
        } elseif ($section === 'Dry Goods Section') {
            $tone = 'drygoods';
        } elseif ($section === 'Dried and Salted Fish Section') {
            $tone = 'driedfish';
        } elseif ($section === 'Eateries and Cooked Food Section') {
            $tone = 'eateries';
        }
        $aria = $cnt === 1
            ? ('Stall slot ' . $start . ', click to view')
            : ('Stall slots ' . $start . '–' . $end . ', click to view');
        if ($section !== '') {
            $aria = $section . ', ' . $aria;
        }
        echo '<g class="dpm-stall-group" role="button" tabindex="0"'
            . ' data-slot-count="' . $cnt . '"'
            . ' data-slot-start="' . $start . '"'
            . ' data-stall-id="' . htmlspecialchars((string) ($stall['id'] ?? ''), ENT_QUOTES, 'UTF-8') . '"'
            . ($section !== '' ? ' data-section="' . htmlspecialchars($section, ENT_QUOTES, 'UTF-8') . '"' : '')
            . ($block !== '' ? ' data-block="' . htmlspecialchars($block, ENT_QUOTES, 'UTF-8') . '"' : '')
            . ' aria-label="' . htmlspecialchars($aria, ENT_QUOTES, 'UTF-8') . '">';
        $shapeClass = 'dpm-s dpm-s--stall' . ($tone !== '' ? ' dpm-s--' . $tone : '');
        if (($stall['shape'] ?? '') === 'path') {
            echo '<path class="' . $shapeClass . '" d="' . htmlspecialchars((string) $stall['d'], ENT_QUOTES, 'UTF-8') . '"/>';
        } else {
            echo '<rect class="' . $shapeClass . '" x="' . (float) $stall['x'] . '" y="' . (float) $stall['y']
                . '" width="' . (float) $stall['w'] . '" height="' . (float) $stall['h'] . '"/>';
        }
        $cls = htmlspecialchars((string) ($stall['label_class'] ?? 'dpm-t'), ENT_QUOTES, 'UTF-8');
        $lx = (float) ($stall['label_x'] ?? 0);
        $ly = (float) ($stall['label_y'] ?? 0);
        if (!empty($stall['label_lines']) && is_array($stall['label_lines'])) {
            echo '<text class="' . $cls . '" x="' . $lx . '" y="' . $ly . '" text-anchor="middle">';
            $first = true;
            foreach ($stall['label_lines'] as $line) {
                $dy = $first ? '0' : '1.15em';
                $first = false;
                echo '<tspan x="' . $lx . '" dy="' . $dy . '">'
                    . htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') . '</tspan>';
            }
            echo '</text>';
        } elseif (!empty($stall['label'])) {
            $vertical = !empty($stall['label_vertical']);
            $transform = $vertical ? ' transform="rotate(-90 ' . $lx . ' ' . $ly . ')"' : '';
            echo '<text class="' . $cls . '" x="' . $lx . '" y="' . $ly . '"'
                . $transform
                . ' text-anchor="middle" dominant-baseline="middle">'
                . htmlspecialchars((string) $stall['label'], ENT_QUOTES, 'UTF-8')
                . '</text>';
        }
        echo '</g>';
    }
}
?>
<div class="dpm-site-map" role="img" aria-label="Schematic site map of Daet Public Market — <?php echo (int) $dpm_site_total_slots; ?> stall slots">
  <div class="dpm-site-shell">
    <div class="dpm-street dpm-street--n">CORESES ST. (FELIPE II)</div>

    <div class="dpm-site-mid">
      <div class="dpm-street dpm-street--w" aria-hidden="true"><span>PINEAPPLE ST.</span></div>

      <div class="dpm-market-wrap">
        <svg class="dpm-site-svg" viewBox="0 0 800 560" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <defs>
            <pattern id="dpmHatch" patternUnits="userSpaceOnUse" width="10" height="6">
              <line x1="0" y1="3" x2="10" y2="3" stroke="#111" stroke-width="1.4"/>
            </pattern>
            <linearGradient id="dpmGold" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0%" stop-color="#f6c445"/>
              <stop offset="18%" stop-color="#ffe98a"/>
              <stop offset="36%" stop-color="#f0b429"/>
              <stop offset="52%" stop-color="#fff3b0"/>
              <stop offset="70%" stop-color="#e8a317"/>
              <stop offset="86%" stop-color="#ffe07a"/>
              <stop offset="100%" stop-color="#f6c445"/>
            </linearGradient>
          </defs>

          <!-- Market paper + border -->
          <rect x="1" y="1" width="798" height="558" fill="#fbfbf9" stroke="#111" stroke-width="2.5"/>

          <!-- Purok: inside border, outside stall boxes -->
          <text class="dpm-purok" x="14" y="28">P-5 Purok Borabod</text>

          <!-- Utility stairs (not stall slots) -->
          <rect class="dpm-s" x="70" y="42" width="32" height="28" fill="url(#dpmHatch)"/>
          <text class="dpm-tx dpm-tx--inbox" x="86" y="56" text-anchor="middle" dominant-baseline="middle">US</text>
          <rect class="dpm-s" x="310" y="106" width="32" height="28" fill="url(#dpmHatch)"/>
          <text class="dpm-tx dpm-tx--inbox" x="326" y="120" text-anchor="middle" dominant-baseline="middle">US</text>
          <rect class="dpm-s" x="258" y="390" width="32" height="28" fill="url(#dpmHatch)"/>
          <text class="dpm-tx dpm-tx--inbox" x="274" y="404" text-anchor="middle" dominant-baseline="middle">US</text>

          <!-- All clickable stall regions (continuous 1…1083) -->
          <?php
          $dpm_slot_start = 1;
          foreach ($dpm_site_stalls as $dpm_stall) {
              dpmRenderSiteStallGroup($dpm_stall, $dpm_slot_start);
              $dpm_slot_start += (int) $dpm_stall['count'];
          }
          ?>

          <!-- Landmarks only — not rentable / not clickable -->
          <rect class="dpm-s" x="502" y="400" width="52" height="56"/>
          <text class="dpm-tx" x="528" y="418" text-anchor="middle">Understar</text>
          <rect class="dpm-s" x="516" y="424" width="24" height="24"/>
          <text class="dpm-tx" x="528" y="441" text-anchor="middle">CR</text>
          <rect class="dpm-s" x="502" y="468" width="52" height="72"/>
          <text class="dpm-tx" x="528" y="498" text-anchor="middle">SOUTH STAR</text>
          <text class="dpm-tx" x="528" y="512" text-anchor="middle">DRUG</text>
        </svg>
      </div>

      <div class="dpm-street dpm-street--e" aria-hidden="true"><span>VINZONS AVENUE</span></div>
    </div>

    <div class="dpm-street dpm-street--s">J. LUKBAN ST</div>
  </div>

  <p class="dpm-site-map-note">
    <i class="fas fa-map" aria-hidden="true"></i>
    <span data-translate="map.siteClickHint">Click a stall on the map to view stall slots.</span>
    <span class="dpm-site-map-total"> · <?php echo (int) $dpm_site_total_slots; ?> stalls total</span>
  </p>
</div>

<div class="dpm-stall-slots-modal" id="dpmStallSlotsModal" role="dialog" aria-modal="true" aria-labelledby="dpmStallSlotsTitle" hidden>
  <div class="dpm-stall-slots-modal__backdrop" data-dpm-stall-close="1"></div>
  <div class="dpm-stall-slots-modal__panel">
    <button type="button" class="dpm-stall-slots-modal__close" data-dpm-stall-close="1" aria-label="Close">&times;</button>
    <div class="dpm-stall-slots-modal__head">
      <h3 id="dpmStallSlotsTitle" class="dpm-stall-slots-modal__title" data-translate="map.stallSlotsTitle">Stall slots</h3>
      <div class="dpm-stall-slots-modal__legend" aria-label="Slot status legend">
        <span class="dpm-stall-slots-modal__legend-item">
          <span class="dpm-stall-slots-legend-swatch is-available" aria-hidden="true"></span>
          <span data-translate="map.legendAvailable">Available</span>
        </span>
        <span class="dpm-stall-slots-modal__legend-item">
          <span class="dpm-stall-slots-legend-swatch is-occupied" aria-hidden="true"></span>
          <span data-translate="map.legendOccupied">Occupied</span>
        </span>
      </div>
    </div>
    <div id="dpmStallSlotsGrid" class="dpm-stall-slots-grid"></div>
  </div>
</div>

<div class="dpm-slot-detail" id="dpmSlotDetail" hidden>
  <div class="dpm-slot-detail__backdrop" data-dpm-detail-close="1"></div>
  <div class="dpm-slot-detail__panel" role="dialog" aria-modal="true" aria-labelledby="dpmSlotDetailTitle">
    <button type="button" class="dpm-slot-detail__close" data-dpm-detail-close="1" aria-label="Close">&times;</button>
    <h3 id="dpmSlotDetailTitle" class="dpm-slot-detail__title">Stall slot</h3>
    <dl class="dpm-slot-detail__list">
      <div>
        <dt data-dpm-detail-label="slot">Slot number</dt>
        <dd id="dpmSlotDetailNumber"></dd>
      </div>
      <div>
        <dt data-dpm-detail-label="section">Section</dt>
        <dd id="dpmSlotDetailSection"></dd>
      </div>
      <div>
        <dt data-dpm-detail-label="block">Block</dt>
        <dd id="dpmSlotDetailBlock"></dd>
      </div>
    </dl>
    <div class="dpm-slot-detail__photos" id="dpmSlotDetailPhotos" hidden></div>
    <a class="dpm-slot-detail__apply" id="dpmSlotDetailApply" href="application.php">Apply for this slot now</a>
  </div>
</div>

<div class="dpm-photo-view" id="dpmPhotoView" hidden>
  <button type="button" class="dpm-photo-view__backdrop" data-dpm-photo-close="1" aria-label="Close photo"></button>
  <button type="button" class="dpm-photo-view__close" data-dpm-photo-close="1" aria-label="Close">&times;</button>
  <img id="dpmPhotoViewImg" alt="Stall photo">
</div>

<style>
  .dpm-site-map {
    width: 100%;
    max-width: 1000px;
    margin: 0 auto;
  }

  .dpm-site-shell {
    display: grid;
    grid-template-rows: auto minmax(0, auto) auto;
    gap: 0.4rem;
    background: #fff;
    padding: 0.35rem 0.25rem 0.5rem;
  }

  .dpm-site-mid {
    display: grid;
    grid-template-columns: 2.5rem minmax(0, 1fr) 2.5rem;
    gap: 0.35rem;
    align-items: stretch;
  }

  .dpm-street {
    font-family: "Courier New", ui-monospace, monospace;
    font-weight: 800;
    font-size: clamp(0.7rem, 1.5vw, 0.88rem);
    letter-spacing: 0.06em;
    color: #111;
    text-transform: uppercase;
    text-align: center;
    line-height: 1.2;
  }

  .dpm-street--n,
  .dpm-street--s {
    padding: 0.15rem 0.5rem;
  }

  .dpm-street--w,
  .dpm-street--e {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 2.35rem;
  }

  .dpm-street--w span,
  .dpm-street--e span {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    white-space: nowrap;
    font-family: inherit;
    font-weight: inherit;
    font-size: inherit;
    letter-spacing: inherit;
    color: inherit;
    text-transform: inherit;
    line-height: inherit;
  }

  .dpm-market-wrap {
    min-width: 0;
    overflow-x: auto;
    border: 0;
  }

  .dpm-site-svg {
    display: block;
    width: 100%;
    min-width: 640px;
    height: auto;
  }

  .dpm-site-svg .dpm-s {
    fill: #fff;
    stroke: #111;
    stroke-width: 2;
  }

  .dpm-site-svg .dpm-s--special {
    fill: url(#dpmGold);
    stroke: #c48a12;
  }

  .dpm-site-svg .dpm-s--meat {
    fill: #a32020;
    stroke: #6e1818;
  }

  .dpm-site-svg .dpm-s--fish {
    fill: #7277ff;
    stroke: #5054c9;
  }

  .dpm-site-svg .dpm-s--produce {
    fill: #66cd00;
    stroke: #458b00;
  }

  .dpm-site-svg .dpm-s--grocery {
    fill: #f97316;
    stroke: #c2410c;
  }

  .dpm-site-svg .dpm-s--service {
    fill: #8b5cf6;
    stroke: #6d28d9;
  }

  /* Glidden Dry Goods Neutral 40YY 44/095 */
  .dpm-site-svg .dpm-s--drygoods {
    fill: #bbb2a2;
    stroke: #8f8575;
  }

  .dpm-site-svg .dpm-s--driedfish {
    fill: #7c5c3e;
    stroke: #5c4033;
  }

  .dpm-site-svg .dpm-s--eateries {
    fill: #f5c518;
    stroke: #c9970a;
  }

  .dpm-site-svg .dpm-stall-group {
    cursor: pointer;
    outline: none;
    transform-box: fill-box;
    transform-origin: center;
    transition: transform 0.28s cubic-bezier(0.34, 1.35, 0.64, 1);
  }

  .dpm-site-svg .dpm-stall-group .dpm-s--stall {
    transition: fill 0.22s ease, stroke 0.22s ease, stroke-width 0.22s ease, filter 0.28s ease;
  }

  .dpm-site-svg .dpm-stall-group:hover,
  .dpm-site-svg .dpm-stall-group:focus-visible {
    transform: scale(1.06);
  }

  @keyframes dpmStallPulse {
    0%, 100% { stroke: #6366f1; }
    50% { stroke: #818cf8; }
  }

  .dpm-site-svg .dpm-stall-group:hover .dpm-s--stall,
  .dpm-site-svg .dpm-stall-group:focus-visible .dpm-s--stall {
    fill: #eef2ff;
    stroke: #6366f1;
    stroke-width: 2.5;
    filter: drop-shadow(0 4px 10px rgba(99, 102, 241, 0.45));
    animation: dpmStallPulse 1.2s ease-in-out infinite;
  }

  @keyframes dpmSpecialPulse {
    0%, 100% { stroke: #b8860b; }
    50% { stroke: #f59e0b; }
  }

  .dpm-site-svg .dpm-stall-group:hover .dpm-s--special,
  .dpm-site-svg .dpm-stall-group:focus-visible .dpm-s--special {
    fill: url(#dpmGold);
    stroke: #c48a12;
    filter: drop-shadow(0 4px 10px rgba(232, 163, 23, 0.45));
    animation: dpmSpecialPulse 1.2s ease-in-out infinite;
  }

  .dpm-site-svg .dpm-stall-group:hover .dpm-s--meat,
  .dpm-site-svg .dpm-stall-group:focus-visible .dpm-s--meat {
    fill: #a32020;
    stroke: #6e1818;
    filter: none;
    animation: none;
  }

  .dpm-site-svg .dpm-stall-group:hover .dpm-s--fish,
  .dpm-site-svg .dpm-stall-group:focus-visible .dpm-s--fish {
    fill: #7277ff;
    stroke: #5054c9;
    filter: none;
    animation: none;
  }

  .dpm-site-svg .dpm-stall-group:hover .dpm-s--produce,
  .dpm-site-svg .dpm-stall-group:focus-visible .dpm-s--produce {
    fill: #66cd00;
    stroke: #458b00;
    filter: none;
    animation: none;
  }

  .dpm-site-svg .dpm-stall-group:hover .dpm-s--grocery,
  .dpm-site-svg .dpm-stall-group:focus-visible .dpm-s--grocery {
    fill: #f97316;
    stroke: #c2410c;
    filter: none;
    animation: none;
  }

  .dpm-site-svg .dpm-stall-group:hover .dpm-s--service,
  .dpm-site-svg .dpm-stall-group:focus-visible .dpm-s--service {
    fill: #8b5cf6;
    stroke: #6d28d9;
    filter: none;
    animation: none;
  }

  .dpm-site-svg .dpm-stall-group:hover .dpm-s--drygoods,
  .dpm-site-svg .dpm-stall-group:focus-visible .dpm-s--drygoods {
    fill: #bbb2a2;
    stroke: #8f8575;
    filter: none;
    animation: none;
  }

  .dpm-site-svg .dpm-stall-group:hover .dpm-s--driedfish,
  .dpm-site-svg .dpm-stall-group:focus-visible .dpm-s--driedfish {
    fill: #7c5c3e;
    stroke: #5c4033;
    filter: none;
    animation: none;
  }

  .dpm-site-svg .dpm-stall-group:hover .dpm-s--eateries,
  .dpm-site-svg .dpm-stall-group:focus-visible .dpm-s--eateries {
    fill: #f5c518;
    stroke: #c9970a;
    filter: none;
    animation: none;
  }

  .dpm-stall-slots-modal {
    position: fixed;
    inset: 0;
    z-index: 13000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease, visibility 0.2s ease;
  }

  .dpm-stall-slots-modal.is-open {
    opacity: 1;
    visibility: visible;
  }

  .dpm-stall-slots-modal[hidden] {
    display: none !important;
  }

  .dpm-stall-slots-modal:not([hidden]) {
    display: flex;
  }

  .dpm-stall-slots-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
  }

  .dpm-stall-slots-modal__panel {
    position: relative;
    width: min(560px, 100%);
    max-height: min(85vh, 640px);
    overflow-y: auto;
    background: #fff;
    border-radius: 20px;
    padding: 1.5rem 1.35rem 1.35rem;
    box-shadow: 0 24px 60px rgba(46, 35, 108, 0.22);
    border: 1px solid rgba(99, 102, 241, 0.15);
    transform: scale(0.96) translateY(8px);
    transition: transform 0.2s ease;
  }

  .dpm-stall-slots-modal.is-open .dpm-stall-slots-modal__panel {
    transform: scale(1) translateY(0);
  }

  .dpm-stall-slots-modal__close {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    width: 34px;
    height: 34px;
    border: none;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.1);
    color: #4338ca;
    font-size: 1.2rem;
    cursor: pointer;
    line-height: 1;
  }

  .dpm-stall-slots-modal__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem 1rem;
    margin: 0 2rem 1rem 0;
  }

  .dpm-stall-slots-modal__title {
    margin: 0;
    font-family: 'DM Serif Display', Georgia, serif;
    font-size: 1.45rem;
    font-weight: 400;
    color: #1e1b4b;
    line-height: 1.2;
  }

  .dpm-stall-slots-modal__legend {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.65rem 1rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: #475569;
  }

  .dpm-stall-slots-modal__legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
  }

  .dpm-stall-slots-legend-swatch {
    width: 15px;
    height: 15px;
    border-radius: 4px;
    border: 1.5px solid #94a3b8;
    flex-shrink: 0;
  }

  .dpm-stall-slots-legend-swatch.is-available {
    border-color: #22c55e;
    background: #f0fdf4;
  }

  .dpm-stall-slots-legend-swatch.is-occupied {
    border-color: #ef4444;
    background: #fee2e2;
  }

  .dpm-stall-slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
    gap: 0.55rem;
  }

  .dpm-stall-slot-chip {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 2.75rem;
    padding: 0.65rem 0.35rem;
    border-radius: 14px;
    border: 2px solid #86efac;
    background: linear-gradient(180deg, #f0fdf4 0%, #ecfdf5 100%);
    color: #166534;
    font-weight: 700;
    font-size: 0.95rem;
    font-family: inherit;
    width: 100%;
    text-align: center;
    cursor: pointer;
    transition: transform 0.22s cubic-bezier(0.34, 1.35, 0.64, 1), box-shadow 0.22s ease, border-color 0.22s ease;
  }

  .dpm-stall-slot-chip.is-available {
    border-color: #86efac;
    background: linear-gradient(180deg, #f0fdf4 0%, #ecfdf5 100%);
    color: #166534;
  }

  .dpm-stall-slot-chip.is-occupied,
  .dpm-stall-slot-chip.is-occupied:disabled {
    border-color: #fca5a5;
    background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%);
    color: #b91c1c;
    cursor: not-allowed;
    opacity: 1;
  }

  .dpm-stall-slot-chip.is-available:hover {
    transform: translateY(-4px) scale(1.04);
    box-shadow: 0 10px 24px rgba(34, 197, 94, 0.28);
    border-color: #4ade80;
  }

  .dpm-slot-detail {
    position: fixed;
    inset: 0;
    z-index: 15000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
  }

  .dpm-slot-detail[hidden] {
    display: none;
  }

  .dpm-slot-detail__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
  }

  .dpm-slot-detail__panel {
    position: relative;
    width: min(420px, 100%);
    background: #fff;
    border-radius: 18px;
    border: 1px solid #e0e7ff;
    padding: 1.35rem 1.35rem 1.25rem;
    box-shadow: 0 24px 60px rgba(49, 46, 129, 0.32);
    animation: dpmSlotDetailPop 0.28s cubic-bezier(0.34, 1.4, 0.64, 1);
  }

  .dpm-slot-detail__close {
    position: absolute;
    top: 0.7rem;
    right: 0.7rem;
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 50%;
    background: #eef2ff;
    color: #4338ca;
    font-size: 1.2rem;
    line-height: 1;
    cursor: pointer;
  }

  .dpm-slot-detail__close:hover,
  .dpm-slot-detail__close:focus-visible {
    background: #4f46e5;
    color: #fff;
    outline: none;
  }

  .dpm-slot-detail__title {
    margin: 0 2rem 0.9rem 0;
    color: #312e81;
    font-size: 1.2rem;
    font-weight: 750;
  }

  .dpm-slot-detail__list {
    margin: 0 0 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
  }

  .dpm-slot-detail__list div {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.55rem 0.7rem;
    border-radius: 10px;
    background: #f8fafc;
  }

  .dpm-slot-detail__list dt {
    margin: 0;
    color: #6366f1;
    font-weight: 700;
    font-size: 0.82rem;
  }

  .dpm-slot-detail__list dd {
    margin: 0;
    color: #1e1b4b;
    font-weight: 700;
    text-align: right;
  }

  .dpm-slot-detail__apply {
    display: block;
    text-align: center;
    text-decoration: none;
    background: #4f46e5;
    color: #fff;
    font-weight: 700;
    border-radius: 999px;
    padding: 0.8rem 1rem;
    box-shadow: 0 8px 22px rgba(79, 70, 229, 0.35);
  }

  .dpm-slot-detail__apply:hover,
  .dpm-slot-detail__apply:focus-visible {
    background: #4338ca;
    outline: none;
  }

  .dpm-slot-detail__photos {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin-bottom: 0.95rem;
  }

  .dpm-slot-detail__photos[hidden] {
    display: none;
  }

  .dpm-slot-detail__photos img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #e0e7ff;
    background: #f8fafc;
    cursor: zoom-in;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }

  .dpm-slot-detail__photos button {
    display: block;
    width: 100%;
    padding: 0;
    border: none;
    background: transparent;
    border-radius: 10px;
    cursor: zoom-in;
  }

  .dpm-slot-detail__photos button:hover img,
  .dpm-slot-detail__photos button:focus-visible img {
    transform: scale(1.04);
    box-shadow: 0 8px 18px rgba(49, 46, 129, 0.22);
  }

  .dpm-photo-view {
    position: fixed;
    inset: 0;
    z-index: 16000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
  }

  .dpm-photo-view[hidden] {
    display: none;
  }

  .dpm-photo-view__backdrop {
    position: absolute;
    inset: 0;
    border: none;
    background: rgba(15, 23, 42, 0.82);
    cursor: zoom-out;
  }

  .dpm-photo-view img {
    position: relative;
    z-index: 1;
    max-width: min(920px, 92vw);
    max-height: 86vh;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
    background: #fff;
  }

  .dpm-photo-view__close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    z-index: 2;
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 50%;
    background: #4f46e5;
    color: #fff;
    font-size: 1.4rem;
    line-height: 1;
    cursor: pointer;
    box-shadow: 0 8px 18px rgba(79, 70, 229, 0.4);
  }

  .dpm-photo-view__close:hover,
  .dpm-photo-view__close:focus-visible {
    background: #4338ca;
    outline: none;
  }

  @keyframes dpmSlotDetailPop {
    from { opacity: 0; transform: scale(0.9) translateY(8px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
  }

  .dpm-stall-slot-chip.is-occupied:hover,
  .dpm-stall-slot-chip.is-occupied:disabled:hover {
    transform: none;
    box-shadow: none;
    border-color: #fca5a5;
    background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%);
    color: #b91c1c;
  }

  .dpm-stall-slot-chip.is-occupied.is-admin,
  .dpm-stall-slot-chip.is-occupied.is-admin:hover,
  .dpm-stall-slot-chip.is-available.is-admin {
    cursor: pointer;
  }

  .dpm-stall-slot-chip.is-occupied.is-admin:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 8px 18px rgba(239, 68, 68, 0.22);
  }

  .dpm-site-svg .dpm-purok {
    fill: #111;
    font-family: "Courier New", ui-monospace, monospace;
    font-size: 13px;
    font-weight: 800;
  }

  .dpm-site-svg .dpm-t {
    fill: #111;
    font-family: "Courier New", ui-monospace, monospace;
    font-size: 11px;
    font-weight: 800;
    pointer-events: none;
  }

  .dpm-site-svg .dpm-ts {
    fill: #111;
    font-family: "Courier New", ui-monospace, monospace;
    font-size: 8.5px;
    font-weight: 800;
    pointer-events: none;
  }

  .dpm-site-svg .dpm-tx {
    fill: #111;
    font-family: "Courier New", ui-monospace, monospace;
    font-size: 8.5px;
    font-weight: 800;
    pointer-events: none;
  }

  .dpm-site-svg .dpm-tx--stack {
    font-size: 9.5px;
    letter-spacing: 0.02em;
    text-transform: lowercase;
  }

  .dpm-site-svg .dpm-tx--vertical {
    font-size: 6.5px;
    letter-spacing: 0.03em;
    text-transform: lowercase;
  }

  .dpm-site-svg .dpm-tx--vertical-wide {
    font-size: 11px;
    letter-spacing: 0.04em;
  }

  .dpm-site-map-note {
    margin: 0.75rem 0 0;
    font-size: 0.82rem;
    color: #64748b;
    text-align: center;
  }

  .dpm-site-map-note i {
    color: #6366f1;
    margin-right: 0.35rem;
  }

  .dpm-site-map-total {
    font-weight: 700;
    color: #475569;
  }

  body.darkmode .dpm-stall-slots-modal__panel {
    background: #0b0b0d;
    border-color: rgba(255, 255, 255, 0.1);
    color: #fff;
  }

  body.darkmode .dpm-stall-slots-modal__title {
    color: #fff;
  }

  body.darkmode .dpm-stall-slots-modal__legend {
    color: rgba(255, 255, 255, 0.78);
  }

  body.darkmode .dpm-stall-slot-chip.is-available {
    background: linear-gradient(180deg, rgba(34, 197, 94, 0.18) 0%, rgba(34, 197, 94, 0.1) 100%);
    border-color: #4ade80;
    color: #bbf7d0;
  }

  body.darkmode .dpm-stall-slot-chip.is-occupied,
  body.darkmode .dpm-stall-slot-chip.is-occupied:disabled {
    background: linear-gradient(180deg, rgba(239, 68, 68, 0.2) 0%, rgba(239, 68, 68, 0.12) 100%);
    border-color: #f87171;
    color: #fecaca;
    cursor: not-allowed;
  }

  body.darkmode .dpm-site-map-total {
    color: rgba(255, 255, 255, 0.78);
  }

  @media (max-width: 640px) {
    .dpm-site-mid {
      grid-template-columns: 1.85rem minmax(0, 1fr) 1.85rem;
      gap: 0.25rem;
    }

    .dpm-street {
      font-size: 0.7rem;
    }

    .dpm-street--w,
    .dpm-street--e {
      min-width: 1.85rem;
    }
  }
</style>

<script type="application/json" id="dpmSiteOccupiedSlots"><?php echo json_encode($dpm_site_occupied_slots, JSON_UNESCAPED_UNICODE); ?></script>
<script type="application/json" id="dpmSiteSlotPhotos"><?php echo json_encode($dpm_slot_photos ?? [], JSON_UNESCAPED_UNICODE); ?></script>
<?php if (!empty($dpm_site_admin_mode)): ?>
<script>window.DPM_ADMIN_SLOT_META = <?php echo json_encode($dpm_admin_slot_meta, JSON_UNESCAPED_UNICODE); ?>;</script>
<?php endif; ?>
<script>
(function () {
  var adminMode = <?php echo !empty($dpm_site_admin_mode) ? 'true' : 'false'; ?>;
  var modal = document.getElementById('dpmStallSlotsModal');
  var grid = document.getElementById('dpmStallSlotsGrid');
  if (!modal || !grid) return;

  var occupiedSet = {};
  var slotPhotos = {};
  try {
    var occEl = document.getElementById('dpmSiteOccupiedSlots');
    var occList = occEl ? JSON.parse(occEl.textContent || '[]') : [];
    if (Array.isArray(occList)) {
      occList.forEach(function (n) { occupiedSet[n] = true; });
    }
  } catch (e) { /* keep empty */ }
  try {
    var photoEl = document.getElementById('dpmSiteSlotPhotos');
    var photoMap = photoEl ? JSON.parse(photoEl.textContent || '{}') : {};
    if (photoMap && typeof photoMap === 'object') slotPhotos = photoMap;
  } catch (e2) { /* keep empty */ }

  function preferredLang() {
    try { return localStorage.getItem('preferredLanguage') === 'ph' ? 'ph' : 'en'; } catch (e) { return 'en'; }
  }

  function slotStatusLabel(occupied) {
    var lang = preferredLang();
    if (occupied) {
      return lang === 'ph' ? 'Okupado' : 'Occupied';
    }
    return lang === 'ph' ? 'Bakante' : 'Available';
  }

  function sectionLabel(section) {
    var lang = preferredLang();
    var ph = {
      'Special Area Section': 'Seksyon ng Special Area',
      'Meat and Chicken Section': 'Seksyon ng Karne at Manok',
      'Fresh Fish Section': 'Seksyon ng Sariwang Isda',
      'Fruits and Vegetables Section': 'Seksyon ng Prutas at Gulay',
      'Groceries Sari-Sari Section': 'Seksyon ng Groceries / Sari-Sari',
      'Service Section': 'Seksyon ng Serbisyo',
      'Dry Goods Section': 'Seksyon ng Dry Goods',
      'Dried and Salted Fish Section': 'Seksyon ng Pinatuyo at Inasnan na Isda',
      'Eateries and Cooked Food Section': 'Seksyon ng Kainan at Lutong Pagkain'
    };
    if (lang === 'ph' && ph[section]) return ph[section];
    return section || (lang === 'ph' ? 'Mga stall slot' : 'Stall slots');
  }

  var detail = document.getElementById('dpmSlotDetail');
  var photoView = document.getElementById('dpmPhotoView');
  var photoViewImg = document.getElementById('dpmPhotoViewImg');

  function closePhotoView() {
    if (!photoView || photoView.hidden) return;
    photoView.hidden = true;
    if (photoViewImg) photoViewImg.removeAttribute('src');
  }

  function openPhotoView(src, alt) {
    if (!photoView || !photoViewImg || !src) return;
    photoViewImg.src = src;
    photoViewImg.alt = alt || 'Stall photo';
    photoView.hidden = false;
  }

  function closeSlotDetail() {
    closePhotoView();
    if (!detail || detail.hidden) return;
    detail.hidden = true;
  }

  function openSlotDetail(num, section, block) {
    if (!detail) return;
    var lang = preferredLang();
    var titleEl = document.getElementById('dpmSlotDetailTitle');
    var numEl = document.getElementById('dpmSlotDetailNumber');
    var secEl = document.getElementById('dpmSlotDetailSection');
    var blkEl = document.getElementById('dpmSlotDetailBlock');
    var applyEl = document.getElementById('dpmSlotDetailApply');
    if (titleEl) titleEl.textContent = lang === 'ph' ? 'Detalye ng stall' : 'Stall details';
    detail.querySelectorAll('[data-dpm-detail-label]').forEach(function (el) {
      var key = el.getAttribute('data-dpm-detail-label');
      if (key === 'slot') el.textContent = lang === 'ph' ? 'Numero ng slot' : 'Slot number';
      if (key === 'section') el.textContent = lang === 'ph' ? 'Seksyon' : 'Section';
      if (key === 'block') el.textContent = lang === 'ph' ? 'Bloke' : 'Block';
    });
    if (numEl) numEl.textContent = String(num);
    if (secEl) secEl.textContent = sectionLabel(section);
    if (blkEl) blkEl.textContent = block || '—';
    if (applyEl) {
      var params = new URLSearchParams();
      if (section) params.set('section', section);
      if (block) params.set('block', block);
      params.set('slot', String(num));
      applyEl.href = 'application.php?' + params.toString();
      applyEl.textContent = lang === 'ph' ? 'Mag-apply para sa slot na ito ngayon' : 'Apply for this slot now';
    }
    var photosEl = document.getElementById('dpmSlotDetailPhotos');
    if (photosEl) {
      var pics = slotPhotos[String(num)] || slotPhotos[num] || [];
      photosEl.innerHTML = '';
      if (pics.length) {
        pics.forEach(function (src) {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.setAttribute('aria-label', 'View stall ' + num + ' photo larger');
          var img = document.createElement('img');
          img.src = src;
          img.alt = 'Stall ' + num;
          btn.appendChild(img);
          btn.addEventListener('click', function () { openPhotoView(src, 'Stall ' + num); });
          photosEl.appendChild(btn);
        });
        photosEl.hidden = false;
      } else {
        photosEl.hidden = true;
      }
    }
    detail.hidden = false;
    var closeBtn = detail.querySelector('.dpm-slot-detail__close');
    if (closeBtn) closeBtn.focus();
  }

  function openStallSlotsModal(count, start, section, block) {
    var n = parseInt(count, 10) || 0;
    var slotStart = parseInt(start, 10) || 1;
    if (n < 1) return;
    var titleEl = document.getElementById('dpmStallSlotsTitle');
    if (titleEl) titleEl.textContent = sectionLabel(section);
    grid.innerHTML = '';
    for (var i = 0; i < n; i++) {
      var num = slotStart + i;
      var occupied = !!occupiedSet[num];
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'dpm-stall-slot-chip ' + (occupied ? 'is-occupied' : 'is-available');
      chip.textContent = String(num);
      chip.setAttribute('data-slot-number', String(num));
      chip.setAttribute('aria-label', 'Slot ' + num + ', ' + slotStatusLabel(occupied));
      if (adminMode) {
        chip.classList.add('is-admin');
        chip.addEventListener('click', function (slotNum, isOccupied) {
          return function () {
            var meta = (window.DPM_ADMIN_SLOT_META && window.DPM_ADMIN_SLOT_META[String(slotNum)]) || {};
            if (window.dpmAdminEditSlot) {
              window.dpmAdminEditSlot({
                slot: slotNum,
                section: section,
                block: block,
                status: meta.status || (isOccupied ? 'occupied' : 'available'),
                meta: meta
              });
            }
          };
        }(num, occupied));
      } else if (occupied) {
        chip.disabled = true;
        chip.setAttribute('aria-disabled', 'true');
      } else {
        chip.addEventListener('click', function (slotNum) {
          return function () { openSlotDetail(slotNum, section, block); };
        }(num));
      }
      grid.appendChild(chip);
    }
    modal.hidden = false;
    modal.classList.add('is-open');
    if (!adminMode) document.body.style.overflow = 'hidden';
    var closeBtn = modal.querySelector('.dpm-stall-slots-modal__close');
    if (closeBtn) closeBtn.focus();
  }

  function closeStallSlotsModal() {
    closeSlotDetail();
    modal.classList.remove('is-open');
    modal.hidden = true;
    if (!adminMode) document.body.style.overflow = '';
  }

  document.querySelectorAll('.dpm-stall-group').forEach(function (g) {
    function activate() {
      openStallSlotsModal(
        g.getAttribute('data-slot-count'),
        g.getAttribute('data-slot-start'),
        g.getAttribute('data-section') || '',
        g.getAttribute('data-block') || ''
      );
    }
    g.addEventListener('click', activate);
    g.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        activate();
      }
    });
  });

  modal.querySelectorAll('[data-dpm-stall-close]').forEach(function (el) {
    el.addEventListener('click', closeStallSlotsModal);
  });

  if (detail) {
    detail.querySelectorAll('[data-dpm-detail-close]').forEach(function (el) {
      el.addEventListener('click', closeSlotDetail);
    });
  }

  if (photoView) {
    photoView.querySelectorAll('[data-dpm-photo-close]').forEach(function (el) {
      el.addEventListener('click', closePhotoView);
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (photoView && !photoView.hidden) {
      closePhotoView();
      return;
    }
    if (detail && !detail.hidden) {
      closeSlotDetail();
      return;
    }
    if (modal.classList.contains('is-open')) closeStallSlotsModal();
  });
})();
</script>

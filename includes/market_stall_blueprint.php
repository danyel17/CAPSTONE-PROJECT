<?php
/**
 * Ground-floor stall map — sketch-style building plan.
 * Requires $conn. Sections & blocks match application.php dropdowns.
 *
 * Modes:
 *   - User mode (default): each blank box opens a popup showing Section, Block,
 *     Slot, Status and up to 3 photos (read-only).
 *   - Admin mode: set $blueprint_admin_mode = true before include. Boxes call
 *     adminOpenSlotDetail(box) (page-level JS in adminpanel.php) so the admin can
 *     edit status + upload 3 photos, which then reflect here on menu.php.
 */
if (!isset($conn) || !$conn) {
    return;
}

$blueprint_admin_mode = isset($blueprint_admin_mode) ? (bool) $blueprint_admin_mode : false;

/*
 * Column list — every entry maps to a real Section + Block from application.php.
 * 'db' is the market_slots.block_name used for status + photos.
 */
$fp_columns = [
    ['db' => 'FRUITS VEG',  'slots' => range(1, 34),  'cols' => 4, 'section' => 'Fruits and Vegetables Section', 'block' => 'Market Tienda Bldg'],
    ['db' => 'BOT BLOCK A', 'slots' => range(1, 110), 'cols' => 6, 'section' => 'Meat and Chicken Section',       'block' => 'Block A'],
    ['db' => 'BOT BLOCK B', 'slots' => range(1, 79),  'cols' => 5, 'section' => 'Fresh Fish Section',            'block' => 'Block B'],
    ['db' => 'BOT BLOCK C', 'slots' => range(1, 33),  'cols' => 4, 'section' => 'Dried and Salted Fish Section', 'block' => 'Block C'],
];

$fp_side_blocks = [
    ['db' => 'SPECIAL AREA SECTION TOP', 'slots' => range(1, 11), 'cols' => 4, 'section' => 'Groceries Sari-Sari Section',     'block' => 'Market Tienda Bldg'],
    ['db' => 'SERVICE SECTION',          'slots' => range(1, 22), 'cols' => 4, 'section' => 'Service Section',                 'block' => 'Market Tienda Bldg'],
    ['db' => 'BOT BLOCK E',              'slots' => range(1, 26), 'cols' => 4, 'section' => 'Eateries and Cooked Food Section', 'block' => 'BOT Block E'],
    ['db' => 'BOT BEHIND BLOCK B',       'slots' => range(1, 16), 'cols' => 3, 'section' => 'Fresh Fish Section',              'block' => 'BOT Behind Block B'],
    ['db' => 'BOT BEHIND BLOCK C',       'slots' => range(1, 7),  'cols' => 2, 'section' => 'Dried and Salted Fish Section',   'block' => 'BOT Behind Block C'],
    ['db' => 'DRY GOODS SECTION',        'slots' => range(1, 18), 'cols' => 3, 'section' => 'Dry Goods Section',               'block' => 'Market Tienda Bldg'],
];

/* Bulk-load every slot's status + photos in a single query. */
$mkt_slot_map = [];
$mkt_has_images = false;
$colCheck = @$conn->query("SHOW COLUMNS FROM market_slots LIKE 'image1'");
$mkt_has_images = ($colCheck && $colCheck->num_rows > 0);
$holderCheck = @$conn->query("SHOW COLUMNS FROM market_slots LIKE 'holder_name'");
$mkt_has_holder = ($holderCheck && $holderCheck->num_rows > 0);
$mkt_holder_cols = $mkt_has_holder ? ", holder_vendor_id, holder_name, holder_section, holder_block" : "";
$mkt_sql = $mkt_has_images
    ? "SELECT block_name, slot_number, status, image1, image2, image3{$mkt_holder_cols} FROM market_slots"
    : "SELECT block_name, slot_number, status{$mkt_holder_cols} FROM market_slots";
$mkt_res = @$conn->query($mkt_sql);
if ($mkt_res) {
    while ($row = $mkt_res->fetch_assoc()) {
        $mkt_slot_map[$row['block_name']][(int) $row['slot_number']] = $row;
    }
}

if (!function_exists('mkt_gf_slot')) {
    function mkt_gf_slot(array $map, $db, $slot) {
        return $map[$db][(int) $slot] ?? ['status' => 'available', 'image1' => '', 'image2' => '', 'image3' => ''];
    }
}

if (!function_exists('mkt_gf_render_box')) {
    function mkt_gf_render_box(array $map, array $col, $slotNum, $displayNum, $adminMode) {
        $data = mkt_gf_slot($map, $col['db'], $slotNum);
        $status = $data['status'] ?? 'available';
        if ($status === 'occupied') {
            $stateClass = 'is-occupied';
        } elseif ($status === 'second_floor') {
            $stateClass = 'is-second';
        } else {
            $stateClass = 'is-available';
        }
        $img1 = trim((string) ($data['image1'] ?? ''));
        $img2 = trim((string) ($data['image2'] ?? ''));
        $img3 = trim((string) ($data['image3'] ?? ''));
        $e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
        $onclick = $adminMode ? ' onclick="adminOpenSlotDetail(this)"' : '';
        $numClass = $slotNum >= 100 ? ' mkt-gf-box-num--sm' : '';
        $holderAttrs = '';
        if ($adminMode) {
            $holderAttrs = ' data-holder-id="' . $e($data['holder_vendor_id'] ?? '') . '"'
                . ' data-holder-name="' . $e($data['holder_name'] ?? '') . '"'
                . ' data-holder-section="' . $e($data['holder_section'] ?? '') . '"'
                . ' data-holder-block="' . $e($data['holder_block'] ?? '') . '"';
        }
        echo '<button type="button" class="mkt-gf-box ' . $stateClass . '"'
            . ' data-db="' . $e($col['db']) . '"'
            . ' data-section="' . $e($col['section'] ?? '') . '"'
            . ' data-block="' . $e($col['block'] ?? '') . '"'
            . ' data-slot="' . (int) $slotNum . '"'
            . ' data-display-num="' . (int) $displayNum . '"'
            . ' data-status="' . $e($status) . '"'
            . ' data-img1="' . $e($img1) . '"'
            . ' data-img2="' . $e($img2) . '"'
            . ' data-img3="' . $e($img3) . '"'
            . $holderAttrs
            . $onclick
            . ' aria-label="Stall ' . (int) $slotNum . '">'
            . '<span class="mkt-gf-box-num' . $numClass . '">' . (int) $slotNum . '</span>'
            . '</button>';
    }
}

if (!function_exists('mkt_gf_render_column')) {
    function mkt_gf_render_column(array $map, array $col, $adminMode, &$displayCounter) {
        $e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

        echo '<div class="mkt-gf-col">';
        echo '<div class="mkt-gf-col-head">';
        echo '<span class="mkt-gf-col-title">' . $e($col['block'] ?? 'Block') . '</span>';
        if (!empty($col['section'])) {
            echo '<span class="mkt-gf-col-section">' . $e($col['section']) . '</span>';
        }
        echo '</div>';
        echo '<div class="mkt-gf-boxes" style="--mkt-cols:' . (int) $col['cols'] . ';">';
        foreach ($col['slots'] as $slot) {
            mkt_gf_render_box($map, $col, $slot, $displayCounter, $adminMode);
            $displayCounter++;
        }
        echo '</div>';
        echo '</div>';
    }
}

$mkt_display_counter = 1;
?>
<div class="mkt-gf-plan">
  <div class="mkt-gf-legend">
    <span class="mkt-gf-legend-item"><span class="mkt-gf-swatch is-available"></span> Available</span>
    <span class="mkt-gf-legend-item"><span class="mkt-gf-swatch is-occupied"></span> Occupied</span>
    <span class="mkt-gf-legend-item"><span class="mkt-gf-swatch is-second"></span> Second Floor</span>
    <span class="mkt-gf-legend-hint">
      <i class="fas fa-hand-pointer"></i>
      <?php echo $blueprint_admin_mode ? 'Tap a box to edit status &amp; upload photos' : 'Tap any box to see its Section &amp; Block'; ?>
    </span>
  </div>

  <div class="mkt-gf-scroll">
    <div class="mkt-gf-building">
      <!-- Facade (styled like the real Daet Public Market building) -->
      <div class="mkt-gf-facade">
        <span class="mkt-gf-facade-word">DAET</span>
        <span class="mkt-gf-facade-peak"><span class="mkt-gf-facade-window"></span></span>
        <span class="mkt-gf-facade-word">PUBLIC</span>
        <span class="mkt-gf-facade-peak"><span class="mkt-gf-facade-window"></span></span>
        <span class="mkt-gf-facade-word">MARKET</span>
      </div>

      <!-- Interior: left ENT rail + satellite / smaller blocks (upper) -->
      <div class="mkt-gf-interior">
        <div class="mkt-gf-ent-rail">
          <span class="mkt-gf-ent mkt-gf-ent--v">ENT</span>
          <span class="mkt-gf-ent mkt-gf-ent--v">ENT</span>
        </div>

        <div class="mkt-gf-aisles">
          <?php $fp_side_last = count($fp_side_blocks) - 1; foreach ($fp_side_blocks as $si => $col) {
              mkt_gf_render_column($mkt_slot_map, $col, $blueprint_admin_mode, $mkt_display_counter);
              if ($si < $fp_side_last) {
                  echo '<div class="mkt-gf-walkway" aria-hidden="true">'
                      . '<span class="mkt-gf-arrow">&uarr;</span>'
                      . '<span class="mkt-gf-walk-label">WALKWAY</span>'
                      . '<span class="mkt-gf-arrow">&darr;</span>'
                      . '</div>';
              }
          } ?>
        </div>
      </div>

      <!-- Main 4 blocks relocated to sit in front of the bottom entrances -->
      <div class="mkt-gf-side-strip">
        <?php $fp_last = count($fp_columns) - 1; foreach ($fp_columns as $ci => $col) {
            mkt_gf_render_column($mkt_slot_map, $col, $blueprint_admin_mode, $mkt_display_counter);
            if ($ci < $fp_last) {
                echo '<div class="mkt-gf-walkway" aria-hidden="true">'
                    . '<span class="mkt-gf-arrow">&uarr;</span>'
                    . '<span class="mkt-gf-walk-label">WALKWAY</span>'
                    . '<span class="mkt-gf-arrow">&darr;</span>'
                    . '</div>';
            }
        } ?>
      </div>

      <!-- Bottom entrances (4 ENT) -->
      <div class="mkt-gf-bottom-ent">
        <span class="mkt-gf-ent">ENT</span>
        <span class="mkt-gf-ent">ENT</span>
        <span class="mkt-gf-ent">ENT</span>
        <span class="mkt-gf-ent">ENT</span>
      </div>
    </div>
  </div>
</div>

<?php if (!$blueprint_admin_mode): ?>
<!-- Stall detail popup (user, read-only) -->
<div class="mkt-gf-modal" id="mktGfModal" aria-hidden="true">
  <div class="mkt-gf-modal-backdrop" data-mkt-close></div>
  <div class="mkt-gf-modal-card" role="dialog" aria-modal="true" aria-labelledby="mktGfModalTitle">
    <button type="button" class="mkt-gf-modal-close" data-mkt-close aria-label="Close">&times;</button>
    <div class="mkt-gf-modal-icon"><i class="fas fa-store"></i></div>
    <h3 id="mktGfModalTitle">Stall Details</h3>
    <div class="mkt-gf-modal-rows">
      <div class="mkt-gf-modal-row">
        <span class="mkt-gf-modal-label">Section</span>
        <span class="mkt-gf-modal-value" id="mktGfSection">—</span>
      </div>
      <div class="mkt-gf-modal-row">
        <span class="mkt-gf-modal-label">Block</span>
        <span class="mkt-gf-modal-value" id="mktGfBlock">—</span>
      </div>
      <div class="mkt-gf-modal-row">
        <span class="mkt-gf-modal-label">Slot</span>
        <span class="mkt-gf-modal-value" id="mktGfSlot">—</span>
      </div>
      <div class="mkt-gf-modal-row">
        <span class="mkt-gf-modal-label">Status</span>
        <span class="mkt-gf-modal-badge" id="mktGfStatus">—</span>
      </div>
      <div class="mkt-gf-modal-row" id="mktGfOtherRow" hidden>
        <span class="mkt-gf-modal-label">Additional details</span>
        <span class="mkt-gf-modal-value" id="mktGfOther">—</span>
      </div>
    </div>
    <div class="mkt-gf-photos" id="mktGfPhotos"></div>
    <div class="mkt-gf-apply-wrap" id="mktGfApplyWrap" hidden>
      <a href="application.php" class="mkt-gf-apply-btn" id="mktGfApplyBtn">
        <i class="fas fa-file-signature" aria-hidden="true"></i> Apply Now for this slot
      </a>
    </div>
  </div>
</div>

<!-- Full-size photo viewer (stays on page) -->
<div class="mkt-gf-photo-lightbox" id="mktStallPhotoLightbox" aria-hidden="true">
  <div class="mkt-gf-photo-lightbox-backdrop" data-mkt-photo-close></div>
  <div class="mkt-gf-photo-lightbox-panel">
    <button type="button" class="mkt-gf-photo-lightbox-close" data-mkt-photo-close aria-label="Close">&times;</button>
    <img id="mktStallPhotoLightboxImg" src="" alt="Stall photo">
  </div>
</div>
<?php endif; ?>

<style>
  .mkt-gf-plan {
    --mkt-box: 30px;
    --bp-ink: #1f2a44;
    --bp-line: #3b4a6b;
    --bp-paper: #fbfcf7;
    --bp-grid: rgba(59, 74, 107, 0.12);
    --bp-accent: #15803d;
    width: 100%;
  }

  .mkt-gf-legend {
    display: flex; flex-wrap: wrap; align-items: center;
    gap: 0.5rem 1.1rem; margin-bottom: 1rem; font-size: 0.82rem; color: #475569;
  }
  .mkt-gf-legend-item { display: inline-flex; align-items: center; gap: 0.4rem; }
  .mkt-gf-swatch { width: 15px; height: 15px; border-radius: 4px; border: 1.5px solid #94a3b8; display: inline-block; }
  .mkt-gf-swatch.is-available { border-color: #22c55e; background: #f0fdf4; }
  .mkt-gf-swatch.is-occupied  { border-color: #ef4444; background: #fee2e2; }
  .mkt-gf-swatch.is-second    { border-color: #eab308; background: #fef9c3; }
  .mkt-gf-legend-hint { margin-left: auto; color: #6366f1; font-weight: 600; }
  .mkt-gf-legend-hint i { margin-right: 0.3rem; }

  .mkt-gf-scroll { overflow-x: auto; padding-bottom: 0.5rem; }

  .mkt-gf-building {
    min-width: 640px;
    border: 2px solid var(--bp-ink);
    border-radius: 2px;
    background-color: var(--bp-paper);
    background-image:
      linear-gradient(var(--bp-grid) 1px, transparent 1px),
      linear-gradient(90deg, var(--bp-grid) 1px, transparent 1px);
    background-size: 22px 22px;
    box-shadow: inset 0 0 0 4px var(--bp-paper), inset 0 0 0 6px var(--bp-ink);
    padding: 0;
    overflow: hidden;
    font-family: "Courier New", ui-monospace, monospace;
  }

  /* ── Title band (orange market header) ──────────────── */
  .mkt-gf-facade {
    display: flex; align-items: center; justify-content: center;
    gap: clamp(0.6rem, 3vw, 2.4rem);
    background: linear-gradient(180deg, #ff7a18 0%, #f0680a 100%);
    padding: 1.1rem 1rem 0.85rem;
    border-bottom: 3px solid var(--bp-ink);
    position: relative;
  }
  .mkt-gf-facade-word {
    color: #ffffff; font-weight: 800; letter-spacing: 0.32em;
    font-size: clamp(0.9rem, 3.4vw, 1.55rem);
    text-shadow: 0 2px 3px rgba(0,0,0,0.35); line-height: 1;
    font-family: "Courier New", ui-monospace, monospace;
  }
  .mkt-gf-facade-peak {
    position: relative;
    width: 0; height: 0;
    border-left: 18px solid transparent;
    border-right: 18px solid transparent;
    border-bottom: 26px solid #ffa35c;
    filter: drop-shadow(0 -2px 1px rgba(0,0,0,0.15));
  }
  .mkt-gf-facade-window {
    position: absolute; top: 11px; left: 50%; transform: translateX(-50%);
    width: 11px; height: 11px; border-radius: 50%;
    background: #ffffff; border: 2px solid #1d4ed8;
  }

  /* ── Interior (one connected walkway floor) ─────────── */
  .mkt-gf-interior {
    display: flex; align-items: stretch; padding: 1rem; gap: 0.6rem;
    background: repeating-linear-gradient(45deg, rgba(59, 74, 107, 0.09) 0 5px, transparent 5px 11px);
  }

  .mkt-gf-ent-rail {
    display: flex; flex-direction: column; justify-content: space-around; align-items: center;
    gap: 0.5rem; padding-right: 0.3rem;
  }
  .mkt-gf-ent {
    display: inline-flex; align-items: center; gap: 0.3rem;
    background: transparent; color: var(--bp-accent);
    border: 1.5px solid var(--bp-accent);
    font-weight: 800; font-size: 0.68rem;
    letter-spacing: 0.14em; padding: 0.26rem 0.55rem; border-radius: 2px;
    font-family: "Courier New", ui-monospace, monospace;
  }
  .mkt-gf-ent--v { writing-mode: vertical-rl; transform: rotate(180deg); padding: 0.55rem 0.26rem; }

  .mkt-gf-aisles {
    display: flex; flex-wrap: wrap; gap: 0.6rem; flex: 1; justify-content: center;
  }

  /* ── Walkways / circulation paths (no divider lines) ── */
  .mkt-gf-walkway {
    align-self: stretch;
    min-width: 26px;
    display: flex; flex-direction: column; align-items: center; justify-content: space-between;
    padding: 0.35rem 0;
  }
  .mkt-gf-arrow {
    color: var(--bp-line); font-weight: 800; font-size: 0.95rem; line-height: 1;
    font-family: "Courier New", ui-monospace, monospace;
  }
  .mkt-gf-walk-label {
    writing-mode: vertical-rl; transform: rotate(180deg);
    font-size: 0.72rem; letter-spacing: 0.16em; color: #1e293b; font-weight: 700;
    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  }
  .mkt-gf-col {
    background: rgba(255, 255, 255, 0.55); border: 1.5px solid var(--bp-line); border-radius: 2px; padding: 0.45rem;
  }
  .mkt-gf-col-head {
    margin-bottom: 0.45rem; padding-bottom: 0.4rem; border-bottom: 1px solid var(--bp-line); text-align: center;
  }
  .mkt-gf-col-title {
    display: block; font-size: 0.72rem; font-weight: 800; color: var(--bp-ink);
    letter-spacing: 0.06em; line-height: 1.25; text-transform: uppercase;
    font-family: "Courier New", ui-monospace, monospace;
  }
  .mkt-gf-col-section {
    display: block; font-size: 0.62rem; font-weight: 600; color: #56617e;
    margin-top: 0.15rem; line-height: 1.2;
  }
  .mkt-gf-boxes {
    display: grid;
    grid-template-columns: repeat(var(--mkt-cols, 4), var(--mkt-box));
    gap: 4px;
  }

  .mkt-gf-box {
    width: var(--mkt-box); height: var(--mkt-box);
    border: 1.5px solid var(--bp-line); border-radius: 2px;
    background: rgba(255, 255, 255, 0.72); cursor: pointer; padding: 0;
    display: flex; align-items: center; justify-content: center;
    transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
  }
  .mkt-gf-box-num {
    font-size: 0.62rem; font-weight: 700; line-height: 1; color: var(--bp-ink);
    pointer-events: none; user-select: none;
    font-family: "Courier New", ui-monospace, monospace;
  }
  .mkt-gf-box-num--sm { font-size: 0.5rem; }
  .mkt-gf-box.is-available { border-color: #1f7a46; background: rgba(34, 197, 94, 0.1); }
  .mkt-gf-box.is-available .mkt-gf-box-num { color: #15803d; }
  .mkt-gf-box.is-occupied  { border-color: #b1462f; background: rgba(239, 68, 68, 0.12); }
  .mkt-gf-box.is-occupied .mkt-gf-box-num { color: #b91c1c; }
  .mkt-gf-box.is-second    { border-color: #a67c14; background: rgba(234, 179, 8, 0.14); }
  .mkt-gf-box.is-second .mkt-gf-box-num { color: #a16207; }
  .mkt-gf-box:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(15,23,42,0.18); filter: brightness(1.03); }
  .mkt-gf-box:focus-visible { outline: 3px solid var(--bp-accent); outline-offset: 1px; }

  .mkt-gf-side-strip {
    display: flex; flex-wrap: wrap; gap: 0.6rem; justify-content: center;
    padding: 1rem; margin-top: 0;
    background: repeating-linear-gradient(45deg, rgba(59, 74, 107, 0.09) 0 5px, transparent 5px 11px);
  }

  .mkt-gf-bottom-ent {
    display: flex; flex-wrap: wrap; gap: 0.8rem 1.4rem; justify-content: space-around; align-items: flex-start;
    background: repeating-linear-gradient(45deg, rgba(59, 74, 107, 0.09) 0 5px, transparent 5px 11px);
    padding: 0.8rem 1rem;
  }
  .mkt-gf-bottom-ent-item {
    display: flex; flex-direction: column; align-items: center; gap: 0.32rem;
  }
  .mkt-gf-bottom-block {
    font-family: "Courier New", ui-monospace, monospace;
    font-size: 0.6rem; font-weight: 800; letter-spacing: 0.08em;
    color: var(--bp-ink); text-transform: uppercase; text-align: center; line-height: 1.2;
  }

  /* ── User popup ─────────────────────────────────────── */
  .mkt-gf-modal { position: fixed; inset: 0; z-index: 3000; display: none; }
  .mkt-gf-modal.is-open { display: block; }
  .mkt-gf-modal-backdrop { position: absolute; inset: 0; background: rgba(15,23,42,0.55); }
  .mkt-gf-modal-card {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    width: min(92vw, 380px); max-height: 88vh; overflow-y: auto; background: #fff; border-radius: 16px;
    padding: 1.6rem 1.4rem 1.4rem; text-align: center;
    box-shadow: 0 24px 60px rgba(0,0,0,0.3); animation: mktGfPop 0.2s ease;
  }
  @keyframes mktGfPop { from { opacity: 0; transform: translate(-50%, -46%) scale(0.96); } to { opacity: 1; transform: translate(-50%, -50%) scale(1); } }
  .mkt-gf-modal-close {
    position: absolute; top: 0.6rem; right: 0.8rem; background: none; border: none;
    font-size: 1.7rem; line-height: 1; color: #94a3b8; cursor: pointer;
  }
  .mkt-gf-modal-close:hover { color: #475569; }
  .mkt-gf-modal-icon {
    width: 54px; height: 54px; margin: 0 auto 0.7rem; border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
  }
  .mkt-gf-modal-card h3 { margin: 0 0 1rem; font-size: 1.15rem; color: #1e293b; }
  .mkt-gf-modal-rows { text-align: left; display: flex; flex-direction: column; gap: 0.55rem; }
  .mkt-gf-modal-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; }
  .mkt-gf-modal-row:last-child { border-bottom: none; }
  .mkt-gf-modal-label { color: #64748b; font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
  .mkt-gf-modal-value { color: #1e293b; font-weight: 700; text-align: right; }
  .mkt-gf-modal-value.is-second-floor {
    background: #fef9c3; color: #a16207; padding: 0.2rem 0.7rem; border-radius: 999px; font-size: 0.8rem;
    border: 1px solid #eab308;
  }
  .mkt-gf-modal-badge { font-weight: 700; padding: 0.2rem 0.7rem; border-radius: 999px; font-size: 0.8rem; }
  .mkt-gf-modal-badge.is-available { background: #dcfce7; color: #15803d; }
  .mkt-gf-modal-badge.is-occupied  { background: #fee2e2; color: #b91c1c; }
  .mkt-gf-modal-badge.is-second    { background: #fef9c3; color: #a16207; }
  .mkt-gf-photos { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; justify-content: center; }
  .mkt-gf-photos a { display: block; }
  .mkt-gf-photos img { width: 96px; height: 96px; object-fit: cover; border-radius: 10px; border: 1px solid #e2e8f0; cursor: zoom-in; }
  .mkt-gf-photos .mkt-gf-photo-thumb {
    display: block; padding: 0; border: none; background: none; cursor: zoom-in; border-radius: 10px;
  }
  .mkt-gf-photos .mkt-gf-photo-thumb img { display: block; }
  .mkt-gf-photos .mkt-gf-nophoto { color: #94a3b8; font-size: 0.85rem; margin: 0; }

  .mkt-gf-apply-wrap { margin-top: 1rem; }
  .mkt-gf-apply-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem;
    width: 100%; padding: 0.75rem 1rem; border: none; border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;
    font-weight: 700; font-size: 0.92rem; text-decoration: none; cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  .mkt-gf-apply-btn:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35); }

  .mkt-gf-photo-lightbox {
    position: fixed; inset: 0; z-index: 3200; display: none;
  }
  .mkt-gf-photo-lightbox.is-open { display: block; }
  .mkt-gf-photo-lightbox-backdrop {
    position: absolute; inset: 0; background: rgba(15, 23, 42, 0.85);
  }
  .mkt-gf-photo-lightbox-panel {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    max-width: min(92vw, 900px); max-height: 88vh; padding: 0.5rem;
  }
  .mkt-gf-photo-lightbox-panel img {
    display: block; max-width: min(92vw, 900px); max-height: 85vh;
    width: auto; height: auto; border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,0.45);
  }
  .mkt-gf-photo-lightbox-close {
    position: absolute; top: -0.25rem; right: -0.25rem; z-index: 2;
    width: 36px; height: 36px; border: none; border-radius: 50%;
    background: rgba(255,255,255,0.95); color: #334155; font-size: 1.5rem; line-height: 1;
    cursor: pointer; box-shadow: 0 2px 10px rgba(0,0,0,0.2);
  }
  .mkt-gf-photo-lightbox-close:hover { background: #fff; }

  /* Dark mode → classic blue-line blueprint */
  body.darkmode .mkt-gf-building {
    background-color: #0b2038;
    background-image:
      linear-gradient(rgba(147, 197, 253, 0.16) 1px, transparent 1px),
      linear-gradient(90deg, rgba(147, 197, 253, 0.16) 1px, transparent 1px);
    border-color: #93c5fd;
    box-shadow: inset 0 0 0 4px #0b2038, inset 0 0 0 6px #93c5fd;
  }
  body.darkmode .mkt-gf-facade { border-bottom-color: #0b2038; }
  body.darkmode .mkt-gf-facade-word { color: #ffffff; }
  body.darkmode .mkt-gf-interior,
  body.darkmode .mkt-gf-side-strip {
    background: repeating-linear-gradient(45deg, rgba(147, 197, 253, 0.12) 0 5px, transparent 5px 11px);
  }
  body.darkmode .mkt-gf-ent { color: #6ee7b7; border-color: #6ee7b7; }
  body.darkmode .mkt-gf-col { background: rgba(147, 197, 253, 0.06); border-color: #3b6ea5; }
  body.darkmode .mkt-gf-col-head { border-bottom-color: #3b6ea5; }
  body.darkmode .mkt-gf-col-title { color: #e0f2fe; }
  body.darkmode .mkt-gf-col-section { color: #93c5fd; }
  body.darkmode .mkt-gf-box { background: rgba(147, 197, 253, 0.05); border-color: #3b6ea5; }
  body.darkmode .mkt-gf-box-num { color: #e0f2fe; }
  body.darkmode .mkt-gf-legend { color: #cbd5e1; }
  body.darkmode .mkt-gf-arrow { color: #93c5fd; }
  body.darkmode .mkt-gf-walk-label { color: #93c5fd; }
  body.darkmode .mkt-gf-bottom-ent {
    background: repeating-linear-gradient(45deg, rgba(147, 197, 253, 0.12) 0 5px, transparent 5px 11px);
  }
  body.darkmode .mkt-gf-bottom-block { color: #e0f2fe; }
  body.darkmode .mkt-gf-modal-card { background: #1e293b; }
  body.darkmode .mkt-gf-modal-card h3 { color: #f1f5f9; }
  body.darkmode .mkt-gf-modal-value { color: #f1f5f9; }

  @media (max-width: 640px) {
    .mkt-gf-plan { --mkt-box: 26px; }
    .mkt-gf-building { min-width: 560px; }
  }
</style>

<?php if (!$blueprint_admin_mode): ?>
<script>
(function () {
  var modal = document.getElementById('mktGfModal');
  if (!modal || modal.dataset.mktBound === '1') return;
  modal.dataset.mktBound = '1';

  var elSection = document.getElementById('mktGfSection');
  var elBlock = document.getElementById('mktGfBlock');
  var elSlot = document.getElementById('mktGfSlot');
  var elStatus = document.getElementById('mktGfStatus');
  var elOtherRow = document.getElementById('mktGfOtherRow');
  var elOther = document.getElementById('mktGfOther');
  var elPhotos = document.getElementById('mktGfPhotos');
  var elApplyWrap = document.getElementById('mktGfApplyWrap');
  var elApplyBtn = document.getElementById('mktGfApplyBtn');
  var photoLightbox = document.getElementById('mktStallPhotoLightbox');
  var photoLightboxImg = document.getElementById('mktStallPhotoLightboxImg');

  function statusMeta(status) {
    if (status === 'occupied') return { cls: 'is-occupied', label: 'Occupied', other: '' };
    if (status === 'second_floor') return { cls: 'is-available', label: 'Available', other: 'Second Floor' };
    return { cls: 'is-available', label: 'Available', other: '' };
  }

  function openPhotoLightbox(src) {
    if (!photoLightbox || !photoLightboxImg || !src) return;
    photoLightboxImg.src = src;
    photoLightbox.classList.add('is-open');
    photoLightbox.setAttribute('aria-hidden', 'false');
  }

  function closePhotoLightbox() {
    if (!photoLightbox) return;
    photoLightbox.classList.remove('is-open');
    photoLightbox.setAttribute('aria-hidden', 'true');
    if (photoLightboxImg) photoLightboxImg.src = '';
  }

  function normUrl(p) {
    if (!p) return '';
    var u = String(p).replace(/\\/g, '/');
    if (/^https?:\/\//i.test(u)) return u;
    return u.replace(/^\/+/, '');
  }

  function openModal(box) {
    var slotNum = parseInt(box.getAttribute('data-slot'), 10) || parseInt(box.getAttribute('data-display-num'), 10) || 0;
    var meta = statusMeta(box.getAttribute('data-status') || 'available');
    elSection.textContent = box.getAttribute('data-section') || '—';
    elBlock.textContent = box.getAttribute('data-block') || '—';
    elSlot.textContent = 'Stall ' + slotNum;
    elStatus.textContent = meta.label;
    elStatus.className = 'mkt-gf-modal-badge ' + meta.cls;
    if (meta.other) {
      elOtherRow.hidden = false;
      elOther.textContent = meta.other;
      elOther.className = 'mkt-gf-modal-value' + (box.getAttribute('data-status') === 'second_floor' ? ' is-second-floor' : '');
    } else {
      elOtherRow.hidden = true;
      elOther.textContent = '—';
      elOther.className = 'mkt-gf-modal-value';
    }

    var imgs = [box.getAttribute('data-img1'), box.getAttribute('data-img2'), box.getAttribute('data-img3')]
      .map(normUrl).filter(Boolean);
    if (imgs.length) {
      elPhotos.innerHTML = imgs.map(function (src) {
        var s = src.replace(/"/g, '&quot;');
        return '<button type="button" class="mkt-gf-photo-thumb" data-full-src="' + s + '"><img src="' + s + '" alt="Stall photo"></button>';
      }).join('');
    } else {
      elPhotos.innerHTML = '<p class="mkt-gf-nophoto">No photos uploaded for this stall yet.</p>';
    }

    var stallStatus = box.getAttribute('data-status') || 'available';
    var stallSection = box.getAttribute('data-section') || '';
    var stallBlock = box.getAttribute('data-block') || '';
    if (elApplyWrap && elApplyBtn) {
      if (stallStatus === 'occupied') {
        elApplyWrap.hidden = true;
      } else {
        elApplyWrap.hidden = false;
        var q = new URLSearchParams();
        if (stallSection) q.set('section', stallSection);
        if (stallBlock) q.set('block', stallBlock);
        if (slotNum) q.set('slot', String(slotNum));
        elApplyBtn.href = 'application.php?' + q.toString();
      }
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    closePhotoLightbox();
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  document.addEventListener('click', function (e) {
    var thumb = e.target.closest('.mkt-gf-photo-thumb');
    if (thumb) {
      e.preventDefault();
      openPhotoLightbox(thumb.getAttribute('data-full-src') || thumb.querySelector('img')?.src || '');
      return;
    }
    if (e.target.closest('[data-mkt-photo-close]')) {
      closePhotoLightbox();
      return;
    }
    var box = e.target.closest('#stallMapPanel .mkt-gf-box');
    if (box) { openModal(box); return; }
    if (e.target.closest('[data-mkt-close]')) { closeModal(); }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (photoLightbox && photoLightbox.classList.contains('is-open')) {
      closePhotoLightbox();
    } else if (modal.classList.contains('is-open')) {
      closeModal();
    }
  });

  window.openMktStallPhotoLightbox = openPhotoLightbox;
  window.closeMktStallPhotoLightbox = closePhotoLightbox;
})();
</script>
<?php endif; ?>

<?php
/**
 * Daet Elevated Town Plaza stall map — sketch-style (matches market_stall_blueprint).
 * Requires $conn. Block: "Daet Elevated Plaza" (application.php).
 */
if (!isset($conn) || !$conn) {
    return;
}

$elevated_admin_mode = isset($elevated_admin_mode) ? (bool) $elevated_admin_mode : false;

/* Stall columns — section labels vary; all slots use db block "Daet Elevated Plaza". */
$ev_columns = [
    ['db' => 'Daet Elevated Plaza', 'slots' => range(1, 17),  'cols' => 4, 'section' => 'Eateries and Cooked Food Section', 'block' => 'Daet Elevated Plaza'],
    ['db' => 'Daet Elevated Plaza', 'slots' => range(18, 36), 'cols' => 4, 'section' => 'Service Section',                 'block' => 'Daet Elevated Plaza'],
    ['db' => 'Daet Elevated Plaza', 'slots' => range(37, 57), 'cols' => 4, 'section' => 'Groceries Sari-Sari Section',     'block' => 'Daet Elevated Plaza'],
    ['db' => 'Daet Elevated Plaza', 'slots' => range(58, 80), 'cols' => 5, 'section' => 'Dry Goods Section',               'block' => 'Daet Elevated Plaza'],
];

$mkt_ev_slot_map = [];
$ev_colCheck = @$conn->query("SHOW COLUMNS FROM market_slots LIKE 'image1'");
$ev_has_images = ($ev_colCheck && $ev_colCheck->num_rows > 0);
$ev_holderCheck = @$conn->query("SHOW COLUMNS FROM market_slots LIKE 'holder_name'");
$ev_has_holder = ($ev_holderCheck && $ev_holderCheck->num_rows > 0);
$ev_holder_cols = $ev_has_holder ? ", holder_vendor_id, holder_name, holder_section, holder_block" : "";
$ev_sql = $ev_has_images
    ? "SELECT block_name, slot_number, status, image1, image2, image3{$ev_holder_cols} FROM market_slots WHERE block_name = 'Daet Elevated Plaza'"
    : "SELECT block_name, slot_number, status{$ev_holder_cols} FROM market_slots WHERE block_name = 'Daet Elevated Plaza'";
$ev_res = @$conn->query($ev_sql);
if ($ev_res) {
    while ($row = $ev_res->fetch_assoc()) {
        $mkt_ev_slot_map[$row['block_name']][(int) $row['slot_number']] = $row;
    }
}

if (!function_exists('mkt_ev_slot')) {
    function mkt_ev_slot(array $map, $db, $slot) {
        return $map[$db][(int) $slot] ?? ['status' => 'available', 'image1' => '', 'image2' => '', 'image3' => ''];
    }
}

if (!function_exists('mkt_ev_render_box')) {
    function mkt_ev_render_box(array $map, array $col, $slotNum, $displayNum, $adminMode) {
        $data = mkt_ev_slot($map, $col['db'], $slotNum);
        $status = $data['status'] ?? 'available';
        if ($status === 'occupied') {
            $stateClass = 'is-occupied';
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

if (!function_exists('mkt_ev_render_column')) {
    function mkt_ev_render_column(array $map, array $col, $adminMode, &$displayCounter) {
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
            mkt_ev_render_box($map, $col, $slot, $displayCounter, $adminMode);
            $displayCounter++;
        }
        echo '</div>';
        echo '</div>';
    }
}

$mkt_ev_display_counter = 1;
?>
<div class="mkt-ev-plan mkt-gf-plan">
  <div class="mkt-gf-legend">
    <span class="mkt-gf-legend-item"><span class="mkt-gf-swatch is-available"></span> Available</span>
    <span class="mkt-gf-legend-item"><span class="mkt-gf-swatch is-occupied"></span> Occupied</span>
    <span class="mkt-gf-legend-hint">
      <i class="fas fa-hand-pointer"></i>
      <?php echo $elevated_admin_mode ? 'Tap a box to edit status &amp; upload photos' : 'Tap any box to see its Section &amp; Block'; ?>
    </span>
  </div>

  <div class="mkt-gf-scroll">
    <div class="mkt-gf-building mkt-ev-building">
      <!-- Indigo facade -->
      <div class="mkt-ev-facade">
        <span class="mkt-ev-facade-word">DAET</span>
        <span class="mkt-ev-facade-word">ELEVATED</span>
        <span class="mkt-ev-facade-word">TOWN PLAZA</span>
      </div>

      <!-- Interior: left ENT + stalls + right ENT only -->
      <div class="mkt-ev-interior">
        <div class="mkt-ev-ent-rail mkt-ev-ent-rail--left">
          <span class="mkt-ev-ent mkt-ev-ent--v">ENT</span>
        </div>

        <div class="mkt-ev-aisles">
          <?php $ev_last = count($ev_columns) - 1; foreach ($ev_columns as $ci => $col) {
              mkt_ev_render_column($mkt_ev_slot_map, $col, $elevated_admin_mode, $mkt_ev_display_counter);
              if ($ci < $ev_last) {
                  echo '<div class="mkt-gf-walkway" aria-hidden="true">'
                      . '<span class="mkt-gf-arrow">&uarr;</span>'
                      . '<span class="mkt-gf-walk-label">WALKWAY</span>'
                      . '<span class="mkt-gf-arrow">&darr;</span>'
                      . '</div>';
              }
          } ?>
        </div>

        <div class="mkt-ev-ent-rail mkt-ev-ent-rail--right">
          <span class="mkt-ev-ent mkt-ev-ent--v">ENT</span>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (!$elevated_admin_mode): ?>
<div class="mkt-gf-modal" id="mktEvModal" aria-hidden="true">
  <div class="mkt-gf-modal-backdrop" data-mkt-ev-close></div>
  <div class="mkt-gf-modal-card" role="dialog" aria-modal="true" aria-labelledby="mktEvModalTitle">
    <button type="button" class="mkt-gf-modal-close" data-mkt-ev-close aria-label="Close">&times;</button>
    <div class="mkt-gf-modal-icon mkt-ev-modal-icon"><i class="fas fa-building"></i></div>
    <h3 id="mktEvModalTitle">Stall Details</h3>
    <div class="mkt-gf-modal-rows">
      <div class="mkt-gf-modal-row">
        <span class="mkt-gf-modal-label">Section</span>
        <span class="mkt-gf-modal-value" id="mktEvSection">—</span>
      </div>
      <div class="mkt-gf-modal-row">
        <span class="mkt-gf-modal-label">Block</span>
        <span class="mkt-gf-modal-value" id="mktEvBlock">—</span>
      </div>
      <div class="mkt-gf-modal-row">
        <span class="mkt-gf-modal-label">Stall</span>
        <span class="mkt-gf-modal-value" id="mktEvSlot">—</span>
      </div>
      <div class="mkt-gf-modal-row">
        <span class="mkt-gf-modal-label">Status</span>
        <span class="mkt-gf-modal-badge" id="mktEvStatus">—</span>
      </div>
    </div>
    <div class="mkt-gf-photos" id="mktEvPhotos"></div>
    <div class="mkt-gf-apply-wrap" id="mktEvApplyWrap" hidden>
      <a href="application.php" class="mkt-gf-apply-btn" id="mktEvApplyBtn">
        <i class="fas fa-file-signature" aria-hidden="true"></i> Apply Now for this slot
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<style>
  /* Shared stall grid (also in market_stall_blueprint — duplicated so elevated works standalone) */
  .mkt-ev-plan.mkt-gf-plan {
    --mkt-box: 30px;
    --bp-ink: #1f2a44;
    --bp-line: #3b4a6b;
    --bp-paper: #fbfcf7;
    --bp-grid: rgba(59, 74, 107, 0.12);
    --bp-accent: #15803d;
    width: 100%;
  }
  .mkt-ev-plan .mkt-gf-legend {
    display: flex; flex-wrap: wrap; align-items: center;
    gap: 0.5rem 1.1rem; margin-bottom: 1rem; font-size: 0.82rem; color: #475569;
  }
  .mkt-ev-plan .mkt-gf-legend-item { display: inline-flex; align-items: center; gap: 0.4rem; }
  .mkt-ev-plan .mkt-gf-swatch { width: 15px; height: 15px; border-radius: 4px; border: 1.5px solid #94a3b8; display: inline-block; }
  .mkt-ev-plan .mkt-gf-swatch.is-available { border-color: #22c55e; background: #f0fdf4; }
  .mkt-ev-plan .mkt-gf-swatch.is-occupied  { border-color: #ef4444; background: #fee2e2; }
  .mkt-ev-plan .mkt-gf-swatch.is-second    { border-color: #eab308; background: #fef9c3; }
  .mkt-ev-plan .mkt-gf-legend-hint { margin-left: auto; color: #4f46e5; font-weight: 600; }
  .mkt-ev-plan .mkt-gf-scroll { overflow-x: auto; padding-bottom: 0.5rem; }
  .mkt-ev-plan .mkt-gf-building {
    border: 2px solid var(--bp-ink); border-radius: 2px;
    background-color: var(--bp-paper);
    background-image:
      linear-gradient(var(--bp-grid) 1px, transparent 1px),
      linear-gradient(90deg, var(--bp-grid) 1px, transparent 1px);
    background-size: 22px 22px;
    box-shadow: inset 0 0 0 4px var(--bp-paper), inset 0 0 0 6px var(--bp-ink);
    padding: 0; overflow: hidden;
    font-family: "Courier New", ui-monospace, monospace;
  }
  .mkt-ev-plan .mkt-gf-col {
    background: rgba(255, 255, 255, 0.55); border: 1.5px solid var(--bp-line); border-radius: 2px; padding: 0.45rem;
  }
  .mkt-ev-plan .mkt-gf-col-head {
    margin-bottom: 0.45rem; padding-bottom: 0.4rem; border-bottom: 1px solid var(--bp-line); text-align: center;
  }
  .mkt-ev-plan .mkt-gf-col-title {
    display: block; font-size: 0.72rem; font-weight: 800; color: var(--bp-ink);
    letter-spacing: 0.06em; line-height: 1.25; text-transform: uppercase;
    font-family: "Courier New", ui-monospace, monospace;
  }
  .mkt-ev-plan .mkt-gf-col-section {
    display: block; font-size: 0.62rem; font-weight: 600; color: #56617e;
    margin-top: 0.15rem; line-height: 1.2;
  }
  .mkt-ev-plan .mkt-gf-boxes {
    display: grid; grid-template-columns: repeat(var(--mkt-cols, 4), var(--mkt-box)); gap: 4px;
  }
  .mkt-ev-plan .mkt-gf-box {
    width: var(--mkt-box); height: var(--mkt-box);
    border: 1.5px solid var(--bp-line); border-radius: 2px; background: rgba(255, 255, 255, 0.72); cursor: pointer; padding: 0;
    display: flex; align-items: center; justify-content: center;
    transition: transform 0.12s ease, box-shadow 0.12s ease;
  }
  .mkt-ev-plan .mkt-gf-box-num { font-size: 0.62rem; font-weight: 700; line-height: 1; color: var(--bp-ink); pointer-events: none; font-family: "Courier New", ui-monospace, monospace; }
  .mkt-ev-plan .mkt-gf-box-num--sm { font-size: 0.5rem; }
  .mkt-ev-plan .mkt-gf-box.is-available { border-color: #1f7a46; background: rgba(34, 197, 94, 0.1); }
  .mkt-ev-plan .mkt-gf-box.is-available .mkt-gf-box-num { color: #15803d; }
  .mkt-ev-plan .mkt-gf-box.is-occupied  { border-color: #b1462f; background: rgba(239, 68, 68, 0.12); }
  .mkt-ev-plan .mkt-gf-box.is-occupied .mkt-gf-box-num { color: #b91c1c; }
  .mkt-ev-plan .mkt-gf-box.is-second    { border-color: #a67c14; background: rgba(234, 179, 8, 0.14); }
  .mkt-ev-plan .mkt-gf-box.is-second .mkt-gf-box-num { color: #a16207; }
  .mkt-ev-plan .mkt-gf-box:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(15,23,42,0.18); }

  .mkt-ev-building { min-width: 520px; }

  /* Indigo title band */
  .mkt-ev-facade {
    display: flex; align-items: center; justify-content: center;
    gap: clamp(0.5rem, 2.5vw, 1.8rem);
    background: linear-gradient(180deg, #6366f1 0%, #4f46e5 100%);
    padding: 1.1rem 1rem 0.85rem;
    border-bottom: 3px solid var(--bp-ink);
  }
  .mkt-ev-facade-word {
    color: #ffffff; font-weight: 800; letter-spacing: 0.28em;
    font-size: clamp(0.75rem, 2.8vw, 1.35rem);
    text-shadow: 0 2px 3px rgba(0,0,0,0.35); line-height: 1;
    font-family: "Courier New", ui-monospace, monospace;
  }

  /* Left + right ENT on one connected walkway floor */
  .mkt-ev-interior {
    display: flex; align-items: stretch; padding: 1rem; gap: 0.6rem;
    background: repeating-linear-gradient(45deg, rgba(59, 74, 107, 0.09) 0 5px, transparent 5px 11px);
  }
  .mkt-ev-ent-rail {
    display: flex; flex-direction: column; justify-content: center; align-items: center;
    min-width: 2.2rem;
  }
  .mkt-ev-ent-rail--left { padding-right: 0.3rem; }
  .mkt-ev-ent-rail--right { padding-left: 0.3rem; }
  .mkt-ev-ent {
    display: inline-flex; align-items: center; gap: 0.25rem;
    background: transparent; color: var(--bp-accent);
    border: 1.5px solid var(--bp-accent);
    font-weight: 800; font-size: 0.65rem;
    letter-spacing: 0.14em; padding: 0.32rem 0.5rem; border-radius: 2px;
    font-family: "Courier New", ui-monospace, monospace;
  }
  .mkt-ev-ent--v { writing-mode: vertical-rl; transform: rotate(180deg); padding: 0.55rem 0.3rem; }
  .mkt-ev-ent i { font-size: 0.55rem; }

  .mkt-ev-aisles {
    display: flex; flex-wrap: wrap; gap: 0.6rem; flex: 1; justify-content: center;
  }

  /* ── Walkways / circulation paths (no divider lines) ── */
  .mkt-ev-plan .mkt-gf-walkway {
    align-self: stretch;
    min-width: 26px;
    display: flex; flex-direction: column; align-items: center; justify-content: space-between;
    padding: 0.35rem 0;
  }
  .mkt-ev-plan .mkt-gf-arrow {
    color: var(--bp-line); font-weight: 800; font-size: 0.95rem; line-height: 1;
    font-family: "Courier New", ui-monospace, monospace;
  }
  .mkt-ev-plan .mkt-gf-walk-label {
    writing-mode: vertical-rl; transform: rotate(180deg);
    font-size: 0.72rem; letter-spacing: 0.16em; color: #1e293b; font-weight: 700;
    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  }
  body.darkmode .mkt-ev-interior {
    background: repeating-linear-gradient(45deg, rgba(147, 197, 253, 0.12) 0 5px, transparent 5px 11px);
  }
  body.darkmode .mkt-ev-plan .mkt-gf-arrow { color: #93c5fd; }
  body.darkmode .mkt-ev-plan .mkt-gf-walk-label { color: #93c5fd; }

  .mkt-ev-modal-icon {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
  }

  #mktEvModal.mkt-gf-modal { position: fixed; inset: 0; z-index: 3000; display: none; }
  #mktEvModal.mkt-gf-modal.is-open { display: block; }
  #mktEvModal .mkt-gf-modal-backdrop { position: absolute; inset: 0; background: rgba(15,23,42,0.55); }
  #mktEvModal .mkt-gf-modal-card {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    width: min(92vw, 380px); max-height: 88vh; overflow-y: auto; background: #fff; border-radius: 16px;
    padding: 1.6rem 1.4rem 1.4rem; text-align: center; box-shadow: 0 24px 60px rgba(0,0,0,0.3);
  }
  #mktEvModal .mkt-gf-modal-close {
    position: absolute; top: 0.6rem; right: 0.8rem; background: none; border: none;
    font-size: 1.7rem; line-height: 1; color: #94a3b8; cursor: pointer;
  }
  #mktEvModal .mkt-gf-modal-icon {
    width: 54px; height: 54px; margin: 0 auto 0.7rem; border-radius: 50%; color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
  }
  #mktEvModal .mkt-gf-modal-card h3 { margin: 0 0 1rem; font-size: 1.15rem; color: #1e293b; }
  #mktEvModal .mkt-gf-modal-rows { text-align: left; display: flex; flex-direction: column; gap: 0.55rem; }
  #mktEvModal .mkt-gf-modal-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; }
  #mktEvModal .mkt-gf-modal-label { color: #64748b; font-size: 0.82rem; font-weight: 600; text-transform: uppercase; }
  #mktEvModal .mkt-gf-modal-value { color: #1e293b; font-weight: 700; text-align: right; }
  #mktEvModal .mkt-gf-modal-value.is-second-floor {
    background: #fef9c3; color: #a16207; padding: 0.2rem 0.7rem; border-radius: 999px; font-size: 0.8rem;
    border: 1px solid #eab308;
  }
  #mktEvModal .mkt-gf-modal-badge { font-weight: 700; padding: 0.2rem 0.7rem; border-radius: 999px; font-size: 0.8rem; }
  #mktEvModal .mkt-gf-modal-badge.is-available { background: #dcfce7; color: #15803d; }
  #mktEvModal .mkt-gf-modal-badge.is-occupied  { background: #fee2e2; color: #b91c1c; }
  #mktEvModal .mkt-gf-modal-badge.is-second    { background: #fef9c3; color: #a16207; }
  #mktEvModal .mkt-gf-photos { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; justify-content: center; }
  #mktEvModal .mkt-gf-photos img { width: 96px; height: 96px; object-fit: cover; border-radius: 10px; border: 1px solid #e2e8f0; cursor: zoom-in; }
  #mktEvModal .mkt-gf-photo-thumb {
    display: block; padding: 0; border: none; background: none; cursor: zoom-in; border-radius: 10px;
  }
  #mktEvModal .mkt-gf-nophoto { color: #94a3b8; font-size: 0.85rem; margin: 0; }
  #mktEvModal .mkt-gf-apply-wrap { margin-top: 1rem; }
  #mktEvModal .mkt-gf-apply-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem;
    width: 100%; padding: 0.75rem 1rem; border: none; border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;
    font-weight: 700; font-size: 0.92rem; text-decoration: none; cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  #mktEvModal .mkt-gf-apply-btn:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35); }

  /* Dark mode → classic blue-line blueprint */
  body.darkmode .mkt-ev-plan .mkt-gf-building {
    background-color: #0b2038;
    background-image:
      linear-gradient(rgba(147, 197, 253, 0.16) 1px, transparent 1px),
      linear-gradient(90deg, rgba(147, 197, 253, 0.16) 1px, transparent 1px);
    border-color: #93c5fd;
    box-shadow: inset 0 0 0 4px #0b2038, inset 0 0 0 6px #93c5fd;
  }
  body.darkmode .mkt-ev-facade { border-bottom-color: #0b2038; }
  body.darkmode .mkt-ev-facade-word { color: #ffffff; }
  body.darkmode .mkt-ev-ent-rail--left { border-right-color: #3b6ea5; }
  body.darkmode .mkt-ev-ent-rail--right { border-left-color: #3b6ea5; }
  body.darkmode .mkt-ev-ent { background: transparent; color: #6ee7b7; border-color: #6ee7b7; }
  body.darkmode .mkt-ev-plan .mkt-gf-col { background: rgba(147, 197, 253, 0.06); border-color: #3b6ea5; }
  body.darkmode .mkt-ev-plan .mkt-gf-col-head { border-bottom-color: #3b6ea5; }
  body.darkmode .mkt-ev-plan .mkt-gf-col-title { color: #e0f2fe; }
  body.darkmode .mkt-ev-plan .mkt-gf-col-section { color: #93c5fd; }
  body.darkmode .mkt-ev-plan .mkt-gf-box { background: rgba(147, 197, 253, 0.05); border-color: #3b6ea5; }
  body.darkmode .mkt-ev-plan .mkt-gf-box-num { color: #e0f2fe; }

  @media (max-width: 640px) {
    .mkt-ev-plan { --mkt-box: 26px; }
    .mkt-ev-building { min-width: 460px; }
  }
</style>

<?php if (!$elevated_admin_mode): ?>
<script>
(function () {
  var modal = document.getElementById('mktEvModal');
  if (!modal || modal.dataset.mktEvBound === '1') return;
  modal.dataset.mktEvBound = '1';

  var elSection = document.getElementById('mktEvSection');
  var elBlock = document.getElementById('mktEvBlock');
  var elSlot = document.getElementById('mktEvSlot');
  var elStatus = document.getElementById('mktEvStatus');
  var elPhotos = document.getElementById('mktEvPhotos');
  var elApplyWrap = document.getElementById('mktEvApplyWrap');
  var elApplyBtn = document.getElementById('mktEvApplyBtn');

  function statusMeta(status) {
    if (status === 'occupied') return { cls: 'is-occupied', label: 'Occupied' };
    return { cls: 'is-available', label: 'Available' };
  }

  function openPhotoLightbox(src) {
    if (typeof window.openMktStallPhotoLightbox === 'function') {
      window.openMktStallPhotoLightbox(src);
    }
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
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.getElementById('mktGfModal') || !document.getElementById('mktGfModal').classList.contains('is-open')) {
      document.body.style.overflow = '';
    }
  }

  document.addEventListener('click', function (e) {
    var thumb = e.target.closest('#mktEvPhotos .mkt-gf-photo-thumb');
    if (thumb) {
      e.preventDefault();
      openPhotoLightbox(thumb.getAttribute('data-full-src') || (thumb.querySelector('img') && thumb.querySelector('img').src) || '');
      return;
    }
    var box = e.target.closest('.mkt-ev-plan .mkt-gf-box');
    if (!box) return;
    var elevPanel = document.getElementById('elevMapPanel');
    if (elevPanel && !elevPanel.contains(box)) return;
    openModal(box);
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-mkt-ev-close]')) closeModal();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });
})();
</script>
<?php endif; ?>

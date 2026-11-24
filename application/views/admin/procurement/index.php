<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$format = function ($value, $maximumDecimals = 2, $minimumDecimals = 0) {
	return $value === null || $value === ''
		? '—'
		: html_escape(Procurement_service::formatDisplayNumber($value, $maximumDecimals, $minimumDecimals));
};
$formatConfigured = function ($value, $maximumDecimals = 2, $minimumDecimals = 0) use ($format) {
	if ($value === null || $value === '') {
		return '—';
	}
	if (is_numeric($value) || (is_string($value) && preg_match('/^-?[0-9]+\/[1-9][0-9]*$/', $value))) {
		return $format($value, $maximumDecimals, $minimumDecimals);
	}
	return html_escape((string) $value);
};
$statusClass = function ($status) {
	if ($status === Procurement_service::STATUS_RECOMMENDED) {
		return 'text-bg-success';
	}
	if ($status === Procurement_service::STATUS_NO_PURCHASE) {
		return 'text-bg-primary';
	}
	if ($status === Procurement_service::STATUS_MANUAL_REVIEW) {
		return 'text-bg-warning';
	}
	return 'text-bg-danger';
};
?>
<div class="inventory-page-heading">
	<div>
		<div class="inventory-page-eyebrow">PERENCANAAN • DEMO BACA-SAJA</div>
		<h1>Rekomendasi pengadaan</h1>
		<p>Moving Average 3 bulan lengkap; hasil hanya untuk tinjauan, tidak membuat Purchase Order.</p>
	</div>
	<div class="inventory-heading-count">
		<span><?php echo count($recommendations); ?></span>
		<small>pasangan produk–supplier</small>
	</div>
</div>

<div class="alert alert-info py-2 small" role="note">
	<strong><?php echo html_escape($method); ?></strong> · demand:
	<?php echo html_escape(Procurement_service::formatPeriodLabel($period)); ?>.
	Stok layak pakai bersifat sementara; PO berstatus ordered belum dikonfirmasi dan tidak masuk IP/Q.
</div>

<div class="row g-2 mb-3">
	<?php foreach ($status_counts as $status => $count): ?>
		<div class="col-6 col-xl-3">
			<div class="card h-100">
				<div class="card-body py-3">
					<div class="small text-muted"><?php echo html_escape($status); ?></div>
					<div class="fs-5 fw-semibold"><?php echo (int) $count; ?></div>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle">
			<thead>
				<tr>
					<th>Produk / supplier</th>
					<th>Demand &amp; status data</th>
					<th>Stok &amp; coverage</th>
					<th>Perhitungan</th>
					<th>Hasil</th>
					<th>Rincian</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($recommendations as $index => $row): ?>
					<tr>
						<td>
							<div class="fw-semibold"><?php echo html_escape($row['product_name']); ?></div>
							<small><?php echo html_escape($row['product_code']); ?> · <?php echo html_escape($row['supplier_name']); ?></small>
							<small class="d-block">Satuan dasar: <?php echo html_escape($row['base_unit_name']); ?></small>
							<?php if (!empty($row['is_preferred'])): ?><div><span class="badge text-bg-success mt-1">Supplier utama</span></div><?php endif; ?>
							<?php if (!empty($show_scenario_debug) && $row['scenario_tag']): ?>
								<div><span class="badge rounded-pill text-bg-light border mt-1">skenario demo · <?php echo html_escape($row['scenario_tag']); ?></span></div>
							<?php endif; ?>
							<?php if (empty($row['unit_verified'])): ?><div><span class="badge text-bg-warning mt-1">Satuan belum diverifikasi</span></div><?php endif; ?>
						</td>
						<td>
							<div><strong>D:</strong> <?php echo $format($row['demand']); ?> / bulan</div>
							<small><?php echo html_escape(Procurement_service::formatPeriodLabel($row['demand_period'])); ?></small>
							<div><span class="badge text-bg-secondary mt-1"><?php echo html_escape($row['history_status']); ?></span></div>
							<?php if ($row['history_reason_code'] !== null): ?>
								<small class="d-block text-danger mt-1">
									<strong><?php echo html_escape($row['history_reason_code']); ?>:</strong>
									<?php echo html_escape($row['history_reason']); ?>
								</small>
							<?php endif; ?>
							<?php foreach ($row['demand_months'] as $month => $quantity): ?>
								<small class="d-block"><?php echo html_escape($month); ?>: <?php echo $format($quantity); ?></small>
							<?php endforeach; ?>
						</td>
						<td>
							<div>Layak: <?php echo $format($row['usable_stock_base']); ?></div>
							<div>Coverage: <?php echo $format($row['coverage_months'], 1, 1); ?> bln</div>
							<span class="badge text-bg-secondary"><?php echo html_escape($row['coverage_status']); ?></span>
							<?php if ((float) $row['red_risk_quantity'] > 0): ?>
								<small class="d-block text-danger">Risiko merah: <?php echo $format($row['red_risk_quantity']); ?></small>
							<?php endif; ?>
							<?php if ((float) $row['yellow_risk_quantity'] > 0): ?>
								<small class="d-block text-warning">Risiko kuning: <?php echo $format($row['yellow_risk_quantity']); ?></small>
							<?php endif; ?>
							<?php if ((float) $row['reserved_excess'] > 0): ?>
								<small class="d-block text-danger">Reserved melebihi sisa batch: <?php echo $format($row['reserved_excess']); ?></small>
							<?php endif; ?>
							<?php if ((int) $row['open_po_review_count'] > 0): ?>
								<small class="d-block text-warning">
									PO terbuka belum terkonfirmasi: <?php echo $format($row['open_po_review_qty']); ?> satuan beli; tidak dihitung.
								</small>
							<?php endif; ?>
						</td>
						<td>
							<div>P: <?php echo $formatConfigured($row['target_months']); ?> bln</div>
							<div>L: <?php echo $format($row['lead_time_months'], 2); ?> bln (<?php echo $formatConfigured($row['lead_time_days'], 0); ?> hari; <?php echo html_escape($row['lead_time_source'] ?: '—'); ?>)</div>
							<div>
								<?php echo !empty($row['safety_stock_verified']) ? 'SS base' : 'SS konfigurasi'; ?>:
								<?php echo $format($row['safety_stock_display']); ?>
								<span class="badge <?php echo !empty($row['safety_stock_verified']) ? 'text-bg-success' : 'text-bg-warning'; ?>">
									<?php echo !empty($row['safety_stock_verified']) ? 'Satuan terverifikasi' : 'Belum terverifikasi'; ?>
								</span>
							</div>
							<div>IP: <?php echo $format($row['inventory_position_base']); ?></div>
							<div>Q0: <?php echo $format($row['q0']); ?></div>
							<div>Q final: <?php echo $format($row['q_final_base']); ?></div>
						</td>
						<td>
							<span class="badge <?php echo $statusClass($row['status']); ?>"><?php echo html_escape($row['status']); ?></span>
							<div class="small mt-1"><?php echo html_escape($row['reason']); ?></div>
							<?php if ($row['target_met']): ?><div><span class="badge text-bg-success mt-1">Target P terpenuhi</span></div><?php endif; ?>
							<?php if ($row['coverage_after_arrival_display'] !== null): ?>
								<small class="d-block mt-1">Estimasi sederhana setelah tiba: <?php echo $format($row['coverage_after_arrival_display'], 1, 1); ?> bln; bukan simulasi time-phased.</small>
							<?php endif; ?>
						</td>
						<td>
							<details>
								<summary class="small">Lihat rincian rumus</summary>
								<div class="small mt-2">
									<div><?php echo html_escape($row['formula']['q0']); ?></div>
									<div><?php echo html_escape($row['formula']['q_supplier']); ?></div>
									<div><?php echo html_escape($row['formula']['q_final']); ?></div>
									<?php
									$exactDetails = array(
										'D' => array($row['formula_values']['demand'], 2, 0),
										'P' => array($row['formula_values']['target_months'], 2, 0),
										'L' => array($row['formula_values']['lead_time_months'], 2, 0),
										'SS' => array($row['formula_values']['safety_stock'], 2, 0),
										'Layak' => array($row['formula_values']['usable_stock_base'], 2, 0),
										'PO terhitung' => array($row['formula_values']['eligible_open_po_base'], 2, 0),
										'IP' => array($row['formula_values']['inventory_position_base'], 2, 0),
										'Q0' => array($row['formula_values']['q0'], 2, 0),
										'F' => array($row['formula_values']['base_per_purchase_unit'], 9, 0),
										'M' => array($row['formula_values']['moq_purchase_units'], 2, 0),
										'U' => array($row['formula_values']['order_multiple_purchase_units'], 2, 0),
										'q raw' => array($row['formula_values']['raw_purchase_quantity'], 2, 0),
										'q supplier' => array($row['formula_values']['supplier_quantity'], 2, 0),
										'Q final' => array($row['formula_values']['q_final_base'], 2, 0),
										'Coverage setelah tiba' => array($row['formula_values']['coverage_after_arrival'], 1, 1)
									);
									foreach ($exactDetails as $label => $detail):
										if ($detail[0] !== null && $detail[0] !== ''):
									?>
										<div><?php echo html_escape($label); ?> eksak: <?php echo html_escape((string) $detail[0]); ?> <span class="text-muted">(≈ <?php echo $format($detail[0], $detail[1], $detail[2]); ?>)</span></div>
									<?php
										endif;
									endforeach;
									?>
									<hr>
									<div>Unit beli: <?php echo html_escape($row['purchase_unit']); ?></div>
									<div>Mode MOQ: <?php echo html_escape($row['moq_mode']); ?></div>
									<div>PO terhitung ke IP: <?php echo $format($row['eligible_open_po_base']); ?></div>
									<?php if (isset($row['formula']['projection'])): ?>
										<div><?php echo html_escape($row['formula']['projection']); ?></div>
									<?php endif; ?>
								</div>
							</details>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if (!$recommendations): ?>
					<tr><td colspan="6" class="text-center">Belum ada pasangan produk–supplier aktif.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<p class="small text-muted mt-3 mb-0">
	P1.6 ringan: target P global <?php echo $format($target_months); ?> bulan.
	Lead time otomatis, draft PO, dan snapshot rekomendasi belum tersedia.
</p>

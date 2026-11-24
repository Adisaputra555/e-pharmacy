<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="inventory-page">
	<div class="inventory-page-heading">
		<div>
			<div class="inventory-page-eyebrow">STOK & PERSEDIAAN</div>
			<h1>Inventaris</h1>
			<p>Pantau stok, penerimaan batch, penyesuaian, dan aktivitas persediaan dari satu halaman.</p>
		</div>
		<div class="inventory-heading-count">
			<span><?php echo count($products); ?></span>
			<small>produk terdaftar</small>
		</div>
	</div>

	<?php if ($this->session->flashdata('success')): ?><div class="alert alert-success py-2"><?php echo html_escape($this->session->flashdata('success')); ?></div><?php endif; ?>
	<?php if ($this->session->flashdata('error')): ?><div class="alert alert-danger py-2"><?php echo html_escape($this->session->flashdata('error')); ?></div><?php endif; ?>

	<ul class="nav nav-tabs inventory-tabs" role="tablist" aria-label="Menu inventaris">
		<li class="nav-item" role="presentation"><button class="nav-link active" id="inventory-overview-tab" data-bs-toggle="tab" data-bs-target="#inventory-overview" type="button" role="tab" aria-controls="inventory-overview" aria-selected="true">Ringkasan Stok</button></li>
		<li class="nav-item" role="presentation"><button class="nav-link" id="inventory-receive-tab" data-bs-toggle="tab" data-bs-target="#inventory-receive" type="button" role="tab" aria-controls="inventory-receive" aria-selected="false">Terima Batch</button></li>
		<li class="nav-item" role="presentation"><button class="nav-link" id="inventory-adjust-tab" data-bs-toggle="tab" data-bs-target="#inventory-adjust" type="button" role="tab" aria-controls="inventory-adjust" aria-selected="false">Penyesuaian</button></li>
		<li class="nav-item" role="presentation"><button class="nav-link" id="inventory-movements-tab" data-bs-toggle="tab" data-bs-target="#inventory-movements" type="button" role="tab" aria-controls="inventory-movements" aria-selected="false">Riwayat Stok</button></li>
		<li class="nav-item" role="presentation"><button class="nav-link" id="inventory-opname-tab" data-bs-toggle="tab" data-bs-target="#inventory-opname" type="button" role="tab" aria-controls="inventory-opname" aria-selected="false">Stok Opname</button></li>
	</ul>

	<div class="tab-content inventory-tab-content">
		<section class="tab-pane fade show active" id="inventory-overview" role="tabpanel" aria-labelledby="inventory-overview-tab" tabindex="0">
			<div class="row g-3">
				<div class="col-12 col-xl-7">
					<div class="card border-0 shadow-sm h-100">
						<div class="card-header bg-white border-0 px-3 pt-3">
							<h2 class="h6 mb-1">Stok per Produk</h2>
							<p class="small text-muted mb-0">Jumlah stok yang tersedia dibandingkan dengan batas minimum.</p>
						</div>
						<div class="table-responsive">
							<table class="table table-hover align-middle mb-0">
								<thead class="table-light"><tr><th>Kode</th><th>Produk</th><th>Stok Tersedia</th><th>Minimum</th><th>Status</th></tr></thead>
								<tbody>
									<?php if (!$products): ?><tr><td colspan="5" class="text-muted text-center py-4">Belum ada data produk.</td></tr><?php endif; ?>
									<?php foreach ($products as $product): ?>
										<tr>
											<td><?php echo html_escape($product->code); ?></td>
											<td><?php echo html_escape($product->name); ?></td>
											<td><?php echo number_format($product->available, 3, ',', '.'); ?></td>
											<td><?php echo number_format((float) $product->minimum_stock, 3, ',', '.'); ?></td>
											<td><?php if ($product->available <= (float) $product->minimum_stock): ?><span class="badge text-bg-danger">Minimum</span><?php else: ?><span class="badge text-bg-success">Aman</span><?php endif; ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
				<div class="col-12 col-xl-5">
					<div class="card border-0 shadow-sm h-100">
						<div class="card-header bg-white border-0 px-3 pt-3">
							<h2 class="h6 mb-1">Kedaluwarsa dalam 30 Hari</h2>
							<p class="small text-muted mb-0">Batch aktif yang perlu segera diperiksa.</p>
						</div>
						<div class="table-responsive">
							<table class="table table-hover align-middle mb-0">
								<thead class="table-light"><tr><th>Produk</th><th>Batch</th><th>Kedaluwarsa</th></tr></thead>
								<tbody>
									<?php if (!$expiring_batches): ?><tr><td colspan="3" class="text-muted text-center py-4">Tidak ada batch mendekati kedaluwarsa.</td></tr><?php endif; ?>
									<?php foreach ($expiring_batches as $batch): ?>
										<tr><td><?php echo html_escape($batch->product_name); ?></td><td><?php echo html_escape($batch->batch_number); ?></td><td><?php echo html_escape($batch->expiration_date); ?></td></tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</section>

		<section class="tab-pane fade" id="inventory-receive" role="tabpanel" aria-labelledby="inventory-receive-tab" tabindex="0">
			<div class="inventory-tab-intro"><h2>Terima Batch Baru</h2><p>Catat batch baru agar jumlah persediaan langsung masuk ke stok apotek.</p></div>
			<div class="card border-0 shadow-sm inventory-form-card"><div class="card-body">
				<form method="post" action="<?php echo site_url('admin/inventory/receive_store'); ?>">
					<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
					<div class="row g-3">
						<div class="col-md-6"><label class="form-label" for="inventory_receive_product">Produk</label><select class="form-select" id="inventory_receive_product" name="product_id" required><option value="">Pilih produk</option><?php foreach ($receive_products as $product): ?><option value="<?php echo (int) $product->id; ?>"><?php echo html_escape($product->code . ' - ' . $product->name); ?></option><?php endforeach; ?></select></div>
						<div class="col-md-6"><label class="form-label" for="inventory_receive_batch">Nomor batch</label><input class="form-control" id="inventory_receive_batch" name="batch_number" required></div>
						<div class="col-md-4"><label class="form-label" for="inventory_receive_expiration">Tanggal kedaluwarsa</label><input class="form-control" id="inventory_receive_expiration" name="expiration_date" type="date" required></div>
						<div class="col-md-4"><label class="form-label" for="inventory_receive_quantity">Jumlah awal</label><input class="form-control" id="inventory_receive_quantity" name="quantity" type="number" min="0.001" step="0.001" required></div>
						<div class="col-md-4"><label class="form-label" for="inventory_receive_purchase_price">Harga beli</label><input class="form-control" id="inventory_receive_purchase_price" name="purchase_price" type="number" min="0" step="0.01" required></div>
						<div class="col-md-4"><label class="form-label" for="inventory_receive_selling_price">Harga jual batch</label><input class="form-control" id="inventory_receive_selling_price" name="selling_price" type="number" min="0" step="0.01" required></div>
					</div>
					<div class="inventory-form-actions"><button class="btn btn-success" type="submit">Simpan Penerimaan</button></div>
				</form>
			</div></div>
		</section>

		<section class="tab-pane fade" id="inventory-adjust" role="tabpanel" aria-labelledby="inventory-adjust-tab" tabindex="0">
			<div class="inventory-tab-intro"><h2>Penyesuaian Stok</h2><p>Catat stok masuk atau keluar pada batch yang sudah tersimpan.</p></div>
			<div class="card border-0 shadow-sm inventory-form-card"><div class="card-body">
				<form method="post" action="<?php echo site_url('admin/inventory/adjust_store'); ?>">
					<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
					<div class="row g-3">
						<div class="col-md-6"><label class="form-label" for="inventory_adjust_batch">Batch</label><select class="form-select" id="inventory_adjust_batch" name="batch_id" required><option value="">Pilih batch</option><?php foreach ($batches as $batch): ?><option value="<?php echo (int) $batch->id; ?>"><?php echo html_escape($batch->product_name . ' / ' . $batch->batch_number . ' (' . $batch->remaining_quantity . ')'); ?></option><?php endforeach; ?></select></div>
						<div class="col-md-3"><label class="form-label" for="inventory_adjust_type">Jenis</label><select class="form-select" id="inventory_adjust_type" name="movement_type" required><option value="stock_in">Stok Masuk</option><option value="stock_out">Stok Keluar</option></select></div>
						<div class="col-md-3"><label class="form-label" for="inventory_adjust_quantity">Jumlah</label><input class="form-control" id="inventory_adjust_quantity" name="quantity" type="number" min="0.001" step="0.001" required></div>
						<div class="col-12"><label class="form-label" for="inventory_adjust_notes">Catatan</label><textarea class="form-control" id="inventory_adjust_notes" name="notes" rows="3" placeholder="Tambahkan alasan penyesuaian"></textarea></div>
					</div>
					<div class="inventory-form-actions"><button class="btn btn-success" type="submit" <?php echo !$batches ? 'disabled' : ''; ?>>Simpan Penyesuaian</button></div>
					<?php if (!$batches): ?><div class="small text-muted mt-2">Belum ada batch aktif untuk disesuaikan.</div><?php endif; ?>
				</form>
			</div></div>
		</section>

		<section class="tab-pane fade" id="inventory-movements" role="tabpanel" aria-labelledby="inventory-movements-tab" tabindex="0">
			<div class="inventory-tab-intro"><h2>Riwayat Pergerakan Stok</h2><p>Aktivitas terbaru stok masuk, keluar, dan penyesuaian.</p></div>
			<div class="card border-0 shadow-sm"><div class="table-responsive">
				<table class="table table-hover align-middle mb-0">
					<thead class="table-light"><tr><th>Waktu</th><th>Produk</th><th>Batch</th><th>Jenis</th><th>Jumlah</th><th>Saldo</th><th>Catatan</th></tr></thead>
					<tbody>
						<?php if (!$movements): ?><tr><td colspan="7" class="text-muted text-center py-4">Belum ada pergerakan stok.</td></tr><?php endif; ?>
						<?php foreach ($movements as $movement): ?>
							<tr>
								<td><?php echo html_escape($movement->created_at); ?></td>
								<td><?php echo html_escape($movement->product_name); ?></td>
								<td><?php echo html_escape((string) $movement->batch_number); ?></td>
								<td><?php echo html_escape($movement->movement_type); ?></td>
								<td><?php echo number_format((float) $movement->quantity, 3, ',', '.'); ?></td>
								<td><?php echo number_format((float) $movement->balance_after, 3, ',', '.'); ?></td>
								<td><?php echo html_escape((string) $movement->notes); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div></div>
		</section>

		<section class="tab-pane fade" id="inventory-opname" role="tabpanel" aria-labelledby="inventory-opname-tab" tabindex="0">
			<div class="inventory-tab-intro"><h2>Stok Opname</h2><p>Bandingkan jumlah fisik setiap batch dengan stok yang tercatat di sistem.</p></div>
			<?php if (!$batches): ?>
				<div class="alert alert-info">Belum ada batch aktif untuk dilakukan stok opname.</div>
			<?php else: ?>
				<div class="card border-0 shadow-sm"><div class="card-body">
					<div class="alert alert-info">Masukkan jumlah fisik setiap batch. Perubahan stok akan diterapkan setelah disimpan.</div>
					<form method="post" action="<?php echo site_url('admin/inventory/opname_store'); ?>">
						<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
						<div class="table-responsive">
							<table class="table table-hover align-middle mb-0">
								<thead class="table-light"><tr><th>Produk</th><th>Batch</th><th>Kedaluwarsa</th><th>Stok Sistem</th><th>Stok Fisik</th></tr></thead>
								<tbody>
									<?php foreach ($batches as $batch): ?>
										<tr>
											<td><?php echo html_escape($batch->product_name); ?></td>
											<td><?php echo html_escape($batch->batch_number); ?></td>
											<td><?php echo html_escape($batch->expiration_date); ?></td>
											<td><?php echo number_format((float) $batch->remaining_quantity, 3, ',', '.'); ?></td>
											<td><input class="form-control form-control-sm" name="actual[<?php echo (int) $batch->id; ?>]" type="number" min="0" step="0.001" value="<?php echo html_escape((string) $batch->remaining_quantity); ?>" required></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<div class="inventory-form-actions"><button class="btn btn-success" type="submit">Simpan Opname</button></div>
					</form>
				</div></div>
			<?php endif; ?>
		</section>
	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function () {
		var tabTriggers = document.querySelectorAll('.inventory-tabs [data-bs-toggle="tab"]');
		if (!window.bootstrap || !window.bootstrap.Tab) return;

		tabTriggers.forEach(function (trigger) {
			trigger.addEventListener('shown.bs.tab', function (event) {
				window.history.replaceState(null, '', event.target.getAttribute('data-bs-target'));
			});
		});

		if (/^#inventory-(overview|receive|adjust|movements|opname)$/.test(window.location.hash)) {
			var activeTrigger = document.querySelector('.inventory-tabs [data-bs-target="' + window.location.hash + '"]');
			if (activeTrigger) window.bootstrap.Tab.getOrCreateInstance(activeTrigger).show();
		}
	});
</script>

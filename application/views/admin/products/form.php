<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
	<h1 class="h4 mb-0"><?php echo html_escape($title); ?></h1>
</div>

<?php if ($this->session->flashdata('error')): ?>
	<div class="alert alert-danger py-2"><?php echo $this->session->flashdata('error'); ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('success')): ?>
	<div class="alert alert-success py-2"><?php echo html_escape($this->session->flashdata('success')); ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
	<div class="card-body">
		<form method="post" action="<?php echo $action; ?>" enctype="multipart/form-data" data-crud-form data-modal-size="lg">
			<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
			<div class="row g-3">
				<div class="col-12"><div class="crud-form-section"><span class="crud-section-index">01</span><div><h3>Identitas produk</h3><p>Kode, barcode, dan nama utama produk.</p></div></div></div>
				<div class="col-md-4">
					<label class="form-label">Kode Produk<?php if (!$row): ?> <span class="badge text-bg-success">Otomatis</span><?php endif; ?></label>
					<input class="form-control" id="product_code" name="code" value="<?php echo $row ? html_escape($row->code) : html_escape($generated_code); ?>" required <?php echo !$row ? 'readonly' : ''; ?>>
					<?php if (!$row): ?><div class="form-text">Dibuat otomatis dan unik.</div><?php endif; ?>
				</div>
				<div class="col-md-4">
					<label class="form-label">Barcode<?php if (!$row): ?> <span class="badge text-bg-success">Otomatis</span><?php endif; ?></label>
					<input class="form-control" id="product_barcode" name="barcode" value="<?php echo $row ? html_escape((string) $row->barcode) : html_escape($generated_barcode); ?>" <?php echo !$row ? 'readonly' : ''; ?>>
					<?php if (!$row): ?><div class="form-text">Barcode internal EAN-13.</div><?php endif; ?>
				</div>
				<div class="col-md-4"><label class="form-label">Nama Obat</label><input class="form-control" name="name" value="<?php echo $row ? html_escape($row->name) : ''; ?>" required></div>
				<div class="col-md-4"><label class="form-label">Nama Generik</label><input class="form-control" name="generic_name" value="<?php echo $row ? html_escape((string) $row->generic_name) : ''; ?>"></div>

				<div class="col-12"><div class="crud-form-section"><span class="crud-section-index">02</span><div><h3>Kategori &amp; informasi obat</h3><p>Atur klasifikasi, satuan, dan informasi kemasan.</p></div></div></div>
				<div class="col-md-4">
					<label class="form-label">Kategori</label>
					<select class="form-select" name="category_id">
						<option value="">- Pilih -</option>
						<?php foreach ($categories as $item): ?>
							<option value="<?php echo $item->id; ?>" <?php echo $row && (int) $row->category_id === (int) $item->id ? 'selected' : ''; ?>><?php echo html_escape($item->name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-4">
					<label class="form-label">Satuan</label>
					<select class="form-select" name="unit_id">
						<option value="">- Pilih -</option>
						<?php foreach ($units as $item): ?>
							<option value="<?php echo $item->id; ?>" <?php echo $row && (int) $row->unit_id === (int) $item->id ? 'selected' : ''; ?>><?php echo html_escape($item->name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="col-md-4">
					<label class="form-label">Produsen</label>
					<select class="form-select" name="manufacturer_id">
						<option value="">- Pilih -</option>
						<?php foreach ($manufacturers as $item): ?>
							<option value="<?php echo $item->id; ?>" <?php echo $row && (int) $row->manufacturer_id === (int) $item->id ? 'selected' : ''; ?>><?php echo html_escape($item->name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-4"><label class="form-label">Bentuk Sediaan</label><input class="form-control" name="dosage_form" value="<?php echo $row ? html_escape((string) $row->dosage_form) : ''; ?>"></div>
				<div class="col-md-4"><label class="form-label">Kekuatan</label><input class="form-control" name="strength" value="<?php echo $row ? html_escape((string) $row->strength) : ''; ?>"></div>

				<div class="col-md-4"><label class="form-label">Nomor Registrasi</label><input class="form-control" name="registration_number" value="<?php echo $row ? html_escape((string) $row->registration_number) : ''; ?>"></div>
				<div class="col-md-4"><label class="form-label">Klasifikasi Obat</label>
					<select class="form-select" name="drug_classification" required>
						<?php foreach (array('obat-bebas', 'obat-bebas-terbatas', 'obat-keras', 'narkotika', 'psikotropika', 'alat-kesehatan', 'suplemen', 'lainnya') as $classification): ?>
							<option value="<?php echo $classification; ?>" <?php echo $row && $row->drug_classification === $classification ? 'selected' : (!$row && $classification === 'obat-bebas' ? 'selected' : ''); ?>><?php echo ucfirst(str_replace('-', ' ', $classification)); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-4"><label class="form-label">Foto Produk</label><input class="form-control" name="photo" type="file" accept=".jpg,.jpeg,.png,.webp"></div>

				<div class="col-12"><div class="crud-form-section"><span class="crud-section-index">03</span><div><h3>Harga &amp; persediaan</h3><p>Informasi harga dan batas stok minimum.</p></div></div></div>
				<div class="col-md-4"><label class="form-label">Harga Beli</label><input class="form-control" name="purchase_price" type="number" min="0" step="0.01" value="<?php echo $row ? html_escape((string) $row->purchase_price) : '0'; ?>" required></div>
				<div class="col-md-4"><label class="form-label">Harga Jual</label><input class="form-control" name="selling_price" type="number" min="0" step="0.01" value="<?php echo $row ? html_escape((string) $row->selling_price) : '0'; ?>" required></div>
				<div class="col-md-4"><label class="form-label">Stok Minimum</label><input class="form-control" name="minimum_stock" type="number" min="0" step="0.001" value="<?php echo $row ? html_escape((string) $row->minimum_stock) : '0'; ?>" required></div>

				<div class="col-12"><div class="crud-form-section"><span class="crud-section-index">04</span><div><h3>Catatan &amp; pengaturan</h3><p>Deskripsi dan status operasional produk.</p></div></div></div>
				<div class="col-12"><label class="form-label">Deskripsi</label><textarea class="form-control" name="description" rows="4"><?php echo $row ? html_escape((string) $row->description) : ''; ?></textarea></div>
				<div class="col-md-6">
					<div class="form-check mt-2"><input class="form-check-input" id="requires_prescription" name="requires_prescription" type="checkbox" value="1" <?php echo $row && $row->requires_prescription ? 'checked' : ''; ?>><label class="form-check-label" for="requires_prescription">Memerlukan resep</label></div>
				</div>
				<div class="col-md-6">
					<div class="form-check mt-2"><input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" <?php echo !$row || $row->is_active ? 'checked' : ''; ?>><label class="form-check-label" for="is_active">Produk aktif</label></div>
				</div>
			</div>
			<div class="crud-form-actions d-flex gap-2"><button class="btn btn-success" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="<?php echo site_url('admin/products'); ?>">Kembali</a></div>
		</form>
	</div>
</div>

<?php if (!empty($units_v2_enabled)): ?>
				<div class="card border-0 shadow-sm mt-4">
					<div class="card-header bg-white">
						<h2 class="h6 mb-1">Konfigurasi dan verifikasi satuan</h2>
						<p class="text-muted small mb-0">Faktor alternatif selalu dihitung langsung ke satuan dasar produk.</p>
					</div>
					<div class="card-body">
						<?php if (!$row): ?>
							<div class="alert alert-info mb-0">Simpan produk terlebih dahulu untuk mengatur dan memverifikasi satuannya.</div>
						<?php else: ?>
							<?php
							$statusLabels = array(
								'verified' => array('Terverifikasi', 'text-bg-success'),
								'legacy_unverified' => array('Satuan belum diverifikasi', 'text-bg-warning'),
								'unknown' => array('Satuan tidak diketahui', 'text-bg-secondary')
							);
							$statusLabel = isset($statusLabels[$unit_status]) ? $statusLabels[$unit_status] : $statusLabels['unknown'];
							?>
							<p><span class="badge <?php echo $statusLabel[1]; ?>"><?php echo html_escape($statusLabel[0]); ?></span></p>

							<?php if ($unit_status === 'unknown'): ?>
								<div class="alert alert-warning mb-0">Pilih dan simpan satuan dasar pada formulir produk terlebih dahulu. Tidak ada faktor yang diasumsikan.</div>
							<?php elseif ($unit_verification): ?>
								<div class="table-responsive mb-3">
									<table class="table table-sm align-middle">
										<thead><tr><th>Satuan</th><th>Faktor ke dasar</th><th>Skala input</th><th>Skala dasar</th><th>Versi</th></tr></thead>
										<tbody>
											<?php foreach ($unit_configuration as $unit): ?>
												<tr>
													<td><?php echo html_escape($unit['unit_name']); ?> (<?php echo html_escape($unit['unit_code']); ?>)</td>
													<td><?php echo html_escape($unit['conversion_factor_to_base']); ?></td>
													<td><?php echo (int) $unit['input_scale']; ?></td>
													<td><?php echo (int) $unit['base_scale']; ?></td>
													<td><?php echo (int) $unit['config_version']; ?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
								<div class="small text-muted">
									Diverifikasi oleh <?php echo html_escape($unit_verification['verifier_name']); ?>
									pada <?php echo html_escape($unit_verification['verified_at']); ?>.
									Bukti: <?php echo html_escape($unit_verification['mapping_evidence']); ?>
								</div>
							<?php else: ?>
								<form method="post" action="<?php echo site_url('admin/products/save_unit_configuration/' . (int) $row->id); ?>">
									<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
									<?php
									$baseUnitName = (string) $row->unit_id;
									foreach ($units as $item) {
										if ((int) $item->id === (int) $row->unit_id) {
											$baseUnitName = $item->name . ' (' . $item->code . ')';
											break;
										}
									}
									?>
									<div class="alert alert-light border">
										Satuan dasar saat ini: <strong><?php echo html_escape($baseUnitName); ?></strong>.
										Maksimal tiga satuan alternatif; konfigurasi yang sudah dicatat tidak dihapus.
									</div>
									<div class="mb-3">
										<label class="form-label" for="unit_base_scale">Skala maksimal satuan dasar (0–3)</label>
										<input class="form-control" id="unit_base_scale" name="base_scale" type="number" min="0" max="3" step="1" value="<?php echo (int) $unit_base_scale; ?>" required>
									</div>
									<div class="table-responsive">
										<table class="table table-sm align-middle">
											<thead><tr><th>Satuan alternatif</th><th>Faktor langsung ke dasar</th><th>Skala input (0–3)</th></tr></thead>
											<tbody>
												<?php for ($index = 0; $index < 3; $index++): ?>
													<?php $currentUnit = isset($unit_alternatives[$index]) ? $unit_alternatives[$index] : null; ?>
													<tr>
														<td>
															<select class="form-select" name="unit_config[<?php echo $index; ?>][unit_id]">
																<option value="">- Tidak digunakan -</option>
																<?php foreach ($units as $item): ?>
																	<?php if ((int) $item->id === (int) $row->unit_id) { continue; } ?>
																	<option value="<?php echo (int) $item->id; ?>" <?php echo $currentUnit && (int) $currentUnit['unit_id'] === (int) $item->id ? 'selected' : ''; ?>>
																		<?php echo html_escape($item->name . ' (' . $item->code . ')'); ?>
																	</option>
																<?php endforeach; ?>
															</select>
														</td>
														<td><input class="form-control" name="unit_config[<?php echo $index; ?>][factor]" inputmode="decimal" value="<?php echo $currentUnit ? html_escape($currentUnit['conversion_factor_to_base']) : ''; ?>" placeholder="Contoh: 10"></td>
														<td><input class="form-control" name="unit_config[<?php echo $index; ?>][input_scale]" type="number" min="0" max="3" step="1" value="<?php echo $currentUnit ? (int) $currentUnit['input_scale'] : 0; ?>"></td>
													</tr>
												<?php endfor; ?>
											</tbody>
										</table>
									</div>
									<div class="mb-3">
										<label class="form-label" for="unit_config_reason">Alasan perubahan konfigurasi</label>
										<textarea class="form-control" id="unit_config_reason" name="reason" maxlength="500" required></textarea>
									</div>
									<button class="btn btn-outline-primary" type="submit">Simpan konfigurasi</button>
								</form>

								<?php if ($unit_configuration): ?>
									<form method="post" action="<?php echo site_url('admin/products/verify_unit_configuration/' . (int) $row->id); ?>" class="border-top mt-4 pt-3">
										<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
										<h3 class="h6">Verifikasi satu kali</h3>
										<div class="mb-3">
											<label class="form-label" for="source_unit_description">Sumber/pemetaan satuan</label>
											<input class="form-control" id="source_unit_description" name="source_unit_description" maxlength="255" required>
										</div>
										<div class="mb-3">
											<label class="form-label" for="mapping_evidence">Bukti verifikasi</label>
											<textarea class="form-control" id="mapping_evidence" name="mapping_evidence" required></textarea>
										</div>
										<button class="btn btn-success" type="submit">Verifikasi dan kunci</button>
									</form>
								<?php endif; ?>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

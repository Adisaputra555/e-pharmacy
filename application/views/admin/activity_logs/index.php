<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="mb-3">
	<h1 class="h4 mb-1">Aktivitas Pengguna</h1>
	<div class="text-muted small">Riwayat login dan perubahan penting yang dilakukan pengguna.</div>
</div>

<form class="row g-2 mb-3" method="get" action="<?php echo site_url('admin/activity_logs'); ?>">
	<div class="col-sm-6 col-md-4"><input class="form-control" name="search" value="<?php echo html_escape($search); ?>" placeholder="Cari pengguna atau aktivitas"></div>
	<div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Cari</button></div>
</form>

<div class="card border-0 shadow-sm">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead class="table-light"><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Data</th><th>IP</th></tr></thead>
			<tbody>
				<?php if (!$rows): ?>
					<tr><td colspan="5" class="text-center text-muted py-4">Belum ada aktivitas tercatat.</td></tr>
				<?php endif; ?>
				<?php foreach ($rows as $row): ?>
					<?php
					$activity_labels = array(
						'login_success' => 'Login berhasil',
						'login_failed' => 'Login gagal',
						'login_inactive_account' => 'Login ditolak: akun nonaktif',
						'login_locked_account' => 'Login ditolak: akun terkunci',
						'logout' => 'Logout',
						'user_created' => 'Membuat pengguna',
						'user_updated' => 'Mengubah pengguna',
						'prescription_review' => 'Memeriksa resep',
						'prescription_file_viewed' => 'Melihat file resep',
						'prescription_uploaded' => 'Mengunggah resep',
						'order_created' => 'Membuat pesanan',
						'order_cancelled' => 'Membatalkan pesanan',
						'cash_sale_created' => 'Membuat penjualan kasir',
						'cart_item_updated' => 'Mengubah keranjang',
						'cart_item_removed' => 'Menghapus item keranjang',
						'address_created' => 'Menambah alamat',
						'role_permissions_updated' => 'Mengubah akses role',
						'pharmacy_settings_updated' => 'Mengubah pengaturan apotek',
						'payment_verified' => 'Memverifikasi pembayaran',
						'shipment_status_updated' => 'Mengubah status pengiriman',
						'stock_received' => 'Menerima stok',
						'stock_adjusted' => 'Menyesuaikan stok',
						'stock_opname_completed' => 'Menyelesaikan stok opname',
						'purchase_created' => 'Membuat pembelian',
						'purchase_received' => 'Menerima pembelian',
						'product_created' => 'Membuat produk',
						'product_updated' => 'Mengubah produk',
						'product_deleted' => 'Menghapus produk'
					);
					$activity_label = isset($activity_labels[$row->action]) ? $activity_labels[$row->action] : ucfirst(str_replace('_', ' ', $row->action));
					if (!isset($activity_labels[$row->action])) {
						$entity_labels = array(
							'categories' => 'Kategori',
							'units' => 'Satuan',
							'manufacturers' => 'Produsen',
							'suppliers' => 'Supplier'
						);
						foreach (array('_created' => 'Menambah', '_updated' => 'Mengubah', '_deleted' => 'Menghapus') as $suffix => $verb) {
							if (substr($row->action, -strlen($suffix)) === $suffix) {
								$entity = substr($row->action, 0, -strlen($suffix));
								$activity_label = $verb . ' ' . (isset($entity_labels[$entity]) ? $entity_labels[$entity] : str_replace('_', ' ', $entity));
								break;
							}
						}
					}
					?>
					<tr>
						<td class="text-nowrap"><?php echo html_escape($row->created_at); ?></td>
						<td>
							<div class="fw-semibold"><?php echo html_escape($row->user_name ?: 'Pengguna dihapus'); ?></div>
							<small class="text-muted"><?php echo html_escape((string) $row->user_email); ?></small>
						</td>
						<td><?php echo html_escape($activity_label); ?></td>
						<td>
							<?php if ($row->entity_type): ?><div><?php echo html_escape($row->entity_type); ?><?php echo $row->entity_id ? ' #' . (int) $row->entity_id : ''; ?></div><?php endif; ?>
							<?php if ($row->old_values || $row->new_values): ?>
								<details><summary class="small text-muted">Detail perubahan</summary>
									<?php if ($row->old_values): ?><div class="small mt-1">Sebelum: <code><?php echo html_escape($row->old_values); ?></code></div><?php endif; ?>
									<?php if ($row->new_values): ?><div class="small mt-1">Sesudah: <code><?php echo html_escape($row->new_values); ?></code></div><?php endif; ?>
								</details>
							<?php endif; ?>
						</td>
						<td>
							<div><?php echo html_escape((string) $row->ip_address); ?></div>
							<?php if ($row->user_agent): ?><small class="text-muted"><?php echo html_escape($row->user_agent); ?></small><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if ($this->pagination->create_links()): ?><div class="card-footer"><?php echo $this->pagination->create_links(); ?></div><?php endif; ?>
</div>

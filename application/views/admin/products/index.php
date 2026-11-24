<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
	<h1 class="h4 mb-0">Produk</h1>
	<a class="btn btn-success" href="<?php echo site_url('admin/products/create'); ?>" data-modal-url data-modal-size="lg">Tambah Produk</a>
</div>

<?php if ($this->session->flashdata('success')): ?>
	<div class="alert alert-success py-2"><?php echo html_escape($this->session->flashdata('success')); ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
	<div class="alert alert-danger py-2"><?php echo html_escape($this->session->flashdata('error')); ?></div>
<?php endif; ?>

<form class="row g-2 mb-3" method="get" action="<?php echo site_url('admin/products'); ?>">
	<div class="col-sm-6 col-md-4"><input class="form-control" name="search" value="<?php echo html_escape($search); ?>" placeholder="Cari kode, nama, generik"></div>
	<div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Cari</button></div>
</form>

<div class="card border-0 shadow-sm">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead class="table-light"><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Satuan</th><th>Harga Jual</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
			<tbody>
				<?php if (!$rows): ?>
					<tr><td colspan="7" class="text-center text-muted py-4">Belum ada produk.</td></tr>
				<?php endif; ?>
				<?php foreach ($rows as $row): ?>
					<tr>
						<td><?php echo html_escape($row->code); ?></td>
						<td>
							<div class="fw-semibold"><?php echo html_escape($row->name); ?></div>
							<small class="text-muted"><?php echo html_escape((string) $row->generic_name); ?></small>
						</td>
						<td><?php echo html_escape((string) $row->category_name); ?></td>
						<td>
							<?php echo html_escape((string) $row->unit_name); ?>
							<?php if (!empty($units_v2_enabled)): ?>
								<?php
								$unitStatus = isset($unit_statuses[(int) $row->id]) ? $unit_statuses[(int) $row->id] : 'unknown';
								$unitBadge = $unitStatus === 'verified'
									? array('Terverifikasi', 'text-bg-success')
									: ($unitStatus === 'legacy_unverified'
										? array('Satuan belum diverifikasi', 'text-bg-warning')
										: array('Satuan tidak diketahui', 'text-bg-secondary'));
								?>
								<div><span class="badge <?php echo $unitBadge[1]; ?>"><?php echo html_escape($unitBadge[0]); ?></span></div>
							<?php endif; ?>
						</td>
						<td>Rp <?php echo number_format((float) $row->selling_price, 0, ',', '.'); ?></td>
						<td>
							<span class="badge <?php echo (int) $row->is_active ? 'text-bg-success' : 'text-bg-secondary'; ?>">
								<?php echo (int) $row->is_active ? 'Aktif' : 'Nonaktif'; ?>
							</span>
						</td>
						<td class="text-end text-nowrap">
							<a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('admin/products/show/' . $row->id); ?>" data-modal-url data-modal-size="lg">Lihat</a>
							<a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('admin/products/edit/' . $row->id); ?>" data-modal-url data-modal-size="lg">Edit</a>
							<form class="d-inline" method="post" action="<?php echo site_url('admin/products/delete/' . $row->id); ?>" data-crud-delete data-delete-message="Hapus produk ini?">
								<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
								<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if ($this->pagination->create_links()): ?>
		<div class="card-footer"><?php echo $this->pagination->create_links(); ?></div>
	<?php endif; ?>
</div>

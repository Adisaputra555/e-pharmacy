<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
	<h1 class="h4 mb-0">Pengguna &amp; Role</h1>
	<a class="btn btn-success" href="<?php echo site_url('admin/users/create'); ?>" data-modal-url>Tambah Pengguna</a>
</div>

<?php if ($this->session->flashdata('success')): ?>
	<div class="alert alert-success py-2"><?php echo html_escape($this->session->flashdata('success')); ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
	<div class="alert alert-danger py-2"><?php echo html_escape($this->session->flashdata('error')); ?></div>
<?php endif; ?>

<form class="row g-2 mb-3" method="get" action="<?php echo site_url('admin/users'); ?>">
	<div class="col-sm-6 col-md-4"><input class="form-control" name="search" value="<?php echo html_escape($search); ?>" placeholder="Cari nama, email, atau telepon"></div>
	<div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Cari</button></div>
</form>

<div class="card border-0 shadow-sm">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead class="table-light"><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th class="text-end">Aksi</th></tr></thead>
			<tbody>
				<?php if (!$rows): ?>
					<tr><td colspan="6" class="text-center text-muted py-4">Belum ada pengguna.</td></tr>
				<?php endif; ?>
				<?php foreach ($rows as $row): ?>
					<tr>
						<td><div class="fw-semibold"><?php echo html_escape($row->full_name); ?></div><small class="text-muted"><?php echo html_escape((string) $row->phone); ?></small></td>
						<td><?php echo html_escape($row->email); ?></td>
						<td><?php echo html_escape($row->role_names ?: 'Belum ada role'); ?></td>
						<td><span class="badge <?php echo $row->status === 'active' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo $row->status === 'active' ? 'Aktif' : 'Nonaktif'; ?></span></td>
						<td><?php echo $row->last_login_at ? html_escape($row->last_login_at) : '<span class="text-muted">Belum pernah</span>'; ?></td>
						<td class="text-end text-nowrap">
							<a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('admin/users/show/' . $row->id); ?>" data-modal-url>Lihat</a>
							<a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('admin/users/edit/' . $row->id); ?>" data-modal-url>Edit</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if ($this->pagination->create_links()): ?><div class="card-footer"><?php echo $this->pagination->create_links(); ?></div><?php endif; ?>
</div>

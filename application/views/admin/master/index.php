<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
	<h1 class="h4 mb-0"><?php echo html_escape($title); ?></h1>
	<a class="btn btn-success" href="<?php echo site_url($module_url . '/create'); ?>" data-modal-url>Tambah</a>
</div>

<?php if ($this->session->flashdata('success')): ?>
	<div class="alert alert-success py-2"><?php echo html_escape($this->session->flashdata('success')); ?></div>
<?php endif; ?>

<form class="row g-2 mb-3" method="get" action="<?php echo site_url($module_url); ?>">
	<div class="col-sm-6 col-md-4">
		<input class="form-control" name="search" value="<?php echo html_escape($search); ?>" placeholder="Cari data">
	</div>
	<div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Cari</button></div>
</form>

<div class="card border-0 shadow-sm">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead class="table-light">
				<tr>
					<?php foreach ($fields as $field): ?>
						<?php if (!in_array($field['type'], array('textarea'), true)): ?>
							<th><?php echo html_escape($field['label']); ?></th>
						<?php endif; ?>
					<?php endforeach; ?>
					<th class="text-end">Aksi</th>
				</tr>
			</thead>
			<tbody>
				<?php if (!$rows): ?>
					<tr><td colspan="<?php echo count($fields) + 1; ?>" class="text-center text-muted py-4">Belum ada data.</td></tr>
				<?php endif; ?>
				<?php foreach ($rows as $row): ?>
					<tr>
						<?php foreach ($fields as $field): ?>
							<?php if (!in_array($field['type'], array('textarea'), true)): ?>
								<td>
									<?php if ($field['type'] === 'checkbox'): ?>
										<span class="badge <?php echo (int) $row->{$field['name']} ? 'text-bg-success' : 'text-bg-secondary'; ?>">
											<?php echo (int) $row->{$field['name']} ? 'Aktif' : 'Nonaktif'; ?>
										</span>
									<?php else: ?>
										<?php echo html_escape((string) $row->{$field['name']}); ?>
									<?php endif; ?>
								</td>
							<?php endif; ?>
						<?php endforeach; ?>
						<td class="text-end text-nowrap">
							<a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url($module_url . '/show/' . $row->id); ?>" data-modal-url>Lihat</a>
							<a class="btn btn-sm btn-outline-primary" href="<?php echo site_url($module_url . '/edit/' . $row->id); ?>" data-modal-url>Edit</a>
							<form class="d-inline" method="post" action="<?php echo site_url($module_url . '/delete/' . $row->id); ?>" data-crud-delete data-delete-message="Hapus data ini?">
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

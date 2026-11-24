<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<h1 class="h4 mb-3">Stok Opname</h1>
<div class="card border-0 shadow-sm">
	<div class="card-body">
		<form method="post" action="<?php echo site_url('admin/inventory/opname_store'); ?>">
		<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
		<div class="alert alert-info">Masukkan jumlah fisik setiap batch, lalu simpan untuk menerapkan selisih ke stok.</div>
		<div class="table-responsive">
			<table class="table table-hover align-middle mb-0">
				<thead class="table-light"><tr><th>Produk</th><th>Batch</th><th>Kedaluwarsa</th><th>Stok Sistem</th><th>Stok Fisik</th></tr></thead>
				<tbody>
					<?php foreach ($batches as $batch): ?>
						<tr><td><?php echo html_escape($batch->product_name); ?></td><td><?php echo html_escape($batch->batch_number); ?></td><td><?php echo html_escape($batch->expiration_date); ?></td><td><?php echo number_format((float) $batch->remaining_quantity, 3, ',', '.'); ?></td><td><input class="form-control form-control-sm" name="actual[<?php echo $batch->id; ?>]" type="number" min="0" step="0.001" value="<?php echo html_escape((string) $batch->remaining_quantity); ?>" required></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="mt-3"><button class="btn btn-success" type="submit">Simpan Opname</button></div>
		</form>
	</div>
</div>

<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<h1 class="h4 mb-3">Penyesuaian Stok</h1>
<div class="card border-0 shadow-sm"><div class="card-body">
	<form method="post" action="<?php echo site_url('admin/inventory/adjust_store'); ?>">
		<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
		<div class="row g-3">
			<div class="col-md-6"><label class="form-label">Batch</label><select class="form-select" name="batch_id" required><option value="">- Pilih -</option><?php foreach ($batches as $batch): ?><option value="<?php echo $batch->id; ?>"><?php echo html_escape($batch->product_name . ' / ' . $batch->batch_number . ' (' . $batch->remaining_quantity . ')'); ?></option><?php endforeach; ?></select></div>
			<div class="col-md-3"><label class="form-label">Jenis</label><select class="form-select" name="movement_type" required><option value="stock_in">Stok Masuk</option><option value="stock_out">Stok Keluar</option></select></div>
			<div class="col-md-3"><label class="form-label">Jumlah</label><input class="form-control" name="quantity" type="number" min="0.001" step="0.001" required></div>
			<div class="col-12"><label class="form-label">Catatan</label><textarea class="form-control" name="notes" rows="3"></textarea></div>
		</div>
		<div class="mt-4 d-flex gap-2"><button class="btn btn-success" type="submit">Simpan Penyesuaian</button><a class="btn btn-outline-secondary" href="<?php echo site_url('admin/inventory'); ?>">Kembali</a></div>
	</form>
</div></div>

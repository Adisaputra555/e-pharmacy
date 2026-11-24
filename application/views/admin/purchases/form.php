<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<h1 class="h4 mb-3">Buat Pembelian</h1>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="post" action="<?php echo site_url('admin/purchases/store'); ?>">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Supplier</label><select class="form-select" name="supplier_id" required><option value="">- Pilih -</option><?php foreach ($suppliers as $supplier): ?><option value="<?php echo $supplier->id; ?>"><?php echo html_escape($supplier->name); ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label">Produk</label><select class="form-select" name="product_id" required><option value="">- Pilih -</option><?php foreach ($products as $product): ?><option value="<?php echo $product->id; ?>"><?php echo html_escape($product->code . ' - ' . $product->name); ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Jumlah</label><input class="form-control" name="quantity" type="number" min="0.001" step="0.001" required></div>
<div class="col-md-4"><label class="form-label">Harga Beli</label><input class="form-control" name="purchase_price" type="number" min="0" step="0.01" required></div>
<div class="col-md-4"><label class="form-label">Diskon</label><input class="form-control" name="discount" type="number" min="0" step="0.01" value="0"></div>
<div class="col-12"><label class="form-label">Catatan</label><textarea class="form-control" name="notes" rows="3"></textarea></div>
</div><div class="mt-4 d-flex gap-2"><button class="btn btn-success" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="<?php echo site_url('admin/purchases'); ?>">Kembali</a></div>
</form></div></div>

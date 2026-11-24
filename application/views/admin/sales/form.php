<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<h1 class="h4 mb-3">Transaksi Penjualan Kasir</h1>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="post" action="<?php echo site_url('admin/sales/store'); ?>">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Nama Pelanggan</label><input class="form-control" name="customer_name" value="Walk-in Customer"></div>
<div class="col-md-6"><label class="form-label">Nomor Telepon</label><input class="form-control" name="customer_phone"></div>
<div class="col-md-6"><label class="form-label">Obat Bebas</label><select class="form-select" name="product_id" required><option value="">- Pilih -</option><?php foreach ($products as $product): ?><?php if (!(int) $product->requires_prescription): ?><option value="<?php echo $product->id; ?>"><?php echo html_escape($product->code . ' - ' . $product->name . ' (Rp ' . number_format((float) $product->selling_price,0,',','.') . ')'); ?></option><?php endif; ?><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label">Jumlah</label><input class="form-control" name="quantity" type="number" min="0.001" step="0.001" required></div>
<div class="col-md-3"><label class="form-label">Diskon</label><input class="form-control" name="discount" type="number" min="0" step="0.01" value="0"></div>
</div><div class="mt-4 d-flex gap-2"><button class="btn btn-success" type="submit">Proses Pembayaran Tunai</button><a class="btn btn-outline-secondary" href="<?php echo site_url('admin/sales'); ?>">Kembali</a></div>
</form></div></div>

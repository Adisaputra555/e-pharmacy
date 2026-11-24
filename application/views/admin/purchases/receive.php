<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<h1 class="h4 mb-3"><?php echo html_escape($title); ?></h1>
<div class="card border-0 shadow-sm"><div class="card-body">
<?php foreach ($items as $item): ?>
<form method="post" action="<?php echo site_url('admin/purchases/receive_store/' . $order->id); ?>">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
<input type="hidden" name="item_id" value="<?php echo $item->id; ?>">
<div class="row g-3 align-items-end">
<div class="col-md-3"><label class="form-label">Produk</label><input class="form-control" value="<?php echo html_escape($item->product_name); ?>" disabled></div>
<div class="col-md-2"><label class="form-label">Jumlah</label><input class="form-control" value="<?php echo html_escape((string) $item->quantity); ?>" disabled></div>
<div class="col-md-3"><label class="form-label">Nomor Batch</label><input class="form-control" name="batch_number" value="<?php echo html_escape((string) $item->batch_number); ?>" required></div>
<div class="col-md-3"><label class="form-label">Tanggal Kedaluwarsa</label><input class="form-control" name="expiration_date" type="date" value="<?php echo html_escape((string) $item->expiration_date); ?>" required></div>
<div class="col-md-1"><button class="btn btn-success w-100" type="submit" <?php echo $item->received_quantity >= $item->quantity ? 'disabled' : ''; ?>>Terima</button></div>
</div>
</form>
<?php endforeach; ?>
</div></div>

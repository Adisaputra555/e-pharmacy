<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Laporan Penjualan</h1></div>
<form class="row g-2 mb-3" method="get" action="<?php echo site_url('admin/reports/sales'); ?>"><div class="col-sm-4"><input class="form-control" name="from" type="date" value="<?php echo html_escape($from); ?>"></div><div class="col-sm-4"><input class="form-control" name="to" type="date" value="<?php echo html_escape($to); ?>"></div><div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Terapkan</button></div></form>
<div class="alert alert-success">Total penjualan periode: <strong>Rp <?php echo number_format($total,0,',','.'); ?></strong></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Nomor</th><th>Pelanggan</th><th>Status</th><th>Total</th><th>Tanggal</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?php echo html_escape($row->order_number); ?></td><td><?php echo html_escape($row->customer_name); ?></td><td><?php echo html_escape($row->status); ?></td><td>Rp <?php echo number_format((float) $row->total,0,',','.'); ?></td><td><?php echo html_escape($row->created_at); ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>

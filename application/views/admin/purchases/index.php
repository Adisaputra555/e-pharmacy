<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Pembelian</h1><a class="btn btn-success" href="<?php echo site_url('admin/purchases/create'); ?>">Buat Pembelian</a></div>
<?php if ($this->session->flashdata('success')): ?><div class="alert alert-success py-2"><?php echo html_escape($this->session->flashdata('success')); ?></div><?php endif; ?>
<?php if ($this->session->flashdata('error')): ?><div class="alert alert-danger py-2"><?php echo html_escape($this->session->flashdata('error')); ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Nomor</th><th>Supplier</th><th>Tanggal</th><th>Status</th><th>Total</th><th class="text-end">Aksi</th></tr></thead><tbody>
<?php foreach ($orders as $order): ?>
<tr><td><?php echo html_escape($order->purchase_number); ?></td><td><?php echo html_escape($order->supplier_name); ?></td><td><?php echo html_escape($order->purchase_date); ?></td><td><?php echo html_escape($order->status); ?></td><td>Rp <?php echo number_format((float) $order->total, 0, ',', '.'); ?></td><td class="text-end"><?php if ($order->status !== 'received'): ?><a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('admin/purchases/receive/' . $order->id); ?>">Terima</a><?php else: ?><span class="text-success">Diterima</span><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>

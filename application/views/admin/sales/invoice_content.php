<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $pharmacy = pharmacy_profile(); ?>
<article class="invoice-receipt">
	<header class="invoice-receipt-header">
		<?php if (!empty($pharmacy['store_logo'])): ?>
			<img class="invoice-pharmacy-logo" src="<?php echo html_escape(base_url($pharmacy['store_logo'])); ?>" alt="Logo <?php echo html_escape($pharmacy['store_name']); ?>">
		<?php else: ?>
			<span class="invoice-pharmacy-mark" aria-hidden="true">+</span>
		<?php endif; ?>
		<div class="invoice-pharmacy-info">
			<h3><?php echo html_escape($pharmacy['store_name']); ?></h3>
			<?php if ($pharmacy['store_address']): ?><div><?php echo nl2br(html_escape($pharmacy['store_address'])); ?></div><?php endif; ?>
			<?php if ($pharmacy['store_phone'] || $pharmacy['store_email']): ?>
				<div><?php echo html_escape(implode(' · ', array_filter(array($pharmacy['store_phone'], $pharmacy['store_email'])))); ?></div>
			<?php endif; ?>
		</div>
	</header>

	<div class="invoice-receipt-meta">
		<div class="invoice-meta-item"><span>Nomor transaksi</span><strong><?php echo html_escape($order->order_number); ?></strong></div>
		<div class="invoice-meta-item"><span>Tanggal</span><strong><?php echo html_escape($order->created_at); ?></strong></div>
		<div class="invoice-meta-item"><span>Pelanggan</span><strong><?php echo html_escape($order->customer_name); ?></strong></div>
		<div class="invoice-meta-item"><span>Status</span><strong class="invoice-status"><?php echo html_escape($order->status); ?></strong></div>
	</div>

	<div class="table-responsive invoice-table-wrap">
		<table class="table invoice-table">
			<thead>
				<tr><th>Produk</th><th>Jumlah</th><th>Harga</th><th class="text-end">Total</th></tr>
			</thead>
			<tbody>
				<?php foreach ($items as $item): ?>
					<tr>
						<td data-label="Produk"><?php echo html_escape($item->product_name); ?></td>
						<td data-label="Jumlah"><?php echo number_format((float) $item->quantity, 3, ',', '.'); ?></td>
						<td data-label="Harga">Rp <?php echo number_format((float) $item->unit_price_snapshot, 0, ',', '.'); ?></td>
						<td data-label="Total" class="text-end">Rp <?php echo number_format((float) $item->total, 0, ',', '.'); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="invoice-totals">
		<div><span>Subtotal</span><span>Rp <?php echo number_format((float) $order->subtotal, 0, ',', '.'); ?></span></div>
		<div><span>Diskon</span><span>Rp <?php echo number_format((float) $order->discount, 0, ',', '.'); ?></span></div>
		<div class="invoice-grand-total"><strong>Total pembayaran</strong><strong>Rp <?php echo number_format((float) $order->total, 0, ',', '.'); ?></strong></div>
	</div>

	<?php if ($pharmacy['receipt_footer']): ?>
		<footer class="invoice-receipt-footer"><?php echo nl2br(html_escape($pharmacy['receipt_footer'])); ?></footer>
	<?php endif; ?>
</article>

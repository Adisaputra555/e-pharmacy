<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<h1 class="h4 mb-3">Riwayat Pergerakan Stok</h1>
<div class="card border-0 shadow-sm"><div class="table-responsive">
	<table class="table table-hover align-middle mb-0">
		<thead class="table-light"><tr><th>Waktu</th><th>Produk</th><th>Batch</th><th>Jenis</th><th>Jumlah</th><th>Saldo</th><th>Catatan</th></tr></thead>
		<tbody>
			<?php foreach ($movements as $movement): ?>
				<tr><td><?php echo html_escape($movement->created_at); ?></td><td><?php echo html_escape($movement->product_name); ?></td><td><?php echo html_escape((string) $movement->batch_number); ?></td><td><?php echo html_escape($movement->movement_type); ?></td><td><?php echo number_format((float) $movement->quantity, 3, ',', '.'); ?></td><td><?php echo number_format((float) $movement->balance_after, 3, ',', '.'); ?></td><td><?php echo html_escape((string) $movement->notes); ?></td></tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div></div>

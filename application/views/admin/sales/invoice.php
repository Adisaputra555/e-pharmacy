<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="invoice-page-head">
	<div>
		<div class="invoice-drawer-eyebrow">RINCIAN TRANSAKSI</div>
		<h1><?php echo html_escape($title); ?></h1>
	</div>
	<div class="d-flex align-items-center gap-2">
		<a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('admin/sales'); ?>">Kembali ke penjualan</a>
		<button class="invoice-print-button" type="button" onclick="window.print()" title="Cetak invoice">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M7 14h10v7H7zM17 11h.01"/></svg>
			<span>Cetak invoice</span>
		</button>
	</div>
</div>
<?php echo $invoice_content; ?>

<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
	<h1 class="h4 mb-0">Penjualan Kasir</h1>
	<a class="btn btn-success" href="<?php echo site_url('admin/sales/create'); ?>">Transaksi Baru</a>
</div>
<?php if ($this->session->flashdata('success')): ?><div class="alert alert-success py-2"><?php echo html_escape($this->session->flashdata('success')); ?></div><?php endif; ?>
<div class="card border-0 shadow-sm">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead class="table-light">
				<tr><th>Nomor</th><th>Pelanggan</th><th>Status</th><th>Total</th><th>Tanggal</th><th>Aksi</th></tr>
			</thead>
			<tbody>
				<?php foreach ($orders as $order): ?>
					<tr>
						<td data-label="Nomor"><?php echo html_escape($order->order_number); ?></td>
						<td data-label="Pelanggan"><?php echo html_escape($order->customer_name); ?></td>
						<td data-label="Status"><?php echo html_escape($order->status); ?></td>
						<td data-label="Total">Rp <?php echo number_format((float) $order->total, 0, ',', '.'); ?></td>
						<td data-label="Tanggal"><?php echo html_escape($order->created_at); ?></td>
						<td data-label="Aksi">
							<a
								class="btn btn-sm btn-outline-primary js-invoice-drawer"
								href="<?php echo site_url('admin/sales/invoice/' . $order->id); ?>"
								data-invoice-url="<?php echo site_url('admin/sales/invoice_drawer/' . $order->id); ?>"
								data-invoice-number="<?php echo html_escape($order->order_number); ?>"
								aria-controls="invoiceDrawer"
							>Invoice</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="offcanvas offcanvas-end invoice-drawer" tabindex="-1" id="invoiceDrawer" aria-labelledby="invoiceDrawerTitle">
	<div class="offcanvas-header">
		<div class="invoice-drawer-heading">
			<div class="invoice-drawer-eyebrow">RINCIAN TRANSAKSI</div>
			<h2 class="offcanvas-title" id="invoiceDrawerTitle">Invoice</h2>
		</div>
		<div class="invoice-drawer-actions">
			<button class="invoice-print-button" type="button" id="invoiceDrawerPrint" title="Cetak invoice">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M7 14h10v7H7zM17 11h.01"/></svg>
				<span>Cetak</span>
			</button>
			<button type="button" class="invoice-close-button" data-bs-dismiss="offcanvas" aria-label="Tutup invoice">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
			</button>
		</div>
	</div>
	<div class="offcanvas-body" id="invoiceDrawerContent" aria-live="polite">
		<div class="invoice-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Memuat invoice...</div>
	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function () {
		var drawerElement = document.getElementById('invoiceDrawer');
		var drawerContent = document.getElementById('invoiceDrawerContent');
		var drawerTitle = document.getElementById('invoiceDrawerTitle');
		var requestNumber = 0;
		document.body.appendChild(drawerElement);

		document.addEventListener('click', function (event) {
			var trigger = event.target.closest('.js-invoice-drawer');
			if (!trigger || !window.bootstrap || !window.bootstrap.Offcanvas) return;

			event.preventDefault();
			var currentRequest = ++requestNumber;
			var drawer = window.bootstrap.Offcanvas.getOrCreateInstance(drawerElement);
			drawerTitle.textContent = 'Invoice ' + trigger.getAttribute('data-invoice-number');
			drawerContent.innerHTML = '<div class="invoice-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Memuat invoice...</div>';
			drawer.show();

			fetch(trigger.getAttribute('data-invoice-url'), {
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			})
				.then(function (response) {
					if (!response.ok) throw new Error('HTTP ' + response.status);
					return response.text();
				})
				.then(function (html) {
					if (currentRequest === requestNumber) drawerContent.innerHTML = html;
				})
				.catch(function (error) {
					if (currentRequest !== requestNumber) return;
					var message = document.createElement('div');
					message.className = 'alert alert-danger';
					message.setAttribute('role', 'alert');
					message.textContent = 'Invoice gagal dimuat (' + error.message + '). Coba lagi atau buka invoice di halaman penuh.';
					var fullPageLink = document.createElement('a');
					fullPageLink.className = 'btn btn-sm btn-outline-secondary';
					fullPageLink.href = trigger.href;
					fullPageLink.textContent = 'Buka halaman invoice';
					drawerContent.replaceChildren(message, fullPageLink);
				});
		});

		document.getElementById('invoiceDrawerPrint').addEventListener('click', function () {
			document.body.classList.add('invoice-drawer-printing');
			window.addEventListener('afterprint', function () {
				document.body.classList.remove('invoice-drawer-printing');
			}, { once: true });
			window.print();
		});
	});
</script>

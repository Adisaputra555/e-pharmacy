<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="record-view" data-modal-size="lg">
	<div class="modal-view-toolbar">
		<h1 class="h4 mb-0">Detail Produk</h1>
		<a class="btn btn-outline-primary" href="<?php echo site_url('admin/products/edit/' . $product->id); ?>" data-modal-url data-modal-size="lg">Edit Produk</a>
	</div>

	<section class="record-hero">
		<span class="record-mark" aria-hidden="true">Rx</span>
		<div class="record-hero-copy">
			<div class="record-kicker">Informasi produk</div>
			<h2 class="record-title"><?php echo html_escape($product->name); ?></h2>
			<p class="record-subtitle"><?php echo $product->generic_name ? html_escape($product->generic_name) : 'Nama generik belum diisi'; ?></p>
			<div class="record-badges">
				<span class="record-badge <?php echo (int) $product->is_active ? 'is-success' : 'is-muted'; ?>"><?php echo (int) $product->is_active ? 'Aktif' : 'Nonaktif'; ?></span>
				<span class="record-badge"><?php echo html_escape(ucfirst(str_replace('-', ' ', $product->drug_classification))); ?></span>
				<span class="record-badge"><?php echo (int) $product->requires_prescription ? 'Memerlukan resep' : 'Tanpa resep'; ?></span>
			</div>
		</div>
	</section>

	<div class="record-detail-grid">
		<div class="record-detail-item"><span class="record-detail-label">Kode produk</span><span class="record-detail-value is-mono"><?php echo html_escape($product->code); ?></span></div>
		<div class="record-detail-item"><span class="record-detail-label">Barcode</span><span class="record-detail-value is-mono"><?php echo $product->barcode ? html_escape($product->barcode) : 'Belum diisi'; ?></span></div>
		<div class="record-detail-item"><span class="record-detail-label">Kategori</span><span class="record-detail-value"><?php echo $product->category_name ? html_escape($product->category_name) : 'Belum dipilih'; ?></span></div>
		<div class="record-detail-item"><span class="record-detail-label">Satuan</span><span class="record-detail-value"><?php echo $product->unit_name ? html_escape($product->unit_name) : 'Belum dipilih'; ?></span></div>
		<div class="record-detail-item"><span class="record-detail-label">Produsen</span><span class="record-detail-value"><?php echo $product->manufacturer_name ? html_escape($product->manufacturer_name) : 'Belum dipilih'; ?></span></div>
		<div class="record-detail-item"><span class="record-detail-label">Bentuk sediaan</span><span class="record-detail-value"><?php echo $product->dosage_form ? html_escape($product->dosage_form) : 'Belum diisi'; ?></span></div>
		<div class="record-detail-item"><span class="record-detail-label">Kekuatan</span><span class="record-detail-value"><?php echo $product->strength ? html_escape($product->strength) : 'Belum diisi'; ?></span></div>
		<div class="record-detail-item"><span class="record-detail-label">Nomor registrasi</span><span class="record-detail-value"><?php echo $product->registration_number ? html_escape($product->registration_number) : 'Belum diisi'; ?></span></div>
		<div class="record-detail-item"><span class="record-detail-label">Harga beli</span><span class="record-detail-value">Rp <?php echo number_format((float) $product->purchase_price, 0, ',', '.'); ?></span></div>
		<div class="record-detail-item"><span class="record-detail-label">Harga jual</span><span class="record-detail-value">Rp <?php echo number_format((float) $product->selling_price, 0, ',', '.'); ?></span></div>
		<div class="record-detail-item"><span class="record-detail-label">Stok minimum</span><span class="record-detail-value"><?php echo number_format((float) $product->minimum_stock, 3, ',', '.'); ?></span></div>
		<div class="record-detail-item is-wide"><span class="record-detail-label">Deskripsi</span><span class="record-detail-value record-description"><?php echo $product->description ? nl2br(html_escape($product->description)) : 'Belum ada deskripsi.'; ?></span></div>
	</div>
</div>

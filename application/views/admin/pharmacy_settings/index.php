<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $logo_url = $values['store_logo'] !== '' ? base_url($values['store_logo']) : ''; ?>
<div class="settings-page">
	<div class="settings-page-head">
		<div>
			<div class="settings-page-eyebrow">PROFIL BISNIS</div>
			<h2 class="settings-page-title">Profil apotek</h2>
			<p class="settings-page-description">Atur identitas dan informasi operasional yang digunakan di aplikasi, halaman login, dan struk.</p>
		</div>
		<button class="settings-save-button" type="submit" form="pharmacySettingsForm">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
			Simpan perubahan
		</button>
	</div>

	<?php if ($this->session->flashdata('success')): ?>
		<div class="alert alert-success settings-alert" role="status"><?php echo html_escape($this->session->flashdata('success')); ?></div>
	<?php endif; ?>
	<?php if ($error !== ''): ?>
		<div class="alert alert-danger settings-alert" role="alert"><?php echo html_escape($error); ?></div>
	<?php endif; ?>

	<div class="settings-layout">
		<aside class="settings-sidebar">
			<div class="settings-preview" aria-label="Pratinjau identitas apotek">
				<div class="settings-preview-label">PRATINJAU IDENTITAS</div>
				<div class="settings-preview-brand">
					<img class="settings-preview-logo" id="settingsPreviewLogo" src="<?php echo html_escape($logo_url); ?>" alt="Logo apotek" <?php echo $logo_url === '' ? 'hidden' : ''; ?>>
					<span class="settings-preview-logo" id="settingsPreviewFallback" <?php echo $logo_url !== '' ? 'hidden' : ''; ?> aria-hidden="true">+</span>
					<div style="min-width:0">
						<div class="settings-preview-name" id="settingsPreviewName"><?php echo html_escape($values['store_name']); ?></div>
						<div class="settings-preview-tagline" id="settingsPreviewTagline"><?php echo html_escape($values['store_tagline'] !== '' ? $values['store_tagline'] : 'Profil apotek Anda'); ?></div>
					</div>
				</div>
				<div class="settings-preview-divider"></div>
				<div class="settings-preview-hours">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
					<span id="settingsPreviewHours"><?php echo html_escape($values['store_hours'] !== '' ? $values['store_hours'] : 'Jam operasional belum diatur'); ?></span>
				</div>
			</div>

			<div class="settings-nav-card">
				<div class="settings-nav-title">MENU PENGATURAN</div>
				<nav class="settings-nav-list" aria-label="Navigasi pengaturan apotek">
					<a class="settings-nav-link" href="#settings-identity"><span class="settings-nav-number">01</span> Identitas apotek</a>
					<a class="settings-nav-link" href="#settings-contact"><span class="settings-nav-number">02</span> Alamat & kontak</a>
					<a class="settings-nav-link" href="#settings-licenses"><span class="settings-nav-number">03</span> Informasi perizinan</a>
					<a class="settings-nav-link" href="#settings-receipt"><span class="settings-nav-number">04</span> Struk transaksi</a>
				</nav>
			</div>

			<div class="settings-tip">
				<div class="settings-tip-icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 18h6M10 22h4M8 14c-1.2-.9-2-2.4-2-4a6 6 0 1 1 12 0c0 1.6-.8 3.1-2 4-.7.6-1 1.1-1 2h-6c0-.9-.3-1.4-1-2Z"/></svg>
				</div>
				<div>
					<strong>Tips profil apotek</strong>
					<span>Gunakan logo persegi yang jelas. Informasi ini dapat tampil di halaman login dan dokumen transaksi.</span>
				</div>
			</div>
		</aside>

		<form id="pharmacySettingsForm" class="settings-form" method="post" action="<?php echo site_url('admin/pharmacy_settings/save'); ?>" enctype="multipart/form-data">
			<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">

			<section class="settings-section" id="settings-identity">
				<div class="settings-section-head">
					<div class="settings-section-number">01</div>
					<div>
						<h3>Identitas apotek</h3>
						<p>Nama dan logo utama yang mewakili apotek di dalam aplikasi.</p>
					</div>
				</div>
				<div class="settings-section-body">
					<div class="settings-fields">
						<div class="settings-field">
							<label class="form-label" for="store_name">Nama apotek <span class="text-danger">*</span></label>
							<input class="form-control" id="store_name" name="store_name" value="<?php echo html_escape($values['store_name']); ?>" maxlength="150" required>
							<div class="form-text">Nama ini digunakan pada navigasi dan identitas aplikasi.</div>
						</div>
						<div class="settings-field">
							<label class="form-label" for="store_tagline">Tagline</label>
							<input class="form-control" id="store_tagline" name="store_tagline" value="<?php echo html_escape($values['store_tagline']); ?>" maxlength="200" placeholder="Contoh: Sehat dimulai dari sini">
							<div class="form-text">Kalimat singkat untuk melengkapi nama apotek.</div>
						</div>
						<div class="settings-field settings-field-full">
							<label class="form-label" for="store_logo">Logo apotek</label>
							<div class="settings-logo-box">
								<img class="settings-logo-thumb" id="settingsLogoThumb" src="<?php echo html_escape($logo_url); ?>" alt="Pratinjau logo" <?php echo $logo_url === '' ? 'hidden' : ''; ?>>
								<span class="settings-logo-thumb" id="settingsLogoThumbFallback" <?php echo $logo_url !== '' ? 'hidden' : ''; ?> aria-hidden="true">+</span>
								<div class="settings-logo-copy">
									<strong>Tambahkan logo apotek</strong>
									<span>JPG, PNG, atau WEBP · Maksimal 2 MB</span>
									<label class="settings-upload-button" for="store_logo">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 16V4m0 0L7 9m5-5 5 5"/><path d="M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5"/></svg>
										Pilih file logo
									</label>
									<div class="settings-file-name" id="settingsLogoFileName"><?php echo $logo_url !== '' ? 'Logo saat ini digunakan' : 'Belum ada file baru dipilih'; ?></div>
								</div>
							</div>
							<input class="settings-file-input" id="store_logo" name="store_logo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="settingsLogoFileName">
							<?php if ($logo_url !== ''): ?>
								<div class="form-check settings-remove-logo">
									<input class="form-check-input" id="remove_logo" type="checkbox" name="remove_logo" value="1">
									<label class="form-check-label" for="remove_logo">Hapus logo saat ini</label>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</section>

			<section class="settings-section" id="settings-contact">
				<div class="settings-section-head">
					<div class="settings-section-number">02</div>
					<div>
						<h3>Alamat & kontak</h3>
						<p>Informasi operasional untuk pelanggan dan dokumen transaksi.</p>
					</div>
				</div>
				<div class="settings-section-body">
					<div class="settings-fields">
						<div class="settings-field settings-field-full">
							<label class="form-label" for="store_address">Alamat lengkap</label>
							<textarea class="form-control" id="store_address" name="store_address" rows="3" maxlength="2000" placeholder="Masukkan alamat lengkap apotek"><?php echo html_escape($values['store_address']); ?></textarea>
						</div>
						<div class="settings-field">
							<label class="form-label" for="store_phone">Nomor telepon</label>
							<input class="form-control" id="store_phone" name="store_phone" value="<?php echo html_escape($values['store_phone']); ?>" maxlength="30" inputmode="tel" placeholder="Nomor telepon lokal">
						</div>
						<div class="settings-field">
							<label class="form-label" for="store_email">Email</label>
							<input class="form-control" id="store_email" name="store_email" type="email" value="<?php echo html_escape($values['store_email']); ?>" maxlength="190" placeholder="Alamat email toko">
						</div>
						<div class="settings-field">
							<label class="form-label" for="store_website">Website</label>
							<input class="form-control" id="store_website" name="store_website" type="url" value="<?php echo html_escape($values['store_website']); ?>" maxlength="255" placeholder="https://contoh.id">
						</div>
						<div class="settings-field">
							<label class="form-label" for="store_hours">Jam operasional</label>
							<textarea class="form-control" id="store_hours" name="store_hours" rows="2" maxlength="300" placeholder="Senin-Sabtu, 08.00-21.00"><?php echo html_escape($values['store_hours']); ?></textarea>
							<div class="form-text">Contoh: Senin-Sabtu, 08.00-21.00.</div>
						</div>
					</div>
				</div>
			</section>

			<section class="settings-section" id="settings-licenses">
				<div class="settings-section-head">
					<div class="settings-section-number">03</div>
					<div>
						<h3>Informasi perizinan</h3>
						<p>Data administrasi apotek bersifat opsional dan dapat dilengkapi kapan saja.</p>
					</div>
				</div>
				<div class="settings-section-body">
					<div class="settings-fields">
						<div class="settings-field">
							<label class="form-label" for="pharmacist_name">Apoteker penanggung jawab</label>
							<input class="form-control" id="pharmacist_name" name="pharmacist_name" value="<?php echo html_escape($values['pharmacist_name']); ?>" maxlength="150" placeholder="Nama apoteker">
						</div>
						<div class="settings-field">
							<label class="form-label" for="pharmacist_license">Nomor SIPA</label>
							<input class="form-control" id="pharmacist_license" name="pharmacist_license" value="<?php echo html_escape($values['pharmacist_license']); ?>" maxlength="100" placeholder="Nomor izin praktik">
						</div>
						<div class="settings-field">
							<label class="form-label" for="pharmacy_license">Nomor SIA</label>
							<input class="form-control" id="pharmacy_license" name="pharmacy_license" value="<?php echo html_escape($values['pharmacy_license']); ?>" maxlength="100" placeholder="Nomor izin apotek">
						</div>
						<div class="settings-field">
							<label class="form-label" for="store_npwp">NPWP</label>
							<input class="form-control" id="store_npwp" name="store_npwp" value="<?php echo html_escape($values['store_npwp']); ?>" maxlength="30" placeholder="Nomor pokok wajib pajak">
						</div>
					</div>
				</div>
			</section>

			<section class="settings-section" id="settings-receipt">
				<div class="settings-section-head">
					<div class="settings-section-number">04</div>
					<div>
						<h3>Struk transaksi</h3>
						<p>Beri sentuhan personal pada bagian penutup struk penjualan.</p>
					</div>
				</div>
				<div class="settings-section-body">
					<div class="settings-field">
						<label class="form-label" for="receipt_footer">Pesan pada struk</label>
						<textarea class="form-control" id="receipt_footer" name="receipt_footer" rows="3" maxlength="500" placeholder="Terima kasih telah berbelanja"><?php echo html_escape($values['receipt_footer']); ?></textarea>
						<div class="form-text">Pesan ini akan ditampilkan di bagian bawah struk.</div>
					</div>
				</div>
			</section>

			<div class="settings-form-footer">
				<span class="settings-form-note">Pastikan informasi apotek sudah benar sebelum disimpan.</span>
				<button class="settings-save-button" type="submit">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
					Simpan perubahan
				</button>
			</div>
		</form>
	</div>
</div>

<script>
	(function () {
		var form = document.getElementById('pharmacySettingsForm');
		if (!form) return;

		var logoInput = document.getElementById('store_logo');
		var removeLogo = document.getElementById('remove_logo');
		var previewLogo = document.getElementById('settingsPreviewLogo');
		var previewFallback = document.getElementById('settingsPreviewFallback');
		var thumb = document.getElementById('settingsLogoThumb');
		var thumbFallback = document.getElementById('settingsLogoThumbFallback');
		var fileName = document.getElementById('settingsLogoFileName');
		var nameInput = document.getElementById('store_name');
		var taglineInput = document.getElementById('store_tagline');
		var hoursInput = document.getElementById('store_hours');
		var previewName = document.getElementById('settingsPreviewName');
		var previewTagline = document.getElementById('settingsPreviewTagline');
		var previewHours = document.getElementById('settingsPreviewHours');
		var selectedLogoUrl = '';
		var currentLogoName = <?php echo json_encode($logo_url !== '' ? 'Logo saat ini digunakan' : 'Belum ada file baru dipilih', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

		function setLogoPreview(source) {
			[previewLogo, thumb].forEach(function (image) {
				if (source) {
					image.src = source;
					image.hidden = false;
				} else {
					image.removeAttribute('src');
					image.hidden = true;
				}
			});
			previewFallback.hidden = Boolean(source);
			thumbFallback.hidden = Boolean(source);
		}

		function updateLogoPreview() {
			if (selectedLogoUrl) {
				setLogoPreview(selectedLogoUrl);
				return;
			}
			if (removeLogo && removeLogo.checked) {
				setLogoPreview('');
				fileName.textContent = 'Logo akan dihapus saat disimpan';
				return;
			}
			setLogoPreview(<?php echo json_encode($logo_url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>);
			fileName.textContent = currentLogoName;
		}

		logoInput.addEventListener('change', function () {
			if (selectedLogoUrl) URL.revokeObjectURL(selectedLogoUrl);
			selectedLogoUrl = logoInput.files && logoInput.files.length ? URL.createObjectURL(logoInput.files[0]) : '';
			if (selectedLogoUrl) {
				fileName.textContent = logoInput.files[0].name;
				setLogoPreview(selectedLogoUrl);
			} else {
				updateLogoPreview();
			}
		});

		if (removeLogo) {
			removeLogo.addEventListener('change', updateLogoPreview);
		}

		nameInput.addEventListener('input', function () {
			previewName.textContent = nameInput.value.trim() || 'Nama apotek';
		});
		taglineInput.addEventListener('input', function () {
			previewTagline.textContent = taglineInput.value.trim() || 'Profil apotek Anda';
		});
		hoursInput.addEventListener('input', function () {
			previewHours.textContent = hoursInput.value.trim() || 'Jam operasional belum diatur';
		});
	})();
</script>

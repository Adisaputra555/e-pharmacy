<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$pharmacy = pharmacy_profile();
$contact_items = array();
foreach (array(
	'store_address' => 'Alamat',
	'store_hours' => 'Jam operasional',
	'store_phone' => 'Telepon',
	'store_email' => 'Email',
	'pharmacist_name' => 'Apoteker penanggung jawab',
	'pharmacist_license' => 'Nomor SIPA',
	'pharmacy_license' => 'Nomor SIA'
) as $key => $label) {
	$value = trim($pharmacy[$key]);
	if ($value !== '' || $key === 'store_hours') {
		$contact_items[] = array('key' => $key, 'label' => $label, 'value' => $value !== '' ? $value : 'Belum diatur');
	}
}
$website_scheme = strtolower((string) parse_url($pharmacy['store_website'], PHP_URL_SCHEME));
$show_website = filter_var($pharmacy['store_website'], FILTER_VALIDATE_URL) &&
	in_array($website_scheme, array('http', 'https'), true);
?>
<!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Masuk | <?php echo html_escape($pharmacy['store_name']); ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		:root { color-scheme: light; --ink: #1b3027; --muted: #78877f; --green: #147d59; }
		* { box-sizing: border-box; }
		body { min-width: 320px; min-height: 100vh; margin: 0; color: var(--ink); background: #f5f8f6; font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
		.login-shell { display: grid; grid-template-columns: minmax(0, 1.04fr) minmax(390px, .96fr); min-height: 100vh; }
		.info-panel { position: relative; display: flex; align-items: center; overflow: hidden; min-height: 100vh; padding: clamp(32px, 6vw, 82px); background: #082f22; color: #fff; }
		.info-panel::before, .info-panel::after { position: absolute; z-index: 0; border: 1px solid rgba(202, 244, 215, .09); border-radius: 50%; content: ""; pointer-events: none; }
		.info-panel::before { top: -230px; right: -180px; width: 540px; height: 540px; box-shadow: 0 0 0 42px rgba(202, 244, 215, .025), 0 0 0 88px rgba(202, 244, 215, .02); }
		.info-panel::after { right: 12%; bottom: -270px; width: 390px; height: 390px; background: radial-gradient(circle, rgba(42, 153, 101, .19), transparent 68%); }
		.info-content { position: relative; z-index: 1; width: 100%; max-width: 610px; margin: auto; }
		.brand-row { display: flex; align-items: center; gap: 14px; margin-bottom: clamp(48px, 8vh, 86px); }
		.brand-logo, .brand-mark { display: grid; place-items: center; width: 54px; height: 54px; flex: 0 0 54px; border-radius: 17px; }
		.brand-logo { object-fit: contain; padding: 4px; background: #fff; }
		.brand-mark { background: #b7f36b; color: #0e4934; font-size: 30px; font-weight: 800; box-shadow: 0 10px 24px rgba(0, 0, 0, .13); }
		.brand-name { max-width: 440px; overflow: hidden; color: #fff; font-size: 18px; font-weight: 760; letter-spacing: -.025em; text-overflow: ellipsis; white-space: nowrap; }
		.brand-subtitle { margin-top: 4px; color: #a9c2b4; font-size: 10px; letter-spacing: .14em; text-transform: uppercase; }
		.info-eyebrow { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 16px; color: #c4dfcd; font-size: 10px; font-weight: 750; letter-spacing: .16em; text-transform: uppercase; }
		.info-eyebrow::before { width: 19px; height: 1px; background: #b7f36b; content: ""; }
		.info-title { max-width: 580px; margin: 0; color: #fff; font-size: clamp(32px, 4vw, 48px); font-weight: 700; letter-spacing: -.05em; line-height: 1.12; }
		.info-copy { max-width: 490px; margin: 18px 0 0; color: #b2c7ba; font-size: 14px; line-height: 1.8; }
		.info-details { margin-top: clamp(34px, 6vh, 58px); }
		.info-details-title { margin-bottom: 13px; color: #e5f1e9; font-size: 11px; font-weight: 700; }
		.contact-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
		.contact-item { display: flex; align-items: flex-start; gap: 11px; min-width: 0; padding: 13px; border: 1px solid rgba(230, 246, 235, .1); border-radius: 13px; background: rgba(255, 255, 255, .045); }
		.contact-icon { display: grid; place-items: center; width: 30px; height: 30px; flex: 0 0 30px; border-radius: 9px; background: rgba(183, 243, 107, .12); color: #c1ee8d; }
		.contact-icon svg { width: 15px; height: 15px; }
		.contact-label { margin-bottom: 3px; color: #8eac9a; font-size: 9px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
		.contact-value { overflow-wrap: anywhere; color: #edf5ef; font-size: 11px; line-height: 1.55; }
		.contact-value a { color: inherit; text-decoration: none; }
		.contact-value a:hover { color: #c1ee8d; }
		.info-empty { padding: 13px 15px; border: 1px dashed rgba(230, 246, 235, .16); border-radius: 13px; color: #a9c2b4; font-size: 11px; }
		.auth-panel { display: grid; place-items: center; min-height: 100vh; padding: clamp(28px, 5vw, 72px); background: #f7f9f8; }
		.auth-content { width: 100%; max-width: 410px; }
		.auth-chip { display: inline-flex; align-items: center; gap: 7px; padding: 7px 10px; border: 1px solid #e6eee9; border-radius: 999px; background: #fff; color: #61756a; font-size: 9px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; }
		.auth-chip svg { width: 13px; height: 13px; color: var(--green); }
		.auth-title { margin: 22px 0 7px; color: #1b3027; font-size: 30px; font-weight: 740; letter-spacing: -.045em; }
		.auth-description { margin: 0 0 28px; color: var(--muted); font-size: 12px; line-height: 1.7; }
		.form-label { margin-bottom: 7px; color: #33473d; font-size: 11px; font-weight: 650; }
		.form-control { min-height: 47px; border: 1px solid #dfe8e3; border-radius: 11px; background: #fff; color: #23372d; font-size: 12px; }
		.form-control::placeholder { color: #a5b0aa; }
		.form-control:focus { border-color: #69b994; box-shadow: 0 0 0 3px rgba(36, 155, 106, .11); }
		.password-wrap { position: relative; }
		.password-wrap .form-control { padding-right: 44px; }
		.password-toggle { position: absolute; top: 50%; right: 9px; display: grid; place-items: center; width: 30px; height: 30px; transform: translateY(-50%); border: 0; border-radius: 8px; background: transparent; color: #819087; }
		.password-toggle:hover { background: #f1f6f3; color: var(--green); }
		.password-toggle svg { width: 17px; height: 17px; }
		.btn-login { min-height: 46px; border: 0; border-radius: 11px; background: #147d59; color: #fff; font-size: 12px; font-weight: 700; box-shadow: 0 8px 18px rgba(20, 125, 89, .16); transition: transform .15s ease, background .15s ease; }
		.btn-login:hover { transform: translateY(-1px); background: #106b4b; color: #fff; }
		.auth-note { display: flex; justify-content: center; gap: 7px; margin-top: 34px; color: #8c9991; font-size: 10px; }
		.auth-note svg { width: 13px; height: 13px; color: #6a9c7d; }
		@media (max-width: 900px) {
			.login-shell { grid-template-columns: minmax(0, 1fr) minmax(350px, .95fr); }
			.info-panel { padding: 34px; }
			.brand-row { margin-bottom: 42px; }
			.info-title { font-size: clamp(30px, 4.5vw, 40px); }
			.auth-panel { padding: 34px; }
		}
		@media (max-width: 700px) {
			.login-shell { grid-template-columns: 1fr; }
			.info-panel { min-height: auto; padding: 28px 22px 24px; }
			.brand-row { margin-bottom: 32px; }
			.brand-logo, .brand-mark { width: 46px; height: 46px; flex-basis: 46px; border-radius: 14px; }
			.info-title { max-width: 520px; font-size: 32px; }
			.info-copy { margin-top: 11px; font-size: 12px; }
			.info-details { margin-top: 23px; }
			.contact-grid { gap: 8px; }
			.contact-item { padding: 10px; }
			.auth-panel { min-height: 560px; padding: 38px 23px; }
			.auth-title { margin-top: 18px; font-size: 27px; }
		}
		@media (max-width: 430px) {
			.contact-grid { grid-template-columns: 1fr; }
			.contact-item { align-items: center; }
		}
	</style>
</head>
<body>
	<div class="login-shell">
		<aside class="info-panel">
			<div class="info-content">
				<div class="brand-row">
					<?php if ($pharmacy['store_logo']): ?>
						<img class="brand-logo" src="<?php echo html_escape(base_url($pharmacy['store_logo'])); ?>" alt="Logo <?php echo html_escape($pharmacy['store_name']); ?>">
					<?php else: ?>
						<div class="brand-mark" aria-hidden="true">+</div>
					<?php endif; ?>
					<div class="min-w-0">
						<div class="brand-name"><?php echo html_escape($pharmacy['store_name']); ?></div>
						<div class="brand-subtitle">Informasi apotek</div>
					</div>
				</div>

				<div class="info-eyebrow">Selamat datang</div>
				<h2 class="info-title">Kesehatan terasa lebih dekat.</h2>
				<p class="info-copy"><?php echo html_escape($pharmacy['store_tagline'] ?: 'Masuk untuk mengakses layanan dan informasi dari ' . $pharmacy['store_name'] . '.'); ?></p>

				<div class="info-details">
					<div class="info-details-title">Informasi apotek</div>
					<?php if ($contact_items || $show_website): ?>
						<div class="contact-grid">
							<?php foreach ($contact_items as $contact): ?>
								<div class="contact-item">
									<span class="contact-icon" aria-hidden="true">
										<svg viewBox="0 0 24 24" fill="none">
											<?php if ($contact['key'] === 'store_address'): ?>
												<path d="M19 10.2c0 5-7 11-7 11s-7-6-7-11a7 7 0 1 1 14 0Z" stroke="currentColor" stroke-width="1.7"></path><circle cx="12" cy="10" r="2.2" stroke="currentColor" stroke-width="1.7"></circle>
											<?php elseif ($contact['key'] === 'store_hours'): ?>
												<circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"></circle><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"></path>
											<?php elseif ($contact['key'] === 'store_phone'): ?>
												<path d="M7 3.8h3l1.4 4-2 1.5a15 15 0 0 0 5.3 5.3l1.5-2 4 1.4v3a2 2 0 0 1-2.2 2C10 18.3 5.7 14 5 6a2 2 0 0 1 2-2.2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"></path>
											<?php elseif ($contact['key'] === 'pharmacist_name'): ?>
												<circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.7"></circle><path d="M5.5 20a6.5 6.5 0 0 1 13 0M18 5v5M15.5 7.5h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path>
											<?php elseif ($contact['key'] === 'pharmacist_license' || $contact['key'] === 'pharmacy_license'): ?>
												<path d="M6 3.5h8l4 4V20a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 20V5a1.5 1.5 0 0 1 2-1.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"></path><path d="M13.5 4v4h4M8 13h8M8 16h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path>
											<?php else: ?>
												<rect x="3.5" y="5.5" width="17" height="13" rx="2" stroke="currentColor" stroke-width="1.7"></rect><path d="m4.5 7 7.5 5.5L19.5 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"></path>
											<?php endif; ?>
										</svg>
									</span>
									<span class="min-w-0">
										<span class="contact-label d-block"><?php echo html_escape($contact['label']); ?></span>
										<span class="contact-value d-block"><?php echo nl2br(html_escape($contact['value'])); ?></span>
									</span>
								</div>
							<?php endforeach; ?>
							<?php if ($show_website): ?>
								<div class="contact-item">
									<span class="contact-icon" aria-hidden="true">
										<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"></circle><path d="M3.8 12h16.4M12 3.5a13 13 0 0 1 0 17M12 3.5a13 13 0 0 0 0 17" stroke="currentColor" stroke-width="1.5"></path></svg>
									</span>
									<span class="min-w-0">
										<span class="contact-label d-block">Website</span>
										<span class="contact-value d-block"><a href="<?php echo html_escape($pharmacy['store_website']); ?>" target="_blank" rel="noopener noreferrer"><?php echo html_escape($pharmacy['store_website']); ?></a></span>
									</span>
								</div>
							<?php endif; ?>
						</div>
					<?php else: ?>
						<div class="info-empty">Informasi alamat dan kontak belum diisi.</div>
					<?php endif; ?>
				</div>
			</div>
		</aside>

		<main class="auth-panel">
			<div class="auth-content">
				<div class="auth-chip">
					<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.7"></rect><path d="M8 10V7a4 4 0 1 1 8 0v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"></path></svg>
					Akses akun
				</div>
				<h1 class="auth-title">Masuk ke akun Anda</h1>
				<p class="auth-description">Gunakan email dan kata sandi yang terdaftar untuk melanjutkan.</p>

				<?php if ($this->session->flashdata('error')): ?>
					<div class="alert alert-danger py-2 small" role="alert"><?php echo html_escape($this->session->flashdata('error')); ?></div>
				<?php endif; ?>
				<?php if ($this->session->flashdata('success')): ?>
					<div class="alert alert-success py-2 small" role="status"><?php echo html_escape($this->session->flashdata('success')); ?></div>
				<?php endif; ?>

				<form method="post" action="<?php echo site_url('login'); ?>">
					<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
					<div class="mb-3">
						<label class="form-label" for="email">Email</label>
						<input class="form-control" id="email" name="email" type="email" placeholder="Nama akun" autocomplete="username" inputmode="email" required autofocus>
					</div>
					<div class="mb-4">
						<label class="form-label" for="password">Kata sandi</label>
						<div class="password-wrap">
							<input class="form-control" id="password" name="password" type="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required>
							<button class="password-toggle" id="togglePassword" type="button" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi">
								<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.8 12s3.2-6 9.2-6 9.2 6 9.2 6-3.2 6-9.2 6-9.2-6-9.2-6Z" stroke="currentColor" stroke-width="1.7"></path><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.7"></circle></svg>
							</button>
						</div>
					</div>
					<button class="btn btn-login w-100" type="submit">Masuk</button>
				</form>

				<div class="auth-note">
					<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 19 6v5c0 4.7-3 8-7 10-4-2-7-5.3-7-10V6l7-3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"></path><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
					Jangan bagikan kata sandi Anda kepada siapa pun.
				</div>
			</div>
		</main>
	</div>
	<script>
		(function () {
			const passwordInput = document.getElementById('password');
			const toggleButton = document.getElementById('togglePassword');
			if (!passwordInput || !toggleButton) {
				return;
			}

			toggleButton.addEventListener('click', function () {
				const shouldShow = passwordInput.type === 'password';
				passwordInput.type = shouldShow ? 'text' : 'password';
				toggleButton.setAttribute('aria-label', shouldShow ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
				toggleButton.title = shouldShow ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi';
			});
		})();
	</script>
</body>
</html>

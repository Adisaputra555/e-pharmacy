<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $pharmacy = pharmacy_profile(); ?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Registrasi | <?php echo html_escape($pharmacy['store_name']); ?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3"><div class="card border-0 shadow-sm" style="max-width:460px;width:100%"><div class="card-body p-4">
<div class="mb-3"><div class="fw-bold text-success"><?php echo html_escape($pharmacy['store_name']); ?></div><h1 class="h4 mb-0 mt-2">Registrasi Pelanggan</h1></div>
<?php if ($this->session->flashdata('error')): ?><div class="alert alert-danger py-2"><?php echo $this->session->flashdata('error'); ?></div><?php endif; ?>
<form method="post" action="<?php echo site_url('register'); ?>">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
<div class="mb-3"><label class="form-label">Nama Lengkap</label><input class="form-control" name="full_name" required></div>
<div class="mb-3"><label class="form-label">Email</label><input class="form-control" name="email" type="email" required></div>
<div class="mb-3"><label class="form-label">Telepon</label><input class="form-control" name="phone" required></div>
<div class="mb-3"><label class="form-label">Password</label><input class="form-control" name="password" type="password" minlength="8" required></div>
<button class="btn btn-success w-100" type="submit">Daftar</button>
</form><div class="text-center mt-3"><a href="<?php echo site_url('login'); ?>">Sudah punya akun? Masuk</a></div>
</div></div></body></html>

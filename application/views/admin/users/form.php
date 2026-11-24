<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<h1 class="h4 mb-3"><?php echo html_escape($title); ?></h1>
<form method="post" action="<?php echo $action; ?>" data-crud-form>
	<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
	<div class="row g-3">
		<div class="col-12"><div class="crud-form-section"><span class="crud-section-index">01</span><div><h3>Informasi akun</h3><p>Data profil dan kredensial untuk masuk ke aplikasi.</p></div></div></div>
		<div class="col-md-6">
			<label class="form-label">Nama lengkap</label>
			<input class="form-control" name="full_name" value="<?php echo $row ? html_escape($row->full_name) : ''; ?>" maxlength="150" required>
		</div>
		<div class="col-md-6">
			<label class="form-label">Email</label>
			<input class="form-control" name="email" type="email" value="<?php echo $row ? html_escape($row->email) : ''; ?>" maxlength="190" required>
		</div>
		<div class="col-md-6">
			<label class="form-label">Telepon</label>
			<input class="form-control" name="phone" value="<?php echo $row ? html_escape((string) $row->phone) : ''; ?>" maxlength="30">
		</div>
		<div class="col-md-6">
			<label class="form-label">Password<?php echo $row ? ' baru (opsional)' : ''; ?></label>
			<input class="form-control" name="password" type="password" minlength="8" <?php echo $row ? '' : 'required'; ?> autocomplete="new-password">
			<?php if ($row): ?><small class="text-muted">Kosongkan jika tidak ingin mengganti password.</small><?php endif; ?>
		</div>
		<div class="col-md-6">
			<label class="form-label">Status akun</label>
			<select class="form-select" name="status" required>
				<option value="active" <?php echo !$row || $row->status === 'active' ? 'selected' : ''; ?>>Aktif</option>
				<option value="inactive" <?php echo $row && $row->status === 'inactive' ? 'selected' : ''; ?>>Nonaktif</option>
			</select>
		</div>
		<div class="col-12">
			<div class="crud-form-section mb-3"><span class="crud-section-index">02</span><div><h3>Hak akses</h3><p>Pilih role untuk menentukan menu yang dapat digunakan.</p></div></div>
			<label class="form-label d-block">Role</label>
			<div class="row g-2">
				<?php foreach ($roles as $role): ?>
					<div class="col-sm-6">
						<label class="form-check role-choice border p-3 ps-5 h-100">
							<input class="form-check-input" type="checkbox" name="role_ids[]" value="<?php echo (int) $role->id; ?>" <?php echo in_array((int) $role->id, $selected_roles, true) ? 'checked' : ''; ?>>
							<span class="form-check-label fw-semibold"><?php echo html_escape($role->name); ?></span>
							<small class="d-block text-muted"><?php echo html_escape($role->slug); ?></small>
						</label>
					</div>
				<?php endforeach; ?>
			</div>
			<small class="text-muted">Role mengatur menu dan hak akses akun. Pilih minimal satu.</small>
		</div>
	</div>
	<div class="crud-form-actions d-flex gap-2">
		<button class="btn btn-success" type="submit">Simpan</button>
		<a class="btn btn-outline-secondary" href="<?php echo site_url('admin/users'); ?>">Kembali</a>
	</div>
</form>

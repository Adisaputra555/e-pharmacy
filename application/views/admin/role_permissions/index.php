<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="mb-3">
	<h1 class="h4 mb-1">Akses Role</h1>
	<div class="text-muted small">Tentukan menu/modul yang dapat dibuka setiap role. Akses URL langsung juga diperiksa di server.</div>
</div>

<?php if ($this->session->flashdata('success')): ?><div class="alert alert-success py-2"><?php echo html_escape($this->session->flashdata('success')); ?></div><?php endif; ?>
<?php if ($this->session->flashdata('error')): ?><div class="alert alert-danger py-2"><?php echo html_escape($this->session->flashdata('error')); ?></div><?php endif; ?>

<div class="alert alert-info py-2 small">
	Administrator selalu memiliki semua akses. Dashboard tetap tersedia untuk role operasional. Jika pengguna memiliki beberapa role, aksesnya merupakan gabungan izin dari seluruh role tersebut.
</div>

<form method="post" action="<?php echo site_url('admin/role_permissions/save'); ?>">
	<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
	<?php foreach ($roles as $role): ?>
		<?php
		$role_permissions = isset($permissions[$role->id]) ? $permissions[$role->id] : array();
		$grouped_modules = array();
		foreach ($modules as $permission_key => $module) {
			$grouped_modules[$module['group']][$permission_key] = $module;
		}
		$is_administrator = $role->slug === 'administrator';
		$is_customer = $role->slug === 'pelanggan';
		?>
		<div class="card border-0 shadow-sm mb-3">
			<div class="card-header d-flex justify-content-between align-items-center py-3">
				<div><strong><?php echo html_escape($role->name); ?></strong> <small class="text-muted ms-1"><?php echo html_escape($role->slug); ?></small></div>
				<?php if ($is_administrator): ?><span class="badge text-bg-success">Akses penuh tetap</span><?php endif; ?>
			</div>
			<div class="card-body">
				<div class="row g-3">
					<?php foreach ($grouped_modules as $group_name => $group_modules): ?>
						<div class="col-12 col-md-6 col-xl-4">
							<div class="small fw-bold text-muted mb-2"><?php echo html_escape($group_name); ?></div>
							<?php foreach ($group_modules as $permission_key => $module): ?>
								<?php
								$is_required = !$is_customer && !empty($module['required']);
								$is_checked = $is_administrator || $is_required || in_array($permission_key, $role_permissions, true);
								$is_disabled = $is_administrator || $is_customer || $is_required;
								?>
								<label class="form-check mb-2">
									<input class="form-check-input" type="checkbox" name="permissions[<?php echo (int) $role->id; ?>][]" value="<?php echo html_escape($permission_key); ?>" <?php echo $is_checked ? 'checked' : ''; ?> <?php echo $is_disabled ? 'disabled' : ''; ?>>
									<span class="form-check-label"><?php echo html_escape($module['name']); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<?php if ($is_customer): ?><small class="text-muted">Role pelanggan tidak mendapatkan akses ke panel operasional.</small><?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
	<button class="btn btn-success" type="submit">Simpan Akses Role</button>
</form>

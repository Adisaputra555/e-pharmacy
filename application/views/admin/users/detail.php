<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="record-view">
	<div class="modal-view-toolbar">
		<h1 class="h4 mb-0"><?php echo html_escape($title); ?></h1>
		<a class="btn btn-outline-primary" href="<?php echo site_url('admin/users/edit/' . $user->id); ?>" data-modal-url>Edit Pengguna</a>
	</div>

	<section class="record-hero">
		<span class="record-mark" aria-hidden="true">U</span>
		<div class="record-hero-copy">
			<div class="record-kicker">Profil pengguna</div>
			<h2 class="record-title"><?php echo html_escape($user->full_name); ?></h2>
			<p class="record-subtitle"><?php echo html_escape($user->email); ?></p>
			<div class="record-badges">
				<span class="record-badge <?php echo $user->status === 'active' ? 'is-success' : 'is-muted'; ?>"><?php echo $user->status === 'active' ? 'Aktif' : 'Nonaktif'; ?></span>
				<?php foreach ($role_names as $role_name): ?><span class="record-badge"><?php echo html_escape($role_name); ?></span><?php endforeach; ?>
				<?php if (!$role_names): ?><span class="record-badge is-muted">Belum ada role</span><?php endif; ?>
			</div>
		</div>
	</section>

	<div class="record-detail-grid">
		<div class="record-detail-item"><span class="record-detail-label">Telepon</span><span class="record-detail-value"><?php echo $user->phone ? html_escape($user->phone) : 'Belum diisi'; ?></span></div>
		<div class="record-detail-item"><span class="record-detail-label">Login terakhir</span><span class="record-detail-value"><?php echo $user->last_login_at ? html_escape($user->last_login_at) : 'Belum pernah login'; ?></span></div>
		<div class="record-detail-item is-wide"><span class="record-detail-label">Akun dibuat</span><span class="record-detail-value"><?php echo html_escape($user->created_at); ?></span></div>
	</div>
</div>

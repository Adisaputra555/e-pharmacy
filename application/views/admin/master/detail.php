<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="record-view">
	<div class="modal-view-toolbar">
		<h1 class="h4 mb-0"><?php echo html_escape($title); ?></h1>
		<a class="btn btn-outline-primary" href="<?php echo site_url($module_url . '/edit/' . $row->id); ?>" data-modal-url>Edit Data</a>
	</div>

	<?php if (isset($row->name)): ?>
		<section class="record-hero">
			<span class="record-mark" aria-hidden="true">MD</span>
			<div class="record-hero-copy">
				<div class="record-kicker">Informasi data</div>
				<h2 class="record-title"><?php echo html_escape($row->name); ?></h2>
				<?php if (isset($row->code) && $row->code !== ''): ?><p class="record-subtitle"><?php echo html_escape($row->code); ?></p><?php endif; ?>
				<?php if (isset($row->is_active)): ?><div class="record-badges"><span class="record-badge <?php echo (int) $row->is_active ? 'is-success' : 'is-muted'; ?>"><?php echo (int) $row->is_active ? 'Aktif' : 'Nonaktif'; ?></span></div><?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<div class="record-detail-grid">
		<?php foreach ($fields as $field): ?>
			<?php if (isset($row->name) && $field['name'] === 'name') { continue; } ?>
			<div class="record-detail-item<?php echo $field['type'] === 'textarea' ? ' is-wide' : ''; ?>">
				<span class="record-detail-label"><?php echo html_escape($field['label']); ?></span>
				<span class="record-detail-value<?php echo $field['type'] === 'textarea' ? ' record-description' : ''; ?>">
					<?php if (isset($field['type']) && $field['type'] === 'checkbox'): ?>
						<span class="record-badge <?php echo (int) $row->{$field['name']} ? 'is-success' : 'is-muted'; ?>"><?php echo (int) $row->{$field['name']} ? 'Aktif' : 'Nonaktif'; ?></span>
					<?php else: ?>
						<?php echo $row->{$field['name']} !== null && $row->{$field['name']} !== '' ? nl2br(html_escape((string) $row->{$field['name']})) : 'Belum diisi'; ?>
					<?php endif; ?>
				</span>
			</div>
		<?php endforeach; ?>
	</div>
</div>

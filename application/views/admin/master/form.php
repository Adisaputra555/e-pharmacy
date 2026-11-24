<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
	<h1 class="h4 mb-0"><?php echo html_escape($title); ?></h1>
</div>

<div class="card border-0 shadow-sm">
	<div class="card-body">
		<form method="post" action="<?php echo $action; ?>" data-crud-form>
			<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
			<div class="row g-3">
				<div class="col-12"><div class="crud-form-section"><span class="crud-section-index">01</span><div><h3>Informasi dasar</h3><p>Lengkapi detail data berikut sebelum menyimpan.</p></div></div></div>
				<?php foreach ($fields as $field): ?>
					<div class="<?php echo $field['type'] === 'textarea' ? 'col-12' : 'col-md-6'; ?>">
						<?php if ($field['type'] === 'checkbox'): ?>
							<div class="form-check mt-4">
								<input class="form-check-input" id="<?php echo $field['name']; ?>" name="<?php echo $field['name']; ?>" type="checkbox" value="1" <?php echo (!$row || (int) $row->{$field['name']}) ? 'checked' : ''; ?>>
								<label class="form-check-label" for="<?php echo $field['name']; ?>"><?php echo html_escape($field['label']); ?></label>
							</div>
						<?php elseif ($field['type'] === 'textarea'): ?>
							<label class="form-label" for="<?php echo $field['name']; ?>"><?php echo html_escape($field['label']); ?></label>
							<textarea class="form-control" id="<?php echo $field['name']; ?>" name="<?php echo $field['name']; ?>" rows="4"><?php echo $row ? html_escape((string) $row->{$field['name']}) : ''; ?></textarea>
						<?php else: ?>
							<label class="form-label" for="<?php echo $field['name']; ?>"><?php echo html_escape($field['label']); ?></label>
							<input class="form-control" id="<?php echo $field['name']; ?>" name="<?php echo $field['name']; ?>" type="<?php echo html_escape($field['type']); ?>" value="<?php echo $row ? html_escape((string) $row->{$field['name']}) : ''; ?>" <?php echo isset($field['required']) && $field['required'] ? 'required' : ''; ?>>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="crud-form-actions d-flex gap-2">
				<button class="btn btn-success" type="submit">Simpan</button>
				<a class="btn btn-outline-secondary" href="<?php echo site_url($module_url); ?>">Kembali</a>
			</div>
		</form>
	</div>
</div>

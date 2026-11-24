<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$pharmacy = pharmacy_profile();
$pharmacy_name = $pharmacy['store_name'];
$roles = $this->session->userdata('roles');
$roles = is_array($roles) ? $roles : array();
$sidebar_role = in_array('administrator', $roles, true) ? 'Administrator' : ($roles ? ucfirst(str_replace('-', ' ', $roles[0])) : 'Pengguna');
$permissions = $this->session->userdata('permissions');
$permissions = is_array($permissions) ? $permissions : array();
$can_view_orders = in_array('administrator', $roles, true) || in_array('orders', $permissions, true);
$can_open_pharmacy_settings = in_array('administrator', $roles, true) || in_array('pharmacy_settings', $permissions, true);
$pending_orders_count = $can_view_orders ? (int) $this->db->where('status', 'pending')->count_all_results('orders') : 0;
$current_uri = trim(uri_string(), '/');
$this->config->load('role_permissions', true);
$permission_modules = $this->config->item('modules', 'role_permissions');
$navigation_groups = array();
foreach ($permission_modules as $permission_key => $module) {
	if (!isset($navigation_groups[$module['group']])) {
		$navigation_groups[$module['group']] = array('label' => $module['group'], 'items' => array());
	}
	$navigation_groups[$module['group']]['items'][] = array(
		'name' => $module['name'],
		'url' => $module['url'],
		'icon' => $module['icon'],
		'permission' => $permission_key
	);
}
$procurementFeatureEnabled = false;
if (defined('ENVIRONMENT') && ENVIRONMENT === 'demo') {
	$procurementConfigPath = APPPATH . 'config' . DIRECTORY_SEPARATOR . 'secrets' .
		DIRECTORY_SEPARATOR . 'p16_procurement.php';
	if (is_file($procurementConfigPath)) {
		$procurementConfig = require $procurementConfigPath;
		if (is_array($procurementConfig) &&
			isset($procurementConfig['procurement_recommendations_enabled']) &&
			$procurementConfig['procurement_recommendations_enabled'] === true) {
			$markerResult = $this->db->query(
				'SELECT environment FROM environment_marker WHERE id = 1 LIMIT 1'
			);
			if (!$markerResult) {
				log_message('error', 'Procurement menu disabled because the environment marker could not be read.');
			} else {
				$marker = $markerResult->row_array();
				if (!$marker) {
					log_message('error', 'Procurement menu disabled because the environment marker is missing.');
				} else {
					require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Procurement_service.php';
					$procurementFeatureEnabled = Procurement_service::isFeatureEnabled(
						ENVIRONMENT,
						$procurementConfig,
						isset($marker['environment']) ? $marker['environment'] : null
					);
				}
			}
		}
	}
}
if ($procurementFeatureEnabled && isset($navigation_groups['OPERASIONAL'])) {
	$navigation_groups['OPERASIONAL']['items'][] = array(
		'name' => 'Rekomendasi pengadaan',
		'url' => 'admin/procurement',
		'icon' => '▧',
		'permission' => 'inventory'
	);
}
$demoBannerEnabled = false;
if (defined('ENVIRONMENT') && ENVIRONMENT === 'demo') {
	$demoMarkerResult = $this->db->query(
		'SELECT environment FROM environment_marker WHERE id = 1 LIMIT 1'
	);
	if ($demoMarkerResult) {
		$demoMarker = $demoMarkerResult->row_array();
		$demoBannerEnabled = $demoMarker &&
			isset($demoMarker['environment']) &&
			$demoMarker['environment'] === 'demo';
	}
}
if (in_array('administrator', $roles, true)) {
	$admin_items = isset($navigation_groups['ADMINISTRASI']) ? $navigation_groups['ADMINISTRASI']['items'] : array();
	$admin_items = array_merge($admin_items, array(
		array('name' => 'Pengguna & Role', 'url' => 'admin/users', 'icon' => '♙', 'permission' => 'users'),
		array('name' => 'Akses Role', 'url' => 'admin/role_permissions', 'icon' => '▣', 'permission' => 'role_permissions'),
		array('name' => 'Aktivitas Pengguna', 'url' => 'admin/activity_logs', 'icon' => '◷', 'permission' => 'activity_logs')
	));
	$navigation_groups['ADMINISTRASI'] = array('label' => 'ADMINISTRASI', 'items' => $admin_items);
}
$navigation = array_values($navigation_groups);
?>
<!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token-name" content="<?php echo html_escape($this->security->get_csrf_token_name()); ?>">
	<meta name="csrf-token-hash" content="<?php echo html_escape($this->security->get_csrf_hash()); ?>">
	<title><?php echo html_escape(isset($title) ? $title : 'Dashboard'); ?> | <?php echo html_escape($pharmacy_name); ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		:root {
			--ink: #172a25;
			--muted: #788780;
			--line: #e8eeeb;
			--canvas: #f5f8f6;
			--green: #147d59;
			--green-dark: #0c6044;
			--sidebar: #061f17;
			--sidebar-raised: #0c2b20;
			--lime: #b7f36b;
		}
		body { background: var(--canvas); color: var(--ink); font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
		.app-shell { min-height: 100vh; }
		.sidebar {
			--bs-offcanvas-width: 258px;
			flex: 0 0 258px;
			width: 258px;
			background-color: var(--sidebar) !important;
			background-image: none !important;
			color: #dce9e2;
			border: 0;
			border-right: 1px solid #102f24;
			box-shadow: none;
			transition: flex-basis .2s ease, width .2s ease;
		}
		.sidebar-inner { position: sticky; top: 0; display: flex; flex-direction: column; height: 100vh; overflow-y: auto; padding: 20px 13px 14px; }
		.brand { display: flex; align-items: center; gap: 11px; padding: 2px 8px 20px; margin-bottom: 14px; border-bottom: 1px solid rgba(220, 239, 228, .1); color: #fff; text-decoration: none; }
		.brand-mark { display: grid; place-items: center; width: 37px; height: 37px; border-radius: 12px; color: #123b25; font-weight: 850; font-size: 20px; background: var(--lime); box-shadow: none; }
		.brand-logo { width: 37px; height: 37px; flex: 0 0 37px; border-radius: 12px; object-fit: contain; background: #fff; padding: 3px; }
		.brand-title { max-width: 175px; overflow: hidden; font-size: 14px; font-weight: 760; letter-spacing: -.025em; line-height: 1.1; text-overflow: ellipsis; white-space: nowrap; }
		.brand-subtitle { color: #86a497; font-size: 9px; margin-top: 4px; letter-spacing: .13em; text-transform: uppercase; }
		.nav-group { margin: 5px 0 18px; }
		.nav-heading { color: #698679; font-size: 9px; font-weight: 780; letter-spacing: .16em; padding: 0 11px; margin-bottom: 7px; }
		.sidebar-link { display: flex; align-items: center; gap: 11px; min-height: 41px; padding: 8px 11px; margin: 3px 0; border: 1px solid transparent; border-radius: 10px; color: #a9beb3; text-decoration: none; font-size: 12px; font-weight: 560; transition: background .16s ease, color .16s ease, border-color .16s ease; }
		.sidebar-link:hover { background: #0b2b20; color: #fff; }
		.sidebar-link.active { border-color: #1d4734; color: #fff; background: #123426; box-shadow: inset 3px 0 var(--lime); font-weight: 700; }
		.nav-icon { display: grid; place-items: center; width: 20px; color: #739387; font-size: 15px; line-height: 1; }
		.sidebar-link.active .nav-icon { color: var(--lime); }
		.sidebar-footer { margin-top: auto; padding: 14px 0 0; border-top: 1px solid rgba(220, 239, 228, .1); }
		.sidebar-profile { display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid rgba(220, 239, 228, .12); border-radius: 13px; background: var(--sidebar-raised); }
		.sidebar-profile-avatar { display: grid; place-items: center; flex: 0 0 34px; width: 34px; height: 34px; border-radius: 10px; background: #214d38; color: var(--lime); font-size: 12px; font-weight: 800; }
		.sidebar-profile-name { overflow: hidden; color: #f3f8f5; font-size: 11px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
		.sidebar-profile-role { margin-top: 3px; color: #8ca699; font-size: 9px; }
		.main-area { min-width: 0; flex: 1; }
		.topbar { position: sticky; z-index: 1020; top: 0; display: grid !important; grid-template-columns: auto minmax(180px, 1fr) auto; align-items: center; gap: 18px; min-height: 60px; border-bottom: 1px solid #e8edf4; background: rgba(255, 255, 255, .96); backdrop-filter: blur(14px); }
		.topbar-start, .topbar-actions { display: flex; align-items: center; gap: 12px; min-width: 0; }
		.topbar-actions { justify-content: flex-end; }
		.topbar-start { gap: 14px; }
		.topbar .mobile-menu { display: grid; place-items: center; width: 34px; height: 34px; flex: 0 0 34px; padding: 0; border: 0; border-radius: 8px; background: transparent; color: #526174; }
		.topbar .mobile-menu:hover, .topbar .mobile-menu:focus-visible { background: #eef3ff; color: #0b2a24; }
		.topbar .mobile-menu svg { width: 19px; height: 19px; }
		.topbar-search { position: relative; width: 100%; max-width: 420px; }
		.topbar-search .form-control { min-height: 38px; padding: 8px 14px 8px 39px; border: 1px solid transparent; border-radius: 8px; background: #eef3ff; color: #243a2f; font-size: 11px; box-shadow: none; }
		.topbar-search .form-control:focus { border-color: #cbd9f2; background: #f5f7ff; box-shadow: 0 0 0 3px rgba(61, 103, 172, .08); }
		.topbar-search-icon { position: absolute; top: 50%; left: 14px; width: 15px; height: 15px; color: #84958b; transform: translateY(-50%); pointer-events: none; }
		.header-icon { position: relative; display: grid; place-items: center; width: 36px; height: 36px; flex: 0 0 36px; border: 1px solid transparent; border-radius: 9px; background: transparent; color: #536477; transition: background .15s ease, color .15s ease, border-color .15s ease; }
		.header-icon:hover, .header-icon:focus-visible { border-color: transparent; background: #eef3ff; color: #0b2a24; }
		.header-icon svg { width: 17px; height: 17px; }
		.header-notification-dot { position: absolute; top: 5px; right: 5px; width: 8px; height: 8px; border: 2px solid #fff; border-radius: 50%; background: #d64242; }
		.header-profile-toggle { display: flex; align-items: center; gap: 8px; min-height: 42px; padding: 4px 7px 4px 4px; border: 0; border-radius: 9px; background: transparent; color: #26384a; }
		.header-profile-toggle:hover, .header-profile-toggle[aria-expanded="true"] { background: #f2f5fb; }
		.header-profile-avatar { display: grid; place-items: center; width: 30px; height: 30px; flex: 0 0 30px; border-radius: 50%; background: #0b2a24; color: #fff; font-size: 11px; font-weight: 750; }
		.header-profile-copy { display: flex; flex-direction: column; min-width: 0; text-align: left; }
		.header-profile-name { max-width: 160px; overflow: hidden; font-size: 10px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
		.header-profile-role { max-width: 180px; overflow: hidden; margin-top: 2px; color: #718198; font-size: 8px; text-overflow: ellipsis; white-space: nowrap; }
		.header-profile-caret { width: 13px; height: 13px; flex: 0 0 13px; color: #7b8998; transition: transform .15s ease; }
		.header-profile-toggle[aria-expanded="true"] .header-profile-caret { transform: rotate(180deg); }
		.header-menu { min-width: 210px; padding: 8px; border: 1px solid #edf1ee; border-radius: 15px; background: #fff; box-shadow: 0 14px 36px rgba(28, 57, 44, .13); }
		.header-menu-caption { padding: 9px 10px 7px; color: #829087; font-size: 9px; font-weight: 800; letter-spacing: .08em; }
		.header-menu .dropdown-item { display: flex; align-items: center; gap: 10px; min-height: 36px; border-radius: 9px; color: #293a31; font-size: 11px; }
		.header-menu .dropdown-item:hover { background: #f3f8f5; color: #126343; }
		.header-menu .dropdown-item svg { width: 15px; height: 15px; color: #819188; }
		.header-menu .dropdown-divider { margin: 6px 0; }
		.header-menu .header-menu-logout { color: #c94848; }
		.header-menu .header-menu-logout:hover { background: #fff4f3; color: #bd3737; }
		.header-notification-menu { width: 255px; padding: 14px; border: 1px solid #edf1ee; border-radius: 15px; box-shadow: 0 14px 36px rgba(28, 57, 44, .13); }
		.header-notification-title { color: #26382f; font-size: 12px; font-weight: 750; }
		.header-notification-copy { color: #849188; font-size: 10px; line-height: 1.6; }
		.header-notification-link { display: inline-flex; margin-top: 10px; color: #147d59; font-size: 10px; font-weight: 700; text-decoration: none; }
		.header-notification-link:hover { color: #0c6044; }
		#accountModal .modal-content { overflow: hidden; border: 1px solid #e8eeeb; border-radius: 19px; box-shadow: 0 24px 70px rgba(10, 35, 25, .2); }
		#accountModal .modal-header { border-bottom-color: #edf2ef; background: #f8fbf9; }
		.page-heading { font-size: 19px; font-weight: 760; letter-spacing: -.035em; margin: 0; }
		.page-context { color: var(--muted); font-size: 11px; margin-top: 3px; }
		.content-area { padding: 25px clamp(16px, 3vw, 38px) 40px; max-width: 1700px; margin: 0 auto; }
		.demo-global-banner { display: flex; align-items: center; justify-content: center; min-height: 34px; padding: 7px 16px; background: #fff4cc; border-bottom: 1px solid #ead89a; color: #73520a; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
		.content-area .card { border: 1px solid var(--line) !important; border-radius: 15px; box-shadow: 0 5px 20px rgba(28, 57, 44, .035) !important; }
		.content-area .card-header, .content-area .card-footer { background: #fff; border-color: var(--line); }
		.content-area .btn { border-radius: 9px; font-size: 12px; font-weight: 650; padding: 8px 13px; }
		.content-area .btn-sm { padding: 5px 10px; font-size: 11px; }
		.content-area .btn-success { background: var(--green); border-color: var(--green); }
		.content-area .btn-success:hover { background: var(--green-dark); }
		.content-area .form-control, .content-area .form-select { min-height: 39px; border-radius: 9px; border-color: #dfe8e3; font-size: 12px; box-shadow: none; }
		.content-area .form-control:focus, .content-area .form-select:focus { border-color: #60b994; box-shadow: 0 0 0 3px rgba(36, 155, 106, .11); }
		.content-area > form.row.mb-3 { align-items: center; margin: 0 0 16px !important; padding: 10px 12px; border: 1px solid #e7eee9; border-radius: 15px; background: #fff; box-shadow: 0 7px 22px rgba(28, 57, 44, .035); }
		.content-area > form.row.mb-3 .form-control { min-height: 40px; border-color: #e5ece7; background: #f9fbfa; }
		.content-area > form.row.mb-3 .form-control:focus { background: #fff; }
		.content-area > form.row.mb-3 .btn-outline-secondary { min-height: 40px; border-color: #dce9e0; background: #eff8f2; color: #176d4c; }
		.content-area > form.row.mb-3 .btn-outline-secondary:hover { border-color: #c9e2d2; background: #e4f3e9; color: #10583d; }
		.table-toolbar { display: flex; align-items: center; gap: 10px 12px; flex-wrap: wrap; margin: 0; padding: 13px 15px; border-bottom: 1px solid #edf2ef; background: #fff; }
		.table-toolbar > [class*="col-"] { width: auto; max-width: none; flex: 0 0 auto; margin: 0; padding: 0; }
		.table-toolbar > .col-sm-6 { max-width: 360px; flex: 1 1 280px; }
		.table-toolbar .form-control { min-height: 39px; border-color: #e5ece7; border-radius: 11px; background: #f9fbfa; font-size: 11px; }
		.table-toolbar .form-control:focus { background: #fff; }
		.table-toolbar input.form-control { width: min(360px, 100%); }
		.table-toolbar .col-auto { display: flex; align-items: center; }
		.table-toolbar .btn-outline-secondary { min-height: 39px; border-color: #dce9e0; background: #eff8f2; color: #176d4c; }
		.table-toolbar-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }
		.table-results-count { color: #8a9890; font-size: 10px; white-space: nowrap; }
		.table-toolbar .table-status-filter { min-width: 145px; width: auto; min-height: 39px; padding: 8px 30px 8px 12px; border-color: #e5ece7; border-radius: 11px; background-color: #fff; color: #405148; font-size: 11px; }
		.table-toolbar .table-export-button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 39px; padding: 8px 13px; border: 1px solid #dce9e0; border-radius: 11px; background: #0b3324; color: #fff; font-size: 11px; font-weight: 700; transition: background .15s ease, transform .15s ease; }
		.table-toolbar .table-export-button:hover { background: #14583d; transform: translateY(-1px); }
		.table-toolbar .table-export-button svg { width: 14px; height: 14px; }
		.table-filter-empty td { padding: 28px 16px !important; color: #819087 !important; text-align: center; }
		.content-area .card:has(.table-responsive) { overflow: hidden; border: 1px solid #e5eee8 !important; border-radius: 18px; background: #fff; box-shadow: 0 12px 34px rgba(27, 62, 44, .055) !important; }
		.content-area .table-responsive { border-radius: inherit; scrollbar-color: #c4d6ca #f6faf7; scrollbar-width: thin; }
		.content-area .table { --bs-table-bg: transparent; --bs-table-color: var(--ink); --bs-table-striped-bg: #fbfdfc; --bs-table-hover-bg: #f3f9f5; margin-bottom: 0; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }
		.content-area .table > :not(caption) > * > * { padding: 15px 17px; border-bottom: 1px solid #edf2ef; }
		.content-area .table thead { background: #f6faf7; }
		.content-area .table thead th { padding-top: 14px; padding-bottom: 14px; border-bottom: 1px solid #e6eee9; background: #f6faf7; color: #718178; font-size: 10px; font-weight: 750; letter-spacing: .075em; text-transform: uppercase; white-space: nowrap; }
		.content-area .table tbody tr { transition: background-color .16s ease; }
		.content-area .table-hover > tbody > tr:hover > * { --bs-table-bg-state: #f3f9f5; color: inherit; }
		.content-area .table-hover > tbody > tr:hover > td:first-child { box-shadow: inset 3px 0 #83c99d; }
		.content-area .table tbody tr:last-child > * { border-bottom: 0; }
		.content-area .table tbody td { color: #405148; }
		.content-area .table tbody td .fw-semibold { color: #263a30; font-weight: 680 !important; }
		.content-area .table tbody td small { color: #87958d !important; font-size: 10px; }
		.content-area .table tbody td[colspan] { padding: 34px 16px !important; color: #819087; font-size: 12px; }
		.content-area .table .badge { padding: 6px 10px; border: 1px solid transparent; border-radius: 999px; font-size: 10px; font-weight: 700; letter-spacing: .01em; }
		.content-area .table .badge.text-bg-success { border-color: #d7eddf; background: #eaf7ef !important; color: #16764f !important; }
		.content-area .table .badge.text-bg-secondary { border-color: #e5e9e6; background: #f1f3f2 !important; color: #68756e !important; }
		.content-area .table .badge.text-bg-warning { border-color: #f3e5b7; background: #fff7dd !important; color: #866312 !important; }
		.content-area .table .badge.text-bg-danger { border-color: #f3d9d7; background: #fff0ef !important; color: #a63e38 !important; }
		.content-area .table .btn { min-height: 31px; border-radius: 9px; font-size: 10px; font-weight: 680; transition: background-color .15s ease, border-color .15s ease, color .15s ease, transform .15s ease; }
		.content-area .table .btn:hover { transform: translateY(-1px); }
		.content-area .table .btn-outline-secondary { border-color: #e2eae5; background: #fff; color: #52645a; }
		.content-area .table .btn-outline-secondary:hover { border-color: #d4e5da; background: #f4faf6; color: #245c43; }
		.content-area .table .btn-outline-primary { border-color: #d6e9dd; background: #f8fcf9; color: #147d59; }
		.content-area .table .btn-outline-primary:hover { border-color: #b6dbc4; background: #eaf6ee; color: #0c6044; }
		.content-area .table .btn-outline-danger { border-color: #f0dfde; background: #fffafa; color: #a44c46; }
		.content-area .table .btn-outline-danger:hover { border-color: #e9c4c1; background: #fff0ef; color: #8d302b; }
		.content-area .card:has(.table-responsive) > .card-footer { padding: 13px 17px; border-top: 1px solid #edf2ef; background: #fff; }
		.content-area .card:has(.table-responsive) .pagination { gap: 5px; margin: 0; }
		.content-area .card:has(.table-responsive) .page-link { min-width: 34px; border: 0; border-radius: 9px; background: #f5f8f6; color: #52665a; text-align: center; font-size: 11px; }
		.content-area .card:has(.table-responsive) .page-item.active .page-link { background: #147d59; color: #fff; box-shadow: 0 4px 10px rgba(20, 125, 89, .18); }
		.content-area .card:has(.table-responsive) .page-item.disabled .page-link { background: #fafbfa; color: #b1bdb5; }
		.content-area h1.h4 { font-size: 16px; font-weight: 750; letter-spacing: -.02em; }
		#crudModal .modal-dialog { --bs-modal-width: 500px; }
		#crudModal .modal-dialog.modal-lg { --bs-modal-width: min(900px, calc(100vw - 32px)); }
		#crudModal .modal-content { overflow: hidden; border: 1px solid rgba(255, 255, 255, .85); border-radius: 22px; background: #fff; box-shadow: 0 28px 90px rgba(10, 35, 25, .28); }
		#crudModal .modal-header { min-height: 82px; padding: 17px 24px; border-bottom: 1px solid #edf2ef; background: #f8fbf9; }
		#crudModal .modal-brand-mark { display: grid; place-items: center; flex: 0 0 42px; width: 42px; height: 42px; border-radius: 14px; background: #b7f36b; color: #10513f; font-size: 23px; font-weight: 800; }
		#crudModal .modal-eyebrow { margin-bottom: 3px; color: #789087; font-size: 9px; font-weight: 750; letter-spacing: .13em; }
		#crudModal .modal-title { color: #172a25; font-size: 18px !important; font-weight: 760 !important; letter-spacing: -.025em; }
		#crudModal .btn-close { padding: 11px; border-radius: 11px; background-color: #edf3ef; opacity: .8; }
		#crudModal .btn-close:hover { background-color: #e2eee7; opacity: 1; }
		#crudModal .modal-body { overflow-x: hidden; padding: 25px 26px 28px; }
		#crudModal .modal-body h1 { color: #172a25; font-size: 17px; font-weight: 750; }
		#crudModal .modal-body .card { border: 0 !important; border-radius: 0; box-shadow: none !important; }
		#crudModal .modal-body .card-body { padding: 0; }
		#crudModal .modal-body .form-label { margin-bottom: 7px; color: #40534a; font-size: 12px; font-weight: 680; }
		#crudModal .modal-body .form-control, #crudModal .modal-body .form-select { min-height: 43px; border: 1px solid #dfe9e3; border-radius: 11px; background-color: #fff; color: #20342b; font-size: 13px; box-shadow: none; transition: border-color .16s ease, box-shadow .16s ease; }
		#crudModal .modal-body textarea.form-control { min-height: 104px; }
		#crudModal .modal-body .form-control:focus, #crudModal .modal-body .form-select:focus { border-color: #55ad83; box-shadow: 0 0 0 4px rgba(31, 145, 91, .12); }
		#crudModal .modal-body .form-check-input { width: 17px; height: 17px; margin-top: .16em; border-color: #bdcec4; }
		#crudModal .modal-body .form-check-input:checked { border-color: #147d59; background-color: #147d59; }
		#crudModal .modal-body .form-check-input:focus { box-shadow: 0 0 0 4px rgba(31, 145, 91, .12); }
		#crudModal .modal-body .form-check-label { color: #40534a; font-size: 12px; }
		#crudModal .modal-body .form-text { margin-top: 6px; color: #819087; font-size: 10px; }
		#crudModal .modal-body .form-control[readonly] { border-color: #e8eee9; background: #f7faf8; color: #65756c; }
		#crudModal .crud-form-section { display: flex; align-items: flex-start; gap: 12px; margin: 4px 0 1px; padding: 15px 0 2px; border-top: 1px solid #eaf0ec; }
		#crudModal .crud-section-index { display: grid; place-items: center; flex: 0 0 29px; width: 29px; height: 29px; border-radius: 10px; background: #eaf5ee; color: #147d59; font-size: 10px; font-weight: 800; }
		#crudModal .crud-form-section h3 { margin: 1px 0 3px; color: #263a30; font-size: 13px; font-weight: 750; }
		#crudModal .crud-form-section p { margin: 0; color: #829087; font-size: 10px; line-height: 1.5; }
		#crudModal .role-choice { min-height: 72px; border-color: #e5ece7 !important; border-radius: 13px !important; background: #fbfcfb; transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease; }
		#crudModal .role-choice:hover { border-color: #cfe2d5 !important; background: #f7fbf8; }
		#crudModal .role-choice .form-check-label { color: #33483d; }
		#crudModal .record-view { display: grid; gap: 15px; }
		#crudModal .modal-view-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: 12px; }
		#crudModal .modal-view-toolbar .btn { min-height: 36px; border-radius: 10px; font-size: 11px; font-weight: 700; }
		#crudModal .record-hero { display: flex; align-items: center; gap: 14px; padding: 17px; border: 1px solid #e5eee8; border-radius: 17px; background: #f7faf8; }
		#crudModal .record-mark { display: grid; place-items: center; flex: 0 0 46px; width: 46px; height: 46px; border-radius: 15px; background: #e8f5ed; color: #147d59; font-size: 15px; font-weight: 800; }
		#crudModal .record-hero-copy { min-width: 0; flex: 1; }
		#crudModal .record-kicker { margin-bottom: 4px; color: #84938a; font-size: 9px; font-weight: 750; letter-spacing: .12em; text-transform: uppercase; }
		#crudModal .record-title { margin: 0; color: #21382c; font-size: 16px; font-weight: 760; line-height: 1.35; overflow-wrap: anywhere; }
		#crudModal .record-subtitle { margin: 4px 0 0; color: #7a8a80; font-size: 11px; }
		#crudModal .record-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
		#crudModal .record-badge { display: inline-flex; align-items: center; min-height: 24px; padding: 4px 9px; border: 1px solid #dfe9e3; border-radius: 999px; background: #fff; color: #52665a; font-size: 9px; font-weight: 700; }
		#crudModal .record-badge.is-success { border-color: #d7eddf; background: #eaf7ef; color: #16764f; }
		#crudModal .record-badge.is-muted { border-color: #e5e9e6; background: #f1f3f2; color: #68756e; }
		#crudModal .record-detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
		#crudModal .record-detail-item { min-width: 0; padding: 12px 14px; border: 1px solid #e8eee9; border-radius: 13px; background: #fff; }
		#crudModal .record-detail-label { display: block; margin-bottom: 5px; color: #849188; font-size: 9px; font-weight: 720; letter-spacing: .07em; text-transform: uppercase; }
		#crudModal .record-detail-value { display: block; color: #304238; font-size: 12px; font-weight: 650; line-height: 1.5; overflow-wrap: anywhere; }
		#crudModal .record-detail-value.is-mono { color: #416452; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11px; letter-spacing: .01em; }
		#crudModal .record-detail-item.is-wide { grid-column: 1 / -1; }
		#crudModal .record-description { color: #53645a; font-size: 12px; font-weight: 450; white-space: pre-wrap; }
		#crudModal .crud-form-actions { position: sticky; z-index: 2; bottom: -28px; margin: 22px -26px -28px; padding: 16px 26px 20px; border-top: 1px solid #edf2ef; background: #fff; }
		#crudModal .crud-form-actions .btn { min-height: 41px; padding: 9px 17px; border-radius: 11px; font-size: 12px; font-weight: 700; }
		#crudModal .crud-form-actions .btn-success { border-color: #147d59; background: #147d59; box-shadow: 0 5px 12px rgba(20, 125, 89, .16); }
		#crudModal .crud-form-actions .btn-success:hover { border-color: #0c6044; background: #0c6044; }
		#crudModal .alert { border: 0; border-radius: 12px; font-size: 12px; }
		#crudModal.fade .modal-dialog { transform: translateY(14px) scale(.985); transition: transform .24s cubic-bezier(.2, .75, .25, 1); }
		#crudModal.show .modal-dialog { transform: translateY(0) scale(1); }
		.modal-backdrop.show { --bs-backdrop-opacity: .42; background-color: #10271f; -webkit-backdrop-filter: blur(8px) saturate(.82); backdrop-filter: blur(8px) saturate(.82); }
		.dashboard-welcome { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; margin-bottom: 20px; }
		.dashboard-eyebrow { margin-bottom: 7px; color: #178057; font-size: 9px; font-weight: 800; letter-spacing: .15em; }
		.dashboard-welcome h2 { margin: 0; color: #183329; font-size: 22px; font-weight: 780; letter-spacing: -.04em; }
		.dashboard-welcome p { margin: 5px 0 0; color: #839188; font-size: 11px; }
		.dashboard-date { padding: 8px 12px; border: 1px solid #e2ebe5; border-radius: 10px; background: #fff; color: #62746a; font-size: 10px; font-weight: 650; }
		.dashboard-stat-card { padding: 17px; border: 1px solid #e7eee9; border-radius: 16px; background: #fff; box-shadow: 0 7px 22px rgba(28, 57, 44, .035); transition: transform .16s ease, box-shadow .16s ease; }
		.dashboard-stat-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(28, 57, 44, .075); }
		.dashboard-stat-top { display: flex; align-items: center; gap: 10px; }
		.dashboard-stat-icon { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 11px; font-size: 12px; font-weight: 800; }
		.dashboard-stat-icon.tone-green { background: #e5f5ec; color: #147d59; }
		.dashboard-stat-icon.tone-lime { background: #f1f8d9; color: #6b8f16; }
		.dashboard-stat-icon.tone-blue { background: #e9f2fb; color: #3973aa; }
		.dashboard-stat-icon.tone-amber { background: #fff3dc; color: #a5771a; }
		.dashboard-stat-caption { color: #75867c; font-size: 11px; font-weight: 600; }
		.dashboard-stat-value { margin-top: 15px; color: #182b22; font-size: 26px; font-weight: 790; letter-spacing: -.04em; line-height: 1.1; }
		.dashboard-stat-value-currency { font-size: 20px; overflow-wrap: anywhere; }
		.dashboard-stat-foot { margin-top: 6px; color: #9aa79f; font-size: 9px; }
		.dashboard-panel { padding: 19px; border: 1px solid #e7eee9; border-radius: 18px; background: #fff; box-shadow: 0 7px 24px rgba(28, 57, 44, .04); }
		.dashboard-panel-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
		.dashboard-panel-heading h3 { margin: 0; color: #20372b; font-size: 13px; font-weight: 760; }
		.dashboard-panel-heading p { margin: 4px 0 0; color: #8a9890; font-size: 10px; }
		.dashboard-legend { display: flex; gap: 13px; flex-wrap: wrap; color: #718178; font-size: 9px; }
		.dashboard-legend span { display: inline-flex; align-items: center; gap: 6px; }
		.legend-dot { width: 7px; height: 7px; border-radius: 50%; }
		.dot-sales { background: #0b3324; }
		.dot-purchases { background: #a7e836; }
		.dashboard-chart-wrap { position: relative; height: 285px; }
		.dashboard-donut-wrap { position: relative; height: 260px; }
		.dashboard-chart-fallback { position: absolute; inset: 0; display: grid; place-items: center; color: #89978e; font-size: 11px; text-align: center; }
		.dashboard-chart-fallback[hidden] { display: none; }
		.dashboard-mini-stat { display: flex; flex-direction: column; gap: 8px; height: 100%; padding: 15px 17px; border: 1px solid #e7eee9; border-radius: 14px; background: #fff; }
		.dashboard-mini-stat span { color: #78877e; font-size: 10px; }
		.dashboard-mini-stat strong { color: #20372b; font-size: 16px; font-weight: 760; letter-spacing: -.02em; }
		.settings-page { color: var(--ink); }
		.settings-page-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; margin-bottom: 21px; }
		.settings-page-eyebrow { margin-bottom: 6px; color: #178057; font-size: 9px; font-weight: 800; letter-spacing: .15em; }
		.settings-page-title { margin: 0; color: #183329; font-size: 22px; font-weight: 780; letter-spacing: -.04em; }
		.settings-page-description { margin: 5px 0 0; color: #839188; font-size: 11px; line-height: 1.65; }
		.settings-save-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 9px 15px; border: 0; border-radius: 11px; background: #147d59; color: #fff; font-size: 11px; font-weight: 750; box-shadow: 0 7px 16px rgba(20, 125, 89, .14); transition: background .15s ease, transform .15s ease; }
		.settings-save-button:hover { transform: translateY(-1px); background: #0c6044; color: #fff; }
		.settings-save-button svg { width: 15px; height: 15px; }
		.settings-alert { padding: 11px 14px; border: 1px solid transparent; border-radius: 12px; font-size: 11px; }
		.settings-layout { display: grid; grid-template-columns: 235px minmax(0, 1fr); align-items: start; gap: 18px; }
		.settings-sidebar { position: sticky; top: 84px; display: grid; gap: 13px; }
		.settings-preview { overflow: hidden; padding: 17px; border: 1px solid #194a35; border-radius: 17px; background: linear-gradient(145deg, #0b3525, #174a34); color: #fff; box-shadow: 0 10px 24px rgba(12, 49, 34, .12); }
		.settings-preview-label { margin-bottom: 14px; color: #a4c4ae; font-size: 8px; font-weight: 800; letter-spacing: .14em; }
		.settings-preview-brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
		.settings-preview-logo { display: grid; place-items: center; width: 40px; height: 40px; flex: 0 0 40px; border-radius: 12px; object-fit: contain; background: #fff; color: #0e4934; font-size: 22px; font-weight: 800; }
		.settings-preview-name { overflow: hidden; color: #fff; font-size: 12px; font-weight: 750; text-overflow: ellipsis; white-space: nowrap; }
		.settings-preview-tagline { overflow: hidden; margin-top: 3px; color: #a9c2b4; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }
		.settings-preview-divider { height: 1px; margin: 15px 0 12px; background: rgba(235, 248, 239, .12); }
		.settings-preview-hours { display: flex; align-items: flex-start; gap: 8px; color: #dcebe1; font-size: 9px; line-height: 1.5; }
		.settings-preview-hours svg { width: 13px; height: 13px; flex: 0 0 13px; color: #b7f36b; }
		.settings-nav-card, .settings-tip { padding: 14px; border: 1px solid #e7eee9; border-radius: 15px; background: #fff; box-shadow: 0 5px 16px rgba(28, 57, 44, .03); }
		.settings-nav-title { margin: 0 0 8px; padding: 0 7px; color: #8a9890; font-size: 8px; font-weight: 800; letter-spacing: .12em; }
		.settings-nav-list { display: grid; gap: 3px; }
		.settings-nav-link { display: flex; align-items: center; gap: 9px; min-height: 36px; padding: 7px 9px; border-radius: 9px; color: #617168; font-size: 10px; font-weight: 620; text-decoration: none; transition: background .15s ease, color .15s ease; }
		.settings-nav-link:hover, .settings-nav-link:focus-visible { background: #eff7f1; color: #126343; }
		.settings-nav-number { display: grid; place-items: center; width: 21px; height: 21px; flex: 0 0 21px; border-radius: 7px; background: #f0f5f2; color: #71837a; font-size: 8px; font-weight: 800; }
		.settings-tip { display: flex; align-items: flex-start; gap: 9px; background: #f4f8e9; border-color: #e9f0d7; }
		.settings-tip-icon { display: grid; place-items: center; width: 25px; height: 25px; flex: 0 0 25px; border-radius: 8px; background: #e7f2c9; color: #64852b; }
		.settings-tip-icon svg { width: 14px; height: 14px; }
		.settings-tip strong { display: block; margin: 1px 0 3px; color: #506b25; font-size: 9px; }
		.settings-tip span { display: block; color: #7d8c5c; font-size: 9px; line-height: 1.55; }
		.settings-form { min-width: 0; }
		.settings-section { scroll-margin-top: 82px; margin-bottom: 14px; overflow: hidden; border: 1px solid #e5ede8; border-radius: 17px; background: #fff; box-shadow: 0 7px 22px rgba(28, 57, 44, .035); }
		.settings-section-head { display: flex; align-items: flex-start; gap: 12px; padding: 18px 20px 15px; border-bottom: 1px solid #edf2ef; background: linear-gradient(180deg, #fff, #fcfdfc); }
		.settings-section-number { display: grid; place-items: center; width: 32px; height: 32px; flex: 0 0 32px; border: 1px solid #dcefe3; border-radius: 10px; background: #edf7f0; color: #147d59; font-size: 9px; font-weight: 850; }
		.settings-section-head h3 { margin: 1px 0 4px; color: #20372b; font-size: 12px; font-weight: 760; }
		.settings-section-head p { margin: 0; color: #87958d; font-size: 9px; line-height: 1.55; }
		.settings-section-body { padding: 18px 20px 20px; }
		.settings-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px 16px; }
		.settings-field { min-width: 0; }
		.settings-field-full { grid-column: 1 / -1; }
		.settings-field .form-label { margin-bottom: 6px; color: #405349; font-size: 10px; font-weight: 700; }
		.settings-field .form-control { min-height: 39px; border-color: #e2eae5; border-radius: 9px; background: #fcfdfc; font-size: 10px; }
		.settings-field textarea.form-control { min-height: auto; line-height: 1.55; }
		.settings-field .form-control:focus { background: #fff; }
		.settings-field .form-text { margin-top: 5px; color: #97a39c; font-size: 8px; line-height: 1.5; }
		.settings-logo-box { display: flex; align-items: center; gap: 13px; min-height: 96px; padding: 13px; border: 1px dashed #cbdcd1; border-radius: 13px; background: #f8fbf9; }
		.settings-logo-thumb { display: grid; place-items: center; width: 62px; height: 62px; flex: 0 0 62px; border: 1px solid #e5ece7; border-radius: 13px; background: #fff; color: #147d59; font-size: 28px; font-weight: 800; object-fit: contain; }
		.settings-logo-copy { min-width: 0; }
		.settings-logo-copy strong { display: block; margin-bottom: 3px; color: #31483b; font-size: 10px; }
		.settings-logo-copy span { display: block; color: #8b9991; font-size: 8px; line-height: 1.5; }
		.settings-upload-button { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; color: #147d59; font-size: 9px; font-weight: 750; cursor: pointer; }
		.settings-upload-button:hover { color: #0c6044; }
		.settings-upload-button svg { width: 13px; height: 13px; }
		.settings-file-input { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; clip-path: inset(50%); }
		.settings-file-name { max-width: 230px; overflow: hidden; margin-top: 5px; color: #78877f; font-size: 8px; text-overflow: ellipsis; white-space: nowrap; }
		.settings-remove-logo { margin-top: 10px; }
		.settings-remove-logo .form-check-label { color: #829087; font-size: 9px; }
		.settings-form-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 4px 2px 6px; }
		.settings-form-note { color: #8b9991; font-size: 9px; }
		.settings-form-footer .settings-save-button { min-width: 164px; }
		.settings-anchor-target { scroll-margin-top: 82px; }
		.invoice-drawer { --bs-offcanvas-width: min(560px, 100vw); background: #f5f8f6; }
		.invoice-drawer .offcanvas-header { align-items: flex-start; gap: 16px; min-height: 72px; padding: 17px 20px; border-bottom: 1px solid #e6ede8; background: #fff; }
		.invoice-drawer-heading { min-width: 0; flex: 1 1 auto; }
		.invoice-drawer .offcanvas-title { color: #20372b; font-size: 14px; font-weight: 780; }
		#invoiceDrawerTitle { overflow-wrap: anywhere; }
		.invoice-drawer-actions { display: flex; align-items: flex-start; justify-content: flex-end; gap: 8px; flex: 0 0 auto; margin-left: auto; }
		.invoice-drawer .offcanvas-body { padding: 18px; }
		.invoice-print-button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 35px; padding: 8px 12px; border: 1px solid #cfe5d6; border-radius: 10px; background: #edf7f0; color: #146545; font-size: 10px; font-weight: 760; line-height: 1; transition: background .16s ease, border-color .16s ease, color .16s ease, box-shadow .16s ease, transform .16s ease; }
		.invoice-print-button:hover { transform: translateY(-1px); border-color: #147d59; background: #147d59; color: #fff; box-shadow: 0 6px 14px rgba(20, 125, 89, .18); }
		.invoice-print-button:active { transform: translateY(0); }
		.invoice-print-button:focus-visible { outline: 3px solid rgba(20, 125, 89, .2); outline-offset: 2px; }
		.invoice-print-button svg { width: 15px; height: 15px; flex: 0 0 15px; }
		.invoice-close-button { display: grid; place-items: center; width: 35px; height: 35px; flex: 0 0 35px; padding: 0; border: 1px solid #e7ece8; border-radius: 10px; background: #f7f9f8; color: #75847b; transition: background .16s ease, border-color .16s ease, color .16s ease, transform .16s ease; }
		.invoice-close-button:hover { transform: rotate(4deg); border-color: #f0d7d4; background: #fff1ef; color: #b94a40; }
		.invoice-close-button:focus-visible { outline: 3px solid rgba(20, 125, 89, .2); outline-offset: 2px; }
		.invoice-close-button svg { width: 16px; height: 16px; }
		.invoice-drawer-eyebrow { margin-bottom: 4px; color: #178057; font-size: 8px; font-weight: 800; letter-spacing: .13em; }
		.invoice-loading { display: flex; align-items: center; justify-content: center; gap: 9px; min-height: 180px; color: #78877e; font-size: 11px; }
		.invoice-receipt { padding: 22px; border: 1px solid #e5ede8; border-radius: 15px; background: #fff; box-shadow: 0 7px 22px rgba(28, 57, 44, .04); }
		.invoice-receipt-header { display: flex; align-items: flex-start; gap: 12px; padding-bottom: 17px; border-bottom: 1px solid #edf2ef; }
		.invoice-pharmacy-logo, .invoice-pharmacy-mark { display: grid; place-items: center; width: 44px; height: 44px; flex: 0 0 44px; border: 1px solid #e5ece7; border-radius: 12px; background: #fff; color: #147d59; object-fit: contain; font-size: 23px; font-weight: 800; }
		.invoice-pharmacy-info { min-width: 0; color: #7d8b83; font-size: 9px; line-height: 1.6; overflow-wrap: anywhere; }
		.invoice-pharmacy-info h3 { margin: 0 0 4px; color: #20372b; font-size: 13px; font-weight: 780; }
		.invoice-receipt-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 16px 0; border-bottom: 1px solid #edf2ef; }
		.invoice-meta-item { min-width: 0; }
		.invoice-meta-item span { display: block; margin-bottom: 4px; color: #8c9991; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
		.invoice-meta-item strong { display: block; color: #33483c; font-size: 10px; font-weight: 700; overflow-wrap: anywhere; }
		.invoice-meta-item .invoice-status { color: #147d59; text-transform: capitalize; }
		.invoice-table-wrap { margin-top: 14px; }
		.invoice-receipt .invoice-table { margin-bottom: 0; font-size: 9px; }
		.invoice-receipt .invoice-table th { padding: 9px 7px; border-bottom-color: #e8eee9; color: #7e8d84; font-size: 8px; font-weight: 750; white-space: nowrap; }
		.invoice-receipt .invoice-table td { padding: 10px 7px; border-bottom-color: #f0f3f1; color: #405349; vertical-align: middle; }
		.invoice-totals { display: grid; gap: 8px; max-width: 270px; margin: 15px 0 0 auto; color: #738178; font-size: 9px; }
		.invoice-totals > div { display: flex; justify-content: space-between; gap: 18px; }
		.invoice-totals .invoice-grand-total { margin-top: 3px; padding-top: 10px; border-top: 1px solid #e6ede8; color: #173a2a; font-size: 11px; }
		.invoice-receipt-footer { margin-top: 19px; padding-top: 13px; border-top: 1px dashed #dfe8e2; color: #89968e; font-size: 9px; line-height: 1.6; text-align: center; }
		.invoice-page-head { display: flex; align-items: center; justify-content: space-between; gap: 15px; margin-bottom: 16px; }
		.invoice-page-head h1 { margin: 0; color: #20372b; font-size: 17px; font-weight: 780; }
		.inventory-page-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
		.inventory-page-eyebrow { margin-bottom: 6px; color: #178057; font-size: 9px; font-weight: 800; letter-spacing: .14em; }
		.inventory-page-heading h1 { margin: 0; color: #183329; font-size: 21px; font-weight: 780; letter-spacing: -.035em; }
		.inventory-page-heading p { margin: 5px 0 0; color: #839188; font-size: 10px; line-height: 1.6; }
		.inventory-heading-count { display: flex; align-items: center; gap: 8px; padding: 8px 11px; border: 1px solid #e2ebe5; border-radius: 11px; background: #fff; color: #617168; }
		.inventory-heading-count span { color: #147d59; font-size: 15px; font-weight: 800; }
		.inventory-heading-count small { font-size: 9px; }
		.inventory-tabs { display: flex; flex-wrap: nowrap; gap: 4px; overflow-x: auto; overflow-y: hidden; margin-bottom: 16px; border-bottom-color: #e4ece6; scrollbar-width: none; }
		.inventory-tabs::-webkit-scrollbar { display: none; }
		.inventory-tabs .nav-item { flex: 0 0 auto; }
		.inventory-tabs .nav-link { margin-bottom: -1px; padding: 10px 13px; border: 1px solid transparent; border-radius: 10px 10px 0 0; color: #738178; font-size: 10px; font-weight: 700; white-space: nowrap; }
		.inventory-tabs .nav-link:hover { border-color: #eef3ef #eef3ef #e4ece6; background: #f7faf8; color: #276247; }
		.inventory-tabs .nav-link.active { border-color: #e4ece6 #e4ece6 #fff; background: #fff; color: #147d59; box-shadow: 0 -2px 0 #147d59 inset; }
		.inventory-tab-content { min-height: 250px; }
		.inventory-tab-content .tab-pane { outline: none; }
		.inventory-tab-intro { margin: 2px 0 14px; }
		.inventory-tab-intro h2 { margin: 0 0 4px; color: #20372b; font-size: 13px; font-weight: 760; }
		.inventory-tab-intro p { margin: 0; color: #87958d; font-size: 9px; line-height: 1.6; }
		.inventory-form-card { max-width: 920px; }
		.inventory-form-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 18px; padding-top: 15px; border-top: 1px solid #edf2ef; }
		@media (max-width: 575.98px) {
			.invoice-drawer .offcanvas-header { padding: 14px 15px; }
			.invoice-drawer-actions { gap: 6px; }
			.invoice-print-button { padding-right: 9px; padding-left: 9px; }
			.invoice-drawer .offcanvas-body { padding: 12px; }
			.invoice-receipt { padding: 15px; }
			.invoice-receipt-meta { gap: 10px; }
			.invoice-page-head { align-items: flex-start; flex-direction: column; }
			.invoice-page-head > div:last-child { width: 100%; }
			.invoice-page-head > div:last-child .btn { flex: 1; }
			.inventory-page-heading { align-items: flex-start; }
			.inventory-heading-count { flex-direction: column; align-items: flex-start; gap: 1px; }
			.inventory-tabs .nav-link { padding: 9px 10px; font-size: 9px; }
			.inventory-form-actions { justify-content: stretch; }
			.inventory-form-actions .btn { width: 100%; }
		}
		@media print {
			@page { size: A4; margin: 9mm; }
			body:not(.invoice-drawer-printing) { min-width: 0 !important; background: #fff !important; color: #172a25 !important; }
			body:not(.invoice-drawer-printing) .sidebar,
			body:not(.invoice-drawer-printing) .topbar,
			body:not(.invoice-drawer-printing) .mobile-menu,
			body:not(.invoice-drawer-printing) .table-toolbar,
			body:not(.invoice-drawer-printing) .inventory-tabs,
			body:not(.invoice-drawer-printing) .modal,
			body:not(.invoice-drawer-printing) .dropdown-menu,
			body:not(.invoice-drawer-printing) .btn,
			body:not(.invoice-drawer-printing) button,
			body:not(.invoice-drawer-printing) .no-print { display: none !important; }
			body:not(.invoice-drawer-printing) .app-shell { display: block !important; min-height: 0 !important; }
			body:not(.invoice-drawer-printing) .main-area { display: block !important; width: 100% !important; min-width: 0 !important; margin: 0 !important; }
			body:not(.invoice-drawer-printing) .content-area { padding: 0 !important; }
			body:not(.invoice-drawer-printing) .content-area .table-responsive { overflow: visible !important; }
			body:not(.invoice-drawer-printing) .content-area .table { display: table !important; width: 100% !important; border-collapse: collapse !important; font-size: 9pt !important; color: #172a25 !important; }
			body:not(.invoice-drawer-printing) .content-area .table thead { position: static !important; display: table-header-group !important; width: auto !important; height: auto !important; overflow: visible !important; clip: auto !important; }
			body:not(.invoice-drawer-printing) .content-area .table tbody { display: table-row-group !important; }
			body:not(.invoice-drawer-printing) .content-area .table tr { display: table-row !important; break-inside: avoid; box-shadow: none !important; }
			body:not(.invoice-drawer-printing) .content-area .table tr[hidden],
			body:not(.invoice-drawer-printing) .content-area .table tr.table-filter-hidden { display: none !important; }
			body:not(.invoice-drawer-printing) .content-area .table th,
			body:not(.invoice-drawer-printing) .content-area .table td { display: table-cell !important; padding: 5px 6px !important; border: 1px solid #dce5df !important; color: #172a25 !important; text-align: left !important; white-space: normal !important; }
			body:not(.invoice-drawer-printing) .content-area .table thead th { background: #eaf2ed !important; font-weight: 700 !important; }
			body:not(.invoice-drawer-printing) .content-area .table td::before { display: none !important; content: none !important; }
			body:not(.invoice-drawer-printing) .content-area .table .print-hidden-column { display: none !important; }
			body:not(.invoice-drawer-printing) .content-area .card { border: 1px solid #dce5df !important; box-shadow: none !important; }
			body:not(.invoice-drawer-printing) .content-area .tab-pane.active { display: block !important; opacity: 1 !important; visibility: visible !important; }
			body:not(.invoice-drawer-printing) .content-area .tab-pane:not(.active) { display: none !important; }
			body:not(.invoice-drawer-printing) .content-area a { color: #172a25 !important; text-decoration: none !important; }
			body:not(.invoice-drawer-printing) .content-area .alert { display: none !important; }
			body.invoice-drawer-printing { min-width: 0 !important; background: #fff !important; }
			body.invoice-drawer-printing > * { display: none !important; }
			body.invoice-drawer-printing > #invoiceDrawer { display: block !important; position: static !important; width: 100% !important; height: auto !important; max-height: none !important; overflow: visible !important; transform: none !important; visibility: visible !important; border: 0 !important; box-shadow: none !important; background: #fff !important; }
			body.invoice-drawer-printing #invoiceDrawer .offcanvas-header { display: none !important; }
			body.invoice-drawer-printing #invoiceDrawer .offcanvas-body { display: block !important; height: auto !important; max-height: none !important; overflow: visible !important; padding: 0 !important; }
			body.invoice-drawer-printing .invoice-receipt { width: 100% !important; margin: 0 !important; padding: 0 !important; border: 0 !important; border-radius: 0 !important; box-shadow: none !important; }
			body.invoice-drawer-printing .invoice-receipt-header { padding-bottom: 8px !important; }
			body.invoice-drawer-printing .invoice-receipt-meta { gap: 7px !important; padding: 8px 0 !important; }
			body.invoice-drawer-printing .invoice-table-wrap { overflow: visible !important; margin-top: 8px !important; }
			body.invoice-drawer-printing .invoice-receipt .invoice-table { display: table !important; width: 100% !important; border-collapse: collapse !important; }
			body.invoice-drawer-printing .invoice-table thead { position: static !important; display: table-header-group !important; width: auto !important; height: auto !important; overflow: visible !important; clip: auto !important; }
			body.invoice-drawer-printing .invoice-table tbody { display: table-row-group !important; padding: 0 !important; }
			body.invoice-drawer-printing .invoice-table tr { display: table-row !important; overflow: visible !important; border: 0 !important; box-shadow: none !important; break-inside: avoid; }
			body.invoice-drawer-printing .invoice-table th,
			body.invoice-drawer-printing .invoice-table td { display: table-cell !important; padding: 5px 6px !important; border-bottom: 1px solid #e8eee9 !important; text-align: left !important; white-space: normal !important; }
			body.invoice-drawer-printing .invoice-table td::before { display: none !important; content: none !important; }
			body.invoice-drawer-printing .invoice-totals { gap: 5px !important; margin-top: 8px !important; }
			body.invoice-drawer-printing .invoice-receipt-footer { margin-top: 10px !important; padding-top: 8px !important; }
		}
		.content-area .text-muted { color: var(--muted) !important; }
		.mobile-menu { border: 1px solid #e3ebe6; background: #fff; color: var(--ink); width: 38px; height: 38px; border-radius: 11px; }
		.user-chip { display: flex; align-items: center; gap: 9px; padding: 5px 10px 5px 5px; background: #fff; border: 1px solid #e7eee9; border-radius: 999px; }
		.user-avatar { display: grid; place-items: center; width: 31px; height: 31px; border-radius: 50%; color: #146545; background: #dcf2e7; font-size: 11px; font-weight: 750; }
		.user-label { max-width: 155px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; font-weight: 680; }
		@media (min-width: 768px) {
			.sidebar.offcanvas-md { position: sticky; top: 0; height: 100vh; transform: none; visibility: visible !important; }
			.app-shell.sidebar-collapsed .sidebar { flex-basis: 76px; width: 76px; }
			.app-shell.sidebar-collapsed .sidebar-inner { padding-right: 9px; padding-left: 9px; }
			.app-shell.sidebar-collapsed .sidebar .brand { justify-content: center; padding-right: 0; padding-left: 0; }
			.app-shell.sidebar-collapsed .sidebar .brand > span:last-child,
			.app-shell.sidebar-collapsed .sidebar .nav-heading,
			.app-shell.sidebar-collapsed .sidebar .sidebar-link > span:not(.nav-icon),
			.app-shell.sidebar-collapsed .sidebar .sidebar-profile > span:last-child { display: none !important; }
			.app-shell.sidebar-collapsed .sidebar .sidebar-link { justify-content: center; padding-right: 6px; padding-left: 6px; }
			.app-shell.sidebar-collapsed .sidebar .nav-icon { width: 24px; font-size: 17px; }
			.app-shell.sidebar-collapsed .sidebar .sidebar-profile { justify-content: center; padding: 8px 4px; }
			.app-shell.sidebar-collapsed .sidebar .sidebar-profile-avatar { flex-basis: 34px; }
		}
		@media (min-width: 768px) and (max-width: 900px) {
			.topbar { gap: 16px; }
			.topbar-actions { gap: 8px; }
			.header-profile-name { max-width: 100px; }
			.header-profile-role { max-width: 115px; }
		}
		@media (max-width: 767.98px) {
			.sidebar { --bs-offcanvas-width: min(85vw, 300px); }
			.topbar { grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: "start actions" "search search"; gap: 10px 12px; min-height: 0; padding-top: 11px; padding-bottom: 11px; }
			.topbar-start { display: flex; grid-area: start; }
			.topbar-search { grid-area: search; }
			.topbar-actions { grid-area: actions; }
			.topbar-actions { gap: 7px; }
			.header-profile-name { max-width: 82px; }
			.header-profile-role { display: none; }
			.header-profile-toggle { gap: 6px; padding-right: 4px; }
			.page-context { display: none; }
			.content-area { padding-top: 19px; }
			.topbar { min-height: 64px; }
			.table-toolbar { align-items: stretch; gap: 8px; padding: 11px; }
			.table-toolbar > .col-sm-6 { max-width: none; flex: 1 1 100%; }
			.table-toolbar-actions { width: 100%; justify-content: flex-end; margin-left: 0; }
			.table-toolbar .table-status-filter { min-width: 0; flex: 1 1 auto; }
			.dashboard-welcome { align-items: flex-start; flex-direction: column; }
			.dashboard-welcome h2 { font-size: 19px; }
			.dashboard-date { align-self: flex-start; }
			.dashboard-panel { padding: 15px; }
			.dashboard-chart-wrap { height: 250px; }
			.dashboard-donut-wrap { height: 235px; }
			.settings-page-head { align-items: flex-start; flex-direction: column; }
			.settings-page-head > .settings-save-button { width: 100%; }
			.settings-section-head { padding: 15px; }
			.settings-section-body { padding: 15px; }
			.settings-fields { grid-template-columns: 1fr; gap: 13px; }
			.settings-field-full { grid-column: auto; }
			.settings-form-footer { align-items: stretch; flex-direction: column-reverse; }
			.settings-form-footer .settings-save-button { width: 100%; }
			.content-area .card:has(.table-responsive) { border-radius: 15px; }
			.content-area .table { font-size: 11px; }
			.content-area .table > :not(caption) > * > * { padding: 11px 12px; }
			.content-area .table thead th { padding-top: 12px; padding-bottom: 12px; font-size: 9px; }
			.content-area .table .btn { min-height: 29px; padding: 5px 8px; }
			.content-area > form.row.mb-3 { padding: 8px; }
			.content-area .table-responsive { overflow: visible; }
			.content-area .table { display: block; width: 100%; }
			.content-area .table thead { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
			.content-area .table tbody { display: grid; gap: 10px; padding: 11px; }
			.content-area .table tbody tr { display: block; overflow: hidden; border: 1px solid #e8eee9; border-radius: 14px; background: #fff; box-shadow: 0 4px 13px rgba(27, 62, 44, .045); }
			.content-area .table tbody tr:hover { border-color: #cfe4d6; box-shadow: 0 7px 18px rgba(27, 62, 44, .08); }
			.content-area .table tbody td { display: block; padding: 10px 12px; border-bottom: 1px solid #f0f3f1; overflow-wrap: anywhere; text-align: left !important; white-space: normal !important; }
			.content-area .table tbody td::before { display: block; margin-bottom: 4px; color: #84938a; content: attr(data-label); font-size: 9px; font-weight: 750; letter-spacing: .07em; text-transform: uppercase; }
			.content-area .table tbody td:last-child { border-bottom: 0; }
			.content-area .table tbody td[data-label="Aksi"] { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
			.content-area .table tbody td[data-label="Aksi"]::before { flex: 0 0 100%; }
			.content-area .table tbody td[data-label="Aksi"] > form.d-flex { display: grid !important; flex: 1 1 100%; grid-template-columns: minmax(0, 1fr) auto; align-items: center; }
			.content-area .table tbody td[colspan] { padding: 22px 14px !important; text-align: center !important; }
			.content-area .table tbody td[colspan]::before { display: none; }
			#crudModal .modal-dialog, #crudModal .modal-dialog.modal-lg { --bs-modal-width: calc(100vw - 16px); --bs-modal-margin: 8px; }
			#crudModal .modal-header { min-height: 72px; padding: 14px 17px; }
			#crudModal .modal-body { padding: 20px 17px 23px; }
			#crudModal .record-hero { align-items: flex-start; padding: 14px; }
			#crudModal .record-detail-grid { grid-template-columns: 1fr; gap: 8px; }
			#crudModal .record-detail-item.is-wide { grid-column: auto; }
			#crudModal .crud-form-actions { bottom: -23px; margin: 20px -17px -23px; padding: 14px 17px 17px; }
		}
		@media (max-width: 1100px) {
			.settings-layout { grid-template-columns: 1fr; }
			.settings-sidebar { position: static; grid-template-columns: minmax(220px, .75fr) minmax(0, 1.25fr); align-items: stretch; }
			.settings-nav-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
			.settings-tip { grid-column: 1 / -1; }
		}
		@media (max-width: 700px) {
			.settings-sidebar { grid-template-columns: 1fr; }
			.settings-nav-list { grid-template-columns: 1fr 1fr; }
			.settings-tip { grid-column: auto; }
			.settings-section-head { gap: 10px; }
			.settings-logo-box { align-items: flex-start; }
		}
		@media (prefers-reduced-motion: reduce) { #crudModal.fade .modal-dialog { transition: none; } }
	</style>
</head>
<body>
	<div class="app-shell d-md-flex">
		<aside class="sidebar offcanvas-md offcanvas-start" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
			<div class="sidebar-inner">
				<div class="offcanvas-header d-md-none px-2 pt-0">
					<a class="brand p-0" href="<?php echo html_escape(site_url('admin/dashboard')); ?>"><?php if ($pharmacy['store_logo']): ?><img class="brand-logo" src="<?php echo html_escape(base_url($pharmacy['store_logo'])); ?>" alt="Logo <?php echo html_escape($pharmacy_name); ?>"><?php else: ?><span class="brand-mark">+</span><?php endif; ?><span><span class="brand-title d-block"><?php echo html_escape($pharmacy_name); ?></span><span class="brand-subtitle d-block">Management</span></span></a>
					<button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Tutup menu"></button>
				</div>
				<a class="brand d-none d-md-flex" href="<?php echo html_escape(site_url('admin/dashboard')); ?>" id="adminSidebarLabel">
					<?php if ($pharmacy['store_logo']): ?><img class="brand-logo" src="<?php echo html_escape(base_url($pharmacy['store_logo'])); ?>" alt="Logo <?php echo html_escape($pharmacy_name); ?>"><?php else: ?><span class="brand-mark">+</span><?php endif; ?>
					<span><span class="brand-title d-block"><?php echo html_escape($pharmacy_name); ?></span><span class="brand-subtitle d-block">Management</span></span>
				</a>
				<nav aria-label="Navigasi utama">
					<?php foreach ($navigation as $group): ?>
						<?php
						$visible_items = array();
						foreach ($group['items'] as $item) {
							if (in_array('administrator', $roles, true) || in_array($item['permission'], $permissions, true)) {
								$visible_items[] = $item;
							}
						}
						?>
						<?php if ($visible_items): ?>
							<div class="nav-group">
								<div class="nav-heading"><?php echo html_escape($group['label']); ?></div>
								<?php foreach ($visible_items as $item): ?>
									<?php $active = $current_uri === $item['url'] || strpos($current_uri, $item['url'] . '/') === 0; ?>
									<a class="sidebar-link<?php echo html_escape($active ? ' active' : ''); ?>" href="<?php echo html_escape(site_url($item['url'])); ?>"<?php echo html_escape($active ? ' aria-current="page"' : ''); ?>>
										<span class="nav-icon" aria-hidden="true"><?php echo html_escape($item['icon']); ?></span>
										<span><?php echo html_escape($item['name']); ?></span>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</nav>
				<div class="sidebar-footer">
					<div class="sidebar-profile">
						<span class="sidebar-profile-avatar"><?php echo html_escape(strtoupper(substr((string) $this->session->userdata('user_name'), 0, 1))); ?></span>
						<span class="min-w-0">
							<span class="sidebar-profile-name d-block"><?php echo html_escape($this->session->userdata('user_name')); ?></span>
							<span class="sidebar-profile-role d-block"><?php echo html_escape($sidebar_role); ?></span>
						</span>
					</div>
				</div>
			</div>
		</aside>

		<div class="main-area">
			<header class="topbar px-3 px-md-4">
				<div class="topbar-start">
					<button class="mobile-menu" id="toggleSidebar" type="button" aria-controls="adminSidebar" aria-expanded="true" aria-label="Ciutkan sidebar" title="Ciutkan sidebar">
						<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M4 12h10M4 18h16M17 9l3 3-3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"></path></svg>
					</button>
				</div>
				<label class="topbar-search">
					<svg class="topbar-search-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8"></circle><path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
					<input class="form-control" id="menuSearch" type="search" placeholder="Cari apa saja..." aria-label="Cari menu navigasi" autocomplete="off">
				</label>
				<div class="topbar-actions">
					<button class="header-icon" id="toggleFullscreen" type="button" aria-label="Aktifkan layar penuh" title="Layar penuh">
						<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 4H4v4M16 4h4v4M20 16v4h-4M4 16v4h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"></path></svg>
					</button>
					<div class="dropdown">
						<button class="header-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?php echo html_escape('Notifikasi' . ($pending_orders_count ? ', ' . $pending_orders_count . ' pesanan menunggu' : '')); ?>">
							<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"></path></svg>
							<?php if ($pending_orders_count > 0): ?><span class="header-notification-dot" aria-hidden="true"></span><?php endif; ?>
						</button>
						<div class="dropdown-menu dropdown-menu-end header-notification-menu">
							<div class="header-notification-title">Notifikasi</div>
							<div class="header-notification-copy mt-1">
								<?php if ($can_view_orders && $pending_orders_count > 0): ?>
									Ada <?php echo html_escape(number_format($pending_orders_count, 0, ',', '.')); ?> pesanan yang menunggu diproses.
								<?php else: ?>
									Tidak ada notifikasi baru untuk saat ini.
								<?php endif; ?>
							</div>
							<?php if ($can_view_orders): ?><a class="header-notification-link" href="<?php echo html_escape(site_url('admin/orders')); ?>">Lihat pesanan <span class="ms-1" aria-hidden="true">→</span></a><?php endif; ?>
						</div>
					</div>
					<div class="dropdown">
						<button class="header-profile-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu profil pengguna">
							<span class="header-profile-avatar"><?php echo html_escape(strtoupper(substr((string) $this->session->userdata('user_name'), 0, 1))); ?></span>
							<span class="header-profile-copy">
								<span class="header-profile-name"><?php echo html_escape($this->session->userdata('user_name')); ?></span>
								<span class="header-profile-role"><?php echo html_escape($sidebar_role); ?></span>
							</span>
							<svg class="header-profile-caret" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
						</button>
						<div class="dropdown-menu dropdown-menu-end header-menu">
							<div class="header-menu-caption">SELAMAT DATANG!</div>
							<button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#accountModal">
								<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.6"></circle><path d="M5 20a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path></svg>
								<span>Akun Saya</span>
							</button>
							<?php if ($can_open_pharmacy_settings): ?>
								<a class="dropdown-item" href="<?php echo html_escape(site_url('admin/pharmacy_settings')); ?>">
									<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z" stroke="currentColor" stroke-width="1.6"></path><path d="m19.4 15 .1.1a1.8 1.8 0 1 1-2.5 2.5l-.1-.1a1.8 1.8 0 0 0-3 .8v.2a1.8 1.8 0 1 1-3.6 0v-.2a1.8 1.8 0 0 0-3-.8l-.1.1a1.8 1.8 0 1 1-2.5-2.5l.1-.1a1.8 1.8 0 0 0-.8-3H4a1.8 1.8 0 1 1 0-3.6h.2a1.8 1.8 0 0 0 .8-3l-.1-.1a1.8 1.8 0 1 1 2.5-2.5l.1.1a1.8 1.8 0 0 0 3-.8V2a1.8 1.8 0 1 1 3.6 0v.2a1.8 1.8 0 0 0 3 .8l.1-.1a1.8 1.8 0 1 1 2.5 2.5l-.1.1a1.8 1.8 0 0 0 .8 3h.2a1.8 1.8 0 1 1 0 3.6H20a1.8 1.8 0 0 0-.6 2.9Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"></path></svg>
									<span>Pengaturan</span>
								</a>
							<?php endif; ?>
							<div class="dropdown-divider"></div>
							<a class="dropdown-item header-menu-logout" href="<?php echo html_escape(site_url('logout')); ?>">
								<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"></path></svg>
								<span>Keluar</span>
							</a>
						</div>
					</div>
				</div>
			</header>
			<?php if ($demoBannerEnabled): ?>
				<div class="demo-global-banner" role="status" aria-label="Lingkungan demo">DEMO · Lingkungan sintetis · Tidak untuk produksi</div>
			<?php endif; ?>

			<main class="content-area">
				<?php if (isset($content)): ?>
					<?php echo $content; ?>
				<?php else: ?>
					<div class="row g-3">
						<?php
						$cards = array(
							array('Total Produk', 'products'),
							array('Total Pelanggan', 'customers'),
							array('Total Pesanan', 'orders'),
							array('Pesanan Pending', 'pending_orders'),
							array('Stok Minimum', 'low_stock'),
							array('Batch Kedaluwarsa 30 Hari', 'expiring_batches')
						);
						foreach ($cards as $card):
						?>
							<div class="col-12 col-sm-6 col-xl-4">
								<div class="card metric-card h-100">
									<div class="card-body">
										<div class="text-muted small"><?php echo html_escape($card[0]); ?></div>
										<div class="fs-3 fw-semibold"><?php echo html_escape(number_format((float) $metrics[$card[1]], 0, ',', '.')); ?></div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
						<div class="col-12 col-md-6"><div class="card border-0 h-100"><div class="card-body"><div class="text-muted small">Penjualan Lunas Hari Ini</div><div class="fs-4 fw-semibold">Rp <?php echo html_escape(number_format((float) $metrics['today_sales'], 0, ',', '.')); ?></div></div></div></div>
						<div class="col-12 col-md-6"><div class="card border-0 h-100"><div class="card-body"><div class="text-muted small">Penjualan Lunas Bulan Ini</div><div class="fs-4 fw-semibold">Rp <?php echo html_escape(number_format((float) $metrics['month_sales'], 0, ',', '.')); ?></div></div></div></div>
					</div>
				<?php endif; ?>
			</main>
		</div>
	</div>
	<div class="modal fade" id="crudModal" tabindex="-1" aria-labelledby="crudModalTitle" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
			<div class="modal-content border-0 shadow">
				<div class="modal-header d-flex justify-content-between align-items-center">
					<div class="d-flex align-items-center gap-3">
						<span class="modal-brand-mark" aria-hidden="true">+</span>
						<div><div class="modal-eyebrow">APOTEK ONLINE · ADMIN</div><h2 class="modal-title mb-0" id="crudModalTitle">Kelola Data</h2></div>
					</div>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
				</div>
				<div class="modal-body" id="crudModalContent"></div>
			</div>
		</div>
	</div>
	<div class="modal fade" id="accountModal" tabindex="-1" aria-labelledby="accountModalTitle" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-sm">
			<div class="modal-content">
				<div class="modal-header">
					<h2 class="modal-title fs-6" id="accountModalTitle">Akun Saya</h2>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
				</div>
				<div class="modal-body">
					<div class="d-flex align-items-center gap-3 mb-3">
						<span class="header-profile-avatar"><?php echo html_escape(strtoupper(substr((string) $this->session->userdata('user_name'), 0, 1))); ?></span>
						<div class="min-w-0">
							<div class="fw-semibold text-truncate"><?php echo html_escape($this->session->userdata('user_name')); ?></div>
							<div class="small text-muted"><?php echo html_escape($sidebar_role); ?></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<?php if (!empty($dashboard_charts)): ?><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script><?php endif; ?>
	<script>
		(function () {
			const excelExportUrl = <?php echo json_encode(site_url('admin/exports/excel'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
			const modalElement = document.getElementById('crudModal');
			const modal = new bootstrap.Modal(modalElement);
			const modalTitle = document.getElementById('crudModalTitle');
			const modalContent = document.getElementById('crudModalContent');

			const modalDialog = modalElement.querySelector('.modal-dialog');

			const sidebarToggle = document.getElementById('toggleSidebar');
			const appShell = document.querySelector('.app-shell');
			const sidebar = document.getElementById('adminSidebar');
			if (sidebarToggle && appShell && sidebar) {
				const desktopSidebar = window.matchMedia('(min-width: 768px)');
				const mobileSidebar = bootstrap.Offcanvas.getOrCreateInstance(sidebar);
				const updateSidebarToggle = function () {
					const isDesktop = desktopSidebar.matches;
					const isCollapsed = appShell.classList.contains('sidebar-collapsed');
					const isOpen = sidebar.classList.contains('show');
					const label = isDesktop ? (isCollapsed ? 'Lebarkan sidebar' : 'Ciutkan sidebar') : (isOpen ? 'Tutup menu' : 'Buka menu');
					sidebarToggle.setAttribute('aria-label', label);
					sidebarToggle.setAttribute('title', label);
					sidebarToggle.setAttribute('aria-expanded', String(isDesktop ? !isCollapsed : isOpen));
				};
				sidebarToggle.addEventListener('click', function (event) {
					event.preventDefault();
					event.stopPropagation();
					if (desktopSidebar.matches) {
						appShell.classList.toggle('sidebar-collapsed');
						updateSidebarToggle();
					} else {
						mobileSidebar.toggle();
					}
				});
				sidebar.addEventListener('shown.bs.offcanvas', updateSidebarToggle);
				sidebar.addEventListener('hidden.bs.offcanvas', updateSidebarToggle);
				desktopSidebar.addEventListener('change', function () {
					if (desktopSidebar.matches && sidebar.classList.contains('show')) {
						mobileSidebar.hide();
					}
					updateSidebarToggle();
				});
				updateSidebarToggle();
			}

			const fullscreenButton = document.getElementById('toggleFullscreen');
			if (fullscreenButton) {
				const updateFullscreenLabel = function () {
					const isFullscreen = Boolean(document.fullscreenElement);
					fullscreenButton.setAttribute('aria-label', isFullscreen ? 'Keluar dari layar penuh' : 'Aktifkan layar penuh');
					fullscreenButton.title = isFullscreen ? 'Keluar dari layar penuh' : 'Layar penuh';
				};
				if (!document.fullscreenEnabled || !document.documentElement.requestFullscreen) {
					fullscreenButton.disabled = true;
					fullscreenButton.title = 'Layar penuh tidak didukung browser ini';
				} else {
					fullscreenButton.addEventListener('click', function () {
						const action = document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen();
						action.catch(function () {
							fullscreenButton.title = 'Browser tidak mengizinkan mode layar penuh';
						});
					});
					document.addEventListener('fullscreenchange', updateFullscreenLabel);
					updateFullscreenLabel();
				}
			}

			const menuSearch = document.getElementById('menuSearch');
			if (menuSearch) {
				const menuGroups = Array.from(document.querySelectorAll('.sidebar .nav-group'));
				menuSearch.addEventListener('input', function () {
					const query = menuSearch.value.trim().toLowerCase();
					menuGroups.forEach(function (group) {
						const links = Array.from(group.querySelectorAll('.sidebar-link'));
						let visibleLinks = 0;
						links.forEach(function (link) {
							const matches = !query || link.textContent.toLowerCase().indexOf(query) !== -1;
							link.hidden = !matches;
							if (matches) {
								visibleLinks++;
							}
						});
						group.hidden = visibleLinks === 0;
					});
				});
				menuSearch.addEventListener('keydown', function (event) {
					if (event.key !== 'Enter') {
						return;
					}
					const firstMatch = document.querySelector('.sidebar .sidebar-link:not([hidden])');
					if (firstMatch) {
						event.preventDefault();
						window.location.href = firstMatch.href;
					}
				});
			}

			document.querySelectorAll('.content-area .table-responsive .table:not(.invoice-table)').forEach(function (table) {
				const headerCells = Array.from(table.querySelectorAll('thead tr:last-child th'));
				const headers = headerCells.map(function (header) {
					return header.textContent.trim();
				});
				const dataColumns = headers.map(function (header, index) {
					return {label: header, index: index};
				}).filter(function (column) {
					return column.label && !/aksi|tindakan|action/i.test(column.label);
				});
				const body = table.tBodies[0];
				if (!body) {
					return;
				}
				headerCells.forEach(function (header, index) {
					if (/aksi|tindakan|action/i.test(headers[index])) {
						header.classList.add('print-hidden-column');
						Array.from(table.rows).forEach(function (row) {
							if (row.cells[index]) {
								row.cells[index].classList.add('print-hidden-column');
							}
						});
					}
				});
				table.querySelectorAll('tbody tr').forEach(function (row) {
					Array.from(row.cells).forEach(function (cell, index) {
						if (cell.colSpan > 1 || cell.hasAttribute('data-label')) {
							return;
						}
						const label = headers[index] || (index === row.cells.length - 1 ? 'Aksi' : '');
						if (label) {
							cell.dataset.label = label;
						}
					});
				});

				const wrapper = table.closest('.table-responsive');
				const toolbarContainer = wrapper.parentElement;
				const content = table.closest('.content-area');
				const searchForm = Array.from(content.querySelectorAll('form.row.mb-3')).find(function (form) {
					return form.querySelector('input[name="search"]') && form.dataset.tableToolbarReady !== 'true';
				});
				const toolbar = searchForm || document.createElement('div');
				if (searchForm) {
					searchForm.dataset.tableToolbarReady = 'true';
					searchForm.classList.remove('row', 'g-2', 'mb-3');
					searchForm.classList.add('table-toolbar');
					toolbarContainer.insertBefore(searchForm, wrapper);
				} else {
					toolbar.className = 'table-toolbar';
					const searchInput = document.createElement('input');
					searchInput.type = 'search';
					searchInput.className = 'form-control table-live-search';
					searchInput.placeholder = 'Cari di tabel...';
					searchInput.setAttribute('aria-label', 'Cari pada tabel');
					toolbar.appendChild(searchInput);
					toolbarContainer.insertBefore(toolbar, wrapper);
				}

				const searchInput = toolbar.querySelector('input[type="search"], input[name="search"]');
				const actions = document.createElement('div');
				actions.className = 'table-toolbar-actions';
				const statusIndex = headers.findIndex(function (header) {
					return /status|^aktif$/i.test(header.trim());
				});
				let selectedStatus = '';
				if (statusIndex !== -1) {
					const statusValues = new Map();
					Array.from(body.rows).forEach(function (row) {
						if (row.querySelector('td[colspan]') || !row.cells[statusIndex]) {
							return;
						}
						const value = row.cells[statusIndex].textContent.trim();
						if (value) {
							statusValues.set(value.toLowerCase(), value);
						}
					});
					if (statusValues.size) {
						const filter = document.createElement('select');
						filter.className = 'form-select table-status-filter';
						filter.setAttribute('aria-label', 'Filter status tabel');
						const allStatuses = document.createElement('option');
						allStatuses.value = '';
						allStatuses.textContent = 'Semua status';
						filter.appendChild(allStatuses);
						Array.from(statusValues.entries()).sort(function (left, right) {
							return left[1].localeCompare(right[1]);
						}).forEach(function (entry) {
							const option = document.createElement('option');
							option.value = entry[0];
							option.textContent = entry[1];
							filter.appendChild(option);
						});
						filter.addEventListener('change', function () {
							selectedStatus = filter.value;
							applyTableFilters();
						});
						actions.appendChild(filter);
					}
				}

				const dataRows = Array.from(body.rows).filter(function (row) {
					return !row.querySelector('td[colspan]');
				});
				let noMatchRow = null;
				if (dataRows.length) {
					noMatchRow = document.createElement('tr');
					noMatchRow.className = 'table-filter-empty';
					noMatchRow.dataset.tableNoMatch = 'true';
					noMatchRow.hidden = true;
					const emptyCell = document.createElement('td');
					emptyCell.colSpan = Math.max(headers.length, 1);
					emptyCell.textContent = 'Tidak ada data yang cocok dengan pencarian.';
					noMatchRow.appendChild(emptyCell);
					body.appendChild(noMatchRow);
				}

				const resultCount = document.createElement('span');
				resultCount.className = 'table-results-count';
				resultCount.textContent = dataRows.length + ' data';
				actions.appendChild(resultCount);

				const exportButton = document.createElement('button');
				exportButton.type = 'button';
				exportButton.className = 'table-export-button';
				exportButton.innerHTML = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v11m0 0 4-4m-4 4-4-4M5 16v4h14v-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg><span>Excel</span>';
				exportButton.setAttribute('aria-label', 'Ekspor data tabel ke Excel');
				exportButton.title = 'Unduh baris yang tampil di halaman ini sebagai Excel (.xlsx)';
				actions.appendChild(exportButton);
				toolbar.appendChild(actions);

				function applyTableFilters() {
					const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
					let visibleCount = 0;
					dataRows.forEach(function (row) {
						const matchesText = !query || dataColumns.some(function (column) {
							return row.cells[column.index] && row.cells[column.index].textContent.toLowerCase().indexOf(query) !== -1;
						});
						const matchesStatus = !selectedStatus || !row.cells[statusIndex] || row.cells[statusIndex].textContent.trim().toLowerCase() === selectedStatus;
						const visible = matchesText && matchesStatus;
						row.style.display = visible ? '' : 'none';
						row.classList.toggle('table-filter-hidden', !visible);
						if (visible) {
							visibleCount++;
						}
					});
					if (noMatchRow) {
						noMatchRow.hidden = visibleCount > 0;
					}
					resultCount.textContent = visibleCount + ' / ' + dataRows.length + ' baris';
				}

				if (searchInput) {
					searchInput.addEventListener('input', applyTableFilters);
				}
				applyTableFilters();

				exportButton.addEventListener('click', function () {
					const titleNode = document.querySelector('.page-heading')
						|| document.querySelector('.inventory-page-heading h1')
						|| document.querySelector('.content-area h1');
					const title = titleNode ? titleNode.textContent.trim() : 'data';
					const csrfName = document.querySelector('meta[name="csrf-token-name"]');
					const csrfHash = document.querySelector('meta[name="csrf-token-hash"]');
					if (!csrfName || !csrfName.content || !csrfHash || !csrfHash.content) {
						window.alert('Ekspor Excel gagal karena token keamanan tidak tersedia. Muat ulang halaman lalu coba kembali.');
						return;
					}

					const exportRows = [];
					dataRows.forEach(function (row) {
						if (row.style.display !== 'none') {
							exportRows.push(dataColumns.map(function (column) {
								const cell = row.cells[column.index];
								return cell ? (cell.innerText || cell.textContent).replace(/\s+/g, ' ').trim() : '';
							}));
						}
					});

					const payload = new FormData();
					payload.append('payload', JSON.stringify({
						title: title,
						headers: dataColumns.map(function (column) { return column.label; }),
						rows: exportRows
					}));
					payload.append(csrfName.content, csrfHash.content);

					const originalLabel = exportButton.innerHTML;
					exportButton.disabled = true;
					exportButton.innerHTML = '<span>Menyiapkan Excel...</span>';
					(async function () {
						try {
							const response = await fetch(excelExportUrl, {
								method: 'POST',
								body: payload,
								credentials: 'same-origin',
								headers: {'X-Requested-With': 'XMLHttpRequest'}
							});
							const nextCsrfHash = response.headers.get('X-CSRF-Hash');
							if (nextCsrfHash) {
								csrfHash.content = nextCsrfHash;
							}
							if (response.url.indexOf('/login') !== -1) {
								window.location.href = response.url;
								return;
							}
							if (!response.ok) {
								const errorText = (await response.text()).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
								throw new Error(errorText || 'Server menolak permintaan ekspor (HTTP ' + response.status + ').');
							}
							const contentType = response.headers.get('Content-Type') || '';
							if (contentType.indexOf('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') === -1) {
								throw new Error('Server tidak mengembalikan file Excel yang valid.');
							}
							const file = await response.blob();
							if (!file.size) {
								throw new Error('File Excel yang dihasilkan kosong.');
							}
							const filename = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'data';
							const downloadUrl = URL.createObjectURL(file);
							const download = document.createElement('a');
							download.href = downloadUrl;
							download.download = filename + '-' + new Date().toISOString().slice(0, 10) + '.xlsx';
							document.body.appendChild(download);
							download.click();
							download.remove();
							window.setTimeout(function () { URL.revokeObjectURL(downloadUrl); }, 1000);
						} catch (error) {
							window.alert('Ekspor Excel gagal: ' + error.message);
						} finally {
							exportButton.disabled = false;
							exportButton.innerHTML = originalLabel;
						}
					})();
				});
			});

			const salesCanvas = document.getElementById('salesComparisonChart');
			const salesFallback = document.getElementById('salesChartFallback');
			const dashboardPeriodFilter = document.getElementById('dashboardPeriodFilter');
			const productCategoryCanvas = document.getElementById('productCategoryChart');
			const productCategoryFallback = document.getElementById('productCategoryFallback');
			if (salesCanvas || productCategoryCanvas) {
				if (typeof Chart === 'undefined') {
					if (dashboardPeriodFilter) {
						dashboardPeriodFilter.disabled = true;
						dashboardPeriodFilter.title = 'Filter grafik tidak tersedia karena pustaka grafik gagal dimuat.';
					}
					if (salesFallback) {
						salesFallback.hidden = false;
						salesFallback.textContent = 'Grafik tidak tersedia. Periksa koneksi internet lalu muat ulang halaman.';
					}
					if (productCategoryFallback) {
						productCategoryFallback.hidden = false;
						productCategoryFallback.textContent = 'Grafik tidak tersedia. Periksa koneksi internet lalu muat ulang halaman.';
					}
				} else {
					Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui, sans-serif';
					Chart.defaults.color = '#7b8a81';
					if (salesCanvas) {
						const labels = JSON.parse(salesCanvas.dataset.labels);
						const sales = JSON.parse(salesCanvas.dataset.sales);
						const purchases = JSON.parse(salesCanvas.dataset.purchases);
						const salesContext = salesCanvas.getContext('2d');
						const salesFill = salesContext.createLinearGradient(0, 0, 0, salesCanvas.parentElement.clientHeight || 260);
						salesFill.addColorStop(0, 'rgba(11, 42, 36, 0.18)');
						salesFill.addColorStop(1, 'rgba(11, 42, 36, 0.01)');
						const salesChart = new Chart(salesCanvas, {
							type: 'line',
							data: {
								labels: labels,
								datasets: [
									{label: 'Penjualan', data: sales, borderColor: '#0b2a24', backgroundColor: salesFill, fill: true, tension: 0.35, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 4, pointHoverBackgroundColor: '#0b2a24'},
									{label: 'Pembelian', data: purchases, borderColor: '#08734f', backgroundColor: 'transparent', borderDash: [5, 4], fill: false, tension: 0.35, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, pointHoverBackgroundColor: '#08734f'}
								]
							},
							options: {
								responsive: true,
								maintainAspectRatio: false,
								interaction: {mode: 'index', intersect: false},
								plugins: {
									legend: {display: false},
									tooltip: {
										padding: 11,
										cornerRadius: 9,
										displayColors: true,
										callbacks: {label: function (context) {
											return context.dataset.label + ': ' + new Intl.NumberFormat('id-ID', {style: 'currency', currency: 'IDR', maximumFractionDigits: 0}).format(context.raw);
										}}
									}
								},
								scales: {
									x: {grid: {display: false}, border: {display: false}, ticks: {maxRotation: 0, padding: 8}},
									y: {
										beginAtZero: true,
										border: {display: false},
										grid: {color: '#e9eef5', drawTicks: false},
										ticks: {padding: 10, callback: function (value) {
											return new Intl.NumberFormat('id-ID', {notation: 'compact', maximumFractionDigits: 1}).format(value);
										}}
									}
								}
							}
						});
						if (dashboardPeriodFilter) {
							dashboardPeriodFilter.addEventListener('change', function () {
								const periodLength = Number(dashboardPeriodFilter.value);
								salesChart.data.labels = labels.slice(-periodLength);
								salesChart.data.datasets[0].data = sales.slice(-periodLength);
								salesChart.data.datasets[1].data = purchases.slice(-periodLength);
								salesChart.update();
								const hasSelectedTransactions = sales.slice(-periodLength)
									.concat(purchases.slice(-periodLength))
									.some(function (value) { return Number(value) > 0; });
								if (salesFallback) {
									salesFallback.hidden = hasSelectedTransactions;
									salesFallback.textContent = 'Belum ada transaksi pada ' + periodLength + ' bulan terakhir.';
								}
							});
						}
						const hasTransactions = sales.concat(purchases).some(function (value) { return Number(value) > 0; });
						if (!hasTransactions && salesFallback) {
							salesFallback.hidden = false;
							salesFallback.textContent = 'Belum ada transaksi pada enam bulan terakhir.';
						} else if (salesFallback) {
							salesFallback.hidden = true;
						}
					}
					if (productCategoryCanvas) {
						const labels = JSON.parse(productCategoryCanvas.dataset.labels);
						const values = JSON.parse(productCategoryCanvas.dataset.values);
						const categoryPalette = JSON.parse(productCategoryCanvas.dataset.colors);
						const categoryColors = labels.map(function (label, index) {
							return categoryPalette[index % categoryPalette.length];
						});
						if (!values.length) {
							productCategoryCanvas.hidden = true;
							if (productCategoryFallback) {
								productCategoryFallback.hidden = false;
								productCategoryFallback.textContent = 'Belum ada data produk.';
							}
						} else {
							new Chart(productCategoryCanvas, {
								type: 'doughnut',
								data: {
									labels: labels,
									datasets: [{data: values, backgroundColor: categoryColors, borderColor: '#fff', borderWidth: 3, hoverOffset: 4}]
								},
								options: {
									responsive: true,
									maintainAspectRatio: false,
									cutout: '74%',
									plugins: {legend: {display: false}, tooltip: {padding: 10, cornerRadius: 8}}
								},
								plugins: [{
									id: 'orderCenterLabel',
									afterDraw: function (chart) {
										const area = chart.chartArea;
										if (!area) {
											return;
										}
										const total = values.reduce(function (sum, value) { return sum + Number(value); }, 0);
										const context = chart.ctx;
										context.save();
										context.textAlign = 'center';
										context.fillStyle = '#63746a';
										context.font = '600 9px Inter, sans-serif';
										context.fillText('TOTAL PRODUK', (area.left + area.right) / 2, (area.top + area.bottom) / 2 - 5);
										context.fillStyle = '#17281e';
										context.font = '700 19px Inter, sans-serif';
										context.fillText(new Intl.NumberFormat('id-ID').format(total), (area.left + area.right) / 2, (area.top + area.bottom) / 2 + 19);
										context.restore();
									}
								}]
							});
							if (productCategoryFallback) {
								productCategoryFallback.hidden = true;
							}
						}
					}
				}
			}

			function setModalSize(size) {
				modalDialog.classList.remove('modal-sm', 'modal-lg', 'modal-xl');
				if (['sm', 'lg', 'xl'].indexOf(size) !== -1) {
					modalDialog.classList.add('modal-' + size);
				}
			}

			function renderModalContent(markup, requestedSize) {
				modalContent.innerHTML = markup;
				const sizeElement = modalContent.querySelector('[data-modal-size]');
				setModalSize(requestedSize || (sizeElement ? sizeElement.dataset.modalSize : 'md'));
				const heading = modalContent.querySelector('h1, h2, h3');
				if (heading) {
					modalTitle.textContent = heading.textContent.trim();
					const wrapper = heading.parentElement;
					if (wrapper && wrapper.children.length === 1 && wrapper.textContent.trim() === heading.textContent.trim()) {
						wrapper.remove();
					} else {
						heading.remove();
					}
				}
			}

			function showModalError(message) {
				const alert = document.createElement('div');
				alert.className = 'alert alert-danger mb-3';
				alert.setAttribute('role', 'alert');
				alert.textContent = message;
				modalContent.prepend(alert);
			}

			document.addEventListener('click', async function (event) {
				const trigger = event.target.closest('a[data-modal-url]');
				if (!trigger) {
					return;
				}
				event.preventDefault();
				setModalSize(trigger.dataset.modalSize || 'md');
				modalTitle.textContent = 'Memuat...';
				modalContent.innerHTML = '<div class="text-center text-muted py-5">Memuat formulir...</div>';
				modal.show();
				try {
					const response = await fetch(trigger.href, {
						headers: {'X-Requested-With': 'XMLHttpRequest'}
					});
					if (response.url.indexOf('/login') !== -1) {
						window.location.href = response.url;
						return;
					}
					if (!response.ok) {
						throw new Error('Data gagal dimuat. Silakan coba lagi.');
					}
					renderModalContent(await response.text(), trigger.dataset.modalSize);
				} catch (error) {
					modalContent.innerHTML = '';
					showModalError(error.message);
				}
			});

			modalElement.addEventListener('hidden.bs.modal', function () {
				setModalSize('md');
				modalTitle.textContent = 'Kelola Data';
				modalContent.innerHTML = '';
			});

			document.addEventListener('submit', async function (event) {
				const form = event.target.closest('form[data-crud-form], form[data-crud-delete]');
				if (!form) {
					return;
				}
				event.preventDefault();

				const isDelete = form.hasAttribute('data-crud-delete');
				if (isDelete && !window.confirm(form.dataset.deleteMessage || 'Hapus data ini?')) {
					return;
				}

				const submitButton = form.querySelector('[type="submit"]');
				const originalLabel = submitButton ? submitButton.textContent : '';
				if (submitButton) {
					submitButton.disabled = true;
					submitButton.textContent = isDelete ? 'Menghapus...' : 'Menyimpan...';
				}

				try {
					const response = await fetch(form.action, {
						method: (form.method || 'POST').toUpperCase(),
						body: new FormData(form),
						headers: {'X-Requested-With': 'XMLHttpRequest'}
					});
					if (response.url.indexOf('/login') !== -1) {
						window.location.href = response.url;
						return;
					}
					if (!response.ok) {
						let message = 'Perubahan gagal disimpan. Periksa isian dan coba lagi.';
						try {
							const payload = await response.json();
							if (payload.message) {
								message = payload.message;
							}
							if (payload.csrf_token_name && payload.csrf_hash) {
								const tokenField = form.elements.namedItem(payload.csrf_token_name);
								if (tokenField) {
									tokenField.value = payload.csrf_hash;
								}
							}
						} catch (error) {
							message = 'Perubahan gagal disimpan. Periksa isian dan coba lagi.';
						}
						throw new Error(message);
					}

					const finalUrl = new URL(response.url);
					if (response.redirected && finalUrl.pathname !== window.location.pathname) {
						renderModalContent(await response.text());
						return;
					}
					window.location.reload();
				} catch (error) {
					if (submitButton) {
						submitButton.disabled = false;
						submitButton.textContent = originalLabel;
					}
					if (isDelete) {
						window.alert(error.message);
					} else {
						showModalError(error.message);
					}
				}
			});
		})();
	</script>
</body>
</html>

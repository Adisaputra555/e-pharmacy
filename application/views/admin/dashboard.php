<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<style>
	.dashboard-page { --dash-ink: #15231c; --dash-muted: #6e7c73; --dash-line: #e9eeea; --dash-green: #073a27; --dash-lime: #a5e900; color: var(--dash-ink); }
	.dashboard-titlebar { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; margin: 0 0 20px; }
	.dashboard-titlebar h2 { margin: 0; color: #14251c; font-size: 26px; font-weight: 700; letter-spacing: -.04em; }
	.dashboard-titlebar p { margin: 5px 0 0; color: var(--dash-muted); font-size: 12px; }
	.dashboard-date { display: inline-flex; align-items: center; gap: 8px; padding: 10px 13px; border: 1px solid var(--dash-line); border-radius: 999px; background: #fff; color: #35473c; font-size: 11px; font-weight: 600; white-space: nowrap; }
	.dashboard-date svg { width: 15px; height: 15px; fill: none; stroke: #688074; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.5; }
	.dashboard-title-tools { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
	.dashboard-period-control { display: inline-flex; align-items: center; gap: 8px; min-height: 39px; padding: 0 11px; border: 1px solid var(--dash-line); border-radius: 999px; background: #fff; color: #5d6e63; font-size: 10px; }
	.dashboard-period-control select { min-width: 82px; padding: 0 18px 0 0; border: 0; outline: 0; background-color: transparent; color: #25392d; font: inherit; font-weight: 650; cursor: pointer; }
	.dashboard-period-control:focus-within { border-color: #8cae96; box-shadow: 0 0 0 3px rgba(23,97,62,.08); }
	.dashboard-primary-grid { margin-bottom: 13px; }
	.dashboard-chart-grid { margin-top: 20px !important; }
	.dashboard-feature-card, .dashboard-primary-card { display: flex; flex-direction: column; justify-content: space-between; height: 100%; min-height: 168px; padding: 20px 22px; border: 1px solid var(--dash-line); border-radius: 18px; background: #fff; transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease; }
	.dashboard-feature-card { position: relative; overflow: hidden; border-color: #073a27; background: #073a27; color: #fff; }
	.dashboard-feature-card:hover, .dashboard-primary-card:hover, .dashboard-overview-card:hover { border-color: #cbd9ce; box-shadow: 0 8px 20px rgba(22,43,30,.06); transform: translateY(-2px); }
	.dashboard-feature-card:hover { border-color: #073a27; }
	.dashboard-metric-label { color: #718177; font-size: 11px; font-weight: 600; }
	.dashboard-feature-card .dashboard-metric-label { color: #c2d9cb; }
	.dashboard-metric-value { margin-top: 16px; color: #14271d; font-size: clamp(25px, 2.5vw, 34px); font-weight: 700; letter-spacing: -.045em; line-height: 1.1; overflow-wrap: anywhere; }
	.dashboard-feature-card .dashboard-metric-value { position: relative; z-index: 1; color: #fff; }
	.dashboard-metric-note { margin-top: 7px; color: #849188; font-size: 10px; }
	.dashboard-feature-card .dashboard-metric-note { color: #bed4c6; }
	.dashboard-kpi-mark { display: flex; align-items: center; justify-content: space-between; }
	.dashboard-kpi-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--dash-lime); }
	.dashboard-primary-card .dashboard-kpi-dot { background: #0d6946; }
	.dashboard-summary-title { margin: 23px 0 10px; color: #53645a; font-size: 10px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; }
	.dashboard-overview-card { display: flex; align-items: center; gap: 12px; min-height: 82px; height: 100%; padding: 14px 15px; border: 1px solid var(--dash-line); border-radius: 15px; background: #fff; transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease; }
	.dashboard-overview-icon { display: grid; place-items: center; width: 34px; height: 34px; flex: 0 0 34px; border-radius: 10px; background: #f2f5f2; }
	.dashboard-overview-icon::before { width: 8px; height: 8px; border-radius: 50%; background: #375f49; content: ""; }
	.dashboard-overview-card.is-warning .dashboard-overview-icon { background: #fbf5e8; }
	.dashboard-overview-card.is-warning .dashboard-overview-icon::before { background: #ae7a19; }
	.dashboard-overview-card.is-alert .dashboard-overview-icon { background: #fbefed; }
	.dashboard-overview-card.is-alert .dashboard-overview-icon::before { background: #b94a42; }
	.dashboard-overview-label { color: #56665c; font-size: 10px; font-weight: 550; line-height: 1.35; }
	.dashboard-overview-value { margin-top: 3px; color: #20352a; font-size: 16px; font-weight: 700; letter-spacing: -.02em; }
	.dashboard-panel { height: 100%; padding: 20px; border: 1px solid var(--dash-line); border-radius: 18px; background: #fff; box-shadow: 0 4px 16px rgba(22, 43, 30, .025); }
	.dashboard-panel-heading { align-items: flex-start; margin-bottom: 15px; }
	.dashboard-panel-heading h3 { margin: 0; color: #1b2b21; font-size: 15px; font-weight: 700; letter-spacing: -.02em; }
	.dashboard-panel-heading p { margin: 4px 0 0; color: var(--dash-muted); font-size: 10px; line-height: 1.5; }
	.dashboard-legend { display: flex; gap: 14px; flex-wrap: wrap; color: #5e6f64; font-size: 10px; }
	.dashboard-legend span { display: inline-flex; align-items: center; gap: 6px; }
	.legend-dot { width: 8px; height: 8px; border-radius: 50%; }
	.dot-sales { background: #073a27; }
	.dot-purchases { background: #a5e900; }
	.dashboard-chart-wrap { position: relative; height: 285px; }
	.dashboard-donut-wrap { position: relative; height: 260px; }
	.dashboard-chart-fallback { position: absolute; inset: 0; display: grid; place-items: center; color: var(--dash-muted); font-size: 11px; text-align: center; }
	.dashboard-chart-fallback[hidden] { display: none; }
	.dashboard-analysis { margin-top: 25px !important; }
	.dashboard-analysis > .dashboard-panel-heading { padding: 0 1px; margin-bottom: 13px; }
	.dashboard-analysis > .dashboard-panel-heading h3 { color: #18271e; font-size: 18px; font-weight: 720; letter-spacing: -.025em; }
	.dashboard-analysis > .dashboard-panel-heading p { max-width: 900px; color: var(--dash-muted); font-size: 10px; line-height: 1.55; }
	.dashboard-market-list { display: grid; gap: 7px; }
	.dashboard-market-list-scroll { max-height: 386px; overflow-y: auto; overscroll-behavior: contain; padding: 1px 5px 1px 1px; scrollbar-color: #cbd6ce #f5f7f5; scrollbar-width: thin; }
	.dashboard-market-row { display: grid; grid-template-columns: 30px minmax(0, 1fr) 78px 67px 102px; align-items: center; gap: 9px; min-height: 61px; padding: 8px 10px; border: 1px solid #edf1ed; border-radius: 12px; background: #fff; transition: border-color .15s ease, background .15s ease; }
	.dashboard-market-row:hover { border-color: #cbd9ce; background: #fbfdfb; }
	.dashboard-market-rank { display: grid; place-items: center; width: 26px; height: 26px; border-radius: 8px; background: #f1f4f1; color: #627268; font-size: 10px; font-weight: 700; }
	.dashboard-market-row:first-child .dashboard-market-rank { background: #eaf3ed; color: #145d3c; }
	.dashboard-market-name { min-width: 0; }
	.dashboard-market-name strong { display: block; overflow: hidden; color: #203329; font-size: 11px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
	.dashboard-market-name small { display: block; margin-top: 3px; color: #77857c; font-size: 9px; }
	.dashboard-market-quantity { color: #1e3428; font-size: 12px; font-weight: 700; line-height: 1.25; text-align: right; white-space: nowrap; }
	.dashboard-market-quantity small { display: block; margin-top: 3px; color: #718077; font-size: 8px; font-weight: 500; }
	.dashboard-market-stock { display: block; margin-top: 3px; color: #53675a; font-size: 9px; font-weight: 500; }
	.dashboard-market-sparkline { width: 67px; height: 27px; overflow: visible; }
	.dashboard-market-sparkline polyline { fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 2; }
	.dashboard-market-trend { display: inline-flex; align-items: center; justify-content: center; gap: 4px; min-width: 100px; padding: 6px 7px; border-radius: 8px; font-size: 9px; font-weight: 650; white-space: nowrap; }
	.dashboard-market-trend-up { background: #edf7f0; color: #17613e; }
	.dashboard-market-trend-down { background: #fff0ef; color: #a6423d; }
	.dashboard-market-trend-steady { background: #f2f4f2; color: #58675e; }
	.dashboard-rank-panel { min-height: 455px; }
	.dashboard-coverage-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(120px, .75fr); align-items: center; gap: 12px; padding: 10px 11px; border: 1px solid #edf1ed; border-radius: 11px; }
	.dashboard-coverage-product { min-width: 0; }
	.dashboard-coverage-product strong { display: block; overflow: hidden; color: #203329; font-size: 10px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
	.dashboard-coverage-product small { display: block; margin-top: 4px; color: #64746a; font-size: 9px; }
	.dashboard-coverage-value { min-width: 115px; }
	.dashboard-coverage-value strong { display: block; color: #20352a; font-size: 11px; font-weight: 700; }
	.dashboard-coverage-track { height: 5px; overflow: hidden; margin-top: 6px; border-radius: 999px; background: #edf1ed; }
	.dashboard-coverage-track span { display: block; height: 100%; border-radius: inherit; background: #17613e; }
	.coverage-tone-danger { background: #b94a42 !important; }
	.coverage-tone-warning { background: #ba861e !important; }
	.coverage-tone-success { background: #17613e !important; }
	.coverage-tone-primary { background: #789083 !important; }
	.coverage-tone-secondary { background: #a8b1aa !important; }
	.coverage-tone-overstock { background: #ba861e !important; }
	.dashboard-coverage-caption { display: block; margin-top: 4px; color: #65756a; font-size: 9px; }
	.dashboard-overstock-list { display: grid; gap: 9px; max-height: 390px; overflow-y: auto; overscroll-behavior: contain; padding: 1px 5px 1px 1px; scrollbar-color: #cbd6ce #f5f7f5; scrollbar-width: thin; }
	.dashboard-overstock-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px 12px; align-items: center; padding: 11px 12px; border: 1px solid #edf1ed; border-radius: 11px; }
	.dashboard-overstock-row strong { display: block; overflow: hidden; color: #203329; font-size: 10px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
	.dashboard-overstock-row small { display: block; margin-top: 3px; color: #64746a; font-size: 9px; }
	.dashboard-overstock-coverage { color: #855b15; font-size: 10px; font-weight: 700; white-space: nowrap; }
	.dashboard-forecast-wrap { max-height: 490px; overflow: auto; overscroll-behavior: contain; scrollbar-color: #cbd6ce #f5f7f5; scrollbar-width: thin; }
	.dashboard-forecast-table { min-width: 1180px; }
	.dashboard-forecast-table thead th { position: sticky; z-index: 2; top: 0; }
	.forecast-horizons { display: flex; align-items: stretch; gap: 4px; }
	.forecast-horizon { display: grid; gap: 2px; min-width: 52px; padding: 5px 6px; border-radius: 7px; background: #f4f7f4; text-align: center; }
	.forecast-horizon small { color: #65756a; font-size: 8px; }
	.forecast-horizon strong { color: #20392b; font-size: 10px; font-weight: 700; }
	.forecast-history { display: flex; align-items: center; gap: 5px; margin-top: 5px; }
	.forecast-history svg { width: 54px; height: 17px; color: #17613e; }
	.forecast-history polyline { fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
	.forecast-history small { color: #65756a; font-size: 8px; }
	.dashboard-rise { animation: dashboard-rise .35s ease-out both; }
	@keyframes dashboard-rise { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
	.dashboard-table-wrap { overflow-x: auto; border: 1px solid var(--dash-line); border-radius: 12px; }
	.dashboard-data-table { margin: 0; color: #36483d; font-size: 10px; }
	.dashboard-data-table thead th { padding: 11px 12px; background: #f5f7f5; color: #53665a; font-size: 9px; font-weight: 700; white-space: nowrap; }
	.dashboard-data-table tbody td { padding: 11px 12px; border-color: #edf1ed; vertical-align: middle; }
	.dashboard-data-table tbody tr:hover td { background: #fafcfa; }
	.dashboard-data-table .badge { padding: 5px 8px; border: 0; border-radius: 999px; font-size: 9px; font-weight: 650; }
	.dashboard-data-table .badge.text-bg-success { background: #edf7f0 !important; color: #17613e !important; }
	.dashboard-data-table .badge.text-bg-danger { background: #fff0ef !important; color: #a6423d !important; }
	.dashboard-data-table .badge.text-bg-warning { background: #fcf5e7 !important; color: #8a6417 !important; }
	.dashboard-data-table .badge.text-bg-info { background: #edf4f6 !important; color: #426d78 !important; }
	.dashboard-data-table .badge.text-bg-secondary, .dashboard-data-table .badge.text-bg-light { background: #f1f3f1 !important; color: #5e6c63 !important; }
	.dashboard-forecast-status { padding: 6px 9px !important; }
	.dashboard-empty { padding: 23px 12px; color: #64746a; text-align: center; font-size: 11px; }
	@media (max-width: 1199.98px) {
		.dashboard-market-row { grid-template-columns: 28px minmax(0, 1fr) 72px 60px 100px; gap: 7px; padding: 8px; }
	}
	@media (max-width: 767.98px) {
		.dashboard-titlebar { align-items: flex-start; flex-direction: column; gap: 12px; }
		.dashboard-titlebar h2 { font-size: 24px; }
		.dashboard-title-tools { justify-content: flex-start; }
		.dashboard-panel { padding: 16px; border-radius: 15px; }
		.dashboard-chart-wrap { height: 250px; }
		.dashboard-donut-wrap { height: 235px; }
		.dashboard-market-row { grid-template-columns: 28px minmax(0, 1fr) 68px 100px; gap: 7px; }
		.dashboard-market-quantity { grid-column: 3; grid-row: 1; }
		.dashboard-market-sparkline { grid-column: 3; grid-row: 2; width: 64px; }
		.dashboard-market-trend { grid-column: 4; grid-row: 1 / span 2; min-width: 96px; }
		.dashboard-table-wrap { overflow-x: auto !important; }
		.dashboard-data-table { display: table !important; width: max-content; min-width: 100%; }
		.dashboard-data-table thead { position: static !important; width: auto !important; height: auto !important; margin: 0 !important; overflow: visible !important; clip: auto !important; }
		.dashboard-data-table tbody { display: table-row-group !important; padding: 0 !important; }
		.dashboard-data-table tbody tr { display: table-row !important; overflow: visible !important; border: 0 !important; border-radius: 0 !important; box-shadow: none !important; }
		.dashboard-data-table tbody td { display: table-cell !important; white-space: nowrap; }
		.dashboard-overview-card { min-height: 74px; gap: 9px; padding: 11px; }
		.dashboard-overview-icon { width: 29px; height: 29px; flex-basis: 29px; }
		.dashboard-overview-label { font-size: 9px; }
		.dashboard-overview-value { font-size: 14px; }
		.dashboard-rank-panel { min-height: 0; }
		.dashboard-market-list-scroll { max-height: 340px; }
		.dashboard-overstock-list { max-height: 330px; }
		.dashboard-forecast-wrap { max-height: 420px; }
		.dashboard-coverage-row { grid-template-columns: minmax(0, 1fr) minmax(104px, .7fr); gap: 8px; padding: 9px; }
	}
	@media (prefers-reduced-motion: reduce) {
		.dashboard-page *, .dashboard-page *::before, .dashboard-page *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; scroll-behavior: auto !important; transition-duration: .01ms !important; }
	}
</style>
<style>
	#apotek-dashboard-stitch {
		--dash-ink: #0b1c30;
		--dash-muted: #687889;
		--dash-line: #e5ebf3;
		--dash-surface: #fff;
		--dash-canvas: #f8f9ff;
		--dash-primary: #0b2a24;
		--dash-secondary: #08734f;
		--dash-lime: #b7f36b;
		color: var(--dash-ink);
		font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
	}
	#apotek-dashboard-stitch *, #apotek-dashboard-stitch *::before, #apotek-dashboard-stitch *::after { box-sizing: border-box; }
	#apotek-dashboard-stitch .dashboard-titlebar { align-items: center; margin-bottom: 18px; }
	#apotek-dashboard-stitch .dashboard-titlebar h2 { color: var(--dash-ink); font-size: 25px; font-weight: 720; letter-spacing: -.035em; }
	#apotek-dashboard-stitch .dashboard-titlebar p { color: var(--dash-muted); font-size: 12px; }
	#apotek-dashboard-stitch .dashboard-filter-control { display: inline-flex; align-items: center; gap: 4px; padding: 4px; border: 1px solid var(--dash-line); border-radius: 9px; background: #fff; box-shadow: 0 2px 8px rgba(11,28,48,.035); }
	#apotek-dashboard-stitch .dashboard-date, #apotek-dashboard-stitch .dashboard-period-control { min-height: 32px; border: 0; border-radius: 6px; box-shadow: none; }
	#apotek-dashboard-stitch .dashboard-date { padding: 6px 9px; background: transparent; color: #40536a; font-size: 9px; }
	#apotek-dashboard-stitch .dashboard-date svg { width: 14px; height: 14px; stroke: #667a91; }
	#apotek-dashboard-stitch .dashboard-period-control { gap: 6px; padding: 0 8px; background: #edf3ff; color: #405b80; font-size: 9px; }
	#apotek-dashboard-stitch .dashboard-period-control select { min-width: 67px; padding-right: 14px; color: #233d60; font-size: 9px; font-weight: 700; }
	#apotek-dashboard-stitch .dashboard-feature-card, #apotek-dashboard-stitch .dashboard-primary-card, #apotek-dashboard-stitch .dashboard-overview-card, #apotek-dashboard-stitch .dashboard-panel { border-color: var(--dash-line); border-radius: 12px; box-shadow: 0 2px 8px rgba(11,28,48,.035); }
	#apotek-dashboard-stitch .dashboard-feature-card { min-height: 170px; padding: 21px; border-color: var(--dash-primary); background: var(--dash-primary); }
	#apotek-dashboard-stitch .dashboard-sales-decoration { position: absolute; z-index: 0; right: -14px; bottom: -19px; display: grid; width: 88px; height: 88px; color: var(--dash-lime); opacity: .9; pointer-events: none; transform-origin: center; transition: transform .55s cubic-bezier(.2,.7,.2,1); }
	#apotek-dashboard-stitch .dashboard-sales-decoration svg { width: 100%; height: 100%; overflow: visible; }
	#apotek-dashboard-stitch .dashboard-sales-decoration path { fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 8; }
	#apotek-dashboard-stitch .dashboard-primary-card { min-height: 170px; padding: 21px; }
	@media (hover: hover) {
		#apotek-dashboard-stitch .dashboard-feature-card:hover, #apotek-dashboard-stitch .dashboard-primary-card:hover { border-color: #cbd9ce; box-shadow: 0 12px 26px rgba(11,28,48,.13); transform: translateY(-4px); }
		#apotek-dashboard-stitch .dashboard-feature-card:hover { border-color: var(--dash-primary); }
		#apotek-dashboard-stitch .dashboard-feature-card:hover .dashboard-sales-decoration { transform: rotate(9deg) scale(1.04); }
	}
	#apotek-dashboard-stitch .dashboard-kpi-mark { min-height: 32px; }
	#apotek-dashboard-stitch .dashboard-metric-heading { display: inline-flex; align-items: center; gap: 9px; }
	#apotek-dashboard-stitch .dashboard-kpi-dot { width: 8px; height: 8px; flex: 0 0 8px; background: var(--dash-lime); }
	#apotek-dashboard-stitch .dashboard-primary-card .dashboard-kpi-dot { background: var(--dash-secondary); }
	#apotek-dashboard-stitch .dashboard-primary-icon { width: 20px; height: 20px; flex: 0 0 20px; fill: none; stroke: var(--dash-lime); stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.8; }
	#apotek-dashboard-stitch .dashboard-primary-icon-tile { display: grid; place-items: center; width: 32px; height: 32px; flex: 0 0 32px; border-radius: 9px; background: #edf4f1; color: var(--dash-secondary); }
	#apotek-dashboard-stitch .dashboard-primary-icon-tile-value { background: #eef2fb; color: #35506b; }
	#apotek-dashboard-stitch .dashboard-primary-icon-tile svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.7; }
	#apotek-dashboard-stitch .dashboard-metric-value { color: var(--dash-ink); font-size: clamp(24px, 2.4vw, 32px); }
	#apotek-dashboard-stitch .dashboard-feature-card .dashboard-metric-value { color: #fff; }
	#apotek-dashboard-stitch .dashboard-metric-unit { margin-left: 5px; color: #718198; font-size: 12px; font-weight: 600; letter-spacing: 0; }
	#apotek-dashboard-stitch .dashboard-feature-card .dashboard-metric-unit { color: #d3e2dc; }
	#apotek-dashboard-stitch .dashboard-metric-label { font-size: 10px; letter-spacing: .045em; text-transform: uppercase; }
	#apotek-dashboard-stitch .dashboard-metric-note { font-size: 10px; }
	#apotek-dashboard-stitch .dashboard-summary-title { margin: 20px 0 9px; color: #657587; font-size: 10px; letter-spacing: .09em; }
	#apotek-dashboard-stitch .dashboard-overview-grid { --bs-gutter-x: .65rem; --bs-gutter-y: .65rem; }
	#apotek-dashboard-stitch .dashboard-overview-card { display: flex; flex-direction: column; align-items: stretch; justify-content: space-between; gap: 9px; min-height: 86px; padding: 11px 12px; background: #fff; }
	#apotek-dashboard-stitch .dashboard-overview-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 7px; min-height: 30px; }
	#apotek-dashboard-stitch .dashboard-overview-icon { display: grid; place-items: center; width: 28px; height: 28px; flex: 0 0 28px; border-radius: 8px; background: #edf4f1; color: var(--dash-secondary); }
	#apotek-dashboard-stitch .dashboard-overview-icon::before { display: none; }
	#apotek-dashboard-stitch .dashboard-overview-icon svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.7; }
	#apotek-dashboard-stitch .dashboard-overview-customers .dashboard-overview-icon, #apotek-dashboard-stitch .dashboard-overview-orders .dashboard-overview-icon { background: #eef2fb; color: #435b7a; }
	#apotek-dashboard-stitch .dashboard-overview-pending .dashboard-overview-icon { background: #fff5dc; color: #a76c09; }
	#apotek-dashboard-stitch .dashboard-overview-critical .dashboard-overview-icon, #apotek-dashboard-stitch .dashboard-overview-expired .dashboard-overview-icon { background: #fff0ef; color: #bd4440; }
	#apotek-dashboard-stitch .dashboard-overview-expiry .dashboard-overview-icon { background: #fff5dc; color: #a76c09; }
	#apotek-dashboard-stitch .dashboard-overview-label { color: #64748b; font-size: 9px; font-weight: 600; line-height: 1.35; }
	#apotek-dashboard-stitch .dashboard-overview-value-wrap { min-width: 0; }
	#apotek-dashboard-stitch .dashboard-overview-value { margin: 0; color: var(--dash-ink); font-size: 15px; font-weight: 730; letter-spacing: -.025em; line-height: 1.15; overflow-wrap: anywhere; }
	#apotek-dashboard-stitch .dashboard-overview-card.is-warning .dashboard-overview-value { color: #96620d; }
	#apotek-dashboard-stitch .dashboard-overview-card.is-alert .dashboard-overview-value { color: #b83b37; }
	#apotek-dashboard-stitch .dashboard-panel { padding: 18px; background: var(--dash-surface); }
	#apotek-dashboard-stitch .dashboard-panel-heading h3 { color: var(--dash-ink); font-size: 14px; font-weight: 700; }
	#apotek-dashboard-stitch .dashboard-panel-heading p { color: var(--dash-muted); font-size: 10px; }
	#apotek-dashboard-stitch .dashboard-chart-grid { margin-top: 15px !important; }
	#apotek-dashboard-stitch .dashboard-chart-wrap { height: 255px; }
	#apotek-dashboard-stitch .dashboard-donut-wrap { display: flex; align-items: center; height: auto; min-height: 235px; }
	#apotek-dashboard-stitch .dashboard-category-layout { display: grid; grid-template-columns: minmax(150px, .9fr) minmax(0, 1.1fr); align-items: center; gap: 12px; width: 100%; }
	#apotek-dashboard-stitch .dashboard-category-chart { position: relative; width: 100%; height: 190px; }
	#apotek-dashboard-stitch .dashboard-category-legend { display: grid; gap: 8px; min-width: 0; }
	#apotek-dashboard-stitch .dashboard-category-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 7px; border-radius: 8px; transition: background-color .16s ease; }
	#apotek-dashboard-stitch .dashboard-category-item:hover { background: #f4f7fb; }
	#apotek-dashboard-stitch .dashboard-category-name { display: flex; align-items: center; gap: 7px; min-width: 0; color: #3b4b5d; font-size: 9px; }
	#apotek-dashboard-stitch .dashboard-category-name span:last-child { overflow: hidden; text-overflow: ellipsis; }
	#apotek-dashboard-stitch .dashboard-category-swatch { width: 8px; height: 8px; flex: 0 0 8px; border-radius: 50%; }
	#apotek-dashboard-stitch .dashboard-category-value { flex: 0 0 auto; color: #33465a; font-size: 9px; font-weight: 700; white-space: nowrap; }
	#apotek-dashboard-stitch .dashboard-analysis { margin-top: 17px !important; }
	#apotek-dashboard-stitch .dashboard-analysis > .dashboard-panel-heading { margin: 3px 1px 10px; }
	#apotek-dashboard-stitch .dashboard-analysis > .dashboard-panel-heading h3 { color: var(--dash-ink); font-size: 16px; }
	#apotek-dashboard-stitch .dashboard-market-list { gap: 8px; }
	#apotek-dashboard-stitch .dashboard-market-list-scroll { max-height: 390px; padding: 1px; }
	#apotek-dashboard-stitch .dashboard-market-row { min-height: 57px; padding: 8px 9px; border-color: #edf1f6; border-radius: 9px; }
	#apotek-dashboard-stitch .dashboard-market-row:hover { border-color: #cbd9e8; background: #f9fbfe; }
	#apotek-dashboard-stitch .dashboard-market-rank { border-radius: 7px; background: #eaf0f8; color: #35506b; }
	#apotek-dashboard-stitch .dashboard-market-row:first-child .dashboard-market-rank { background: var(--dash-primary); color: var(--dash-lime); }
	#apotek-dashboard-stitch .dashboard-market-name strong { color: var(--dash-ink); font-size: 10px; }
	#apotek-dashboard-stitch .dashboard-market-quantity { color: var(--dash-ink); }
	#apotek-dashboard-stitch .dashboard-market-trend-up { background: #eaf6ef; color: #08734f; }
	#apotek-dashboard-stitch .dashboard-market-trend-down { background: #fff0ef; color: #a6423d; }
	#apotek-dashboard-stitch .dashboard-market-trend-steady { background: #f0f3f7; color: #58677a; }
	#apotek-dashboard-stitch .dashboard-rank-panel { min-height: 370px; }
	#apotek-dashboard-stitch .dashboard-movement-panel { min-height: 0; }
	#apotek-dashboard-stitch .dashboard-movement-panel > .dashboard-panel-heading { display: flex; justify-content: space-between; gap: 12px; }
	#apotek-dashboard-stitch .dashboard-movement-tabs { flex: 0 0 auto; gap: 2px; padding: 3px; border-radius: 8px; background: #eef2fb; }
	#apotek-dashboard-stitch .dashboard-movement-tabs .nav-link { padding: 6px 9px; border-radius: 6px; color: #627186; font-size: 9px; font-weight: 650; line-height: 1.15; }
	#apotek-dashboard-stitch .dashboard-movement-tabs .nav-link.active { background: #fff; color: var(--dash-primary); box-shadow: 0 1px 5px rgba(11,28,48,.09); }
	#apotek-dashboard-stitch .dashboard-tab-caption { margin: 0 0 10px; }
	#apotek-dashboard-stitch .dashboard-tab-caption h4 { margin: 0; color: #203329; font-size: 11px; font-weight: 700; }
	#apotek-dashboard-stitch .dashboard-tab-caption p { margin: 3px 0 0; color: var(--dash-muted); font-size: 9px; line-height: 1.4; }
	#apotek-dashboard-stitch .dashboard-movement-content { min-height: 310px; }
	#apotek-dashboard-stitch .dashboard-health-panel { min-height: 0; }
	#apotek-dashboard-stitch .dashboard-health-summary { padding: 11px; border: 1px solid #edf1f6; border-radius: 9px; background: #f7f9fc; }
	#apotek-dashboard-stitch .dashboard-health-summary-heading { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 9px; }
	#apotek-dashboard-stitch .dashboard-health-summary-heading strong { color: #33465a; font-size: 10px; font-weight: 700; }
	#apotek-dashboard-stitch .dashboard-health-summary-heading small { color: #718198; font-size: 8px; text-align: right; }
	#apotek-dashboard-stitch .dashboard-health-statuses { display: flex; flex-wrap: wrap; gap: 6px; }
	#apotek-dashboard-stitch .dashboard-health-status { display: inline-flex; align-items: center; gap: 5px; padding: 5px 7px; border: 1px solid #e8edf4; border-radius: 7px; background: #fff; color: #526174; font-size: 8px; white-space: nowrap; }
	#apotek-dashboard-stitch .dashboard-health-status strong { color: #263a30; font-size: 9px; }
	#apotek-dashboard-stitch .dashboard-health-status-dot { width: 7px; height: 7px; flex: 0 0 7px; border-radius: 2px; background: #94a3b8; }
	#apotek-dashboard-stitch .dashboard-health-status-kritis .dashboard-health-status-dot { background: #d64242; }
	#apotek-dashboard-stitch .dashboard-health-status-perlu-diperhatikan .dashboard-health-status-dot { background: #f3a712; }
	#apotek-dashboard-stitch .dashboard-health-status-ideal .dashboard-health-status-dot { background: #08734f; }
	#apotek-dashboard-stitch .dashboard-health-status-overstock .dashboard-health-status-dot { background: #e6a10a; }
	#apotek-dashboard-stitch .dashboard-health-columns { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 15px; }
	#apotek-dashboard-stitch .dashboard-health-section { min-width: 0; }
	#apotek-dashboard-stitch .dashboard-health-section-heading { min-height: 35px; margin-bottom: 7px; }
	#apotek-dashboard-stitch .dashboard-health-section-heading h4 { margin: 0; color: #33465a; font-size: 10px; font-weight: 700; }
	#apotek-dashboard-stitch .dashboard-health-section-heading span { display: block; margin-top: 3px; color: #718198; font-size: 8px; }
	#apotek-dashboard-stitch .dashboard-health-scroll { max-height: 290px; gap: 7px; padding-right: 3px; }
	#apotek-dashboard-stitch .dashboard-health-section .dashboard-coverage-row { grid-template-columns: minmax(0, 1fr); gap: 7px; padding: 9px; }
	#apotek-dashboard-stitch .dashboard-health-section .dashboard-coverage-value { min-width: 0; }
	#apotek-dashboard-stitch .dashboard-health-section .dashboard-coverage-product strong, #apotek-dashboard-stitch .dashboard-health-section .dashboard-overstock-row strong { font-size: 9px; }
	#apotek-dashboard-stitch .dashboard-health-section .dashboard-coverage-product small, #apotek-dashboard-stitch .dashboard-health-section .dashboard-overstock-row small { font-size: 8px; }
	#apotek-dashboard-stitch .dashboard-health-section .dashboard-overstock-row { grid-template-columns: minmax(0, 1fr); gap: 6px; padding: 9px; }
	#apotek-dashboard-stitch .dashboard-health-section .dashboard-overstock-coverage { text-align: right; }
	#apotek-dashboard-stitch .dashboard-coverage-row, #apotek-dashboard-stitch .dashboard-overstock-row { border-color: #edf1f6; border-radius: 9px; }
	#apotek-dashboard-stitch .dashboard-coverage-track { height: 6px; background: #edf1f6; }
	#apotek-dashboard-stitch .coverage-tone-success { background: var(--dash-secondary) !important; }
	#apotek-dashboard-stitch .coverage-tone-primary, #apotek-dashboard-stitch .coverage-tone-secondary { background: #94a3b8 !important; }
	#apotek-dashboard-stitch .coverage-tone-warning, #apotek-dashboard-stitch .coverage-tone-overstock { background: #f3a712 !important; }
	#apotek-dashboard-stitch .coverage-tone-danger { background: #d64242 !important; }
	#apotek-dashboard-stitch .dashboard-forecast-wrap { max-height: 455px; border-color: var(--dash-line); border-radius: 10px; }
	#apotek-dashboard-stitch .dashboard-forecast-table { min-width: 1120px; }
	#apotek-dashboard-stitch .dashboard-forecast-panel { padding: 21px; }
	#apotek-dashboard-stitch .dashboard-forecast-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 15px; }
	#apotek-dashboard-stitch .dashboard-forecast-title { display: flex; align-items: flex-start; gap: 10px; min-width: 0; }
	#apotek-dashboard-stitch .dashboard-forecast-title-mark { width: 9px; height: 9px; flex: 0 0 9px; margin-top: 5px; border-radius: 50%; background: var(--dash-secondary); }
	#apotek-dashboard-stitch .dashboard-forecast-title h3 { margin: 0; color: var(--dash-ink); font-size: 15px; font-weight: 720; letter-spacing: -.02em; }
	#apotek-dashboard-stitch .dashboard-forecast-title p { margin: 4px 0 0; color: #526174; font-size: 10px; }
	#apotek-dashboard-stitch .dashboard-forecast-title small { display: block; margin-top: 4px; color: var(--dash-muted); font-size: 9px; line-height: 1.45; }
	#apotek-dashboard-stitch .dashboard-forecast-statuses { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 7px; max-width: 48%; }
	#apotek-dashboard-stitch .dashboard-forecast-status-card { display: grid; grid-template-columns: minmax(0, auto) auto; align-items: center; gap: 8px; min-height: 36px; padding: 6px 9px; border: 1px solid #e8edf4; border-radius: 8px; background: #f1f5fb; color: #5c6e83; font-size: 8px; }
	#apotek-dashboard-stitch .dashboard-forecast-status-card strong { color: var(--dash-primary); font-size: 12px; }
	#apotek-dashboard-stitch .dashboard-forecast-note { margin: 0 0 12px; padding: 9px 11px; border-radius: 8px; background: #f7f9fc; color: #66778a; font-size: 9px; line-height: 1.45; }
	#apotek-dashboard-stitch .dashboard-forecast-wrap { display: grid; gap: 8px; max-height: 520px; padding: 1px 5px 1px 1px; border: 0; }
	#apotek-dashboard-stitch .dashboard-forecast-product { display: grid; grid-template-columns: minmax(130px, 1.05fr) minmax(190px, 1.35fr) minmax(155px, 1fr) minmax(175px, 1.15fr); align-items: center; gap: 13px; padding: 12px 13px; border: 1px solid #edf1f6; border-radius: 9px; background: #fff; transition: border-color .16s ease, box-shadow .16s ease; }
	#apotek-dashboard-stitch .dashboard-forecast-product:hover { border-color: #d3deeb; box-shadow: 0 4px 12px rgba(11,28,48,.045); }
	#apotek-dashboard-stitch .dashboard-forecast-product-identity, #apotek-dashboard-stitch .dashboard-forecast-demand, #apotek-dashboard-stitch .dashboard-forecast-projection, #apotek-dashboard-stitch .dashboard-forecast-stock { min-width: 0; }
	#apotek-dashboard-stitch .dashboard-forecast-product-name strong { display: block; overflow: hidden; color: #22364c; font-size: 10px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
	#apotek-dashboard-stitch .dashboard-forecast-product-name small { display: block; margin-top: 3px; color: #7a8a9d; font-size: 8px; }
	#apotek-dashboard-stitch .dashboard-forecast-product-badges { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
	#apotek-dashboard-stitch .dashboard-forecast-product-badges .badge { padding: 4px 6px; border-radius: 5px; font-size: 7px; font-weight: 650; }
	#apotek-dashboard-stitch .dashboard-forecast-base-unit { display: block; margin-top: 5px; color: #7a8a9d; font-size: 8px; }
	#apotek-dashboard-stitch .dashboard-forecast-block-title { display: flex; align-items: baseline; justify-content: space-between; gap: 5px; margin-bottom: 7px; color: #64748b; font-size: 8px; font-weight: 700; text-transform: uppercase; }
	#apotek-dashboard-stitch .dashboard-forecast-block-title small { color: #8391a2; font-size: 7px; font-weight: 500; text-align: right; text-transform: none; }
	#apotek-dashboard-stitch .dashboard-forecast-months { display: grid; grid-template-columns: repeat(3, minmax(35px, 1fr)) 48px; align-items: center; gap: 4px; }
	#apotek-dashboard-stitch .dashboard-forecast-month { display: grid; gap: 3px; min-width: 0; padding: 5px; border-radius: 6px; background: #f2f6fc; text-align: center; }
	#apotek-dashboard-stitch .dashboard-forecast-month small { overflow: hidden; color: #718198; font-size: 7px; text-overflow: ellipsis; white-space: nowrap; }
	#apotek-dashboard-stitch .dashboard-forecast-month strong { color: #273e55; font-size: 9px; font-weight: 700; }
	#apotek-dashboard-stitch .dashboard-forecast-demand-summary { margin-top: 6px; color: #66778a; font-size: 8px; line-height: 1.4; }
	#apotek-dashboard-stitch .dashboard-forecast-demand-summary span { padding: 0 2px; color: #a0acb9; }
	#apotek-dashboard-stitch .dashboard-forecast-product .forecast-horizons { gap: 5px; }
	#apotek-dashboard-stitch .dashboard-forecast-product .forecast-horizon { flex: 1 1 0; min-width: 0; padding: 6px 5px; background: #eff4fb; }
	#apotek-dashboard-stitch .dashboard-forecast-product .forecast-horizon small { font-size: 7px; }
	#apotek-dashboard-stitch .dashboard-forecast-product .forecast-horizon strong { font-size: 9px; }
	#apotek-dashboard-stitch .dashboard-forecast-coverage-value { display: block; color: var(--dash-primary); font-size: 12px; font-weight: 750; line-height: 1.2; }
	#apotek-dashboard-stitch .dashboard-forecast-stock > small { display: block; margin-top: 3px; color: #6b7b8d; font-size: 8px; line-height: 1.4; }
	#apotek-dashboard-stitch .dashboard-forecast-stock-warnings { display: grid; gap: 3px; margin-top: 5px; font-size: 7px; line-height: 1.35; }
	#apotek-dashboard-stitch .forecast-warning-critical { color: #b83b37; }
	#apotek-dashboard-stitch .forecast-warning-caution { color: #98660a; }
	#apotek-dashboard-stitch .dashboard-data-table { color: #3b4b5d; font-size: 10px; }
	#apotek-dashboard-stitch .dashboard-data-table thead th { padding: 10px 11px; background: #f1f5fb; color: #5c6e83; font-size: 8px; letter-spacing: .06em; text-transform: uppercase; }
	#apotek-dashboard-stitch .dashboard-data-table tbody td { padding: 10px 11px; border-color: #edf1f6; color: #3b4b5d; }
	#apotek-dashboard-stitch .dashboard-data-table tbody tr:hover td { background: #f8fafd; }
	#apotek-dashboard-stitch .dashboard-data-table .badge { border-radius: 6px; font-size: 8px; }
	#apotek-dashboard-stitch .dashboard-data-table .badge.text-bg-success { background: #e8f5ed !important; color: #08734f !important; }
	#apotek-dashboard-stitch .dashboard-data-table .badge.text-bg-danger { background: #fff0ef !important; color: #a6423d !important; }
	#apotek-dashboard-stitch .dashboard-data-table .badge.text-bg-warning { background: #fff5dc !important; color: #89620f !important; }
	#apotek-dashboard-stitch .dashboard-data-table .badge.text-bg-info, #apotek-dashboard-stitch .dashboard-data-table .badge.text-bg-secondary, #apotek-dashboard-stitch .dashboard-data-table .badge.text-bg-light { background: #edf2f8 !important; color: #4a5d72 !important; }
	#apotek-dashboard-stitch .dashboard-forecast-status { padding: 6px 9px !important; border: 1px solid #e8edf4; border-radius: 7px; background: #f3f6fa !important; color: #506174 !important; }
	#apotek-dashboard-stitch .forecast-horizon { border-radius: 6px; background: #f1f5fb; }
	#apotek-dashboard-stitch .forecast-horizon strong { color: var(--dash-primary); }
	#apotek-dashboard-stitch .dashboard-empty { color: var(--dash-muted); }
	#apotek-dashboard-stitch .dashboard-rise { animation-duration: .28s; }
	@media (max-width: 1199.98px) {
		#apotek-dashboard-stitch .dashboard-category-layout { grid-template-columns: minmax(140px, .8fr) minmax(0, 1.2fr); }
		#apotek-dashboard-stitch .dashboard-market-row { grid-template-columns: 28px minmax(0, 1fr) 72px 60px 100px; gap: 7px; padding: 8px; }
		#apotek-dashboard-stitch .dashboard-forecast-header { flex-direction: column; }
		#apotek-dashboard-stitch .dashboard-forecast-statuses { justify-content: flex-start; max-width: none; }
		#apotek-dashboard-stitch .dashboard-forecast-product { grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr); }
	}
	@media (max-width: 767.98px) {
		#apotek-dashboard-stitch .dashboard-titlebar { align-items: flex-start; flex-direction: column; gap: 12px; }
		#apotek-dashboard-stitch .dashboard-title-tools { justify-content: flex-start; }
		#apotek-dashboard-stitch .dashboard-movement-panel > .dashboard-panel-heading { flex-direction: column; }
		#apotek-dashboard-stitch .dashboard-movement-tabs { width: fit-content; }
		#apotek-dashboard-stitch .dashboard-feature-card, #apotek-dashboard-stitch .dashboard-primary-card { min-height: 145px; padding: 16px; }
		#apotek-dashboard-stitch .dashboard-panel { padding: 15px; }
		#apotek-dashboard-stitch .dashboard-chart-wrap { height: 220px; }
		#apotek-dashboard-stitch .dashboard-category-layout { grid-template-columns: 1fr; }
		#apotek-dashboard-stitch .dashboard-category-chart { height: 185px; max-width: 220px; margin: 0 auto; }
		#apotek-dashboard-stitch .dashboard-market-row { grid-template-columns: 28px minmax(0, 1fr) 68px 96px; gap: 7px; }
		#apotek-dashboard-stitch .dashboard-market-quantity { grid-column: 3; grid-row: 1; }
		#apotek-dashboard-stitch .dashboard-market-sparkline { grid-column: 3; grid-row: 2; width: 60px; }
		#apotek-dashboard-stitch .dashboard-market-trend { grid-column: 4; grid-row: 1 / span 2; min-width: 92px; }
		#apotek-dashboard-stitch .dashboard-rank-panel { min-height: 0; }
		#apotek-dashboard-stitch .dashboard-health-columns { grid-template-columns: 1fr; }
		#apotek-dashboard-stitch .dashboard-health-scroll { max-height: 260px; }
		#apotek-dashboard-stitch .dashboard-overstock-list { max-height: 300px; }
		#apotek-dashboard-stitch .dashboard-forecast-table { display: table !important; width: max-content; min-width: 1120px; }
		#apotek-dashboard-stitch .dashboard-forecast-product { grid-template-columns: 1fr; gap: 10px; }
		#apotek-dashboard-stitch .dashboard-forecast-table thead { position: static !important; width: auto !important; height: auto !important; margin: 0 !important; overflow: visible !important; clip: auto !important; }
		#apotek-dashboard-stitch .dashboard-forecast-table tbody { display: table-row-group !important; padding: 0 !important; }
		#apotek-dashboard-stitch .dashboard-forecast-table tbody tr { display: table-row !important; overflow: visible !important; border: 0 !important; border-radius: 0 !important; box-shadow: none !important; }
		#apotek-dashboard-stitch .dashboard-forecast-table tbody td { display: table-cell !important; white-space: nowrap; }
	}
	@media (prefers-reduced-motion: reduce) {
		#apotek-dashboard-stitch *, #apotek-dashboard-stitch *::before, #apotek-dashboard-stitch *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; scroll-behavior: auto !important; transition-duration: .01ms !important; }
	}
</style>
<div class="dashboard-page apotek-dashboard-stitch" id="apotek-dashboard-stitch">
	<header class="dashboard-titlebar">
		<div>
			<h2>Dasbor</h2>
			<p>Ringkasan operasional apotek hari ini.</p>
		</div>
		<div class="dashboard-title-tools">
			<div class="dashboard-filter-control" role="group" aria-label="Tanggal saat ini dan periode grafik">
				<div class="dashboard-date">
					<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" /><path d="M8 3.5v3M16 3.5v3M4 9h16" /></svg>
					<?php echo html_escape($dashboard_date); ?>
				</div>
				<label class="dashboard-period-control">
					<span>Grafik</span>
					<select id="dashboardPeriodFilter" aria-label="Periode grafik penjualan">
						<option value="6">6 bulan</option>
						<option value="3">3 bulan</option>
					</select>
				</label>
			</div>
		</div>
	</header>

	<div class="row g-3 dashboard-primary-grid">
		<div class="col-12 col-md-4">
			<section class="dashboard-feature-card dashboard-rise">
				<span class="dashboard-sales-decoration" aria-hidden="true">
					<svg viewBox="0 0 80 80"><path d="M56 4 35 76M13 18l54 45M8 49l64-23" /></svg>
				</span>
				<div class="dashboard-kpi-mark">
					<span class="dashboard-metric-heading"><span class="dashboard-kpi-dot"></span><span class="dashboard-metric-label">Penjualan lunas bulan ini</span></span>
					<svg class="dashboard-primary-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 17l5-5 4 3 7-8M14 7h6v6" /></svg>
				</div>
				<div>
					<div class="dashboard-metric-value">Rp <span data-count-up data-count-target="<?php echo html_escape(number_format((float) $metrics['month_sales'], 0, '.', '')); ?>"><?php echo html_escape(number_format((float) $metrics['month_sales'], 0, ',', '.')); ?></span></div>
					<div class="dashboard-metric-note">Transaksi lunas; tanggal memakai pembayaran lunas pertama.</div>
				</div>
			</section>
		</div>
		<div class="col-12 col-md-4">
			<section class="dashboard-primary-card dashboard-rise">
				<div class="dashboard-kpi-mark">
					<span class="dashboard-metric-label">Total produk katalog</span>
					<span class="dashboard-primary-icon-tile" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3ZM4 7.5l8 4.6 8-4.6M12 12.1V21" /></svg></span>
				</div>
				<div>
					<div class="dashboard-metric-value"><span data-count-up data-count-target="<?php echo html_escape(number_format((float) $metrics['products'], 0, '.', '')); ?>"><?php echo html_escape(number_format((float) $metrics['products'], 0, ',', '.')); ?></span><small class="dashboard-metric-unit">SKU</small></div>
					<div class="dashboard-metric-note">Produk dalam katalog</div>
				</div>
			</section>
		</div>
		<div class="col-12 col-md-4">
			<section class="dashboard-primary-card dashboard-rise">
				<div class="dashboard-kpi-mark">
					<span class="dashboard-metric-label">Nilai persediaan</span>
					<span class="dashboard-primary-icon-tile dashboard-primary-icon-tile-value" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 6.5h15a2 2 0 0 1 2 2v10H5a2 2 0 0 1-2-2v-11a2 2 0 0 1 2-2h13M3 9h18M16 14h2" /></svg></span>
				</div>
				<div>
					<div class="dashboard-metric-value">Rp <span data-count-up data-count-target="<?php echo html_escape(number_format((float) $metrics['inventory_value'], 0, '.', '')); ?>"><?php echo html_escape(number_format((float) $metrics['inventory_value'], 0, ',', '.')); ?></span></div>
					<div class="dashboard-metric-note">Berdasarkan HPP batch aktif</div>
				</div>
			</section>
		</div>
	</div>

	<h3 class="dashboard-summary-title">Ringkasan lainnya</h3>
	<div class="row g-2 dashboard-overview-grid">
		<?php
		$overview_cards = array(
			array('Penjualan lunas hari ini', 'today_sales', 'currency', '', 'sales'),
			array('Total pelanggan', 'customers', 'number', '', 'customers'),
			array('Total pesanan', 'orders', 'number', '', 'orders'),
			array('Pesanan menunggu', 'pending_orders', 'number', 'warning', 'pending'),
			array('Stok kritis', 'low_stock', 'number', 'alert', 'critical'),
			array('Produk mendekati kedaluwarsa', 'near_expiry_products', 'number', 'warning', 'expiry'),
			array('Batch kedaluwarsa ≤ 30 hari', 'expiring_batches', 'number', 'alert', 'expired')
		);
		$overview_icon_paths = array(
			'sales' => 'M4 17l5-5 4 3 7-8M14 7h6v6',
			'customers' => 'M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM16 4.2a3.5 3.5 0 0 1 0 6.8M16 15h.5a3.5 3.5 0 0 1 3.5 3.5V20',
			'orders' => 'M7 3.5h8l3 3V20H7zM10 11h5M10 14.5h5M10 18h3M15 3.5v3h3',
			'pending' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM12 7v5l3 2',
			'critical' => 'm12 3 9 17H3L12 3ZM12 9v5M12 17.5h.01',
			'expiry' => 'M4 6h16v15H4zM8 3v6M16 3v6M4 10h16M8 14h3M8 17h5',
			'expired' => 'M4 7h16v14H4zM4 7l8-4 8 4M12 11v5M12 18h.01'
		);
		foreach ($overview_cards as $card):
			$overview_state = $card[3] !== '' && (float) $metrics[$card[1]] > 0
				? ' is-' . $card[3]
				: '';
		?>
			<div class="col-6 col-md-3 col-xl">
				<div class="dashboard-overview-card dashboard-overview-<?php echo html_escape($card[4]); ?><?php echo html_escape($overview_state); ?>">
					<div class="dashboard-overview-top">
						<div class="dashboard-overview-label"><?php echo html_escape($card[0]); ?></div>
						<span class="dashboard-overview-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="<?php echo html_escape($overview_icon_paths[$card[4]]); ?>" /></svg></span>
					</div>
					<div class="dashboard-overview-value-wrap">
						<div class="dashboard-overview-value"><?php echo html_escape($card[2] === 'currency' ? 'Rp ' : ''); ?><?php echo html_escape(number_format((float) $metrics[$card[1]], 0, ',', '.')); ?></div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

<div class="row g-3 dashboard-chart-grid">
	<div class="col-12 col-xl-8">
		<section class="dashboard-panel h-100 dashboard-rise">
			<div class="dashboard-panel-heading">
				<div>
					<h3>Penjualan lunas &amp; pembelian</h3>
					<p>Penjualan lunas menurut tanggal pembayaran pertama dan pembelian enam bulan terakhir.</p>
				</div>
				<div class="dashboard-legend">
					<span><i class="legend-dot dot-sales"></i>Penjualan lunas</span>
					<span><i class="legend-dot dot-purchases"></i>Pembelian</span>
				</div>
			</div>
			<div class="dashboard-chart-wrap">
				<canvas id="salesComparisonChart"
					data-labels="<?php echo html_escape(json_encode($sales_chart['labels'])); ?>"
					data-sales="<?php echo html_escape(json_encode($sales_chart['sales'])); ?>"
					data-purchases="<?php echo html_escape(json_encode($sales_chart['purchases'])); ?>"
					aria-label="Grafik penjualan lunas dan pembelian enam bulan terakhir" role="img"></canvas>
				<div class="dashboard-chart-fallback" id="salesChartFallback" hidden>Grafik belum dapat dimuat.</div>
			</div>
		</section>
	</div>
	<div class="col-12 col-xl-4">
		<section class="dashboard-panel h-100 dashboard-rise">
			<div class="dashboard-panel-heading">
				<div>
					<h3>Produk per kategori</h3>
					<p>Komposisi katalog produk.</p>
				</div>
			</div>
			<div class="dashboard-donut-wrap">
				<?php
				$category_colors = array('#0b2a24', '#08734f', '#f3a712', '#475569', '#38a879', '#91a4b7', '#cad5df');
				$category_total = array_sum($product_category_chart['values']);
				?>
				<div class="dashboard-category-layout">
					<div class="dashboard-category-chart">
						<canvas id="productCategoryChart"
							data-labels="<?php echo html_escape(json_encode($product_category_chart['labels'])); ?>"
							data-values="<?php echo html_escape(json_encode($product_category_chart['values'])); ?>"
							data-colors="<?php echo html_escape(json_encode($category_colors)); ?>"
							aria-label="Grafik jumlah produk per kategori" role="img"></canvas>
						<div class="dashboard-chart-fallback" id="productCategoryFallback" hidden>Belum ada data produk.</div>
					</div>
					<div class="dashboard-category-legend">
						<?php foreach ($product_category_chart['labels'] as $index => $category_label): ?>
							<?php
							$category_value = (int) $product_category_chart['values'][$index];
							$category_percent = $category_total > 0 ? ($category_value / $category_total * 100) : 0;
							$category_color = $category_colors[$index % count($category_colors)];
							?>
							<div class="dashboard-category-item">
								<div class="dashboard-category-name">
									<span class="dashboard-category-swatch" style="background-color: <?php echo html_escape($category_color); ?>"></span>
									<span><?php echo html_escape($category_label); ?></span>
								</div>
								<span class="dashboard-category-value"><?php echo html_escape(number_format($category_percent, 0, ',', '.')); ?>% <span class="text-muted">(<?php echo html_escape(number_format($category_value, 0, ',', '.')); ?>)</span></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</section>
	</div>
</div>

<section class="dashboard-analysis mt-4">
	<div class="dashboard-panel-heading">
		<div>
			<h3>Analisis pergerakan &amp; persediaan</h3>
			<p>Peringkat unit dasar transaksi lunas memakai 90 hari terakhir (<?php echo html_escape($ranking_period['start']); ?>–<?php echo html_escape($ranking_period['end']); ?>); cakupan stok memakai tiga bulan kalender lengkap (<?php echo html_escape($sales_window['start']); ?>–<?php echo html_escape($sales_window['end']); ?>).</p>
		</div>
	</div>
	<div class="row g-3">
		<div class="col-12 col-xl-6">
			<section class="dashboard-panel dashboard-rank-panel dashboard-movement-panel h-100 dashboard-rise">
				<div class="dashboard-panel-heading">
						<div>
							<h3>Analisis pergerakan obat</h3>
							<p>Performa perputaran obat apotek dalam 90 hari terakhir.</p>
						</div>
						<ul class="nav nav-pills dashboard-movement-tabs" role="tablist" aria-label="Pilih analisis pergerakan obat">
							<li class="nav-item" role="presentation">
								<button class="nav-link active" id="dashboard-fast-tab" data-bs-toggle="pill" data-bs-target="#dashboard-fast-moving" type="button" role="tab" aria-controls="dashboard-fast-moving" aria-selected="true">Fast-Moving</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" id="dashboard-slow-tab" data-bs-toggle="pill" data-bs-target="#dashboard-slow-moving" type="button" role="tab" aria-controls="dashboard-slow-moving" aria-selected="false">Slow-Moving</button>
							</li>
						</ul>
				</div>
				<div class="tab-content dashboard-movement-content">
						<div class="tab-pane fade show active" id="dashboard-fast-moving" role="tabpanel" aria-labelledby="dashboard-fast-tab" tabindex="0">
							<div class="dashboard-tab-caption">
								<h4>Top 5 Obat Terlaris</h4>
								<p>Unit dasar dari transaksi lunas 90 hari terakhir; tren membandingkan dua periode 30 hari.</p>
						</div>
							<div class="dashboard-market-list dashboard-market-list-scroll">
								<?php if (!$top_selling_products): ?><div class="dashboard-empty">Belum ada transaksi lunas pada periode ini.</div><?php endif; ?>
								<?php foreach (array_slice($top_selling_products, 0, 5) as $index => $product): ?>
									<?php
									$trend = $product->sales_trend;
									$trend_class = $trend['direction'] === 'up' ? 'up' : ($trend['direction'] === 'down' ? 'down' : 'steady');
									$trend_symbol = $trend['direction'] === 'up' ? '▲' : ($trend['direction'] === 'down' ? '▼' : '→');
									$trend_accessible_label = 'Tren ' . ($trend['direction'] === 'up' ? 'naik' : ($trend['direction'] === 'down' ? 'turun' : 'stabil')) . ', ' . $trend['change'];
									$trend_display_label = $trend_symbol . ' ' . ($trend['direction'] === 'up' ? ($trend['change'] === 'Baru' ? 'Baru' : 'Naik ' . $trend['change']) : ($trend['direction'] === 'down' ? 'Turun ' . $trend['change'] : 'Stabil ' . $trend['change']));
									$sold_quantity_display = rtrim(rtrim(number_format((float) $product->recent_sold_quantity, 2, ',', '.'), '0'), ',');
									$max_trend = max($trend['periods']);
									$min_trend = min($trend['periods']);
									$trend_range = $max_trend - $min_trend;
									$trend_points = array();
									foreach ($trend['periods'] as $point_index => $point_value) {
										$x = 2 + ($point_index * 33);
										$y = $trend_range > 0 ? 22 - (($point_value - $min_trend) / $trend_range * 18) : 13;
										$trend_points[] = $x . ',' . number_format($y, 1, '.', '');
									}
									?>
									<div class="dashboard-market-row">
										<div class="dashboard-market-rank"><?php echo html_escape((int) $index + 1); ?></div>
										<div class="dashboard-market-name"><strong><?php echo html_escape($product->name); ?></strong><small><?php echo html_escape($product->code); ?></small></div>
										<div class="dashboard-market-quantity"><?php echo html_escape($sold_quantity_display); ?><small>unit dasar / 90 hari</small></div>
										<svg class="dashboard-market-sparkline dashboard-market-trend-<?php echo html_escape($trend_class); ?>" viewBox="0 0 72 26" role="img" aria-label="Grafik penjualan lunas tiga periode 30 hari">
											<polyline points="<?php echo html_escape(implode(' ', $trend_points)); ?>"></polyline>
										</svg>
										<span class="dashboard-market-trend dashboard-market-trend-<?php echo html_escape($trend_class); ?>" aria-label="<?php echo html_escape($trend_accessible_label); ?>"><?php echo html_escape($trend_display_label); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="tab-pane fade" id="dashboard-slow-moving" role="tabpanel" aria-labelledby="dashboard-slow-tab" tabindex="0">
							<div class="dashboard-tab-caption">
								<h4>Top 5 Obat Slow-Moving</h4>
								<p>Produk dengan stok tersedia dan unit dasar transaksi lunas terendah; tren membandingkan dua periode 30 hari.</p>
							</div>
							<div class="dashboard-market-list dashboard-market-list-scroll">
								<?php if (!$sales_period_has_data): ?><div class="dashboard-empty">Data penjualan belum cukup pada periode acuan.</div><?php elseif (!$slow_moving_products): ?><div class="dashboard-empty">Tidak ada produk dengan stok tersedia.</div><?php endif; ?>
								<?php if ($sales_period_has_data): foreach (array_slice($slow_moving_products, 0, 5) as $index => $product): ?>
									<?php
									$trend = $product->sales_trend;
									$trend_class = $trend['direction'] === 'up' ? 'up' : ($trend['direction'] === 'down' ? 'down' : 'steady');
									$trend_symbol = $trend['direction'] === 'up' ? '▲' : ($trend['direction'] === 'down' ? '▼' : '→');
									$trend_accessible_label = 'Tren ' . ($trend['direction'] === 'up' ? 'naik' : ($trend['direction'] === 'down' ? 'turun' : 'stabil')) . ', ' . $trend['change'];
									$trend_display_label = $trend_symbol . ' ' . ($trend['direction'] === 'up' ? ($trend['change'] === 'Baru' ? 'Baru' : 'Naik ' . $trend['change']) : ($trend['direction'] === 'down' ? 'Turun ' . $trend['change'] : 'Stabil ' . $trend['change']));
									$sold_quantity_display = rtrim(rtrim(number_format((float) $product->recent_sold_quantity, 2, ',', '.'), '0'), ',');
									$available_stock_display = rtrim(rtrim(number_format((float) $product->available_stock, 2, ',', '.'), '0'), ',');
									$max_trend = max($trend['periods']);
									$min_trend = min($trend['periods']);
									$trend_range = $max_trend - $min_trend;
									$trend_points = array();
									foreach ($trend['periods'] as $point_index => $point_value) {
										$x = 2 + ($point_index * 33);
										$y = $trend_range > 0 ? 22 - (($point_value - $min_trend) / $trend_range * 18) : 13;
										$trend_points[] = $x . ',' . number_format($y, 1, '.', '');
									}
									?>
									<div class="dashboard-market-row dashboard-market-row-slow">
										<div class="dashboard-market-rank"><?php echo html_escape((int) $index + 1); ?></div>
										<div class="dashboard-market-name"><strong><?php echo html_escape($product->name); ?></strong><small><?php echo html_escape($product->code); ?></small></div>
										<div class="dashboard-market-quantity"><?php echo html_escape($sold_quantity_display); ?><small>unit dasar lunas / 90 hari</small><span class="dashboard-market-stock">Stok: <?php echo html_escape($available_stock_display); ?></span></div>
										<svg class="dashboard-market-sparkline dashboard-market-trend-<?php echo html_escape($trend_class); ?>" viewBox="0 0 72 26" role="img" aria-label="Grafik penjualan lunas tiga periode 30 hari"><polyline points="<?php echo html_escape(implode(' ', $trend_points)); ?>"></polyline></svg>
										<span class="dashboard-market-trend dashboard-market-trend-<?php echo html_escape($trend_class); ?>" aria-label="<?php echo html_escape($trend_accessible_label); ?>"><?php echo html_escape($trend_display_label); ?></span>
									</div>
								<?php endforeach; endif; ?>
							</div>
						</div>
				</div>
			</section>
		</div>
		<div class="col-12 col-xl-6">
			<section class="dashboard-panel dashboard-health-panel h-100 dashboard-rise">
				<div class="dashboard-panel-heading">
						<div>
							<h3>Kesehatan distribusi stok</h3>
							<p>Coverage berdasarkan tiga bulan kalender lengkap dan pemantauan obat overstock.</p>
						</div>
				</div>
				<?php
				$coverage_status_counts = array(
						'kritis' => 0,
						'perlu diperhatikan' => 0,
						'ideal' => 0,
						'overstock' => 0,
						'belum cukup data' => 0
				);
				foreach ($coverage_products as $coverage_product) {
						$coverage_status_key = strtolower(trim($coverage_product->coverage_status));
						if (isset($coverage_status_counts[$coverage_status_key])) {
							$coverage_status_counts[$coverage_status_key]++;
						}
				}
				$coverage_status_labels = array(
						'kritis' => 'Kritis',
						'perlu diperhatikan' => 'Perlu perhatian',
						'ideal' => 'Ideal',
						'overstock' => 'Overstock',
						'belum cukup data' => 'Belum cukup data'
				);
				?>
				<div class="dashboard-health-summary">
						<div class="dashboard-health-summary-heading">
							<strong>Ringkasan status cakupan</strong>
							<small>Dari daftar rasio stok (maks. 20 produk)</small>
						</div>
						<div class="dashboard-health-statuses">
							<?php foreach ($coverage_status_labels as $status_key => $status_label): ?>
								<?php $status_class = str_replace(' ', '-', $status_key); ?>
								<div class="dashboard-health-status dashboard-health-status-<?php echo html_escape($status_class); ?>">
									<span class="dashboard-health-status-dot" aria-hidden="true"></span>
									<span><?php echo html_escape($status_label); ?></span>
									<strong><?php echo html_escape((int) $coverage_status_counts[$status_key]); ?></strong>
								</div>
							<?php endforeach; ?>
						</div>
				</div>
				<div class="dashboard-health-columns">
						<section class="dashboard-health-section">
							<div class="dashboard-health-section-heading">
								<h4>Rasio sisa stok</h4>
								<span>Cakupan / target 6 bln</span>
						</div>
							<div class="dashboard-overstock-list dashboard-health-scroll">
								<?php if (!$coverage_products): ?><div class="dashboard-empty">Belum ada produk dengan stok tersedia.</div><?php endif; ?>
								<?php foreach ($coverage_products as $product): ?>
									<?php
									$coverage_percent = $product->coverage_months === null
										? null
										: max(0, (float) $product->coverage_months / 6 * 100);
									$coverage_bar_width = $coverage_percent === null ? 0 : min(100, $coverage_percent);
									?>
									<div class="dashboard-coverage-row">
										<div class="dashboard-coverage-product">
											<strong><?php echo html_escape($product->name); ?></strong>
											<small>Stok <?php echo html_escape(number_format($product->available_stock, 2, ',', '.')); ?> · Rata-rata <?php echo html_escape(number_format($product->average_monthly_sales, 2, ',', '.')); ?>/bln</small>
										</div>
										<div class="dashboard-coverage-value">
											<strong><?php echo html_escape($product->coverage_months === null ? '—' : number_format($product->coverage_months, 1, ',', '.') . ' bln'); ?></strong>
											<div class="dashboard-coverage-track" role="img" aria-label="<?php echo html_escape($coverage_percent === null ? 'Cakupan tidak tersedia' : number_format($coverage_percent, 0, ',', '.') . '% dari target enam bulan'); ?>">
												<span class="coverage-tone-<?php echo html_escape($product->coverage_class); ?>" style="width: <?php echo html_escape(number_format($coverage_bar_width, 1, '.', '')); ?>%"></span>
											</div>
											<span class="dashboard-coverage-caption"><?php echo html_escape($coverage_percent === null ? $product->coverage_status : number_format($coverage_percent, 0, ',', '.') . '% target 6 bln'); ?> · <?php echo html_escape($product->coverage_status); ?></span>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</section>
						<section class="dashboard-health-section">
							<div class="dashboard-health-section-heading">
								<h4>Perlu redistribusi / promosi</h4>
								<span>Obat overstock (&gt; 6 bln)</span>
							</div>
							<div class="dashboard-overstock-list dashboard-health-scroll">
								<?php if (!$overstock_products): ?><div class="dashboard-empty"><?php echo html_escape($sales_period_has_data ? 'Tidak ada produk dengan stok lebih dari 6 bulan.' : 'Belum dapat diklasifikasikan karena data penjualan belum cukup.'); ?></div><?php endif; ?>
								<?php foreach ($overstock_products as $product): ?>
									<?php
									$overstock_percent = max(100, (float) $product->coverage_months / 6 * 100);
									$overstock_bar_width = min(100, $overstock_percent);
									?>
									<div class="dashboard-overstock-row">
										<div>
											<strong><?php echo html_escape($product->name); ?></strong>
											<small>Stok tersedia <?php echo html_escape(number_format($product->available_stock, 2, ',', '.')); ?></small>
											<div class="dashboard-coverage-track"><span class="coverage-tone-overstock" style="width: <?php echo html_escape(number_format($overstock_bar_width, 1, '.', '')); ?>%"></span></div>
										</div>
										<div class="dashboard-overstock-coverage"><?php echo html_escape(number_format($product->coverage_months, 1, ',', '.')); ?> bln<br><small><?php echo html_escape(number_format($overstock_percent, 0, ',', '.')); ?>% target</small></div>
									</div>
								<?php endforeach; ?>
							</div>
						</section>
				</div>
			</section>
		</div>
	</div>
</section>

<?php if (!empty($forecast_dashboard_enabled)): ?>
	<section class="dashboard-panel dashboard-forecast-panel mt-4 dashboard-rise">
		<div class="dashboard-forecast-header">
			<div class="dashboard-forecast-title">
				<span class="dashboard-forecast-title-mark" aria-hidden="true"></span>
				<div>
					<h3>Forecast &amp; Coverage Stok — Moving Average</h3>
					<p><?php echo html_escape($forecast_dashboard['method']); ?> · periode <?php echo html_escape($forecast_dashboard['period']['start']); ?>–<?php echo html_escape($forecast_dashboard['period']['end']); ?></p>
					<small>Forecast per produk dari permintaan bulanan. Slow-moving tetap memakai periode 90 hari terpisah.</small>
				</div>
			</div>
			<div class="dashboard-forecast-statuses" aria-label="Ringkasan status data forecast">
				<?php foreach ($forecast_dashboard['status_counts'] as $status => $count): ?>
					<div class="dashboard-forecast-status-card">
						<span><?php echo html_escape($status); ?></span>
						<strong><?php echo html_escape((int) $count); ?></strong>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="dashboard-forecast-note">
			Stok layak pakai sementara: batch aktif dan belum kedaluwarsa, Σ max(0, sisa − reservasi). Batch merah/kuning tetap dihitung; kuantitasnya hanya peringatan.
		</div>
		<div class="dashboard-forecast-list dashboard-forecast-wrap">
			<?php if (!$forecast_dashboard['products']): ?><div class="dashboard-empty">Belum ada produk aktif untuk dianalisis.</div><?php endif; ?>
			<?php foreach ($forecast_dashboard['products'] as $product): ?>
				<?php
				$status_class = $product['data_status'] === 'Data perlu diperiksa'
					? 'danger'
					: ($product['data_status'] === 'Cukup data'
						? 'success'
						: ($product['data_status'] === 'Tidak ada penjualan' ? 'info' : 'secondary'));
				$base_unit_label = !$product['unit_verified']
					? 'Belum diverifikasi'
					: ($product['base_unit']
						? trim((string) $product['base_unit_code'] . ' — ' . (string) $product['base_unit'])
						: '—');
				$demand_values = array_values($product['monthly_demand']);
				$demand_complete = !in_array(null, $demand_values, true);
				$demand_max = 0;
				foreach ($demand_values as $demand_value) {
					if ($demand_value !== null) {
						$demand_max = max($demand_max, (float) $demand_value);
					}
				}
				$demand_points = array();
				foreach ($demand_values as $demand_index => $demand_value) {
					$demand_x = 2 + ($demand_index * 22);
					$demand_y = $demand_max > 0
						? 15 - ((float) $demand_value / $demand_max * 12)
						: 9;
					$demand_points[] = $demand_x . ',' . number_format($demand_y, 1, '.', '');
				}
				?>
				<article class="dashboard-forecast-product">
					<div class="dashboard-forecast-product-identity">
						<div class="dashboard-forecast-product-name">
							<strong><?php echo html_escape($product['name']); ?></strong>
							<small><?php echo html_escape($product['code']); ?></small>
						</div>
						<div class="dashboard-forecast-product-badges">
							<span class="badge text-bg-<?php echo html_escape($status_class); ?>"><?php echo html_escape($product['data_status']); ?></span>
							<?php if (!$product['unit_verified']): ?><span class="badge text-bg-warning">Satuan belum diverifikasi</span><?php endif; ?>
						</div>
						<small class="dashboard-forecast-base-unit">Base unit: <?php echo html_escape($base_unit_label); ?></small>
					</div>
					<div class="dashboard-forecast-demand">
						<div class="dashboard-forecast-block-title">Permintaan aktual <small>3 bulan lengkap</small></div>
						<div class="dashboard-forecast-months">
							<?php foreach ($forecast_dashboard['period']['months'] as $month): ?>
								<span class="dashboard-forecast-month">
									<small><?php echo html_escape($month); ?></small>
									<strong><?php echo html_escape($product['monthly_demand'][$month] === null ? '—' : number_format((float) $product['monthly_demand'][$month], 3, ',', '.')); ?></strong>
								</span>
							<?php endforeach; ?>
							<?php if ($demand_complete): ?>
								<span class="forecast-history" title="Grafik permintaan aktual tiga bulan terakhir">
									<svg viewBox="0 0 48 18" role="img" aria-label="Grafik permintaan aktual tiga bulan terakhir"><polyline points="<?php echo html_escape(implode(' ', $demand_points)); ?>"></polyline></svg>
								</span>
							<?php endif; ?>
						</div>
						<div class="dashboard-forecast-demand-summary">
							Demand bersih: <?php echo html_escape($product['data_status'] === 'Data perlu diperiksa' || $product['net_demand'] === null ? '—' : number_format((float) $product['net_demand'], 3, ',', '.')); ?>
							<span>·</span>
							D per bulan: <?php echo html_escape($product['average_monthly_demand'] === null ? '—' : number_format((float) $product['average_monthly_demand'], 3, ',', '.')); ?>
						</div>
					</div>
					<div class="dashboard-forecast-projection">
						<div class="dashboard-forecast-block-title">Proyeksi MA <small><?php echo html_escape($forecast_dashboard['method']); ?></small></div>
						<div class="forecast-horizons">
							<?php foreach (array(1, 3, 6) as $horizon): ?>
								<span class="forecast-horizon" title="Proyeksi <?php echo html_escape($horizon); ?> bulan">
									<small><?php echo html_escape($horizon); ?> bln</small>
									<strong><?php echo html_escape($product['forecast'][$horizon] === null ? '—' : number_format((float) $product['forecast'][$horizon], 2, ',', '.')); ?></strong>
								</span>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="dashboard-forecast-stock">
						<div class="dashboard-forecast-block-title">Coverage &amp; stok layak</div>
						<strong class="dashboard-forecast-coverage-value"><?php echo html_escape($product['coverage_months'] === null ? '—' : number_format((float) $product['coverage_months'], 1, ',', '.') . ' bln'); ?></strong>
						<small><?php echo html_escape(number_format((float) $product['usable_stock'], 3, ',', '.')); ?> <?php echo html_escape($base_unit_label); ?> · <?php echo html_escape($product['coverage_status']); ?></small>
						<?php if ($product['reserved_excess'] > 0 || $product['red_risk_quantity'] > 0 || $product['yellow_risk_quantity'] > 0): ?>
							<div class="dashboard-forecast-stock-warnings">
								<?php if ($product['reserved_excess'] > 0): ?><span class="forecast-warning-critical">Reservasi melebihi sisa: <?php echo html_escape(number_format((float) $product['reserved_excess'], 3, ',', '.')); ?></span><?php endif; ?>
								<?php if ($product['red_risk_quantity'] > 0): ?><span class="forecast-warning-critical">Merah &lt;3 bln: <?php echo html_escape(number_format((float) $product['red_risk_quantity'], 3, ',', '.')); ?></span><?php endif; ?>
								<?php if ($product['yellow_risk_quantity'] > 0): ?><span class="forecast-warning-caution">Kuning 3–6 bln: <?php echo html_escape(number_format((float) $product['yellow_risk_quantity'], 3, ',', '.')); ?></span><?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

</div>
	<script>
		(function () {
			var countUpElements = document.querySelectorAll('#apotek-dashboard-stitch [data-count-up]');
			var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			if (!countUpElements.length || reduceMotion) {
				return;
			}

			var formatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
			countUpElements.forEach(function (element) {
				var target = Number(element.getAttribute('data-count-target'));
				if (!Number.isFinite(target) || target <= 0) {
					return;
				}

				var startTime = null;
				var duration = 850;
				element.textContent = formatter.format(0);

				function animateCount(timestamp) {
					if (startTime === null) {
						startTime = timestamp;
					}
					var progress = Math.min((timestamp - startTime) / duration, 1);
					var easedProgress = 1 - Math.pow(1 - progress, 3);
					element.textContent = formatter.format(Math.round(target * easedProgress));
					if (progress < 1) {
						window.requestAnimationFrame(animateCount);
					}
				}

				window.requestAnimationFrame(animateCount);
			});
		})();
	</script>

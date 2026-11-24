<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Procurement extends Admin_Controller
{
	private $procurementService;

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('inventory');

		$environment = defined('ENVIRONMENT') ? ENVIRONMENT : '';
		if ($environment !== 'demo') {
			show_404();
		}
		$configPath = APPPATH . 'config' . DIRECTORY_SEPARATOR . 'secrets' .
			DIRECTORY_SEPARATOR . 'p16_procurement.php';
		$localConfig = is_file($configPath) ? require $configPath : array();
		if (!is_array($localConfig) ||
			!isset($localConfig['procurement_recommendations_enabled']) ||
			$localConfig['procurement_recommendations_enabled'] !== true) {
			show_404();
		}

		$markerResult = $this->db->query(
			'SELECT environment FROM environment_marker WHERE id = 1 LIMIT 1'
		);
		if (!$markerResult) {
			log_message('error', 'Procurement page disabled because the environment marker could not be read.');
			show_404();
		}
		$marker = $markerResult->row_array();
		if (!$marker) {
			log_message('error', 'Procurement page disabled because the environment marker is missing.');
			show_404();
		}

		require_once APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'Procurement_service.php';
		if (!Procurement_service::isFeatureEnabled(
			$environment,
			$localConfig,
			isset($marker['environment']) ? $marker['environment'] : null
		)) {
			show_404();
		}

		$this->load->library('Forecast_service');
		$this->procurementService = new Procurement_service(
			$this->db,
			$this->forecast_service,
			$localConfig
		);
	}

	public function index()
	{
		$data = $this->procurementService->getRecommendations();
		$data['show_scenario_debug'] = defined('ENVIRONMENT') &&
			ENVIRONMENT === 'demo' &&
			$this->input->get('debug', true) === '1';
		$data['title'] = 'Rekomendasi Pengadaan';
		$data['content'] = $this->load->view('admin/procurement/index', $data, true);
		$this->load->view('layouts/admin', $data);
	}
}

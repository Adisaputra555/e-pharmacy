<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Exports extends Admin_Controller
{
	public function excel()
	{
		$this->output->set_header('X-CSRF-Hash: ' . $this->security->get_csrf_hash());
		if ($this->input->method(true) !== 'POST') {
			show_error('Metode permintaan ekspor tidak valid.', 405, 'Ekspor Gagal');
			return;
		}

		$raw_payload = $this->input->post('payload', false);
		if (!is_string($raw_payload) || strlen($raw_payload) > 5000000) {
			show_error('Data ekspor kosong atau terlalu besar.', 400, 'Ekspor Gagal');
			return;
		}

		$payload = json_decode($raw_payload, true);
		if (!is_array($payload) || !isset($payload['title'], $payload['headers'], $payload['rows'])
			|| !is_string($payload['title']) || !is_array($payload['headers']) || !is_array($payload['rows'])) {
			show_error('Format data ekspor tidak valid.', 400, 'Ekspor Gagal');
			return;
		}

		$headers = array_values($payload['headers']);
		$rows = array_values($payload['rows']);
		if (!$headers || count($headers) > 50 || count($rows) > 10000) {
			show_error('Jumlah kolom atau baris melewati batas ekspor.', 400, 'Ekspor Gagal');
			return;
		}

		foreach ($headers as $header) {
			if (!is_string($header) || trim($header) === '' || strlen($header) > 200) {
				show_error('Nama kolom pada data ekspor tidak valid.', 400, 'Ekspor Gagal');
				return;
			}
		}
		foreach ($rows as $row) {
			if (!is_array($row) || count($row) !== count($headers)) {
				show_error('Struktur baris pada data ekspor tidak sesuai.', 400, 'Ekspor Gagal');
				return;
			}
			foreach ($row as $value) {
				if ((!is_scalar($value) && $value !== null) || strlen((string) $value) > 32767) {
					show_error('Nilai pada data ekspor tidak valid atau terlalu panjang.', 400, 'Ekspor Gagal');
					return;
				}
			}
		}

		$title = trim(strip_tags($payload['title']));
		if ($title === '') {
			$title = 'Data';
		}
		try {
			$this->load->library('xlsx_export');
			$workbook = $this->xlsx_export->build($title, $headers, $rows);
		} catch (RuntimeException $error) {
			log_message('error', 'Excel export failed: ' . $error->getMessage());
			show_error('File Excel tidak dapat dibuat saat ini. Silakan coba kembali.', 500, 'Ekspor Gagal');
			return;
		}

		$filename = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
		$filename = substr($filename !== '' ? $filename : 'data', 0, 80);
		$filename = trim($filename, '-') . '-' . date('Y-m-d') . '.xlsx';

		$this->output
			->set_header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
			->set_header('Content-Disposition: attachment; filename="' . $filename . '"')
			->set_header('Cache-Control: no-store, no-cache, must-revalidate')
			->set_header('X-Content-Type-Options: nosniff')
			->set_output($workbook);
	}
}

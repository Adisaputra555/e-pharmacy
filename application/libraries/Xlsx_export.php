<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Xlsx_export
{
	public function build($title, $headers, $rows)
	{
		if (!class_exists('ZipArchive')) {
			throw new RuntimeException('Ekstensi ZIP PHP tidak tersedia.');
		}

		$headers = array_values($headers);
		$rows = array_values($rows);
		$column_count = count($headers);
		if ($column_count < 1) {
			throw new RuntimeException('Workbook Excel harus memiliki setidaknya satu kolom.');
		}

		$last_column = $this->column_name($column_count);
		$last_row = count($rows) + 4;
		$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<dimension ref="A1:' . $last_column . $last_row . '"/>'
			. '<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/>'
			. '<selection pane="bottomLeft" activeCell="A5" sqref="A5"/></sheetView></sheetViews>'
			. '<sheetFormatPr defaultRowHeight="19"/><cols>';

		for ($column = 0; $column < $column_count; $column++) {
			$width = $this->column_width($headers[$column], $rows, $column);
			$excel_column = $column + 1;
			$sheet .= '<col min="' . $excel_column . '" max="' . $excel_column . '" width="' . $width . '" customWidth="1"/>';
		}

		$sheet .= '</cols><sheetData>';
		$sheet .= '<row r="1" ht="31" customHeight="1">' . $this->cell('A1', $title, 1) . '</row>';
		$sheet .= '<row r="2" ht="20" customHeight="1">' . $this->cell('A2', 'Diekspor pada ' . date('d-m-Y H:i'), 2) . '</row>';
		$sheet .= '<row r="3"/><row r="4" ht="25" customHeight="1">';
		foreach ($headers as $index => $header) {
			$sheet .= $this->cell($this->column_name($index + 1) . '4', $header, 3);
		}
		$sheet .= '</row>';

		foreach ($rows as $row_index => $row) {
			$excel_row = $row_index + 5;
			$style = ($row_index % 2) === 0 ? 4 : 5;
			$sheet .= '<row r="' . $excel_row . '" ht="21" customHeight="1">';
			foreach ($headers as $column_index => $unused_header) {
				$value = isset($row[$column_index]) ? $row[$column_index] : '';
				$sheet .= $this->cell($this->column_name($column_index + 1) . $excel_row, $value, $style);
			}
			$sheet .= '</row>';
		}

		$sheet .= '</sheetData><autoFilter ref="A4:' . $last_column . $last_row . '"/>';
		if ($column_count > 1) {
			$sheet .= '<mergeCells count="2"><mergeCell ref="A1:' . $last_column . '1"/><mergeCell ref="A2:' . $last_column . '2"/></mergeCells>';
		}
		$sheet .= '<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/></worksheet>';

		$files = array(
			'[Content_Types].xml' => $this->content_types_xml(),
			'_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
				. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
				. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
				. '</Relationships>',
			'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
				. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
				. '<sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets></workbook>',
			'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
				. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
				. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
				. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
				. '</Relationships>',
			'xl/styles.xml' => $this->styles_xml(),
			'xl/worksheets/sheet1.xml' => $sheet
		);

		$temp_path = tempnam(sys_get_temp_dir(), 'apotek_xlsx_');
		if ($temp_path === false) {
			throw new RuntimeException('File sementara Excel tidak dapat dibuat.');
		}

		$zip = new ZipArchive();
		$zip_open = false;
		try {
			if ($zip->open($temp_path, ZipArchive::OVERWRITE) !== true) {
				throw new RuntimeException('Workbook Excel tidak dapat dikemas.');
			}
			$zip_open = true;
			foreach ($files as $path => $content) {
				if (!$zip->addFromString($path, $content)) {
					throw new RuntimeException('Bagian workbook Excel gagal ditulis.');
				}
			}
			if (!$zip->close()) {
				$zip_open = false;
				throw new RuntimeException('Workbook Excel gagal diselesaikan.');
			}
			$zip_open = false;

			$workbook = file_get_contents($temp_path);
			if ($workbook === false) {
				throw new RuntimeException('Workbook Excel tidak dapat dibaca kembali.');
			}
			return $workbook;
		} finally {
			if ($zip_open) {
				$zip->close();
			}
			if (is_file($temp_path) && !unlink($temp_path) && function_exists('log_message')) {
				log_message('error', 'Temporary Excel workbook could not be removed: ' . $temp_path);
			}
		}
	}

	private function content_types_xml()
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>';
	}

	private function styles_xml()
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="4">'
			. '<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
			. '<font><b/><sz val="18"/><color rgb="FF0B3324"/><name val="Aptos Display"/><family val="2"/></font>'
			. '<font><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/><family val="2"/></font>'
			. '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
			. '</fonts>'
			. '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FF0B3324"/><bgColor indexed="64"/></patternFill></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFF2F7F4"/><bgColor indexed="64"/></patternFill></fill></fills>'
			. '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
			. '<border><left/><right/><top/><bottom style="thin"><color rgb="FFDCE5DF"/></bottom><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="6">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'
			. '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'
			. '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
			. '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
			. '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
			. '<dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
			. '</styleSheet>';
	}

	private function cell($reference, $value, $style)
	{
		return '<c r="' . $reference . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
			. $this->xml_escape($value) . '</t></is></c>';
	}

	private function xml_escape($value)
	{
		$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value);
		return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}

	private function column_width($header, $rows, $column_index)
	{
		$width = strlen((string) $header);
		foreach ($rows as $row) {
			if (isset($row[$column_index])) {
				$width = max($width, strlen((string) $row[$column_index]));
			}
		}
		return max(12, min(38, $width + 2));
	}

	private function column_name($number)
	{
		$name = '';
		while ($number > 0) {
			$remainder = ($number - 1) % 26;
			$name = chr(65 + $remainder) . $name;
			$number = (int) (($number - $remainder - 1) / 26);
		}
		return $name;
	}
}

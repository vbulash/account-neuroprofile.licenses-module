<?php

namespace Modules\Licenses\Services;

use App\Models\Contract;
use Modules\Licenses\Entities\License;
use App\Models\LicenseStatus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as WriterXlsx;

/**
 * Сервис работы с лицензиями
 * 
 * @method static mixed export(int $contract)
 * @method static bool repair(int $license)
 * @method static iterable info(int $contract)
 */
class LicenseService {
	public function export(int $contract) {
		$_contract = Contract::findOrFail($contract);

		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();

		$sheet->setCellValue('A1', 'Персональный ключ');
		$sheet->setCellValue('B1', 'Статус лицензии');
		for ($row = 1; $row <= 1; $row++)
			for ($column = 1; $column <= 2; $column++) {
				$letter = Coordinate::stringFromColumnIndex($column);
				$style = $sheet->getStyle($letter . $row);
				$style->getFont()->setBold(true);
				// $style->getFill()->setFillType(Fill::);
				$style->getFill()->getStartColor()->setRGB('B0B3B2');
			}
		$sheet->freezePane('A2');

		$row = 1;
		foreach ($_contract->licenses as $license) {
			$sheet->setCellValue('A' . (++$row), $license->pkey);
			$sheet->setCellValue('B' . $row, LicenseStatus::getName($license->status));
		}

		$tmpsheet = 'tmp/' . Str::uuid() . '.xlsx';
		$writer = new WriterXlsx($spreadsheet);
		try {
			Storage::makeDirectory('tmp');
			$writer->save(Storage::path($tmpsheet));
			// Экспорт лицензий - Название клиента - Номер договора
			$tempFile = sprintf("Экспорт лицензий - %s - %s", $_contract->client->getTitle(), $_contract->number);
			$tempFile = str_replace([
				' ',
				'.',
				',',
				'\"',
				'\'',
				'\\',
				'/',
				'«',
				'»'
			], '_', $tempFile);
			return response()
				->download(Storage::path($tmpsheet), $tempFile . '.xlsx')
				->deleteFileAfterSend();
		} catch (\Exception $e) {
		}
		return true;
	}

	public function repair(int $license) {
		$_license = License::findOrFail($license);
		if ($_license->status == LicenseStatus::USING->value || $_license->status == LicenseStatus::BROKEN->value) {
			$_license->update(['status' => LicenseStatus::FREE->value]);
			if ($_license->history()->count() > 0)
				$_license->history()->delete();
		}
		return true;
	}

	public function info(int $contract): iterable {
		$_contract = Contract::findOrFail($contract);
		$statuses = $_contract->licenses->groupBy('status')->toArray();
		foreach (LicenseStatus::cases() as $status)
			$result[] = [
				'name' => LicenseStatus::getName($status->value),
				'count' => array_key_exists($status->value, $statuses) ? count($statuses[$status->value]) : 0
			];

		return $result;
	}
}
<?php

namespace Modules\Licenses\Http\Controllers;

use App\Models\Contract;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Modules\Licenses\Entities\License;
use App\Models\LicenseStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as WriterXlsx;
use Yajra\DataTables\DataTables;

class LicensesController extends Controller {
	use AuthorizesRequests, ValidatesRequests;

	public function getData(Request $request, int $contract): JsonResponse {
		$_contract = Contract::findOrFail($contract);
		$status = intval($request->status);
		$query = $_contract->licenses()
			->get()
			->filter(function ($license) use ($status) {
				if ($status == 0)
					return true;
				if ($license->status == $status)
					return true;
				return false;
			});

		return DataTables::of($query)
			->editColumn('status', fn($license) => LicenseStatus::getName($license->status))
			->addColumn('action', function ($license) use ($contract) {
				$items = [];
				if ($license->status == LicenseStatus::USING->value || $license->status == LicenseStatus::BROKEN->value) {
					$repairLink = sprintf("clickRepair(%d, %s)", $license->getKey(), $license->history()->count() > 0 ? 'true' : 'false');
					$items[] = [
						'type' => 'item',
						'click' => $repairLink, 'icon' => 'fas fa-tools', 'title' => 'Исправить'
					];
				}
				return createDropdown('Действия', $items);
			})
			->make(true);
	}

	public function index(int $contract) {
		$_contract = Contract::findOrFail($contract);
		$heading = sprintf("Лицензии договора № %s клиента &laquo;%s&raquo;", $_contract->number, $_contract->client->getTitle());

		return view('licenses::index', [
			'contract' => $contract,
			'heading' => $heading,
		]);
	}

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

	public function repair(Request $request, int $contract) {
		$license = $request->license;
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
<?php

namespace Modules\Licenses\Http\Controllers;

use App\Models\Contract;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Modules\Licenses\Entities\License;
use Modules\Licenses\Facades\License as LicenseFacade;
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
		return LicenseFacade::export($contract);
	}

	public function repair(Request $request, int $contract) {
		return LicenseFacade::repair($request->license);
	}

	public function info(int $contract): iterable {
		return LicenseFacade::info($contract);
	}
}
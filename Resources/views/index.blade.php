@extends('layouts.datatables')

@section('header')
	<div class="d-flex align-items-start">
		<div class="d-flex flex-column justify-content-start align-items-start me-5">
			<h3 class="block-title">Список лицензий договора</h3>
			<h3 class="block-title mb-2"><small>Здесь вы можете отфильтровать лицензии по статусам, исправить проблемы с лицензиями
					из-за сбоев и экспортировать лицензии в формате Excel</small></h3>
			<form action="{{ route('contracts.licenses.export', ['contract' => $contract]) }}" method="post" id="export">
				@csrf
				<button type="submit" class="btn btn-primary">Экспорт всех лицензий</button>
			</form>
		</div>
		<div class="d-flex flex-column justify-content-start align-items-start">
			<label class="form-label" for="filter-status">Показать лицензии со статусом:</label>
			<select name="filter-status" id="filter-status" style="padding: 6px 12px; border-radius: 6px;">
				<option value="0" selected>Все лицензии</option>
				@foreach (App\Models\LicenseStatus::cases() as $status)
					<option value="{{ $status->value }}">{{ App\Models\LicenseStatus::getName($status->value) }}</option>
				@endforeach
			</select>
		</div>
	</div>
@endsection

@section('pretable')
	<div class="block block-rounded">
		<div class="block-header block-header-default">
			<h3 class="block-title">Статистика лицензий договора</h3>
			<div class="block-options">
				<button type="button" class="btn-block-option" data-toggle="block-option" data-action="content_toggle"><i
						class="si si-arrow-up"></i></button>
			</div>
		</div>
		<div class="block-content block-content-full">
			<table id="info"></table>
		</div>
	</div>
@endsection

@section('thead')
	<tr>
		<th style="width: 30px">#</th>
		<th>Персональный ключ</th>
		<th>Статус лицензии</th>
		<th>&nbsp;</th>
	</tr>
@endsection

@push('js_end')
	<script>
		function statistics() {
			$.ajax({
				method: 'POST',
				url: "{{ route('contracts.licenses.info', ['contract' => $contract]) }}",
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				success: (data) => {
					let infoRows = '';
					for (let index in data)
						infoRows = infoRows + `
                    <tr>
                        <td style="padding-right: 16px;"><strong>${data[index].name}</strong></td>
                        <td>${data[index].count}</td>
                    </tr>
			    `;
					$('#info').html(infoRows);
				}
			});
		}

		function repair(license) {
			$.ajax({
				method: 'POST',
				contentType: 'application/x-www-form-urlencoded',
				url: "{{ route('contracts.licenses.repair', ['contract' => $contract]) }}",
				data: {
					license: license,
				},
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				beforeSend: () => {
					Dashmix.helpers('jq-notify', {
						from: 'bottom',
						type: 'info',
						icon: 'fa fa-info-circle me-2',
						message: 'Исправление статуса лицензии...'
					});
				},
				success: () => {
					statistics();
					window.datatable.ajax.reload();
					Dashmix.helpers('jq-notify');
				}
			});
		}

		document.getElementById('confirm-yes').addEventListener('click', (event) => {
			repair(event.target.dataset.license);
		}, false);

		function clickRepair(license, hasHistory) {
			if (hasHistory) {
				document.getElementById('confirm-title').innerText = "Подтвердите удаление при исправлении статуса лицензии";
				document.getElementById('confirm-body').innerHTML =
					"При исправлении статуса лицензии необходимо удалить запись истории прохождения тестирования.<br/>Подтверждаете ?";
				document.getElementById('confirm-yes').dataset.license = license;
				let confirmDialog = new bootstrap.Modal(document.getElementById('modal-confirm'));
				confirmDialog.show();
			} else repair(license);
		}

		$(function() {
			window.datatable = $('#datatable').DataTable({
				language: {
					"url": "{{ asset('lang/ru/datatables.json', true) }}",
					searchPlaceholder: 'Персональный ключ...',
				},
				processing: true,
				serverSide: true,
				ajax: {
					url: '{!! route('contracts.licenses.index.data', ['contract' => $contract]) !!}',
					data: (data) => {
						data.status = $('#filter-status').val();
					}
				},
				pageLength: 50,
				responsive: true,
				createdRow: function(row, data, dataIndex) {
					switch (data.status) {
						case "{{ App\Models\LicenseStatus::getName(App\Models\LicenseStatus::FREE->value) }}":
							break;
						case "{{ App\Models\LicenseStatus::getName(App\Models\LicenseStatus::USING->value) }}":
						case "{{ App\Models\LicenseStatus::getName(App\Models\LicenseStatus::BROKEN->value) }}":
							row.style.color = 'red';
							break;
						case "{{ App\Models\LicenseStatus::getName(App\Models\LicenseStatus::USED->value) }}":
							row.classList.add('fw-bold');
							break;
					}
				},
				columns: [{
						data: 'id',
						name: 'id',
						responsivePriority: 1
					},
					{
						data: 'pkey',
						name: 'pkey',
						responsivePriority: 1,
						sortable: false
					},
					{
						data: 'status',
						name: 'status',
						responsivePriority: 2,
						sortable: false
					},
					{
						data: 'action',
						name: 'action',
						sortable: false,
						responsivePriority: 1,
						className: 'no-wrap dt-actions'
					}
				]
			});

			window.datatable.on('draw', function() {
				$('.dropdown-toggle.actions').on('shown.bs.dropdown', (event) => {
					const menu = event.target.parentElement.querySelector('.dropdown-menu');
					let parent = menu.closest('.dataTables_wrapper');
					const parentRect = parent.getBoundingClientRect();
					parentRect.top = Math.abs(parentRect.top);
					const menuRect = menu.getBoundingClientRect();
					const buttonRect = event.target.getBoundingClientRect();
					const menuTop = Math.abs(buttonRect.top) + buttonRect.height + 4;
					if (menuTop + menuRect.height > parentRect.top + parentRect.height) {
						const clientHeight = parentRect.height + menuTop + menuRect.height - (
							parentRect.top + parentRect.height);
						parent.style.height = clientHeight.toString() + 'px';
					}
				});
			});

			$('#filter-status').change(() => {
				window.datatable.draw();
			});

			$('#export').submit(() => {
				Dashmix.helpers('jq-notify', {
					from: 'bottom',
					type: 'info',
					icon: 'fa fa-info-circle me-2',
					message: 'Создание файла экспорта...'
				});
			});

			statistics();
		});
	</script>
@endpush

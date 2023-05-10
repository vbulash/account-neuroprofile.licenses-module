<?php

namespace Modules\Licenses\Services;

class LicenseService {
	public function showInfo() {
		return 'info:' . rand(0, 1000);
	}
}
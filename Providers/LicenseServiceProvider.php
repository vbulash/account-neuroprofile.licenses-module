<?php

namespace Modules\Licenses\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;
use Modules\Licenses\Services\LicenseService;

class LicenseServiceProvider extends ServiceProvider {
	public function boot() {
		$this->app->bind('license', LicenseService::class);
	}
}
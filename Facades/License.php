<?php

namespace Modules\Licenses\Facades;

use Illuminate\Support\Facades\Facade;

class License extends Facade {
	protected static function getFacadeAccessor() {
		return 'license';
	}
}
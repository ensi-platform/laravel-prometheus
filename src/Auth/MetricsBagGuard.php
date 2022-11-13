<?php

namespace Madridianfox\LaravelPrometheus\Auth;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;

class MetricsBagGuard implements Guard
{
    use GuardHelpers;

    public function user()
    {
        // TODO: Implement user() method.
    }

    public function validate(array $credentials = [])
    {
        // TODO: Implement validate() method.
    }
}
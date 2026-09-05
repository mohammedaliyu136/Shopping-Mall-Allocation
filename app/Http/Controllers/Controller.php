<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    // FR-011 - controllers authorise every action they expose.
    use AuthorizesRequests, ValidatesRequests;
}

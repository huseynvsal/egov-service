<?php

namespace App\Http\Controllers;

use App\Contracts\LogRepositoryInterface;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __invoke(LogRepositoryInterface $logRepo): View
    {
        return view('report', ['reportData' => $logRepo->yearlyReport()]);
    }
}

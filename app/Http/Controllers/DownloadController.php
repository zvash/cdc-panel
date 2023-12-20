<?php

namespace App\Http\Controllers;

use App\Models\AppraisalJobFile;
use App\Models\User;
use Illuminate\Http\Request;

class DownloadController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function downloadAppraisalJobFile(AppraisalJobFile $appraisalJobFile)
    {
        /** @var  User $user */
        $user = auth()->user();
        if (
            $user->hasManagementAccess()
            || $user->id === $appraisalJobFile->user_id
            || $user->id === $appraisalJobFile->appraisalJob->appraiser_id
        ) {
            return response()->download(
                storage_path('app/' . $appraisalJobFile->file),
                explode('/', $appraisalJobFile->file)[1]
            );
        } else {
            abort(403);
        }
    }
}

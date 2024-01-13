<?php

namespace App\Http\Controllers;

use App\Models\AppraisalJobFile;
use App\Models\AppraisalJobRejection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            || $user->id == $appraisalJobFile->user_id
            || $user->id == $appraisalJobFile->appraisalJob->appraiser_id
            || $user->id == $appraisalJobFile->appraisalJob->inferReviewer()
        ) {
            return Storage::disk('media')->download($appraisalJobFile->file);
        } else {
            abort(403);
        }
    }

    public function downloadRejectedAppraisalJobFile(AppraisalJobRejection $appraisalJobRejection)
    {
        /** @var  User $user */
        $user = auth()->user();
        if (
            $user->hasManagementAccess()
            || $user->id == $appraisalJobRejection->user_id
            || $user->id == $appraisalJobRejection->appraisalJob->appraiser_id
            || $user->id == $appraisalJobRejection->appraisalJob->inferReviewer()
        ) {
            return Storage::disk('media')->download($appraisalJobRejection->file);
        } else {
            abort(403);
        }
    }
}

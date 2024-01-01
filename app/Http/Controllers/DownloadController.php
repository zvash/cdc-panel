<?php

namespace App\Http\Controllers;

use App\Models\AppraisalJobFile;
use App\Models\AppraisalJobRejection;
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
            || $user->id == $appraisalJobFile->user_id
            || $user->id == $appraisalJobFile->appraisalJob->appraiser_id
            || $user->id == $appraisalJobFile->appraisalJob->reviewer_id
            || (
                $appraisalJobFile->appraisalJob->appraiser_id
                && User::query()
                    ->where('id', $appraisalJobFile->appraisalJob->appraiser_id)
                    ->whereJsonContains('reviewers', "{$user->id}")
                    ->exists()
            )
        ) {
            return response()->download(
                storage_path('app/' . $appraisalJobFile->file),
                explode('/', $appraisalJobFile->file)[1]
            );
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
            || $user->id == $appraisalJobRejection->appraisalJob->reviewer_id
            || (
                $appraisalJobRejection->appraisalJob->appraiser_id
                && User::query()
                    ->where('id', $appraisalJobRejection->appraisalJob->appraiser_id)
                    ->whereJsonContains('reviewers', "{$user->id}")
                    ->exists()
            )
        ) {
            return response()->download(
                storage_path('app/' . $appraisalJobRejection->file),
                explode('/', $appraisalJobRejection->file)[1]
            );
        } else {
            abort(403);
        }
    }
}

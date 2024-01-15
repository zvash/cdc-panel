<?php

namespace App\Http\Controllers;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PDFController extends Controller
{
    public function appraiserInvoice(int $appraiserId, int $year, int $month)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }
        $user = User::query()->find($user->id);
        if (!$user->hasManagementAccess() && $user->id != $appraiserId) {
            abort(403);
        }
        $rawQueryAsString = "
            SELECT
                appraisal_jobs.id,
                appraisal_jobs.reference_number,
                appraisal_jobs.appraisal_type_id,
                appraisal_jobs.client_id,
                appraisal_jobs.office_id,
                appraisal_jobs.fee_quoted,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted AS cdc_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 AS cdc_tax,
                appraisal_jobs.fee_quoted * IFNULL(appraisal_jobs.commission, users.commission) / 100 AS appraiser_fee,
                (appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted) * IFNULL(appraisal_jobs.commission, users.commission) / 100 AS appraiser_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 * IFNULL(appraisal_jobs.commission, users.commission) / 100 AS appraiser_tax,
                appraisal_jobs.completed_at,
                appraisal_jobs.appraiser_id AS appraiser_id,
                'Appraiser' AS user_type,
                CONCAT('INV-', YEAR(completed_at), '-', MONTH(completed_at)) AS invoice_number,
                users.commission,
                province_taxes.total as province_tax,
                YEAR(appraisal_jobs.completed_at) AS completed_at_year,
                MONTH(appraisal_jobs.completed_at) AS completed_at_month
            FROM
                appraisal_jobs
            INNER JOIN
                users
            ON
                users.id = appraiser_id
            INNER JOIN
                provinces
            ON
                provinces.name = appraisal_jobs.province
            INNER JOIN
                province_taxes
            ON
                province_taxes.province_id = provinces.id
            WHERE
                completed_at IS NOT NULL
            AND
                fee_quoted IS NOT NULL
            AND
                province IS NOT NULL
            AND
                appraiser_id IS NOT NULL
            AND
                appraiser_id = $appraiserId
            AND
                YEAR(completed_at) = $year
            AND
                MONTH(completed_at) = $month
            UNION
            SELECT
                appraisal_jobs.id,
                appraisal_jobs.reference_number,
                appraisal_jobs.appraisal_type_id,
                appraisal_jobs.client_id,
                appraisal_jobs.office_id,
                appraisal_jobs.fee_quoted,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted AS cdc_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 AS cdc_tax,
                appraisal_jobs.fee_quoted * IFNULL(appraisal_jobs.reviewer_commission, users.reviewer_commission) / 100 AS appraiser_fee,
                (appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted) * IFNULL(appraisal_jobs.reviewer_commission, users.reviewer_commission) / 100 AS appraiser_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 * IFNULL(appraisal_jobs.reviewer_commission, users.reviewer_commission) / 100 AS appraiser_tax,
                appraisal_jobs.completed_at,
                appraisal_jobs.reviewer_id AS appraiser_id,
                'Reviewer' AS user_type,
                CONCAT('INV-', YEAR(completed_at), '-', MONTH(completed_at)) AS invoice_number,
                users.reviewer_commission as commission,
                province_taxes.total as province_tax,
                YEAR(appraisal_jobs.completed_at) AS completed_at_year,
                MONTH(appraisal_jobs.completed_at) AS completed_at_month
            FROM
                appraisal_jobs
            INNER JOIN
                users
            ON
                users.id = reviewer_id
            INNER JOIN
                provinces
            ON
                provinces.name = appraisal_jobs.province
            INNER JOIN
                province_taxes
            ON
                province_taxes.province_id = provinces.id
            WHERE
                completed_at IS NOT NULL
            AND
                fee_quoted IS NOT NULL
            AND
                province IS NOT NULL
            AND
                reviewer_id IS NOT NULL
            AND
                reviewer_id = $appraiserId
            AND
                YEAR(completed_at) = $year
            AND
                MONTH(completed_at) = $month
        ";
        $appraiser = User::query()->find($appraiserId);
        $invoices = collect(DB::select($rawQueryAsString));
        $info = collect([
            'invoice_number' => "INV-$year-$month",
            'appraiser' => $appraiser,
            'invoices' => $invoices,
        ]);
        $pdf = Pdf::loadView('printable.appraiser-invoice', compact('info'))
            ->setPaper('a4', 'landscape');

        $name = 'invoice-' . Str::kebab($appraiser->name) . '-inv-' . $year . '-' . $month . '.pdf';
        return $pdf->download($name);
    }
}

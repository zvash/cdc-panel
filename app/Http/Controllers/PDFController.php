<?php

namespace App\Http\Controllers;

use App\Models\Client;
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
                appraisal_jobs.client_id,
                appraisal_jobs.office_id,
                appraisal_jobs.appraiser_id,
                appraisal_jobs.reviewer_id,
                appraisal_jobs.property_address,
                appraisal_jobs.appraisal_type_id,
                appraisal_jobs.created_at,
                appraisal_jobs.reference_number,
                appraisal_jobs.fee_quoted,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted AS cdc_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 AS cdc_tax,
                appraisal_jobs.fee_quoted * IFNULL(appraisal_jobs.commission, users.commission) / 100 AS appraiser_fee,
                (appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted) * IFNULL(appraisal_jobs.commission, users.commission) / 100 AS appraiser_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 * IFNULL(appraisal_jobs.commission, users.commission) / 100 AS appraiser_tax,
                appraisal_jobs.payment_terms,
                appraisal_jobs.completed_at,
                appraisal_jobs.commission as job_commission,
                appraisal_jobs.reviewer_commission as job_reviewer_commission,
                appraisal_jobs.fee_quoted * IFNULL(appraisal_jobs.reviewer_commission, users.reviewer_commission) / 100 AS reviewer_fee,
                (appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted) * IFNULL(appraisal_jobs.reviewer_commission, users.reviewer_commission) / 100 AS reviewer_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 * IFNULL(appraisal_jobs.reviewer_commission, users.reviewer_commission) / 100 AS reviewer_tax,
                YEAR(appraisal_jobs.completed_at) AS completed_at_year,
                MONTH(appraisal_jobs.completed_at) AS completed_at_month,
                DATE(appraisal_jobs.completed_at) AS completed_at_date,
                CONCAT('INV-', YEAR(completed_at), '-', MONTH(completed_at)) AS invoice_number,
                users.commission as user_commission,
                reviewers.reviewer_commission as user_reviewer_commission,
                province_taxes.total as province_tax,
                IFNULL(appraisal_jobs.commission, users.commission) as commission,
                IFNULL(appraisal_jobs.reviewer_commission, reviewers.reviewer_commission) as reviewer_commission,
                IF(appraisal_jobs.appraiser_id = $appraiserId, 'Appraiser', 'Reviewer') as user_type,
                users.name as appraiser_name,
                reviewers.name as reviewer_name,
                clients.name as client_name,
                appraisal_types.name AS appraisal_type_name
            FROM
                appraisal_jobs
            INNER JOIN
                users
            ON
                users.id = appraiser_id
            INNER JOIN
                clients
            ON
                clients.id = appraisal_jobs.client_id
            INNER JOIN
                appraisal_types
            ON
                appraisal_types.id = appraisal_jobs.appraisal_type_id
            INNER JOIN users as reviewers
                    ON reviewers.id = reviewer_id
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
                (reviewer_id = $appraiserId OR appraiser_id = $appraiserId)
            AND
                YEAR(completed_at) = $year
            AND
                MONTH(completed_at) = $month";
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
//        return view('printable.appraiser-invoice', compact('info'));
    }

    public function clientInvoice(int $clientId, int $year, int $month)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }
        $user = User::query()->find($user->id);
        if (!$user->hasManagementAccess()) {
            abort(403);
        }

        $rawQueryAsString = "
            SELECT
                appraisal_jobs.id,
                appraisal_jobs.client_id,
                appraisal_jobs.office_id,
                appraisal_jobs.appraiser_id,
                appraisal_jobs.reviewer_id,
                appraisal_jobs.property_address,
                appraisal_jobs.appraisal_type_id,
                appraisal_jobs.created_at,
                appraisal_jobs.reference_number,
                appraisal_jobs.fee_quoted,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted AS cdc_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 AS cdc_tax,
                appraisal_jobs.fee_quoted * IFNULL(appraisal_jobs.commission, users.commission) / 100 AS appraiser_fee,
                (appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted) * IFNULL(appraisal_jobs.commission, users.commission) / 100 AS appraiser_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 * IFNULL(appraisal_jobs.commission, users.commission) / 100 AS appraiser_tax,
                appraisal_jobs.payment_terms,
                appraisal_jobs.completed_at,
                appraisal_jobs.commission as job_commission,
                appraisal_jobs.reviewer_commission as job_reviewer_commission,
                appraisal_jobs.fee_quoted * IFNULL(appraisal_jobs.reviewer_commission, users.reviewer_commission) / 100 AS reviewer_fee,
                (appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted) * IFNULL(appraisal_jobs.reviewer_commission, users.reviewer_commission) / 100 AS reviewer_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 * IFNULL(appraisal_jobs.reviewer_commission, users.reviewer_commission) / 100 AS reviewer_tax,
                YEAR(appraisal_jobs.completed_at) AS completed_at_year,
                MONTH(appraisal_jobs.completed_at) AS completed_at_month,
                DATE(appraisal_jobs.completed_at) AS completed_at_date,
                CONCAT('INV-', YEAR(completed_at), '-', MONTH(completed_at)) AS invoice_number,
                users.commission as user_commission,
                reviewers.reviewer_commission as user_reviewer_commission,
                province_taxes.total as province_tax,
                IFNULL(appraisal_jobs.commission, users.commission) as commission,
                IFNULL(appraisal_jobs.reviewer_commission, reviewers.reviewer_commission) as reviewer_commission,
                users.name as appraiser_name,
                reviewers.name as reviewer_name,
                clients.name as client_name,
                appraisal_types.name AS appraisal_type_name
            FROM
                appraisal_jobs
            INNER JOIN
                users
            ON
                users.id = appraiser_id
            INNER JOIN
                clients
            ON
                clients.id = appraisal_jobs.client_id
            INNER JOIN
                appraisal_types
            ON
                appraisal_types.id = appraisal_jobs.appraisal_type_id
            INNER JOIN users as reviewers
                    ON reviewers.id = reviewer_id
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
                client_id = $clientId
            AND
                YEAR(completed_at) = $year
            AND
                MONTH(completed_at) = $month";
        $client = Client::query()->find($clientId);
        $invoices = collect(DB::select($rawQueryAsString));
        $info = collect([
            'invoice_number' => "INV-$year-$month",
            'client' => $client,
            'invoices' => $invoices,
        ]);
        $pdf = Pdf::loadView('printable.client-invoice', compact('info'))
            ->setPaper('a4', 'landscape');

        $name = 'invoice-' . Str::kebab($client->name) . '-inv-' . $year . '-' . $month . '.pdf';
        return $pdf->download($name);
//        return view('printable.client-invoice', compact('info'));
    }
}

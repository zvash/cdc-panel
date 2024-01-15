@extends('printable.print-layout')

@section('content')
    <div class="container">
        <h2>Appraiser Monthly Invoice</h2>
        <br>
        <h3>Client: {{ $info['client']->name }}</h3>
        <h3>Invoice Number: {{ $info['invoice_number'] }}</h3>
        <br>

        <table class="table table-bordered full-width">
            <thead>
            <tr>
                <th>#</th>
                <th>File Number</th>
                <th>Appraiser</th>
                <th>Appraisal Type</th>
                <th>Address</th>
                <th>Completed At</th>
                <th>CDC Fee</th>
                <th>CDC Tax</th>
                <th>CDC Total</th>
                <th>Appraiser Fee</th>
                <th>Appraiser Tax</th>
                <th>Appraiser Total</th>
                <th>Reviewer</th>
                <th>Reviewer Fee</th>
                <th>Reviewer Tax</th>
                <th>Reviewer Total</th>
            </tr>
            </thead>
            <tbody>
            <?php $income = 0; ?>
            @foreach($info['invoices'] as $invoice)
                <?php $income += $invoice->cdc_fee_with_tax; ?>
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $invoice->reference_number }}</td>
                    <td>{{ $invoice->appraiser_name }}</td>
                    <td>{{ $invoice->appraisal_type_name }}</td>
                    <td>{{ $invoice->property_address }}</td>
                    <td>{{ $invoice->completed_at_date }}</td>
                    <td>${{ round($invoice->fee_quoted, 2) }}</td>
                    <td>{{ round($invoice->cdc_tax, 2) }}%</td>
                    <td>${{ round($invoice->cdc_fee_with_tax, 2) }}</td>
                    <td>${{ round($invoice->appraiser_fee, 2) }}</td>
                    <td>{{ round($invoice->appraiser_tax, 2) }}%</td>
                    <td>${{ round($invoice->appraiser_fee_with_tax, 2) }}</td>
                    <td>{{ $invoice->reviewer_name }}</td>
                    <td>${{ round($invoice->reviewer_fee, 2) }}</td>
                    <td>{{ round($invoice->reviewer_tax, 2) }}%</td>
                    <td>${{ round($invoice->reviewer_fee_with_tax, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="16" class="text-right"><strong>Total Income: ${{ round($income, 2) }}</strong></td>
            </tbody>
        </table>
    </div>
@endsection

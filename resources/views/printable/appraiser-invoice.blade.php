@extends('printable.print-layout')

@section('content')
    <div class="container">
        <table class="header-table">
            <tr>
                <td>
                    <h2>Appraiser Monthly Invoice</h2>
                    <h3>{{ date('Y-m-d') }}</h3>
                    <h3>{{ $info['appraiser']->name }}</h3>
                    <h3>Invoice Number: {{ $info['invoice_number'] }}</h3>
                    <br>
                </td>
                <td>
                    <h2>Bill to:</h2>
                    <h3>CDC Inc.</h3>
                    <h3>193 Athabascan Avenue</h3>
                    <h3>Sherwood Park, AB T8A 4C8</h3>
                    <h3><b>Our GST# 878012319RT 0001</b></h3>
                </td>
            </tr>
        </table>

        <br/>

        <table class="table table-bordered full-width">
            <thead>
            <tr>
                <th>#</th>
                <th>Source</th>
                <th>File Number</th>
                <th>Appraisal Type</th>
                <th>Address</th>
                <th>Completed At</th>
                <th>CDC Fee</th>
                <th>CDC GST</th>
                <th>CDC Total</th>
                <th>Appraiser Fee</th>
                <th>Appraiser GST</th>
                <th>Appraiser Total</th>
            </tr>
            </thead>
            <tbody>
            <?php $income = 0; ?>
            @foreach($info['invoices'] as $invoice)
                    <?php $income += round($invoice->user_type == 'Appraiser' ? $invoice->appraiser_fee_with_tax : $invoice->reviewer_fee_with_tax, 2); ?>
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $invoice->client_name }}</td>
                    <td>{{ $invoice->reference_number }}</td>
                    <td>{{ $invoice->appraisal_type_name }}</td>
                    <td>{{ $invoice->property_address }}</td>
                    <td>{{ $invoice->completed_at_date }}</td>
                    <td>${{ round($invoice->fee_quoted, 2) }}</td>
                    <td>{{ round($invoice->cdc_tax, 2) }}%</td>
                    <td>${{ round($invoice->cdc_fee_with_tax, 2) }}</td>
                    <td>
                        ${{ $invoice->user_type == 'Appraiser' ? round($invoice->appraiser_fee, 2) : round($invoice->reviewer_fee, 2) }}</td>
                    <td>{{ $invoice->user_type == 'Appraiser' ? round($invoice->appraiser_tax, 2) : round($invoice->reviewer_tax, 2) }}
                        %
                    </td>
                    <td>
                        ${{ $invoice->user_type == 'Appraiser' ? round($invoice->appraiser_fee_with_tax, 2) : round($invoice->reviewer_fee_with_tax, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="12" class="text-right"><strong>Total Income: ${{ round($income, 2) }}</strong></td>
            </tbody>
        </table>
    </div>
@endsection

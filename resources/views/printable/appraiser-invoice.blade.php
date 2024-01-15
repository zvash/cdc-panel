@extends('printable.print-layout')

@section('content')
    <div class="container">
        <h2>Appraiser Monthly Invoice</h2>
        <br>
        <h3>Appraiser: {{ $info['appraiser']->name }}</h3>
        <h3>Invoice Number: {{ $info['invoice_number'] }}</h3>
        <br>

        <table class="table table-bordered full-width">
            <thead>
            <tr>
                <th>#</th>
                <th>CDC Fee</th>
                <th>CDC Tax</th>
                <th>CDC Total</th>
                <th>Appraiser Fee</th>
                <th>Appraiser Tax</th>
                <th>Appraiser Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach($info['invoices'] as $invoice)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>${{ round($invoice->fee_quoted, 2) }}</td>
                    <td>{{ round($invoice->cdc_tax, 2) }}%</td>
                    <td>${{ round($invoice->cdc_fee_with_tax, 2) }}</td>
                    <td>${{ round($invoice->appraiser_fee, 2) }}</td>
                    <td>{{ round($invoice->appraiser_tax, 2) }}%</td>
                    <td>${{ round($invoice->appraiser_fee_with_tax, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection

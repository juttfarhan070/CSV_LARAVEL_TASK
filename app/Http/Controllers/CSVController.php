<?php

namespace App\Http\Controllers;
use League\Csv\Reader;
use League\Csv\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
class CSVController extends Controller
{

    public function index()
    {
        return view('csv');
    }

    public function upload(Request $request)
    {
        $csvFile = $request->file('csv_file');
        $filePath = $csvFile->getRealPath();

        // Read the CSV file
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);

        $records = LazyCollection::make(static function () use ($csv) {
            yield from $csv->getRecords();
        })->toArray();



        $processedData = [];
        // Modify the data
        foreach ($records as $record) {
            $email = $record['Email_address'];

            $orderDate = strtotime($record['Order_Date']);

            // Check if the customer email already exists in the processed data
            if (isset($processedData[$email])) {
                // Update the last order date for the customer
                $processedData[$email]['last_order_date'] = max($processedData[$email]['last_order_date'], $orderDate);

                // Increment the total number of orders and product quantities for the customer
                $processedData[$email]['total_orders']++;
                $processedData[$email]['total_product_qty'] += $record['product_qty'];
            } else {
                // Add a new entry for the customer in the processed data
                $processedData[$email] = [
                    'first_order_date' => $orderDate,
                    'last_order_date' => $orderDate,
                    'total_orders' => 1,
                    'total_product_qty' => $record['product_qty'],
                ];
            }
        }

        foreach ($processedData as &$data) {
            $data['days_difference'] = floor(($data['last_order_date'] - $data['first_order_date']) / (60 * 60 * 24));
        }

        // Define the headers for the new CSV file
        $headers = [
            'Customer Email',
            'First Order Date',
            'Last Order Date',
            'Days Difference',
            'Total Number of Orders',
            'Total Number of Product Quantities',
        ];

        // Create a new CSV file and write the headers
        $newCsvPath = storage_path('app/public/new_csv_file.csv');
        $csv = Writer::createFromPath($newCsvPath, 'w+');
        $csv->insertOne($headers);

        // Write the processed data to the new CSV file
        foreach ($processedData as $email => $data) {
            $csv->insertOne([
                $email,
                date('Y-m-d H:i', $data['first_order_date']),
                date('Y-m-d H:i', $data['last_order_date']),
                $data['days_difference'],
                $data['total_orders'],
                $data['total_product_qty'],
            ]);
        }


        // $csv->output('new.csv');

        return redirect('/csv/download');
    }

    public function download()
    {
        $filePath = storage_path('app/public/new_csv_file.csv');

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

}

<?php

declare(strict_types=1);

use App\Models\Booking;

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/models/Booking.php';

app_start_session();

$currentUser = app_current_user();
if ($currentUser === null || $currentUser['role'] !== 'passenger') {
    http_response_code(403);
    exit('A passenger account is required to download this reservation.');
}

$referenceValue = $_GET['ref'] ?? null;
$reference = is_string($referenceValue) ? $referenceValue : '';
try {
    $pdo = require __DIR__ . '/config/db.php';
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('The database connection did not return PDO.');
    }

    $ticket = (new Booking($pdo))->findTicketForPassenger($reference, $currentUser['id']);
} catch (PDOException | RuntimeException $exception) {
    error_log('Passenger PDF ticket lookup failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Reservation details are temporarily unavailable.');
}

if ($ticket === null) {
    http_response_code(404);
    exit('This reservation could not be found.');
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    error_log('Passenger PDF ticket unavailable: Composer dependencies are not installed.');
    http_response_code(503);
    exit('PDF tickets are temporarily unavailable. Please contact support.');
}
require_once $autoload;

if (!class_exists(\FPDF::class)) {
    error_log('Passenger PDF ticket unavailable: FPDF class could not be loaded.');
    http_response_code(503);
    exit('PDF tickets are temporarily unavailable. Please contact support.');
}

$pdfText = static function (string $value): string {
    $value = str_replace('₦', 'NGN ', $value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';
    $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);

    if (is_string($encoded)) {
        return $encoded;
    }

    $fallback = preg_replace('/[^\x20-\x7E]/', '?', $value);

    return is_string($fallback) ? $fallback : '';
};

try {
    $pdf = new \FPDF('P', 'mm', 'A4');
    $pdf->SetTitle($pdfText('Reservation ' . $ticket['booking_reference']));
    $pdf->SetAuthor($pdfText('Airline Reservation'));
    $pdf->SetMargins(18, 18, 18);
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->AddPage();

    $pdf->SetTextColor(23, 24, 32);
    $pdf->SetFont('Arial', 'B', 19);
    $pdf->Cell(0, 11, $pdfText('Reservation ticket'), 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(88, 89, 101);
    $pdf->Cell(0, 7, $pdfText('PENDING RESERVATION - NO PAYMENT COLLECTED'), 0, 1);
    $pdf->Ln(5);

    $pdf->SetFillColor(243, 241, 255);
    $pdf->SetDrawColor(182, 172, 239);
    $pdf->SetTextColor(68, 51, 173);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 9, $pdfText('BOOKING REFERENCE  ' . $ticket['booking_reference']), 1, 1, 'L', true);
    $pdf->Ln(5);

    $pdf->SetTextColor(23, 24, 32);
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, $pdfText('Flight itinerary'), 0, 1);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, $pdfText('Flight ' . $ticket['flight']['flight_number']), 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(
        0,
        7,
        $pdfText(
            $ticket['flight']['origin_iata_code'] . ' - ' . $ticket['flight']['origin_city']
            . '  to  '
            . $ticket['flight']['destination_iata_code'] . ' - ' . $ticket['flight']['destination_city']
        ),
        0,
        1
    );
    $pdf->Cell(
        0,
        7,
        $pdfText(
            'Departure: ' . (new DateTimeImmutable($ticket['flight']['departure_at']))->format('D, d M Y H:i')
            . '    Arrival: ' . (new DateTimeImmutable($ticket['flight']['arrival_at']))->format('D, d M Y H:i')
        ),
        0,
        1
    );
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, $pdfText('Passengers and seats'), 0, 1);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(245, 245, 248);
    $pdf->Cell(128, 8, $pdfText('Passenger'), 1, 0, 'L', true);
    $pdf->Cell(46, 8, $pdfText('Seat'), 1, 1, 'L', true);
    $pdf->SetFont('Arial', '', 10);
    foreach ($ticket['travelers'] as $traveler) {
        $pdf->Cell(
            128,
            8,
            $pdfText($traveler['first_name'] . ' ' . $traveler['last_name']),
            1,
            0,
            'L'
        );
        $pdf->Cell(46, 8, $pdfText($traveler['seat_label'] ?? 'Not assigned'), 1, 1, 'L');
    }
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, $pdfText('Fare summary'), 0, 1);
    $pdf->SetFont('Arial', '', 10);
    if ($ticket['ticket_price'] === null) {
        $pdf->MultiCell(0, 7, $pdfText('Fare not recorded for this legacy reservation.'));
    } else {
        $totalFare = (float) $ticket['ticket_price'] * count($ticket['travelers']);
        $pdf->Cell(
            0,
            7,
            $pdfText(
                'NGN ' . number_format($ticket['ticket_price'], 2, '.', ',')
                . ' per passenger; total NGN ' . number_format($totalFare, 2, '.', ',')
            ),
            0,
            1
        );
    }

    $pdf->Ln(7);
    $pdf->SetDrawColor(210, 210, 218);
    $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(88, 89, 101);
    $pdf->MultiCell(
        0,
        6,
        $pdfText(
            'This document records a ' . $ticket['status']
            . ' reservation. No payment was collected. It is not a paid or confirmed ticket and is not a boarding pass.'
        )
    );

    $content = $pdf->Output('S');
    if (!is_string($content) || !str_starts_with($content, '%PDF-')) {
        throw new RuntimeException('PDF generation did not return a valid document.');
    }
} catch (Throwable $exception) {
    error_log('Passenger PDF ticket generation failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('We could not generate this PDF ticket. Please try again later.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="reservation-' . $ticket['booking_reference'] . '.pdf"');
header('Content-Length: ' . strlen($content));
header('X-Content-Type-Options: nosniff');
echo $content;

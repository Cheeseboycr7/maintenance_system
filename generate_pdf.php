<?php
require_once 'auth.php';
redirectIfNotLoggedIn();

require_once 'tcpdf.php'; // You'll need to install TCPDF library

$database = new Database();
$db = $database->getConnection();

// Create new PDF document
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);


// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Maintenance Management System');
$pdf->SetTitle('Maintenance Report');
$pdf->SetSubject('Maintenance Report');

// Add a page
$pdf->AddPage();

// Set content
$html = '<h1>Maintenance Report</h1>';

// Get report data based on parameters
if (isset($_GET['report'])) {
    switch ($_GET['report']) {
        case 'summary':
            $html .= '<h2>Maintenance Summary</h2>';
            
            // Get summary data
            $query = "
                SELECT 
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks,
                    AVG(progress) as avg_progress
                FROM tasks
            ";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $html .= '<table border="1" cellpadding="5">';
            $html .= '<tr><th>Metric</th><th>Value</th></tr>';
            $html .= '<tr><td>Total Tasks</td><td>' . $summary['total_tasks'] . '</td></tr>';
            $html .= '<tr><td>Completed Tasks</td><td>' . $summary['completed_tasks'] . '</td></tr>';
            $html .= '<tr><td>In Progress Tasks</td><td>' . $summary['in_progress_tasks'] . '</td></tr>';
            $html .= '<tr><td>Pending Tasks</td><td>' . $summary['pending_tasks'] . '</td></tr>';
            $html .= '<tr><td>Average Progress</td><td>' . round($summary['avg_progress'], 2) . '%</td></tr>';
            $html .= '</table>';
            break;
            
        // Add more report types as needed
    }
}

// Output HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output('maintenance_report.pdf', 'I');
?>